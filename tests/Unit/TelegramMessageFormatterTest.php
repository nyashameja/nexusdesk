<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use DateTimeImmutable;
use ParagonHostOps\Services\Alerts\Alert;
use ParagonHostOps\Services\Alerts\TelegramMessageFormatter;
use PHPUnit\Framework\TestCase;

final class TelegramMessageFormatterTest extends TestCase
{
    private const NOW = '2026-07-30 11:18:00';

    private function formatter(): TelegramMessageFormatter
    {
        return new TelegramMessageFormatter('https://hostops.example.com', 'cpr48-za1');
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'));
    }

    private function sslAlert(): Alert
    {
        return new Alert(
            key: 'ssl:example.co.za:5',
            category: 'ssl',
            severity: Alert::SEVERITY_CRITICAL,
            title: 'SSL certificate expires in 5 days',
            summary: 'SSL certificate for example.co.za expires in 5 day(s).',
            domain: 'example.co.za',
            client: 'Example Client',
            account: 'examplecp',
            threshold: '5 days',
            dueDate: '2026-08-04',
            days: 5,
            link: '/accounts/123',
            action: 'Confirm that AutoSSL is enabled.',
        );
    }

    // ---- Escaping -------------------------------------------------------

    public function test_escape_replaces_telegram_html_metacharacters(): void
    {
        $this->assertSame('&amp;&lt;&gt;', TelegramMessageFormatter::escape('&<>'));
        $this->assertSame('Smith &amp; Co', TelegramMessageFormatter::escape('Smith & Co'));
    }

    public function test_escape_is_applied_before_ampersands_in_entities(): void
    {
        // & must be escaped first, otherwise "&lt;" becomes "&amp;lt;".
        $this->assertSame('&lt;script&gt;', TelegramMessageFormatter::escape('<script>'));
    }

    public function test_dynamic_values_cannot_inject_markup(): void
    {
        $alert = new Alert(
            key: 'disk:1:80',
            category: 'disk',
            severity: Alert::SEVERITY_WARNING,
            title: 'Disk usage is approaching the limit',
            summary: 'x',
            domain: 'evil.example',
            client: 'Smith & Sons <b>Pty</b>',
            current: '87%',
            link: '/accounts/1',
        );

        $out = $this->formatter()->format($alert, $this->now());

        $this->assertStringContainsString('Smith &amp; Sons &lt;b&gt;Pty&lt;/b&gt;', $out);
        $this->assertStringNotContainsString('<b>Pty</b>', $out);
    }

    // ---- Single alert ---------------------------------------------------

    public function test_single_alert_includes_key_operational_fields(): void
    {
        $out = $this->formatter()->format($this->sslAlert(), $this->now());

        $this->assertStringContainsString('CRITICAL', $out);
        $this->assertStringContainsString('SSL CERTIFICATE', $out);
        $this->assertStringContainsString('example.co.za', $out);
        $this->assertStringContainsString('Example Client', $out);
        $this->assertStringContainsString('examplecp', $out);
        $this->assertStringContainsString('cpr48-za1', $out);
        $this->assertStringContainsString('4 August 2026', $out);
        $this->assertStringContainsString('5 days', $out);
        $this->assertStringContainsString('Recommended action', $out);
    }

    public function test_single_alert_includes_dashboard_link_and_reference(): void
    {
        $out = $this->formatter()->format($this->sslAlert(), $this->now());

        $this->assertStringContainsString('https://hostops.example.com/accounts/123', $out);
        $this->assertStringContainsString('Alert reference: #ALT-', $out);
    }

    public function test_timestamp_uses_south_african_time(): void
    {
        // 11:18 UTC is 13:18 in Africa/Johannesburg (UTC+2, no DST).
        $out = $this->formatter()->format($this->sslAlert(), $this->now());

        $this->assertStringContainsString('30 July 2026 at 13:18', $out);
    }

    public function test_unknown_fields_are_omitted_rather_than_printed_as_unknown(): void
    {
        $alert = new Alert(
            key: 'domain:9:30',
            category: 'domain',
            severity: Alert::SEVERITY_WARNING,
            title: 'Domain expires in 30 days',
            summary: 'x',
            domain: 'bare.co.za',
        );

        $out = $this->formatter()->format($alert, $this->now());

        $this->assertStringNotContainsString('Client:', $out);
        $this->assertStringNotContainsString('Package:', $out);
        $this->assertStringNotContainsString('unknown', strtolower($out));
    }

    public function test_link_is_omitted_when_app_url_is_not_configured(): void
    {
        $out = (new TelegramMessageFormatter('', 'cpr48-za1'))->format($this->sslAlert(), $this->now());

        $this->assertStringNotContainsString('<a href', $out);
    }

