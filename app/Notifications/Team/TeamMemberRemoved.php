<?php

namespace App\Notifications\Team;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * Access removed.
 *
 * Sending this is not a courtesy, it is a security control: somebody who is
 * quietly removed assumes their session simply broke and files a ticket,
 * while somebody removed *without their knowledge* by an attacker who has
 * taken over an admin account has no signal at all.
 */
class TeamMemberRemoved extends LifecycleNotification
{
    public function __construct(private readonly Tenant $tenant)
    {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        return new LifecycleContent(
            subject: sprintf('Your access to %s has been removed', $this->tenant->name),
            heading: 'Your access has been removed',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    'An administrator has removed your access to the <strong>%s</strong> workspace. Any active sessions and API tokens have been revoked.',
                    e($this->tenant->name),
                ),
                'If this is unexpected, contact the workspace owner directly — we cannot restore access on their behalf.',
            ],
            eyebrow: 'Access',
            preheader: 'Your sessions and API tokens for this workspace have been revoked.',
            outro: [
                'This message is a security notice and cannot be turned off.',
            ],
            tone: 'warning',
        );
    }
}
