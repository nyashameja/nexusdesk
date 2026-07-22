<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm;

use ParagonHostOps\Services\Whm\Exceptions\WhmAuthenticationException;
use ParagonHostOps\Services\Whm\Exceptions\WhmConnectionException;
use ParagonHostOps\Services\Whm\Exceptions\WhmException;
use ParagonHostOps\Services\Whm\Exceptions\WhmPermissionException;
use Throwable;

/**
 * Probes which read-only WHM functions the configured token can actually call
 * and records a status for each. Nothing destructive is ever invoked.
 */
final class CapabilityChecker
{
    public const AVAILABLE         = 'available';
    public const PERMISSION_DENIED = 'permission_denied';
    public const UNSUPPORTED       = 'unsupported';
    public const SERVER_ERROR      = 'server_error';
    public const NOT_TESTED        = 'not_tested';
    public const AUTH_FAILED       = 'auth_failed';

    /**
     * Functions that are safe to probe with no (or trivial) parameters.
     *
     * accountsummary is intentionally excluded from automatic probing because
     * it requires a specific account; it is checked implicitly during sync.
     *
     * @var array<int, string>
     */
    private const PROBE_FUNCTIONS = [
        'version',
        'listaccts',
        'listpkgs',
        'get_server_information',
        'servicestatus',
        'showbw',
        'fetch_ssl_vhosts',
        'listsuspended',
    ];

    public function __construct(private WhmApiClient $client)
    {
    }

    /**
     * @return array<string, array{status:string, message:string}>
     */
    public function probeAll(): array
    {
        $results = [];

        foreach (self::PROBE_FUNCTIONS as $function) {
            $results[$function] = $this->probe($function);
        }

        return $results;
    }

    /**
     * @return array{status:string, message:string}
     */
    public function probe(string $function): array
    {
        try {
            $this->client->get($function);
            return ['status' => self::AVAILABLE, 'message' => 'Available'];
        } catch (WhmPermissionException) {
            return ['status' => self::PERMISSION_DENIED, 'message' => 'Permission denied for the current token'];
        } catch (WhmAuthenticationException) {
            return ['status' => self::AUTH_FAILED, 'message' => 'Authentication failed'];
        } catch (WhmConnectionException) {
            return ['status' => self::SERVER_ERROR, 'message' => 'Server unreachable'];
        } catch (WhmException $e) {
            // Unknown-function style errors map to "unsupported".
            $reason = strtolower($e->getMessage());
            if (str_contains($reason, 'unknown') || str_contains($reason, 'not exist') || str_contains($reason, 'invalid function')) {
                return ['status' => self::UNSUPPORTED, 'message' => 'Not supported on this server'];
            }
            return ['status' => self::SERVER_ERROR, 'message' => 'WHM reported an error'];
        } catch (Throwable) {
            return ['status' => self::SERVER_ERROR, 'message' => 'Unexpected error while probing'];
        }
    }

    /**
     * Human-friendly label for a status code.
     */
    public static function label(string $status): string
    {
        return match ($status) {
            self::AVAILABLE         => 'Available',
            self::PERMISSION_DENIED => 'Permission denied',
            self::UNSUPPORTED       => 'Unsupported',
            self::SERVER_ERROR      => 'Server error',
            self::AUTH_FAILED       => 'Authentication failed',
            default                 => 'Not yet tested',
        };
    }
}
