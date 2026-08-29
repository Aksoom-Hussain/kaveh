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
    private ?string $lastFailure = null;

    public function __construct(
        private readonly ?Client $http = null,
    ) {}

    public function lastFailure(): ?string
    {
        return $this->lastFailure;
    }

    public function send(IngestBatch $batch): bool
    {
        $this->lastFailure = null;

        return (bool) Silencer::run(function () use ($batch): bool {
            $apiKey = (string) config('kaveh.api_key', '');
            $base = rtrim((string) config('kaveh.server_url', ''), '/');

            if ($apiKey === '' || $base === '') {
                $this->lastFailure = 'missing KAVEH_API_KEY or KAVEH_SERVER_URL';

                return false;
            }

            $payload = json_encode($batch->toArray(), JSON_THROW_ON_ERROR);
            $body = function_exists('gzencode') ? gzencode($payload, 6) : $payload;
            $encoding = function_exists('gzencode') ? 'gzip' : 'identity';

            $client = $this->http ?? new Client([
                'timeout' => (int) config('kaveh.timeout', 5),
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
                if (! $ok) {
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
                $this->lastFailure = $e->getMessage();
                Silencer::report($e);

                return false;
            }
        }, false);
    }
}
