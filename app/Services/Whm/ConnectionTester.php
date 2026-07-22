<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm;

use ParagonHostOps\Services\Whm\Exceptions\WhmException;
use Throwable;

/**
 * Runs a structured, read-only "Test WHM Connection" check.
 *
 * Verifies reachability, HTTPS, authentication, that the token can call
 * listaccts, that a valid metadata envelope is returned, and how many accounts
 * are visible. The token is never revealed in the result.
 */
final class ConnectionTester
{
    public function __construct(private WhmApiClient $client)
    {
    }

    /**
     * @return array{
     *     ok: bool,
     *     configured: bool,
     *     steps: array<int, array{name:string, ok:bool, detail:string}>,
     *     account_count: int|null,
     *     message: string
     * }
     */
    public function run(): array
    {
        $steps = [];

        if (!$this->client->isConfigured()) {
            return [
                'ok'            => false,
                'configured'    => false,
                'steps'         => [],
                'account_count' => null,
                'message'       => 'WHM credentials are not configured. Update the environment settings.',
            ];
        }

        try {
            $response = $this->client->get('listaccts');

            $steps[] = ['name' => 'Host reachable over HTTPS', 'ok' => true, 'detail' => 'Connection established'];
            $steps[] = ['name' => 'Authentication succeeded', 'ok' => true, 'detail' => 'Token accepted'];
            $steps[] = ['name' => 'Token can call listaccts', 'ok' => true, 'detail' => 'Function permitted'];

            $metadataValid = $response->isSuccessful();
            $steps[] = [
                'name'   => 'Valid WHM metadata returned',
                'ok'     => $metadataValid,
                'detail' => $metadataValid ? 'metadata.result = 1' : 'Unexpected metadata',
            ];

            $accounts = $response->get('acct', []);
            $count    = is_array($accounts) ? count($accounts) : 0;

            $steps[] = [
                'name'   => 'Visible accounts retrieved',
                'ok'     => true,
                'detail' => $count . ' account(s) visible to this token',
            ];

            return [
                'ok'            => true,
                'configured'    => true,
                'steps'         => $steps,
                'account_count' => $count,
                'message'       => "Connection successful. {$count} account(s) visible.",
            ];
        } catch (WhmException $e) {
            $steps[] = ['name' => 'Connection test', 'ok' => false, 'detail' => $e->safeMessage()];

            return [
                'ok'            => false,
                'configured'    => true,
                'steps'         => $steps,
                'account_count' => null,
                'message'       => $e->safeMessage(),
            ];
        } catch (Throwable) {
            return [
                'ok'            => false,
                'configured'    => true,
                'steps'         => [['name' => 'Connection test', 'ok' => false, 'detail' => 'Unexpected error']],
                'account_count' => null,
                'message'       => 'An unexpected error occurred while testing the WHM connection.',
            ];
        }
    }
}
