<?php

namespace App\Automations;

use App\Mail\CampaignEmail;
use App\Models\AutomationStep;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactAutomation;
use App\Models\SuppressionEntry;
use App\Models\Tag;
use App\Services\EmailHtmlRenderer;
use App\Services\SmtpPool;
use App\Services\SpinSyntaxService;
use App\Support\MergeTags;
use App\Support\SafeRedirect;
use App\Support\UnsubscribeLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Executes one automation step for one contact.
 *
 * Every executor is deliberately small and side-effect-explicit: this code runs
 * unattended, against real inboxes, for thousands of contacts, and a subtle bug
 * here is a subtle bug repeated at scale.
 */
final class StepRunner
{
    public function __construct(
        private readonly SpinSyntaxService $spintax,
        private readonly EmailHtmlRenderer $renderer,
    ) {}

    public function run(AutomationStep $step, ContactAutomation $enrolment): StepOutcome
    {
        $contact = Contact::withoutGlobalScopes()->find($enrolment->contact_id);

        if ($contact === null || $contact->status !== Contact::STATUS_ACTIVE) {
            // Someone who unsubscribed mid-journey drops out of it. Continuing
            // would mean mailing a person who explicitly asked us not to.
            return StepOutcome::stop();
        }

        $config = $step->config ?? [];

        return match ($step->type) {
            AutomationStep::TYPE_WAIT => $this->wait($config),
            AutomationStep::TYPE_SEND_EMAIL => $this->sendEmail($config, $contact),
            AutomationStep::TYPE_ADD_TAG => $this->addTag($config, $contact),
            AutomationStep::TYPE_REMOVE_TAG => $this->removeTag($config, $contact),
            AutomationStep::TYPE_UPDATE_FIELD => $this->updateField($config, $contact),
            AutomationStep::TYPE_WEBHOOK => $this->webhook($config, $contact),
            AutomationStep::TYPE_CONDITION => $this->condition($config, $contact, $step),
            default => StepOutcome::continue(),
        };
    }

    /** @param array<string, mixed> $config */
    private function wait(array $config): StepOutcome
    {
        $amount = max(1, (int) ($config['amount'] ?? 1));

        $until = match ($config['unit'] ?? 'days') {
            'minutes' => now()->addMinutes($amount),
            'hours' => now()->addHours($amount),
            default => now()->addDays($amount),
        };

        return StepOutcome::waitUntil($until);
    }

    /**
     * Send one campaign's content to one contact.
     *
     * Reuses the campaign as a template rather than duplicating the composer:
     * the same rendering, spin syntax, tracking and unsubscribe handling the
     * broadcast path uses, so an automation email is never second-class.
     *
     * @param  array<string, mixed>  $config
     */
    private function sendEmail(array $config, Contact $contact): StepOutcome
    {
        $campaignId = $config['campaign_id'] ?? null;

        if (! is_string($campaignId)) {
            return StepOutcome::continue();
        }

        $campaign = Campaign::withoutGlobalScopes()
            ->with('smtpAccounts')
            ->where('tenant_id', $contact->tenant_id)
            ->find($campaignId);

        if ($campaign === null) {
            throw new RuntimeException("Automation references a campaign that no longer exists [{$campaignId}].");
        }

        if (SuppressionEntry::suppresses($contact->email, (string) $contact->tenant_id)) {
            return StepOutcome::continue();
        }

        $pool = $campaign->smtpAccounts->isNotEmpty()
            ? new SmtpPool($campaign->smtpAccounts)
            : new SmtpPool(\App\Models\SmtpAccount::withoutGlobalScopes()
                ->where('tenant_id', $contact->tenant_id)
                ->where('status', \App\Models\SmtpAccount::STATUS_ACTIVE)
                ->get());

        $relay = $pool->next();

        if ($relay === null) {
            throw new RuntimeException('No SMTP relay with remaining quota is available.');
        }

        $data = MergeTags::sampleData($contact);
        $seed = crc32($campaign->getKey().'|'.$contact->getKey());

        $html = $this->renderer->render(
            $this->spintax->compile((string) ($campaign->editor_html ?: $campaign->body_html), $data, $seed),
            $this->spintax->compile((string) $campaign->preheader, $data, $seed),
        );

        $unsubUrl = UnsubscribeLink::for($campaign, $contact);
        $mailerKey = 'smtp_auto_'.$relay->getKey();

        config(["mail.mailers.{$mailerKey}" => [
            'transport' => 'smtp',
            'host' => $relay->host,
            'port' => $relay->port,
            'encryption' => $relay->encryption === 'none' ? null : $relay->encryption,
            'username' => $relay->username,
            'password' => $relay->password,
            'timeout' => 15,
        ]]);

        try {
            Mail::mailer($mailerKey)->send(
                (new CampaignEmail(
                    $this->spintax->compile($campaign->subject, $data, $seed),
                    $html,
                    $this->renderer->toPlainText($html),
                    $unsubUrl,
                ))->from($relay->from_email, $relay->from_name)->to($contact->email),
            );

            $pool->commitUsage();
            app(\App\Billing\PlanGate::class)->recordEmailsSent((string) $contact->tenant_id, 1);
        } finally {
            // Per-tenant credentials must not linger in the shared config
            // repository; under Octane the next job would inherit them.
            $mailers = config('mail.mailers', []);
            unset($mailers[$mailerKey]);
            config(['mail.mailers' => $mailers]);
            Mail::forgetMailers();
        }

        return StepOutcome::continue();
    }

