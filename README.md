# Kaveh

**Self-hosted Laravel monitoring** — one Composer package that can run as a **client**, a **server**, or **both**.

Inspired by [Laravel Telescope](https://github.com/laravel/telescope) and [Laravel Pulse](https://github.com/laravel/pulse), with a key difference: production apps can **ship telemetry off the app disk** to a separate Kaveh host instead of storing everything locally.

| | |
|---|---|
| Packagist | https://packagist.org/packages/aksoom-hussain/kaveh |
| Source | https://github.com/Aksoom-Hussain/kaveh |
| License | MIT |

```bash
composer require aksoom-hussain/kaveh
php artisan kaveh:install
```

---

## Why Kaveh?

| Problem | What Kaveh does |
|---------|-----------------|
| Telescope fills production disks | Remote mode batches events to a central server |
| You need request / exception / job visibility | Built-in watchers (Telescope-style) |
| You want Pulse-like graphs | Dashboard charts + optional `laravel/pulse` metrics API |
| Multiple apps, one ops dashboard | Projects + API keys (multi-tenant ingest) |
| “What failed for checkout today?” | Custom `Kaveh::track()` events + optional event RAG |

---

## Use cases (pick one)

### 1) Production app → central Kaveh server *(recommended)*

**Who:** teams with one or more Laravel apps who want a shared monitoring host.

```
┌─────────────────────┐         HTTPS batch          ┌──────────────────────┐
│  Your Laravel app   │  ─────────────────────────►  │  Kaveh server        │
│  role=client        │   POST /api/v1/ingest        │  role=server         │
│  mode=remote        │   Authorization: Bearer kv_  │  /kaveh dashboard    │
└─────────────────────┘                              └──────────────────────┘
```

**Server (once):**

```bash
composer require aksoom-hussain/kaveh
php artisan kaveh:install --role=server --non-interactive
php artisan migrate
# Open /kaveh → register → Projects → Issue API key
```

**Client (each app):**

```bash
composer require aksoom-hussain/kaveh
php artisan kaveh:install --role=client --mode=remote \
  --server-url=https://kaveh.yourcompany.com \
  --api-key=kv_xxxxxxxx \
  --non-interactive

# Keep a worker running so batches flush off-request
php artisan queue:work
```

```env
KAVEH_ENABLED=true
KAVEH_ROLE=client
KAVEH_MODE=remote
KAVEH_SERVER_URL=https://kaveh.yourcompany.com
KAVEH_API_KEY=kv_xxxxxxxx
KAVEH_USE_QUEUE=true
```

Then open the server dashboard → select the **project** → time range `1h / 6h / 24h / 7d` → browse requests, exceptions, jobs, slow queries.

---

### 2) Local / staging only (Telescope replacement on one box)

**Who:** developers who want Telescope-like insight without a second host.

```bash
php artisan kaveh:install --role=both --mode=local --non-interactive
php artisan migrate
php artisan serve
# Visit /kaveh/login
```

Events stay in the same app database. Good for staging; for production prefer **use case 1**.

---

### 3) Same app as client + server

**Who:** small installs that want watchers and dashboard on one Laravel app.

```bash
php artisan kaveh:install --role=both --mode=both \
  --server-url="${APP_URL}" --non-interactive
```

```env
KAVEH_ROLE=both
KAVEH_MODE=both
KAVEH_SERVER_ENABLED=true
KAVEH_SERVER_URL="${APP_URL}"
KAVEH_API_KEY=kv_xxxxxxxx   # from Projects after first login
KAVEH_USE_QUEUE=true        # important — avoids request/ingest deadlock
```

Always run `php artisan queue:work`. Prefer `role=server` on the monitor host and `role=client` on production apps if traffic is high (avoids the monitor watching itself).

---

### 4) Custom business events (beyond HTTP / jobs)

Track domain failures that watchers will not see automatically:

```php
use Kaveh\Kaveh;

public function checkout(Order $order): void
{
    try {
        $this->payment->charge($order);
    } catch (\Throwable $e) {
        Kaveh::track('checkout.failed', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'gateway' => $order->payment_gateway,
            'reason' => $e->getMessage(),
        ], tags: ['billing', 'checkout'], level: 'error');

        throw $e;
    }

    Kaveh::track('checkout.completed', [
        'order_id' => $order->id,
        'total' => $order->total,
    ], tags: ['billing', 'checkout']);
}
```

These show up in **Events** like Telescope entries, with type `custom`, searchable name/tags, and full JSON context on the detail page.

---

## Quick start

### Requirements

- PHP **8.2+**
- Laravel **11 / 12 / 13**
- A queue worker when `KAVEH_USE_QUEUE=true` (recommended)

### Install

```bash
composer require aksoom-hussain/kaveh
php artisan kaveh:install
```

| Role | You get |
|------|---------|
| `client` | Watchers + `Kaveh::track()` + local/remote ship |
| `server` | Ingest API + dashboard + alerts + metrics API |
| `both` | Everything on one app |

Publishes:

- `config/kaveh.php`
- `App\Providers\KavehServiceProvider` (gate `viewKaveh`, same idea as Telescope/Pulse)
- `.env` keys (`KAVEH_*`)

---

## What gets collected automatically?

| Watcher | Captures | Toggle |
|---------|----------|--------|
| Exceptions | Error/critical exceptions + stack | `KAVEH_WATCH_EXCEPTIONS` |
| Requests | Method, URI, status, duration, IP | `KAVEH_WATCH_REQUESTS` |
| Queries | Slow SQL over threshold | `KAVEH_WATCH_QUERIES` + `KAVEH_SLOW_QUERY_MS` |
| Jobs | Processed / failed queue jobs | `KAVEH_WATCH_JOBS` |
| Logs | Error logs (noisy; off by default) | `KAVEH_WATCH_LOGS` |

Request watcher ignores `/kaveh/*`, `/pulse/*`, `/telescope/*`, `/api/v1/ingest`, Livewire, etc. Add more with `KAVEH_IGNORE_PATHS`.

Kaveh’s own jobs (`FlushEventsJob`, `EmbedEventJob`, …) are ignored so the monitor does not flood itself.

---

## Server dashboard

After server install:

| Surface | URL |
|---------|-----|
| Login | `/kaveh/login` |
| Overview | Graphs + recent events (project + `1h/6h/24h/7d`) |
| Events | Telescope-style list (verb, path, status, duration) |
| Alerts | Threshold rules → webhook / email |
| Projects | Multi-tenant projects + API keys |

**Authorization** — edit the published provider:

```php
// app/Providers/KavehServiceProvider.php
Gate::define('viewKaveh', function ($user) {
    return in_array($user->email, [
        'ops@yourcompany.com',
    ], true);
});
```

Default: open in `local`, denied elsewhere until you customize the gate.

### Optional Pulse graphs

If [`laravel/pulse`](https://github.com/laravel/pulse) is installed on the **server** host, Overview charts read `pulse_aggregates` (CPU, memory, traffic, queue, cache):

```
GET /kaveh/api/metrics/pulse?period=60&range=3600   # session auth
GET /kaveh/api/metrics/events?hours=24&project_id=1
```

---

## Ingest API (for custom shippers / debugging)

```http
POST /api/v1/ingest
Authorization: Bearer kv_xxxxxxxx
Content-Type: application/json
```

```json
{
  "events": [
    {
      "type": "custom",
      "name": "deploy.finished",
      "timestamp": "2026-08-08T12:00:00Z",
      "environment": "production",
      "hostname": "web-1",
      "level": "info",
      "tags": ["deploy"],
      "context": { "version": "1.4.2" }
    }
  ]
}
```

- `202` accepted · `401` bad key · `422` invalid · `429` rate limited  
- Duplicate event ids per project are ignored (idempotent)

cURL check:

```bash
curl -X POST "https://kaveh.yourcompany.com/api/v1/ingest" \
  -H "Authorization: Bearer kv_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"events":[{"type":"custom","name":"healthcheck","timestamp":"'"$(date -u +%Y-%m-%dT%H:%M:%SZ)"'","environment":"production","hostname":"ci","level":"info","context":{"ok":true},"tags":["health"]}]}'
```

---

## Configuration cheatsheet

```env
KAVEH_ENABLED=true
KAVEH_ROLE=client|server|both
KAVEH_MODE=local|remote|both
KAVEH_SERVER_URL=https://kaveh.yourcompany.com
KAVEH_API_KEY=kv_xxxxxxxx
KAVEH_PATH=kaveh
KAVEH_USE_QUEUE=true
KAVEH_FAIL_SILENTLY=true          # never break the host app
KAVEH_WATCH_EXCEPTIONS=true
KAVEH_WATCH_REQUESTS=true
KAVEH_WATCH_QUERIES=true
KAVEH_SLOW_QUERY_MS=100
KAVEH_WATCH_JOBS=true
KAVEH_WATCH_LOGS=false
```

Schedule on the server:

```php
// routes/console.php or Kernel
Schedule::command('kaveh:prune-events')->daily();
Schedule::command('kaveh:evaluate-alerts')->everyMinute();
```

---

## Example: wire a production app in 5 minutes

1. On **Kaveh server**: Projects → create a project (e.g. **Shop**) → Issue API key → copy `kv_…`
2. On the **application** you want to monitor:

```bash
composer require aksoom-hussain/kaveh
php artisan kaveh:install --role=client --mode=remote \
  --server-url=https://kaveh.yourcompany.com \
  --api-key=kv_xxxxxxxx \
  --non-interactive
php artisan queue:work   # supervisord / Horizon in production
```

3. Hit any HTTP route or fail a job → refresh Kaveh **Events** (select that project).
4. Optionally track checkout / webhook failures with `Kaveh::track()` (see use case 4).

---

## Security notes

- Treat API keys like passwords; rotate from **Projects**.
- Context is redacted (passwords, tokens, auth headers) before ship.
- Lock down `viewKaveh` before exposing `/kaveh` on the public internet.
- Prefer HTTPS for `KAVEH_SERVER_URL`.

More: [docs/security.md](docs/security.md)

---

## Documentation

| Doc | Description |
|-----|-------------|
| [docs/use-cases.md](docs/use-cases.md) | Deeper scenarios & architecture diagrams |
| [docs/client.md](docs/client.md) | Client install, watchers, queue, track() |
| [docs/server.md](docs/server.md) | Server install, dashboard, alerts, keys |
| [docs/ingest-api.md](docs/ingest-api.md) | Ingest payload reference |
| [docs/custom-events.md](docs/custom-events.md) | Snippets for billing, queues, webhooks |

---

## Development

```bash
composer test
```

Package layout: `src/`, `config/`, `routes/`, `resources/views/`, `database/migrations/`, `stubs/`, `docs/`.

---

## License

[MIT](LICENSE.md)
