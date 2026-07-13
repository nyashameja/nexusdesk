<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\ZohoRepositoryInterface;

/**
 * Persists the single Zoho connection row and the cached financial documents.
 * Portal finance pages read exclusively from this cache so they render fast and
 * survive Zoho outages / rate limits.
 */
final class MySqlZohoRepository extends MySqlRepository implements ZohoRepositoryInterface
{
    public function connection(): ?array
    {
        return $this->db->selectOne('SELECT * FROM zoho_connections ORDER BY id DESC LIMIT 1');
    }

    public function saveConnection(array $data): void
    {
        $existing = $this->connection();
        if ($existing === null) {
            $this->db->insert('zoho_connections', $data);
        } else {
            $this->db->update('zoho_connections', $data, ['id' => (int) $existing['id']]);
        }
    }

    public function markSynced(): void
    {
        $existing = $this->connection();
        if ($existing !== null) {
            $this->db->run('UPDATE zoho_connections SET last_synced_at = NOW() WHERE id = ?', [$existing['id']]);
        }
    }

    public function disconnect(): void
    {
        $existing = $this->connection();
        if ($existing !== null) {
            $this->db->run(
                'UPDATE zoho_connections SET is_active = 0, access_token = NULL, refresh_token = NULL WHERE id = ?',
                [$existing['id']]
            );
        }
    }

    public function upsertDocument(array $data): void
    {
        if (isset($data['payload']) && is_array($data['payload'])) {
            $data['payload'] = json_encode($data['payload'], JSON_UNESCAPED_SLASHES);
        }
        $existing = $this->documentByZohoId((string) $data['doc_type'], (string) $data['zoho_document_id']);
        if ($existing === null) {
            $this->db->insert('zoho_documents', $data);
        } else {
            $this->db->update('zoho_documents', $data, ['id' => (int) $existing['id']]);
        }
    }

    public function documents(string $docType, ?int $companyId, int $limit = 100): array
    {
        $sql = 'SELECT * FROM zoho_documents WHERE doc_type = ?';
        $params = [$docType];
        if ($companyId !== null) {
            $sql .= ' AND company_id = ?';
            $params[] = $companyId;
        }
        $sql .= ' ORDER BY issue_date DESC, id DESC LIMIT ?';
        $params[] = $limit;
        return $this->db->select($sql, $params);
    }

    public function document(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM zoho_documents WHERE id = ?', [$id]);
    }

    public function documentByZohoId(string $docType, string $zohoId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM zoho_documents WHERE doc_type = ? AND zoho_document_id = ?',
            [$docType, $zohoId]
        );
    }

    public function summaryForCompany(int $companyId): array
    {
        $row = $this->db->selectOne(
            "SELECT
                COALESCE(SUM(balance), 0) AS balance,
                SUM(CASE WHEN balance > 0 THEN 1 ELSE 0 END) AS outstanding_count,
                COUNT(*) AS invoice_count
             FROM zoho_documents
             WHERE doc_type = 'invoice' AND company_id = ?",
            [$companyId]
        );
        return [
            'balance'           => (float) ($row['balance'] ?? 0),
            'outstanding_count' => (int) ($row['outstanding_count'] ?? 0),
            'invoice_count'     => (int) ($row['invoice_count'] ?? 0),
        ];
    }
}
