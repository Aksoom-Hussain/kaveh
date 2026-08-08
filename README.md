# aksoom-hussain/kaveh

One Composer package for Laravel monitoring — **client and server in the same package**.

Inspired by [Telescope](https://github.com/laravel/telescope) + [Pulse](https://github.com/laravel/pulse). Ship telemetry off production disks, or keep it local.

- Packagist: https://packagist.org/packages/aksoom-hussain/kaveh
- Source: https://github.com/Aksoom-Hussain/kaveh

## Install

```bash
composer require aksoom-hussain/kaveh
php artisan kaveh:install
```

`kaveh:install` asks what you need and writes `.env` + `config/kaveh.php`, and publishes `App\Providers\KavehServiceProvider` (`viewKaveh` gate).

| Role | What you get |
|------|----------------|
| `client` | Watchers + `Kaveh::track()` + local/remote modes |
| `server` | Ingest API + dashboard + alerts + event RAG + Pulse metrics API |
| `both` | Full double-edged install on one app |

### Non-interactive

```bash
# Remote client pointing at a Kaveh server
php artisan kaveh:install --role=client --mode=remote \
  --server-url=https://kaveh.example.com --api-key=kv_xxx --non-interactive

# Self-hosted server
php artisan kaveh:install --role=server --non-interactive
```

## Client usage

```php
use Kaveh\Kaveh;

Kaveh::track('checkout.failed', [
    'order_id' => $order->id,
], tags: ['billing']);
```

```env
KAVEH_ROLE=client
KAVEH_MODE=remote
KAVEH_SERVER_URL=https://kaveh.example.com
KAVEH_API_KEY=kv_xxx
KAVEH_USE_QUEUE=true
```

## Server usage

After `--role=server` (or `both`):

- Dashboard: `/kaveh/login` (`KAVEH_PATH`)
- Ingest: `POST /api/v1/ingest` with `Authorization: Bearer kv_…`
- Metrics: `GET /kaveh/api/metrics/pulse` (session) — Pulse graphs when `laravel/pulse` is present
- Gate: edit `App\Providers\KavehServiceProvider` → `viewKaveh`

## Docs

Full docs live in the monorepo knowledge base (`kb/`) when developing from source. Packagist installs use this README + inline config comments.
