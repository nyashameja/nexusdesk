<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Services\Alerts\AlertChannelInterface;
use ParagonHostOps\Services\Alerts\AlertService;
use ParagonHostOps\Services\Alerts\TelegramAlertChannel;
use ParagonHostOps\Services\AuditLogger;

/**
 * Alerts settings: shows configuration for every channel, runs a check on
 * demand, and sends a test message. Requires settings.view.
 */
final class AlertsController extends Controller
{
    /**
     * @param array<int, AlertChannelInterface> $channels
     */
    public function __construct(
        private AlertService $alerts,
        private array $channels,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $telegram = (array) config('alerts.telegram', []);

        $channelRows = [];
        foreach ($this->channels as $channel) {
            $channelRows[] = [
                'name'       => $channel->name(),
                'configured' => $channel->isConfigured(),
                'detail'     => $this->detailFor($channel->name(), $telegram),
            ];
        }

        return $this->view('settings.alerts', [
            'title'      => 'Alerts',
            'enabled'    => (bool) config('alerts.enabled', false),
            'channels'   => $channelRows,
            'recipients' => (array) config('alerts.email.to', []),
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
            . ($summary['emailed'] ? ', notifications sent.' : ($summary['new'] > 0 ? ' (delivery disabled or no channel configured).' : '.'))
        );
        return $this->redirect('/settings/alerts');
    }

    public function test(Request $request, array $params): Response
    {
        $sent = [];
        $failed = [];
        $failureDetails = [];

        foreach ($this->channels as $channel) {
            if (!$channel->isConfigured()) {
                continue;
            }
            $ok = $channel->notify(
                'Paragon HostOps: test alert',
                "This is a test alert from Paragon HostOps.\n\nIf you received this, alerts are working."
            );
            if ($ok) {
                $sent[] = $channel->name();
                continue;
            }
            $failed[] = $channel->name();
            if ($channel instanceof TelegramAlertChannel && $channel->lastError() !== '') {
                $failureDetails[] = $channel->name() . ' — ' . $channel->lastError();
            }
        }

        $this->audit->record('alerts.test', 'Sent test alert. OK: ' . (implode(',', $sent) ?: 'none') . '; failed: ' . (implode(',', $failed) ?: 'none'));

        if ($sent === [] && $failed === []) {
            $this->session()->flash('error', 'No channel is configured. Set up email and/or Telegram in .env.');
        } elseif ($failed === []) {
            $this->session()->flash('success', 'Test sent via: ' . implode(', ', $sent) . '.');
        } else {
            $message = 'Sent via: ' . (implode(', ', $sent) ?: 'none') . '. Failed: ' . implode(', ', $failed) . '.';
            if ($failureDetails !== []) {
                $message .= ' (' . implode('; ', $failureDetails) . ')';
            }
            $this->session()->flash('warning', $message);
        }

        return $this->redirect('/settings/alerts');
    }

    /**
     * @param array<string, mixed> $telegram
     */
    private function detailFor(string $channel, array $telegram): string
    {
        if ($channel === 'email') {
            $to = (array) config('alerts.email.to', []);
            return $to === [] ? 'No recipients (set ALERT_EMAIL_TO)' : implode(', ', $to);
        }
        if ($channel === 'telegram') {
            if (empty($telegram['bot_token']) || empty($telegram['chat_id'])) {
                return 'Bot token / chat ID not set';
            }
            return empty($telegram['enabled']) ? 'Configured but disabled (ALERT_TELEGRAM_ENABLED=false)' : 'Chat ID ' . $telegram['chat_id'];
        }
        return '';
    }
}
