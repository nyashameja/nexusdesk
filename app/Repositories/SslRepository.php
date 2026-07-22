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
