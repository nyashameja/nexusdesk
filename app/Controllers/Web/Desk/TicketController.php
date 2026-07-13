<?php

declare(strict_types=1);

namespace App\Controllers\Web\Desk;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Repositories\Contracts\LookupRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\AuthContext;
use App\Services\Ticket\SlaService;
use App\Services\Ticket\TicketService;
use App\Support\Validation\Validator;

/**
 * Agent-facing ticket queue and workspace: list, view, reply/note, status
 * change, assign, transfer, and time logging.
 */
final class TicketController extends Controller
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly TicketService $ticketService,
        private readonly LookupRepositoryInterface $lookups,
        private readonly DepartmentRepositoryInterface $departments,
        private readonly SlaService $sla,
        private readonly UserRepositoryInterface $users,
        private readonly CompanyRepositoryInterface $companies,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'status'        => $request->query('status'),
            'priority'      => $request->query('priority'),
            'department_id' => $request->query('department_id'),
            'q'             => $request->query('q'),
        ], static fn ($v) => $v !== null && $v !== '');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        return $this->view('desk.tickets_index', [
            'title'       => 'Tickets',
            'active'      => 'tickets',
            'tickets'     => $this->tickets->search($filters, $page, $perPage),
            'total'       => $this->tickets->countSearch($filters),
            'page'        => $page,
            'perPage'     => $perPage,
            'filters'     => $filters,
            'statuses'    => $this->lookups->statuses(),
            'priorities'  => $this->lookups->priorities(),
            'departments' => $this->departments->all(),
        ]);
    }

    /** Show the "raise a ticket on behalf of a client" form. */
    public function create(Request $request): Response
    {
        if (!AuthContext::can('tickets.create')) {
            throw HttpException::forbidden('You cannot create tickets.');
        }
        return $this->view('desk.ticket_create', [
            'title'       => 'New ticket',
            'active'      => 'tickets',
            'departments' => $this->departments->all(),
            'priorities'  => $this->lookups->priorities(),
            'companies'   => $this->companies->paginate(1, 500),
        ]);
    }

    /** Create a ticket on behalf of a client. */
    public function store(Request $request): Response
    {
        if (!AuthContext::can('tickets.create')) {
            throw HttpException::forbidden('You cannot create tickets.');
        }

        $validator = Validator::make(
            $request->only(['client_email', 'client_name', 'department_id', 'priority', 'subject', 'message']),
            [
                'client_email'  => 'required|email',
                'client_name'   => 'required|max:160',
                'department_id' => 'required|integer',
                'priority'      => 'required',
                'subject'       => 'required|max:255',
                'message'       => 'required|min:5',
            ]
        );
        if ($validator->fails()) {
            return (new RedirectResponse('/desk/tickets/new'))
                ->withErrors($validator->errors())
                ->withInput($request->all());
        }

        // Resolve the client: link to an existing customer account by email
        // (adopting their company), otherwise open as an unregistered contact.
        $email = strtolower(trim((string) $request->input('client_email')));
        $existing = $this->users->findByEmail($email);
        $requesterId = $existing?->id;
        $companyId = $existing?->companyId;
        if ($companyId === null && $request->input('company_id')) {
            $companyId = (int) $request->input('company_id');
        }

        $ticket = $this->ticketService->create([
            'subject'         => (string) $request->input('subject'),
            'department_id'   => (int) $request->input('department_id'),
            'priority'        => (string) $request->input('priority'),
            'message'         => (string) $request->input('message'),
            'requester_id'    => $requesterId,
            'requester_email' => $email,
            'requester_name'  => $existing?->fullName() ?? (string) $request->input('client_name'),
            'company_id'      => $companyId,
            'source'          => 'agent',
        ]);

        // Optionally assign to the creating agent.
        if ((string) $request->input('assign_to_me') === '1') {
            $this->ticketService->assign($ticket->id(), AuthContext::id(), AuthContext::id());
        }

        return (new RedirectResponse('/desk/tickets/' . $ticket->id()))
            ->with('status', 'Ticket ' . $ticket->reference() . ' created on behalf of the client.');
    }

    public function show(Request $request, int $id): Response
    {
        $ticket = $this->tickets->find($id);
        if ($ticket === null) {
            throw HttpException::notFound('Ticket not found.');
        }

        return $this->view('desk.ticket_show', [
            'title'       => $ticket->reference(),
            'active'      => 'tickets',
            'ticket'      => $ticket,
            'messages'    => $this->tickets->messages($id, includeInternal: true),
            'statuses'    => $this->lookups->statuses(),
            'priorities'  => $this->lookups->priorities(),
            'departments' => $this->departments->all(),
            'timeMinutes' => $this->tickets->totalTimeMinutes($id),
            'slaRemaining' => $this->sla->minutesRemaining($ticket->dueResolutionAt()),
        ]);
    }

    public function reply(Request $request, int $id): Response
    {
        $ticket = $this->tickets->find($id);
        if ($ticket === null) {
            throw HttpException::notFound('Ticket not found.');
        }

        $validator = Validator::make($request->only(['body']), ['body' => 'required|min:1']);
        if ($validator->fails()) {
            return (new RedirectResponse('/desk/tickets/' . $id))->withErrors($validator->errors());
        }

        $internal = (bool) $request->input('internal', false);
        $this->ticketService->reply(
            $id,
            (string) $request->input('body'),
            'agent',
            AuthContext::id(),
            $internal
        );

        // Optional status change alongside the reply.
        $status = (string) $request->input('status', '');
        if ($status !== '') {
            $this->ticketService->changeStatus($id, $status, AuthContext::id());
        }

        return new RedirectResponse('/desk/tickets/' . $id);
    }

    public function updateStatus(Request $request, int $id): Response
    {
        $this->ticketService->changeStatus($id, (string) $request->input('status'), AuthContext::id());
        return new RedirectResponse('/desk/tickets/' . $id);
    }

    public function assign(Request $request, int $id): Response
    {
        $agentId = $request->input('agent_id');
        $this->ticketService->assign($id, $agentId !== '' && $agentId !== null ? (int) $agentId : null, AuthContext::id());
        return new RedirectResponse('/desk/tickets/' . $id);
    }

    public function transfer(Request $request, int $id): Response
    {
        $this->ticketService->transfer($id, (int) $request->input('department_id'), AuthContext::id());
        return new RedirectResponse('/desk/tickets/' . $id);
    }

    public function logTime(Request $request, int $id): Response
    {
        $minutes = (int) $request->input('minutes', 0);
        if ($minutes > 0) {
            $this->ticketService->logTime(
                $id,
                (int) AuthContext::id(),
                $minutes,
                (bool) $request->input('billable', false),
                $request->input('note') !== '' ? (string) $request->input('note') : null
            );
        }
        return new RedirectResponse('/desk/tickets/' . $id);
    }
}
