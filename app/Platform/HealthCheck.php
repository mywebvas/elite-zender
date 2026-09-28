<?php

namespace App\Platform;

use App\Models\Campaign;
use App\Models\ContactAutomation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Is the platform actually working?
 *
 * Each check answers a question an operator would otherwise answer by SSHing
 * in. They are deliberately cheap and individually guarded: a health page that
 * itself errors when Redis is down is useless precisely when it is needed.
 */
final class HealthCheck
{
    /**
     * @return list<array{name: string, status: string, detail: string}>
     */
    public function run(): array
    {
        return [
            $this->check('Database', function (): string {
                $start = microtime(true);
                DB::select('select 1');

                return round((microtime(true) - $start) * 1000).' ms · '.config('database.default');
            }),

            $this->check('Cache', function (): string {
                $probe = 'health:'.uniqid();
                Cache::put($probe, 'ok', 10);
                $value = Cache::get($probe);
                Cache::forget($probe);

                if ($value !== 'ok') {
                    throw new RuntimeException('Wrote a value but could not read it back.');
                }

                return 'Read/write OK · '.config('cache.default');
            }),

            $this->check('Storage', function (): string {
                $probe = 'health/'.uniqid().'.txt';
                Storage::disk('local')->put($probe, 'ok');
                Storage::disk('local')->delete($probe);

                return 'Writable';
            }),

            $this->check('Queue worker', function (): string {
                // A scheduler heartbeat is the only honest signal: an empty
                // queue looks identical whether workers are flying or dead.
                $heartbeat = Cache::get('scheduler:heartbeat');

                if ($heartbeat === null) {
                    throw new RuntimeException('No heartbeat recorded yet — is the scheduler running?');
                }

                $age = now()->diffInMinutes(\Illuminate\Support\Carbon::parse($heartbeat), absolute: true);

                if ($age > 15) {
                    throw new RuntimeException("Last heartbeat {$age} minutes ago — the scheduler looks stopped.");
                }

                return 'Last heartbeat '.\Illuminate\Support\Carbon::parse($heartbeat)->diffForHumans();
            }),

            $this->check('Failed jobs', function (): string {
                $count = DB::table('failed_jobs')->count();

                if ($count > 0) {
                    throw new RuntimeException("{$count} job(s) need attention.");
                }

                return 'None';
            }),

            $this->check('Stuck campaigns', function (): string {
                // Sending for over a day with recipients left is not "busy".
                $stuck = Campaign::withoutGlobalScopes()
                    ->where('status', Campaign::STATUS_SENDING)
                    ->where('started_at', '<', now()->subDay())
                    ->count();

                if ($stuck > 0) {
                    throw new RuntimeException("{$stuck} campaign(s) have been sending for over 24 hours.");
                }

                return 'None';
            }),

            $this->check('Paused automations', function (): string {
                $paused = ContactAutomation::withoutGlobalScopes()
                    ->where('status', ContactAutomation::STATUS_PAUSED)
                    ->count();

                if ($paused > 0) {
                    throw new RuntimeException("{$paused} enrolment(s) paused after a step failed.");
                }

                return 'None';
            }),
        ];
    }

    /** @return array<string, int> */
    public function queueDepths(): array
    {
        $depths = [];

        foreach (['high', 'default', 'low'] as $queue) {
            try {
                $depths[$queue] = config('queue.default') === 'database'
                    ? DB::table('jobs')->where('queue', $queue)->count()
                    : (int) app('queue')->connection()->size($queue);
            } catch (Throwable) {
                $depths[$queue] = -1; // unknown rather than a misleading zero
            }
        }

        return $depths;
    }

    /**
     * @return list<array{command: string, expression: string, next: string}>
     */
    public function scheduledCommands(): array
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = [];

        foreach ($schedule->events() as $event) {
            $events[] = [
                'command' => trim(str_replace([PHP_BINARY, "'artisan'", 'artisan'], '', (string) $event->command)),
                'expression' => $event->expression,
                'next' => $event->nextRunDate()->diffForHumans(),
            ];
        }

        return $events;
    }

    /**
     * @param  callable(): string  $probe
     * @return array{name: string, status: string, detail: string}
     */
    private function check(string $name, callable $probe): array
    {
        try {
            return ['name' => $name, 'status' => 'ok', 'detail' => $probe()];
        } catch (Throwable $e) {
            return ['name' => $name, 'status' => 'fail', 'detail' => $e->getMessage()];
        }
    }
}
