<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Data access for cached SSL certificate records.
 */
final class SslRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * SSL certificates with their linked account, filtered and paginated.
     *
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function listing(string $filter, string $search, int $page, int $perPage): array
    {
        $conditions = ['1=1'];
        $args = [];

        if ($search !== '') {
            $conditions[] = '(s.domain LIKE ? OR a.username LIKE ?)';
            $like = '%' . $search . '%';
            array_push($args, $like, $like);
        }
        if ($filter === 'attention') {
            $conditions[] = "(s.status IN ('expired','invalid','missing') OR s.days_remaining <= 30)";
        } elseif ($filter !== '') {
            $conditions[] = 's.status = ?';
            $args[] = $filter;
        }

        $where   = implode(' AND ', $conditions);
        $page    = max(1, $page);
        $perPage = max(5, min($perPage, 100));
        $offset  = ($page - 1) * $perPage;

        $total = (int) ($this->db->first(
            "SELECT COUNT(*) AS c FROM whm_ssl_certificates s LEFT JOIN whm_accounts a ON a.id = s.account_id WHERE {$where}",
            $args
        )['c'] ?? 0);

        $rows = $this->db->all(
            "SELECT s.*, a.username AS account_username, a.id AS acct_id
               FROM whm_ssl_certificates s
          LEFT JOIN whm_accounts a ON a.id = s.account_id
              WHERE {$where}
           ORDER BY (s.days_remaining IS NULL), s.days_remaining ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $args
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Counts by attention band for the SSL centre header.
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        $row = $this->db->first(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'valid' AND days_remaining > 60 THEN 1 ELSE 0 END) AS healthy,
                SUM(CASE WHEN status = 'valid' AND days_remaining BETWEEN 31 AND 60 THEN 1 ELSE 0 END) AS d60,
                SUM(CASE WHEN status = 'expiring' OR (status='valid' AND days_remaining BETWEEN 15 AND 30) THEN 1 ELSE 0 END) AS d30,
                SUM(CASE WHEN days_remaining BETWEEN 0 AND 14 AND status <> 'expired' THEN 1 ELSE 0 END) AS d14,
                SUM(CASE WHEN status = 'expired' OR days_remaining < 0 THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN status IN ('invalid','missing') THEN 1 ELSE 0 END) AS issues
             FROM whm_ssl_certificates"
        ) ?? [];

        return array_map(static fn ($v): int => (int) $v, [
            'total'   => $row['total'] ?? 0,
            'healthy' => $row['healthy'] ?? 0,
            'd60'     => $row['d60'] ?? 0,
            'd30'     => $row['d30'] ?? 0,
            'd14'     => $row['d14'] ?? 0,
            'expired' => $row['expired'] ?? 0,
            'issues'  => $row['issues'] ?? 0,
        ]);
    }

    /**
     * @param array<string, mixed> $data Normalised SSL fields.
     */
    public function upsert(?int $accountId, array $data): void
    {
        $existing = $this->db->first(
            'SELECT id FROM whm_ssl_certificates WHERE domain = ? LIMIT 1',
            [$data['domain']]
        );

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE whm_ssl_certificates SET account_id = ?, issuer = ?, cert_type = ?, valid_from = ?,
                        valid_to = ?, days_remaining = ?, covered_hosts = ?, status = ?, last_checked_at = UTC_TIMESTAMP()
                 WHERE id = ?',
                [
                    $accountId, $data['issuer'], $data['cert_type'], $data['valid_from'],
                    $data['valid_to'], $data['days_remaining'], $data['covered_hosts'], $data['status'],
                    (int) $existing['id'],
                ]
            );
            return;
        }

        $this->db->execute(
            'INSERT INTO whm_ssl_certificates
                (account_id, domain, issuer, cert_type, valid_from, valid_to, days_remaining, covered_hosts, status, last_checked_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                $accountId, $data['domain'], $data['issuer'], $data['cert_type'], $data['valid_from'],
                $data['valid_to'], $data['days_remaining'], $data['covered_hosts'], $data['status'],
            ]
        );
    }
}
