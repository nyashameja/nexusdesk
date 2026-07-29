<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Domains;

use ParagonHostOps\Repositories\DomainRepository;
use ParagonHostOps\Repositories\NotificationRepository;

/**
 * Orchestrates domain expiry checks: looks up each domain, stores the result,
 * derives a status, and raises notifications for domains that are expiring or
 * expired. Runs on demand or from cron.
 */
final class DomainExpiryService
{
    public function __construct(
        private DomainRepository $domains,
        private DomainExpiryChecker $checker,
        private NotificationRepository $notifications,
    ) {
    }

    /**
     * Check one domain and persist the outcome.
     *
     * @param array<string, mixed> $domain
     * @return array{updated:bool, expires_at:?string, status:string, message:string}
     */
    public function check(array $domain): array
    {
        $result = $this->checker->lookup((string) $domain['domain']);
        $expires = $result['expires_at'];

        if ($expires === null) {
            return ['updated' => false, 'expires_at' => $domain['expires_at'] ?? null, 'status' => (string) ($domain['status'] ?? 'unknown'), 'message' => $result['message']];
        }

        $status = self::statusFor($expires);
        $this->domains->applyLookup((int) $domain['id'], $expires, $result['registrar'], $status);

        $this->maybeNotify((string) $domain['domain'], $expires, $status);

        return ['updated' => true, 'expires_at' => $expires, 'status' => $status, 'message' => $result['message']];
    }

    /**
     * Check every stored domain (used by the "check all" action and cron).
     *
     * @return array{checked:int, updated:int, expiring:int, expired:int}
     */
    public function checkAll(): array
    {
        $checked = $updated = $expiring = $expired = 0;

        foreach ($this->domains->allForCheck() as $domain) {
            $checked++;
            $r = $this->check($domain);
            if ($r['updated']) {
                $updated++;
            }
            if ($r['status'] === 'expired') {
                $expired++;
            } elseif ($r['status'] === 'expiring') {
                $expiring++;
            }
        }

        return ['checked' => $checked, 'updated' => $updated, 'expiring' => $expiring, 'expired' => $expired];
    }

    /**
     * Derive a domain status from an expiry date (pure, unit-tested).
     */
    public static function statusFor(?string $expiresAt): string
    {
        if ($expiresAt === null || $expiresAt === '') {
            return 'unknown';
        }
        $ts = strtotime($expiresAt);
        if ($ts === false) {
            return 'unknown';
        }
        $days = (int) floor(($ts - strtotime('today')) / 86400);
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 30) {
            return 'expiring';
        }
        return 'active';
    }

    private function maybeNotify(string $domain, string $expires, string $status): void
    {
        if ($status === 'expired') {
            $this->notifications->createOnce('domain', "Domain expired: {$domain}", "Expired on {$expires}.", 'danger');
        } elseif ($status === 'expiring') {
            $this->notifications->createOnce('domain', "Domain expiring soon: {$domain}", "Expires on {$expires}.", 'warning');
        }
    }
}
