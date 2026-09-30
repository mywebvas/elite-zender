<?php

namespace App\Notifications\Lifecycle;

use App\Models\DataRequest;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/** The export is built and waiting behind the customer's own login. */
class DataExportReady extends LifecycleNotification
{
    public function __construct(private readonly DataRequest $request)
    {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        return new LifecycleContent(
            subject: 'Your data export is ready',
            heading: 'Your export is ready',
            greetingName: $this->firstName($notifiable),
            lines: [
                'Everything in your workspace — contacts, lists, campaigns, engagement history, team and relay configuration — is packaged and waiting.',
                sprintf(
                    'The download sits behind your login and is deleted automatically after %d days. We do not attach it to email: an archive of your entire contact list should not be sitting in an inbox.',
                    DataRequest::EXPORT_TTL_DAYS,
                ),
            ],
            eyebrow: 'Data export',
            preheader: 'Waiting behind your login. Deleted automatically after '.DataRequest::EXPORT_TTL_DAYS.' days.',
            facts: array_filter([
                'Size' => $this->request->file_size !== null
                    ? number_format($this->request->file_size / 1024, 0).' KB'
                    : null,
                'Available until' => $this->request->expires_at?->toFormattedDayDateString(),
            ]),
            actionLabel: 'Download your data',
            actionUrl: route('settings.index').'#data',
            tone: 'success',
        );
    }
}
