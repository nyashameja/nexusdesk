<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface LookupRepositoryInterface
{
    /** @return array<int,array<string,mixed>> */
    public function statuses(): array;
    /** @return array<int,array<string,mixed>> */
    public function priorities(): array;
    public function statusBySlug(string $slug): ?array;
    public function priorityBySlug(string $slug): ?array;
    public function defaultStatusId(): int;
    /** @return array{first_response_minutes:int,resolution_minutes:int}|null */
    public function slaTarget(int $policyId, int $priorityId): ?array;
}
