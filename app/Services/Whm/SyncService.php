<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm;

use ParagonHostOps\Core\Logger;
use ParagonHostOps\Repositories\AccountRepository;
use ParagonHostOps\Repositories\CapabilityRepository;
use ParagonHostOps\Repositories\PackageRepository;
use ParagonHostOps\Repositories\ServerRepository;
use ParagonHostOps\Repositories\SslRepository;
use ParagonHostOps\Repositories\SyncRepository;
use ParagonHostOps\Services\Whm\Exceptions\WhmException;
use ParagonHostOps\Services\Whm\Exceptions\WhmPermissionException;

/**
 * Read-only synchronisation service.
 *
 * Pulls the visible WHM data (packages, accounts, disk, bandwidth, SSL) and
 * caches it locally so the dashboard never calls WHM on page load. Every WHM
 * call is wrapped so a permission/transport failure on one function degrades
 * gracefully (recorded, run marked "partial") instead of aborting the whole
 * sync. A MySQL advisory lock prevents concurrent full syncs.
 */
final class SyncService
{
    private int $serverId = 0;
    private ?int $currentRunId = null;

    public function __construct(
        private WhmApiClient $client,
        private WhmNormalizer $normalizer,
        private ServerRepository $servers,
        private AccountRepository $accounts,
        private PackageRepository $packages,
        private SslRepository $ssl,
        private CapabilityRepository $capabilities,
        private SyncRepository $sync,
        private Logger $logger,
        private string $whmHost,
    ) {
    }

    /**
     * Full synchronisation of all read-only data.
     */
    public function syncAll(): SyncResult
    {
        $result = new SyncResult();

        if (!$this->client->isConfigured()) {
            $result->locked = false;
            $result->addError('WHM is not configured.');
            $runId = $this->sync->start('full');
            $this->sync->finish($runId, 'failed', $result->counts(), 'WHM is not configured.');
            return $result;
        }

        if (!$this->sync->acquireLock()) {
            $result->locked = true;
            return $result;
        }

        $runId = $this->sync->start('full');
        $this->currentRunId = $runId;

        try {
            $this->serverId = $this->resolveServer();

            $this->syncPackagesInto($result);
            $usernames = $this->syncAccountsInto($result);
            $this->syncBandwidthInto($result);
            $this->syncSslInto($result);

            // Retire accounts that are no longer visible to WHM.
            if ($usernames !== []) {
                $retired = $this->accounts->softDeleteMissing($this->serverId, $usernames);
                if ($retired > 0) {
                    $result->note("{$retired} account(s) no longer visible were archived");
                }
            }

            $this->sync->finish($runId, $result->status(), $result->counts(), $result->summary());
        } catch (\Throwable $e) {
            // Unexpected failure — record safely and mark the run failed.
            $this->logger->error('Sync run failed: ' . $e->getMessage());
            $this->sync->recordError($runId, 'sync', $e->getMessage());
            $result->addError('Synchronisation aborted unexpectedly.');
            $this->sync->finish($runId, 'failed', $result->counts(), 'Synchronisation aborted unexpectedly.');
        } finally {
            $this->currentRunId = null;
            $this->sync->releaseLock();
        }

        return $result;
    }

    /**
     * Refresh a single account by username (accountsummary + SSL status).
     */
    public function syncAccount(string $username): SyncResult
    {
        $result = new SyncResult();

        if (!$this->client->isConfigured()) {
            $result->addError('WHM is not configured.');
            return $result;
        }

        $runId = $this->sync->start('account');
        $this->currentRunId = $runId;
        $this->serverId = $this->resolveServer();

        try {
            $response = $this->client->get('accountsummary', ['user' => $username]);
            $accts = $response->get('acct', []);
            foreach (is_array($accts) ? $accts : [] as $acct) {
                $this->persistAccount($this->normalizer->account($acct), $result);
            }
            $this->sync->finish($runId, $result->status(), $result->counts(), $result->summary());
        } catch (WhmException $e) {
            $this->sync->recordError($runId, 'accountsummary', $e->getMessage());
            $result->addError($e->safeMessage());
            $this->sync->finish($runId, $result->status(), $result->counts(), $e->safeMessage());
        } finally {
            $this->currentRunId = null;
        }

        return $result;
    }

    // ---------------------------------------------------------------------

    private function resolveServer(): int
    {
        $serverId = $this->servers->ensure($this->whmHost);

        // Enrich with server info when the token permits it (best effort).
        try {
            $info = $this->client->get('get_server_information');
            $this->capabilities->record('get_server_information', CapabilityChecker::AVAILABLE, 'Available');
            $this->servers->updateInfo($serverId, [
                'ip'          => $info->get('ip'),
                'whm_version' => $info->get('version'),
                'os'          => $info->get('operatingsystem'),
            ]);
        } catch (WhmException $e) {
            $this->recordCapabilityFromException('get_server_information', $e);
        }

        return $serverId;
    }

