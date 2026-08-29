# Changelog

All notable changes to `aksoom-hussain/kaveh` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.1] - 2026-08-29

### Added

- **Delete project** — `DELETE /kaveh/projects/{project}` removes a project, its
  API keys, alert rules, and events. Events are drained in 1,000-row chunks so
  large projects do not lock the database or hit gateway timeouts.
- `ProjectDeleteTest` covering authorised delete and cross-org denial.

### Fixed

- **Projects page** — `ShareDashboardFilters` no longer redirects Projects and
  Alerts routes to add `project_id` / `range` query params (fixes broken create
  flows and extra round-trips).
- **Create project** — clearer validation errors and input preservation; friendly
  message when the user has no linked organization instead of a 404 from
  `firstOrFail()`.
- **Projects list** — uses `$managedProjects` with eager-loaded API keys so the
  list is not overwritten by the shared topbar `$projects` collection.

## [0.2.0] - 2026-08-27

Upgrading from 0.1.x? Read [UPGRADE.md](UPGRADE.md) first — this release renames
every database table and changes redaction semantics.

### Added

- `kaveh:install --force`: opt in to overwriting a published `config/kaveh.php`
  or `app/Providers/KavehServiceProvider.php`. The replaced file is copied to
  `<file>.bak.<timestamp>` first.
- Migration `rename_legacy_server_tables`: renames the seven 0.1.4 server tables
  to their `kaveh_*` names in place, preserving data. It only acts when the
  legacy table exists, the `kaveh_*` target does not, and the column set matches
  0.1.4 exactly; one ambiguous table aborts the whole batch with a warning.
- Config keys `batch.max_pending` (`KAVEH_MAX_PENDING`, default 1000) and
  `batch.retry_cooldown` (`KAVEH_RETRY_COOLDOWN`, default 30) for the client
  retry buffer.
- Config key `redact.key_patterns` (default `Redactor::SECRET_PATTERNS`) to
  extend the built-in secret-key substring floor.
- Config key `watchers.RequestWatcher.session_keys` (default `[]`): an allowlist
  of session keys to capture. Empty means no session data is captured.
- Config key `server.max_decompressed_bytes` (`KAVEH_MAX_DECOMPRESSED_BYTES`,
  default 16 MB), the ingest gzip inflate ceiling.
- Test suite (testbench + SQLite in-memory) and a CI matrix over PHP 8.2–8.4 and
  Illuminate 11/12/13.

### Changed

- **BREAKING** — the seven server tables are prefixed: `organizations`,
  `organization_user`, `projects`, `api_keys`, `events`, `alert_rules`,
  `alert_firings` are now `kaveh_organizations`, `kaveh_organization_user`,
  `kaveh_projects`, `kaveh_api_keys`, `kaveh_events`, `kaveh_alert_rules`,
  `kaveh_alert_firings`.
- **BREAKING** — the client buffer/local-store table `kaveh_events` is now
  `kaveh_client_events` (`kaveh.local.table`). In 0.1.4 it collided head-on with
  the new server events table under `role=both`.
- **BREAKING** — `redact.keys` is now **additive** to a built-in floor instead of
  replacing it. A short custom list can no longer silently disable protection.
- **BREAKING** — `kaveh:install` no longer publishes with `--force`. An existing
  `config/kaveh.php` or `app/Providers/KavehServiceProvider.php` is kept.
- Secret-key matching is substring-based on the normalised key (`_`, `-`, `.`
  and spaces stripped), and now backs header redaction too. Known
  over-redaction: `auth` masks `author`, `token` masks `tokenizer_version`.
- `kaveh:prune-events` drains in 1,000-row chunks until exhausted, in both the
  retention and the overflow phase. The old 5,000-row-per-run overflow cap is
  gone, as is the unbounded id `pluck()` in the retention phase.
- `kaveh:install` returns a real failure code for an invalid `--role` and for a
  failing `migrate` step, instead of continuing and printing "Kaveh is ready".
- `kaveh:check` returns success (not failure) when `KAVEH_ENABLED` or
  `KAVEH_CHECK_ENABLED` is false, so supervisord no longer restart-loops, and
  returns an invalid-input code for a non-numeric `--interval` instead of
  silently using 5s.
- Client durability: a batch is discarded only after a confirmed 2xx. A failed
  synchronous send is returned to the front of the buffer and spilled to the
  local store, capped at `batch.max_pending` (oldest dropped).
  `FlushEventsJob` now has `tries = 5` and `backoff = [10, 30, 120, 600]` and
  throws on a failed send instead of swallowing it.
- Ingest gzip decoding is streamed in 8 KB chunks and returns `413` past
  `server.max_decompressed_bytes`.
- Batch ingest no longer wraps all events in one outer transaction; per-event
  failures flow through the `207` partial-success path.
- Redaction applies a byte budget once at depth 0, trimming the largest fields
  first, and uses a single `[REDACTED]` marker everywhere.
- Added explicit `illuminate/foundation`, `illuminate/events`,
  `illuminate/cache` and `illuminate/mail` requirements, plus a
  `composer test` script.

### Fixed

- **Cross-tenant data leak**: dashboard and metrics endpoints accepted any
  `project_id`. Project access is now authorised through a shared
  `userProjectIds()` / `authorizeProject()` pair, and an omitted `project_id`
  falls back to the caller's own projects.
- **Dashboard denied everyone**: `KavehServiceProvider` imported the `Gate`
  facade, which is never bound, so `viewKaveh` was never defined and the
  authorize middleware rejected every request. It now uses the `Gate` contract,
  and skips definition entirely when the host app already defines `viewKaveh`.
- `kaveh:prune-events` fatal-errored with "class not found" on any run that had
  rows to delete, because it still referenced the deleted `EventEmbedding`
  model.
- Ingest was vulnerable to a gzip bomb: an unbounded `gzdecode()` on the request
  body.
- `LocalEventStore::prune()` is now failure-silenced like `store()`, and its
  fallback table name follows the `kaveh_client_events` rename.
- `JobWatcher` no longer references the deleted `EmbedEventJob`; both
  flush-feedback-loop guards are preserved.
- Request watcher no longer captures the session wholesale.

### Removed

- **BREAKING** — the RAG / chat subsystem: `ChatController`, `EmbeddingService`,
  `EventRagService`, `EmbedEventJob`, the `EventEmbedding` model, the chat views,
  the `event_embeddings` table, and `EventRecord::embedding()` /
  `searchableText()`. No chat route or container binding was ever registered.
- **BREAKING** — config keys `openai_api_key`, `openai_base_url`,
  `embedding_model` and `chat_model` (and their `KAVEH_OPENAI_*` /
  `KAVEH_EMBEDDING_MODEL` / `KAVEH_CHAT_MODEL` env vars).
- **BREAKING** — config key `batch.flush_interval_seconds` and
  `KAVEH_FLUSH_INTERVAL`. Nothing read them: the buffer flushes on the size
  threshold and on application termination.

[0.2.0]: https://github.com/Aksoom-Hussain/kaveh/compare/v0.1.4...v0.2.0
