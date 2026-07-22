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
        return Response::redirect(is_string($referer) && $referer !== '' ? $referer : url($fallback));
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
