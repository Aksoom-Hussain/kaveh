<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * kaveh:install writes .env and then migrates in the same process, long after
 * the provider decided which migration directories to load. A first run with
 * --role=server therefore used to print "Nothing to migrate." and leave an
 * empty database (KV-22), and a --role=client run used to leave a stale
 * KAVEH_SERVER_ENABLED=true behind (KV-23).
 */
final class InstallCommandTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    /** @var array<string, string|null> Skeleton files the command rewrites, as they were. */
    private array $restore = [];

    /** Boot as a plain client install: no server migrations registered. */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('kaveh.role', 'client');
        $app['config']->set('kaveh.server.enabled', false);
    }

    /** Only the host app's own tables exist; nothing Kaveh owns is migrated yet. */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            File::delete($path);
        }
        foreach ($this->restore as $path => $contents) {
            $contents === null ? File::delete($path) : File::put($path, $contents);
        }

        parent::tearDown();
    }

    public function test_a_single_server_install_creates_the_server_tables(): void
    {
        $this->assertFalse(Schema::hasTable('kaveh_events'), 'precondition: no server tables yet');

        $this->trackPublishedFiles();

        $this->artisan('kaveh:install', ['--role' => 'server', '--non-interactive' => true])
            ->assertSuccessful();

        foreach ([
            'kaveh_organizations',
            'kaveh_organization_user',
            'kaveh_projects',
            'kaveh_api_keys',
            'kaveh_events',
            'kaveh_alert_rules',
            'kaveh_alert_firings',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing server table {$table}");
        }
    }

    public function test_a_client_install_clears_a_stale_server_enabled_flag(): void
    {
        $envPath = base_path('.env');
        $this->cleanup[] = $envPath;
        File::put($envPath, "APP_ENV=testing\nKAVEH_ROLE=server\nKAVEH_SERVER_ENABLED=true\n");

        $this->trackPublishedFiles();

        $this->artisan('kaveh:install', [
            '--role' => 'client',
            '--mode' => 'local',
            '--non-interactive' => true,
        ])->assertSuccessful();

        $env = File::get($envPath);
        $this->assertStringContainsString('KAVEH_ROLE=client', $env);
        $this->assertStringContainsString('KAVEH_SERVER_ENABLED=false', $env);
        $this->assertStringNotContainsString('KAVEH_SERVER_ENABLED=true', $env);
        $this->assertFalse((bool) config('kaveh.server.enabled'));
    }

    /** The command publishes into the skeleton app; remove what it creates. */
    private function trackPublishedFiles(): void
    {
        foreach ([config_path('kaveh.php'), app_path('Providers/KavehServiceProvider.php')] as $path) {
            if (! File::exists($path)) {
                $this->cleanup[] = $path;
            }
        }

        // Install also registers the provider in the skeleton's bootstrap file.
        $providers = base_path('bootstrap/providers.php');
        $this->restore[$providers] = File::exists($providers) ? File::get($providers) : null;
    }
}
