# aksoom-hussain/kaveh

One Composer package for Laravel monitoring — **client and server in the same package**.

Inspired by Telescope + Pulse. Ship telemetry off production disks, or keep it local.

## Install

```bash
composer require aksoom-hussain/kaveh
php artisan kaveh:install
```

`kaveh:install` asks what you need and writes `.env` + `config/kaveh.php`:

| Role | What you get |
|------|----------------|
| `client` | Watchers + `Kaveh::track()` + local/remote modes |
| `server` | Ingest API + dashboard + alerts + event RAG |
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
```

## Server usage

After `--role=server` (or `both`):

- Dashboard: `/kaveh/login`
- Ingest: `POST /api/v1/ingest` with `Authorization: Bearer kv_…`

## Publish to Packagist

This directory (`Kaveh/`) **is** the package root (`composer.json` → `aksoom-hussain/kaveh`).

```bash
git tag v0.1.0
# submit https://packagist.org/packages/submit
```

## Local example app

Use the root [`demo/`](../demo) Laravel app:

```bash
cd ../demo
php artisan serve
```

See [`demo/README.md`](../demo/README.md) for credentials and try-it steps.

`packages/server` is an older thin host; prefer `demo/` for testing.

## Docs

See repo [`kb/`](../kb/index.md) and docs RAG under [`rag/`](../rag/).
