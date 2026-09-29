<?php

use App\Console\Commands\FinaliseCampaigns;
use App\Console\Commands\ProcessDataRequests;
use App\Console\Commands\PurgeAuditLogs;
use App\Console\Commands\ResetDailySmtpQuotas;
use App\Console\Commands\RunAutomations;
use App\Console\Commands\RunBillingCycle;
use App\Console\Commands\RunLifecycle;
use App\Console\Commands\ScanBounces;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
|
| Nothing was scheduled before this: daily SMTP quotas never reset (so every
| relay permanently hit its cap), campaigns never left "sending", bounces were
| never scanned and audit logs grew without bound.
|
| `withoutOverlapping` + `onOneServer` keep these safe when more than one
| scheduler container is running.
|
*/

Schedule::command(ResetDailySmtpQuotas::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Automations are the one job where lateness is visible to the recipient:
// a "welcome" email an hour after signup reads as broken.
Schedule::command(RunAutomations::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command(FinaliseCampaigns::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(ScanBounces::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// One daily pass, ordered: trials, renewals, dunning retries, lapses. Early
// enough that a failed charge still leaves a working day for support to help.
Schedule::command(RunBillingCycle::class)
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

// Lifecycle messages run *after* the billing cycle, hourly rather than
// daily. Hourly is not extra volume — every send is claimed in
// `lifecycle_messages` and can only happen once — it is latency: an
// abandoned checkout recovered within the hour converts far better than one
// chased tomorrow morning.
Schedule::command(RunLifecycle::class)
    ->hourlyAt(20)
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Erasure requests whose cooling-off window has closed, and exports that
// have aged out. Hourly rather than daily so "deleted on the 14th" means the
// 14th, not "some time on the 15th".
Schedule::command(ProcessDataRequests::class)
    ->hourlyAt(40)
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(PurgeAuditLogs::class)
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();

// Heartbeat for the operator health page. An empty queue looks identical
// whether workers are flying or dead; this is the only honest signal that the
// scheduler itself is alive.
Schedule::call(fn () => cache()->put('scheduler:heartbeat', now()->toIso8601String(), now()->addHours(6)))
    ->everyFiveMinutes()
    ->name('scheduler-heartbeat')
    ->withoutOverlapping();

// Keep the failed_jobs table bounded; anything older has been triaged or lost.
Schedule::command('queue:prune-failed', ['--hours' => 720])->daily();
Schedule::command('queue:prune-batches', ['--hours' => 720])->daily();