    /** @param array<string, mixed> $config */
    private function addTag(array $config, Contact $contact): StepOutcome
    {
        $name = trim((string) ($config['tag_name'] ?? ''));

        if ($name === '') {
            return StepOutcome::continue();
        }

        $tag = Tag::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $contact->tenant_id, 'name' => $name],
        );

        $contact->tags()->syncWithoutDetaching([$tag->getKey()]);

        return StepOutcome::continue();
    }

    /** @param array<string, mixed> $config */
    private function removeTag(array $config, Contact $contact): StepOutcome
    {
        $name = trim((string) ($config['tag_name'] ?? ''));

        if ($name === '') {
            return StepOutcome::continue();
        }

        $tag = Tag::withoutGlobalScopes()
            ->where('tenant_id', $contact->tenant_id)
            ->where('name', $name)
            ->first();

        if ($tag !== null) {
            $contact->tags()->detach($tag->getKey());
        }

        return StepOutcome::continue();
    }

    /** @param array<string, mixed> $config */
    private function updateField(array $config, Contact $contact): StepOutcome
    {
        $field = (string) ($config['field'] ?? '');

        // Allow-listed: an automation must never be able to write to an
        // arbitrary column.
        if (! in_array($field, ['first_name', 'last_name', 'status'], true)) {
            return StepOutcome::continue();
        }

        $value = (string) ($config['value'] ?? '');

        if ($field === 'status' && ! in_array($value, Contact::STATUSES, true)) {
            return StepOutcome::continue();
        }

        $contact->forceFill([$field => $value])->save();

        return StepOutcome::continue();
    }

    /** @param array<string, mixed> $config */
    private function webhook(array $config, Contact $contact): StepOutcome
    {
        $url = (string) ($config['url'] ?? '');

        // Same allow-list the click relay uses: an automation must not become
        // an SSRF primitive pointed at the cloud metadata endpoint.
        if (! SafeRedirect::isAllowed($url)) {
            throw new RuntimeException("Automation webhook URL is not permitted [{$url}].");
        }

        $response = Http::timeout(10)
            ->withHeaders(['User-Agent' => 'EliteSender-Automations/1.0'])
            ->asJson()
            ->post($url, [
                'event' => 'automation.step',
                'contact' => [
                    'id' => $contact->getKey(),
                    'email' => $contact->email,
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'status' => $contact->status,
                ],
                'sent_at' => now()->toIso8601String(),
            ]);

        if ($response->failed()) {
            Log::warning('Automation webhook returned an error', [
                'url' => $url,
                'status' => $response->status(),
            ]);
        }

        // A customer endpoint being down must not pause the journey.
        return StepOutcome::continue();
    }

    /**
     * Branch.
     *
     * A false condition ends the journey rather than skipping one step —
     * "only continue if still tagged VIP" is the overwhelmingly common intent,
     * and silently continuing would mail the wrong people.
     *
     * @param  array<string, mixed>  $config
     */
    private function condition(array $config, Contact $contact, AutomationStep $step): StepOutcome
    {
        $value = trim((string) ($config['value'] ?? ''));

        $matches = match ($config['subject'] ?? 'tag') {
            'status' => $contact->status === $value,
            default => $contact->tags()->where('tags.name', $value)->exists(),
        };

        return $matches ? StepOutcome::continue() : StepOutcome::stop();
    }
}
