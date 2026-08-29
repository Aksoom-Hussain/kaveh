<?php

namespace Kaveh\Server\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Project;

class PruneEventsCommand extends Command
{
    /** Rows deleted per statement — small enough not to blow up memory or lock tables. */
    private const CHUNK = 1000;

    /** Safety stop so a runaway loop cannot spin forever: CHUNK * this per project per phase. */
    private const MAX_CHUNKS = 1000;

    protected $signature = 'kaveh:prune-events';

    protected $description = 'Prune events past per-project retention and enforce max_events quotas';

    public function handle(): int
    {
        $deleted = 0;

        Project::query()->each(function (Project $project) use (&$deleted) {
            $cutoff = now()->subDays($project->retention_days);

            // Both phases drain in chunks: a single capped pass leaves a large
            // backlog permanently behind on a daily schedule.
            $deleted += $this->drain(fn () => EventRecord::query()
                ->where('project_id', $project->id)
                ->where('occurred_at', '<', $cutoff)
                ->take(self::CHUNK)
                ->pluck('id'));

            $deleted += $this->drain(fn () => EventRecord::query()
                ->where('project_id', $project->id)
                ->orderByDesc('occurred_at')
                ->skip($project->max_events)
                ->take(self::CHUNK)
                ->pluck('id'));
        });

        $this->info("Pruned {$deleted} events.");

        return self::SUCCESS;
    }

    /**
     * @param  callable(): Collection<int, mixed>  $next
     */
    private function drain(callable $next): int
    {
        $deleted = 0;

        for ($i = 0; $i < self::MAX_CHUNKS; $i++) {
            $ids = $next();

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += EventRecord::query()->whereIn('id', $ids)->delete();
        }

        return $deleted;
    }
}
