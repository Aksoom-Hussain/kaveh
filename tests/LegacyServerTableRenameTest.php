<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Covers the <= 0.1.4 -> kaveh_* upgrade migration.
 *
 * The suite boots with all migrations already applied, so the kaveh_* tables
 * exist. Tests that need to simulate a 0.1.4 install tear those down and
 * recreate the old generic-named schema before invoking the migration.
 */
class LegacyServerTableRenameTest extends TestCase
{
    private const KAVEH_TABLES = [
        'kaveh_alert_firings', 'kaveh_alert_rules', 'kaveh_events', 'kaveh_api_keys',
        'kaveh_projects', 'kaveh_organization_user', 'kaveh_organizations',
    ];

    private const LEGACY_TABLES = [
        'alert_firings', 'alert_rules', 'events', 'api_keys',
        'projects', 'organization_user', 'organizations',
    ];

    public function test_fresh_install_creates_the_kaveh_tables_and_the_rename_is_a_no_op(): void
    {
        foreach (self::KAVEH_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} missing after a fresh migrate");
        }

        $this->migration()->up();

        foreach (self::KAVEH_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        foreach (self::LEGACY_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} should not have been created");
        }
    }

    public function test_upgrade_renames_legacy_tables_and_preserves_row_data(): void
    {
        $this->simulateLegacyInstall();
        $this->seedLegacyData();

        $this->migration()->up();

        foreach (self::LEGACY_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} should have been renamed away");
        }

        $this->assertSame(1, DB::table('kaveh_organizations')->count());
        $this->assertSame('Acme', DB::table('kaveh_organizations')->value('name'));

        $project = DB::table('kaveh_projects')->first();
        $this->assertSame('Checkout', $project->name);
        $this->assertSame(30, (int) $project->retention_days);

        $this->assertSame(2, DB::table('kaveh_events')->count());
        $event = DB::table('kaveh_events')->where('uuid', 'evt-1')->first();
        $this->assertSame('exception', $event->type);
        $this->assertSame('RuntimeException', $event->name);
        $this->assertSame((int) $project->id, (int) $event->project_id);

        $this->assertSame('kv_live', DB::table('kaveh_api_keys')->value('key_prefix'));
        $this->assertSame('Too many errors', DB::table('kaveh_alert_rules')->value('name'));
        $this->assertSame(11, (int) DB::table('kaveh_alert_firings')->value('count'));
    }

    public function test_foreign_keys_and_indexes_survive_the_rename(): void
    {
        $this->simulateLegacyInstall();
        $this->seedLegacyData();

        $this->migration()->up();

        $references = array_map(
            fn ($fk) => $fk->table,
            DB::select('PRAGMA foreign_key_list(kaveh_events)')
        );
        $this->assertContains('kaveh_projects', $references, 'kaveh_events FK still points at the old table');

        $indexes = array_map(fn ($i) => $i->name, DB::select('PRAGMA index_list(kaveh_events)'));
        $this->assertNotEmpty($indexes, 'indexes were lost in the rename');

        // The FK is genuinely enforced against the new table, not just declared.
        DB::statement('PRAGMA foreign_keys = ON');
        $orphan = DB::table('kaveh_projects')->max('id') + 99;
        $this->expectException(\Illuminate\Database\QueryException::class);

        try {
            DB::table('kaveh_events')->insert([
                'project_id' => $orphan,
                'uuid' => 'evt-orphan',
                'type' => 'log',
                'name' => 'nope',
                'level' => 'info',
            ]);
        } finally {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
    }

    public function test_a_non_kaveh_table_with_a_colliding_name_is_left_untouched(): void
    {
        $this->simulateLegacyInstall();
        $this->seedLegacyData();

        // The host app owns `events`: same name, different shape.
        Schema::drop('events');
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
        DB::table('events')->insert(['title' => 'Company picnic']);

        $this->migration()->up();

        $this->assertTrue(Schema::hasTable('events'), 'the host app table was stolen');
        $this->assertSame('Company picnic', DB::table('events')->value('title'));
        $this->assertFalse(Schema::hasTable('kaveh_events'));

        // All-or-nothing: one ambiguous table aborts the whole batch.
        foreach (self::KAVEH_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} was renamed despite an ambiguous sibling");
        }
        foreach (array_diff(self::LEGACY_TABLES, ['events']) as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} should have been left alone");
        }
    }

    public function test_rerunning_the_migration_is_a_no_op(): void
    {
        $this->simulateLegacyInstall();
        $this->seedLegacyData();

        $this->migration()->up();
        $before = DB::table('kaveh_events')->count();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame($before, DB::table('kaveh_events')->count());
        foreach (self::KAVEH_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
    }

    public function test_down_renames_the_tables_back_with_their_data(): void
    {
        $this->simulateLegacyInstall();
        $this->seedLegacyData();

        $this->migration()->up();
        $this->migration()->down();

        foreach (self::LEGACY_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} missing after rollback");
        }
        foreach (self::KAVEH_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertSame(2, DB::table('events')->count());
        $this->assertSame('Acme', DB::table('organizations')->value('name'));
    }

    private function migration(): Migration
    {
        return require __DIR__.'/../database/migrations/server/2026_08_09_000001_rename_legacy_server_tables.php';
    }

    /**
     * Drop the kaveh_* tables and recreate the exact schema Kaveh 0.1.4 shipped.
     */
    private function simulateLegacyInstall(): void
    {
        foreach (self::KAVEH_TABLES as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('retention_days')->default(14);
            $table->unsignedBigInteger('max_events')->default(1_000_000);
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 12);
            $table->string('key_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('uuid')->index();
            $table->string('type', 32)->index();
            $table->string('name')->index();
            $table->string('level', 32)->default('info')->index();
            $table->string('environment', 64)->nullable()->index();
            $table->string('hostname')->nullable();
            $table->string('trace_id')->nullable()->index();
            $table->json('user')->nullable();
            $table->json('context')->nullable();
            $table->json('tags')->nullable();
            $table->decimal('duration_ms', 12, 3)->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['project_id', 'uuid']);
        });

        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('metric');
            $table->string('event_name')->nullable();
            $table->unsignedInteger('threshold')->default(10);
            $table->unsignedInteger('window_minutes')->default(5);
            $table->unsignedInteger('cooldown_minutes')->default(30);
            $table->string('channel');
            $table->string('target');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('alert_firings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('count');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    private function seedLegacyData(): void
    {
        $now = now();

        $organizationId = DB::table('organizations')->insertGetId([
            'name' => 'Acme', 'slug' => 'acme', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $projectId = DB::table('projects')->insertGetId([
            'organization_id' => $organizationId,
            'name' => 'Checkout',
            'slug' => 'checkout',
            'retention_days' => 30,
            'max_events' => 500000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('api_keys')->insert([
            'project_id' => $projectId,
            'name' => 'CI',
            'key_prefix' => 'kv_live',
            'key_hash' => str_repeat('a', 64),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (['evt-1' => 'RuntimeException', 'evt-2' => 'LogicException'] as $uuid => $name) {
            DB::table('events')->insert([
                'project_id' => $projectId,
                'uuid' => $uuid,
                'type' => 'exception',
                'name' => $name,
                'level' => 'error',
                'environment' => 'production',
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $ruleId = DB::table('alert_rules')->insertGetId([
            'project_id' => $projectId,
            'name' => 'Too many errors',
            'metric' => 'exception_rate',
            'threshold' => 10,
            'window_minutes' => 5,
            'cooldown_minutes' => 30,
            'channel' => 'webhook',
            'target' => 'https://hooks.test/kaveh',
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('alert_firings')->insert([
            'alert_rule_id' => $ruleId,
            'count' => 11,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
