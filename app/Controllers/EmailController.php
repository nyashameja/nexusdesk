<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\AccountRepository;

/**
 * Email Centre — a read-only per-account mailbox summary, shown only where the
 * information is accessible to the current token. Deeper mailbox data requires
 * the cPanel API privilege (a future release). No mailboxes are created,
 * deleted or modified in Version 1.
 */
final class EmailController extends Controller
{
    public function __construct(private AccountRepository $accounts)
    {
    }

    public function index(Request $request, array $params): Response
    {
        return $this->view('email.index', [
            'title'   => 'Email Centre',
            'summary' => $this->accounts->emailSummary(),
        ]);
    }
}
