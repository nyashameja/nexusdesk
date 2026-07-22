<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm;

use ParagonHostOps\Core\Logger;
use ParagonHostOps\Services\Whm\Exceptions\WhmApiException;
use ParagonHostOps\Services\Whm\Exceptions\WhmAuthenticationException;
use ParagonHostOps\Services\Whm\Exceptions\WhmConnectionException;
use ParagonHostOps\Services\Whm\Exceptions\WhmInvalidResponseException;
use ParagonHostOps\Services\Whm\Exceptions\WhmPermissionException;

/**
 * Reusable WHM API 1 client.
 *
 * Responsibilities:
 *  - Build WHM json-api URLs safely.
 *  - Send the correct "Authorization: whm user:token" header over HTTPS.
 *  - Always include api.version=1.
 *  - Decode JSON, validate HTTP status and WHM metadata.result.
 *  - Return a standardised WhmResponse or throw a controlled exception.
 *  - Retry only transient (network/5xx) failures — never authentication.
 *  - Never expose the token in logs, messages or responses.
 *
 * The credentials live only inside this object; nothing leaves it.
 */
final class WhmApiClient
{
    private string $host;
    private int $port;
    private string $username;
    private string $token;
    private int $apiVersion;
    private int $maxRetries;
    private int $retryBaseDelayMs;

    /**
     * @param array<string, mixed> $config The config/whm.php array.
     */
    public function __construct(
        private WhmTransportInterface $transport,
        array $config,
        private ?Logger $logger = null,
    ) {
        $this->host             = trim((string) ($config['host'] ?? ''));
        $this->port             = (int) ($config['port'] ?? 2087);
        $this->username         = trim((string) ($config['username'] ?? ''));
        $this->token            = (string) ($config['token'] ?? '');
        $this->apiVersion       = (int) ($config['api_version'] ?? 1);
        $this->maxRetries       = (int) ($config['retry']['max_attempts'] ?? 2);
        $this->retryBaseDelayMs = (int) ($config['retry']['base_delay_ms'] ?? 250);
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->username !== '' && $this->token !== '';
    }

    /**
     * Call a WHM API 1 function via GET.
     *
     * @param array<string, scalar> $params
     */
    public function get(string $function, array $params = []): WhmResponse
    {
        return $this->request('GET', $function, $params);
    }

    /**
     * Internal request dispatcher. Currently GET-only; POST support can be
     * added here without changing callers.
     *
     * @param array<string, scalar> $params
     */
    private function request(string $method, string $function, array $params): WhmResponse
    {
        if (!$this->isConfigured()) {
            throw new WhmAuthenticationException('WHM API client is not configured.');
        }

        $url     = $this->buildUrl($function);
        $headers = $this->buildHeaders();
        $query   = array_merge(['api.version' => $this->apiVersion], $params);

        $attempt      = 0;
        $lastResult   = null;

        do {
            $attempt++;
            $result     = $this->transport->get($url, $headers, $query);
            $lastResult = $result;

            // Retry only transient transport failures, never auth/permission.
            if ($this->isTransient($result) && $attempt <= $this->maxRetries) {
                $this->backoff($attempt);
                continue;
            }

            break;
        } while (true);

        return $this->handle($function, $lastResult);
    }

    private function buildUrl(string $function): string
    {
        // Only the function name — never user input — forms the path.
        $safeFunction = preg_replace('/[^a-zA-Z0-9_]/', '', $function) ?? '';

        return sprintf('https://%s:%d/json-api/%s', $this->host, $this->port, $safeFunction);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        return [
            'Authorization' => sprintf('whm %s:%s', $this->username, $this->token),
            'Accept'        => 'application/json',
        ];
    }

    /**
     * @param array{status:int, body:string, error:?string, timedOut:bool, connectFailed:bool} $result
     */
    private function isTransient(array $result): bool
    {
        return $result['timedOut']
            || $result['connectFailed']
            || ($result['status'] >= 500 && $result['status'] <= 599);
    }

    private function backoff(int $attempt): void
    {
        $delayMs = $this->retryBaseDelayMs * (2 ** ($attempt - 1));
        usleep($delayMs * 1000);
    }

    /**
     * @param array{status:int, body:string, error:?string, timedOut:bool, connectFailed:bool} $result
     */
    private function handle(string $function, array $result): WhmResponse
    {
        $status = $result['status'];

        if ($result['connectFailed'] || $result['timedOut'] || $status === 0) {
            $this->log('error', "WHM connection failure calling {$function}", $result['error']);
            throw new WhmConnectionException(
                "Connection to WHM failed for '{$function}': " . ($result['error'] ?? 'unknown transport error')
            );
        }

        if ($status === 401 || $status === 403) {
            $this->log('warning', "WHM authentication/permission failure ({$status}) calling {$function}");
            // 403 with a valid token typically indicates a privilege problem.
            if ($status === 403) {
                throw new WhmPermissionException("WHM denied access to '{$function}' (HTTP 403).");
            }
            throw new WhmAuthenticationException("WHM rejected the credentials (HTTP 401).");
        }

        if ($status < 200 || $status >= 300) {
            $this->log('error', "WHM returned HTTP {$status} calling {$function}");
            throw new WhmApiException("WHM returned HTTP {$status} for '{$function}'.");
        }

        $decoded = json_decode($result['body'], true);

        if (!is_array($decoded)) {
            $this->log('error', "WHM returned non-JSON calling {$function}");
            throw new WhmInvalidResponseException("WHM returned invalid JSON for '{$function}'.");
        }

        $metadata = $this->extractMetadata($decoded);

        if ($metadata === null) {
            throw new WhmInvalidResponseException("WHM response for '{$function}' is missing metadata.");
        }

        $response = new WhmResponse($metadata, $this->extractData($decoded), $decoded);

        if (!$response->isSuccessful()) {
            $reason = $response->reason();
            $this->log('warning', "WHM metadata.result=0 for {$function}: {$reason}");

            // Some reseller permission errors surface here rather than as 403.
            if ($this->looksLikePermissionError($reason)) {
                throw new WhmPermissionException("WHM permission denied for '{$function}': {$reason}");
            }

            throw new WhmApiException("WHM reported failure for '{$function}': {$reason}");
        }

        return $response;
    }

    /**
     * WHM API 1 places metadata either at the top level or under "metadata".
     *
     * @param array<string, mixed> $decoded
     * @return array<string, mixed>|null
     */
    private function extractMetadata(array $decoded): ?array
    {
        if (isset($decoded['metadata']) && is_array($decoded['metadata'])) {
            return $decoded['metadata'];
        }

        if (isset($decoded['result'])) {
            return ['result' => $decoded['result'], 'reason' => $decoded['reason'] ?? ''];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<string, mixed>
     */
    private function extractData(array $decoded): array
    {
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        // Fall back to the whole payload minus the metadata envelope.
        $data = $decoded;
        unset($data['metadata']);
        return $data;
    }

    private function looksLikePermissionError(string $reason): bool
    {
        $needles = ['permission', 'not allowed', 'access denied', 'privilege', 'unauthorized'];
        $reason  = strtolower($reason);

        foreach ($needles as $needle) {
            if (str_contains($reason, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function log(string $level, string $message, ?string $detail = null): void
    {
        if ($this->logger === null) {
            return;
        }

        // The Logger redacts credentials; we also avoid passing any here.
        $context = $detail !== null ? ['detail' => $detail] : [];
        $this->logger->{$level}($message, $context);
    }
}
