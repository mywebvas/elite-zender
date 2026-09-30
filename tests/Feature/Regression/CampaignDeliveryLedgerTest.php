<?php

use App\Console\Commands\FinaliseCampaigns;
use App\Jobs\SendCampaignChunkJob;
use App\Mail\CampaignEmail;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Plan;
use App\Models\SmtpAccount;
use App\Models\Subscription;
use App\Services\SpinSyntaxService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * The campaign ledger — how many recipients were sent, failed, skipped — used
 * to be unclosable, and the two escape hatches around it were worse than the
 * hole itself.
 *
 *  1. `elitesender:finalise-campaigns` only completed a campaign when
 *     `sent_count >= recipients_count`. A single suppressed or unsubscribed
 *     address made that unreachable, so the campaign showed "Sending" forever.
 *  2. When the relay pool ran out of daily quota mid-chunk the job called
 *     `release()`, which replays the *entire* chunk. Every recipient already
 *     delivered got the campaign a second time.
 *  3. Nothing stopped the workers at the plan's monthly allowance, so a large
 *     list happily sent — and billed — past what the customer had bought.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();

    $this->relay = SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'status' => SmtpAccount::STATUS_ACTIVE,
        'daily_limit' => 1000,
        'sent_today' => 0,
    ]);
});

function chunkCampaign(string $tenantId, int $recipients): Campaign
{
    return Campaign::factory()->create([
        'tenant_id' => $tenantId,
        'status' => Campaign::STATUS_SENDING,
        'subject' => 'Ledger',
        'body_html' => '<p>Hello [Name]</p>',
        'recipients_count' => $recipients,
    ]);
}

it('counts a recipient who became unmailable after fan-out as skipped', function (): void {
    Mail::fake();

    $campaign = chunkCampaign($this->user->tenant_id, 2);

    $active = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $goneQuiet = Contact::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'status' => Contact::STATUS_UNSUBSCRIBED,
    ]);

    (new SendCampaignChunkJob($campaign, [$active->id, $goneQuiet->id]))->handle(new SpinSyntaxService);

    $campaign->refresh();

    expect($campaign->sent_count)->toBe(1)
        ->and($campaign->skipped_count)->toBe(1)
        ->and($campaign->sent_count + $campaign->failed_count + $campaign->skipped_count)
        ->toBe($campaign->recipients_count);
});

it('finalises a campaign whose remaining recipients were skipped', function (): void {
    $campaign = chunkCampaign($this->user->tenant_id, 10);
    $campaign->forceFill(['sent_count' => 7, 'failed_count' => 2, 'skipped_count' => 1])->save();

    $this->artisan(FinaliseCampaigns::class)->assertSuccessful();

    expect($campaign->fresh()->status)->toBe(Campaign::STATUS_COMPLETED)
        ->and($campaign->fresh()->completed_at)->not->toBeNull();
});

it('leaves a campaign sending while recipients are still unaccounted for', function (): void {
    $campaign = chunkCampaign($this->user->tenant_id, 10);
    $campaign->forceFill(['sent_count' => 4, 'failed_count' => 1, 'skipped_count' => 0])->save();

    $this->artisan(FinaliseCampaigns::class)->assertSuccessful();

    expect($campaign->fresh()->status)->toBe(Campaign::STATUS_SENDING);
});

it('requeues only the untouched recipients when relay quota runs out mid-chunk', function (): void {
    Mail::fake();
    Queue::fake();

    // One relay, enough quota for exactly two of the three recipients.
    $this->relay->forceFill(['daily_limit' => 2, 'sent_today' => 0])->save();

    $campaign = chunkCampaign($this->user->tenant_id, 3);

    $contacts = Contact::factory()->count(3)->create(['tenant_id' => $this->user->tenant_id]);

    (new SendCampaignChunkJob($campaign, $contacts->pluck('id')->all()))->handle(new SpinSyntaxService);

    Mail::assertSent(CampaignEmail::class, 2);

    // The follow-up job must carry the leftovers and nothing else — a replay
    // of the whole chunk would double-send the first two.
    Queue::assertPushed(SendCampaignChunkJob::class, function (SendCampaignChunkJob $job) use ($contacts): bool {
        return $job->contactIds === [(string) $contacts->last()->id];
    });

    expect($campaign->fresh()->sent_count)->toBe(2);
});

it('stops the workers at the plan monthly allowance instead of billing past it', function (): void {
    Mail::fake();

    $plan = Plan::factory()->create([
        'code' => 'capped',
        'limits' => ['emails_per_month' => 2, 'contacts' => null, 'smtp_accounts' => null, 'users' => null],
    ]);

    Subscription::withoutGlobalScopes()->create([
        'tenant_id' => $this->user->tenant_id,
        'plan_id' => $plan->id,
        'status' => Subscription::STATUS_ACTIVE,
        'currency' => 'USD',
        'amount' => 0,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    $campaign = chunkCampaign($this->user->tenant_id, 5);
    $contacts = Contact::factory()->count(5)->create(['tenant_id' => $this->user->tenant_id]);

    (new SendCampaignChunkJob($campaign, $contacts->pluck('id')->all()))->handle(new SpinSyntaxService);

    Mail::assertSent(CampaignEmail::class, 2);

    expect($campaign->fresh()->status)->toBe(Campaign::STATUS_PAUSED)
        ->and($campaign->fresh()->sent_count)->toBe(2);
});
