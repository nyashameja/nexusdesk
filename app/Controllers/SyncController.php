<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Repositories\CapabilityRepository;
use ParagonHostOps\Repositories\SyncRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Whm\CapabilityChecker;
use ParagonHostOps\Services\Whm\SyncService;
use ParagonHostOps\Services\Whm\WhmApiClient;

/**
 * Synchronisation dashboard: run a read-only sync, view history and the
 * recorded API capabilities.
 */
final class SyncController extends Controller
{
    public function __construct(
        private SyncService $sync,
        private SyncRepository $runs,
        private CapabilityRepository $capabilities,
        private WhmApiClient $whm,
        private AuditLogger $audit,
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        return $this->view('sync.index', [
            'title'        => 'Synchronisation',
            'history'      => $this->runs->history(10),
            'capabilities' => $this->decorateCapabilities($this->capabilities->all()),
            'configured'   => $this->whm->isConfigured(),
        ]);
    }

    public function history(Request $request, array $params): Response
    {
        $runs = $this->runs->history(50);
        $errors = [];
        foreach ($runs as $run) {
            if ((int) $run['records_failed'] > 0 || $run['status'] === 'failed') {
                $errors[(int) $run['id']] = $this->runs->errorsFor((int) $run['id']);
            }
        }

        return $this->view('sync.history', [
            'title'   => 'Synchronisation History',
            'history' => $runs,
            'errors'  => $errors,
        ]);
    }

    /**
     * Run a full synchronisation (read-only). CSRF + permission protected.
     */
    public function run(Request $request, array $params): Response
    {
        $this->audit->record('sync.started', 'Full WHM synchronisation started.');
        $result = $this->sync->syncAll();

        if ($result->locked) {
            $this->session()->flash('warning', 'A synchronisation is already running. Please wait for it to finish.');
        } elseif ($result->status() === 'completed') {
            $this->audit->record('sync.completed', 'Synchronisation completed: ' . $result->summary());
            $this->session()->flash('success', 'Synchronisation completed. ' . $result->summary());
        } elseif ($result->status() === 'partial') {
            $this->audit->record('sync.completed', 'Synchronisation partial: ' . $result->summary());
            $this->session()->flash('warning', 'Synchronisation partially completed. ' . $result->summary());
        } else {
            $this->audit->record('sync.failed', 'Synchronisation failed: ' . $result->summary());
            $this->session()->flash('error', 'Synchronisation failed. ' . $result->summary());
        }

        return $this->redirect('/sync');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function decorateCapabilities(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['label'] = CapabilityChecker::label((string) $row['status']);
        }
        return $rows;
    }
}
