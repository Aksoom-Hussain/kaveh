<?php

declare(strict_types=1);

namespace Kaveh\Watchers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\KavehManager;
use Kaveh\Support\Redactor;
use Kaveh\Support\Silencer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Telescope-equivalent HTTP request watcher.
 *
 * @see https://github.com/laravel/telescope/blob/5.x/src/Watchers/RequestWatcher.php
 */
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
                $response = $event->response;
                $path = '/'.ltrim($request->path(), '/');

                if ($this->shouldIgnore($path) || $this->shouldIgnoreHttpMethod($request) || $this->shouldIgnoreStatusCode($response)) {
                    return;
                }

                $start = defined('LARAVEL_START')
                    ? LARAVEL_START
                    : $request->server('REQUEST_TIME_FLOAT');
                $duration = $start ? floor((microtime(true) - (float) $start) * 1000) : null;

                $route = $request->route();

                $this->kaveh->record(new EventEnvelope(
                    id: EventEnvelope::generateId(),
                    type: EventType::Request,
                    name: $request->method().' '.$request->path(),
                    timestamp: gmdate('c'),
                    environment: (string) config('kaveh.environment'),
                    hostname: gethostname() ?: 'unknown',
                    traceId: $request->headers->get('X-Request-Id') ?: $request->headers->get('X-Correlation-Id'),
                    user: $this->authenticatedUser(),
                    context: [
                        'ip_address' => $request->ip(),
                        'uri' => str_replace($request->root(), '', $request->fullUrl()) ?: '/',
                        'method' => $request->method(),
                        'controller_action' => $route ? $route->getActionName() : null,
                        'middleware' => array_values($route?->gatherMiddleware() ?? []),
                        'headers' => $this->formatHeaders($request->headers->all()),
                        'payload' => $this->input($request),
                        'session' => $this->sessionVariables($request),
                        'response_status' => $response->getStatusCode(),
                        'response_headers' => $this->formatHeaders($response->headers->all()),
                        'response' => $this->formatResponse($response),
                        'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
                        // Back-compat aliases used by the dashboard helpers.
                        'ip' => $request->ip(),
                        'status' => $response->getStatusCode(),
                        'input' => $this->input($request),
                    ],
                    tags: [
                        'request',
                        'status:'.$response->getStatusCode(),
                        'method:'.strtoupper($request->method()),
                    ],
                    level: $response->getStatusCode() >= 500
                        ? 'error'
                        : ($response->getStatusCode() >= 400 ? 'warning' : 'info'),
                    durationMs: $duration !== null ? (float) $duration : null,
                ));
            });
        });
    }

    /**
     * @param  array<string, list<string|null>>  $headers
     * @return array<string, string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $values) {
            $formatted[(string) $name] = implode(', ', array_map(static fn ($v) => (string) $v, $values));
        }

        return $this->redactor->redactHeaders($formatted);
    }

    /**
     * @return array<string, mixed>|string
     */
    private function input(Request $request): array|string
    {
        if (Str::startsWith(strtolower($request->headers->get('Content-Type') ?? ''), 'text/plain')) {
            return (string) $request->getContent();
        }

        /** @var array<string, mixed> $files */
        $files = $request->files->all();

        array_walk_recursive($files, static function (&$file): void {
            if (is_object($file) && method_exists($file, 'getClientOriginalName')) {
                $file = [
                    'name' => $file->getClientOriginalName(),
                    'size' => method_exists($file, 'isFile') && $file->isFile()
                        ? round($file->getSize() / 1000, 1).'KB'
                        : '0',
                ];
            }
        });

        /** @var array<string, mixed> $input */
        $input = array_replace_recursive($request->input(), $files);

        return $this->hideParameters($input, $this->hiddenRequestParameters());
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionVariables(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        try {
            /** @var array<string, mixed> $session */
            $session = $request->session()->all();

            return $this->hideParameters($session, $this->hiddenRequestParameters());
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Format response like Telescope: JSON body, redirect target, view data, or short labels.
     *
     * @return array<string, mixed>|string
     */
    private function formatResponse(Response $response): array|string
    {
        $content = $response->getContent();

        if (is_string($content) && $content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $this->contentWithinLimits($content)
                    ? $this->hideParameters($decoded, $this->hiddenResponseParameters())
                    : 'Purged By Kaveh';
            }

            if (Str::startsWith(strtolower($response->headers->get('Content-Type') ?? ''), 'text/plain')) {
                return $this->contentWithinLimits($content) ? $content : 'Purged By Kaveh';
            }
        }

        if ($response instanceof RedirectResponse) {
            return 'Redirected to '.$response->getTargetUrl();
        }

        if ($response instanceof IlluminateResponse) {
            $original = $response->getOriginalContent();
            if ($original instanceof View) {
                return [
                    'view' => $original->getPath(),
                    'data' => $this->extractDataFromView($original),
                ];
            }
        }

        if (is_string($content) && $content === '') {
            return 'Empty Response';
        }

        return 'HTML Response';
    }

    private function contentWithinLimits(string $content): bool
    {
        $limitKb = (int) ($this->options()['size_limit'] ?? 64);

        return intdiv(mb_strlen($content), 1000) <= max(1, $limitKb);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractDataFromView(View $view): array
    {
        return collect($view->getData())->map(function ($value) {
            if ($value instanceof Model) {
                return [
                    'class' => $value::class,
                    'key' => $value->getKey(),
                    'attributes' => $this->hideParameters(
                        $value->attributesToArray(),
                        $this->hiddenResponseParameters()
                    ),
                ];
            }

            if (is_object($value)) {
                return [
                    'class' => $value::class,
                    'properties' => json_decode(json_encode($value), true),
                ];
            }

            return json_decode(json_encode($value), true);
        })->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $hidden
     * @return array<string, mixed>
     */
    private function hideParameters(array $data, array $hidden): array
    {
        foreach ($hidden as $parameter) {
            if (Arr::has($data, $parameter)) {
                Arr::set($data, $parameter, '********');
            }
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function hiddenRequestParameters(): array
    {
        return array_values(array_unique(array_merge(
            (array) config('kaveh.redact.keys', []),
            (array) ($this->options()['hidden_request_parameters'] ?? [
                'password',
                'password_confirmation',
                'token',
                '_token',
                'current_password',
            ]),
        )));
    }

    /**
     * @return list<string>
     */
    private function hiddenResponseParameters(): array
    {
        return array_values(array_unique(array_merge(
            (array) config('kaveh.redact.keys', []),
            (array) ($this->options()['hidden_response_parameters'] ?? [
                'password',
                'token',
                'authorization',
            ]),
        )));
    }

    private function shouldIgnoreHttpMethod(Request $request): bool
    {
        $ignored = collect($this->options()['ignore_http_methods'] ?? [])
            ->map(static fn ($method) => strtolower((string) $method))
            ->all();

        return in_array(strtolower($request->method()), $ignored, true);
    }

    private function shouldIgnoreStatusCode(Response $response): bool
    {
        return in_array(
            $response->getStatusCode(),
            (array) ($this->options()['ignore_status_codes'] ?? []),
            true
        );
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

    /**
     * @return array{id?: string|int|null, name?: string|null, email?: string|null}|null
     */
    private function authenticatedUser(): ?array
    {
        try {
            $user = auth()->user();
            if (! $user) {
                return null;
            }

            return [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $options = config('kaveh.watchers.'.self::class, []);

        return is_array($options) ? $options : [];
    }
}
