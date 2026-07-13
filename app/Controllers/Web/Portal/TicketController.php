<?php

declare(strict_types=1);

namespace App\Controllers\Web\Portal;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Repositories\Contracts\LookupRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Services\Auth\AuthContext;
use App\Services\Ticket\TicketService;
use App\Support\Validation\Validator;

/**
 * Customer-facing tickets. Every action is scoped to the signed-in customer:
 * they can only see and reply to their own tickets.
 */
final class TicketController extends Controller
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly TicketService $ticketService,
        private readonly DepartmentRepositoryInterface $departments,
        private readonly LookupRepositoryInterface $lookups,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = AuthContext::user();
        $page = max(1, (int) $request->query('page', 1));
        $filters = ['requester_id' => $user?->id];
        if ($request->query('status')) {
            $filters['status'] = $request->query('status');
        }

        return $this->view('portal.tickets_index', [
            'title'    => 'My tickets',
            'active'   => 'tickets',
            'tickets'  => $this->tickets->search($filters, $page, 15),
            'total'    => $this->tickets->countSearch($filters),
            'page'     => $page,
            'perPage'  => 15,
            'statuses' => $this->lookups->statuses(),
            'filters'  => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('portal.ticket_create', [
            'title'       => 'New ticket',
            'active'      => 'tickets',
            'departments' => $this->departments->all(publicOnly: true),
            'priorities'  => $this->lookups->priorities(),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = AuthContext::user();
        $validator = Validator::make(
            $request->only(['department_id', 'priority', 'subject', 'message']),
            [
                'department_id' => 'required|integer',
                'priority'      => 'required',
                'subject'       => 'required|max:255',
                'message'       => 'required|min:10',
            ]
        );
        if ($validator->fails()) {
            return (new RedirectResponse('/portal/tickets/new'))
                ->withErrors($validator->errors())
                ->withInput($request->all());
        }

        $ticket = $this->ticketService->create([
            'subject'         => (string) $request->input('subject'),
            'department_id'   => (int) $request->input('department_id'),
            'priority'        => (string) $request->input('priority'),
            'message'         => (string) $request->input('message'),
            'requester_id'    => $user?->id,
            'requester_email' => $user?->email,
            'requester_name'  => $user?->fullName(),
            'company_id'      => $user?->companyId,
            'source'          => 'portal',
        ]);

        return new RedirectResponse('/portal/tickets/' . $ticket->id());
    }

    public function show(Request $request, int $id): Response
    {
        $ticket = $this->ownedTicket($id);

        return $this->view('portal.ticket_show', [
            'title'    => $ticket->reference(),
            'active'   => 'tickets',
            'ticket'   => $ticket,
            'messages' => $this->tickets->messages($id, includeInternal: false),
        ]);
    }

    public function reply(Request $request, int $id): Response
    {
        $this->ownedTicket($id);

        $validator = Validator::make($request->only(['body']), ['body' => 'required|min:1']);
        if ($validator->fails()) {
            return (new RedirectResponse('/portal/tickets/' . $id))->withErrors($validator->errors());
        }

        $this->ticketService->reply($id, (string) $request->input('body'), 'customer', AuthContext::id(), false);
        return new RedirectResponse('/portal/tickets/' . $id);
    }

    /** Load a ticket and enforce that it belongs to the current customer. */
    private function ownedTicket(int $id): \App\Models\Ticket
    {
        $ticket = $this->tickets->find($id);
        $user = AuthContext::user();
        if ($ticket === null || $user === null || $ticket->requesterId() !== $user->id) {
            throw HttpException::notFound('Ticket not found.');
        }
        return $ticket;
    }
}
