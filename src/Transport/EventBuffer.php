<?php

declare(strict_types=1);

namespace Kaveh\Transport;

use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\IngestBatch;
use Kaveh\Jobs\FlushEventsJob;
use Kaveh\Storage\LocalEventStore;

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
        $mode = (string) config('kaveh.mode', 'remote');

        if (in_array($mode, ['local', 'both'], true)) {
            $this->local->store($event);
        }

        if (in_array($mode, ['remote', 'both'], true)) {
            $this->events[] = $event;
            $size = (int) config('kaveh.batch.size', 50);
            if (count($this->events) >= $size) {
                $this->flush();
            }
        }
    }

    public function flush(): void
    {
        if ($this->events === []) {
            return;
        }

        $batch = $this->events;
        $this->events = [];

        if (config('kaveh.batch.use_queue')) {
            FlushEventsJob::dispatch(
                array_map(static fn (EventEnvelope $e): array => $e->toArray(), $batch)
            )->onQueue((string) config('kaveh.batch.queue', 'default'));

            return;
        }

        $this->remote->send(new IngestBatch($batch, gmdate('c')));
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
