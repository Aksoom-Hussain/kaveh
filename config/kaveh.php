<?php

use Kaveh\Http\Middleware\Authenticate;
use Kaveh\Http\Middleware\Authorize;
use Kaveh\Http\Middleware\ShareDashboardFilters;
use Kaveh\Support\Redactor;
use Kaveh\Transport\EventBuffer;
use Kaveh\Watchers\ExceptionWatcher;
use Kaveh\Watchers\JobWatcher;
use Kaveh\Watchers\LogWatcher;
use Kaveh\Watchers\QueryWatcher;
use Kaveh\Watchers\RequestWatcher;

return [

    /*
    |--------------------------------------------------------------------------
    | Kaveh Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Kaveh will be accessible from. If null,
    | Kaveh resides under the same domain as the application.
    |
    */

    'domain' => env('KAVEH_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Kaveh Path
    |--------------------------------------------------------------------------
    |
    | URI path for the Kaveh dashboard (same idea as TELESCOPE_PATH / PULSE_PATH).
    |
    */

    'path' => env('KAVEH_PATH', env('KAVEH_PATH_PREFIX', 'kaveh')),

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    */

    'enabled' => env('KAVEH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Fail silently
    |--------------------------------------------------------------------------
    |
    | When true (default), client watchers / track() / flush never throw into
    | the host app. Set KAVEH_DEBUG=true to Log::debug swallowed errors.
    |
    */

    'fail_silently' => env('KAVEH_FAIL_SILENTLY', true),

    'debug' => env('KAVEH_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Role: client | server | both
    |--------------------------------------------------------------------------
    |
    | client — Telescope-like watchers + track() (local and/or remote ship)
    | server — Pulse-like dashboard + ingest API + alerts
    | both   — double-edged sword on one app
    |
    */

    'role' => env('KAVEH_ROLE', 'client'),

    /*
    |--------------------------------------------------------------------------
    | Client storage mode: local | remote | both
    |--------------------------------------------------------------------------
    */

    'mode' => env('KAVEH_MODE', 'remote'),

    'server_url' => env('KAVEH_SERVER_URL', 'http://localhost:8080'),
    'api_key' => env('KAVEH_API_KEY', ''),
    'timeout' => (int) env('KAVEH_TIMEOUT', 3),
    'connect_timeout' => (float) env('KAVEH_CONNECT_TIMEOUT', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | When the remote ingest server fails N consecutive times, trip the
    | circuit open for `cooldown` seconds to avoid repeatedly stalling
    | workers with doomed network attempts.
    |
    */
    'circuit_breaker' => [
        'enabled' => filter_var(env('KAVEH_CIRCUIT_BREAKER_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'threshold' => (int) env('KAVEH_CIRCUIT_BREAKER_THRESHOLD', 3),
        'cooldown' => (int) env('KAVEH_CIRCUIT_BREAKER_COOLDOWN', 60),
    ],

    'batch' => [
        'size' => (int) env('KAVEH_BATCH_SIZE', 50),
        'use_queue' => (bool) env('KAVEH_USE_QUEUE', true),
        'queue' => env('KAVEH_QUEUE', 'default'),
        // Ceiling on events held in memory awaiting a successful ship, and the
        // cooldown after a failed send before the size-threshold flush retries.
        'max_pending' => (int) env('KAVEH_MAX_PENDING', EventBuffer::MAX_PENDING_EVENTS),
        'retry_cooldown' => (int) env('KAVEH_RETRY_COOLDOWN', EventBuffer::RETRY_COOLDOWN_SECONDS),
    ],

    'sample_rate' => (float) env('KAVEH_SAMPLE_RATE', 1.0),
    'max_context_bytes' => (int) env('KAVEH_MAX_CONTEXT_BYTES', 65536),
    'max_string_length' => (int) env('KAVEH_MAX_STRING_LENGTH', 2000),

    /*
    |--------------------------------------------------------------------------
    | System check worker (Pulse-like)
    |--------------------------------------------------------------------------
    |
    | Run `php artisan kaveh:check` under supervisord / systemd on each app
    | host to ship CPU, memory, and disk gauges to the Kaveh server.
    |
    */

    'check' => [
        'enabled' => env('KAVEH_CHECK_ENABLED', true),
        'interval' => (int) env('KAVEH_CHECK_INTERVAL', 15),
        'disks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('KAVEH_CHECK_DISKS', '/')),
        ))),
    ],

    'local' => [
        'connection' => env('KAVEH_DB_CONNECTION'),
        'table' => 'kaveh_client_events',
        'retention_hours' => (int) env('KAVEH_LOCAL_RETENTION_HOURS', 48),
    ],

    'redact' => [
        'keys' => [
            'password', 'password_confirmation', 'secret', 'token', 'api_key',
            'authorization', 'cookie', 'credit_card', 'card_number', 'cvv', 'ssn',
        ],
        'headers' => [
            'authorization', 'cookie', 'x-api-key', 'x-csrf-token',
        ],
        // Substring patterns matched against *any* key, at any depth.
        'key_patterns' => Redactor::SECRET_PATTERNS,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Assigned to every Kaveh dashboard route (same pattern as Telescope/Pulse).
    |
    */

    'middleware' => [
        'web',
        Authenticate::class,
        Authorize::class,
        ShareDashboardFilters::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Watchers (Telescope-style)
    |--------------------------------------------------------------------------
    |
    | Each watcher gathers application data. Toggle with env or set options.
    |
    */

    'watchers' => [
        ExceptionWatcher::class => env('KAVEH_WATCH_EXCEPTIONS', true),

        RequestWatcher::class => [
            'enabled' => env('KAVEH_WATCH_REQUESTS', true),
            // Telescope-compatible size limit for JSON / text response bodies (KB).
            'size_limit' => (int) env('KAVEH_REQUEST_SIZE_LIMIT', 64),
            // Session keys to include in captured context. Empty = no session
            // data leaves the app; opt in per key, never wholesale.
            'session_keys' => [],
            'ignore_http_methods' => [],
            'ignore_status_codes' => [],
            'hidden_request_parameters' => [
                'password',
                'password_confirmation',
                'token',
                '_token',
                'current_password',
            ],
            'hidden_response_parameters' => [
                'password',
                'token',
                'authorization',
            ],
        ],

        QueryWatcher::class => [
            'enabled' => env('KAVEH_WATCH_QUERIES', true),
            'slow' => env('KAVEH_SLOW_QUERY_MS', 100),
        ],

        JobWatcher::class => env('KAVEH_WATCH_JOBS', true),

        LogWatcher::class => [
            'enabled' => env('KAVEH_WATCH_LOGS', true),
        ],
    ],

    'ignore_paths' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('KAVEH_IGNORE_PATHS', '')),
    ))),

    'slow_query_ms' => (float) env('KAVEH_SLOW_QUERY_MS', 100),
    'environment' => env('KAVEH_ENVIRONMENT', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Server (dashboard / ingest / alerts)
    |--------------------------------------------------------------------------
    */

    'server' => [
        'enabled' => env('KAVEH_SERVER_ENABLED', false),
        /** @deprecated use top-level path */
        'path_prefix' => env('KAVEH_PATH', env('KAVEH_PATH_PREFIX', 'kaveh')),
        'user_model' => env('KAVEH_USER_MODEL', \App\Models\User::class),
        'registers_routes' => env('KAVEH_REGISTER_ROUTES', true),
        // Hard ceiling on a gzip-encoded ingest body after decompression (bomb guard).
        'max_decompressed_bytes' => (int) env('KAVEH_MAX_DECOMPRESSED_BYTES', 16 * 1024 * 1024),
        // Ingest POSTs per minute, keyed by Authorization header (falls back to IP).
        'ingest_rate_limit' => (int) env('KAVEH_INGEST_RATE_LIMIT', 600),
    ],

];
