<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Data access for the client CRM.
 *
 * All writes use an explicit field allow-list (mass-assignment protection);
 * user-supplied ids are always parameter-bound.
 */
final class ClientRepository
{
    /** Columns a client create/update is permitted to set. */
    private const FILLABLE = [
        'client_type', 'company_name', 'first_name', 'last_name',
        'primary_email', 'secondary_email', 'phone', 'whatsapp',
        'country', 'province', 'city', 'billing_address', 'tax_number',
        'status', 'notes',
    ];

    public function __construct(private Database $db)
    {
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function paginate(string $search, string $status, int $page, int $perPage): array
    {
        $conditions = ['c.deleted_at IS NULL'];
        $args = [];

        if ($search !== '') {
            $conditions[] = '(c.company_name LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.primary_email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($args, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $conditions[] = 'c.status = ?';
            $args[] = $status;
        }

        $where   = implode(' AND ', $conditions);
        $page    = max(1, $page);
        $perPage = max(5, min($perPage, 100));
        $offset  = ($page - 1) * $perPage;

        $total = (int) ($this->db->first("SELECT COUNT(*) AS c FROM clients c WHERE {$where}", $args)['c'] ?? 0);

        $rows = $this->db->all(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM whm_accounts a WHERE a.client_id = c.id AND a.deleted_at IS NULL) AS account_count,
                    (SELECT COUNT(*) FROM domains d WHERE d.client_id = c.id AND d.deleted_at IS NULL) AS domain_count
               FROM clients c
              WHERE {$where}
           ORDER BY COALESCE(NULLIF(c.company_name,''), c.last_name, c.first_name) ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $args
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$id]);
    }

    public function displayName(array $client): string
    {
        $company = trim((string) ($client['company_name'] ?? ''));
        if ($company !== '') {
            return $company;
        }
        $name = trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
        return $name !== '' ? $name : 'Client #' . ($client['id'] ?? '');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $fields = $this->filter($data);
        $fields['created_at'] = null;
        $fields['updated_at'] = null;

        $columns = array_keys($fields);
        $placeholders = array_map(static fn (string $c): string => $c === 'created_at' || $c === 'updated_at' ? 'UTC_TIMESTAMP()' : '?', $columns);

        $params = [];
        foreach ($fields as $col => $val) {
            if ($col !== 'created_at' && $col !== 'updated_at') {
                $params[] = $val;
            }
        }

        $this->db->execute(
            'INSERT INTO clients (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', $placeholders) . ')',
            $params
        );
        return $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $fields = $this->filter($data);
        if ($fields === []) {
            return;
        }

        $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($fields)));
        $params = array_values($fields);
        $params[] = $id;

        $this->db->execute(
            "UPDATE clients SET {$set}, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL",
            $params
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute('UPDATE clients SET deleted_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function linkedAccounts(int $clientId): array
    {
        return $this->db->all(
            'SELECT id, domain, username, package, suspended, ssl_status FROM whm_accounts WHERE client_id = ? AND deleted_at IS NULL ORDER BY domain',
            [$clientId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function linkedDomains(int $clientId): array
    {
        return $this->db->all(
            'SELECT id, domain, status, expires_at FROM domains WHERE client_id = ? AND deleted_at IS NULL ORDER BY domain',
            [$clientId]
        );
    }

    /**
     * Financial roll-up from subscriptions for a client.
     *
     * @return array{monthly:float, annual:float, outstanding:float}
     */
    public function financials(int $clientId): array
    {
        $row = $this->db->first(
            "SELECT
                COALESCE(SUM(CASE billing_cycle
                    WHEN 'monthly'  THEN price
                    WHEN 'quarterly' THEN price/3
                    WHEN 'biannual' THEN price/6
                    WHEN 'annual'   THEN price/12
                    ELSE 0 END), 0) AS monthly,
                COALESCE(SUM(outstanding), 0) AS outstanding
             FROM subscriptions
             WHERE client_id = ? AND deleted_at IS NULL AND payment_status <> 'cancelled'",
            [$clientId]
        ) ?? [];

        $monthly = round((float) ($row['monthly'] ?? 0), 2);
        return [
            'monthly'     => $monthly,
            'annual'      => round($monthly * 12, 2),
            'outstanding' => round((float) ($row['outstanding'] ?? 0), 2),
        ];
    }

    /**
     * Unlinked cached accounts, optionally ranked by likely match to a client.
     *
     * @return array<int, array<string, mixed>>
     */
    public function unlinkedAccounts(?string $emailHint = null): array
    {
        return $this->db->all(
            'SELECT id, domain, username, email FROM whm_accounts
              WHERE client_id IS NULL AND deleted_at IS NULL
           ORDER BY domain LIMIT 500'
        );
    }

    /**
     * Link (or unlink) a WHM account to a client. Administrator-confirmed.
     */
    public function linkAccount(int $accountId, ?int $clientId): void
    {
        $this->db->execute(
            'UPDATE whm_accounts SET client_id = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$clientId, $accountId]
        );
    }

    public function addNote(int $clientId, ?int $userId, string $body): void
    {
        $this->db->execute(
            'INSERT INTO client_notes (client_id, user_id, body, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$clientId, $userId, $body]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function notes(int $clientId): array
    {
        return $this->db->all(
            'SELECT n.*, u.name AS author FROM client_notes n
          LEFT JOIN users u ON u.id = n.user_id
             WHERE n.client_id = ? ORDER BY n.created_at DESC',
            [$clientId]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        $out = [];
        foreach (self::FILLABLE as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                $out[$field] = is_string($value) && trim($value) === '' ? null : $value;
            }
        }
        return $out;
    }
}
