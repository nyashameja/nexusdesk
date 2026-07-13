<?php

declare(strict_types=1);

namespace App\Services\Zoho;

use App\Core\Database;
use App\Infrastructure\Logging\LoggerInterface;
use App\Integrations\Zoho\ZohoBooksClientInterface;
use App\Repositories\Contracts\ZohoRepositoryInterface;

/**
 * Orchestrates the Zoho Books integration: connection state, cache sync, and
 * cache reads for the portal. Accounting stays in Zoho; NexusDesk only mirrors
 * a read-only summary keyed to each client company by its Zoho contact id.
 */
final class ZohoBooksService
{
    /** Resource => cached doc_type. */
    private const RESOURCES = [
        'invoices'        => 'invoice',
        'estimates'       => 'quote',
        'creditnotes'     => 'credit_note',
        'customerpayments' => 'payment',
    ];

    public function __construct(
        private readonly ZohoBooksClientInterface $client,
        private readonly ZohoRepositoryInterface $repository,
        private readonly Database $db,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isConnected(): bool
    {
        $connection = $this->repository->connection();
        return $connection !== null && (int) $connection['is_active'] === 1;
    }

    public function connection(): ?array
    {
        return $this->repository->connection();
    }

    /** Pull all financial documents into the local cache. Returns count synced. */
    public function sync(): int
    {
        $connection = $this->repository->connection();
        if ($connection === null || (int) $connection['is_active'] !== 1) {
            return 0;
        }
        $orgId = (string) ($connection['organization_id'] ?? '');
        if ($orgId === '') {
            return 0;
        }

        $companyMap = $this->companyMap();
        $count = 0;

        foreach (self::RESOURCES as $resource => $docType) {
            foreach ($this->client->fetch($resource, $orgId) as $doc) {
                $mapped = $this->mapDocument($docType, $doc, $companyMap);
                if ($mapped !== null) {
                    $this->repository->upsertDocument($mapped);
                    $count++;
                }
            }
        }

        $this->repository->markSynced();
        $this->logger->info('Zoho sync complete', ['documents' => $count]);
        return $count;
    }

    /** @return array<string,int> zoho_contact_id => company_id */
    private function companyMap(): array
    {
        $rows = $this->db->select(
            'SELECT id, zoho_contact_id FROM companies WHERE zoho_contact_id IS NOT NULL AND zoho_contact_id <> ""'
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['zoho_contact_id']] = (int) $row['id'];
        }
        return $map;
    }

    /**
     * @param array<string,mixed> $doc
     * @param array<string,int> $companyMap
     * @return array<string,mixed>|null
     */
    private function mapDocument(string $docType, array $doc, array $companyMap): ?array
    {
        $idKey = match ($docType) {
            'invoice'     => 'invoice_id',
            'quote'       => 'estimate_id',
            'credit_note' => 'creditnote_id',
            'payment'     => 'payment_id',
            default       => 'invoice_id',
        };
        $numberKey = match ($docType) {
            'invoice'     => 'invoice_number',
            'quote'       => 'estimate_number',
            'credit_note' => 'creditnote_number',
            'payment'     => 'payment_number',
            default       => 'number',
        };
        $zohoId = (string) ($doc[$idKey] ?? '');
        if ($zohoId === '') {
            return null;
        }
        $contactId = (string) ($doc['customer_id'] ?? '');

        return [
            'company_id'       => $companyMap[$contactId] ?? null,
            'zoho_contact_id'  => $contactId,
            'doc_type'         => $docType,
            'zoho_document_id' => $zohoId,
            'number'           => (string) ($doc[$numberKey] ?? ''),
            'status'           => (string) ($doc['status'] ?? ''),
            'currency'         => substr((string) ($doc['currency_code'] ?? 'USD'), 0, 3),
            'total'            => (float) ($doc['total'] ?? $doc['amount'] ?? 0),
            'balance'          => (float) ($doc['balance'] ?? 0),
            'issue_date'       => $this->date($doc['date'] ?? null),
            'due_date'         => $this->date($doc['due_date'] ?? null),
            'payload'          => $doc,
        ];
    }

    private function date(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        return $value;
    }

    /** @return array{balance:float,outstanding_count:int,invoice_count:int} */
    public function summaryForCompany(?int $companyId): array
    {
        if ($companyId === null) {
            return ['balance' => 0.0, 'outstanding_count' => 0, 'invoice_count' => 0];
        }
        return $this->repository->summaryForCompany($companyId);
    }

    /** @return array<int,array<string,mixed>> */
    public function documentsForCompany(string $docType, ?int $companyId): array
    {
        return $this->repository->documents($docType, $companyId);
    }

    public function invoicePdf(int $cachedDocumentId): ?string
    {
        $doc = $this->repository->document($cachedDocumentId);
        $connection = $this->repository->connection();
        if ($doc === null || $connection === null) {
            return null;
        }
        return $this->client->fetchInvoicePdf(
            (string) $connection['organization_id'],
            (string) $doc['zoho_document_id']
        );
    }
}
