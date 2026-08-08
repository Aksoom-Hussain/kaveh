<?php

declare(strict_types=1);

namespace Kaveh\Watchers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\KavehManager;
use Kaveh\Support\Redactor;

final class QueryWatcher
{
    public function __construct(
        private readonly KavehManager $kaveh,
        private readonly Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(QueryExecuted::class, function (QueryExecuted $event): void {
            $threshold = (float) config('kaveh.slow_query_ms', 100);
            if ($event->time < $threshold) {
                return;
            }

            $this->kaveh->record(new EventEnvelope(
                id: EventEnvelope::generateId(),
                type: EventType::Query,
                name: 'slow_query',
                timestamp: gmdate('c'),
                environment: (string) config('kaveh.environment'),
                hostname: gethostname() ?: 'unknown',
                context: $this->redactor->redact([
                    'sql' => $event->sql,
                    'bindings_count' => count($event->bindings),
                    'connection' => $event->connectionName,
                ]),
                tags: ['query', 'slow'],
                level: 'warning',
                durationMs: (float) $event->time,
            ));
        });
    }
}
