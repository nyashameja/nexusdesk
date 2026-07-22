<?php

declare(strict_types=1);

namespace ParagonHostOps\Helpers;

/**
 * RFC 4180 CSV builder with CSV-injection protection.
 *
 * Any cell that begins with a formula trigger (= + - @, tab, CR) is prefixed
 * with a single quote so spreadsheet apps do not execute it.
 */
final class Csv
{
    /**
     * @param array<int, string>              $headers
     * @param array<int, array<int, mixed>>   $rows
     */
    public static function build(array $headers, array $rows): string
    {
        $lines = [self::row($headers)];
        foreach ($rows as $row) {
            $lines[] = self::row($row);
        }
        // Prepend a UTF-8 BOM so Excel reads accents correctly.
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param array<int, mixed> $cells
     */
    private static function row(array $cells): string
    {
        return implode(',', array_map(self::cell(...), $cells));
    }

    private static function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        // Neutralise formula injection.
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'" . $value;
        }

        // Quote if it contains a comma, quote or newline; escape embedded quotes.
        if (preg_match('/[",\r\n]/', $value)) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
