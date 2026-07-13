<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller with view/redirect/json helpers. Controllers stay thin:
 * validate input, call a service, return a response.
 */
abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        return View::make($template, $data, $layout);
    }

    protected function redirect(string $location): RedirectResponse
    {
        return new RedirectResponse($location);
    }

    protected function back(string $fallback = '/'): RedirectResponse
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        return new RedirectResponse($referer);
    }

    /** @param mixed $data @param array<string,mixed> $meta */
    protected function json(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return JsonResponse::ok($data, $meta, $status);
    }
}
