<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Exceptions\HttpException;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\AccountRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Whm\SyncService;

/**
 * Hosting accounts: searchable/sortable/paginated table (cached data) and the
 * read-only account-detail screen. No destructive actions in Version 1.
 */
final class AccountsController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private AccountRepository $accounts,
        private SyncService $sync,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $filters = [
            'q'         => (string) $request->query('q', ''),
            'package'   => (string) $request->query('package', ''),
            'status'    => (string) $request->query('status', ''),
            'disk'      => (string) $request->query('disk', ''),
            'bandwidth' => (string) $request->query('bandwidth', ''),
            'ssl'       => (string) $request->query('ssl', ''),
            'linked'    => (string) $request->query('linked', ''),
        ];

        $sort = (string) $request->query('sort', 'domain');
        $dir  = (string) $request->query('dir', 'asc');
        $page = max(1, (int) $request->query('page', 1));

        $result = $this->accounts->search($filters, $sort, $dir, $page, self::PER_PAGE);
        $total  = $result['total'];

        return $this->view('accounts.index', [
            'title'    => 'Hosting Accounts',
            'accounts' => $result['rows'],
            'filters'  => $filters,
            'sort'     => $sort,
            'dir'      => $dir,
            'page'     => $page,
            'perPage'  => self::PER_PAGE,
            'total'    => $total,
            'pages'    => (int) ceil($total / self::PER_PAGE),
            'packages' => $this->accounts->distinctPackages(),
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $account = $this->accounts->detail($id);

        if ($account === null) {
            throw new HttpException(404, 'Hosting account not found.');
        }

        return $this->view('accounts.show', [
            'title'   => $account['domain'] ?? 'Account',
            'account' => $account,
        ]);
    }

    /**
     * Read-only "Refresh account data" action — re-syncs a single account from
     * WHM. No destructive capability.
     */
    public function refresh(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $account = $this->accounts->detail($id);

        if ($account === null) {
            throw new HttpException(404, 'Hosting account not found.');
        }

        $result = $this->sync->syncAccount((string) $account['username']);
        $this->audit->record('account.refreshed', 'Refreshed account ' . $account['username'], 'whm_account', $id);

        if ($result->status() === 'completed') {
            $this->session()->flash('success', 'Account data refreshed from WHM.');
        } else {
            $this->session()->flash('warning', 'Refresh completed with issues: ' . $result->summary());
        }

        return $this->redirect('/accounts/' . $id);
    }
}
