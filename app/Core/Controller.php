<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

use ParagonHostOps\Services\Auth;

/**
 * Base controller providing view rendering, redirects and JSON helpers.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], int $status = 200): Response
    {
        /** @var View $renderer */
        $renderer = app(View::class);

        // Make common data available to every view.
        $data += [
            'auth'        => app(Auth::class),
            'appName'     => config('app.name'),
            'tagline'     => config('app.tagline'),
            'notifUnread' => $this->unreadNotifications(),
        ];

        return Response::html($renderer->render($view, $data), $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }

    protected function back(string $fallback = '/dashboard'): Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        // Only honour a same-origin referer to prevent open-redirects.
        if (is_string($referer) && $referer !== '') {
            $appHost     = parse_url((string) config('app.url'), PHP_URL_HOST);
            $refererHost = parse_url($referer, PHP_URL_HOST);
            if ($refererHost !== null && $refererHost === $appHost) {
                return Response::redirect($referer);
            }
        }

        return Response::redirect(url($fallback));
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function session(): Session
    {
        return app(Session::class);
    }

    /**
     * Unread notification count for the top bar. Fails safe to 0 if the
     * database is unavailable (e.g. on an error page).
     */
    private function unreadNotifications(): int
    {
        try {
            return app(\ParagonHostOps\Repositories\NotificationRepository::class)->unreadCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
