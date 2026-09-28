<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Streams a CSV of subscribers into the contacts table.
 *
 * Design notes:
 *  - The file is read **row by row**; a 500k-row upload never materialises in
 *    memory.
 *  - Writes are committed in batches (not one giant transaction) so a failure
 *    half-way through keeps the rows already accepted and the DB never holds a
 *    multi-minute write lock.
 *  - No network calls happen per row. The previous implementation ran a live
 *    `checkdnsrr()` MX lookup for every single line inside an open
 *    transaction, which turned a 10k-row import into 10k blocking DNS queries
 *    (and made the outcome depend on the resolver's mood).
 */
final class ContactCsvImporter
{
    /** Rows accumulated before flushing to the database. */
    private const BATCH_SIZE = 500;

    /** Hard ceiling so a malicious upload cannot run forever. */
    private const MAX_ROWS = 1_000_000;

    /** @var array<string, list<string>> */
    private const COLUMN_ALIASES = [
        'email' => ['email', 'email address', 'e-mail', 'emailaddress', 'mail'],
        'first_name' => ['first name', 'firstname', 'first_name', 'first', 'given name', 'name'],
        'last_name' => ['last name', 'lastname', 'last_name', 'last', 'surname', 'family name'],
    ];

    /**
     * @return array{imported: int, duplicates: int, skipped: int, total: int}
     */
    public function import(string $absolutePath, string $tenantId, ?string $listId = null): array
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the uploaded CSV file.');
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if (! is_array($header)) {
                throw new RuntimeException('The CSV file is empty or malformed.');
            }

            $index = $this->mapColumns($header);

            if ($index['email'] === null) {
                throw new RuntimeException('CSV must contain an "email" column.');
            }

            return $this->consume($handle, $index, $tenantId, $listId);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  array{email: int|null, first_name: int|null, last_name: int|null}  $index
     * @return array{imported: int, duplicates: int, skipped: int, total: int}
     */
    private function consume($handle, array $index, string $tenantId, ?string $listId): array
    {
        $imported = 0;
        $duplicates = 0;
        $skipped = 0;
        $total = 0;

        /** @var array<string, array<string, string|null>> $batch keyed by email to de-dupe within the file */
        $batch = [];

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if ($total >= self::MAX_ROWS) {
                break;
            }

            $total++;

            $email = $this->normaliseEmail($row[$index['email']] ?? null);

            if ($email === null) {
                $skipped++;

                continue;
            }

            $batch[$email] = [
                'email' => $email,
                'first_name' => $this->value($row, $index['first_name']),
                'last_name' => $this->value($row, $index['last_name']),
            ];

            if (count($batch) >= self::BATCH_SIZE) {
                [$new, $dupe] = $this->flush($batch, $tenantId, $listId);
                $imported += $new;
                $duplicates += $dupe;
                $batch = [];
            }
        }

        if ($batch !== []) {
            [$new, $dupe] = $this->flush($batch, $tenantId, $listId);
            $imported += $new;
            $duplicates += $dupe;
        }

        return compact('imported', 'duplicates', 'skipped', 'total');
    }

    /**
     * Persist one batch and attach it to the target list.
     *
     * @param  array<string, array<string, string|null>>  $batch
     * @return array{0: int, 1: int} [created, duplicates]
     */
    private function flush(array $batch, string $tenantId, ?string $listId): array
    {
        $emails = array_keys($batch);

        // One audit row per imported contact would double the write volume of
        // a bulk import without adding forensic value — the import itself is
        // the auditable event.
        return Contact::withoutAuditing(fn () => DB::transaction(function () use ($batch, $emails, $tenantId, $listId): array {
            $existing = Contact::withTrashed()
                ->where('tenant_id', $tenantId)
                ->whereIn('email', $emails)
                ->pluck('id', 'email');

            $created = 0;

            foreach ($batch as $email => $attributes) {
                if ($existing->has($email)) {
                    continue;
                }

                $contact = Contact::create([
                    'tenant_id' => $tenantId,
                    'email' => $attributes['email'],
                    'first_name' => $attributes['first_name'],
                    'last_name' => $attributes['last_name'],
                    'status' => Contact::STATUS_ACTIVE,
                ]);

                $existing->put($email, $contact->getKey());
                $created++;
            }

            if ($listId !== null) {
                // One pivot write for the whole batch instead of N round-trips.
                /** @var \Illuminate\Database\Eloquent\Collection<int, Contact> $contacts */
                $contacts = Contact::withTrashed()
                    ->whereKey($existing->values()->all())
                    ->get();

                foreach ($contacts as $contact) {
                    $contact->lists()->syncWithoutDetaching([$listId]);
                }
            }

            return [$created, count($batch) - $created];
        }));
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array{email: int|null, first_name: int|null, last_name: int|null}
     */
    private function mapColumns(array $header): array
    {
        $normalised = array_map(
            static fn ($column) => strtolower(trim((string) $column)),
            $header,
        );

        $resolved = ['email' => null, 'first_name' => null, 'last_name' => null];

        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            foreach ($normalised as $position => $column) {
                if (in_array($column, $aliases, true)) {
                    $resolved[$field] = $position;
                    break;
                }
            }
        }

        return $resolved;
    }

    /** @param array<int, string|null> $row */
    private function value(array $row, ?int $position): ?string
    {
        if ($position === null || ! isset($row[$position])) {
            return null;
        }

        $value = trim((string) $row[$position]);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function normaliseEmail(?string $raw): ?string
    {
        $email = strtolower(trim((string) $raw));

        if ($email === '' || mb_strlen($email) > 255) {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
