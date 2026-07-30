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
 *
 * Secrets are never read back into the view — only whether they are present.
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
        return $this->view('settings.alerts', [
            'title'      => 'Alerts',
            'enabled'    => (bool) config('alerts.enabled', false),
            'channels'   => $this->channelRows(),
            'recipients' => (array) config('alerts.email.to', []),
            'thresholds' => (array) config('alerts.thresholds', []),
        ]);
    }

    public function run(Request $request, array $params): Response
    {
        $summary = $this->alerts->run();
        $this->audit->record('alerts.run', "Alert check: {$summary['new']} new, {$summary['active']} active.");

        $message = "Alert check complete: {$summary['new']} new alert(s), {$summary['active']} active";
        if ($summary['delivered'] !== []) {
            $message .= '. Sent via ' . implode(', ', $summary['delivered']) . '.';
        } elseif ($summary['new'] > 0) {
            $message .= ' (delivery disabled or no channel configured).';
        } else {
            $message .= '.';
        }

        $this->session()->flash($summary['failed'] === [] ? 'success' : 'warning',
            $summary['failed'] === []
                ? $message
                : $message . ' Failed: ' . implode(', ', $summary['failed']) . '.');

        return $this->redirect('/settings/alerts');
    }

    public function test(Request $request, array $params): Response
    {
        $sent = [];
        $failed = [];
        $details = [];

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
            if ($channel->lastError() !== '') {
                $details[] = $channel->name() . ' — ' . $channel->lastError();
            }
        }

        $this->audit->record(
            'alerts.test',
            'Sent test alert. OK: ' . (implode(',', $sent) ?: 'none') . '; failed: ' . (implode(',', $failed) ?: 'none')
        );

        if ($sent === [] && $failed === []) {
            $this->session()->flash('error', 'No channel is configured. Set up email and/or Telegram in .env.');
        } elseif ($failed === []) {
            $this->session()->flash('success', 'Test sent via: ' . implode(', ', $sent) . '.');
        } else {
            $message = 'Sent via: ' . (implode(', ', $sent) ?: 'none') . '. Failed: ' . implode(', ', $failed) . '.';
            if ($details !== []) {
                $message .= ' (' . implode('; ', $details) . ')';
            }
            $this->session()->flash('warning', $message);
        }

        return $this->redirect('/settings/alerts');
    }

    // -----------------------------------------------------------------

    /**
     * Per-channel status for the settings table. Reports only whether each
     * secret is present — never its value.
     *
     * @return array<int, array{name:string, configured:bool, detail:string, settings:array<int,array{0:string,1:bool,2:string}>}>
     */
    private function channelRows(): array
    {
        $rows = [];

        foreach ($this->channels as $channel) {
            $rows[] = [
                'name'       => $channel->name(),
                'configured' => $channel->isConfigured(),
                'detail'     => $this->detailFor($channel),
                'settings'   => $this->settingsFor($channel),
            ];
        }

        return $rows;
    }

    private function detailFor(AlertChannelInterface $channel): string
    {
        if ($channel->name() === 'email') {
            $to = (array) config('alerts.email.to', []);
            return $to === [] ? 'No recipients set (ALERT_EMAIL_TO)' : count($to) . ' recipient(s)';
        }

        if ($channel->name() === 'telegram') {
            $telegram = (array) config('alerts.telegram', []);
            if (empty($telegram['bot_token']) || empty($telegram['chat_id'])) {
                return 'Bot token and/or chat ID not set';
            }
            if (!TelegramAlertChannel::isValidChatId((string) $telegram['chat_id'])) {
                return 'Chat ID is not a valid Telegram id';
            }
            return empty($telegram['enabled'])
                ? 'Configured but disabled (ALERT_TELEGRAM_ENABLED=false)'
                : 'Ready';
        }

        return '';
    }

    /**
     * Presence-only checklist per channel: [label, present, note].
     *
     * @return array<int, array{0:string,1:bool,2:string}>
     */
    private function settingsFor(AlertChannelInterface $channel): array
    {
        if ($channel->name() === 'email') {
            $to   = (array) config('alerts.email.to', []);
            $from = (string) config('alerts.email.from', '');
            return [
                ['Recipients', $to !== [], $to === [] ? 'Set ALERT_EMAIL_TO' : implode(', ', $to)],
                ['Sender address', $from !== '', $from !== '' ? $from : 'Defaults to noreply@ your app host'],
            ];
        }

        if ($channel->name() === 'telegram') {
            $telegram = (array) config('alerts.telegram', []);
            $chatId   = (string) ($telegram['chat_id'] ?? '');
            return [
                ['Enabled', !empty($telegram['enabled']), 'ALERT_TELEGRAM_ENABLED'],
                ['Bot token', !empty($telegram['bot_token']), 'Stored in .env — never displayed'],
                ['Chat ID', TelegramAlertChannel::isValidChatId($chatId), $chatId !== '' ? 'Configured' : 'Set ALERT_TELEGRAM_CHAT_ID'],
            ];
        }

        return [];
    }
}
