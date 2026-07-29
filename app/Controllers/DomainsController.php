<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Exceptions\HttpException;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\DomainRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Domains\DomainExpiryService;
use ParagonHostOps\Validators\Validator;

/**
 * Local domain registry (no registrar API in Version 1). View requires
 * domains.view; mutations require domains.manage + CSRF.
 */
final class DomainsController extends Controller
{
    private const PER_PAGE = 25;
    private const STATUSES = ['active', 'expiring', 'expired', 'transfer_pending', 'renewal_pending', 'cancelled', 'unknown'];

    public function __construct(
        private DomainRepository $domains,
        private DomainExpiryService $expiry,
        private AuditLogger $audit,
    ) {
    }

    /**
     * Look up a single domain's expiry (RDAP/WHOIS) and store the result.
     */
    public function checkExpiry(Request $request, array $params): Response
    {
        $domain = $this->requireDomain($params);
        $result = $this->expiry->check($domain);

        $this->audit->record('domain.expiry_checked', 'Checked expiry for ' . $domain['domain'], 'domain', (int) $domain['id']);

        if ($result['updated']) {
            $this->session()->flash('success', "{$domain['domain']}: expires {$result['expires_at']} ({$result['status']}).");
        } else {
            $this->session()->flash('warning', "{$domain['domain']}: {$result['message']}");
        }

        return $this->redirect('/domains/' . (int) $domain['id']);
    }

    /**
     * Look up every stored domain's expiry.
     */
    public function checkAll(Request $request, array $params): Response
    {
        $summary = $this->expiry->checkAll();
        $this->audit->record('domain.expiry_checked_all', "Checked {$summary['checked']} domain(s) for expiry.");
        $this->session()->flash(
            'success',
            "Checked {$summary['checked']} domain(s): {$summary['updated']} updated, {$summary['expiring']} expiring, {$summary['expired']} expired."
        );
        return $this->redirect('/domains');
    }

    public function index(Request $request, array $params): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $page   = max(1, (int) $request->query('page', 1));

        $result = $this->domains->paginate($search, $status, $page, self::PER_PAGE);

        return $this->view('domains.index', [
            'title'    => 'Domains',
            'domains'  => $result['rows'],
            'counts'   => $this->domains->statusCounts(),
            'search'   => $search,
            'status'   => $status,
            'statuses' => self::STATUSES,
            'page'     => $page,
            'total'    => $result['total'],
            'pages'    => (int) ceil($result['total'] / self::PER_PAGE),
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $domain = $this->requireDomain($params);
        return $this->view('domains.show', ['title' => $domain['domain'], 'domain' => $domain]);
    }

    public function create(Request $request, array $params): Response
    {
        return $this->form(null);
    }

    public function edit(Request $request, array $params): Response
    {
        return $this->form($this->requireDomain($params));
    }

    public function store(Request $request, array $params): Response
    {
        $data = $this->validated($request);
        if ($data instanceof Response) {
            return $data;
        }
        $id = $this->domains->create($data);
        $this->audit->record('domain.created', 'Created domain ' . $data['domain'], 'domain', $id);
        $this->session()->flash('success', 'Domain added.');
        return $this->redirect('/domains/' . $id);
    }

    public function update(Request $request, array $params): Response
    {
        $domain = $this->requireDomain($params);
        $data = $this->validated($request);
        if ($data instanceof Response) {
            return $data;
        }
        $this->domains->update((int) $domain['id'], $data);
        $this->audit->record('domain.updated', 'Updated domain ' . $domain['domain'], 'domain', (int) $domain['id']);
        $this->session()->flash('success', 'Domain updated.');
        return $this->redirect('/domains/' . (int) $domain['id']);
    }

    // ---------------------------------------------------------------------

    private function form(?array $domain): Response
    {
        return $this->view('domains.form', [
            'title'    => $domain === null ? 'Add Domain' : 'Edit Domain',
            'domain'   => $domain,
            'statuses' => self::STATUSES,
            'clients'  => $this->domains->clientOptions(),
            'accounts' => $this->domains->accountOptions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireDomain(array $params): array
    {
        $domain = $this->domains->find((int) ($params['id'] ?? 0));
        if ($domain === null) {
            throw new HttpException(404, 'Domain not found.');
        }
        return $domain;
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function validated(Request $request): array|Response
    {
        $validator = (new Validator($request->all()))
            ->required('domain', 'Domain')
            ->max('domain', 'Domain', 190)
            ->in('status', 'Status', self::STATUSES)
            ->date('registered_at', 'Registration date')
            ->date('expires_at', 'Expiry date')
            ->numeric('renewal_cost', 'Renewal cost')
            ->numeric('client_price', 'Client price');

        if ($validator->fails()) {
            $_SESSION['_old'] = $request->all();
            $this->session()->flash('error', $validator->firstError());
            return $this->back('/domains');
        }

        return [
            'domain'        => strtolower(trim((string) $request->input('domain', ''))),
            'client_id'     => (string) $request->input('client_id', ''),
            'account_id'    => (string) $request->input('account_id', ''),
            'registrar'     => (string) $request->input('registrar', ''),
            'registered_at' => (string) $request->input('registered_at', ''),
            'expires_at'    => (string) $request->input('expires_at', ''),
            'auto_renew'    => $request->input('auto_renew') ? 1 : 0,
            'nameserver1'   => (string) $request->input('nameserver1', ''),
            'nameserver2'   => (string) $request->input('nameserver2', ''),
            'status'        => (string) $request->input('status', 'unknown') ?: 'unknown',
            'renewal_cost'  => (string) $request->input('renewal_cost', ''),
            'client_price'  => (string) $request->input('client_price', ''),
            'notes'         => (string) $request->input('notes', ''),
        ];
    }
}
