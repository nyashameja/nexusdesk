<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\SearchRepositoryInterface;

/**
 * Global search results page (staff). Searches tickets, companies, knowledge
 * base and users, grouped by type.
 */
final class SearchController extends Controller
{
    public function __construct(private readonly SearchRepositoryInterface $search)
    {
    }

    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $results = $query !== '' ? $this->search->global($query) : [
            'tickets' => [], 'companies' => [], 'articles' => [], 'users' => [],
        ];

        $count = array_sum(array_map('count', $results));

        return $this->view('search.results', [
            'title'   => 'Search',
            'active'  => 'search',
            'query'   => $query,
            'results' => $results,
            'count'   => $count,
        ]);
    }
}
