<?php

use App\Automations\StepRunner;
use App\Mail\CampaignEmail;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactAutomation;
use App\Models\SmtpAccount;
use Illuminate\Support\Facades\Mail;

/**
 * Automation mail was a second-class citizen of its own pipeline.
 *
 *  1. It rendered with `MergeTags::sampleData()`, whose whole job is to
 *     back-fill demo values. A subscriber with no first name was therefore
 *     greeted, in a real inbox, as "Ada" — and `[Email]` resolved to
 *     ada@example.com.
 *  2. It carried a List-Unsubscribe header but no visible opt-out link in the
 *     body, which is not what CAN-SPAM asks for and not what a webmail client
 *     necessarily renders.
 *  3. It injected neither the open pixel nor the click relay, so every
 *     journey-driven send was invisible in reporting.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();

    SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'status' => SmtpAccount::STATUS_ACTIVE,
        'from_email' => 'hello@acme.test',
        'from_name' => 'Acme',
    ]);

    $this->campaign = Campaign::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'subject' => 'Welcome [Name]',
        'editor_html' => '<p>Hi [Name], your address is [Email].</p><p><a href="https://example.com/start">Start here</a></p>',
    ]);

    $this->automation = Automation::factory()->create(['tenant_id' => $this->user->tenant_id]);

    $this->step = AutomationStep::create([
        'automation_id' => $this->automation->id,
        'type' => AutomationStep::TYPE_SEND_EMAIL,
        'config' => ['campaign_id' => $this->campaign->id],
        'order_index' => 0,
    ]);
});

function runFirstStepFor(Contact $contact): void
{
    $enrolment = ContactAutomation::create([
        'tenant_id' => $contact->tenant_id,
        'contact_id' => $contact->id,
        'automation_id' => test()->automation->id,
        'current_step_id' => test()->step->id,
        'status' => ContactAutomation::STATUS_RUNNING,
        'execute_next_at' => now(),
    ]);

    app(StepRunner::class)->run(test()->step, $enrolment);
}

it('never merges sample data into a real automation email', function (): void {
    Mail::fake();

    $contact = Contact::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'first_name' => null,
        'last_name' => null,
        'email' => 'real.person@example.org',
    ]);

    runFirstStepFor($contact);

    Mail::assertSent(CampaignEmail::class, function (CampaignEmail $mail): bool {
        return ! str_contains($mail->htmlBody, 'Ada')
            && ! str_contains($mail->htmlBody, 'ada@example.com')
            && ! str_contains($mail->campaignSubject, 'Ada')
            && str_contains($mail->htmlBody, 'real.person@example.org');
    });
});

it('uses the contact own details when it has them', function (): void {
    Mail::fake();

    $contact = Contact::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'first_name' => 'Grace',
        'email' => 'grace@example.org',
    ]);

    runFirstStepFor($contact);

    Mail::assertSent(CampaignEmail::class, fn (CampaignEmail $mail) => str_contains($mail->htmlBody, 'Hi Grace,')
        && $mail->campaignSubject === 'Welcome Grace');
});

it('carries a visible unsubscribe link, not only the header', function (): void {
    Mail::fake();

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    runFirstStepFor($contact);

    Mail::assertSent(CampaignEmail::class, fn (CampaignEmail $mail) => str_contains($mail->htmlBody, 'Unsubscribe here')
        && str_contains($mail->htmlBody, '/unsubscribe/'.$this->campaign->id.'/'.$contact->id)
        && $mail->unsubUrl !== null);
});

it('tracks opens and clicks the same way a broadcast does', function (): void {
    Mail::fake();

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    runFirstStepFor($contact);

    $pixel = route('tracking.open', ['campaign' => $this->campaign->id, 'contact' => $contact->id]);
    $click = route('tracking.click', [
        'campaign' => $this->campaign->id,
        'contact' => $contact->id,
        'url' => base64_encode('https://example.com/start'),
    ]);

    Mail::assertSent(CampaignEmail::class, fn (CampaignEmail $mail) => str_contains($mail->htmlBody, $pixel)
        && str_contains($mail->htmlBody, $click));
});

it('does not relay the unsubscribe link through the click tracker', function (): void {
    Mail::fake();

    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    runFirstStepFor($contact);

    Mail::assertSent(CampaignEmail::class, function (CampaignEmail $mail): bool {
        // The opt-out URL is signed; wrapping it in the relay would break the
        // signature and turn every unsubscribe into a 403.
        preg_match('/href="([^"]*unsubscribe[^"]*)"/', $mail->htmlBody, $m);

        return isset($m[1]) && ! str_contains($m[1], '/t/c/');
    });
});
