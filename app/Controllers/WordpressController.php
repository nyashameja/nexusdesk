<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Exceptions\HttpException;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\WordpressRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Validators\Validator;

/**
 * Local WordPress site registry. View requires wordpress.view; mutations
 * require wordpress.manage + CSRF. No plugin/core updates are performed in v1.
 */
final class WordpressController extends Controller
{
    private const PER_PAGE = 25;
    private const STATUSES = ['healthy', 'updates_required', 'maintenance_overdue', 'backup_overdue', 'security_review', 'unknown'];

    public function __construct(
        private WordpressRepository $sites,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $page   = max(1, (int) $request->query('page', 1));

        $result = $this->sites->paginate($search, $status, $page, self::PER_PAGE);

        return $this->view('wordpress.index', [
            'title'    => 'WordPress',
            'sites'    => $result['rows'],
            'counts'   => $this->sites->statusCounts(),
            'search'   => $search,
            'status'   => $status,
            'statuses' => self::STATUSES,
            'page'     => $page,
            'total'    => $result['total'],
            'pages'    => (int) ceil($result['total'] / self::PER_PAGE),
        ]);
    }

    public function create(Request $request, array $params): Response
    {
        return $this->form(null);
    }

    public function edit(Request $request, array $params): Response
    {
        return $this->form($this->require($params));
    }

    public function store(Request $request, array $params): Response
    {
        $data = $this->validated($request);
        if ($data instanceof Response) {
            return $data;
        }
        $id = $this->sites->create($data);
        $this->audit->record('wordpress.created', 'Added WordPress site ' . $data['name'], 'wordpress_site', $id);
        $this->session()->flash('success', 'WordPress site added.');
        return $this->redirect('/wordpress');
    }

    public function update(Request $request, array $params): Response
    {
        $site = $this->require($params);
        $data = $this->validated($request);
        if ($data instanceof Response) {
            return $data;
        }
        $this->sites->update((int) $site['id'], $data);
        $this->audit->record('wordpress.updated', 'Updated WordPress site', 'wordpress_site', (int) $site['id']);
        $this->session()->flash('success', 'WordPress site updated.');
        return $this->redirect('/wordpress');
    }

    // ---------------------------------------------------------------------

    private function form(?array $site): Response
    {
        return $this->view('wordpress.form', [
            'title'    => $site === null ? 'Add WordPress site' : 'Edit WordPress site',
            'site'     => $site,
            'statuses' => self::STATUSES,
            'clients'  => $this->sites->clientOptions(),
            'accounts' => $this->sites->accountOptions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function require(array $params): array
    {
        $site = $this->sites->find((int) ($params['id'] ?? 0));
        if ($site === null) {
            throw new HttpException(404, 'WordPress site not found.');
        }
        return $site;
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function validated(Request $request): array|Response
    {
        $validator = (new Validator($request->all()))
            ->required('name', 'Site name')
            ->max('name', 'Site name', 190)
            ->in('wp_status', 'Status', self::STATUSES)
            ->date('last_backup_at', 'Last backup date')
            ->date('last_update_at', 'Last update date')
            ->numeric('maintenance_fee', 'Maintenance fee');

        if ($validator->fails()) {
            $_SESSION['_old'] = $request->all();
            $this->session()->flash('error', $validator->firstError());
            return $this->back('/wordpress');
        }

        return [
            'name'             => (string) $request->input('name', ''),
            'url'              => (string) $request->input('url', ''),
            'staging_url'      => (string) $request->input('staging_url', ''),
            'client_id'        => (string) $request->input('client_id', ''),
            'account_id'       => (string) $request->input('account_id', ''),
            'wp_status'        => (string) $request->input('wp_status', 'unknown') ?: 'unknown',
            'wp_version'       => (string) $request->input('wp_version', ''),
            'php_version'      => (string) $request->input('php_version', ''),
            'last_backup_at'   => (string) $request->input('last_backup_at', ''),
            'last_update_at'   => (string) $request->input('last_update_at', ''),
            'maintenance_plan' => (string) $request->input('maintenance_plan', ''),
            'maintenance_fee'  => (string) $request->input('maintenance_fee', ''),
            'notes'            => (string) $request->input('notes', ''),
        ];
    }
}
