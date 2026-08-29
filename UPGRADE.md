# Upgrading Kaveh 0.1.4 → 0.2.0

0.2.0 renames every database table, removes the RAG/chat feature, and changes
redaction semantics. Read this page before running `composer update`. The full
list of changes is in [CHANGELOG.md](CHANGELOG.md).

**Take a database backup first.** The rename migration is designed to be safe and
to refuse rather than guess, but a restore point costs you nothing.

---

## 1. Server tables are renamed to `kaveh_*`

| 0.1.4 | 0.2.0 |
|---|---|
| `organizations` | `kaveh_organizations` |
| `organization_user` | `kaveh_organization_user` |
| `projects` | `kaveh_projects` |
| `api_keys` | `kaveh_api_keys` |
| `events` | `kaveh_events` |
| `alert_rules` | `kaveh_alert_rules` |
| `alert_firings` | `kaveh_alert_firings` |

The names 0.1.4 used are names a host application very plausibly uses for its own
tables — and Kaveh supports `role=both`, i.e. running inside that host app. So
the migration `2026_08_09_000001_rename_legacy_server_tables` renames in place
and **preserves your data**, but only when it is certain the table is Kaveh's.

### The three detection signals

For each table, all three must hold:

1. The legacy table exists (otherwise: fresh install, or already renamed).
2. The `kaveh_*` target does **not** already exist.
3. The legacy table's column set matches 0.1.4's exactly — set equality, not a
   superset. Extra columns mean it is not the table Kaveh created.

### All-or-nothing

If any one table fails signals 2 or 3, **nothing is renamed at all** and the
migration logs a warning (to the log and to STDERR) naming the table and the
reason. Foreign keys span the whole set, so half a rename is worse than none.

A warning therefore means: *your server tables are still on their old names and
0.2.0's models are pointing at names that do not exist.* The dashboard and ingest
will fail until you resolve it. Resolve it by renaming the tables yourself, then
re-running `php artisan migrate`.

The rename runs outside Laravel's migration transaction on purpose: SQLite only
rewrites other tables' `REFERENCES` clauses when `PRAGMA foreign_keys` is ON, and
that pragma is a no-op inside a transaction. The migration turns it on, wraps the
renames in an explicit transaction, and restores the previous setting. MySQL and
PostgreSQL carry indexes and foreign keys through `RENAME TO` on their own.

> The `create` migration's `down()` still drops only `kaveh_*` names. Rolling back
> will not drop generic-named host tables.

## 2. The client buffer table is renamed — and it can block signal 2

In 0.1.4 the client's local store / buffer table was also called `kaveh_events`.
In 0.2.0 it is `kaveh_client_events`.

This matters on a `role=both` (or previously-client, now-server) install:
the leftover 0.1.4 **client** `kaveh_events` table occupies the name the
**server** `events` table needs, so the rename aborts on signal 2 with
"`kaveh_events` already exists".

The client table is a short-lived buffer (`local.retention_hours`, default 48),
so the fix is normally to get the old one out of the way:

```sql
-- after confirming you do not need <48h of buffered client events
DROP TABLE kaveh_events;          -- the 0.1.4 CLIENT buffer table
-- or, to keep the rows:
ALTER TABLE kaveh_events RENAME TO kaveh_client_events;
```

Do this **before** `php artisan migrate`. If you renamed rather than dropped,
the new client migration will find `kaveh_client_events` already present and
create nothing.

> **If you published `config/kaveh.php` in 0.1.x**, your copy still has
> `'local' => ['table' => 'kaveh_events']`. 0.2.0 no longer overwrites a
> published config (see §5), so you must edit that value to
> `'kaveh_client_events'` by hand.

## 3. RAG / chat is removed

`ChatController`, the embedding and RAG services, `EmbedEventJob`, the
`EventEmbedding` model, the chat views and `EventRecord::embedding()` /
`searchableText()` are all gone. No chat route was ever registered, so nothing
user-facing disappears.

Remove these four keys from a published `config/kaveh.php`, and the matching env
vars from `.env`:

