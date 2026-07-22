<?php

declare(strict_types=1);

namespace ParagonHostOps\Controllers;

use ParagonHostOps\Core\Controller;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Whm\CapabilityChecker;
use ParagonHostOps\Services\Whm\ConnectionTester;
use ParagonHostOps\Services\Whm\WhmApiClient;

/**
 * Application settings, including the read-only WHM connection test and the
 * capability checker. The token itself is never displayed.
 */
final class SettingsController extends Controller
{
    public function __construct(
        private WhmApiClient $whm,
        private ConnectionTester $tester,
        private CapabilityChecker $capabilities,
        private AuditLogger $audit,
    ) {
    }

    public function whm(Request $request, array $params): Response
    {
        return $this->view('settings.whm', [
            'title'         => 'WHM Settings',
            'configured'    => $this->whm->isConfigured(),
            'host'          => $this->maskedHost(),
            'username'      => (string) config('whm.username'),
            'tokenPresent'  => config('whm.token') !== '' && config('whm.token') !== null,
            'mockMode'      => (bool) config('whm.mock_mode'),
        ]);
    }

    /**
     * AJAX endpoint: run the connection test and return a JSON summary.
     */
    public function testConnection(Request $request, array $params): Response
    {
        $result = $this->tester->run();
        $this->audit->record('whm.connection_tested', 'WHM connection test executed: ' . ($result['ok'] ? 'success' : 'failure'));

        return $this->json($result);
    }

    /**
     * AJAX endpoint: probe token capabilities.
     */
    public function capabilities(Request $request, array $params): Response
    {
        $results = $this->capabilities->probeAll();

        $formatted = [];
        foreach ($results as $function => $info) {
            $formatted[] = [
                'function' => $function,
                'status'   => $info['status'],
                'label'    => CapabilityChecker::label($info['status']),
                'message'  => $info['message'],
            ];
        }

        return $this->json(['capabilities' => $formatted]);
    }

    /**
     * Show only the host (never the token) with light masking for the UI.
     */
    private function maskedHost(): string
    {
        $host = (string) config('whm.host');
        return $host === '' ? '(not configured)' : $host;
    }
}
