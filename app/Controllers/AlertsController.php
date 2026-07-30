<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Services\Alerts\AlertService;
use ParagonHostOps\Services\Alerts\EmailAlertChannel;
use ParagonHostOps\Services\AuditLogger;

/**
 * Alerts settings: shows configuration, runs a check on demand, and sends a
 * test email. Requires settings.view.
 */
final class AlertsController extends Controller
{
    public function __construct(
        private AlertService $alerts,
        private EmailAlertChannel $email,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        return $this->view('settings.alerts', [
            'title'      => 'Alerts',
            'enabled'    => (bool) config('alerts.enabled', false),
            'recipients' => (array) config('alerts.email.to', []),
            'configured' => $this->email->isConfigured(),
            'thresholds' => (array) config('alerts.thresholds', []),
        ]);
    }

    public function run(Request $request, array $params): Response
    {
        $summary = $this->alerts->run();
        $this->audit->record('alerts.run', "Alert check: {$summary['new']} new, {$summary['active']} active.");
        $this->session()->flash(
            'success',
            "Alert check complete: {$summary['new']} new alert(s), {$summary['active']} active"
            . ($summary['emailed'] ? ', email sent.' : ($summary['new'] > 0 ? ' (email disabled or no recipients).' : '.'))
        );
        return $this->redirect('/settings/alerts');
    }

    public function test(Request $request, array $params): Response
    {
        if (!$this->email->isConfigured()) {
            $this->session()->flash('error', 'No alert recipients configured. Set ALERT_EMAIL_TO in .env.');
            return $this->redirect('/settings/alerts');
        }

        $ok = $this->email->notify(
            'Paragon HostOps: test alert',
            "This is a test alert from Paragon HostOps.\n\nIf you received this, email alerts are working."
        );
        $this->audit->record('alerts.test', 'Sent a test alert email: ' . ($ok ? 'success' : 'failure'));
        $this->session()->flash(
            $ok ? 'success' : 'error',
            $ok ? 'Test email sent to the configured recipients.' : 'The mail server rejected the test email (check server mail settings).'
        );
        return $this->redirect('/settings/alerts');
    }
}
