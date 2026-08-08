# Security

## API keys

- Shown **once** when issued; stored as SHA-256 hashes.
- Scope: one Laravel **project** (tenant).
- Rotate by issuing a new key and revoking the old one in **Projects**.

## Dashboard access

Protect `/kaveh` with the `viewKaveh` gate (same pattern as Telescope’s `viewTelescope` / Pulse’s `viewPulse`).

Never leave the default “open in local only” gate unchanged on a public server without reviewing who can register/login.

## Redaction

Client redactor strips common secrets from context and headers before events leave the app (passwords, tokens, authorization headers, etc.). Still avoid putting raw secrets in `Kaveh::track()` context.

## Fail-soft client

With `KAVEH_FAIL_SILENTLY=true` (default), transport and watcher errors are swallowed so monitoring cannot crash checkout or APIs. Set `KAVEH_DEBUG=true` temporarily to log swallowed errors while diagnosing.

## Network

- Use HTTPS for `KAVEH_SERVER_URL`.
- Restrict ingest to known clients at the firewall / WAF if possible.
- Rate limit is applied (`throttle:kaveh-ingest`).
