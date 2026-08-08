# Custom event examples

Use `Kaveh::track()` for domain signals watchers will not infer.

```php
use Kaveh\Kaveh;
```

## Billing / checkout

```php
Kaveh::track('checkout.failed', [
    'order_id' => $order->id,
    'amount' => $order->total,
    'currency' => $order->currency,
    'gateway' => 'stripe',
    'decline_code' => $e->getDeclineCode(),
], tags: ['billing', 'checkout'], level: 'error');
```

## Shopify (or any) webhooks

```php
Kaveh::track('webhook.received', [
    'provider' => 'shopify',
    'topic' => $topic,
    'shop' => $shop,
], tags: ['webhook']);

// on handler failure
Kaveh::track('webhook.failed', [
    'provider' => 'shopify',
    'topic' => $topic,
    'shop' => $shop,
    'error' => $e->getMessage(),
], tags: ['webhook'], level: 'error');
```

## Background jobs (extra context)

Job watcher already records success/failure. Add business context:

```php
public function handle(): void
{
    Kaveh::track('sync.started', [
        'resource' => 'customers',
        'batch_id' => $this->batchId,
    ], tags: ['sync']);

    // ...

    Kaveh::track('sync.finished', [
        'resource' => 'customers',
        'batch_id' => $this->batchId,
        'imported' => $count,
        'duration_ms' => $ms,
    ], tags: ['sync']);
}
```

## Feature flags / experiments

```php
Kaveh::track('experiment.exposure', [
    'flag' => 'new_checkout',
    'variant' => $variant,
    'user_id' => $user->id,
], tags: ['experiment']);
```

## Deploy / ops

```php
Kaveh::track('deploy.finished', [
    'version' => config('app.version'),
    'sha' => trim((string) @file_get_contents(base_path('VERSION'))),
], tags: ['deploy'], level: 'info');
```

## Facade style

```php
use Kaveh\Facades\Kaveh;

Kaveh::track('search.slow', [
    'q' => $query,
    'took_ms' => $took,
], level: 'warning', tags: ['search']);
```

## Tips

- Keep `name` stable and dot-namespaced (`area.action`) so alerts and search stay useful.
- Put identifiers in `context`, not in the name.
- Use `tags` for faceting (`billing`, `shopify`, `sync`).
- Levels: `info`, `warning`, `error`, `critical`.
