<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\ContactList;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchCampaignJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public Campaign $campaign;

    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign;
    }

    public function handle(): void
    {
        // Safety check using atomic DB update to prevent double-dispatch
        $updated = Campaign::withoutGlobalScopes()
            ->where('id', $this->campaign->id)
            ->where('status', 'draft')
            ->update(['status' => 'sending']);

        if (!$updated) {
            Log::warning("Campaign {$this->campaign->id} is not in draft status or already locked. Aborting dispatch.");
            return;
        }

        // Refresh campaign model to reflect status update
        $this->campaign->refresh();

        // Bind the tenant context for the duration of this job
        $tenant = $this->campaign->tenant;
        \App\Tenancy\TenantContext::set($tenant);

        $list = ContactList::withoutGlobalScopes()->find($this->campaign->list_id);
        
        if (!$list) {
            $this->campaign->update(['status' => 'paused']);
            \App\Tenancy\TenantContext::set(null);
            Log::error("Contact list not found for campaign {$this->campaign->id}");
            return;
        }

        // Chunk active contacts and dispatch
        $list->contacts()->withoutGlobalScopes()->where('status', 'active')->chunk(500, function ($contacts) {
            SendCampaignChunkJob::dispatch($this->campaign, $contacts->pluck('id')->toArray());
        });

        \App\Tenancy\TenantContext::set(null);
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('DispatchCampaignJob failed', [
            'campaign' => $this->campaign->id,
            'error'    => $e->getMessage(),
        ]);
        $this->campaign->update(['status' => 'paused']);
    }
}

