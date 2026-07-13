<?php

declare(strict_types=1);

namespace App\Services\Ticket;

use App\Repositories\Contracts\LookupRepositoryInterface;

/**
 * Computes SLA deadlines for a ticket from its department policy + priority.
 *
 * The foundation uses calendar-time deadlines (now + target minutes). The
 * schema and Stage 2 doc reserve business-hours/holiday-aware calculation as a
 * service-layer refinement (business_hours / business_holidays tables) added
 * with the reporting/SLA-monitor stage — the interface here does not change.
 */
final class SlaService
{
    public function __construct(private readonly LookupRepositoryInterface $lookups)
    {
    }

    /**
     * @return array{due_first_response_at:?string,due_resolution_at:?string}
     */
    public function deadlines(?int $policyId, int $priorityId, ?\DateTimeImmutable $from = null): array
    {
        $from ??= new \DateTimeImmutable('now');

        if ($policyId === null) {
            return ['due_first_response_at' => null, 'due_resolution_at' => null];
        }

        $target = $this->lookups->slaTarget($policyId, $priorityId);
        if ($target === null) {
            return ['due_first_response_at' => null, 'due_resolution_at' => null];
        }

        return [
            'due_first_response_at' => $from->modify("+{$target['first_response_minutes']} minutes")->format('Y-m-d H:i:s'),
            'due_resolution_at'     => $from->modify("+{$target['resolution_minutes']} minutes")->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Remaining minutes to a deadline (negative = breached), or null if none.
     */
    public function minutesRemaining(?string $deadline): ?int
    {
        if ($deadline === null) {
            return null;
        }
        $due = strtotime($deadline);
        if ($due === false) {
            return null;
        }
        return (int) round(($due - time()) / 60);
    }
}
