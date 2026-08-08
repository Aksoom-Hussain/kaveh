<?php

namespace Kaveh\Server\Jobs;

use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Services\Rag\EmbeddingService;
use Kaveh\Server\Models\EventEmbedding;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EmbedEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $eventId) {}

    public function handle(EmbeddingService $embeddings): void
    {
        $event = EventRecord::query()->find($this->eventId);
        if (! $event) {
            return;
        }

        $content = $event->searchableText();
        $vector = $embeddings->embed($content);

        EventEmbedding::query()->updateOrCreate(
            ['event_id' => $event->id],
            [
                'project_id' => $event->project_id,
                'content' => $content,
                'embedding' => $vector,
            ]
        );
    }
}
