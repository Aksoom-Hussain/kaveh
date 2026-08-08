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
        Silencer::run(function () use ($transport): void {
            $envelopes = [];
            foreach ($this->events as $payload) {
                try {
                    $envelopes[] = EventEnvelope::fromArray($payload);
                } catch (\Throwable $e) {
                    Silencer::report($e);
                }
            }

            if ($envelopes === []) {
                return;
            }

            $transport->send(new IngestBatch($envelopes, gmdate('c')));
        });
    }
}
