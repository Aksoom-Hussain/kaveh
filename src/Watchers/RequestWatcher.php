<?php

declare(strict_types=1);

namespace Kaveh\Watchers;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\KavehManager;
use Kaveh\Support\Silencer;
use Kaveh\Support\Redactor;

final class RequestWatcher
{
    public function __construct(
        private readonly KavehManager $kaveh,
        private readonly Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $event): void {
            Silencer::run(function () use ($event): void {
                $request = $event->request;
                $path = '/'.ltrim($request->path(), '/');

                if ($this->shouldIgnore($path)) {
                    return;
                }

                $response = $event->response;
                $start = defined('LARAVEL_START') ? LARAVEL_START : null;
                $duration = $start ? (microtime(true) - $start) * 1000 : null;

                $headers = [];
                foreach ($request->headers->all() as $name => $values) {
                    $headers[$name] = implode(', ', $values);
                }

                $this->kaveh->record(new EventEnvelope(
                    id: EventEnvelope::generateId(),
                    type: EventType::Request,
                    name: $request->method().' '.$request->path(),
                    timestamp: gmdate('c'),
                    environment: (string) config('kaveh.environment'),
                    hostname: gethostname() ?: 'unknown',
                    traceId: $request->headers->get('X-Request-Id'),
                    context: $this->redactor->redact([
                        'method' => $request->method(),
                        'uri' => $request->getRequestUri(),
                        'status' => $response->getStatusCode(),
                        'ip' => $request->ip(),
                        'headers' => $this->redactor->redactHeaders($headers),
                        'input' => $request->except(['password', 'password_confirmation', 'token']),
                    ]),
                    tags: ['request', 'status:'.$response->getStatusCode()],
                    level: $response->getStatusCode() >= 500 ? 'error' : 'info',
                    durationMs: $duration,
                ));
            });
        });
    }

    private function shouldIgnore(string $path): bool
    {
        $prefix = '/'.trim((string) config('kaveh.path', 'kaveh'), '/');
        $defaults = [
            $prefix,
            $prefix.'/*',
            '/pulse',
            '/pulse/*',
            '/telescope',
            '/telescope/*',
            '/api/v1/ingest',
            '/livewire/*',
            '/_boost/*',
            '/up',
        ];

        $patterns = array_merge($defaults, (array) config('kaveh.ignore_paths', []));

        foreach ($patterns as $pattern) {
            $pattern = '/'.ltrim((string) $pattern, '/');
            if (Str::is($pattern, $path) || Str::is(rtrim($pattern, '/*'), $path)) {
                return true;
            }
        }

        return false;
    }
}
