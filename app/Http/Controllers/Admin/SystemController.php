<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Platform\ActivityLogger;
use App\Platform\HealthCheck;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Operational health and the failed-job queue.
 *
 * `failed_jobs` has existed since day one with no way to look at it. In a
 * sending platform a failed job is usually somebody's campaign that silently
 * stopped — noticing it an hour later rather than a week later is the whole
 * value of this page.
 */
class SystemController extends Controller
{
    public function __construct(
        private readonly HealthCheck $health,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(): View
    {
        return view('admin.system.index', [
            'checks' => $this->health->run(),
            'queues' => $this->health->queueDepths(),
            'failed' => DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(50)
                ->get()
                ->map(fn ($job) => (object) [
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'name' => $this->jobName($job->payload),
                    'exception' => $this->firstLine($job->exception),
                    'failed_at' => $job->failed_at,
                ]),
            'failedCount' => DB::table('failed_jobs')->count(),
            'scheduled' => $this->health->scheduledCommands(),
        ]);
    }

    public function retry(Request $request): RedirectResponse
    {
        $uuid = (string) $request->input('uuid', 'all');

        Artisan::call('queue:retry', [$uuid === 'all' ? 'id' : 'id' => $uuid === 'all' ? ['all'] : [$uuid]]);

        $this->activity->record(
            action: 'system.retry_jobs',
            description: $uuid === 'all' ? 'Retried every failed job' : "Retried failed job {$uuid}",
            severity: AdminActivity::SEVERITY_NOTICE,
        );

        return back()->with('success', $uuid === 'all' ? 'All failed jobs re-queued.' : 'Job re-queued.');
    }

    public function forget(Request $request): RedirectResponse
    {
        $uuid = (string) $request->input('uuid', '');

        if ($uuid === 'all') {
            $count = DB::table('failed_jobs')->count();
            DB::table('failed_jobs')->delete();
        } else {
            $count = DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        }

        $this->activity->record(
            action: 'system.discard_jobs',
            description: "Discarded {$count} failed job(s)",
            severity: AdminActivity::SEVERITY_NOTICE,
        );

        return back()->with('success', "Discarded {$count} failed job(s).");
    }

    /** Pull the job class out of the serialised payload for a readable list. */
    private function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);

        return class_basename($decoded['displayName'] ?? ($decoded['job'] ?? 'Unknown job'));
    }

    private function firstLine(string $exception): string
    {
        return mb_substr(strtok($exception, "\n") ?: $exception, 0, 200);
    }
}
