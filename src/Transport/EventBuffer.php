<?php

declare(strict_types=1);

namespace Kaveh\Transport;

use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\IngestBatch;
use Kaveh\Jobs\FlushEventsJob;
use Kaveh\Storage\LocalEventStore;
use Kaveh\Support\Silencer;

final class EventBuffer
{
    /** @var list<EventEnvelope> */
    private array $events = [];

    public function __construct(
        private readonly RemoteTransport $remote,
        private readonly LocalEventStore $local,
    ) {}

    public function push(EventEnvelope $event): void
    {
        Silencer::run(function () use ($event): void {
            $mode = (string) config('kaveh.mode', 'remote');

            if (in_array($mode, ['local', 'both'], true)) {
                try {
                    $this->local->store($event);
                } catch (\Throwable $e) {
                    Silencer::report($e);
                    // Continue — remote ship must not die because local DB failed.
                }
            }

            if (in_array($mode, ['remote', 'both'], true)) {
                $this->events[] = $event;
                $size = (int) config('kaveh.batch.size', 50);
                if (count($this->events) >= $size) {
                    $this->flush();
                }
            }
        });
    }

    public function flush(): void
    {
        Silencer::run(function (): void {
            if ($this->events === []) {
                return;
            }

            $batch = $this->events;
            $this->events = [];

            if (config('kaveh.batch.use_queue')) {
                try {
                    FlushEventsJob::dispatch(
                        array_map(static fn (EventEnvelope $e): array => $e->toArray(), $batch)
                    )->onQueue((string) config('kaveh.batch.queue', 'default'));
                } catch (\Throwable $e) {
                    Silencer::report($e);
                    // Fallback: try sync send so events are not lost silently without attempt.
                    $this->remote->send(new IngestBatch($batch, gmdate('c')));
                }

                return;
            }

            $this->remote->send(new IngestBatch($batch, gmdate('c')));
        });
    }

    public function __destruct()
    {
        try {
            $this->flush();
        } catch (\Throwable) {
            // Never break the host app on shutdown flush.
        }
    }
}
