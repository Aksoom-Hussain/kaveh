# Ingest API

`POST /api/v1/ingest`

Authenticate with a **project API key** from the Kaveh dashboard (Projects).

## Request

```http
POST /api/v1/ingest HTTP/1.1
Host: kaveh.example.com
Authorization: Bearer kv_xxxxxxxx
Content-Type: application/json
Accept: application/json
```

Optional: `Content-Encoding: gzip` when the body is gzip-compressed.

### Body

```json
{
  "sent_at": "2026-08-08T12:00:00+00:00",
  "events": [
    {
      "id": "optional-stable-uuid",
      "type": "custom",
      "name": "checkout.failed",
      "timestamp": "2026-08-08T12:00:00+00:00",
      "environment": "production",
      "hostname": "web-1",
      "trace_id": "optional",
      "level": "error",
      "tags": ["billing"],
      "context": { "order_id": 42 },
      "duration_ms": 125.5,
      "user": { "id": 9, "email": "a@b.c" }
    }
  ]
}
```

### Event types

`exception` · `request` · `query` · `job` · `log` · `custom`

### Timestamps

Send ISO-8601 (prefer UTC / `Z`). Ambiguous local times cause confusing “Happened” ordering in the UI.

## Responses

| Code | Meaning |
|------|---------|
| `202` | Accepted |
| `207` | Partial — some events failed validation |
| `401` | Invalid / revoked API key |
| `422` | Payload invalid |
| `429` | Rate limited (`throttle:kaveh-ingest`) |

Duplicate `(project_id, event id)` pairs are ignored (idempotent retries are safe).

## cURL example

```bash
curl -X POST "https://kaveh.example.com/api/v1/ingest" \
  -H "Authorization: Bearer kv_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "events": [{
      "type": "custom",
      "name": "healthcheck",
      "timestamp": "2026-08-08T12:00:00Z",
      "environment": "production",
      "hostname": "ci",
      "level": "info",
      "tags": ["health"],
      "context": { "ok": true }
    }]
  }'
```

## Dashboard metrics (session auth)

These are **not** the ingest API; they power Overview charts for logged-in users:

```
GET /kaveh/api/metrics/pulse?period=60&range=3600
GET /kaveh/api/metrics/events?hours=24&project_id=1
```

`period` for Pulse aggregates: `60`, `360`, `1440`, `10080` (seconds bucket sizes used by Laravel Pulse).
