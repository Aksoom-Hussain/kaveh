<?php

declare(strict_types=1);

namespace Kaveh\Console;

use Illuminate\Console\Command;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\Contracts\IngestBatch;
use Kaveh\Storage\LocalEventStore;
use Kaveh\Support\Silencer;
use Kaveh\Support\SystemStats;
use Kaveh\Transport\RemoteTransport;

/**
 * Pulse-like long-running worker that samples CPU / memory / disk and ships to Kaveh.
 *
 * Run under supervisord / systemd (same idea as `php artisan pulse:check`):
 *
 *   php artisan kaveh:check
 *
 * Or once via scheduler:
 *
 *   php artisan kaveh:check --once
 */
final class CheckCommand extends Command
{
    protected $signature = 'kaveh:check
        {--once : Sample once and exit}
        {--interval= : Seconds between samples (default from config)}';

    protected $description = 'Sample host CPU, memory, and disk usage and ship to the Kaveh server';

    public function handle(RemoteTransport $remote, LocalEventStore $local): int
    {
        if (! config('kaveh.enabled')) {
            $this->warn('Kaveh is disabled (KAVEH_ENABLED=false).');

            return self::FAILURE;
        }

        $role = (string) config('kaveh.role', 'client');
        if ($role === 'server') {
            $this->warn('KAVEH_ROLE=server does not emit client system stats. Use client or both on the app host.');

            return self::FAILURE;
        }

        if (! config('kaveh.check.enabled', true)) {
            $this->warn('System check is disabled (KAVEH_CHECK_ENABLED=false).');

            return self::FAILURE;
        }

        $interval = max(5, (int) ($this->option('interval') ?: config('kaveh.check.interval', 15)));
        $once = (bool) $this->option('once');

        $this->info($once
            ? 'Sampling system stats once…'
            : "Sampling system stats every {$interval}s (Ctrl+C to stop).");

        do {
            Silencer::run(function () use ($remote, $local): void {
                $this->shipSample($remote, $local);
            });

            if ($once) {
                break;
            }

            sleep($interval);
        } while (true);

        return self::SUCCESS;
    }

    private function shipSample(RemoteTransport $remote, LocalEventStore $local): void
    {
        $stats = SystemStats::collect();
        $hostname = gethostname() ?: 'unknown';

        $envelope = new EventEnvelope(
            id: EventEnvelope::generateId(),
            type: EventType::System,
            name: 'system.stats',
            timestamp: gmdate('c'),
            environment: (string) config('kaveh.environment', config('app.env', 'production')),
            hostname: $hostname,
            context: $stats,
            tags: ['system', 'kaveh-check'],
            level: 'info',
        );

        $mode = (string) config('kaveh.mode', 'remote');

        if (in_array($mode, ['local', 'both'], true)) {
            try {
                $local->store($envelope);
            } catch (\Throwable $e) {
                Silencer::report($e);
            }
        }

        if (in_array($mode, ['remote', 'both'], true)) {
            // Sync ship — this worker should not depend on queue:work (Pulse-style).
            $remote->send(new IngestBatch([$envelope], gmdate('c')));
        }

        if ($this->output->isVerbose()) {
            $cpu = $stats['cpu_percent'] ?? 'n/a';
            $mem = $stats['memory_percent'] ?? 'n/a';
            $disk = $stats['disk_percent'] ?? 'n/a';
            $this->line(sprintf(
                '[%s] %s cpu=%s%% mem=%s%% disk=%s%%',
                gmdate('H:i:s'),
                $hostname,
                $cpu,
                $mem,
                $disk
            ));
        }
    }
}
