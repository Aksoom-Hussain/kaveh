<?php

namespace Kaveh\Server\Http\Controllers;

use Kaveh\Server\Models\EventRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        // No outer transaction, and every event is isolated: the client has
        // already discarded the batch, so one bad event must not destroy 499
        // good ones. Rejects go out through the existing 207 partial path.
        foreach ($raw['events'] as $index => $eventData) {
            try {
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

                EventRecord::query()->create([
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

                $accepted++;
            } catch (\Throwable $e) {
                // Unparseable timestamp, over-length column, driver error — the
                // event is rejected, the batch continues.
                $errors[] = "events.$index: ".$e->getMessage();
            }
        }

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

        if ($encoding === 'gzip' && function_exists('inflate_init')) {
            $decoded = $this->inflate($content);
            if ($decoded === null) {
                return null;
            }

            $content = $decoded;
        }

        $json = json_decode($content, true);

        return is_array($json) ? $json : null;
    }

    /**
     * Stream-inflate a gzip body with a hard ceiling on the decompressed size,
     * so a small bomb can't expand into all the memory the box has. 413 past
     * the cap; null when the body simply isn't valid gzip.
     */
    private function inflate(string $content): ?string
    {
        $max = (int) config('kaveh.server.max_decompressed_bytes', 16 * 1024 * 1024);

        $context = @inflate_init(ZLIB_ENCODING_GZIP);
        if ($context === false) {
            return null;
        }

        $out = '';
        foreach (str_split($content, 8192) ?: [''] as $chunk) {
            $piece = @inflate_add($context, $chunk);
            if ($piece === false) {
                return null;
            }

            $out .= $piece;
            abort_if(strlen($out) > $max, 413, 'Payload too large');
        }

        $piece = @inflate_add($context, '', ZLIB_FINISH);
        if ($piece === false) {
            return null;
        }

        $out .= $piece;
        abort_if(strlen($out) > $max, 413, 'Payload too large');

        return $out;
    }
}
