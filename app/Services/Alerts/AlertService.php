<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Alerts;

use ParagonHostOps\Repositories\AlertRepository;
use ParagonHostOps\Repositories\NotificationRepository;

/**
 * Detects alert conditions (SSL / domain expiry, disk & bandwidth usage),
 * de-duplicates them against the alerts log, records in-app notifications, and
 * sends a single digest per run through the configured channels.
 *
 * Threshold bucketing is pure and unit-tested; the run() method wires it to the
 * repositories and channels.
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
     * @return array{new:int, active:int, resolved:int, emailed:bool}
     */
    public function run(): array
    {
        $alerts = $this->collect();               // key => [category,message,severity]
        $active = array_keys($alerts);

        $resolved = $this->alerts->deleteResolved($active);
        $already  = $this->alerts->sentKeys();
        $newKeys  = array_values(array_diff($active, $already));

        if ($newKeys === []) {
            return ['new' => 0, 'active' => count($active), 'resolved' => $resolved, 'emailed' => false];
        }

        // In-app notifications for each new alert.
        foreach ($newKeys as $key) {
            $a = $alerts[$key];
            $this->notifications->createOnce($a['category'], $a['message'], '', $a['severity']);
        }

        // One digest email covering all new alerts.
        $emailed = false;
        if ($this->enabled) {
            $subject = 'Paragon HostOps: ' . count($newKeys) . ' new alert' . (count($newKeys) === 1 ? '' : 's');
            $body    = $this->digest(array_map(static fn (string $k) => $alerts[$k], $newKeys));
            foreach ($this->channels as $channel) {
                if ($channel->isConfigured() && $channel->notify($subject, $body)) {
                    $emailed = true;
                }
            }
        }

        $this->alerts->markSent(array_map(
            static fn (string $k): array => ['key' => $k, 'category' => $alerts[$k]['category'], 'message' => $alerts[$k]['message']],
            $newKeys
        ));

        return ['new' => count($newKeys), 'active' => count($active), 'resolved' => $resolved, 'emailed' => $emailed];
    }

    /**
     * Build the active alert set keyed by a stable de-dup key.
     *
     * @return array<string, array{category:string, message:string, severity:string}>
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
            $out["ssl:{$row['domain']}:{$bucket}"] = [
                'category' => 'ssl',
                'message'  => $days < 0
                    ? "SSL certificate for {$row['domain']} has expired."
                    : "SSL certificate for {$row['domain']} expires in {$days} day(s).",
                'severity' => self::severity($bucket),
            ];
        }

        foreach ($this->alerts->domainCandidates() as $row) {
            $days   = (int) $row['days'];
            $bucket = self::bucketDays($days);
            if ($bucket === null) {
                continue;
            }
            $out["domain:{$row['id']}:{$bucket}"] = [
                'category' => 'domain',
                'message'  => $days < 0
                    ? "Domain {$row['domain']} has expired."
                    : "Domain {$row['domain']} expires in {$days} day(s).",
                'severity' => self::severity($bucket),
            ];
        }

        foreach ($this->alerts->usageCandidates() as $row) {
            $disk = self::bucketUsage((float) $row['disk_pct']);
            if ($disk !== null) {
                $out["disk:{$row['id']}:{$disk}"] = [
                    'category' => 'disk',
                    'message'  => "{$row['domain']} disk usage at {$row['disk_pct']}%.",
                    'severity' => self::severity($disk),
                ];
            }
            $bw = self::bucketUsage((float) $row['bw_pct']);
            if ($bw !== null) {
                $out["bw:{$row['id']}:{$bw}"] = [
                    'category' => 'bandwidth',
                    'message'  => "{$row['domain']} bandwidth usage at {$row['bw_pct']}%.",
                    'severity' => self::severity($bw),
                ];
            }
        }

        return $out;
    }

    /**
     * @param array<int, array{category:string, message:string, severity:string}> $items
     */
    private function digest(array $items): string
    {
        $groups = ['ssl' => [], 'domain' => [], 'disk' => [], 'bandwidth' => []];
        foreach ($items as $item) {
            $groups[$item['category']][] = $item['message'];
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

    private static function severity(string $bucket): string
    {
        return match ($bucket) {
            'expired', '5', '95' => 'danger',
            '15', '80'           => 'warning',
            default              => 'info',
        };
    }
}
