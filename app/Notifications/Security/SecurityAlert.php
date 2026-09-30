<?php

namespace App\Notifications\Security;

use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;
use Illuminate\Support\Facades\Request;

/**
 * "Something changed on your account."
 *
 * Not a courtesy — the primary detection control for account takeover.
 *
 * The attack it defeats is mundane and common: somebody gets a session (a
 * stolen cookie, an unlocked laptop, a shared machine), changes the email
 * address, then runs a password reset to the address they now control. Every
 * step of that is a legitimate action by an authenticated user, so nothing
 * else in the stack objects. The only thing that alerts the real owner is a
 * message to the address that is being taken away from them.
 *
 * Which is why an email change notifies the *old* address, and why none of
 * these can be switched off in preferences.
 */
class SecurityAlert extends LifecycleNotification
{
    /**
     * @param  list<string>  $detail  extra lines, already escaped
     */
    public function __construct(
        private readonly string $event,
        private readonly string $headline,
        private readonly array $detail = [],
        private readonly ?string $whenWrong = null,
    ) {
        parent::__construct();

        // Captured at construction: the queued job runs outside the request
        // that caused it, so there is no client IP to read by then.
        $this->context = [
            'ip' => Request::ip(),
            'agent' => \Illuminate\Support\Str::limit((string) Request::userAgent(), 80),
            'at' => now(),
        ];
    }

    /** @var array{ip: string|null, agent: string, at: \Carbon\CarbonInterface} */
    private array $context;

    /** Security notices are mandatory; `category()` stays 'billing'-grade. */
    public function category(): string
    {
        return 'security';
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $lines = array_merge([$this->headline], $this->detail);

        return new LifecycleContent(
            subject: 'Security alert: '.$this->event,
            heading: $this->event,
            greetingName: $this->firstName($notifiable),
            lines: $lines,
            eyebrow: 'Security',
            preheader: 'If this was not you, act now — instructions inside.',
            facts: array_filter([
                'When' => $this->context['at']->toDayDateTimeString().' UTC',
                'IP address' => $this->context['ip'],
                'Browser' => $this->context['agent'] !== '' ? $this->context['agent'] : null,
            ]),
            actionLabel: 'Review your account',
            actionUrl: route('settings.index'),
            outro: [
                $this->whenWrong ?? sprintf(
                    '<strong>If this was not you</strong>, reset your password immediately and then write to %s. Include the time above — it is what lets us find the session and kill it.',
                    e((string) config('platform.support_email')),
                ),
                'Security notices like this one cannot be turned off.',
            ],
            tone: 'danger',
        );
    }
}
