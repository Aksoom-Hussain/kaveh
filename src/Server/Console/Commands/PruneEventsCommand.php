<?php

namespace Kaveh\Server\Console\Commands;

use Kaveh\Server\Models\EventEmbedding;
use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Project;
use Illuminate\Console\Command;

class PruneEventsCommand extends Command
{
    protected $signature = 'kaveh:prune-events';

    protected $description = 'Prune events past per-project retention and enforce max_events quotas';

    public function handle(): int
    {
        $deleted = 0;

        Project::query()->each(function (Project $project) use (&$deleted) {
            $cutoff = now()->subDays($project->retention_days);
            $ids = EventRecord::query()
                ->where('project_id', $project->id)
                ->where('occurred_at', '<', $cutoff)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                EventEmbedding::query()->whereIn('event_id', $ids)->delete();
                $deleted += EventRecord::query()->whereIn('id', $ids)->delete();
            }

            $overflow = EventRecord::query()
                ->where('project_id', $project->id)
                ->orderByDesc('occurred_at')
                ->skip($project->max_events)
                ->take(5000)
                ->pluck('id');

            if ($overflow->isNotEmpty()) {
                EventEmbedding::query()->whereIn('event_id', $overflow)->delete();
                $deleted += EventRecord::query()->whereIn('id', $overflow)->delete();
            }
        });

        $this->info("Pruned {$deleted} events.");

        return self::SUCCESS;
    }
}
