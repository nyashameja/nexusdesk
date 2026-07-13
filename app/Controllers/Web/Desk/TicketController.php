<?php

declare(strict_types=1);

namespace App\Controllers\Web\Desk;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Repositories\Contracts\LookupRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
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
