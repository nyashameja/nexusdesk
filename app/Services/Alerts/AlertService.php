<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Alerts;

use ParagonHostOps\Repositories\AlertRepository;
use ParagonHostOps\Repositories\NotificationRepository;

/**
 * Detects alert conditions (SSL / domain expiry, disk & bandwidth usage),
 * de-duplicates them against the alerts log, records in-app notifications, and
 * hands the structured alerts to each configured channel.
 *
 * Detection stays free of presentation: this class produces Alert objects and
 * a plain-text digest; channels decide how to render them.
 *
 * Threshold bucketing is pure and unit-tested; run() wires it to repositories
 * and channels.
 */
final class AlertService
{
    /**
     * @param array<int, AlertChannelInterface> $channels
     */
    public function __construct(
        private AlertRepository $alerts,
        private NotificationRepository $notifications,
        private array $channels,
        private bool $enabled,
    ) {
    }

    /**
     * @return array{new:int, active:int, resolved:int, emailed:bool, delivered:array<int,string>, failed:array<int,string>}
     */
    public function run(): array
    {
        $alerts = $this->collect();               // key => Alert
        $active = array_keys($alerts);

        $resolved = $this->alerts->deleteResolved($active);
        $already  = $this->alerts->sentKeys();
        $newKeys  = array_values(array_diff($active, $already));

        if ($newKeys === []) {
            return [
                'new' => 0, 'active' => count($active), 'resolved' => $resolved,
                'emailed' => false, 'delivered' => [], 'failed' => [],
            ];
        }

        $newAlerts = array_map(static fn (string $k): Alert => $alerts[$k], $newKeys);

        // In-app notifications for each new alert.
        foreach ($newAlerts as $alert) {
            $this->notifications->createOnce($alert->category, $alert->summary, $alert->link ?? '', $this->notificationSeverity($alert));
        }

        // One digest per run, per channel.
        $delivered = [];
        $failed    = [];
        if ($this->enabled) {
            $subject = 'Paragon HostOps: ' . count($newAlerts) . ' new alert' . (count($newAlerts) === 1 ? '' : 's');
            $body    = $this->digest($newAlerts);

            foreach ($this->channels as $channel) {
                if (!$channel->isConfigured()) {
                    continue;
                }
                if ($channel->notifyAlerts($newAlerts, $subject, $body)) {
                    $delivered[] = $channel->name();
                } else {
                    $failed[] = $channel->name();
                }
            }
        }

        $this->alerts->markSent(array_map(
            static fn (Alert $a): array => ['key' => $a->key, 'category' => $a->category, 'message' => $a->summary],
            $newAlerts
        ));

        return [
            'new'       => count($newKeys),
            'active'    => count($active),
            'resolved'  => $resolved,
            'emailed'   => $delivered !== [],
            'delivered' => $delivered,
            'failed'    => $failed,
        ];
    }

    /**
     * Build the active alert set keyed by a stable de-dup key.
     *
     * @return array<string, Alert>
     */
    private function collect(): array
    {
        $out = [];

        foreach ($this->alerts->sslCandidates() as $row) {
            $days   = (int) $row['days_remaining'];
            $bucket = self::bucketDays($days);
            if ($bucket === null) {
                continue;
            }
            $domain = (string) $row['domain'];
            $key    = "ssl:{$domain}:{$bucket}";
            $out[$key] = new Alert(
                key:       $key,
                category:  'ssl',
                severity:  self::severity($bucket),
                title:     $days < 0 ? 'SSL certificate has expired' : "SSL certificate expires in {$days} days",
                summary:   $days < 0
                    ? "SSL certificate for {$domain} has expired."
                    : "SSL certificate for {$domain} expires in {$days} day(s).",
                domain:    $domain,
                client:    self::nullable($row['client_name'] ?? null),
                account:   self::nullable($row['username'] ?? null),
                package:   self::nullable($row['package'] ?? null),
                threshold: self::daysThresholdLabel($bucket),
                dueDate:   self::nullable($row['valid_to'] ?? null),
                days:      $days,
                link:      isset($row['account_id']) && $row['account_id'] !== null
                    ? '/accounts/' . (int) $row['account_id']
                    : '/ssl',
                action:    'Confirm that AutoSSL is enabled for this domain and run an SSL validation check.',
            );
        }

        foreach ($this->alerts->domainCandidates() as $row) {
            $days   = (int) $row['days'];
            $bucket = self::bucketDays($days);
            if ($bucket === null) {
                continue;
            }
            $domain = (string) $row['domain'];
            $key    = "domain:{$row['id']}:{$bucket}";
            $out[$key] = new Alert(
                key:       $key,
                category:  'domain',
                severity:  self::severity($bucket),
                title:     $days < 0 ? 'Domain registration has expired' : "Domain expires in {$days} days",
                summary:   $days < 0
                    ? "Domain {$domain} has expired."
                    : "Domain {$domain} expires in {$days} day(s).",
                domain:    $domain,
                client:    self::nullable($row['client_name'] ?? null),
                current:   empty($row['auto_renew']) ? 'Auto-renew not confirmed' : 'Auto-renew enabled',
                threshold: self::daysThresholdLabel($bucket),
                dueDate:   self::nullable($row['expires_at'] ?? null),
                days:      $days,
                link:      '/domains/' . (int) $row['id'],
                action:    'Confirm who is responsible for renewal and contact the client if payment is required.',
            );
        }

        foreach ($this->alerts->usageCandidates() as $row) {
            $accountId = (int) $row['id'];
            $domain    = (string) $row['domain'];
            $client    = self::nullable($row['client_name'] ?? null);
            $username  = self::nullable($row['username'] ?? null);
            $package   = self::nullable($row['package'] ?? null);

            $disk = self::bucketUsage((float) $row['disk_pct']);
            if ($disk !== null) {
                $key = "disk:{$accountId}:{$disk}";
                $out[$key] = new Alert(
                    key:       $key,
                    category:  'disk',
                    severity:  self::severity($disk),
                    title:     'Disk usage is approaching the limit',
                    summary:   "{$domain} disk usage at {$row['disk_pct']}%.",
                    domain:    $domain,
                    client:    $client,
                    account:   $username,
                    package:   $package,
                    current:   self::usageLabel($row['disk_used_mb'] ?? null, $row['disk_limit_mb'] ?? null, (float) $row['disk_pct']),
                    threshold: $disk . '%',
                    link:      '/accounts/' . $accountId,
                    action:    'Review large files, email storage and backups, or recommend a package upgrade.',
                );
            }

            $bw = self::bucketUsage((float) $row['bw_pct']);
            if ($bw !== null) {
                $key = "bw:{$accountId}:{$bw}";
                $out[$key] = new Alert(
                    key:       $key,
                    category:  'bandwidth',
                    severity:  self::severity($bw),
                    title:     'Bandwidth usage is approaching the limit',
                    summary:   "{$domain} bandwidth usage at {$row['bw_pct']}%.",
                    domain:    $domain,
                    client:    $client,
                    account:   $username,
                    package:   $package,
                    current:   self::usageLabel($row['bandwidth_used_mb'] ?? null, $row['bandwidth_limit_mb'] ?? null, (float) $row['bw_pct']),
                    threshold: $bw . '%',
                    link:      '/accounts/' . $accountId,
                    action:    'Check for traffic spikes or large downloads, or recommend a package upgrade.',
                );
            }
        }

        return $out;
    }

