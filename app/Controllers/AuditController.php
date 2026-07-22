<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\AuditLogRepository;

/**
 * Audit trail viewer (read-only). Requires audit.view.
 */
final class AuditController extends Controller
{
    private const PER_PAGE = 40;

    public function __construct(private AuditLogRepository $audit)
    {
    }

    public function index(Request $request, array $params): Response
    {
        $action = (string) $request->query('action', '');
        $page   = max(1, (int) $request->query('page', 1));
        $result = $this->audit->paginate($action, $page, self::PER_PAGE);

        return $this->view('audit.index', [
            'title'   => 'Audit Logs',
            'logs'    => $result['rows'],
            'actions' => $this->audit->distinctActions(),
            'action'  => $action,
            'page'    => $page,
            'total'   => $result['total'],
            'pages'   => (int) ceil($result['total'] / self::PER_PAGE),
        ]);
    }
}
