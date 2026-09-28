<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\ContactList;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fans a campaign out into per-chunk send jobs.
 *
 * Implements ShouldBeUnique so a double-click on "Send" cannot enqueue the
 * campaign twice even before the atomic status flip below has committed.
 */
class DispatchCampaignJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /** Contacts per chunk job. */
    public const CHUNK_SIZE = 500;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    /** Uniqueness lock is released as soon as the fan-out finishes. */
    public int $uniqueFor = 3600;

    public function __construct(public Campaign $campaign) {}

    public function uniqueId(): string
    {
        return (string) $this->campaign->getKey();
    }

    public function handle(): void
    {
        // Atomic draft → queued transition. If another worker won the race the
        // update affects zero rows and we bail out without sending twice.
        $claimed = Campaign::withoutGlobalScopes()
            ->whereKey($this->campaign->getKey())
            ->where('status', Campaign::STATUS_DRAFT)
            ->update([
                'status' => Campaign::STATUS_SENDING,
                'started_at' => now(),
                'sent_count' => 0,
                'failed_count' => 0,
            ]);

        if ($claimed === 0) {
            Log::warning('DispatchCampaignJob: campaign already claimed', [
                'campaign' => $this->campaign->getKey(),
            ]);

            return;
        }

        $this->campaign->refresh();

        TenantContext::run($this->campaign->tenant, function (): void {
            $this->fanOut();
        });
    }

    private function fanOut(): void
    {
        $list = ContactList::withoutGlobalScopes()->find($this->campaign->list_id);

        if ($list === null) {
            $this->campaign->update(['status' => Campaign::STATUS_PAUSED]);

            Log::error('DispatchCampaignJob: contact list not found', [
                'campaign' => $this->campaign->getKey(),
                'list' => $this->campaign->list_id,
            ]);

            return;
        }

        $query = $list->contacts()
            ->withoutGlobalScopes()
            ->where('contacts.tenant_id', $this->campaign->tenant_id)
            ->where('contacts.status', Contact::STATUS_ACTIVE);

        // Smart retargeting: skip anyone who already opened the source
        // campaign. Previously this was a TODO that still told the operator
        // the exclusion had been applied.
        $excludeOpenersOf = $this->campaign->excludedOpenersCampaignId();

        if ($excludeOpenersOf !== null) {
            $query->whereNotIn('contacts.id', CampaignEvent::withoutGlobalScopes()
                ->where('campaign_id', $excludeOpenersOf)
                ->where('type', CampaignEvent::TYPE_OPEN)
                ->select('contact_id'));
        }

        $recipients = 0;

        $query->select('contacts.id')->chunkById(self::CHUNK_SIZE, function ($contacts) use (&$recipients): void {
            $recipients += $contacts->count();

            SendCampaignChunkJob::dispatch($this->campaign, $contacts->pluck('id')->all());
        }, 'contacts.id', 'id');

        Campaign::withoutGlobalScopes()
            ->whereKey($this->campaign->getKey())
            ->update(['recipients_count' => $recipients]);

        if ($recipients === 0) {
            Campaign::withoutGlobalScopes()
                ->whereKey($this->campaign->getKey())
                ->update(['status' => Campaign::STATUS_COMPLETED, 'completed_at' => now()]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::critical('DispatchCampaignJob failed', [
            'campaign' => $this->campaign->getKey(),
            'error' => $e->getMessage(),
        ]);

        Campaign::withoutGlobalScopes()
            ->whereKey($this->campaign->getKey())
            ->update(['status' => Campaign::STATUS_PAUSED]);
    }
}
