<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\SslRepository;

/**
 * SSL Centre — read-only monitoring of certificate status. Version 1 does not
 * install, renew, purchase or delete certificates.
 */
final class SslController extends Controller
{
    private const PER_PAGE = 30;

    public function __construct(private SslRepository $ssl)
    {
    }

    public function index(Request $request, array $params): Response
    {
        $filter = (string) $request->query('filter', '');
        $search = trim((string) $request->query('q', ''));
        $page   = max(1, (int) $request->query('page', 1));

        $result = $this->ssl->listing($filter, $search, $page, self::PER_PAGE);

        return $this->view('ssl.index', [
            'title'   => 'SSL Centre',
            'certs'   => $result['rows'],
            'summary' => $this->ssl->summary(),
            'filter'  => $filter,
            'search'  => $search,
            'page'    => $page,
            'total'   => $result['total'],
            'pages'   => (int) ceil($result['total'] / self::PER_PAGE),
        ]);
    }
}
