# Server guide

Install Kaveh on a Laravel app that should **receive** telemetry and host the dashboard.

## Install

```bash
composer require aksoom-hussain/kaveh
php artisan kaveh:install --role=server --non-interactive
php artisan migrate
```

Open `https://your-host/kaveh/login`, register the first user, then:

1. **Projects** → create a project (e.g. `Emailwish`)
2. **Issue API key** → copy the `kv_…` value once
3. Give that key to each client app

## Surfaces

| Feature | Path |
|---------|------|
| Dashboard | `/kaveh` (configurable via `KAVEH_PATH`) |
| Ingest | `POST /api/v1/ingest` |
| Pulse metrics JSON | `GET /kaveh/api/metrics/pulse` |
| Event metrics JSON | `GET /kaveh/api/metrics/events` |

Top bar controls:

- **Project** filter (multi-tenant)
- **Time range** pills: `1h` · `6h` · `24h` · `7d`

## Authorization

Published stub:

```php
// app/Providers/KavehServiceProvider.php
protected function authorization(): void
{
    Gate::define('viewKaveh', function ($user = null) {
        return app()->environment('local');
    });
}
```

Change this before production. Example:

```php
Gate::define('viewKaveh', fn ($user) => $user?->isAdmin() === true);
```

## Alerts

Dashboard → **Alerts** → create a rule:

- Metric: exception rate / failed jobs / custom event name
- Threshold + window + cooldown
- Channel: webhook URL or email

Schedule evaluation:

```php
Schedule::command('kaveh:evaluate-alerts')->everyMinute();
Schedule::command('kaveh:prune-events')->daily();
```

## Pulse charts (optional)

```bash
composer require laravel/pulse
php artisan pulse:install
php artisan migrate
```

Keep `php artisan pulse:check` (or the Pulse service) running. Kaveh Overview reads `pulse_aggregates` and never requires Pulse’s own UI.

## Recommended production layout

```env
KAVEH_ENABLED=true
KAVEH_ROLE=server
KAVEH_SERVER_ENABLED=true
KAVEH_PATH=kaveh
# Do NOT enable client watchers on a busy dedicated monitor if you can avoid it
```

Run a queue worker on the server for alert/embed jobs if those features are enabled.

## Troubleshooting “no data”

| Symptom | Check |
|---------|-------|
| Project exists, Events empty | Client not installed / wrong API key / no queue worker |
| Only server hostname in Events | Client watchers off, or still looking at server self-traffic |
| Ingest `401` | Key revoked or typo; issue a new key under Projects |
| Ingest `202` but UI empty | Wrong project selected in top bar; confirm `project_id` |
| Graphs empty | Pulse not installed / `pulse_aggregates` missing — event charts still work |

Quick ingest probe (from any machine):

```bash
curl -X POST "https://your-host/api/v1/ingest" \
  -H "Authorization: Bearer kv_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"events":[{"type":"custom","name":"server.probe","timestamp":"2026-08-08T12:00:00Z","environment":"production","hostname":"probe","level":"info","context":{"ok":true}}]}'
```

Expect HTTP `202`. Refresh Events for that project.
