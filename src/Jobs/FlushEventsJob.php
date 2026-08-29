<?php

declare(strict_types=1);

namespace Kaveh\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\IngestBatch;
use Kaveh\Support\Silencer;
use Kaveh\Transport\RemoteTransport;
use RuntimeException;

final class FlushEventsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 120, 600];

    /**
     * @param  list<array<string, mixed>>  $events
     */
    public function __construct(
        public readonly array $events,
    ) {}

    public function handle(RemoteTransport $transport): void
    {
        $envelopes = [];
        foreach ($this->events as $payload) {
            try {
                $envelopes[] = EventEnvelope::fromArray($payload);
            } catch (\Throwable $e) {
                // A single unparseable payload must not cost the rest of the batch.
                Silencer::report($e);
            }
        }

        if ($envelopes === []) {
            return;
        }

        if (! $transport->send(new IngestBatch($envelopes, gmdate('c')))) {
            // Queue worker, not the host request path: throwing is what the
            // retry/backoff machinery is for, and failed_jobs is the dead letter.
            throw new RuntimeException('Kaveh: ingest send failed for '.count($envelopes).' events.');
        }
    }
}