| Config key | Env |
|---|---|
| `openai_api_key` | `KAVEH_OPENAI_API_KEY` |
| `openai_base_url` | `KAVEH_OPENAI_BASE_URL` |
| `embedding_model` | `KAVEH_EMBEDDING_MODEL` |
| `chat_model` | `KAVEH_CHAT_MODEL` |

Also drop the now-orphaned table — the rename migration does not touch it:

```sql
DROP TABLE event_embeddings;
```

Unrelated but in the same class: `batch.flush_interval_seconds` /
`KAVEH_FLUSH_INTERVAL` is removed. Nothing ever read it.

## 4. `redact.keys` is additive, not a replacement

In 0.1.4, setting `redact.keys` **replaced** the list, so a three-entry config
silently disabled every other protection. In 0.2.0 it is **added on top of** a
built-in floor: any key whose normalised form (lowercased, with `_`, `-`, `.` and
spaces stripped) *contains* one of `Redactor::SECRET_PATTERNS` is masked
regardless of your config.

**You will now see more redaction than before, not less.** Two consequences:

- If you deliberately shortened `redact.keys` to capture a field, that field is
  masked again if its name contains a secret pattern. Rename the field.
- Known over-redaction: `auth` masks `author`, `token` masks `tokenizer_version`.

To extend the floor, append to `redact.key_patterns` (default
`Redactor::SECRET_PATTERNS`) rather than replacing it — patterns are compared
with `str_contains` against the normalised key, so write them without
separators.

Related: the request watcher no longer captures the session wholesale.
`watchers.RequestWatcher.session_keys` is an allowlist, empty by default, so
**no session data is captured** until you opt in per key.

## 5. `kaveh:install` no longer overwrites a published config or provider

0.1.4 published both files with `--force`, so re-running install on an upgrade
silently ate a customized `viewKaveh` gate. 0.2.0 keeps an existing
`config/kaveh.php` and `app/Providers/KavehServiceProvider.php` and reports
`exists — kept`.

That means **re-running install does not bring your published config up to
0.2.0** — the manual edits in §2 and §3 are on you. To take the fresh copies
instead, pass `--force`; each replaced file is copied to `<file>.bak.<timestamp>`
first, so you can diff your gate back in.

`kaveh:install` now also exits with a failure code for an invalid `--role` and
for a failing `migrate` step.

---

## Upgrade procedure

1. **Back up the database.** Take the app off traffic, or at least stop the
   queue workers and the `kaveh:check` supervisor program.
2. If you run a **server** or `role=both` install, deal with the 0.1.4 client
   buffer table now (§2): drop it, or rename it to `kaveh_client_events`.
3. `composer require aksoom-hussain/kaveh:^0.2.0`
4. Edit your published `config/kaveh.php`, if you have one:
   `local.table` → `kaveh_client_events` (§2); delete the four `openai_*` /
   `*_model` keys (§3); delete `batch.flush_interval_seconds`. Remove the
   matching `KAVEH_*` lines from `.env`.
5. `php artisan config:clear`
6. `php artisan migrate` — **read the output.** A `Kaveh: skipped renaming legacy
   server tables` warning means nothing was renamed; fix the named table per §1
   and run `migrate` again before continuing.
7. `DROP TABLE event_embeddings;` (§3).
8. Restart queue workers and `kaveh:check`.

### Verify

```bash
php artisan migrate:status          # rename_legacy_server_tables = Ran
php artisan kaveh:check --once      # ships one sample synchronously
php artisan kaveh:prune-events      # prints "Pruned N events." — no fatal
```

Then confirm the data survived and the dashboard is reachable:

```sql
SELECT count(*) FROM kaveh_events;      -- your 0.1.4 event history
SELECT count(*) FROM kaveh_projects;    -- your projects and their API keys
```

Open `/kaveh` and log in. If you get a 403, your app defines its own `viewKaveh`
gate (0.2.0 respects it instead of overwriting it) — check that it returns true
for your user.
