<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Services\Auth\AuthContext;
use App\Services\Zoho\ZohoBooksService;

/**
 * REST API — invoices (read-only, from the Zoho cache). Customer tokens see
 * only their company's documents.
 */
final class InvoiceController
{
    public function __construct(private readonly ZohoBooksService $zoho)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = AuthContext::user();
        if ($user === null || !$user->can('zoho.view')) {
            return JsonResponse::error('forbidden', 'Insufficient permissions.', 403);
        }
        $companyId = $user->isCustomer() ? $user->companyId : ($request->query('company_id') ? (int) $request->query('company_id') : null);

        $rows = array_map(static fn (array $d): array => [
            'id'        => (int) $d['id'],
            'number'    => $d['number'],
            'status'    => $d['status'],
            'currency'  => $d['currency'],
            'total'     => (float) $d['total'],
            'balance'   => (float) $d['balance'],
            'issue_date' => $d['issue_date'],
            'due_date'  => $d['due_date'],
        ], $this->zoho->documentsForCompany('invoice', $companyId));

        return JsonResponse::ok($rows, ['summary' => $this->zoho->summaryForCompany($companyId)]);
    }
}