    /**
     * Plain-text digest, used by email and as the fallback body for any channel
     * without its own formatting.
     *
     * @param array<int, Alert> $items
     */
    public function digest(array $items): string
    {
        $groups = ['ssl' => [], 'domain' => [], 'disk' => [], 'bandwidth' => []];
        foreach ($items as $item) {
            $groups[$item->category][] = $item->summary;
        }

        $titles = ['ssl' => 'SSL certificates', 'domain' => 'Domains', 'disk' => 'Disk usage', 'bandwidth' => 'Bandwidth usage'];
        $lines  = ['Paragon HostOps detected the following new alerts:', ''];

        foreach ($groups as $key => $messages) {
            if ($messages === []) {
                continue;
            }
            $lines[] = strtoupper($titles[$key]);
            foreach ($messages as $m) {
                $lines[] = '  - ' . $m;
            }
            $lines[] = '';
        }

        $lines[] = 'This is an automated message from Paragon HostOps.';
        return implode("\n", $lines);
    }

    /**
     * Assign a days-remaining value to the smallest matching threshold bucket
     * (30 / 15 / 5 / expired). Returns null when more than 30 days remain.
     */
    public static function bucketDays(int $days): ?string
    {
        return match (true) {
            $days < 0   => 'expired',
            $days <= 5  => '5',
            $days <= 15 => '15',
            $days <= 30 => '30',
            default     => null,
        };
    }

    /**
     * Assign a usage percentage to a threshold bucket (95 / 80). Null below 80.
     */
    public static function bucketUsage(float $percent): ?string
    {
        return match (true) {
            $percent >= 95 => '95',
            $percent >= 80 => '80',
            default        => null,
        };
    }

    /**
     * Alert severity for a crossed bucket. The tightest buckets (expired, 5
     * days, 95%) are treated as critical.
     */
    public static function severity(string $bucket): string
    {
        return match ($bucket) {
            'expired', '5', '95' => Alert::SEVERITY_CRITICAL,
            '15', '80'           => Alert::SEVERITY_WARNING,
            default              => Alert::SEVERITY_INFO,
        };
    }

    // -----------------------------------------------------------------

    /** Map alert severity onto the in-app notification vocabulary. */
    private function notificationSeverity(Alert $alert): string
    {
        return match ($alert->severity) {
            Alert::SEVERITY_CRITICAL => 'danger',
            Alert::SEVERITY_WARNING  => 'warning',
            default                  => 'info',
        };
    }

    private static function daysThresholdLabel(string $bucket): string
    {
        return $bucket === 'expired' ? 'Expired' : $bucket . ' days';
    }

    /** "8.7 GB of 10 GB (87%)" when limits are known, otherwise just the percentage. */
    private static function usageLabel(mixed $usedMb, mixed $limitMb, float $percent): string
    {
        $pct = rtrim(rtrim(number_format($percent, 1), '0'), '.') . '%';

        if ($usedMb === null || $limitMb === null || (float) $limitMb <= 0) {
            return $pct;
        }

        $gb = static fn (float $mb): string => rtrim(rtrim(number_format($mb / 1024, 1), '0'), '.') . ' GB';

        return $gb((float) $usedMb) . ' of ' . $gb((float) $limitMb) . ' (' . $pct . ')';
    }

    private static function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
