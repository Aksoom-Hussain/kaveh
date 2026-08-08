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
use Kaveh\Transport\RemoteTransport;

final class FlushEventsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $events
     */
    public function __construct(
        public readonly array $events,
    ) {}

    public function handle(RemoteTransport $transport): void
    {
        $envelopes = array_map(
            static fn (array $e): EventEnvelope => EventEnvelope::fromArray($e),
            $this->events
        );

        $transport->send(new IngestBatch($envelopes, gmdate('c')));
    }
}
