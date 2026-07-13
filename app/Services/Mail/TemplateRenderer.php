<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Renders {{dotted.key}} placeholders in email templates against a nested
 * variable array. Unknown placeholders render as empty strings.
 */
final class TemplateRenderer
{
    /** @param array<string,mixed> $vars */
    public static function render(string $template, array $vars): string
    {
        $flat = self::flatten($vars);
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', static function (array $m) use ($flat): string {
            return (string) ($flat[$m[1]] ?? '');
        }, $template) ?? $template;
    }

    /**
     * @param array<string,mixed> $vars
     * @return array<string,scalar>
     */
    private static function flatten(array $vars, string $prefix = ''): array
    {
        $out = [];
        foreach ($vars as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $out += self::flatten($value, $dotted);
            } else {
                $out[$dotted] = is_scalar($value) ? $value : '';
            }
        }
        return $out;
    }
}
