<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Controller;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Audit\AuditService;
use App\Services\Settings\Settings;
use App\Support\Validation\Validator;

/**
 * System settings. Branding writes the brand/accent tokens that re-theme the
 * whole UI at runtime (no code change), demonstrating the token-driven design.
 */
final class SettingController extends Controller
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly AuditService $audit,
    ) {
    }

    public function branding(Request $request): Response
    {
        return $this->view('admin.settings_branding', [
            'title'  => 'Branding',
            'active' => 'settings',
            'tab'    => 'branding',
        ]);
    }

    public function updateBranding(Request $request): Response
    {
        $validator = Validator::make(
            $request->only(['app_name', 'primary_color', 'accent_color', 'default_theme']),
            [
                'app_name'      => 'required|max:60',
                'primary_color' => 'required|max:7',
                'accent_color'  => 'required|max:7',
                'default_theme' => 'required|in:light,dark,system',
            ]
        );
        if ($validator->fails()) {
            return (new RedirectResponse('/admin/settings/branding'))->withErrors($validator->errors());
        }

        $this->settings->setMany([
            'general.app_name'      => (string) $request->input('app_name'),
            'branding.primary_color' => (string) $request->input('primary_color'),
            'branding.accent_color'  => (string) $request->input('accent_color'),
            'branding.default_theme' => (string) $request->input('default_theme'),
        ]);
        Settings::flush();
        $this->audit->log('settings.branding_updated');

        return (new RedirectResponse('/admin/settings/branding'))->with('status', 'Branding updated.');
    }

    public function general(Request $request): Response
    {
        return $this->view('admin.settings_general', [
            'title'  => 'General settings',
            'active' => 'settings',
            'tab'    => 'general',
        ]);
    }

    public function updateGeneral(Request $request): Response
    {
        $validator = Validator::make(
            $request->only(['timezone', 'date_format', 'ticket_prefix']),
            [
                'timezone'      => 'required|max:64',
                'date_format'   => 'required|max:20',
                'ticket_prefix' => 'required|max:10',
            ]
        );
        if ($validator->fails()) {
            return (new RedirectResponse('/admin/settings/general'))->withErrors($validator->errors());
        }

        $this->settings->setMany([
            'general.timezone'      => (string) $request->input('timezone'),
            'general.date_format'   => (string) $request->input('date_format'),
            'general.ticket_prefix' => strtoupper((string) $request->input('ticket_prefix')),
        ]);
        Settings::flush();
        $this->audit->log('settings.general_updated');

        return (new RedirectResponse('/admin/settings/general'))->with('status', 'Settings saved.');
    }
}
