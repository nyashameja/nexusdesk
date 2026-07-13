<?php

declare(strict_types=1);

namespace App\Controllers\Web\Manage;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\ReportRepositoryInterface;

/**
 * Manager dashboard: department health, SLA compliance, and volume trend at a
 * glance. Links into the detailed report pages.
 */
final class DashboardController extends Controller
{
    public function __construct(private readonly ReportRepositoryInterface $reports)
    {
    }

    public function index(Request $request): Response
    {
        $days = 30;
        return $this->view('manage.dashboard', [
            'title'      => 'Manager dashboard',
            'active'     => 'manage',
            'summary'    => $this->reports->summary($days),
            'volume'     => $this->reports->ticketVolume($days),
            'departments' => $this->reports->departmentPerformance($days),
            'statuses'   => $this->reports->statusDistribution(),
        ]);
    }
}
