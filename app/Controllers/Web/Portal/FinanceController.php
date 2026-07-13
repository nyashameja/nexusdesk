<?php

declare(strict_types=1);

namespace App\Controllers\Web\Portal;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth\AuthContext;
use App\Services\Zoho\ZohoBooksService;

/**
 * Customer finance pages (invoices, quotes, statements) backed by the local
 * Zoho cache and scoped to the signed-in customer's company. Degrades to an
 * "not connected" empty state when Zoho isn't linked.
 */
final class FinanceController extends Controller
{
    public function __construct(private readonly ZohoBooksService $zoho)
    {
    }

    public function invoices(Request $request): Response
    {
        $companyId = AuthContext::user()?->companyId;
        return $this->view('portal.invoices', [
            'title'     => 'Invoices',
            'active'    => 'invoices',
            'connected' => $this->zoho->isConnected(),
            'invoices'  => $this->zoho->documentsForCompany('invoice', $companyId),
            'summary'   => $this->zoho->summaryForCompany($companyId),
        ]);
    }

    public function invoice(Request $request, int $id): Response
    {
        $doc = $this->ownedDocument($id, 'invoice');
        return $this->view('portal.invoice_show', [
            'title'   => 'Invoice ' . ($doc['number'] ?? ''),
            'active'  => 'invoices',
            'invoice' => $doc,
            'payload' => $this->decodePayload($doc),
        ]);
    }

    public function invoicePdf(Request $request, int $id): Response
    {
        $this->ownedDocument($id, 'invoice');
        $pdf = $this->zoho->invoicePdf($id);
        if ($pdf === null) {
            throw HttpException::notFound('Invoice PDF is not available.');
        }
        return (new Response($pdf))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice-' . $id . '.pdf"');
    }

    public function quotes(Request $request): Response
    {
        $companyId = AuthContext::user()?->companyId;
        return $this->view('portal.quotes', [
            'title'     => 'Quotes',
            'active'    => 'quotes',
            'connected' => $this->zoho->isConnected(),
            'quotes'    => $this->zoho->documentsForCompany('quote', $companyId),
        ]);
    }

    public function statements(Request $request): Response
    {
        $companyId = AuthContext::user()?->companyId;
        return $this->view('portal.statements', [
            'title'      => 'Statements',
            'active'     => 'statements',
            'connected'  => $this->zoho->isConnected(),
            'payments'   => $this->zoho->documentsForCompany('payment', $companyId),
            'creditNotes' => $this->zoho->documentsForCompany('credit_note', $companyId),
            'summary'    => $this->zoho->summaryForCompany($companyId),
        ]);
    }

    /** @return array<string,mixed> */
    private function ownedDocument(int $id, string $docType): array
    {
        $doc = $this->zoho->documentsForCompany($docType, AuthContext::user()?->companyId);
        foreach ($doc as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }
        throw HttpException::notFound('Document not found.');
    }

    /** @param array<string,mixed> $doc @return array<string,mixed> */
    private function decodePayload(array $doc): array
    {
        $payload = json_decode((string) ($doc['payload'] ?? ''), true);
        return is_array($payload) ? $payload : [];
    }
}
