<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\AuditLogRepository;
use ParagonHostOps\Repositories\SecurityRepository;

/**
 * Security Centre — an internal overview of security-relevant signals. It does
 * not claim malware/WAF detection; those integrations show "Integration not
 * configured" until actually wired up.
 */
final class SecurityController extends Controller
{
    public function __construct(
        private SecurityRepository $security,
        private AuditLogRepository $audit,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        return $this->view('security.index', [
            'title'         => 'Security Centre',
            'summary'       => $this->security->summary(),
            'loginFailures' => $this->security->recentLoginFailures(10),
            'locked'        => $this->security->lockedAccounts(),
            'recentAudit'   => $this->audit->recent(15),
        ]);
    }
}
