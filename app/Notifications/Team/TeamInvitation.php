<?php

namespace App\Notifications\Team;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LifecycleContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Come and join this workspace."
 *
 * Sent to an address with no account, so it cannot extend LifecycleNotification
 * (which expects a User). Built on the same content object and template, so it
 * looks like the rest of the product rather than like a different one.
 */
class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly Invitation $invitation,
        private readonly Tenant $tenant,
        private readonly User $inviter,
        private readonly string $token,
    ) {
        $this->onQueue('low');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $content = new LifecycleContent(
            subject: sprintf('%s invited you to %s on %s', $this->inviter->name, $this->tenant->name, config('platform.name')),
            heading: 'You have been invited',
            greetingName: 'there',
            lines: [
                sprintf(
                    '<strong>%s</strong> has invited you to join <strong>%s</strong> on %s as %s.',
                    e($this->inviter->name),
                    e($this->tenant->name),
                    config('platform.name'),
                    $this->article($this->invitation->role),
                ),
                'Accepting takes about thirty seconds — pick a password and you are in. There is nothing to install and no separate account to create.',
            ],
            eyebrow: 'Invitation',
            preheader: sprintf('Join %s — the link is good for %d days.', $this->tenant->name, Invitation::TTL_DAYS),
            facts: [
                'Workspace' => $this->tenant->name,
                'Your role' => ucfirst($this->invitation->role),
                'Link expires' => $this->invitation->expires_at->toFormattedDayDateString(),
            ],
            actionLabel: 'Accept the invitation',
            actionUrl: route('invitations.show', ['token' => $this->token]),
            outro: [
                sprintf(
                    'Not expecting this? You can ignore it safely — the link expires on its own in %d days and nothing happens until somebody uses it.',
                    Invitation::TTL_DAYS,
                ),
            ],
        );

        return (new MailMessage)
            ->subject($content->subject)
            ->view('emails.lifecycle', ['content' => $content])
            ->text('emails.lifecycle-plain', ['content' => $content]);
    }

    private function article(string $role): string
    {
        return (in_array($role[0] ?? '', ['a', 'e', 'i', 'o', 'u'], true) ? 'an ' : 'a ').$role;
    }
}
