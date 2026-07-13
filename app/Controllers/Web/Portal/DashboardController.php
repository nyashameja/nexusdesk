<?php

declare(strict_types=1);

namespace App\Controllers\Web\Portal;

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
        $openTickets = $this->tickets->countSearch([
            'requester_id' => $user?->id,
            'open_only'    => true,
        ]);

        return $this->view('portal.dashboard', [
            'title'       => 'Dashboard',
            'active'      => 'dashboard',
            'openTickets' => $openTickets,
            'recent'      => $this->tickets->search(['requester_id' => $user?->id], 1, 5),
        ]);
    }
}
