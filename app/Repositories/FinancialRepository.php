<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Financial data access. Recurring revenue is modelled by subscriptions;
 * one-off/categorised income by financial_entries. All money is DECIMAL.
 */
final class FinancialRepository
{
    private const SUB_FILLABLE = [
        'client_id', 'account_id', 'description', 'price', 'internal_cost',
        'billing_cycle', 'next_billing_at', 'last_payment_at', 'payment_status', 'outstanding',
    ];

    /** SQL expression normalising a subscription price to a monthly figure. */
    private const MONTHLY_EXPR = "CASE billing_cycle
        WHEN 'monthly'   THEN price
        WHEN 'quarterly' THEN price/3
        WHEN 'biannual'  THEN price/6
        WHEN 'annual'    THEN price/12
        ELSE 0 END";

    public function __construct(private Database $db)
    {
    }

    /**
     * Headline dashboard metrics.
     *
     * @return array<string, float|int>
     */
    public function metrics(): array
    {
        $mrr = (float) ($this->db->first(
            "SELECT COALESCE(SUM({$this->monthly()}),0) AS m
               FROM subscriptions
              WHERE deleted_at IS NULL AND payment_status NOT IN ('cancelled','complimentary')"
        )['m'] ?? 0);

        $cost = (float) ($this->db->first(
            "SELECT COALESCE(SUM(CASE billing_cycle
                WHEN 'monthly' THEN internal_cost WHEN 'quarterly' THEN internal_cost/3
                WHEN 'biannual' THEN internal_cost/6 WHEN 'annual' THEN internal_cost/12 ELSE 0 END),0) AS c
               FROM subscriptions WHERE deleted_at IS NULL AND internal_cost IS NOT NULL
                 AND payment_status NOT IN ('cancelled','complimentary')"
        )['c'] ?? 0);

        $outstanding = (float) ($this->db->first(
            "SELECT COALESCE(SUM(outstanding),0) AS o FROM subscriptions WHERE deleted_at IS NULL"
        )['o'] ?? 0);

        $renewalsThis = (int) ($this->db->first(
            "SELECT COUNT(*) AS c FROM subscriptions
              WHERE deleted_at IS NULL AND next_billing_at IS NOT NULL
                AND YEAR(next_billing_at) = YEAR(UTC_DATE()) AND MONTH(next_billing_at) = MONTH(UTC_DATE())"
        )['c'] ?? 0);

        $renewalsNext = (int) ($this->db->first(
            "SELECT COUNT(*) AS c FROM subscriptions
              WHERE deleted_at IS NULL AND next_billing_at IS NOT NULL
                AND next_billing_at BETWEEN (UTC_DATE() + INTERVAL 1 MONTH - INTERVAL DAYOFMONTH(UTC_DATE())-1 DAY)
                                        AND (UTC_DATE() + INTERVAL 2 MONTH)"
        )['c'] ?? 0);

        $activeClients = (int) ($this->db->first(
            "SELECT COUNT(DISTINCT client_id) AS c FROM subscriptions
              WHERE deleted_at IS NULL AND payment_status IN ('paid','due','partial')"
        )['c'] ?? 0);

        $overdueClients = (int) ($this->db->first(
            "SELECT COUNT(DISTINCT client_id) AS c FROM subscriptions
              WHERE deleted_at IS NULL AND payment_status = 'overdue'"
        )['c'] ?? 0);

        return [
            'mrr'             => round($mrr, 2),
            'arr'             => round($mrr * 12, 2),
            'gross_profit'    => round($mrr - $cost, 2),
            'outstanding'     => round($outstanding, 2),
            'renewals_this'   => $renewalsThis,
            'renewals_next'   => $renewalsNext,
            'active_clients'  => $activeClients,
            'overdue_clients' => $overdueClients,
        ];
    }

    private function monthly(): string
    {
        return self::MONTHLY_EXPR;
    }

    /**
     * Monthly-normalised revenue grouped by service category.
     *
     * @return array<string, float>
     */
    public function revenueByCategory(): array
    {
        $rows = $this->db->all(
            "SELECT COALESCE(sv.category, 'hosting') AS category, COALESCE(SUM({$this->monthly()}),0) AS total
               FROM subscriptions s
          LEFT JOIN services sv ON sv.id = s.service_id
              WHERE s.deleted_at IS NULL AND s.payment_status NOT IN ('cancelled','complimentary')
           GROUP BY category"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['category']] = round((float) $r['total'], 2);
        }
        return $out;
    }

    /**
     * Count of subscriptions by payment status.
     *
     * @return array<string, int>
     */
    public function paymentStatusBreakdown(): array
    {
        $rows = $this->db->all(
            'SELECT payment_status, COUNT(*) AS c FROM subscriptions WHERE deleted_at IS NULL GROUP BY payment_status'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['payment_status']] = (int) $r['c'];
        }
        return $out;
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function subscriptions(string $status, int $page, int $perPage): array
    {
        $conditions = ['s.deleted_at IS NULL'];
        $args = [];
        if ($status !== '') {
            $conditions[] = 's.payment_status = ?';
            $args[] = $status;
        }
        $where   = implode(' AND ', $conditions);
        $page    = max(1, $page);
        $perPage = max(5, min($perPage, 100));
        $offset  = ($page - 1) * $perPage;

        $total = (int) ($this->db->first("SELECT COUNT(*) AS c FROM subscriptions s WHERE {$where}", $args)['c'] ?? 0);

        $rows = $this->db->all(
            "SELECT s.*, COALESCE(NULLIF(c.company_name,''), CONCAT_WS(' ', c.first_name, c.last_name)) AS client_name,
                    a.domain AS account_domain
               FROM subscriptions s
          LEFT JOIN clients c ON c.id = s.client_id
          LEFT JOIN whm_accounts a ON a.id = s.account_id
              WHERE {$where}
           ORDER BY (s.next_billing_at IS NULL), s.next_billing_at ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $args
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSubscription(int $id): ?array
    {
        return $this->db->first('SELECT * FROM subscriptions WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createSubscription(array $data): int
    {
        $fields = $this->filterSubscription($data);
        $columns = array_keys($fields);
        $this->db->execute(
            'INSERT INTO subscriptions (`' . implode('`,`', $columns) . '`, created_at, updated_at) VALUES ('
                . implode(',', array_fill(0, count($columns), '?')) . ', UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            array_values($fields)
        );
        return $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateSubscription(int $id, array $data): void
    {
        $fields = $this->filterSubscription($data);
        if ($fields === []) {
            return;
        }
        $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($fields)));
        $params = array_values($fields);
        $params[] = $id;
        $this->db->execute("UPDATE subscriptions SET {$set}, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL", $params);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterSubscription(array $data): array
    {
        $out = [];
        foreach (self::SUB_FILLABLE as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $v = $data[$f];
            if (in_array($f, ['client_id', 'account_id'], true)) {
                $out[$f] = $v !== '' && $v !== null ? (int) $v : null;
            } elseif (in_array($f, ['price', 'internal_cost', 'outstanding'], true)) {
                $out[$f] = $v !== '' && $v !== null ? (float) $v : ($f === 'price' || $f === 'outstanding' ? 0.0 : null);
            } elseif (in_array($f, ['next_billing_at', 'last_payment_at'], true)) {
                $out[$f] = $v !== '' && $v !== null ? $v : null;
            } else {
                $out[$f] = is_string($v) && trim($v) === '' ? null : $v;
            }
        }
        return $out;
    }
}
