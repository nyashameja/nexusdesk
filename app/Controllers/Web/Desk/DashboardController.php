<?php

declare(strict_types=1);

namespace App\Controllers\Web\Desk;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Services\Auth\AuthContext;

final class DashboardController extends Controller
{
    public function __construct(private readonly TicketRepositoryInterface $tickets)
    {
    }

    public function index(Request $request): Response
    {
        $user = AuthContext::user();
        $counts = $this->tickets->dashboardCounts($user?->id);

        return $this->view('desk.dashboard', [
            'title'   => 'Dashboard',
            'active'  => 'dashboard',
            'counts'  => $counts,
            'myQueue' => $this->tickets->search(
                ['assigned_agent_id' => $user?->id, 'open_only' => true],
                1,
                8
            ),
            'atRisk'  => $this->tickets->slaAtRisk(6),
        ]);
    }
}
