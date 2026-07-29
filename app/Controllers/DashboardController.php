<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\AccountRepository;
use ParagonHostOps\Repositories\DomainRepository;
use ParagonHostOps\Services\Auth;
use ParagonHostOps\Services\Whm\WhmApiClient;

/**
 * Executive overview dashboard. Reads only cached data so the page loads fast
 * and stays available even when WHM is unreachable.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private AccountRepository $accounts,
        private DomainRepository $domains,
        private WhmApiClient $whm,
        private Auth $auth,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $summary = $this->accounts->summary();
        $ssl     = $this->accounts->sslSummary();

        // Chart datasets, assembled server-side and passed to Chart.js via a
        // JSON data island (keeps all scripts external for the strict CSP).
        $charts = [
            'diskByAccount'      => $this->accounts->topDiskUsage(8),
            'bandwidthByAccount' => $this->accounts->topBandwidthUsage(8),
            'byPackage'          => $this->accounts->countByPackage(),
            'activeSuspended'    => ['active' => $summary['active'], 'suspended' => $summary['suspended']],
            'sslDistribution'    => $this->accounts->sslDistribution(),
            'createdOverTime'    => $this->accounts->createdOverTime(),
        ];

        return $this->view('dashboard.index', [
            'title'         => 'Overview',
            'summary'       => $summary,
            'ssl'           => $ssl,
            'packageCount'  => $this->accounts->packageCount(),
            'lastSync'      => $this->accounts->lastSyncAt(),
            'whmConfigured' => $this->whm->isConfigured(),
            'attention'     => $this->accounts->attentionList(10),
            'charts'        => $charts,
            'domainExpiry'  => $this->auth->can('domains.view') ? $this->domains->expirySummary(6) : null,
            'scripts'       => ['assets/js/chart.umd.js', 'assets/js/dashboard.js'],
        ]);
    }
}
