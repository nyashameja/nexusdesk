<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Controller;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Integrations\Zoho\ZohoBooksClientInterface;
use App\Repositories\Contracts\ZohoRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Audit\AuditService;
use App\Services\Settings\Settings;
use App\Services\Zoho\ZohoBooksService;
use App\Support\Security\Crypto;
use App\Support\Security\Session;

/**
 * Zoho Books admin: OAuth connect flow, organization id, manual sync, and
 * disconnect. Client id/secret come from .env; only the (encrypted) refresh
 * token and org id are stored.
 */
final class ZohoController extends Controller
{
    public function __construct(
        private readonly ZohoBooksClientInterface $client,
        private readonly ZohoBooksService $service,
        private readonly ZohoRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('admin.settings_zoho', [
            'title'      => 'Zoho Books',
            'active'     => 'settings',
            'configured' => $this->client->isConfigured(),
            'connection' => $this->service->connection(),
            'connected'  => $this->service->isConnected(),
        ]);
    }

    /** Save the organization id and begin the OAuth authorization redirect. */
    public function connect(Request $request): Response
    {
        $orgId = trim((string) $request->input('organization_id'));
        if ($orgId === '') {
            return (new RedirectResponse('/admin/settings/zoho'))
                ->withErrors(['organization_id' => ['Enter your Zoho Books organization ID.']]);
        }
        if (!$this->client->isConfigured()) {
            return (new RedirectResponse('/admin/settings/zoho'))
                ->withErrors(['organization_id' => ['Set ZOHO_CLIENT_ID and ZOHO_CLIENT_SECRET in .env first.']]);
        }

        // Persist the org id (inactive until the callback returns tokens).
        $this->repository->saveConnection(['organization_id' => $orgId]);

        $state = bin2hex(random_bytes(16));
        Session::put('_zoho_state', $state);

        return new RedirectResponse($this->client->authorizationUrl($state));
    }

    public function callback(Request $request): Response
    {
        $state = (string) $request->query('state', '');
        if (!hash_equals((string) Session::get('_zoho_state', ''), $state)) {
            return (new RedirectResponse('/admin/settings/zoho'))
                ->withErrors(['organization_id' => ['Invalid OAuth state. Please try connecting again.']]);
        }
        Session::forget('_zoho_state');

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return (new RedirectResponse('/admin/settings/zoho'))
                ->withErrors(['organization_id' => ['Authorization was cancelled.']]);
        }

        $tokens = $this->client->exchangeCode($code);
        if ($tokens === null || empty($tokens['refresh_token'])) {
            return (new RedirectResponse('/admin/settings/zoho'))
                ->withErrors(['organization_id' => ['Could not obtain a refresh token from Zoho.']]);
        }

        $this->repository->saveConnection([
            'access_token'     => Crypto::encrypt((string) $tokens['access_token']),
            'refresh_token'    => Crypto::encrypt((string) $tokens['refresh_token']),
            'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($tokens['expires_in'] ?? 3600)),
            'connected_by'     => \App\Services\Auth\AuthContext::id(),
            'is_active'        => 1,
        ]);
        $this->settings->set('zoho', 'enabled', true);
        Settings::flush();
        $this->audit->log('zoho.connected');

        return (new RedirectResponse('/admin/settings/zoho'))->with('status', 'Zoho Books connected.');
    }

    public function sync(Request $request): Response
    {
        $count = $this->service->sync();
        $this->audit->log('zoho.synced', new: ['documents' => $count]);
        return (new RedirectResponse('/admin/settings/zoho'))
            ->with('status', $count > 0 ? "Synced {$count} documents." : 'Sync ran; no documents returned.');
    }

    public function disconnect(Request $request): Response
    {
        $this->repository->disconnect();
        $this->settings->set('zoho', 'enabled', false);
        Settings::flush();
        $this->audit->log('zoho.disconnected');
        return (new RedirectResponse('/admin/settings/zoho'))->with('status', 'Zoho Books disconnected.');
    }
}