    public function test_overdue_days_are_described_as_overdue(): void
    {
        $alert = new Alert(
            key: 'domain:1:expired',
            category: 'domain',
            severity: Alert::SEVERITY_CRITICAL,
            title: 'Domain registration has expired',
            summary: 'x',
            domain: 'gone.co.za',
            days: -3,
        );

        $this->assertStringContainsString('3 days overdue', $this->formatter()->format($alert, $this->now()));
    }

    // ---- Digest ---------------------------------------------------------

    /** @return array<int, Alert> */
    private function manyAlerts(int $count, string $severity = Alert::SEVERITY_WARNING): array
    {
        $out = [];
        for ($i = 1; $i <= $count; $i++) {
            $out[] = new Alert(
                key: "domain:{$i}:30",
                category: 'domain',
                severity: $severity,
                title: 'Domain expires in 30 days',
                summary: 'x',
                domain: "site{$i}.co.za",
                link: "/domains/{$i}",
            );
        }
        return $out;
    }

    public function test_digest_of_one_alert_renders_the_full_single_message(): void
    {
        $out = $this->formatter()->digest([$this->sslAlert()], $this->now());

        $this->assertStringContainsString('Recommended action', $out);
        $this->assertStringNotContainsString('ALERT SUMMARY', $out);
    }

    public function test_digest_groups_by_severity_with_counts(): void
    {
        $alerts = array_merge(
            $this->manyAlerts(2, Alert::SEVERITY_CRITICAL),
            $this->manyAlerts(3, Alert::SEVERITY_WARNING),
        );

        $out = $this->formatter()->digest($alerts, $this->now());

        $this->assertStringContainsString('ALERT SUMMARY', $out);
        $this->assertStringContainsString('Critical: 2', $out);
        $this->assertStringContainsString('Warning: 3', $out);
        $this->assertStringContainsString('cpr48-za1', $out);
    }

    public function test_digest_caps_detail_lines_and_summarises_the_remainder(): void
    {
        $alerts = $this->manyAlerts(TelegramMessageFormatter::DIGEST_LIMIT + 8);

        $out = $this->formatter()->digest($alerts, $this->now());

        $this->assertStringContainsString('…and 8 additional alerts.', $out);
        $this->assertSame(
            TelegramMessageFormatter::DIGEST_LIMIT,
            substr_count($out, '• '),
            'Digest must not list more than DIGEST_LIMIT individual alerts.'
        );
    }

    public function test_digest_singularises_the_remainder_line(): void
    {
        $alerts = $this->manyAlerts(TelegramMessageFormatter::DIGEST_LIMIT + 1);

        $this->assertStringContainsString(
            '…and 1 additional alert.',
            $this->formatter()->digest($alerts, $this->now())
        );
    }

    public function test_digest_of_no_alerts_is_empty(): void
    {
        $this->assertSame('', $this->formatter()->digest([], $this->now()));
    }

    public function test_digest_lists_critical_before_warning(): void
    {
        $alerts = array_merge(
            $this->manyAlerts(1, Alert::SEVERITY_WARNING),
            $this->manyAlerts(1, Alert::SEVERITY_CRITICAL),
        );

        $out = $this->formatter()->digest($alerts, $this->now());

        $this->assertLessThan(
            strpos($out, '<b>Warning</b>'),
            strpos($out, '<b>Critical</b>'),
            'Critical alerts must appear above warnings.'
        );
    }

    // ---- Recovery -------------------------------------------------------

    public function test_resolved_message_reports_recovery(): void
    {
        $out = $this->formatter()->resolved([$this->sslAlert()], $this->now());

        $this->assertStringContainsString('ALERT RESOLVED', $out);
        $this->assertStringContainsString('Resolved:', $out);
    }

    // ---- Inline keyboard ------------------------------------------------

    public function test_inline_keyboard_builds_an_absolute_navigation_button(): void
    {
        $kb = $this->formatter()->inlineKeyboard('/domains/7');

        $this->assertSame('https://hostops.example.com/domains/7', $kb['inline_keyboard'][0][0]['url']);
        $this->assertSame('Open in HostOps', $kb['inline_keyboard'][0][0]['text']);
    }

    public function test_inline_keyboard_is_null_without_a_link(): void
    {
        $this->assertNull($this->formatter()->inlineKeyboard(null));
        $this->assertNull((new TelegramMessageFormatter(''))->inlineKeyboard('/domains/7'));
    }

    // ---- Reference ------------------------------------------------------

    public function test_reference_is_stable_for_the_same_alert_key(): void
    {
        $a = $this->sslAlert();
        $this->assertSame($a->reference(), $this->sslAlert()->reference());
        $this->assertMatchesRegularExpression('/^#ALT-[0-9A-F]{6}$/', $a->reference());
    }
}
