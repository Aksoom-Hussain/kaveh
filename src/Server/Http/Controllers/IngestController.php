<?php

namespace Kaveh\Server\Http\Controllers;

use Kaveh\Server\Jobs\EmbedEventJob;
use Kaveh\Server\Models\EventRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\IngestBatch;

class IngestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $project = $request->attributes->get('kaveh_project');
        $raw = $this->decodeBody($request);

        if (! is_array($raw) || ! isset($raw['events']) || ! is_array($raw['events'])) {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        if (count($raw['events']) > IngestBatch::MAX_EVENTS) {
            return response()->json(['message' => 'Too many events'], 422);
        }

        $accepted = 0;
        $duplicates = 0;
        $errors = [];

        DB::transaction(function () use ($raw, $project, &$accepted, &$duplicates, &$errors) {
            foreach ($raw['events'] as $index => $eventData) {
                if (! is_array($eventData)) {
                    $errors[] = "events.$index must be an object";
                    continue;
                }

                $validation = EventEnvelope::validate($eventData);
                if ($validation !== []) {
                    $errors[] = "events.$index: ".implode('; ', $validation);
                    continue;
                }

                $envelope = EventEnvelope::fromArray($eventData);

                $existing = EventRecord::query()
                    ->where('project_id', $project->id)
                    ->where('uuid', $envelope->id)
                    ->exists();

                if ($existing) {
                    $duplicates++;
                    continue;
                }

                $record = EventRecord::query()->create([
                    'project_id' => $project->id,
                    'uuid' => $envelope->id,
                    'type' => $envelope->type->value,
                    'name' => $envelope->name,
                    'level' => $envelope->level,
                    'environment' => $envelope->environment,
                    'hostname' => $envelope->hostname,
                    'trace_id' => $envelope->traceId,
                    'user' => $envelope->user,
                    'context' => $envelope->context,
                    'tags' => $envelope->tags,
                    'duration_ms' => $envelope->durationMs,
                    'occurred_at' => Carbon::parse($envelope->timestamp)->utc(),
                ]);

                EmbedEventJob::dispatch($record->id);
                $accepted++;
            }
        });

        return response()->json([
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ], $errors === [] ? 202 : 207);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeBody(Request $request): ?array
    {
        $content = $request->getContent();
        $encoding = strtolower((string) $request->header('Content-Encoding', ''));

        if ($encoding === 'gzip' && function_exists('gzdecode')) {
            $decoded = @gzdecode($content);
            if ($decoded === false) {
                return null;
            }
            $content = $decoded;
        }

        $json = json_decode($content, true);

        return is_array($json) ? $json : null;
    }
}
