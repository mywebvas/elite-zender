<?php

namespace App\Support;

/**
 * Sanitizes data for CSV exports to prevent CSV / Formula Injection (CWE-1236).
 *
 * Any cell value starting with '=', '+', '-', '@', tab, or carriage return
 * will be treated as executable formula syntax in spreadsheet software.
 * Prefixing with a single apostrophe forces the spreadsheet to interpret
 * the content strictly as text.
 */
final class CsvSanitizer
{
    /** Dangerous characters that can trigger formula execution in spreadsheet software. */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r", '%'];

    /**
     * @param  list<mixed>|array<string, mixed>  $row
     * @return list<mixed>
     */
    public static function sanitizeRow(array $row): array
    {
        return array_map(self::sanitizeValue(...), array_values($row));
    }

    public static function sanitizeValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        // If string starts with a formula trigger, escape with a single quote prefix
        if ($value !== '' && in_array($value[0], self::FORMULA_TRIGGERS, true)) {
            return "'".$value;
        }

        return $value;
    }
}
