<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\JsonResponse;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\Auth\AuthContext;

/**
 * Notification centre for any authenticated user: full history page, an AJAX
 * unread-count for the topbar bell, and mark-read actions.
 */
final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications)
    {
    }

    public function index(Request $request): Response
    {
        $user = AuthContext::user();
        $items = $user ? $this->notifications->forUser($user->id, false, 50) : [];
        $layout = $user && $user->isStaff() ? 'layouts/app' : 'layouts/app';

        return $this->view('notifications.index', [
            'title'         => 'Notifications',
            'active'        => 'notifications',
            'notifications' => $items,
        ], $layout);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = AuthContext::user();
        $count = $user ? $this->notifications->unreadCount($user->id) : 0;
        return new JsonResponse(['count' => $count]);
    }

    public function markRead(Request $request, int $id): Response
    {
        $user = AuthContext::user();
        if ($user) {
            $this->notifications->markRead($id, $user->id);
        }
        if ($request->expectsJson()) {
            return new JsonResponse(['ok' => true]);
        }
        return new RedirectResponse('/notifications');
    }

    public function markAllRead(Request $request): Response
    {
        $user = AuthContext::user();
        if ($user) {
            $this->notifications->markAllRead($user->id);
        }
        if ($request->expectsJson()) {
            return new JsonResponse(['ok' => true]);
        }
        return (new RedirectResponse('/notifications'))->with('status', 'All notifications marked read.');
    }
}
