<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Plain-PHP template renderer with layout support and escaping helpers.
 * Views live in resources/views and are rendered inside an optional layout.
 */
final class View
{
    private static string $viewPath = '';
    /** @var array<string,mixed> Data shared with every view. */
    private static array $shared = [];

    public static function setViewPath(string $path): void
    {
        self::$viewPath = rtrim($path, '/');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public static function make(string $template, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        $content = self::render($template, $data);

        if ($layout !== null) {
            $content = self::render($layout, array_merge($data, ['content' => $content]));
        }

        return new Response($content);
    }

    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = []): string
    {
        $file = self::$viewPath . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View [$template] not found at $file.");
        }

        $data = array_merge(self::$shared, $data);
        extract($data, EXTR_SKIP);

        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /** Escape for HTML output. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
