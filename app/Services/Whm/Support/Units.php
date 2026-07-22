<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm\Support;

/**
 * Helpers for normalising the inconsistent size / date formats returned by
 * WHM API 1 into stable internal units (megabytes, ISO dates).
 */
final class Units
{
    /**
     * Convert a WHM size value to whole megabytes.
     *
     * Handles suffixed strings ("4210M", "10G", "512000K", "2T"), bare numbers
     * (WHM disk figures are already in MB), and the "unlimited"/"0" sentinels
     * which map to 0 (treated as "no limit" by usage calculations).
     */
    public static function toMegabytes(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        $raw = trim((string) $value);
        if ($raw === '' || strcasecmp($raw, 'unlimited') === 0) {
            return 0;
        }

        if (!preg_match('/^([\d.]+)\s*([KMGT])?B?$/i', $raw, $m)) {
            return 0;
        }

        $number = (float) $m[1];
        $unit   = strtoupper($m[2] ?? 'M'); // bare number = MB in WHM disk fields

        $mb = match ($unit) {
            'K' => $number / 1024,
            'M' => $number,
            'G' => $number * 1024,
            'T' => $number * 1024 * 1024,
            default => $number,
        };

        return (int) round($mb);
    }

    /**
     * Convert a raw byte count (e.g. showbw totalbytes) to whole megabytes.
     */
    public static function bytesToMegabytes(mixed $bytes): int
    {
        $bytes = is_numeric($bytes) ? (float) $bytes : 0.0;
        return (int) round($bytes / (1024 * 1024));
    }

    /**
     * Parse a WHM startdate ("24/3/12 08:14:02" = YY/M/D) into a Y-m-d string,
     * or null when it cannot be interpreted.
     */
    public static function parseStartDate(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // Format: "YY/M/D HH:MM:SS" (two-digit year).
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{1,2})#', $raw, $m)) {
            $year  = 2000 + (int) $m[1];
            $month = (int) $m[2];
            $day   = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // Fall back to strtotime for other shapes.
        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    /**
     * Convert a unix timestamp (SSL not_after/not_before) to a Y-m-d string.
     */
    public static function timestampToDate(mixed $ts): ?string
    {
        if (!is_numeric($ts) || (int) $ts <= 0) {
            return null;
        }
        return date('Y-m-d', (int) $ts);
    }

    /**
     * Whole days from now until the given unix timestamp (may be negative).
     */
    public static function daysUntil(mixed $ts): ?int
    {
        if (!is_numeric($ts) || (int) $ts <= 0) {
            return null;
        }
        return (int) floor(((int) $ts - time()) / 86400);
    }
}
