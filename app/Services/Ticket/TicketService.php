<?php

declare(strict_types=1);

namespace App\Services\Ticket;

use App\Core\Database;
use App\Models\Ticket;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Repositories\Contracts\LookupRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Services\Audit\AuditService;
use App\Services\Mail\MailService;
use App\Services\Settings\Settings;

/**
 * Help-desk use-cases: create tickets, post replies/notes, change status,
 * assign/transfer, log time. Orchestrates repositories inside transactions and
 * records history/audit. Email/notification side-effects are dispatched here
 * (moved to the job queue in the email stage).
 */
final class TicketService
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly LookupRepositoryInterface $lookups,
        private readonly DepartmentRepositoryInterface $departments,
        private readonly NotificationRepositoryInterface $notifications,
        private readonly SlaService $sla,
        private readonly AuditService $audit,
        private readonly MailService $mail,
        private readonly Database $db,
    ) {
    }

    /**
     * @param array{subject:string,department_id:int,priority:string,message:string,
     *   requester_id?:int|null,requester_email?:string|null,requester_name?:string|null,
     *   company_id?:int|null,source?:string} $input
     */
    public function create(array $input): Ticket
    {
        $priority = $this->lookups->priorityBySlug($input['priority']);
        if ($priority === null) {
            throw new \InvalidArgumentException('Unknown priority.');
        }
        $statusId = $this->lookups->defaultStatusId();
        $departmentId = (int) $input['department_id'];
        $policyId = $this->departments->defaultSlaPolicyId($departmentId);
        $deadlines = $this->sla->deadlines($policyId, (int) $priority['id']);

        $reference = $this->buildReference();

        $ticketId = $this->db->transaction(function () use ($input, $priority, $statusId, $departmentId, $policyId, $deadlines, $reference): int {
            $id = $this->tickets->create([
                'reference'             => $reference,
                'subject'               => $input['subject'],
                'department_id'         => $departmentId,
                'status_id'             => $statusId,
                'priority_id'           => (int) $priority['id'],
                'sla_policy_id'         => $policyId,
                'requester_id'          => $input['requester_id'] ?? null,
                'requester_email'       => $input['requester_email'] ?? null,
                'requester_name'        => $input['requester_name'] ?? null,
                'company_id'            => $input['company_id'] ?? null,
                'source'                => $input['source'] ?? 'portal',
                'due_first_response_at' => $deadlines['due_first_response_at'],
                'due_resolution_at'     => $deadlines['due_resolution_at'],
                'last_reply_at'         => date('Y-m-d H:i:s'),
                'last_reply_by'         => 'customer',
            ]);

            $this->tickets->addMessage([
                'ticket_id'   => $id,
                'user_id'     => $input['requester_id'] ?? null,
                'author_type' => 'customer',
                'body_html'   => nl2br(htmlspecialchars($input['message'], ENT_QUOTES, 'UTF-8')),
                'body_text'   => $input['message'],
                'is_internal' => 0,
                'source'      => $input['source'] ?? 'portal',
            ]);

            $this->tickets->addStatusHistory([
                'ticket_id'    => $id,
                'to_status_id' => $statusId,
                'changed_by'   => $input['requester_id'] ?? null,
                'note'         => 'Ticket created',
            ]);

            return $id;
        });

        $this->audit->log('ticket.created', 'Ticket', $ticketId, new: ['reference' => $reference]);

        $ticket = $this->tickets->find($ticketId);
        \assert($ticket !== null);

        // Confirmation email to the requester (queued).
        $email = $ticket->requesterEmail() ?? ($input['requester_email'] ?? null);
        if ($email) {
            $this->mail->queueTemplate($email, $ticket->requesterName(), 'ticket_created', [
                'ticket'   => [
                    'reference' => $ticket->reference(),
                    'subject'   => $ticket->subject(),
                    'url'       => $this->ticketUrl($ticket),
                ],
                'customer' => ['first_name' => explode(' ', $ticket->requesterName())[0] ?? 'there'],
            ]);
        }

        return $ticket;
    }

    public function reply(int $ticketId, string $body, string $authorType, ?int $userId, bool $internal = false): void
    {
        $this->db->transaction(function () use ($ticketId, $body, $authorType, $userId, $internal): void {
            $this->tickets->addMessage([
                'ticket_id'   => $ticketId,
                'user_id'     => $userId,
                'author_type' => $authorType,
                'body_html'   => nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
                'body_text'   => $body,
                'is_internal' => $internal ? 1 : 0,
                'source'      => 'agent',
            ]);

            if (!$internal) {
                $update = [
                    'last_reply_at' => date('Y-m-d H:i:s'),
                    'last_reply_by' => $authorType,
                ];
                // First agent response stamps first_response_at.
                if ($authorType === 'agent') {
                    $ticket = $this->tickets->find($ticketId);
                    if ($ticket !== null && $ticket->get('first_response_at') === null) {
                        $update['first_response_at'] = date('Y-m-d H:i:s');
                    }
                }
                $this->tickets->update($ticketId, $update);
            }
        });

        $this->audit->log($internal ? 'ticket.note_added' : 'ticket.replied', 'Ticket', $ticketId);
        $this->notifyRequester($ticketId, $authorType, $internal);
    }

    public function changeStatus(int $ticketId, string $statusSlug, ?int $userId): void
    {
        $status = $this->lookups->statusBySlug($statusSlug);
        if ($status === null) {
            throw new \InvalidArgumentException('Unknown status.');
        }
        $ticket = $this->tickets->find($ticketId);
        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found.');
        }

        $update = ['status_id' => (int) $status['id']];
        if ((bool) $status['is_resolved'] && $ticket->get('resolved_at') === null) {
            $update['resolved_at'] = date('Y-m-d H:i:s');
        }
        if ($statusSlug === 'closed') {
            $update['closed_at'] = date('Y-m-d H:i:s');
        }

        $this->db->transaction(function () use ($ticketId, $ticket, $status, $update, $userId): void {
            $this->tickets->update($ticketId, $update);
            $this->tickets->addStatusHistory([
                'ticket_id'      => $ticketId,
                'from_status_id' => $ticket->get('status_id') !== null ? (int) $ticket->get('status_id') : null,
                'to_status_id'   => (int) $status['id'],
                'changed_by'     => $userId,
            ]);
        });

        $this->audit->log('ticket.status_changed', 'Ticket', $ticketId, new: ['status' => $statusSlug]);
    }

    public function assign(int $ticketId, ?int $agentId, ?int $actorId): void
    {
        $this->tickets->update($ticketId, ['assigned_agent_id' => $agentId]);
        $this->audit->log('ticket.assigned', 'Ticket', $ticketId, new: ['agent_id' => $agentId], actorId: $actorId);
    }

    public function transfer(int $ticketId, int $departmentId, ?int $actorId): void
    {
        $this->tickets->update($ticketId, ['department_id' => $departmentId]);
        $this->audit->log('ticket.transferred', 'Ticket', $ticketId, new: ['department_id' => $departmentId], actorId: $actorId);
    }

    public function logTime(int $ticketId, int $userId, int $minutes, bool $billable, ?string $note): void
    {
        $this->tickets->addTimeEntry([
            'ticket_id'   => $ticketId,
            'user_id'     => $userId,
            'minutes'     => $minutes,
            'is_billable' => $billable ? 1 : 0,
            'note'        => $note,
        ]);
        $this->audit->log('ticket.time_logged', 'Ticket', $ticketId, new: ['minutes' => $minutes]);
    }

    private function notifyRequester(int $ticketId, string $authorType, bool $internal): void
    {
        if ($internal || $authorType !== 'agent') {
            return;
        }
        $ticket = $this->tickets->find($ticketId);
        if ($ticket === null) {
            return;
        }

        // In-app notification for registered customers.
        if ($ticket->requesterId() !== null) {
            $this->notifications->create([
                'user_id' => $ticket->requesterId(),
                'type'    => 'ticket.reply',
                'title'   => 'New reply on ' . $ticket->reference(),
                'body'    => $ticket->subject(),
                'url'     => '/portal/tickets/' . $ticket->id(),
                'icon'    => 'reply',
            ]);
        }

        // Email notification to the requester (queued).
        if ($ticket->requesterEmail()) {
            $this->mail->queueTemplate($ticket->requesterEmail(), $ticket->requesterName(), 'ticket_agent_reply', [
                'ticket'   => [
                    'reference' => $ticket->reference(),
                    'subject'   => $ticket->subject(),
                    'url'       => $this->ticketUrl($ticket),
                ],
                'customer' => ['first_name' => explode(' ', $ticket->requesterName())[0] ?? 'there'],
                'reply'    => ['body' => 'You have a new reply from our support team.'],
            ]);
        }
    }

    private function ticketUrl(\App\Models\Ticket $ticket): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        if ($ticket->requesterId() !== null) {
            return $base . '/portal/tickets/' . $ticket->id();
        }
        return $base . '/track?reference=' . urlencode($ticket->reference());
    }

    private function buildReference(): string
    {
        $prefix = (string) Settings::get('general.ticket_prefix', 'NEXUS');
        return $prefix . '-' . (1000 + $this->tickets->nextReferenceNumber());
    }
}
