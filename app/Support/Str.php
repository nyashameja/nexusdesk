<?php

declare(strict_types=1);

namespace App\Support;

final class Str
{
    /** URL-safe slug from arbitrary text. */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', $separator, $value) ?? '';
        return trim($value, $separator) ?: 'item';
    }

    /** Truncate to $length, appending an ellipsis when cut. */
    public static function excerpt(string $value, int $length = 160): string
    {
        $value = trim(strip_tags($value));
        if (mb_strlen($value) <= $length) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $length)) . '…';
    }
}
