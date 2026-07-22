<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Aggregates read-only security signals for the Security Centre. It only
 * reports facts the application actually knows — it never fabricates
 * malware/WAF findings.
 */
final class SecurityRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        return [
            'login_failures_24h' => (int) ($this->db->first(
                "SELECT COUNT(*) AS c FROM login_attempts WHERE successful = 0 AND created_at >= (UTC_TIMESTAMP() - INTERVAL 24 HOUR)"
            )['c'] ?? 0),
            'locked_accounts' => (int) ($this->db->first(
                "SELECT COUNT(*) AS c FROM users WHERE locked_until IS NOT NULL AND locked_until > UTC_TIMESTAMP() AND deleted_at IS NULL"
            )['c'] ?? 0),
            'whm_auth_failures' => (int) ($this->db->first(
                "SELECT COUNT(*) AS c FROM whm_api_capabilities WHERE status = 'auth_failed'"
            )['c'] ?? 0),
            'whm_permission_failures' => (int) ($this->db->first(
                "SELECT COUNT(*) AS c FROM whm_api_capabilities WHERE status = 'permission_denied'"
            )['c'] ?? 0),
            'ssl_issues' => (int) ($this->db->first(
                "SELECT COUNT(*) AS c FROM whm_ssl_certificates WHERE status IN ('expired','invalid','missing') OR days_remaining < 0"
            )['c'] ?? 0),
            'sites_offline' => (int) ($this->db->first(
                "SELECT COUNT(*) AS c FROM uptime_monitors WHERE enabled = 1 AND current_status IN ('offline','ssl_problem')"
            )['c'] ?? 0),
            'high_disk_accounts' => (int) ($this->db->first(
                "SELECT COUNT(*) AS c FROM whm_account_usage u JOIN whm_accounts a ON a.id = u.account_id
                  WHERE a.deleted_at IS NULL AND u.disk_limit_mb > 0 AND u.disk_used_mb / u.disk_limit_mb >= 0.8"
            )['c'] ?? 0),
            'suspended_accounts' => (int) ($this->db->first(
                "SELECT COUNT(*) AS c FROM whm_accounts WHERE suspended = 1 AND deleted_at IS NULL"
            )['c'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentLoginFailures(int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        return $this->db->all(
            "SELECT email, ip_address, created_at FROM login_attempts
              WHERE successful = 0 ORDER BY id DESC LIMIT {$limit}"
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lockedAccounts(): array
    {
        return $this->db->all(
            "SELECT name, email, locked_until FROM users
              WHERE locked_until IS NOT NULL AND locked_until > UTC_TIMESTAMP() AND deleted_at IS NULL
           ORDER BY locked_until DESC"
        );
    }
}
