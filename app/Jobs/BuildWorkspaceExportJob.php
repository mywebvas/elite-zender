<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\DataRequest;
use App\Models\SmtpAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Lifecycle\DataExportReady;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use stdClass;
use Throwable;
use ZipArchive;

/**
 * Builds a complete copy of a workspace's data (GDPR Art. 15).
 *
 * Streamed to CSV in chunks rather than serialised in memory: a workspace
 * with half a million contacts must not need half a million contacts' worth
 * of RAM to leave. On the `low` lane so an export can never delay a send.
 *
 * Deliberately excluded: SMTP passwords and payment credentials. An export
 * is a file that travels — through a mail server, a download folder, a
 * laptop — and a bearer credential against a customer's card or sending
 * domain has no business in one. The metadata is there so the customer can
 * see *what* was configured, just not the secret.
 */
class BuildWorkspaceExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public string $dataRequestId)
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $request = DataRequest::withoutGlobalScopes()->find($this->dataRequestId);

        if ($request === null || $request->status !== DataRequest::STATUS_PENDING) {
            return;
        }

        $tenant = Tenant::find($request->tenant_id);

        if ($tenant === null) {
            $request->forceFill(['status' => DataRequest::STATUS_FAILED])->save();

            return;
        }

        try {
            $path = TenantContext::run($tenant, fn (): string => $this->build($tenant, $request));

            $request->forceFill([
                'status' => DataRequest::STATUS_READY,
                'file_path' => $path,
                'file_size' => Storage::disk('local')->size($path),
                'expires_at' => now()->addDays(DataRequest::EXPORT_TTL_DAYS),
            ])->save();

            app(\App\Lifecycle\LifecycleMessenger::class)->sendOnce(
                $tenant,
                'data_export:'.$request->getKey(),
                fn () => new DataExportReady($request->refresh()),
            );
        } catch (Throwable $e) {
            $request->forceFill(['status' => DataRequest::STATUS_FAILED])->save();

            Log::error('Workspace export failed', [
                'data_request' => $request->getKey(),
                'tenant_id' => $tenant->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function build(Tenant $tenant, DataRequest $request): string
    {
        $disk = Storage::disk('local');
        $relative = sprintf('exports/%s/%s.zip', $tenant->getKey(), $request->getKey());

        $disk->makeDirectory(dirname($relative));

        $absolute = $disk->path($relative);

        $zip = new ZipArchive;

        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export archive.');
        }

        $zip->addFromString('README.txt', $this->readme($tenant));
        $zip->addFromString('workspace.json', (string) json_encode($this->workspace($tenant), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip->addFromString('contacts.csv', $this->csv(
            ['email', 'first_name', 'last_name', 'status', 'custom_fields', 'created_at'],
            Contact::withTrashed()->orderBy('id'),
            fn (Contact $c): array => [
                $c->email, $c->first_name, $c->last_name, $c->status,
                json_encode($c->custom_fields ?: new stdClass),
                $c->created_at?->toIso8601String(),
            ],
        ));

        $zip->addFromString('lists.csv', $this->csv(
            ['name', 'description', 'contacts', 'created_at'],
            ContactList::withCount('contacts')->orderBy('id'),
            fn (ContactList $l): array => [$l->name, $l->description, $l->contacts_count, $l->created_at?->toIso8601String()],
        ));

        $zip->addFromString('campaigns.csv', $this->csv(
            ['name', 'subject', 'status', 'recipients', 'sent', 'failed', 'skipped', 'created_at', 'completed_at'],
            Campaign::withTrashed()->orderBy('id'),
            fn (Campaign $c): array => [
                $c->name, $c->subject, $c->status, $c->recipients_count,
                $c->sent_count, $c->failed_count, $c->skipped_count,
                $c->created_at?->toIso8601String(), $c->completed_at?->toIso8601String(),
            ],
        ));

        $zip->addFromString('engagement.csv', $this->csv(
            ['campaign_id', 'contact_id', 'type', 'url', 'occurred_at'],
            CampaignEvent::query()->orderBy('id'),
            fn (CampaignEvent $e): array => [
                $e->campaign_id, $e->contact_id, $e->type, $e->url, $e->created_at?->toIso8601String(),
            ],
        ));

        $zip->addFromString('team.csv', $this->csv(
            ['name', 'email', 'role', 'email_verified_at', 'created_at'],
            User::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->orderBy('id'),
            fn (User $u): array => [
                $u->name, $u->email, $u->role,
                $u->email_verified_at?->toIso8601String(), $u->created_at?->toIso8601String(),
            ],
        ));

        // Configuration without credentials: enough to rebuild the setup
        // elsewhere, not enough to send as this customer.
        $zip->addFromString('smtp-relays.csv', $this->csv(
            ['name', 'host', 'port', 'encryption', 'from_email', 'from_name', 'daily_limit', 'status'],
            SmtpAccount::withTrashed()->orderBy('id'),
            fn (SmtpAccount $a): array => [
                $a->name, $a->host, $a->port, $a->encryption,
                $a->from_email, $a->from_name, $a->daily_limit, $a->status,
            ],
        ));

        $zip->close();

        return $relative;
    }

    /**
     * Stream a query to CSV in chunks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $query
     * @param  callable(mixed): list<mixed>  $row
     * @param  list<string>  $headers
     */
    private function csv(array $headers, $query, callable $row): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Could not open a temporary stream for the export.');
        }

        // Excel opens UTF-8 CSV as mojibake without a BOM, and a customer's
        // first impression of their own data should not be "Ã©".
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, escape: '');

        $query->chunk(1000, function ($records) use ($handle, $row): void {
            foreach ($records as $record) {
                fputcsv($handle, $row($record), escape: '');
            }
        });

        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /** @return array<string, mixed> */
    private function workspace(Tenant $tenant): array
    {
        $subscription = app(\App\Billing\PlanGate::class)->subscriptionFor($tenant);

        return [
            'workspace' => [
                'name' => $tenant->name,
                'created_at' => $tenant->created_at?->toIso8601String(),
                'timezone' => $tenant->timezone(),
            ],
            'subscription' => $subscription === null ? null : [
                'plan' => $subscription->planName(),
                'status' => $subscription->status,
                'currency' => $subscription->currency,
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            ],
            'exported_at' => now()->toIso8601String(),
        ];
    }

    private function readme(Tenant $tenant): string
    {
        return implode("\n", [
            'Data export — '.$tenant->name,
            'Generated '.now()->toDayDateTimeString().' UTC by '.config('platform.name'),
            '',
            'Files',
            '  workspace.json    Workspace and subscription summary',
            '  contacts.csv      Every contact, including deleted ones',
            '  lists.csv         Contact lists and their sizes',
            '  campaigns.csv     Campaigns and their delivery counters',
            '  engagement.csv    Opens, clicks, bounces, complaints, unsubscribes',
            '  team.csv          Workspace members and their roles',
            '  smtp-relays.csv   Relay configuration',
            '',
            'Deliberately not included',
            '  SMTP passwords and payment credentials. An export travels — through',
            '  a mail server, a downloads folder, a laptop — and a bearer credential',
            '  against your card or your sending domain has no business in one.',
            '',
            'CSV files are UTF-8 with a byte-order mark so they open correctly in Excel.',
            '',
            'Questions: '.config('platform.support_email'),
        ]);
    }
}
