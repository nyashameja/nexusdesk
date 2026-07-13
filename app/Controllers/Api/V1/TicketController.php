<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Services\Auth\AuthContext;

/**
 * REST API — tickets. Customer tokens are scoped to their own tickets; staff
 * tokens see all (permission-gated). Mirrors the web TicketService so rules
 * never fork between UI and API.
 */
final class TicketController
{
    public function __construct(private readonly TicketRepositoryInterface $tickets)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = AuthContext::user();
        if ($user === null || !$user->can('tickets.view')) {
            return JsonResponse::error('forbidden', 'Insufficient permissions.', 403);
        }

        $filters = array_filter([
            'status'   => $request->query('status'),
            'priority' => $request->query('priority'),
            'q'        => $request->query('q'),
        ], static fn ($v) => $v !== null && $v !== '');

        // Customers are restricted to their own tickets.
        if ($user->isCustomer()) {
            $filters['requester_id'] = $user->id;
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $tickets = $this->tickets->search($filters, $page, $perPage);
        $total = $this->tickets->countSearch($filters);

        return JsonResponse::ok(
            array_map([$this, 'present'], $tickets),
            ['pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ]]
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = AuthContext::user();
        $ticket = $this->tickets->find($id);

        if ($ticket === null) {
            return JsonResponse::error('not_found', 'Ticket not found.', 404);
        }
        if ($user !== null && $user->isCustomer() && $ticket->requesterId() !== $user->id) {
            // Do not disclose existence to non-owners.
            return JsonResponse::error('not_found', 'Ticket not found.', 404);
        }

        $data = $this->present($ticket);
        $data['messages'] = $this->tickets->messages($id, includeInternal: $user !== null && $user->isStaff());

        return JsonResponse::ok($data);
    }

    /** @return array<string,mixed> */
    private function present(Ticket $ticket): array
    {
        return [
            'id'         => $ticket->id(),
            'reference'  => $ticket->reference(),
            'subject'    => $ticket->subject(),
            'status'     => $ticket->statusSlug(),
            'priority'   => $ticket->prioritySlug(),
            'department' => $ticket->departmentName(),
            'requester'  => $ticket->requesterName(),
            'agent'      => $ticket->assignedAgentName(),
            'created_at' => $ticket->createdAt(),
        ];
    }
}
