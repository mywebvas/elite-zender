<?php

use App\Jobs\SendCampaignChunkJob;
use App\Mail\CampaignEmail;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Models\SuppressionEntry;
use App\Services\SmtpPool;
use App\Services\SpinSyntaxService;
use Illuminate\Support\Facades\Mail;

/**
 * SmtpPool replaced a naive `$i % count` rotation that ignored daily_limit,
 * health_score and status — the fastest route to a blocklisted sending domain.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
});

it('never hands out a relay that is over its daily cap', function (): void {
    $exhausted = SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'daily_limit' => 10,
        'sent_today' => 10,
    ]);
    $fresh = SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'daily_limit' => 10,
        'sent_today' => 0,
    ]);

    $pool = new SmtpPool(collect([$exhausted, $fresh]));

    expect($pool->count())->toBe(1)
        ->and($pool->next()?->id)->toBe($fresh->id);
});

it('skips paused relays', function (): void {
    $paused = SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'status' => SmtpAccount::STATUS_PAUSED,
    ]);

    expect((new SmtpPool(collect([$paused])))->isEmpty())->toBeTrue();
});

it('stops handing out relays once the pool quota is spent', function (): void {
    $only = SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'daily_limit' => 2,
        'sent_today' => 0,
    ]);

    $pool = new SmtpPool(collect([$only]));

    expect($pool->next())->not->toBeNull()
        ->and($pool->next())->not->toBeNull()
        ->and($pool->next())->toBeNull();
});

it('commits usage atomically so concurrent workers cannot overshoot', function (): void {
    $relay = SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'daily_limit' => 100,
        'sent_today' => 5,
    ]);

    $pool = new SmtpPool(collect([$relay]));
    $pool->next();
    $pool->next();
    $pool->next();
    $pool->commitUsage();

    expect($relay->fresh()->sent_today)->toBe(8);
});

it('favours healthier relays when health scores differ', function (): void {
    $healthy = SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'health_score' => 100,
        'daily_limit' => 1000,
    ]);
    $degraded = SmtpAccount::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'health_score' => 25,
        'daily_limit' => 1000,
    ]);

    $pool = new SmtpPool(collect([$healthy, $degraded]));

    $picks = collect(range(1, 10))->map(fn () => $pool->next()->id);

    expect($picks->filter(fn ($id) => $id === $healthy->id)->count())
        ->toBeGreaterThan($picks->filter(fn ($id) => $id === $degraded->id)->count());
});

it('records delivery counters and honours the suppression list', function (): void {
    Mail::fake();

    $smtp = SmtpAccount::factory()->create(['tenant_id' => $this->user->tenant_id]);

    $campaign = Campaign::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'status' => Campaign::STATUS_SENDING,
    ]);
    $campaign->smtpAccounts()->attach($smtp->id);

    $ok = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $burnt = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $optedOut = Contact::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'status' => Contact::STATUS_UNSUBSCRIBED,
    ]);

    SuppressionEntry::suppress($burnt->email, SuppressionEntry::REASON_HARD_BOUNCE, $this->user->tenant_id);

    (new SendCampaignChunkJob($campaign, [$ok->id, $burnt->id, $optedOut->id]))
        ->handle(new SpinSyntaxService);

    Mail::assertSent(CampaignEmail::class, 1);

    expect($campaign->fresh()->sent_count)->toBe(1)
        ->and($smtp->fresh()->sent_today)->toBe(1);
});

it('does not leave per-tenant SMTP credentials in the shared config', function (): void {
    Mail::fake();

    $smtp = SmtpAccount::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $campaign = Campaign::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $campaign->smtpAccounts()->attach($smtp->id);
    $contact = Contact::factory()->create(['tenant_id' => $this->user->tenant_id]);

    (new SendCampaignChunkJob($campaign, [$contact->id]))->handle(new SpinSyntaxService);

    // Under Octane the config repository outlives the job; a leftover mailer
    // would expose one tenant's relay credentials to the next job.
    expect(array_keys(config('mail.mailers')))->not->toContain('smtp_'.$smtp->id);
});
