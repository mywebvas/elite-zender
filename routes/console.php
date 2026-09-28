<?php

use App\Console\Commands\FinaliseCampaigns;
use App\Console\Commands\PurgeAuditLogs;
use App\Console\Commands\ResetDailySmtpQuotas;
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

Schedule::command(FinaliseCampaigns::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(ScanBounces::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(PurgeAuditLogs::class)
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();

// Keep the failed_jobs table bounded; anything older has been triaged or lost.
Schedule::command('queue:prune-failed', ['--hours' => 720])->daily();
Schedule::command('queue:prune-batches', ['--hours' => 720])->daily();
