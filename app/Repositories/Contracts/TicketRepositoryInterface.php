<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Ticket;

interface TicketRepositoryInterface
{
    public function find(int $id): ?Ticket;
    public function findByReference(string $reference): ?Ticket;
    /**
     * @param array<string,mixed> $filters
     * @return array<int,Ticket>
     */
    public function search(array $filters, int $page, int $perPage): array;
    /** @param array<string,mixed> $filters */
    public function countSearch(array $filters): int;
    /** @param array<string,mixed> $data */
    public function create(array $data): int;
    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void;
    public function nextReferenceNumber(): int;

    /** @return array<int,array<string,mixed>> */
    public function messages(int $ticketId, bool $includeInternal): array;
    /** @param array<string,mixed> $data */
    public function addMessage(array $data): int;
    /** @param array<string,mixed> $data */
    public function addStatusHistory(array $data): void;
    /** @param array<string,mixed> $data */
    public function addTimeEntry(array $data): void;
    public function totalTimeMinutes(int $ticketId): int;

    /** @return array<string,int> Dashboard counts. */
    public function dashboardCounts(?int $agentId = null): array;
    /** @return array<int,Ticket> Tickets at SLA risk. */
    public function slaAtRisk(int $limit = 10): array;
}
