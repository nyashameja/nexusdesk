<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Audit\AuditService;
use App\Services\Settings\Settings;

/**
 * AI settings: choose the provider and toggle features. The provider is bound
 * in the container; selecting a real one here is the last step to activate the
 * (already-built) AI touchpoints.
 */
final class AiController extends Controller
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly AuditService $audit,
        private readonly Database $db,
    ) {
    }

    public function index(Request $request): Response
    {
        $recent = [];
        try {
            $recent = $this->db->select('SELECT task, status, created_at FROM ai_requests ORDER BY id DESC LIMIT 10');
        } catch (\Throwable) {
            $recent = [];
        }
        return $this->view('admin.settings_ai', [
            'title'  => 'AI settings',
            'active' => 'settings',
            'recent' => $recent,
        ]);
    }

    public function update(Request $request): Response
    {
        $provider = (string) $request->input('provider', 'null');
        $provider = in_array($provider, ['null', 'anthropic', 'openai'], true) ? $provider : 'null';

        $this->settings->setMany([
            'ai.provider' => $provider,
            'ai.enabled'  => $provider !== 'null',
        ]);
        Settings::flush();
        $this->audit->log('settings.ai_updated', new: ['provider' => $provider]);

        return (new RedirectResponse('/admin/settings/ai'))->with('status', 'AI settings saved.');
    }
}
