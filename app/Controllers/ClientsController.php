<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Exceptions\HttpException;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\ClientRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Auth;
use ParagonHostOps\Validators\Validator;

/**
 * Client CRM controller. Viewing requires clients.view; all mutations require
 * clients.manage (enforced by route middleware) and CSRF.
 */
final class ClientsController extends Controller
{
    private const PER_PAGE = 25;
    private const TYPES    = ['individual', 'company'];
    private const STATUSES = ['active', 'inactive', 'prospect', 'archived'];

    public function __construct(
        private ClientRepository $clients,
        private AuditLogger $audit,
        private Auth $auth,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $page   = max(1, (int) $request->query('page', 1));

        $result = $this->clients->paginate($search, $status, $page, self::PER_PAGE);

        return $this->view('clients.index', [
            'title'    => 'Clients',
            'clients'  => $result['rows'],
            'repo'     => $this->clients,
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
        $client = $this->requireClient($params);

        return $this->view('clients.show', [
            'title'      => $this->clients->displayName($client),
            'client'     => $client,
            'repo'       => $this->clients,
            'accounts'   => $this->clients->linkedAccounts((int) $client['id']),
            'domains'    => $this->clients->linkedDomains((int) $client['id']),
            'notes'      => $this->clients->notes((int) $client['id']),
            'financials' => $this->clients->financials((int) $client['id']),
            'unlinked'   => $this->auth->can('clients.manage') ? $this->clients->unlinkedAccounts() : [],
        ]);
    }

    public function create(Request $request, array $params): Response
    {
        return $this->view('clients.form', [
            'title'    => 'New Client',
            'client'   => null,
            'types'    => self::TYPES,
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request, array $params): Response
    {
        $data = $this->validated($request);
        if ($data instanceof Response) {
            return $data;
        }

        $id = $this->clients->create($data);
        $this->audit->record('client.created', 'Created client ' . ($data['company_name'] ?: $data['first_name'] . ' ' . $data['last_name']), 'client', $id);
        $this->session()->flash('success', 'Client created.');

        return $this->redirect('/clients/' . $id);
    }

    public function edit(Request $request, array $params): Response
    {
        $client = $this->requireClient($params);

        return $this->view('clients.form', [
            'title'    => 'Edit Client',
            'client'   => $client,
            'types'    => self::TYPES,
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, array $params): Response
    {
        $client = $this->requireClient($params);
        $data = $this->validated($request);
        if ($data instanceof Response) {
            return $data;
        }

        $this->clients->update((int) $client['id'], $data);
        $this->audit->record('client.updated', 'Updated client', 'client', (int) $client['id']);
        $this->session()->flash('success', 'Client updated.');

        return $this->redirect('/clients/' . (int) $client['id']);
    }

    public function addNote(Request $request, array $params): Response
    {
        $client = $this->requireClient($params);
        $body = trim((string) $request->input('body', ''));

        if ($body === '') {
            $this->session()->flash('error', 'Note cannot be empty.');
        } else {
            $this->clients->addNote((int) $client['id'], $this->auth->id(), $body);
            $this->audit->record('client.note_added', 'Added note to client', 'client', (int) $client['id']);
            $this->session()->flash('success', 'Note added.');
        }

        return $this->redirect('/clients/' . (int) $client['id']);
    }

    public function linkAccount(Request $request, array $params): Response
    {
        $client    = $this->requireClient($params);
        $accountId = (int) $request->input('account_id', 0);

        if ($accountId > 0) {
            $this->clients->linkAccount($accountId, (int) $client['id']);
            $this->audit->record('client.account_linked', "Linked account #{$accountId} to client", 'client', (int) $client['id']);
            $this->session()->flash('success', 'Hosting account linked to client.');
        }

        return $this->redirect('/clients/' . (int) $client['id']);
    }

    public function unlinkAccount(Request $request, array $params): Response
    {
        $client    = $this->requireClient($params);
        $accountId = (int) ($params['accountId'] ?? 0);

        $this->clients->linkAccount($accountId, null);
        $this->audit->record('client.account_unlinked', "Unlinked account #{$accountId}", 'client', (int) $client['id']);
        $this->session()->flash('success', 'Hosting account unlinked.');

        return $this->redirect('/clients/' . (int) $client['id']);
    }

    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function requireClient(array $params): array
    {
        $client = $this->clients->find((int) ($params['id'] ?? 0));
        if ($client === null) {
            throw new HttpException(404, 'Client not found.');
        }
        return $client;
    }

    /**
     * Validate and return the allow-listed client payload, or a redirect
     * Response back to the form on failure.
     *
     * @return array<string, mixed>|Response
     */
    private function validated(Request $request): array|Response
    {
        $validator = (new Validator($request->all()))
            ->required('client_type', 'Client type')
            ->in('client_type', 'Client type', self::TYPES)
            ->in('status', 'Status', self::STATUSES)
            ->email('primary_email', 'Primary email')
            ->email('secondary_email', 'Secondary email')
            ->max('company_name', 'Company name', 190)
            ->max('first_name', 'First name', 120)
            ->max('last_name', 'Last name', 120);

        $type = (string) $request->input('client_type', 'individual');
        if ($type === 'company' && trim((string) $request->input('company_name', '')) === '') {
            $validator->addError('company_name', 'Company name is required for a company.');
        }
        if ($type === 'individual' && trim((string) $request->input('first_name', '')) === '' && trim((string) $request->input('last_name', '')) === '') {
            $validator->addError('first_name', 'A first or last name is required.');
        }

        if ($validator->fails()) {
            $_SESSION['_old'] = $request->all();
            $this->session()->flash('error', $validator->firstError());
            return $this->back('/clients');
        }

        return [
            'client_type'     => $type,
            'company_name'    => (string) $request->input('company_name', ''),
            'first_name'      => (string) $request->input('first_name', ''),
            'last_name'       => (string) $request->input('last_name', ''),
            'primary_email'   => (string) $request->input('primary_email', ''),
            'secondary_email' => (string) $request->input('secondary_email', ''),
            'phone'           => (string) $request->input('phone', ''),
            'whatsapp'        => (string) $request->input('whatsapp', ''),
            'country'         => (string) $request->input('country', ''),
            'province'        => (string) $request->input('province', ''),
            'city'            => (string) $request->input('city', ''),
            'billing_address' => (string) $request->input('billing_address', ''),
            'tax_number'      => (string) $request->input('tax_number', ''),
            'status'          => (string) $request->input('status', 'active') ?: 'active',
            'notes'           => (string) $request->input('notes', ''),
        ];
    }
}
