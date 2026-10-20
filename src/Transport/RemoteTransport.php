<?php

declare(strict_types=1);

namespace Kaveh\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Kaveh\Contracts\IngestBatch;
use Kaveh\Support\Silencer;
use Throwable;

final class RemoteTransport
{
    private static int $consecutiveFailures = 0;

    private static int $circuitOpenUntil = 0;

    private ?string $lastFailure = null;

    public function __construct(
        private readonly ?Client $http = null,
    ) {}

    public static function resetCircuitBreaker(): void
    {
        self::$consecutiveFailures = 0;
        self::$circuitOpenUntil = 0;
    }

    public static function isCircuitOpen(): bool
    {
        return time() < self::$circuitOpenUntil;
    }

    public static function circuitOpenUntil(): int
    {
        return self::$circuitOpenUntil;
    }

    public static function consecutiveFailures(): int
    {
        return self::$consecutiveFailures;
    }

    public function lastFailure(): ?string
    {
        return $this->lastFailure;
    }

    public function send(IngestBatch $batch): bool
    {
        $this->lastFailure = null;

        $cbEnabled = (bool) config('kaveh.circuit_breaker.enabled', true);
        $threshold = (int) config('kaveh.circuit_breaker.threshold', 3);
        $cooldown = (int) config('kaveh.circuit_breaker.cooldown', 60);

        if ($cbEnabled && $threshold > 0 && time() < self::$circuitOpenUntil) {
            $this->lastFailure = 'Circuit breaker open (cooling down for '.(self::$circuitOpenUntil - time()).'s after '.self::$consecutiveFailures.' consecutive failures)';

            return false;
        }

        return (bool) Silencer::run(function () use ($batch, $cbEnabled, $threshold, $cooldown): bool {
            $apiKey = (string) config('kaveh.api_key', '');
            $base = rtrim((string) config('kaveh.server_url', ''), '/');

            if ($apiKey === '' || $base === '') {
                $this->lastFailure = 'missing KAVEH_API_KEY or KAVEH_SERVER_URL';

                return false;
            }

            $payload = json_encode($batch->toArray(), JSON_THROW_ON_ERROR);
            $body = function_exists('gzencode') ? gzencode($payload, 6) : $payload;
            $encoding = function_exists('gzencode') ? 'gzip' : 'identity';

            $timeout = (float) config('kaveh.timeout', 3);
            $connectTimeout = (float) config('kaveh.connect_timeout', min(1.0, $timeout));

            $client = $this->http ?? new Client([
                'timeout' => $timeout,
                'connect_timeout' => $connectTimeout,
                'http_errors' => false,
            ]);

            try {
                $response = $client->post($base.'/api/v1/ingest', [
                    'headers' => [
                        'Authorization' => 'Bearer '.$apiKey,
                        'Content-Type' => 'application/json',
                        'Content-Encoding' => $encoding,
                        'Accept' => 'application/json',
                        'User-Agent' => 'kaveh-client/1.0',
                    ],
                    'body' => $body,
                ]);

                $ok = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
                if ($ok) {
                    self::$consecutiveFailures = 0;
                    self::$circuitOpenUntil = 0;
                } else {
                    $this->recordFailure($cbEnabled, $threshold, $cooldown);
                    $this->lastFailure = 'HTTP '.$response->getStatusCode().': '.substr((string) $response->getBody(), 0, 200);
                    if (config('kaveh.debug')) {
                        try {
                            Log::debug('Kaveh remote ingest failed', [
                                'status' => $response->getStatusCode(),
                                'body' => (string) $response->getBody(),
                            ]);
                        } catch (Throwable) {
                            //
                        }
                    }
                }

                return $ok;
            } catch (GuzzleException|Throwable $e) {
                $this->recordFailure($cbEnabled, $threshold, $cooldown);
                $this->lastFailure = $e->getMessage();
                Silencer::report($e);

                return false;
            }
        }, false);
    }

    private function recordFailure(bool $cbEnabled, int $threshold, int $cooldown): void
    {
        if (! $cbEnabled || $threshold <= 0) {
            return;
        }

        self::$consecutiveFailures++;
        if (self::$consecutiveFailures >= $threshold) {
            self::$circuitOpenUntil = time() + $cooldown;
        }
    }
}
