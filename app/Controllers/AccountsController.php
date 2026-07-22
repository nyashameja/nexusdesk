<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Database;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;

/**
 * Hosting accounts listing (reads cached data only in Version 1).
 *
 * The full searchable/sortable/paginated table and account-detail screen are
 * completed in the hosting-dashboard phase; this provides the route target and
 * a working cached listing scaffold.
 */
final class AccountsController extends Controller
{
    public function __construct(private Database $db)
    {
    }

    public function index(Request $request, array $params): Response
    {
        $search = trim((string) $request->query('q', ''));
        $page   = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $where  = 'WHERE a.deleted_at IS NULL';
        $args   = [];

        if ($search !== '') {
            $where .= ' AND (a.domain LIKE ? OR a.username LIKE ? OR a.email LIKE ?)';
            $like = '%' . $search . '%';
            $args = [$like, $like, $like];
        }

        $total = (int) ($this->db->first("SELECT COUNT(*) AS c FROM whm_accounts a {$where}", $args)['c'] ?? 0);

        $accounts = $this->db->all(
            "SELECT a.*, u.disk_used_mb, u.disk_limit_mb, u.bandwidth_used_mb, u.bandwidth_limit_mb
               FROM whm_accounts a
          LEFT JOIN whm_account_usage u ON u.account_id = a.id
               {$where}
           ORDER BY a.domain ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $args
        );

        return $this->view('accounts.index', [
            'title'    => 'Hosting Accounts',
            'accounts' => $accounts,
            'search'   => $search,
            'page'     => $page,
            'perPage'  => $perPage,
            'total'    => $total,
            'pages'    => (int) ceil($total / $perPage),
        ]);
    }
}
