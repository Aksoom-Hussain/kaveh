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
    /**
     * Hard ceiling on events held in memory awaiting a successful ship.
     * A failed send puts its batch back on the front of the buffer; past this
     * many events the oldest are dropped, because an unbounded retry buffer in
     * a client library is its own outage. They are also spilled to the local
     * store (best effort) before being dropped, so the data has a second home.
     * Override with `kaveh.batch.max_pending`.
     */
    public const MAX_PENDING_EVENTS = 1000;

    /**
     * Seconds to wait after a failed send before the size-threshold auto-flush
     * fires again. Override with `kaveh.batch.retry_cooldown`.
     */
    public const RETRY_COOLDOWN_SECONDS = 30;

    /** @var list<EventEnvelope> */
    private array $events = [];

    private int $retryAfter = 0;

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
                if (count($this->events) >= $size && time() >= $this->retryAfter) {
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

                    // Handed off: the job (and the queue's own retries) own it now.
                    return;
                } catch (\Throwable $e) {
                    Silencer::report($e);
                    // Fallback: try sync send so events are not lost silently without attempt.
                }
            }

            if (! $this->remote->send(new IngestBatch($batch, gmdate('c')))) {
                $this->retain($batch);
            }
        });
    }

    /**
     * A send failed: keep the batch instead of dropping it. Events go back on the
     * front of the buffer for the next flush, spilled to the local store first when
     * they are not already there, and capped at kaveh.batch.max_pending (oldest out).
     *
     * @param  list<EventEnvelope>  $batch
     */
    private function retain(array $batch): void
    {
        $this->retryAfter = time() + (int) config('kaveh.batch.retry_cooldown', self::RETRY_COOLDOWN_SECONDS);

        if (! in_array((string) config('kaveh.mode', 'remote'), ['local', 'both'], true)) {
            // Remote-only mode never wrote these locally; give them a durable home.
            foreach ($batch as $event) {
                $this->local->store($event); // Silencer-wrapped internally; best effort.
            }
        }

        $this->events = array_merge($batch, $this->events);

        $max = (int) config('kaveh.batch.max_pending', self::MAX_PENDING_EVENTS);
        if (count($this->events) > $max) {
            $this->events = array_slice($this->events, -$max);
        }
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
