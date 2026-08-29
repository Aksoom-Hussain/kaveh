<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade path from Kaveh <= 0.1.4, where the server tables used generic names.
 *
 * ORDERING: this file is dated AFTER 2026_08_08_000001_create_kaveh_core_tables.
 * That create migration kept its filename across the rename release, so:
 *   - fresh install  -> create runs (makes kaveh_*), then this runs and finds
 *                       nothing legacy to do.
 *   - 0.1.4 upgrade  -> create is already recorded in `migrations` and is
 *                       skipped, this runs and renames the real tables.
 * Do not renumber either file.
 *
 * SAFETY: `events`, `projects`, `organizations` and `api_keys` are names a host
 * application very plausibly uses for its own tables (Kaveh supports
 * role=both, i.e. running inside a host app). A blind rename would steal and
 * destroy that data, so nothing is renamed unless every legacy table present
 * positively matches the exact column signature the 0.1.4 migration created and
 * its kaveh_* target name is free. One mismatch aborts the whole batch and
 * warns -- half a rename is worse than none, since the foreign keys point
 * across the set.
 */
return new class extends Migration
{
    /**
     * Off so `PRAGMA foreign_keys` can be changed at all: SQLite makes that
     * pragma a no-op inside a transaction, and Laravel would otherwise wrap
     * this migration in one. The renames get their own explicit transaction.
     */
    public $withinTransaction = false;

    /**
     * Legacy name => [new name, exact column signature created by Kaveh 0.1.4].
     *
     * `kaveh_client_events` is deliberately absent: the client buffer rename is
     * handled by the client migration.
     */
    private const TABLES = [
        'organizations' => ['kaveh_organizations', [
            'id', 'name', 'slug', 'created_at', 'updated_at',
        ]],
        'organization_user' => ['kaveh_organization_user', [
            'id', 'organization_id', 'user_id', 'role', 'created_at', 'updated_at',
        ]],
        'projects' => ['kaveh_projects', [
            'id', 'organization_id', 'name', 'slug', 'retention_days', 'max_events',
            'created_at', 'updated_at',
        ]],
        'api_keys' => ['kaveh_api_keys', [
            'id', 'project_id', 'name', 'key_prefix', 'key_hash', 'last_used_at',
            'revoked_at', 'created_at', 'updated_at',
        ]],
        'events' => ['kaveh_events', [
            'id', 'project_id', 'uuid', 'type', 'name', 'level', 'environment',
            'hostname', 'trace_id', 'user', 'context', 'tags', 'duration_ms',
            'occurred_at', 'created_at', 'updated_at',
        ]],
        'alert_rules' => ['kaveh_alert_rules', [
            'id', 'project_id', 'name', 'metric', 'event_name', 'threshold',
            'window_minutes', 'cooldown_minutes', 'channel', 'target', 'enabled',
            'last_fired_at', 'created_at', 'updated_at',
        ]],
        'alert_firings' => ['kaveh_alert_firings', [
            'id', 'alert_rule_id', 'count', 'meta', 'created_at', 'updated_at',
        ]],
    ];

    public function up(): void
    {
        $pairs = [];
        foreach (self::TABLES as $legacy => [$current, $columns]) {
            $pairs[$legacy] = [$current, $columns];
        }

        $this->renameAll($pairs);
    }

    public function down(): void
    {
        $pairs = [];
        foreach (self::TABLES as $legacy => [$current, $columns]) {
            $pairs[$current] = [$legacy, $columns];
        }

        $this->renameAll($pairs);
    }

    /**
     * @param  array<string, array{0: string, 1: list<string>}>  $pairs  from => [to, expected columns]
     */
    private function renameAll(array $pairs): void
    {
        $todo = [];

        foreach ($pairs as $from => [$to, $columns]) {
            if (! Schema::hasTable($from)) {
                continue; // fresh install, or already renamed on an earlier run.
            }

            if (Schema::hasTable($to)) {
                $this->warn($from, $to, "`{$to}` already exists, so `{$from}` cannot be renamed into it");

                return;
            }

            if (! $this->signatureMatches($from, $columns)) {
                $this->warn($from, $to, "`{$from}` does not have Kaveh's column signature and is assumed to belong to your application");

                return;
            }

            $todo[$from] = $to;
        }

        if ($todo === []) {
            return;
        }

        $this->withForeignKeysIntact(function () use ($todo) {
            foreach ($todo as $from => $to) {
                Schema::rename($from, $to);
            }
        });
    }

    /**
     * Exact set equality -- a superset would mean the host app added columns to
     * a table that merely looks like Kaveh's, which is not proof enough.
     *
     * @param  list<string>  $expected
     */
    private function signatureMatches(string $table, array $expected): bool
    {
        $actual = array_map('strtolower', Schema::getColumnListing($table));
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }

    /**
     * MySQL (InnoDB) and PostgreSQL both carry indexes and foreign keys through
     * `ALTER TABLE ... RENAME TO` automatically; referencing constraints keep
     * their old *names* (e.g. `events_project_id_foreign`) but stay correct, and
     * Postgres keeps the old sequence name, which is likewise cosmetic.
     *
     * SQLite is the exception: it only rewrites the REFERENCES clauses of other
     * tables when foreign key enforcement is ON. Laravel leaves it off by
     * default, which would leave `kaveh_events` pointing at a `projects` table
     * that no longer exists. Turn it on for the duration and restore it.
     */
    private function withForeignKeysIntact(callable $renames): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'sqlite') {
            $connection->transaction($renames);

            return;
        }

        $was = (int) ($connection->select('PRAGMA foreign_keys')[0]->foreign_keys ?? 0);
        $connection->statement('PRAGMA foreign_keys = ON');

        try {
            $connection->transaction($renames);
        } finally {
            $connection->statement('PRAGMA foreign_keys = '.($was ? 'ON' : 'OFF'));
        }
    }

    private function warn(string $from, string $to, string $why): void
    {
        $message = "Kaveh: skipped renaming legacy server tables -- {$why}. "
            ."No tables were touched. If `{$from}` really is Kaveh's, rename it (and its siblings) "
            .'to the kaveh_* names manually, then re-run `php artisan migrate`.';

        Log::warning($message);
        fwrite(STDERR, PHP_EOL.$message.PHP_EOL);
    }
};
