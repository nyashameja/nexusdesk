<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Alerts;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Renders alerts as Telegram HTML messages.
 *
 * Pure and side-effect free — no network, no database — so every branch is
 * unit-testable. Telegram's HTML parse mode only permits a small tag set, so
 * every dynamic value (client names, domains, registrar strings, API errors)
 * is escaped with escape() before interpolation. An unescaped "&" or "<" in a
 * client name would otherwise make the whole sendMessage call fail.
 *
 * @see https://core.telegram.org/bots/api#html-style
 */
final class TelegramMessageFormatter
{
    /** Timestamps are shown in local operating hours, not UTC. */
    public const TIMEZONE = 'Africa/Johannesburg';

    /** Cap on individual alert lines in a digest before summarising the rest. */
    public const DIGEST_LIMIT = 12;

    private const SEVERITY_ICON = [
        Alert::SEVERITY_CRITICAL => '🚨',
        Alert::SEVERITY_WARNING  => '🟠',
        Alert::SEVERITY_INFO     => '🔵',
    ];

    private const CATEGORY_ICON = [
        'ssl'       => '🔐',
        'domain'    => '🌍',
        'disk'      => '💾',
        'bandwidth' => '📶',
        'uptime'    => '🌐',
    ];

    private const SEVERITY_LABEL = [
        Alert::SEVERITY_CRITICAL => 'CRITICAL',
        Alert::SEVERITY_WARNING  => 'WARNING',
        Alert::SEVERITY_INFO     => 'INFORMATION',
    ];

    public function __construct(
        private string $appUrl = '',
        private ?string $serverName = null,
    ) {
    }

