<?php

declare(strict_types=1);

namespace App\Integrations\Zoho;

use App\Infrastructure\Http\HttpClient;
use App\Infrastructure\Logging\LoggerInterface;
use App\Repositories\Contracts\ZohoRepositoryInterface;
use App\Support\Security\Crypto;

/**
 * Zoho Books REST client. Implements the OAuth 2.0 authorization-code flow,
 * refreshes short-lived access tokens from the stored (encrypted) refresh
 * token, and reads financial resources. All Zoho-specific shapes stay inside
 * this namespace; callers receive plain arrays.
 */
final class ZohoBooksClient implements ZohoBooksClientInterface
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly HttpClient $http,
        private readonly ZohoRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isConfigured(): bool
    {
        return (string) $this->config['client_id'] !== '' && (string) $this->config['client_secret'] !== '';
    }

    public function authorizationUrl(string $state): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->config['client_id'],
            'scope'         => $this->config['scope'],
            'redirect_uri'  => $this->config['redirect_uri'],
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ]);
        return $this->config['accounts_url'] . '/oauth/v2/auth?' . $params;
    }

    public function exchangeCode(string $code): ?array
    {
        $response = $this->http->request('POST', $this->config['accounts_url'] . '/oauth/v2/token', [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri'  => $this->config['redirect_uri'],
            'code'          => $code,
        ]);

        $data = json_decode($response['body'], true);
        if (!is_array($data) || empty($data['access_token'])) {
            $this->logger->error('Zoho token exchange failed', ['status' => $response['status']]);
            return null;
        }
        return $data;
    }

    public function accessToken(): ?string
    {
        $connection = $this->repository->connection();
        if ($connection === null || (int) $connection['is_active'] !== 1) {
            return null;
        }

        // Reuse a still-valid access token.
        if (!empty($connection['access_token']) && !empty($connection['token_expires_at'])
            && strtotime((string) $connection['token_expires_at']) > time() + 60) {
            return Crypto::decrypt((string) $connection['access_token']);
        }

        // Refresh.
        $refreshToken = $connection['refresh_token'] ? Crypto::decrypt((string) $connection['refresh_token']) : null;
        if ($refreshToken === null) {
            return null;
        }

        $response = $this->http->request('POST', $this->config['accounts_url'] . '/oauth/v2/token', [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], [
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $refreshToken,
        ]);

        $data = json_decode($response['body'], true);
        if (!is_array($data) || empty($data['access_token'])) {
            $this->logger->error('Zoho token refresh failed', ['status' => $response['status']]);
            return null;
        }

        $this->repository->saveConnection([
            'access_token'     => Crypto::encrypt((string) $data['access_token']),
            'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($data['expires_in'] ?? 3600)),
        ]);

        return (string) $data['access_token'];
    }

    public function fetch(string $resource, string $organizationId): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return [];
        }
        $url = $this->config['api_url'] . '/' . $resource . '?organization_id=' . urlencode($organizationId);
        $data = $this->http->getJson($url, ['Authorization' => 'Zoho-oauthtoken ' . $token]);
        if ($data === null) {
            return [];
        }
        // Zoho wraps the collection under the resource name (invoices, estimates, ...).
        return $data[$resource] ?? [];
    }

    public function fetchInvoicePdf(string $organizationId, string $invoiceId): ?string
    {
        $token = $this->accessToken();
        if ($token === null) {
            return null;
        }
        $url = $this->config['api_url'] . '/invoices/' . urlencode($invoiceId)
            . '?organization_id=' . urlencode($organizationId) . '&accept=pdf';
        $response = $this->http->request('GET', $url, [
            'Authorization' => 'Zoho-oauthtoken ' . $token,
            'Accept'        => 'application/pdf',
        ]);
        if ($response['status'] < 200 || $response['status'] >= 300 || $response['body'] === '') {
            return null;
        }
        return $response['body'];
    }
}
