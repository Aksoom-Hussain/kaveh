<?php

declare(strict_types=1);

namespace Kaveh\Storage;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kaveh\Contracts\EventEnvelope;

final class LocalEventStore
{
    public function store(EventEnvelope $event): void
    {
        $connection = config('kaveh.local.connection');
        $table = (string) config('kaveh.local.table', 'kaveh_events');

        $query = $connection ? DB::connection($connection)->table($table) : DB::table($table);

        $query->insert([
            'uuid' => $event->id,
            'type' => $event->type->value,
            'name' => $event->name,
            'level' => $event->level,
            'environment' => $event->environment,
            'hostname' => $event->hostname,
            'trace_id' => $event->traceId,
            'user' => $event->user ? json_encode($event->user) : null,
            'context' => json_encode($event->context),
            'tags' => json_encode($event->tags),
            'duration_ms' => $event->durationMs,
            'occurred_at' => Carbon::parse($event->timestamp)->utc(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function prune(): int
    {
        $hours = (int) config('kaveh.local.retention_hours', 48);
        $connection = config('kaveh.local.connection');
        $table = (string) config('kaveh.local.table', 'kaveh_events');
        $query = $connection ? DB::connection($connection)->table($table) : DB::table($table);

        return $query->where('created_at', '<', now()->subHours($hours))->delete();
    }
}
