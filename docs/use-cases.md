# Use cases

Concrete setups other developers usually need. For a short overview see the [README](../README.md).

## A. Multiple production apps → one ops dashboard

**Goal:** Shop, API, and internal apps all report into one Kaveh host.

```
 App A (client) ─┐
 App B (client) ─┼──►  Kaveh server (role=server)
 App C (client) ─┘         ├── Project: Shop
                           ├── Project: Billing API
                           └── Project: Internal API
```

1. Install **server** on a dedicated Laravel app / VPS.
2. Create one **Project** per product; issue an API key per project (or per environment).
3. On each app, install **client** with that project’s key.
4. Ops logs into `/kaveh`, picks the project in the top bar, and uses `1h/6h/24h/7d`.

**Why not Telescope on each app?** Central search, shared alerts, and no Telescope SQLite/MySQL growth on every production box.

---

## B. Replace Telescope on staging

**Goal:** Same insight as Telescope, zero second server.

```bash
composer require aksoom-hussain/kaveh
php artisan kaveh:install --role=both --mode=local --non-interactive
php artisan migrate
```

Open `/kaveh`. Watchers record requests, exceptions, jobs, slow queries into local tables.

When you promote to production, switch the **same** package to `role=client` + `mode=remote` and point at your Kaveh server — no rewrite of `Kaveh::track()` calls.

---

## C. Debug “checkout is failing” with custom events

Watchers see HTTP 500s and exceptions. They do **not** know your domain language. Add:

```php
Kaveh::track('checkout.failed', [
    'order_id' => $order->id,
    'step' => 'authorize',
    'gateway_code' => $response->code ?? null,
], tags: ['billing'], level: 'error');
```

Then in the dashboard: Events → filter type `custom` or search `checkout.failed`.

Pair with an **alert** rule: metric `custom_event`, event name `checkout.failed`, threshold `10` / `5` minutes → Slack webhook.

---

## D. Monitor queue health across Redis queues

Enable `KAVEH_WATCH_JOBS=true` on the client. Failed jobs appear as type `job` with status `failed` and exception message.

If the **server** also runs Laravel Pulse, Overview → Queue accordion shows queued / processing / processed series from `pulse_aggregates`.

---

## E. Agency / SaaS: isolate customers by project

Each customer (or each customer environment) gets a Project + API key.

| Project | Key used by |
|---------|-------------|
| Acme production | Acme’s Laravel `KAVEH_API_KEY` |
| Acme staging | Acme staging `.env` |
| Beta Co | Beta’s app |

Ingest is scoped by key → `project_id`. Dashboard project filter never mixes tenants.

---

## F. CI healthcheck after deploy

```bash
curl -fsS -X POST "$KAVEH_SERVER_URL/api/v1/ingest" \
  -H "Authorization: Bearer $KAVEH_API_KEY" \
  -H "Content-Type: application/json" \
  -d "{\"events\":[{\"type\":\"custom\",\"name\":\"deploy.finished\",\"timestamp\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\",\"environment\":\"production\",\"hostname\":\"github-actions\",\"level\":\"info\",\"tags\":[\"deploy\"],\"context\":{\"sha\":\"$GITHUB_SHA\"}}]}"
```

Confirms network + key + ingest path before traffic resumes.

---

## Anti-patterns

| Avoid | Prefer |
|-------|--------|
| `role=both` on a busy production web node | `client` on apps, `server` on a small monitor host |
| Flushing remote batches synchronously in the request | `KAVEH_USE_QUEUE=true` + `queue:work` / Horizon |
| Leaving `viewKaveh` open on a public URL | Gate to ops emails / SSO users |
| Expecting app data without installing the **client** on that app | Install client + API key on the app being monitored |

---

## Minimal architecture checklist

- [ ] Server installed, migrations run, user can log in
- [ ] Project created, API key copied once
- [ ] Client `.env` has `SERVER_URL` + `API_KEY`
- [ ] Queue worker running on client (and server if using embeddings/alerts jobs)
- [ ] Generate traffic → Events list shows hostname of the **client** app (not only the server)
