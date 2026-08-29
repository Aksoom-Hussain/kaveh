<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * End-to-end upgrade from a real Kaveh 0.1.4 install.
 *
 * Unlike LegacyServerTableRenameTest, which drives the rename migration object
 * directly, this boots a database that looks exactly like a 0.1.4 install --
 * the 0.1.4 schema (copied verbatim from `git show v0.1.4:`) plus 0.1.4's
 * migration filenames already recorded in the `migrations` table -- and then
 * runs `php artisan migrate` for real. That is the only way to cover the thing
 * that was broken: the ORDER the migrations run in relative to each other.
 *
 * `defineDatabaseMigrations()` deliberately does NOT run the package
 * migrations; each test body does, so it can assert what the upgrade did.
 */
class UpgradeFromV014Test extends TestCase
{
    private const SERVER_LEGACY_TABLES = [
        'organizations', 'organization_user', 'projects', 'api_keys',
        'events', 'alert_rules', 'alert_firings',
    ];

    private const SERVER_KAVEH_TABLES = [
        'kaveh_organizations', 'kaveh_organization_user', 'kaveh_projects',
        'kaveh_api_keys', 'kaveh_events', 'kaveh_alert_rules', 'kaveh_alert_firings',
    ];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('kaveh.role', $this->role());
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        $role = $this->role();

        if ($role !== 'client') {
            $this->createV014ServerSchema();
            $this->seedV014ServerData();
            $this->markAsAlreadyRun('2026_08_08_000001_create_kaveh_core_tables');
        }

        if ($role !== 'server') {
            $this->createV014ClientSchema();
            $this->seedV014ClientData();
            $this->markAsAlreadyRun('2024_01_01_000001_create_kaveh_events_table');
        }
    }

    /**
     * The broken case: 0.1.4's client buffer is literally named `kaveh_events`,
     * which is the target name the server's `events` table needs.
     */
    public function test_role_both_upgrade_renames_server_tables_and_adopts_the_client_buffer(): void
    {
        $this->artisan('migrate')->run();

        foreach (self::SERVER_LEGACY_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "`{$table}` was not renamed -- the upgrade no-opped");
        }
        foreach (self::SERVER_KAVEH_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "`{$table}` missing after the upgrade");
        }

        // Server data survived, and `kaveh_events` is now the SERVER table.
        $this->assertSame('Acme', DB::table('kaveh_organizations')->value('name'));
        $project = DB::table('kaveh_projects')->first();
        $this->assertSame('Checkout', $project->name);
        $this->assertSame(2, DB::table('kaveh_events')->count());
        $this->assertSame(
            (int) $project->id,
            (int) DB::table('kaveh_events')->where('uuid', 'evt-1')->value('project_id')
        );
        $this->assertSame('kv_live', DB::table('kaveh_api_keys')->value('key_prefix'));
        $this->assertSame(11, (int) DB::table('kaveh_alert_firings')->value('count'));

        // Client buffer survived, in the renamed table -- not orphaned, not duplicated.
        $this->assertClientBufferAdopted();
    }

    public function test_role_client_upgrade_adopts_the_client_buffer(): void
    {
        $this->artisan('migrate')->run();

        $this->assertClientBufferAdopted();
        $this->assertFalse(Schema::hasTable('kaveh_events'), '`kaveh_events` should have been renamed away');

        foreach (self::SERVER_KAVEH_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "`{$table}` should not exist on a client-only install");
        }
    }

    public function test_role_server_upgrade_renames_the_server_tables(): void
    {
        $this->artisan('migrate')->run();

        foreach (self::SERVER_LEGACY_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "`{$table}` was not renamed");
        }
        $this->assertSame(2, DB::table('kaveh_events')->count());
        $this->assertSame('Acme', DB::table('kaveh_organizations')->value('name'));
        $this->assertFalse(Schema::hasTable('kaveh_client_events'));
    }

    /**
     * A host app's own `kaveh_events` (wrong shape) must never be adopted, and
     * because it still occupies the server rename's target name the whole
     * server batch must stay all-or-nothing untouched rather than half-renamed.
     */
    public function test_a_foreign_kaveh_events_table_is_never_adopted(): void
    {
        Schema::drop('kaveh_events');
        Schema::create('kaveh_events', function (Blueprint $table) {
            $table->id();
            $table->string('headline');
            $table->timestamps();
        });
        DB::table('kaveh_events')->insert(['headline' => 'Company picnic']);

        $this->artisan('migrate')->run();

        $this->assertSame('Company picnic', DB::table('kaveh_events')->value('headline'));
        $this->assertSame(0, DB::table('kaveh_client_events')->count(), 'a foreign table was adopted as the buffer');

        foreach (self::SERVER_LEGACY_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "`{$table}` was renamed despite a blocked batch");
        }
        $this->assertSame(2, DB::table('events')->count());
    }

    private function assertClientBufferAdopted(): void
    {
        $this->assertTrue(Schema::hasTable('kaveh_client_events'));
        $this->assertSame(2, DB::table('kaveh_client_events')->count(), 'buffered client events were orphaned');
        $this->assertSame(
            'Illuminate\Http\Request',
            DB::table('kaveh_client_events')->where('uuid', 'buf-1')->value('name')
        );
    }

    private function role(): string
    {
        $name = $this->name();

        return match (true) {
            str_contains($name, 'role_client') => 'client',
            str_contains($name, 'role_server') => 'server',
            default => 'both',
        };
    }

    private function markAsAlreadyRun(string $migration): void
    {
        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => (int) DB::table('migrations')->max('batch'),
        ]);
    }

    /** Verbatim from `git show v0.1.4:database/migrations/client/2024_01_01_000001_create_kaveh_events_table.php`. */
    private function createV014ClientSchema(): void
    {
        Schema::create('kaveh_events', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('type', 32)->index();
            $table->string('name')->index();
            $table->string('level', 32)->default('info');
            $table->string('environment', 64)->nullable();
            $table->string('hostname')->nullable();
            $table->string('trace_id')->nullable()->index();
            $table->json('user')->nullable();
            $table->json('context')->nullable();
            $table->json('tags')->nullable();
            $table->decimal('duration_ms', 12, 3)->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /** Verbatim from `git show v0.1.4:database/migrations/server/2026_08_08_000001_create_kaveh_core_tables.php`. */
    private function createV014ServerSchema(): void
    {
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

        // 0.1.4 also shipped the RAG table. 0.2.0 dropped the feature but an
        // upgraded database still has it, with a foreign key into `events`.
        Schema::create('event_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->timestamps();
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

    private function seedV014ClientData(): void
    {
        $now = now();

        foreach (['buf-1' => 'Illuminate\Http\Request', 'buf-2' => 'Illuminate\Queue\Job'] as $uuid => $name) {
            DB::table('kaveh_events')->insert([
                'uuid' => $uuid,
                'type' => 'request',
                'name' => $name,
                'level' => 'info',
                'environment' => 'production',
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedV014ServerData(): void
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
