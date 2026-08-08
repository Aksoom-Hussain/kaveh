<?php

namespace Kaveh\Server\Services\Rag;

use Kaveh\Server\Models\EventEmbedding;
use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EventRagService
{
    public function __construct(private readonly EmbeddingService $embeddings) {}

    /**
     * @return array{answer: string, citations: list<array<string, mixed>>}
     */
    public function ask(Project $project, string $question): array
    {
        $hits = $this->retrieve($project, $question, 8);
        $context = $hits->map(function (EventRecord $event) {
            return sprintf(
                "[#%d uuid=%s type=%s name=%s level=%s at=%s]\n%s",
                $event->id,
                $event->uuid,
                $event->type,
                $event->name,
                $event->level,
                optional($event->occurred_at)?->toIso8601String(),
                mb_substr(json_encode($event->context) ?: '', 0, 1500)
            );
        })->implode("\n\n");

        $citations = $hits->map(fn (EventRecord $e) => [
            'id' => $e->id,
            'uuid' => $e->uuid,
            'type' => $e->type,
            'name' => $e->name,
            'level' => $e->level,
            'occurred_at' => optional($e->occurred_at)?->toIso8601String(),
        ])->values()->all();

        $answer = $this->generateAnswer($question, $context);

        return ['answer' => $answer, 'citations' => $citations];
    }

    /**
     * @return Collection<int, EventRecord>
     */
    public function retrieve(Project $project, string $question, int $limit = 8): Collection
    {
        $queryVector = $this->embeddings->embed($question) ?? [];
        $rows = EventEmbedding::query()
            ->where('project_id', $project->id)
            ->with('event')
            ->latest('id')
            ->limit(200)
            ->get();

        $scored = $rows->map(function (EventEmbedding $row) use ($queryVector) {
            $score = is_array($row->embedding)
                ? $this->embeddings->cosine($queryVector, $row->embedding)
                : 0.0;

            return ['score' => $score, 'event' => $row->event];
        })
            ->filter(fn ($row) => $row['event'] instanceof EventRecord)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('event');

        if ($scored->isEmpty()) {
            return EventRecord::query()
                ->where('project_id', $project->id)
                ->latest('occurred_at')
                ->limit($limit)
                ->get();
        }

        return $scored->values();
    }

    private function generateAnswer(string $question, string $context): string
    {
        $apiKey = (string) config('kaveh.openai_api_key', '');
        if ($apiKey === '' || $context === '') {
            return $this->fallbackAnswer($question, $context);
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('kaveh.openai_base_url'), '/'))
                ->withToken($apiKey)
                ->timeout(60)
                ->post('/chat/completions', [
                    'model' => config('kaveh.chat_model'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are Kaveh, an observability assistant. Answer using only the provided event context. Cite event ids like #123. If unsure, say so.',
                        ],
                        [
                            'role' => 'user',
                            'content' => "Question: {$question}\n\nEvents:\n{$context}",
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return (string) ($response->json('choices.0.message.content') ?? $this->fallbackAnswer($question, $context));
            }
        } catch (\Throwable $e) {
            Log::warning('Kaveh chat failed', ['message' => $e->getMessage()]);
        }

        return $this->fallbackAnswer($question, $context);
    }

    private function fallbackAnswer(string $question, string $context): string
    {
        if (trim($context) === '') {
            return 'No matching events found for: '.$question;
        }

        $lines = array_slice(explode("\n\n", $context), 0, 5);

        return "Top matching events for \"{$question}\":\n\n".implode("\n\n", $lines);
    }
}
