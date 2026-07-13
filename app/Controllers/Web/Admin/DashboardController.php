<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\TicketRepositoryInterface;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly Database $db,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('admin.dashboard', [
            'title'  => 'Admin dashboard',
            'active' => 'admin_dashboard',
            'counts' => $this->tickets->dashboardCounts(),
            'health' => $this->health(),
        ]);
    }

    /** @return array<string,bool> */
    private function health(): array
    {
        $db = false;
        try {
            $this->db->scalar('SELECT 1');
            $db = true;
        } catch (\Throwable) {
            $db = false;
        }

        return [
            'database' => $db,
            'mail'     => (bool) setting('mail.from_email'),
            'zoho'     => (bool) setting('zoho.enabled', false),
            'storage'  => is_writable(\App\Core\App::storagePath()),
        ];
    }
}
