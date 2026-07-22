<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Exceptions\HttpException;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Helpers\Csv;
use ParagonHostOps\Repositories\ReportRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Auth;

/**
 * Reports with CSV export. Financial reports additionally require the
 * finance.view permission; export requires reports.export.
 */
final class ReportsController extends Controller
{
    /**
     * Report registry: key => [label, repo method, financial?].
     *
     * @var array<string, array{0:string, 1:string, 2:bool}>
     */
    private const REPORTS = [
        'hosting-inventory' => ['Hosting account inventory', 'hostingInventory', false],
        'disk-usage'        => ['Disk usage', 'diskUsage', false],
        'bandwidth-usage'   => ['Bandwidth usage', 'bandwidthUsage', false],
        'ssl-expiry'        => ['SSL expiry', 'sslExpiry', false],
        'domain-expiry'     => ['Domain expiry', 'domainExpiry', false],
        'website-uptime'    => ['Website uptime', 'websiteUptime', false],
        'client-health'     => ['Client health', 'clientHealth', false],
        'sync-failures'     => ['Synchronisation failures', 'syncFailures', false],
        'client-renewals'   => ['Client renewals', 'clientRenewals', true],
        'overdue-payments'  => ['Overdue payments', 'overduePayments', true],
    ];

    public function __construct(
        private ReportRepository $reports,
        private Auth $auth,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $available = [];
        foreach (self::REPORTS as $key => [$label, , $financial]) {
            if ($financial && !$this->auth->can('finance.view')) {
                continue;
            }
            $available[$key] = ['label' => $label, 'financial' => $financial];
        }

        return $this->view('reports.index', [
            'title'     => 'Reports',
            'reports'   => $available,
            'canExport' => $this->auth->can('reports.export'),
        ]);
    }

    public function export(Request $request, array $params): Response
    {
        $key = (string) ($params['type'] ?? '');

        if (!isset(self::REPORTS[$key])) {
            throw new HttpException(404, 'Unknown report.');
        }

        [$label, $method, $financial] = self::REPORTS[$key];

        if (!$this->auth->can('reports.export')) {
            throw new HttpException(403, 'You do not have permission to export reports.');
        }
        if ($financial && !$this->auth->can('finance.view')) {
            throw new HttpException(403, 'You do not have permission to export financial reports.');
        }

        /** @var array{headers:array<int,string>, rows:array<int,array<int,mixed>>} $data */
        $data = $this->reports->{$method}();
        $csv  = Csv::build($data['headers'] ?? $data[0], $data['rows'] ?? $data[1]);

        $this->audit->record('report.exported', "Exported report: {$label}");

        $filename = 'paragon-hostops-' . $key . '-' . date('Ymd') . '.csv';

        return (new Response($csv, 200))
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-store');
    }
}
