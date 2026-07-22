<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Read-only report datasets. Each method returns a [headers, rows] pair ready
 * for CSV export or on-screen preview.
 */
final class ReportRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return array{headers:array<int,string>, rows:array<int,array<int,mixed>>}
     */
    public function hostingInventory(): array
    {
        $rows = $this->db->all(
            "SELECT a.domain, a.username, a.owner, a.package, a.ip_address, a.suspended, a.whm_created_at
               FROM whm_accounts a WHERE a.deleted_at IS NULL ORDER BY a.domain"
        );
        return [
            ['Domain', 'Username', 'Owner', 'Package', 'IP', 'Suspended', 'Created'],
            array_map(static fn ($r) => [$r['domain'], $r['username'], $r['owner'], $r['package'], $r['ip_address'], $r['suspended'] ? 'Yes' : 'No', $r['whm_created_at']], $rows),
        ];
    }

    /**
     * @return array{headers:array<int,string>, rows:array<int,array<int,mixed>>}
     */
    public function diskUsage(): array
    {
        $rows = $this->db->all(
            "SELECT a.domain, a.username, COALESCE(u.disk_used_mb,0) AS used, COALESCE(u.disk_limit_mb,0) AS lim
               FROM whm_accounts a LEFT JOIN whm_account_usage u ON u.account_id = a.id
              WHERE a.deleted_at IS NULL ORDER BY used DESC"
        );
        return [
            ['Domain', 'Username', 'Disk used (MB)', 'Disk limit (MB)', 'Usage %'],
            array_map(static function ($r) {
                $pct = (int) $r['lim'] > 0 ? round((int) $r['used'] / (int) $r['lim'] * 100, 1) : 0;
                return [$r['domain'], $r['username'], $r['used'], $r['lim'], $pct];
            }, $rows),
        ];
    }

    /**
     * @return array{headers:array<int,string>, rows:array<int,array<int,mixed>>}
     */
    public function bandwidthUsage(): array
    {
        $rows = $this->db->all(
            "SELECT a.domain, a.username, COALESCE(u.bandwidth_used_mb,0) AS used, COALESCE(u.bandwidth_limit_mb,0) AS lim
               FROM whm_accounts a LEFT JOIN whm_account_usage u ON u.account_id = a.id
              WHERE a.deleted_at IS NULL ORDER BY used DESC"
        );
        return [
            ['Domain', 'Username', 'Bandwidth used (MB)', 'Bandwidth limit (MB)', 'Usage %'],
            array_map(static function ($r) {
                $pct = (int) $r['lim'] > 0 ? round((int) $r['used'] / (int) $r['lim'] * 100, 1) : 0;
                return [$r['domain'], $r['username'], $r['used'], $r['lim'], $pct];
            }, $rows),
        ];
    }

    /**
     * @return array{headers:array<int,string>, rows:array<int,array<int,mixed>>}
     */
    public function sslExpiry(): array
    {
        $rows = $this->db->all(
            "SELECT domain, issuer, valid_to, days_remaining, status FROM whm_ssl_certificates ORDER BY (days_remaining IS NULL), days_remaining ASC"
        );
        return [
            ['Domain', 'Issuer', 'Expires', 'Days remaining', 'Status'],
            array_map(static fn ($r) => [$r['domain'], $r['issuer'], $r['valid_to'], $r['days_remaining'], $r['status']], $rows),
        ];
    }

    /**
     * @return array{headers:array<int,string>, rows:array<int,array<int,mixed>>}
     */
    public function domainExpiry(): array
    {
        $rows = $this->db->all(
            "SELECT domain, registrar, expires_at, status FROM domains WHERE deleted_at IS NULL ORDER BY (expires_at IS NULL), expires_at ASC"
        );
        return [
            ['Domain', 'Registrar', 'Expires', 'Status'],
            array_map(static fn ($r) => [$r['domain'], $r['registrar'], $r['expires_at'], $r['status']], $rows),
        ];
    }

    /**
     * @return array{headers:array<int,string>, rows:array<int,array<int,mixed>>}
     */
    public function clientRenewals(): array
    {
        $rows = $this->db->all(
            "SELECT COALESCE(NULLIF(c.company_name,''), CONCAT_WS(' ', c.first_name, c.last_name)) AS client,
                    s.description, s.price, s.billing_cycle, s.next_billing_at, s.payment_status
               FROM subscriptions s LEFT JOIN clients c ON c.id = s.client_id
              WHERE s.deleted_at IS NULL AND s.next_billing_at IS NOT NULL
           ORDER BY s.next_billing_at ASC"
        );
        return [
            ['Client', 'Description', 'Price', 'Cycle', 'Next billing', 'Status'],
            array_map(static fn ($r) => [$r['client'], $r['description'], $r['price'], $r['billing_cycle'], $r['next_billing_at'], $r['payment_status']], $rows),
        ];
    }

    /**
     * @return array{headers:array<int,string>, rows:array<int,array<int,mixed>>}
     */
    public function overduePayments(): array
    {
        $rows = $this->db->all(
            "SELECT COALESCE(NULLIF(c.company_name,''), CONCAT_WS(' ', c.first_name, c.last_name)) AS client,
                    s.description, s.outstanding, s.payment_status, s.next_billing_at
               FROM subscriptions s LEFT JOIN clients c ON c.id = s.client_id
              WHERE s.deleted_at IS NULL AND (s.payment_status = 'overdue' OR s.outstanding > 0)
           ORDER BY s.outstanding DESC"
        );
        return [
            ['Client', 'Description', 'Outstanding', 'Status', 'Due'],
            array_map(static fn ($r) => [$r['client'], $r['description'], $r['outstanding'], $r['payment_status'], $r['next_billing_at']], $rows),
        ];
    }

    /**
     * @return array{headers:array<int,string>, rows:array<int,array<int,mixed>>}
     */
    public function websiteUptime(): array
    {
        $rows = $this->db->all(
            "SELECT label, url, current_status, last_status_code, last_response_ms, failure_count, last_checked_at
               FROM uptime_monitors ORDER BY label"
        );
        return [
            ['Label', 'URL', 'Status', 'Last code', 'Response (ms)', 'Failures', 'Last checked'],
            array_map(static fn ($r) => [$r['label'], $r['url'], $r['current_status'], $r['last_status_code'], $r['last_response_ms'], $r['failure_count'], $r['last_checked_at']], $rows),
        ];
    }

    /**
     * @return array{headers:array<int,string>, rows:array<int,array<int,mixed>>}
     */
    public function syncFailures(): array
    {
        $rows = $this->db->all(
            "SELECT id, sync_type, status, records_failed, message, finished_at
               FROM whm_sync_runs WHERE status IN ('failed','partial') ORDER BY id DESC LIMIT 200"
        );
        return [
            ['Run', 'Type', 'Status', 'Failed', 'Message', 'Finished'],
            array_map(static fn ($r) => [$r['id'], $r['sync_type'], $r['status'], $r['records_failed'], $r['message'], $r['finished_at']], $rows),
        ];
    }

    /**
     * @return array{headers:array<int,string>, rows:array<int,array<int,mixed>>}
     */
    public function clientHealth(): array
    {
        $rows = $this->db->all(
            "SELECT a.domain, h.score, h.band, h.computed_at
               FROM health_scores h
          LEFT JOIN whm_accounts a ON a.id = h.subject_id
              WHERE h.subject_type = 'account'
           ORDER BY (h.score IS NULL), h.score ASC"
        );
        return [
            ['Domain', 'Score', 'Band', 'Computed'],
            array_map(static fn ($r) => [$r['domain'], $r['score'], $r['band'], $r['computed_at']], $rows),
        ];
    }
}
