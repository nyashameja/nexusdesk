<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\ReportRepositoryInterface;

/**
 * Aggregate reporting queries over the ticket data. All are parameterised and
 * bounded by a rolling window (days) so they stay cheap on shared hosting.
 */
final class MySqlReportRepository extends MySqlRepository implements ReportRepositoryInterface
{
    public function summary(int $days): array
    {
        $row = $this->db->selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved,
                AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) AS avg_resolution_hours
             FROM tickets
             WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        ) ?? [];

        $open = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM tickets t JOIN ticket_statuses s ON s.id = t.status_id
             WHERE t.deleted_at IS NULL AND s.is_open_state = 1"
        );

        $sla = $this->slaCompliance($days);
        $csat = $this->csatDistribution($days);

        return [
            'total'                => (int) ($row['total'] ?? 0),
            'resolved'             => (int) ($row['resolved'] ?? 0),
            'open'                 => $open,
            'avg_resolution_hours' => round((float) ($row['avg_resolution_hours'] ?? 0), 1),
            'sla_compliance'       => $sla['pct'],
            'csat_average'         => $csat['average'],
        ];
    }

    public function ticketVolume(int $days): array
    {
        $created = $this->db->select(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM tickets
             WHERE deleted_at IS NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)",
            [$days]
        );
        $resolved = $this->db->select(
            "SELECT DATE(resolved_at) AS d, COUNT(*) AS c FROM tickets
             WHERE resolved_at IS NOT NULL AND resolved_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(resolved_at)",
            [$days]
        );

        $createdMap = [];
        foreach ($created as $r) { $createdMap[(string) $r['d']] = (int) $r['c']; }
        $resolvedMap = [];
        foreach ($resolved as $r) { $resolvedMap[(string) $r['d']] = (int) $r['c']; }

        $labels = $createdSeries = $resolvedSeries = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($day));
            $createdSeries[] = $createdMap[$day] ?? 0;
            $resolvedSeries[] = $resolvedMap[$day] ?? 0;
        }

        return ['labels' => $labels, 'created' => $createdSeries, 'resolved' => $resolvedSeries];
    }

    public function statusDistribution(): array
    {
        $rows = $this->db->select(
            "SELECT s.name, COUNT(t.id) AS c
             FROM ticket_statuses s
             LEFT JOIN tickets t ON t.status_id = s.id AND t.deleted_at IS NULL
             GROUP BY s.id, s.name ORDER BY s.sort_order"
        );
        $labels = $counts = [];
        foreach ($rows as $r) { $labels[] = (string) $r['name']; $counts[] = (int) $r['c']; }
        return ['labels' => $labels, 'counts' => $counts];
    }

    public function agentPerformance(int $days): array
    {
        return $this->db->select(
            "SELECT TRIM(CONCAT(u.first_name,' ',u.last_name)) AS name,
                    COUNT(t.id) AS assigned,
                    SUM(CASE WHEN t.resolved_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved,
                    ROUND(AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END), 1) AS avg_hours,
                    ROUND((SELECT AVG(r.rating) FROM ticket_ratings r WHERE r.ticket_id IN
                        (SELECT id FROM tickets WHERE assigned_agent_id = u.id)), 2) AS csat
             FROM users u
             JOIN roles ro ON ro.id = u.role_id AND ro.slug IN ('agent','manager','administrator')
             LEFT JOIN tickets t ON t.assigned_agent_id = u.id AND t.deleted_at IS NULL
                  AND t.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             WHERE u.deleted_at IS NULL
             GROUP BY u.id, name
             HAVING assigned > 0
             ORDER BY resolved DESC",
            [$days]
        );
    }

    public function departmentPerformance(int $days): array
    {
        return $this->db->select(
            "SELECT d.name,
                    COUNT(t.id) AS total,
                    SUM(CASE WHEN t.resolved_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved
             FROM departments d
             LEFT JOIN tickets t ON t.department_id = d.id AND t.deleted_at IS NULL
                  AND t.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY d.id, d.name ORDER BY total DESC",
            [$days]
        );
    }

    public function csatDistribution(int $days): array
    {
        $rows = $this->db->select(
            "SELECT rating, COUNT(*) AS c FROM ticket_ratings
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY rating",
            [$days]
        );
        $map = [];
        foreach ($rows as $r) { $map[(int) $r['rating']] = (int) $r['c']; }
        $labels = ['1★', '2★', '3★', '4★', '5★'];
        $counts = [];
        $sum = $n = 0;
        for ($i = 1; $i <= 5; $i++) {
            $c = $map[$i] ?? 0;
            $counts[] = $c;
            $sum += $i * $c;
            $n += $c;
        }
        return ['labels' => $labels, 'counts' => $counts, 'average' => $n > 0 ? round($sum / $n, 2) : 0.0];
    }

    public function slaCompliance(int $days): array
    {
        $row = $this->db->selectOne(
            "SELECT
                SUM(CASE WHEN resolved_at IS NOT NULL AND (due_resolution_at IS NULL OR resolved_at <= due_resolution_at) THEN 1 ELSE 0 END) AS compliant,
                SUM(CASE WHEN sla_resolution_breached = 1 OR (resolved_at IS NOT NULL AND due_resolution_at IS NOT NULL AND resolved_at > due_resolution_at) THEN 1 ELSE 0 END) AS breached
             FROM tickets
             WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        ) ?? [];
        $compliant = (int) ($row['compliant'] ?? 0);
        $breached = (int) ($row['breached'] ?? 0);
        $total = $compliant + $breached;
        return [
            'compliant' => $compliant,
            'breached'  => $breached,
            'pct'       => $total > 0 ? round($compliant / $total * 100, 1) : 100.0,
        ];
    }
}
