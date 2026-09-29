<?php

namespace App\Notifications\Team;

use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/** Closes the loop for whoever sent the invitation. */
class TeamMemberJoined extends LifecycleNotification
{
    public function __construct(private readonly User $member)
    {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        return new LifecycleContent(
            subject: sprintf('%s has joined your workspace', $this->member->name),
            heading: 'Your invitation was accepted',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    '<strong>%s</strong> (%s) has joined as %s and can sign in now.',
                    e($this->member->name),
                    e($this->member->email),
                    $this->member->role,
                ),
                'You can change their role or remove their access at any time from the team page.',
            ],
            eyebrow: 'Team',
            preheader: sprintf('%s now has access.', $this->member->name),
            actionLabel: 'Manage the team',
            actionUrl: route('team.index'),
            tone: 'success',
        );
    }
}
