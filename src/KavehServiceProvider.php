<?php

declare(strict_types=1);

namespace Kaveh;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kaveh\Console\FlushCommand;
use Kaveh\Console\InstallCommand;
use Kaveh\Console\PruneCommand;
use Kaveh\Http\Middleware\Authenticate;
use Kaveh\Http\Middleware\Authorize;
use Kaveh\Server\Console\Commands\EvaluateAlertsCommand;
use Kaveh\Server\Console\Commands\PruneEventsCommand;
use Kaveh\Server\Models\AlertRule;
use Kaveh\Server\Models\ApiKey;
use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Organization;
use Kaveh\Server\Models\Project;
use Kaveh\Storage\LocalEventStore;
use Kaveh\Support\Redactor;
use Kaveh\Transport\EventBuffer;
use Kaveh\Transport\RemoteTransport;
use Kaveh\Watchers\ExceptionWatcher;
use Kaveh\Watchers\JobWatcher;
use Kaveh\Watchers\LogWatcher;
use Kaveh\Watchers\QueryWatcher;
use Kaveh\Watchers\RequestWatcher;

/**
 * Package service provider — structured like Telescope / Pulse.
 *
 * @see https://github.com/laravel/telescope
 * @see https://github.com/laravel/pulse
 */
class KavehServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/kaveh.php', 'kaveh');

        $this->app->singleton(Redactor::class);
        $this->app->singleton(RemoteTransport::class);
        $this->app->singleton(LocalEventStore::class);
        $this->app->singleton(EventBuffer::class);
        $this->app->singleton(KavehManager::class);
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerPublishing();

        if (! config('kaveh.enabled')) {
            return;
        }

        Route::middlewareGroup('kaveh', config('kaveh.middleware', [
            'web',
            Authenticate::class,
            Authorize::class,
        ]));

        $role = (string) config('kaveh.role', 'client');
        $serverEnabled = (bool) config('kaveh.server.enabled')
            || in_array($role, ['server', 'both'], true);
        $clientEnabled = in_array($role, ['client', 'both'], true)
            || (! $serverEnabled && $role !== 'server');

        if ($clientEnabled && $role !== 'server') {
            $this->registerClientMigrations();
            $this->registerWatchers();
            $this->registerTerminatingFlush();
        }

        if ($serverEnabled) {
            $this->registerAuthorization();
            $this->registerResources();
            $this->registerServerMigrations();
            $this->registerRoutes();
            $this->registerIngestRateLimiter();
            $this->registerRouteBindings();
            $this->registerUserRelations();
        }
    }

    /**
     * Register Telescope-style watchers from config.
     */
    protected function registerWatchers(): void
    {
        $watchers = (array) config('kaveh.watchers', []);

        // Backward-compatible boolean map: ['exceptions' => true]
        if ($this->isLegacyWatcherConfig($watchers)) {
            $this->registerLegacyWatchers($watchers);

            return;
        }

        foreach ($watchers as $class => $options) {
            if (is_int($class) && is_string($options)) {
                $class = $options;
                $options = true;
            }

            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            $enabled = is_array($options)
                ? (bool) ($options['enabled'] ?? true)
                : (bool) $options;

            if (! $enabled) {
                continue;
            }

            if (is_array($options) && isset($options['slow'])) {
                config(['kaveh.slow_query_ms' => (float) $options['slow']]);
            }

            $watcher = $this->app->make($class);
            if (method_exists($watcher, 'register')) {
                $watcher->register();
            }
        }
    }

    /**
     * @param  array<mixed>  $watchers
     */
    protected function isLegacyWatcherConfig(array $watchers): bool
    {
        $keys = array_keys($watchers);

        return $keys !== [] && ! str_contains((string) $keys[0], '\\');
    }

    /**
     * @param  array<string, mixed>  $watchers
     */
    protected function registerLegacyWatchers(array $watchers): void
    {
        $map = [
            'exceptions' => ExceptionWatcher::class,
            'requests' => RequestWatcher::class,
            'queries' => QueryWatcher::class,
            'jobs' => JobWatcher::class,
            'logs' => LogWatcher::class,
        ];

        foreach ($map as $key => $class) {
            if ($watchers[$key] ?? false) {
                $this->app->make($class)->register();
            }
        }
    }


    /**
     * Flush the client event buffer at end of each request/command.
     */
    protected function registerTerminatingFlush(): void
    {
        $this->app->terminating(function () {
            try {
                $this->app->make(KavehManager::class)->flush();
            } catch (\Throwable) {
                //
            }
        });
    }

    /**
     * Register Pulse/Telescope-style authorization gate default.
     */
    protected function registerAuthorization(): void
    {
        $this->callAfterResolving(Gate::class, function (Gate $gate, Application $app): void {
            // Same default as Pulse viewPulse — open in local only.
            // Publish + register App\Providers\KavehServiceProvider to customize.
            $gate->define('viewKaveh', fn ($user = null) => $app->environment('local') || $user !== null);
        });
    }

    /**
     * Register package routes (dashboard + ingest).
     */
    protected function registerRoutes(): void
    {
        if (! config('kaveh.server.registers_routes', true)) {
            return;
        }

        $path = trim((string) config('kaveh.path', config('kaveh.server.path_prefix', 'kaveh')), '/');

        Route::group([
            'domain' => config('kaveh.domain'),
            'prefix' => $path,
            'middleware' => ['web'],
            'as' => 'kaveh.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    /**
     * Register views.
     */
    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'kaveh');
    }

    protected function registerClientMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/client');
    }

    protected function registerServerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/server');
    }

    protected function registerIngestRateLimiter(): void
    {
        RateLimiter::for('kaveh-ingest', function (Request $request) {
            $key = $request->header('Authorization', $request->ip());

            return Limit::perMinute(120)->by(sha1((string) $key));
        });
    }

    protected function registerRouteBindings(): void
    {
        Route::model('event', EventRecord::class);
        Route::model('alert', AlertRule::class);
        Route::model('project', Project::class);
        Route::model('apiKey', ApiKey::class);
    }

    protected function registerUserRelations(): void
    {
        $userModel = config('kaveh.server.user_model', \App\Models\User::class);
        if (class_exists($userModel) && method_exists($userModel, 'resolveRelationUsing')) {
            $userModel::resolveRelationUsing('organizations', function ($user) {
                return $user->belongsToMany(Organization::class)->withPivot('role')->withTimestamps();
            });
        }
    }

    /**
     * Publish config, migrations, views, and app provider stub.
     */
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/kaveh.php' => config_path('kaveh.php'),
        ], ['kaveh', 'kaveh-config']);

        $publishesMigrations = method_exists($this, 'publishesMigrations')
            ? 'publishesMigrations'
            : 'publishes';

        $this->{$publishesMigrations}([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['kaveh', 'kaveh-migrations']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/kaveh'),
        ], ['kaveh', 'kaveh-views']);

        $this->publishes([
            __DIR__.'/../stubs/KavehServiceProvider.stub' => app_path('Providers/KavehServiceProvider.php'),
        ], ['kaveh', 'kaveh-provider']);
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            FlushCommand::class,
            PruneCommand::class,
            PruneEventsCommand::class,
            EvaluateAlertsCommand::class,
        ]);
    }
}
