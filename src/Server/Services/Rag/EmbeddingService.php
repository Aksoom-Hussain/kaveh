<?php

namespace Kaveh\Server\Services\Rag;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    /**
     * @return list<float>|null
     */
    public function embed(string $text): ?array
    {
        $apiKey = (string) config('kaveh.openai_api_key', '');
        if ($apiKey === '') {
            // Deterministic local pseudo-embedding so RAG still works offline.
            return $this->localEmbed($text);
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('kaveh.openai_base_url'), '/'))
                ->withToken($apiKey)
                ->timeout(30)
                ->post('/embeddings', [
                    'model' => config('kaveh.embedding_model'),
                    'input' => mb_substr($text, 0, 8000),
                ]);

            if (! $response->successful()) {
                Log::warning('Kaveh embedding API failed', ['body' => $response->body()]);

                return $this->localEmbed($text);
            }

            /** @var list<float> $vector */
            $vector = $response->json('data.0.embedding') ?? [];

            return $vector !== [] ? $vector : $this->localEmbed($text);
        } catch (\Throwable $e) {
            Log::warning('Kaveh embedding exception', ['message' => $e->getMessage()]);

            return $this->localEmbed($text);
        }
    }

    /**
     * Simple bag-of-hashes embedding (384 dims) for offline / no-key mode.
     *
     * @return list<float>
     */
    public function localEmbed(string $text, int $dims = 384): array
    {
        $vector = array_fill(0, $dims, 0.0);
        $tokens = preg_split('/\W+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            $hash = crc32($token);
            $idx = $hash % $dims;
            $vector[$idx] += 1.0;
        }

        $norm = sqrt(array_sum(array_map(static fn ($v) => $v * $v, $vector))) ?: 1.0;

        return array_map(static fn ($v) => $v / $norm, $vector);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        $denom = sqrt($na) * sqrt($nb);

        return $denom > 0 ? $dot / $denom : 0.0;
    }
}