    /**
     * Escape a value for Telegram HTML parse mode.
     *
     * Telegram requires &, < and > to be replaced; quotes are left alone
     * because we never interpolate into an attribute except href, which is
     * built from our own configuration rather than user input.
     */
    public static function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }

    /**
     * A single alert rendered in full detail — enough to act on without
     * opening the dashboard.
     */
    public function format(Alert $alert, ?DateTimeImmutable $now = null): string
    {
        $icon     = self::CATEGORY_ICON[$alert->category] ?? (self::SEVERITY_ICON[$alert->severity] ?? '🔵');
        $sevIcon  = self::SEVERITY_ICON[$alert->severity] ?? '🔵';
        $sevLabel = self::SEVERITY_LABEL[$alert->severity] ?? 'INFORMATION';

        $lines = [];
        $lines[] = $sevIcon . ' <b>' . self::escape($sevLabel) . ' — ' . self::escape($this->categoryHeading($alert->category)) . '</b>';
        $lines[] = '';
        $lines[] = '<b>' . self::escape($alert->title) . '</b>';
        $lines[] = '';

        foreach ($this->detailRows($alert, $icon) as [$rowIcon, $label, $value]) {
            $lines[] = $rowIcon . ' <b>' . self::escape($label) . ':</b> ' . self::escape($value);
        }

        $lines[] = '🕒 <b>Checked:</b> ' . self::escape($this->timestamp($now));

        if ($alert->action !== null && $alert->action !== '') {
            $lines[] = '';
            $lines[] = '<b>Recommended action</b>';
            $lines[] = self::escape($alert->action);
        }

        $link = $this->link($alert->link);
        if ($link !== null) {
            $lines[] = '';
            $lines[] = '🔗 <a href="' . $link . '">Open in HostOps</a>';
        }

        $lines[] = '';
        $lines[] = 'Alert reference: ' . self::escape($alert->reference());

        return implode("\n", $lines);
    }

    /**
     * Several alerts from one run, condensed into a single scannable message
     * so a synchronisation never floods the chat.
     *
     * @param array<int, Alert> $alerts
     */
    public function digest(array $alerts, ?DateTimeImmutable $now = null): string
    {
        if ($alerts === []) {
            return '';
        }

        if (count($alerts) === 1) {
            return $this->format($alerts[0], $now);
        }

        $bySeverity = [
            Alert::SEVERITY_CRITICAL => [],
            Alert::SEVERITY_WARNING  => [],
            Alert::SEVERITY_INFO     => [],
        ];
        foreach ($alerts as $alert) {
            $bySeverity[$alert->severity][] = $alert;
        }

        $lines = [];
        $lines[] = '📋 <b>PARAGON HOSTOPS ALERT SUMMARY</b>';
        $lines[] = '';
        if ($this->serverName !== null && $this->serverName !== '') {
            $lines[] = '🖥 <b>Server:</b> ' . self::escape($this->serverName);
        }
        $lines[] = '🕒 <b>Checked:</b> ' . self::escape($this->timestamp($now));
        $lines[] = '';

        foreach ($bySeverity as $severity => $items) {
            if ($items === []) {
                continue;
            }
            $lines[] = self::SEVERITY_ICON[$severity] . ' ' . self::escape(ucfirst($severity)) . ': ' . count($items);
        }

        // Detail lines, most severe first, capped so the message stays readable.
        $shown = 0;
        foreach ($bySeverity as $severity => $items) {
            if ($items === [] || $shown >= self::DIGEST_LIMIT) {
                continue;
            }
            $lines[] = '';
            $lines[] = '<b>' . self::escape(ucfirst($severity)) . '</b>';
            foreach ($items as $alert) {
                if ($shown >= self::DIGEST_LIMIT) {
                    break;
                }
                $lines[] = '• ' . self::escape($this->digestLine($alert));
                $shown++;
            }
        }

        $remaining = count($alerts) - $shown;
        if ($remaining > 0) {
            $lines[] = '';
            $lines[] = self::escape('…and ' . $remaining . ' additional alert' . ($remaining === 1 ? '' : 's') . '.');
        }

        $link = $this->link('/notifications');
        if ($link !== null) {
            $lines[] = '';
            $lines[] = '🔗 <a href="' . $link . '">View all alerts</a>';
        }

        return implode("\n", $lines);
    }

    /**
     * Sent when a previously-alerting condition clears, so the chat shows a
     * resolution rather than going silent.
     *
     * @param array<int, Alert> $resolved
     */
    public function resolved(array $resolved, ?DateTimeImmutable $now = null): string
    {
        $lines = [];
        $lines[] = '✅ <b>ALERT RESOLVED</b>';
        $lines[] = '';
        $lines[] = count($resolved) === 1
            ? '<b>' . self::escape($resolved[0]->title) . '</b>'
            : '<b>' . count($resolved) . ' alerts cleared</b>';
        $lines[] = '';

        foreach (array_slice($resolved, 0, self::DIGEST_LIMIT) as $alert) {
            $lines[] = '• ' . self::escape($this->digestLine($alert));
        }

        $remaining = count($resolved) - min(count($resolved), self::DIGEST_LIMIT);
        if ($remaining > 0) {
            $lines[] = self::escape('…and ' . $remaining . ' more.');
        }

        $lines[] = '';
        $lines[] = '🕒 <b>Resolved:</b> ' . self::escape($this->timestamp($now));

        return implode("\n", $lines);
    }

    /**
     * The inline keyboard sent alongside a message. Navigation only — Telegram
     * is never a channel for privileged WHM actions.
     *
     * @return array<string, mixed>|null
     */
    public function inlineKeyboard(?string $path, string $label = 'Open in HostOps'): ?array
    {
        $url = $this->link($path);
        if ($url === null) {
            return null;
        }

        return ['inline_keyboard' => [[['text' => $label, 'url' => $url]]]];
    }

    // -----------------------------------------------------------------

    /**
     * Field rows for a single alert, skipping anything unknown.
     *
     * @return array<int, array{0:string,1:string,2:string}>
     */
    private function detailRows(Alert $alert, string $categoryIcon): array
    {
        $rows = [];

        if ($this->serverName !== null && $this->serverName !== '') {
            $rows[] = ['🖥', 'Server', $this->serverName];
        }
        if ($alert->client !== null && $alert->client !== '') {
            $rows[] = ['👤', 'Client', $alert->client];
        }
        if ($alert->domain !== null && $alert->domain !== '') {
            $rows[] = ['🌐', 'Domain', $alert->domain];
        }
        if ($alert->account !== null && $alert->account !== '') {
            $rows[] = ['📦', 'Account', $alert->account];
        }
        if ($alert->package !== null && $alert->package !== '') {
            $rows[] = ['🏷', 'Package', $alert->package];
        }
        if ($alert->current !== null && $alert->current !== '') {
            $rows[] = [$categoryIcon, 'Current', $alert->current];
        }
        if ($alert->threshold !== null && $alert->threshold !== '') {
            $rows[] = ['⚠️', 'Threshold', $alert->threshold];
        }
        if ($alert->dueDate !== null && $alert->dueDate !== '') {
            $rows[] = ['📅', $alert->category === 'ssl' ? 'Expires' : 'Due', $this->humanDate($alert->dueDate)];
        }
        if ($alert->days !== null) {
            $rows[] = ['⏳', 'Remaining', $alert->days < 0
                ? abs($alert->days) . ' day' . (abs($alert->days) === 1 ? '' : 's') . ' overdue'
                : $alert->days . ' day' . ($alert->days === 1 ? '' : 's')];
        }

        return $rows;
    }

    private function digestLine(Alert $alert): string
    {
        $subject = $alert->domain ?? $alert->account ?? 'Account';
        return $subject . ' — ' . $alert->title;
    }

    private function categoryHeading(string $category): string
    {
        return match ($category) {
            'ssl'       => 'SSL CERTIFICATE',
            'domain'    => 'DOMAIN RENEWAL',
            'disk'      => 'DISK USAGE',
            'bandwidth' => 'BANDWIDTH USAGE',
            'uptime'    => 'UPTIME',
            default     => 'HOSTING ALERT',
        };
    }

    /**
     * Absolute dashboard URL, or null when APP_URL isn't configured (in which
     * case we omit the link rather than emitting a broken one).
     */
    private function link(?string $path): ?string
    {
        if ($path === null || $path === '' || $this->appUrl === '') {
            return null;
        }

        return rtrim($this->appUrl, '/') . '/' . ltrim($path, '/');
    }

    private function timestamp(?DateTimeImmutable $now): string
    {
        $now ??= new DateTimeImmutable('now');
        return $now->setTimezone(new DateTimeZone(self::TIMEZONE))->format('j F Y \a\t H:i');
    }

    private function humanDate(string $date): string
    {
        $ts = strtotime($date);
        return $ts === false ? $date : date('j F Y', $ts);
    }
}
