<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\AccountRepository;
use ParagonHostOps\Services\Whm\WhmApiClient;

/**
 * Executive overview dashboard. Reads only cached data so the page loads fast
 * and stays available even when WHM is unreachable.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private AccountRepository $accounts,
        private WhmApiClient $whm,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $summary = $this->accounts->summary();
        $ssl     = $this->accounts->sslSummary();

        return $this->view('dashboard.index', [
            'title'        => 'Overview',
            'summary'      => $summary,
            'ssl'          => $ssl,
            'packageCount' => $this->accounts->packageCount(),
            'lastSync'     => $this->accounts->lastSyncAt(),
            'whmConfigured'=> $this->whm->isConfigured(),
        ]);
    }
}
