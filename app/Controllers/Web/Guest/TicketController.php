<?php

declare(strict_types=1);

namespace App\Controllers\Web\Guest;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Repositories\Contracts\LookupRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Services\Ticket\TicketService;
use App\Support\Validation\Validator;

/**
 * Public ticket submission and tracking (no login required).
 */
final class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketRepositoryInterface $ticketRepo,
        private readonly DepartmentRepositoryInterface $departments,
        private readonly LookupRepositoryInterface $lookups,
    ) {
    }

    public function create(Request $request): Response
    {
        return $this->view('guest.submit', [
            'title'       => 'Submit a ticket',
            'departments' => $this->departments->all(publicOnly: true),
            'priorities'  => $this->lookups->priorities(),
        ], 'layouts/guest');
    }

    public function store(Request $request): Response
    {
        $validator = Validator::make(
            $request->only(['name', 'email', 'department_id', 'priority', 'subject', 'message']),
            [
                'name'          => 'required|max:160',
                'email'         => 'required|email',
                'department_id' => 'required|integer',
                'priority'      => 'required',
                'subject'       => 'required|max:255',
                'message'       => 'required|min:10',
            ]
        );

        if ($validator->fails()) {
            return (new RedirectResponse('/submit'))
                ->withErrors($validator->errors())
                ->withInput($request->all());
        }

        $ticket = $this->tickets->create([
            'subject'         => (string) $request->input('subject'),
            'department_id'   => (int) $request->input('department_id'),
            'priority'        => (string) $request->input('priority'),
            'message'         => (string) $request->input('message'),
            'requester_email' => (string) $request->input('email'),
            'requester_name'  => (string) $request->input('name'),
            'source'          => 'portal',
        ]);

        return (new RedirectResponse('/submit/success?ref=' . urlencode($ticket->reference())));
    }

    public function success(Request $request): Response
    {
        return $this->view('guest.success', [
            'title'     => 'Ticket submitted',
            'reference' => (string) $request->query('ref', ''),
        ], 'layouts/guest');
    }

    public function trackForm(Request $request): Response
    {
        return $this->view('guest.track', ['title' => 'Track a ticket'], 'layouts/guest');
    }

    public function track(Request $request): Response
    {
        $reference = trim((string) $request->input('reference'));
        $email = trim((string) $request->input('email'));

        $ticket = $this->ticketRepo->findByReference($reference);
        // Verify ownership by matching the email captured on the ticket.
        if ($ticket === null || strcasecmp((string) $ticket->requesterEmail(), $email) !== 0) {
            return (new RedirectResponse('/track'))
                ->withErrors(['reference' => ['No ticket matches that reference and email.']])
                ->withInput(['reference' => $reference, 'email' => $email]);
        }

        return $this->view('guest.track_show', [
            'title'    => 'Ticket ' . $ticket->reference(),
            'ticket'   => $ticket,
            'messages' => $this->ticketRepo->messages($ticket->id(), includeInternal: false),
        ], 'layouts/guest');
    }
}
