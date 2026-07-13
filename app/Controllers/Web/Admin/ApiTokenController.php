<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthContext;
use App\Support\Validation\Validator;

/**
 * Personal API token management. Tokens are shown in plaintext exactly once at
 * creation; only the SHA-256 hash is stored.
 */
final class ApiTokenController extends Controller
{
    public function __construct(
        private readonly Database $db,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = (int) AuthContext::id();
        $tokens = $this->db->select(
            'SELECT id, name, last_used_at, created_at, revoked_at, expires_at
             FROM api_tokens WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
        return $this->view('admin.api_tokens', [
            'title'  => 'API tokens',
            'active' => 'settings',
            'tokens' => $tokens,
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = Validator::make($request->only(['name']), ['name' => 'required|max:100']);
        if ($validator->fails()) {
            return (new RedirectResponse('/admin/settings/api'))->withErrors($validator->errors());
        }

        $plain = 'nxd_live_' . bin2hex(random_bytes(24));
        $this->db->insert('api_tokens', [
            'user_id'    => (int) AuthContext::id(),
            'name'       => (string) $request->input('name'),
            'token_hash' => hash('sha256', $plain),
        ]);
        $this->audit->log('api_token.created');

        return (new RedirectResponse('/admin/settings/api'))
            ->with('new_token', $plain)
            ->with('status', 'Token created. Copy it now — it will not be shown again.');
    }

    public function revoke(Request $request, int $id): Response
    {
        $this->db->run(
            'UPDATE api_tokens SET revoked_at = NOW() WHERE id = ? AND user_id = ?',
            [$id, (int) AuthContext::id()]
        );
        $this->audit->log('api_token.revoked', 'ApiToken', $id);
        return (new RedirectResponse('/admin/settings/api'))->with('status', 'Token revoked.');
    }
}
