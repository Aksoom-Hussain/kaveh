<?php

declare(strict_types=1);

namespace Kaveh\Server\Http\Controllers;

use Kaveh\Server\Models\EventRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pulse-style metrics JSON for the Kaveh dashboard charts.
 *
 * Reads Laravel Pulse aggregates when present, plus Kaveh event timeseries.
 *
 * Pulse stores:
 * - cpu as percent average
 * - memory as kilobytes average (we expose MB)
 * - traffic/queue/cache as counts (summed across keys per bucket)
 */
class MetricsController extends Controller
{
    public function pulse(Request $request): JsonResponse
    {
        if (! Schema::hasTable('pulse_aggregates')) {
            return response()->json([
                'available' => false,
                'message' => 'System metrics are not available yet.',
                'series' => (object) [],
            ]);
        }

        $period = (int) $request->integer('period', 60);
        if (! in_array($period, [60, 360, 1440, 10080], true)) {
            $period = 60;
        }

        $rangeSeconds = (int) $request->integer('range', 3600);
        $rangeSeconds = max(300, min($rangeSeconds, 86400 * 7));
        $since = now()->getTimestamp() - $rangeSeconds;

        // Gauges: one avg row per key — take max across servers (or avg of avgs).
        $cpu = $this->gaugeSeries('cpu', $period, $since);
        $memoryKb = $this->gaugeSeries('memory', $period, $since);
        $memoryMb = array_map(static function (array $p): array {
            return ['t' => $p['t'], 'v' => round($p['v'] / 1024, 2)];
        }, $memoryKb);

        // Counters: sum value across all keys for each bucket (Pulse count aggregates).
        $countTypes = [
            'exception',
            'user_request',
            'slow_request',
            'slow_query',
            'queued',
            'processing',
            'processed',
            'cache_hit',
            'cache_miss',
        ];

        $series = [
            'cpu' => $cpu,
            'memory' => $memoryMb,
            'memory_kb' => $memoryKb,
        ];

        foreach ($countTypes as $type) {
            $series[$type] = $this->countSeries($type, $period, $since);
        }

        return response()->json([
            'available' => true,
            'period' => $period,
            'range' => $rangeSeconds,
            'units' => [
                'cpu' => '%',
                'memory' => 'MB',
                'exception' => 'count',
                'user_request' => 'count',
                'queued' => 'count',
                'processing' => 'count',
                'processed' => 'count',
                'cache_hit' => 'count',
                'cache_miss' => 'count',
            ],
            'series' => $series,
            'latest' => [
                'cpu' => $this->latestValue($cpu),
                'memory_mb' => $this->latestValue($memoryMb),
                'requests' => $this->sumWindow($series['user_request'] ?? []),
                'exceptions' => $this->sumWindow($series['exception'] ?? []),
            ],
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $hours = max(1, min((int) $request->integer('hours', 24), 168));
        $since = now()->subHours($hours);
        $projectId = $request->integer('project_id') ?: null;

        $driver = DB::connection()->getDriverName();
        $bucketExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d %H:00:00', occurred_at)",
            'pgsql' => "to_char(date_trunc('hour', occurred_at), 'YYYY-MM-DD HH24:00:00')",
            default => "DATE_FORMAT(occurred_at, '%Y-%m-%d %H:00:00')",
        };

        $query = EventRecord::query()
            ->selectRaw("{$bucketExpr} as bucket, type, COUNT(*) as aggregate, AVG(duration_ms) as avg_ms")
            ->where('occurred_at', '>=', $since)
            ->groupByRaw('bucket, type')
            ->orderBy('bucket');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $rows = $query->get();

        $series = [];
        $avgDuration = [];
        foreach ($rows as $row) {
            $type = (string) $row->type;
            $iso = $this->bucketToIso((string) $row->bucket);
            $series[$type] ??= [];
            $series[$type][] = [
                't' => $iso,
                'v' => (int) $row->aggregate,
            ];
            if ($row->avg_ms !== null) {
                $avgDuration[$type] ??= [];
                $avgDuration[$type][] = [
                    't' => $iso,
                    'v' => round((float) $row->avg_ms, 2),
                ];
            }
        }

        $totals = EventRecord::query()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->where('occurred_at', '>=', $since)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return response()->json([
            'hours' => $hours,
            'totals' => $totals,
            'series' => $series,
            'avg_duration_ms' => $avgDuration,
        ]);
    }

    /**
     * Average gauge per bucket (cpu / memory). Prefer avg aggregate only.
     * If multiple keys (servers), average their averages.
     *
     * @return list<array{t: string, v: float}>
     */
    private function gaugeSeries(string $type, int $period, int $since): array
    {
        $rows = DB::table('pulse_aggregates')
            ->selectRaw('bucket, AVG(value) as value')
            ->where('period', $period)
            ->where('bucket', '>=', $since)
            ->where('type', $type)
            ->where('aggregate', 'avg')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return $rows->map(static fn ($row): array => [
            't' => gmdate('c', (int) $row->bucket),
            'v' => round((float) $row->value, 2),
        ])->values()->all();
    }

    /**
     * Sum count aggregates across keys for each bucket.
     *
     * @return list<array{t: string, v: float}>
     */
    private function countSeries(string $type, int $period, int $since): array
    {
        $rows = DB::table('pulse_aggregates')
            ->selectRaw('bucket, SUM(value) as value')
            ->where('period', $period)
            ->where('bucket', '>=', $since)
            ->where('type', $type)
            ->where('aggregate', 'count')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return $rows->map(static fn ($row): array => [
            't' => gmdate('c', (int) $row->bucket),
            'v' => round((float) $row->value, 2),
        ])->values()->all();
    }

    /**
     * @param  list<array{t: string, v: float}>  $points
     */
    private function latestValue(array $points): ?float
    {
        if ($points === []) {
            return null;
        }

        return (float) $points[array_key_last($points)]['v'];
    }

    /**
     * @param  list<array{t: string, v: float}>  $points
     */
    private function sumWindow(array $points): float
    {
        return round(array_sum(array_column($points, 'v')), 2);
    }

    private function bucketToIso(string $bucket): string
    {
        $ts = strtotime($bucket.' UTC');

        return $ts ? gmdate('c', $ts) : $bucket;
    }
}