    private function syncPackagesInto(SyncResult $result): void
    {
        try {
            $response = $this->client->get('listpkgs');
            $this->capabilities->record('listpkgs', CapabilityChecker::AVAILABLE, 'Available');
            $pkgs = $response->get('pkg', []);
            $count = 0;
            foreach (is_array($pkgs) ? $pkgs : [] as $pkg) {
                $this->packages->upsert($this->serverId, $this->normalizer->package($pkg));
                $count++;
            }
            $result->note("{$count} package(s) synced");
        } catch (WhmException $e) {
            $this->recordStepFailure('listpkgs', 'packages', $e, $result);
        }
    }

    /**
     * @return array<int, string> usernames seen (for retirement pass)
     */
    private function syncAccountsInto(SyncResult $result): array
    {
        $usernames = [];
        try {
            $response = $this->client->get('listaccts');
            $this->capabilities->record('listaccts', CapabilityChecker::AVAILABLE, 'Available');
            $accts = $response->get('acct', []);
            foreach (is_array($accts) ? $accts : [] as $acct) {
                $normalised = $this->normalizer->account($acct);
                if ($normalised['username'] === '') {
                    continue;
                }
                $usernames[] = $normalised['username'];
                $this->persistAccount($normalised, $result);
            }
        } catch (WhmException $e) {
            $this->recordStepFailure('listaccts', 'accounts', $e, $result);
        }
        return $usernames;
    }

    /**
     * @param array<string, mixed> $normalised
     */
    private function persistAccount(array $normalised, SyncResult $result): void
    {
        $outcome = $this->accounts->upsert($this->serverId, $normalised);
        $this->accounts->upsertDiskUsage($outcome['id'], (int) $normalised['disk_used_mb'], (int) $normalised['disk_limit_mb']);

        $result->processed++;
        $outcome['created'] ? $result->created++ : $result->updated++;
    }

    private function syncBandwidthInto(SyncResult $result): void
    {
        try {
            $response = $this->client->get('showbw');
            $this->capabilities->record('showbw', CapabilityChecker::AVAILABLE, 'Available');
            $accts = $response->get('acct', []);
            $count = 0;
            foreach (is_array($accts) ? $accts : [] as $entry) {
                $bw = $this->normalizer->bandwidth($entry);
                if ($bw['username'] === '') {
                    continue;
                }
                if ($this->accounts->updateBandwidthByUsername($this->serverId, $bw['username'], $bw['bandwidth_used_mb'], $bw['bandwidth_limit_mb'])) {
                    $count++;
                }
            }
            $result->note("{$count} bandwidth record(s) synced");
        } catch (WhmException $e) {
            $this->recordStepFailure('showbw', 'bandwidth', $e, $result);
        }
    }

    private function syncSslInto(SyncResult $result): void
    {
        try {
            $response = $this->client->get('fetch_ssl_vhosts');
            $this->capabilities->record('fetch_ssl_vhosts', CapabilityChecker::AVAILABLE, 'Available');
            $vhosts = $response->get('vhosts', []);
            $count = 0;
            foreach (is_array($vhosts) ? $vhosts : [] as $vhost) {
                $normalised = $this->normalizer->ssl($vhost);
                if ($normalised['domain'] === '') {
                    continue;
                }
                $accountId = $this->accounts->findIdByDomain($this->serverId, $normalised['domain']);
                $this->ssl->upsert($accountId, $normalised);
                $this->accounts->setSslStatusByDomain($this->serverId, $normalised['domain'], $normalised['status']);
                $count++;
            }
            $result->note("{$count} SSL certificate(s) synced");
        } catch (WhmException $e) {
            $this->recordStepFailure('fetch_ssl_vhosts', 'ssl', $e, $result);
        }
    }

    // ---------------------------------------------------------------------

    private function recordStepFailure(string $function, string $context, WhmException $e, SyncResult $result): void
    {
        $this->recordCapabilityFromException($function, $e);
        if ($this->currentRunId !== null) {
            $this->sync->recordError($this->currentRunId, $context, $e->getMessage());
        }
        $this->logger->warning("Sync step '{$context}' failed via {$function}: " . $e->getMessage());
        $result->addError("{$context}: " . $e->safeMessage());
    }

    private function recordCapabilityFromException(string $function, WhmException $e): void
    {
        $status = $e instanceof WhmPermissionException
            ? CapabilityChecker::PERMISSION_DENIED
            : CapabilityChecker::SERVER_ERROR;

        $this->capabilities->record($function, $status, $e->safeMessage());
    }
}
