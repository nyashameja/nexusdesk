<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface ZohoRepositoryInterface
{
    // --- Connection ---------------------------------------------------------
    public function connection(): ?array;
    /** @param array<string,mixed> $data */
    public function saveConnection(array $data): void;
    public function markSynced(): void;
    public function disconnect(): void;

    // --- Cached documents ---------------------------------------------------
    /** @param array<string,mixed> $data */
    public function upsertDocument(array $data): void;
    /** @return array<int,array<string,mixed>> */
    public function documents(string $docType, ?int $companyId, int $limit = 100): array;
    public function document(int $id): ?array;
    public function documentByZohoId(string $docType, string $zohoId): ?array;

    /** @return array{balance:float,outstanding_count:int,invoice_count:int} */
    public function summaryForCompany(int $companyId): array;
}
