# Client guide

Install Kaveh on any Laravel app that should **emit** telemetry.

## Install

```bash
composer require aksoom-hussain/kaveh
php artisan kaveh:install --role=client --mode=remote \
  --server-url=https://kaveh.example.com \
  --api-key=kv_xxxxxxxx \
  --non-interactive
```

### Resulting `.env`

```env
KAVEH_ENABLED=true
KAVEH_ROLE=client
KAVEH_MODE=remote
KAVEH_SERVER_URL=https://kaveh.example.com
KAVEH_API_KEY=kv_xxxxxxxx
KAVEH_USE_QUEUE=true
KAVEH_FAIL_SILENTLY=true
```

`KAVEH_FAIL_SILENTLY=true` (default) means Kaveh never takes down your app if the server is unreachable.

## Modes

| Mode | Behavior |
|------|----------|
| `remote` | Buffer in memory / queue → POST batches to the server (recommended in production) |
| `local` | Persist to local Kaveh tables on this app |
| `both` | Local store **and** remote ship |

## Watchers

Configured in `config/kaveh.php` / env:

```env
KAVEH_WATCH_EXCEPTIONS=true
KAVEH_WATCH_REQUESTS=true
KAVEH_WATCH_QUERIES=true
KAVEH_SLOW_QUERY_MS=100
KAVEH_WATCH_JOBS=true
KAVEH_WATCH_LOGS=false
```

Ignore extra paths (comma-separated):

```env
KAVEH_IGNORE_PATHS=/health,/horizon/*,/nova/*
```

## Queue worker (required for remote)

```bash
php artisan queue:work --queue=default
```

Or Horizon. Without a worker, events stay buffered and may flush late (or only on shutdown).

Force flush:

```bash
php artisan kaveh:flush
```

## Server stats worker (Pulse-like)

Ship CPU / memory / disk gauges from the **app host** (not the Kaveh monitor):

```bash
php artisan kaveh:check
```

Run under supervisord / systemd (same idea as `pulse:check`):

```ini
[program:kaveh-check]
command=php /var/www/app/artisan kaveh:check
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/kaveh-check.log
```

Or once per minute via cron/scheduler:

```bash
php artisan kaveh:check --once
```

```env
KAVEH_CHECK_ENABLED=true
KAVEH_CHECK_INTERVAL=15
KAVEH_CHECK_DISKS=/,/var
```

Stats appear on the Kaveh Overview → **App hosts** accordion for the project.

## Custom events

```php
use Kaveh\Kaveh;
// or: use Kaveh\Facades\Kaveh;

Kaveh::track(
    name: 'shopify.webhook.failed',
    context: [
        'topic' => $topic,
        'shop' => $shopDomain,
        'status' => $response->status(),
    ],
    tags: ['shopify', 'webhook'],
    level: 'error',
);
```

See [custom-events.md](custom-events.md) for more snippets.

## Verify the client

1. Hit a route or run `Kaveh::track('client.ping', ['ok' => true])` in tinker.
2. Run `php artisan queue:work` (one job is enough).
3. On the server dashboard, confirm a new event with **this app’s hostname**.

```bash
php artisan tinker --execute="Kaveh\Kaveh::track('client.ping', ['ok' => true]); Kaveh\Kaveh::flush();"
```

## Local-only client (no server)

```bash
php artisan kaveh:install --role=client --mode=local --non-interactive
php artisan migrate
```

Useful for package development; production should use `remote`.
