<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Exceptions\HttpException;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\ClientRepository;
use ParagonHostOps\Repositories\DomainRepository;
use ParagonHostOps\Repositories\FinancialRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Validators\Validator;

/**
 * Financial dashboard and subscription (recurring-revenue) management.
 * Viewing requires finance.view; mutations require finance.manage + CSRF.
 */
final class FinanceController extends Controller
{
    private const PER_PAGE = 25;
    private const CYCLES   = ['monthly', 'quarterly', 'biannual', 'annual', 'once'];
    private const STATUSES = ['paid', 'partial', 'due', 'overdue', 'suspended', 'complimentary', 'cancelled'];

    public function __construct(
        private FinancialRepository $finance,
        private ClientRepository $clients,
        private DomainRepository $domains,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $charts = [
            'revenueByCategory' => $this->finance->revenueByCategory(),
            'paymentStatus'     => $this->finance->paymentStatusBreakdown(),
        ];

        return $this->view('finance.index', [
            'title'   => 'Finance',
            'metrics' => $this->finance->metrics(),
            'charts'  => $charts,
            'scripts' => ['assets/js/chart.umd.js', 'assets/js/finance.js'],
        ]);
    }

    public function subscriptions(Request $request, array $params): Response
    {
        $status = (string) $request->query('status', '');
        $page   = max(1, (int) $request->query('page', 1));
        $result = $this->finance->subscriptions($status, $page, self::PER_PAGE);

        return $this->view('finance.subscriptions', [
            'title'         => 'Subscriptions',
            'subscriptions' => $result['rows'],
            'status'        => $status,
            'statuses'      => self::STATUSES,
            'page'          => $page,
            'total'         => $result['total'],
            'pages'         => (int) ceil($result['total'] / self::PER_PAGE),
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
        $id = $this->finance->createSubscription($data);
        $this->audit->record('finance.subscription_created', 'Created subscription', 'subscription', $id);
        $this->session()->flash('success', 'Subscription created.');
        return $this->redirect('/finance/subscriptions');
    }

    public function update(Request $request, array $params): Response
    {
        $sub = $this->require($params);
        $data = $this->validated($request);
        if ($data instanceof Response) {
            return $data;
        }
        $this->finance->updateSubscription((int) $sub['id'], $data);
        $this->audit->record('finance.subscription_updated', 'Updated subscription', 'subscription', (int) $sub['id']);
        $this->session()->flash('success', 'Subscription updated.');
        return $this->redirect('/finance/subscriptions');
    }

    // ---------------------------------------------------------------------

    private function form(?array $sub): Response
    {
        return $this->view('finance.form', [
            'title'    => $sub === null ? 'New subscription' : 'Edit subscription',
            'sub'      => $sub,
            'cycles'   => self::CYCLES,
            'statuses' => self::STATUSES,
            'clients'  => $this->domains->clientOptions(),
            'accounts' => $this->domains->accountOptions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function require(array $params): array
    {
        $sub = $this->finance->findSubscription((int) ($params['id'] ?? 0));
        if ($sub === null) {
            throw new HttpException(404, 'Subscription not found.');
        }
        return $sub;
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function validated(Request $request): array|Response
    {
        $validator = (new Validator($request->all()))
            ->required('client_id', 'Client')
            ->numeric('client_id', 'Client')
            ->in('billing_cycle', 'Billing cycle', self::CYCLES)
            ->in('payment_status', 'Payment status', self::STATUSES)
            ->numeric('price', 'Price')
            ->numeric('internal_cost', 'Internal cost')
            ->numeric('outstanding', 'Outstanding')
            ->date('next_billing_at', 'Next billing date')
            ->date('last_payment_at', 'Last payment date');

        if ((int) $request->input('client_id', 0) <= 0) {
            $validator->addError('client_id', 'A client is required.');
        }

        if ($validator->fails()) {
            $_SESSION['_old'] = $request->all();
            $this->session()->flash('error', $validator->firstError());
            return $this->back('/finance/subscriptions');
        }

        return [
            'client_id'       => (string) $request->input('client_id', ''),
            'account_id'      => (string) $request->input('account_id', ''),
            'description'     => (string) $request->input('description', ''),
            'price'           => (string) $request->input('price', '0'),
            'internal_cost'   => (string) $request->input('internal_cost', ''),
            'billing_cycle'   => (string) $request->input('billing_cycle', 'monthly') ?: 'monthly',
            'next_billing_at' => (string) $request->input('next_billing_at', ''),
            'last_payment_at' => (string) $request->input('last_payment_at', ''),
            'payment_status'  => (string) $request->input('payment_status', 'due') ?: 'due',
            'outstanding'     => (string) $request->input('outstanding', '0'),
        ];
    }
}
