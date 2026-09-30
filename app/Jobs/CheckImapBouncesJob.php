<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\BounceProcessor;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Drains a tenant's bounce mailbox over IMAP and applies each DSN.
 *
 * ext-imap is optional (and absent from most modern PHP images), so the job
 * degrades loudly instead of pretending to work: the previous version only
 * wrote "Scanning for bounces..." to the log and returned, which looked
 * healthy on every dashboard while no bounce was ever processed.
 */
class CheckImapBouncesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    /** Messages examined per run — keeps one huge mailbox from starving others. */
    private const BATCH = 200;

    public function __construct(public string $tenantId)
    {
        $this->onQueue('low');
    }

    public function handle(BounceProcessor $processor): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $config = $tenant->setting('imap');

        if (! is_array($config) || empty($config['host']) || empty($config['username'])) {
            // Nothing configured for this workspace — not an error.
            return;
        }

        if (! function_exists('imap_open')) {
            Log::warning('CheckImapBouncesJob: ext-imap is not installed; bounce ingestion is disabled', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        TenantContext::run($tenant, fn () => $this->drain($processor, $config));
    }

    /** @param array<string, mixed> $config */
    private function drain(BounceProcessor $processor, array $config): void
    {
        $mailbox = sprintf(
            '{%s:%d/imap/%s}INBOX',
            $config['host'],
            (int) ($config['port'] ?? 993),
            ($config['encryption'] ?? 'ssl') === 'ssl' ? 'ssl' : 'notls',
        );

        $connection = @imap_open($mailbox, (string) $config['username'], $this->password($config));

        if ($connection === false) {
            Log::error('CheckImapBouncesJob: could not open mailbox', [
                'tenant_id' => $this->tenantId,
                'error' => imap_last_error(),
            ]);

            return;
        }

        try {
            $ids = imap_search($connection, 'UNSEEN SUBJECT "Undelivered"') ?: [];
            $ids = array_slice($ids, 0, self::BATCH);

            foreach ($ids as $id) {
                $raw = (string) imap_body($connection, $id);

                $processor->process($this->tenantId, $raw);

                imap_setflag_full($connection, (string) $id, '\\Seen');
            }

            Log::info('CheckImapBouncesJob: processed bounces', [
                'tenant_id' => $this->tenantId,
                'count' => count($ids),
            ]);
        } finally {
            imap_close($connection);
        }
    }

    /**
     * The mailbox password, decrypted.
     *
     * Stored encrypted by the settings form (it is a credential that reads
     * somebody's inbox). Falls back to the raw value so a config written
     * before encryption existed still connects instead of silently failing
     * every scan.
     *
     * @param  array<string, mixed>  $config
     */
    private function password(array $config): string
    {
        $stored = (string) ($config['password'] ?? '');

        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return $stored;
        }
    }
}
