<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\NotificationRepository;

/**
 * Application notifications list. Any authenticated user may view and clear
 * their notifications.
 */
final class NotificationsController extends Controller
{
    public function __construct(private NotificationRepository $notifications)
    {
    }

    public function index(Request $request, array $params): Response
    {
        return $this->view('notifications.index', [
            'title'         => 'Notifications',
            'notifications' => $this->notifications->recent(80),
        ]);
    }

    public function markAllRead(Request $request, array $params): Response
    {
        $this->notifications->markAllRead();
        $this->session()->flash('success', 'All notifications marked as read.');
        return $this->redirect('/notifications');
    }
}
