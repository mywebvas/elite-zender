<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for every customer-lifecycle email.
 *
 * Queued on the `low` lane on purpose: a welcome email must never sit in
 * front of a campaign send. Retries are bounded — a lifecycle message is
 * time-sensitive, and one that finally lands four hours after the workspace
 * was suspended is worse than one that never arrives.
 */
abstract class LifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct()
    {
        $this->onQueue('low');
    }

    /** Build the message for a specific recipient. */
    abstract protected function content(User $notifiable): LifecycleContent;

    /**
     * Which preference category this message belongs to.
     *
     * Defaults to `billing`, which is mandatory. A message only becomes
     * optional by saying so — the safe direction for a default.
     */
    public function category(): string
    {
        return 'billing';
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $notifiable->wantsNotification($this->category())) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $content = $this->content($notifiable);

        return (new MailMessage)
            ->subject($content->subject)
            ->view('emails.lifecycle', ['content' => $content])
            ->text('emails.lifecycle-plain', ['content' => $content]);
    }

    /**
     * Surfaced in failed_jobs and in the operator console, so a support
     * question ("did they get the suspension warning?") has an answer.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var User $notifiable */
        return ['type' => static::class, 'subject' => $this->content($notifiable)->subject];
    }

    /** First name only — "Hi Ada Lovelace," reads like a mail merge. */
    protected function firstName(User $user): string
    {
        $first = trim(explode(' ', trim($user->name))[0] ?? '');

        return $first !== '' ? $first : 'there';
    }
}
