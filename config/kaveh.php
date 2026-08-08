<?php

declare(strict_types=1);

use Kaveh\Http\Middleware\Authenticate;
use Kaveh\Http\Middleware\Authorize;
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
    | server — Pulse-like dashboard + ingest API + alerts + event RAG
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
    'timeout' => (int) env('KAVEH_TIMEOUT', 5),

    'batch' => [
        'size' => (int) env('KAVEH_BATCH_SIZE', 50),
        'flush_interval_seconds' => (int) env('KAVEH_FLUSH_INTERVAL', 10),
        'use_queue' => (bool) env('KAVEH_USE_QUEUE', true),
        'queue' => env('KAVEH_QUEUE', 'default'),
    ],

    'sample_rate' => (float) env('KAVEH_SAMPLE_RATE', 1.0),
    'max_context_bytes' => (int) env('KAVEH_MAX_CONTEXT_BYTES', 32768),
    'max_string_length' => (int) env('KAVEH_MAX_STRING_LENGTH', 2000),

    'local' => [
        'connection' => env('KAVEH_DB_CONNECTION'),
        'table' => 'kaveh_events',
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
        ],

        QueryWatcher::class => [
            'enabled' => env('KAVEH_WATCH_QUERIES', true),
            'slow' => env('KAVEH_SLOW_QUERY_MS', 100),
        ],

        JobWatcher::class => env('KAVEH_WATCH_JOBS', true),

        LogWatcher::class => [
            'enabled' => env('KAVEH_WATCH_LOGS', false),
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
    | Server (dashboard / ingest / RAG / alerts)
    |--------------------------------------------------------------------------
    */

    'server' => [
        'enabled' => env('KAVEH_SERVER_ENABLED', false),
        /** @deprecated use top-level path */
        'path_prefix' => env('KAVEH_PATH', env('KAVEH_PATH_PREFIX', 'kaveh')),
        'user_model' => env('KAVEH_USER_MODEL', \App\Models\User::class),
        'registers_routes' => env('KAVEH_REGISTER_ROUTES', true),
    ],

    'openai_api_key' => env('KAVEH_OPENAI_API_KEY', env('OPENAI_API_KEY')),
    'openai_base_url' => env('KAVEH_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'embedding_model' => env('KAVEH_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'chat_model' => env('KAVEH_CHAT_MODEL', 'gpt-4o-mini'),
];
