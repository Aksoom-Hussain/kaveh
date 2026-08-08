<?php

declare(strict_types=1);

namespace Kaveh\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Kaveh\Server\Models\ApiKey;
use Kaveh\Server\Models\Organization;
use Kaveh\Server\Models\Project;

final class InstallCommand extends Command
{
    protected $signature = 'kaveh:install
        {--role= : client, server, or both}
        {--mode= : local, remote, or both (client)}
        {--server-url= : Remote Kaveh server URL}
        {--api-key= : Project API key for remote mode}
        {--no-migrate : Skip running migrations}
        {--non-interactive : Do not ask questions}';

    protected $description = 'Install Kaveh and write the config you need for client and/or server';

    public function handle(): int
    {
        $this->components->info('Installing Kaveh');

        $this->call('vendor:publish', [
            '--provider' => 'Kaveh\\KavehServiceProvider',
            '--tag' => 'kaveh-config',
            '--force' => true,
        ]);

        $this->call('vendor:publish', [
            '--provider' => 'Kaveh\\KavehServiceProvider',
            '--tag' => 'kaveh-provider',
            '--force' => true,
        ]);

        $this->registerKavehServiceProvider();

        $role = $this->resolveRole();
        $env = ['KAVEH_ENABLED' => 'true', 'KAVEH_ROLE' => $role];

        if (in_array($role, ['client', 'both'], true)) {
            $env = array_merge($env, $this->configureClient($role));
        }

        if (in_array($role, ['server', 'both'], true)) {
            $env = array_merge($env, $this->configureServer());
        }

        $this->writeEnv($env);
        $this->rewritePublishedConfig($role, $env);

        if (! $this->option('no-migrate')) {
            $this->call('migrate');
        }

        if (in_array($role, ['server', 'both'], true) && $this->shouldSeedServer()) {
            $this->seedServerProject();
        }

        $this->newLine();
        $this->components->info('Kaveh is ready.');
        $this->line('  Role: <fg=cyan>'.$role.'</>');
        if (isset($env['KAVEH_MODE'])) {
            $this->line('  Mode: <fg=cyan>'.$env['KAVEH_MODE'].'</>');
        }
        if (($env['KAVEH_SERVER_ENABLED'] ?? 'false') === 'true') {
            $prefix = trim((string) ($env['KAVEH_PATH'] ?? $env['KAVEH_PATH_PREFIX'] ?? 'kaveh'), '/');
            $this->line('  Dashboard: <fg=cyan>/'.$prefix.'/login</>');
            $this->line('  Ingest: <fg=cyan>/api/v1/ingest</>');
            $this->line('  Gate: edit <fg=gray>App\\Providers\\KavehServiceProvider</> → viewKaveh (like Telescope/Pulse)');
        }
        $this->newLine();
        $this->line('Config published to <fg=gray>config/kaveh.php</> and <fg=gray>.env</>.');

        return self::SUCCESS;
    }

    private function resolveRole(): string
    {
        $role = $this->option('role');
        if (! $role && ! $this->option('non-interactive') && $this->input->isInteractive()) {
            $role = $this->choice(
                'What are you installing?',
                [
                    'client' => 'Client — track events on this app (local and/or ship remote)',
                    'server' => 'Server — ingest API + dashboard + alerts + RAG',
                    'both' => 'Both — client and server on this app',
                ],
                'client'
            );
        }

        $role = $role ?: 'client';
        if (! in_array($role, ['client', 'server', 'both'], true)) {
            $this->error('Invalid --role. Use client, server, or both.');
            exit(1);
        }

        return $role;
    }

    /**
     * @return array<string, string>
     */
    private function configureClient(string $role): array
    {
        $interactive = ! $this->option('non-interactive') && $this->input->isInteractive();

        $mode = $this->option('mode');
        if (! $mode && $interactive) {
            $mode = $this->choice(
                'Client storage mode',
                [
                    'remote' => 'Remote only (recommended for production — no local disk fill)',
                    'local' => 'Local only (Telescope-like on this app)',
                    'both' => 'Both local store and remote ship',
                ],
                'remote'
            );
        }
        $mode = $mode ?: 'remote';

        $env = [
            'KAVEH_MODE' => $mode,
            'KAVEH_WATCH_EXCEPTIONS' => 'true',
            'KAVEH_WATCH_REQUESTS' => 'true',
            'KAVEH_WATCH_QUERIES' => 'true',
            'KAVEH_WATCH_JOBS' => 'true',
            'KAVEH_WATCH_LOGS' => 'false',
            'KAVEH_USE_QUEUE' => 'true',
        ];

        if (in_array($mode, ['remote', 'both'], true)) {
            $serverUrl = $this->option('server-url');
            if (! $serverUrl && $interactive) {
                $serverUrl = $this->ask('Kaveh server URL', $role === 'both' ? config('app.url', 'http://localhost') : 'http://localhost:8080');
            }
            $env['KAVEH_SERVER_URL'] = $serverUrl ?: 'http://localhost:8080';

            $apiKey = $this->option('api-key');
            if (! $apiKey && $interactive) {
                $apiKey = $this->ask('Project API key (leave empty to set later)', '');
            }
            if ($apiKey) {
                $env['KAVEH_API_KEY'] = $apiKey;
            }
        }

        if ($interactive && $this->confirm('Customize watchers?', false)) {
            $env['KAVEH_WATCH_EXCEPTIONS'] = $this->confirm('Watch exceptions?', true) ? 'true' : 'false';
            $env['KAVEH_WATCH_REQUESTS'] = $this->confirm('Watch requests?', true) ? 'true' : 'false';
            $env['KAVEH_WATCH_QUERIES'] = $this->confirm('Watch slow queries?', true) ? 'true' : 'false';
            $env['KAVEH_WATCH_JOBS'] = $this->confirm('Watch jobs?', true) ? 'true' : 'false';
            $env['KAVEH_WATCH_LOGS'] = $this->confirm('Watch all logs? (noisy)', false) ? 'true' : 'false';
        }

        return $env;
    }

