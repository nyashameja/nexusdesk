<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Exceptions\HttpException;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\DomainRepository;
use ParagonHostOps\Repositories\UptimeRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Uptime\UptimeService;
use ParagonHostOps\Validators\Validator;

/**
 * Uptime monitoring. Viewing requires uptime.view; managing monitors and
 * running checks require uptime.manage + CSRF.
 */
final class UptimeController extends Controller
{
    public function __construct(
        private UptimeRepository $monitors,
        private UptimeService $uptime,
        private DomainRepository $options,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        return $this->view('uptime.index', [
            'title'    => 'Uptime',
            'monitors' => $this->monitors->all(),
            'counts'   => $this->monitors->statusCounts(),
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
        $id = $this->monitors->create($data);
        $this->audit->record('uptime.created', 'Added uptime monitor ' . $data['label'], 'uptime_monitor', $id);
        $this->session()->flash('success', 'Monitor added.');
        return $this->redirect('/uptime');
    }

    public function update(Request $request, array $params): Response
    {
        $monitor = $this->require($params);
        $data = $this->validated($request);
        if ($data instanceof Response) {
            return $data;
        }
        $this->monitors->update((int) $monitor['id'], $data);
        $this->audit->record('uptime.updated', 'Updated uptime monitor', 'uptime_monitor', (int) $monitor['id']);
        $this->session()->flash('success', 'Monitor updated.');
        return $this->redirect('/uptime');
    }

    public function check(Request $request, array $params): Response
    {
        $monitor = $this->require($params);
        $result = $this->uptime->checkOne($monitor);
        $this->audit->record('uptime.checked', 'Manual uptime check for ' . $monitor['label'], 'uptime_monitor', (int) $monitor['id']);
        $this->session()->flash(
            $result['is_up'] ? 'success' : 'error',
            $monitor['label'] . ' is ' . strtoupper($result['status']) . ' (' . $result['response_ms'] . ' ms).'
        );
        return $this->redirect('/uptime');
    }

    public function checkAll(Request $request, array $params): Response
    {
        $summary = $this->uptime->checkAll();
        $this->audit->record('uptime.checked_all', "Manual uptime run: {$summary['checked']} checked, {$summary['down']} down.");
        $this->session()->flash('success', "Checked {$summary['checked']} monitor(s): {$summary['up']} up, {$summary['down']} down.");
        return $this->redirect('/uptime');
    }

    // ---------------------------------------------------------------------

    private function form(?array $monitor): Response
    {
        return $this->view('uptime.form', [
            'title'    => $monitor === null ? 'Add monitor' : 'Edit monitor',
            'monitor'  => $monitor,
            'clients'  => $this->options->clientOptions(),
            'accounts' => $this->options->accountOptions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function require(array $params): array
    {
        $monitor = $this->monitors->find((int) ($params['id'] ?? 0));
        if ($monitor === null) {
            throw new HttpException(404, 'Monitor not found.');
        }
        return $monitor;
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function validated(Request $request): array|Response
    {
        $url = trim((string) $request->input('url', ''));

        $validator = (new Validator($request->all()))
            ->required('label', 'Label')
            ->required('url', 'URL')
            ->max('url', 'URL', 255)
            ->numeric('expected_status', 'Expected status');

        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $validator->addError('url', 'URL must be a valid http(s) address.');
        } elseif ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $validator->addError('url', 'URL must start with http:// or https://');
        }

        if ($validator->fails()) {
            $_SESSION['_old'] = $request->all();
            $this->session()->flash('error', $validator->firstError());
            return $this->back('/uptime');
        }

        return [
            'label'           => (string) $request->input('label', ''),
            'url'             => $url,
            'client_id'       => (string) $request->input('client_id', ''),
            'account_id'      => (string) $request->input('account_id', ''),
            'expected_status' => (string) $request->input('expected_status', '200') ?: '200',
            'enabled'         => $request->input('enabled') ? 1 : 0,
        ];
    }
}
