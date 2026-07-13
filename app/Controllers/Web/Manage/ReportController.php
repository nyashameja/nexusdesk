<?php

declare(strict_types=1);

namespace App\Controllers\Web\Manage;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Export\Exporter;

/**
 * Reporting suite: ticket volume, SLA, agent performance, and CSAT — each with
 * Chart.js-style visuals and CSV/XLSX/PDF export.
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportRepositoryInterface $reports,
        private readonly Exporter $exporter,
    ) {
    }

    private function days(Request $request): int
    {
        $days = (int) $request->query('days', 30);
        return in_array($days, [7, 30, 90, 365], true) ? $days : 30;
    }

    public function index(Request $request): Response
    {
        $days = $this->days($request);
        return $this->view('manage.reports_index', [
            'title'   => 'Reports',
            'active'  => 'manage',
            'days'    => $days,
            'summary' => $this->reports->summary($days),
        ]);
    }

    public function volume(Request $request): Response
    {
        $days = $this->days($request);
        return $this->view('manage.report_volume', [
            'title'  => 'Ticket volume',
            'active' => 'manage',
            'days'   => $days,
            'volume' => $this->reports->ticketVolume($days),
        ]);
    }

    public function sla(Request $request): Response
    {
        $days = $this->days($request);
        return $this->view('manage.report_sla', [
            'title'       => 'SLA compliance',
            'active'      => 'manage',
            'days'        => $days,
            'sla'         => $this->reports->slaCompliance($days),
            'departments' => $this->reports->departmentPerformance($days),
        ]);
    }

    public function agents(Request $request): Response
    {
        $days = $this->days($request);
        return $this->view('manage.report_agents', [
            'title'  => 'Agent performance',
            'active' => 'manage',
            'days'   => $days,
            'agents' => $this->reports->agentPerformance($days),
        ]);
    }

    public function satisfaction(Request $request): Response
    {
        $days = $this->days($request);
        return $this->view('manage.report_csat', [
            'title'  => 'Customer satisfaction',
            'active' => 'manage',
            'days'   => $days,
            'csat'   => $this->reports->csatDistribution($days),
        ]);
    }

    /** Export a report as csv|xlsx|pdf. */
    public function export(Request $request, string $report): Response
    {
        $days = $this->days($request);
        $format = (string) $request->query('format', 'csv');
        $format = in_array($format, ['csv', 'xlsx', 'pdf'], true) ? $format : 'csv';

        [$title, $headers, $rows] = $this->dataset($report, $days);
        return $this->exporter->make($format, 'nexusdesk-' . $report, $title, $headers, $rows);
    }

    /** @return array{0:string,1:string[],2:array<int,array<int,scalar>>} */
    private function dataset(string $report, int $days): array
    {
        switch ($report) {
            case 'agents':
                $rows = array_map(static fn (array $a): array => [
                    $a['name'], (int) $a['assigned'], (int) $a['resolved'],
                    $a['avg_hours'] ?? 0, $a['csat'] ?? '—',
                ], $this->reports->agentPerformance($days));
                return ['Agent performance', ['Agent', 'Assigned', 'Resolved', 'Avg hours', 'CSAT'], $rows];

            case 'departments':
                $rows = array_map(static fn (array $d): array => [
                    $d['name'], (int) $d['total'], (int) $d['resolved'],
                ], $this->reports->departmentPerformance($days));
                return ['Department performance', ['Department', 'Total', 'Resolved'], $rows];

            case 'volume':
            default:
                $v = $this->reports->ticketVolume($days);
                $rows = [];
                foreach ($v['labels'] as $i => $label) {
                    $rows[] = [$label, (int) $v['created'][$i], (int) $v['resolved'][$i]];
                }
                return ['Ticket volume', ['Date', 'Created', 'Resolved'], $rows];
        }
    }
}