    /**
     * @return array<string, string>
     */
    private function configureServer(): array
    {
        $interactive = ! $this->option('non-interactive') && $this->input->isInteractive();
        $prefix = 'kaveh';
        if ($interactive) {
            $prefix = $this->ask('Dashboard path prefix', 'kaveh') ?: 'kaveh';
        }

        $path = trim($prefix, '/');

        return [
            'KAVEH_SERVER_ENABLED' => 'true',
            'KAVEH_PATH' => $path,
            'KAVEH_PATH_PREFIX' => $path,
        ];
    }

    private function shouldSeedServer(): bool
    {
        if ($this->option('non-interactive')) {
            return false;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->confirm('Create a demo organization, project, and API key now?', true);
    }

    private function seedServerProject(): void
    {
        $userModel = config('kaveh.server.user_model', \App\Models\User::class);
        if (! class_exists($userModel)) {
            $this->warn('User model not found; skip seeding. Register at /kaveh/register instead.');

            return;
        }

        $email = $this->ask('Admin email', 'admin@kaveh.test');
        $password = $this->secret('Admin password') ?: 'password';
        $orgName = $this->ask('Organization name', 'Demo');

        $user = $userModel::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Kaveh Admin',
                'password' => bcrypt($password),
            ]
        );

        $org = Organization::query()->firstOrCreate(
            ['slug' => Str::slug($orgName)],
            ['name' => $orgName]
        );
        $org->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

        $project = Project::query()->firstOrCreate(
            [
                'organization_id' => $org->id,
                'slug' => 'default',
            ],
            ['name' => 'Default']
        );

        $issued = ApiKey::issue($project, 'install');
        $this->writeEnv(['KAVEH_API_KEY' => $issued['plain']]);

        $this->newLine();
        $this->components->warn('Copy this API key now — it will not be shown again:');
        $this->line('  <fg=green>'.$issued['plain'].'</>');
        $this->line('  Login: <fg=cyan>'.$email.'</> / (the password you entered)');
    }

    /**
     * @param  array<string, string>  $values
     */
    private function writeEnv(array $values): void
    {
        $path = base_path('.env');
        if (! File::exists($path)) {
            $this->warn('.env not found — skipping env write. Set these manually:');
            foreach ($values as $key => $value) {
                $this->line("  {$key}={$value}");
            }

            return;
        }

        $content = File::get($path);
        foreach ($values as $key => $value) {
            $line = $key.'='.$this->escapeEnv((string) $value);
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", $line, $content) ?? $content;
            } else {
                $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
            }
        }
        File::put($path, $content);
        $this->components->twoColumnDetail('.env', 'updated');
    }

    private function escapeEnv(string $value): string
    {
        if ($value === '' || preg_match('/\s|#|"|\'/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $env
     */
    private function rewritePublishedConfig(string $role, array $env): void
    {
        $path = config_path('kaveh.php');
        if (! File::exists($path)) {
            return;
        }

        // Ensure role/server.enabled defaults match what install chose even before config cache rebuild.
        $this->laravel['config']->set('kaveh.role', $role);
        $this->laravel['config']->set('kaveh.server.enabled', ($env['KAVEH_SERVER_ENABLED'] ?? 'false') === 'true');
        if (isset($env['KAVEH_MODE'])) {
            $this->laravel['config']->set('kaveh.mode', $env['KAVEH_MODE']);
        }
        if (isset($env['KAVEH_SERVER_URL'])) {
            $this->laravel['config']->set('kaveh.server_url', $env['KAVEH_SERVER_URL']);
        }
        if (isset($env['KAVEH_API_KEY'])) {
            $this->laravel['config']->set('kaveh.api_key', $env['KAVEH_API_KEY']);
        }
        if (isset($env['KAVEH_PATH'])) {
            $this->laravel['config']->set('kaveh.path', $env['KAVEH_PATH']);
        }
        if (isset($env['KAVEH_PATH_PREFIX'])) {
            $this->laravel['config']->set('kaveh.path', $env['KAVEH_PATH_PREFIX']);
            $this->laravel['config']->set('kaveh.server.path_prefix', $env['KAVEH_PATH_PREFIX']);
        }
    }

    /**
     * Register App\\Providers\\KavehServiceProvider (Telescope install pattern).
     *
     * @see https://github.com/laravel/telescope
     */
    private function registerKavehServiceProvider(): void
    {
        if (method_exists(ServiceProvider::class, 'addProviderToBootstrapFile')
            && ServiceProvider::addProviderToBootstrapFile(\App\Providers\KavehServiceProvider::class)) {
            $this->components->twoColumnDetail('Provider', 'App\\Providers\\KavehServiceProvider');

            return;
        }

        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());
        $appConfigPath = config_path('app.php');
        if (! File::exists($appConfigPath)) {
            return;
        }

        $appConfig = File::get($appConfigPath);
        $provider = $namespace.'\\Providers\\KavehServiceProvider::class';

        if (Str::contains($appConfig, $provider)) {
            return;
        }

        if (Str::contains($appConfig, $namespace.'\\Providers\\AppServiceProvider::class')) {
            $appConfig = str_replace(
                $namespace.'\\Providers\\AppServiceProvider::class,',
                $namespace.'\\Providers\\AppServiceProvider::class,'.PHP_EOL.'        '.$provider.',',
                $appConfig
            );
            File::put($appConfigPath, $appConfig);
            $this->components->twoColumnDetail('Provider', $provider);
        }
    }
}
