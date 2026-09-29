<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\SmtpAccount;
use Illuminate\Support\Facades\Auth;

/**
 * How far a new workspace has got.
 *
 * Every step is derived from real state rather than a stored flag, so the
 * checklist cannot claim something is done that is not, and cannot get stuck
 * because a flag was never written. It is also the definition of "activated"
 * for this product: a workspace that never connects a relay never sends, and
 * a workspace that never sends never renews.
 *
 * Shared between the dedicated onboarding page and the dashboard card so the
 * two can never disagree about what is left to do.
 */
final class ActivationChecklist
{
    /**
     * @return list<array{
     *     key: string, label: string, help: string, done: bool,
     *     url: string, cta: string
     * }>
     */
    public function steps(): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return [
            [
                'key' => 'verify',
                'label' => 'Confirm your email address',
                'help' => 'Proves you own the inbox. Required before the first send — it is what protects everyone\'s deliverability.',
                'done' => $user?->hasVerifiedEmail() ?? false,
                'url' => route('verification.notice'),
                'cta' => 'Confirm now',
            ],
            [
                'key' => 'smtp',
                'label' => 'Connect an SMTP relay',
                'help' => 'Your own sending infrastructure. Add more than one and we rotate across them by health.',
                'done' => SmtpAccount::query()->exists(),
                'url' => route('smtp-accounts.index'),
                'cta' => 'Add a relay',
            ],
            [
                'key' => 'contacts',
                'label' => 'Bring in your contacts',
                'help' => 'Import a CSV or drop in a lead-capture form. Duplicates and malformed rows are handled for you.',
                'done' => Contact::query()->exists(),
                'url' => route('contacts.index'),
                'cta' => 'Import contacts',
            ],
            [
                'key' => 'campaign',
                'label' => 'Build your first campaign',
                'help' => 'The composer previews exactly what will land, rendered by the same pipeline that sends it.',
                'done' => Campaign::query()->exists(),
                'url' => route('campaigns.create'),
                'cta' => 'Open the composer',
            ],
            [
                'key' => 'sent',
                'label' => 'Send it',
                'help' => 'Opens, clicks, bounces and unsubscribes start flowing back the moment it goes out.',
                'done' => Campaign::query()->where('sent_count', '>', 0)->exists(),
                'url' => route('campaigns.index'),
                'cta' => 'Review and send',
            ],
        ];
    }

    /**
     * @return array{steps: list<array<string, mixed>>, completed: int, total: int, percent: int, next: array<string, mixed>|null, complete: bool}
     */
    public function summary(): array
    {
        $steps = $this->steps();
        $completed = count(array_filter($steps, static fn (array $step): bool => $step['done']));
        $total = count($steps);

        // The first unfinished step, so the interface can point at one thing
        // instead of presenting a wall of five.
        $next = null;

        foreach ($steps as $step) {
            if (! $step['done']) {
                $next = $step;
                break;
            }
        }

        return [
            'steps' => $steps,
            'completed' => $completed,
            'total' => $total,
            'percent' => $total === 0 ? 100 : (int) round($completed / $total * 100),
            'next' => $next,
            'complete' => $completed === $total,
        ];
    }
}
