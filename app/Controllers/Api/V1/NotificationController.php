<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\Auth\AuthContext;

final class NotificationController
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = AuthContext::user();
        if ($user === null) {
            return JsonResponse::error('unauthenticated', 'Authentication required.', 401);
        }
        $unreadOnly = (string) $request->query('unread', '') === 'true';
        return JsonResponse::ok($this->notifications->forUser($user->id, $unreadOnly, 50));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = AuthContext::user();
        return JsonResponse::ok(['count' => $user ? $this->notifications->unreadCount($user->id) : 0]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = AuthContext::user();
        if ($user !== null) {
            $this->notifications->markRead($id, $user->id);
        }
        return JsonResponse::ok(['ok' => true]);
    }
}
