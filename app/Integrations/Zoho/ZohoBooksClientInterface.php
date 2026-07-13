<?php

declare(strict_types=1);

namespace App\Integrations\Zoho;

interface ZohoBooksClientInterface
{
    public function isConfigured(): bool;
    public function authorizationUrl(string $state): string;

    /** Exchange an OAuth authorization code for tokens. @return array<string,mixed>|null */
    public function exchangeCode(string $code): ?array;

    /** A valid access token (refreshing via the stored refresh token if needed). */
    public function accessToken(): ?string;

    /** @return array<int,array<string,mixed>> */
    public function fetch(string $resource, string $organizationId): array;

    /** Raw PDF bytes for an invoice, or null. */
    public function fetchInvoicePdf(string $organizationId, string $invoiceId): ?string;
}
