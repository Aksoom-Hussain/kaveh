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
            'source' => 'monitor_host',
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
        $rangeSeconds = (int) $request->integer('range', 0);
        if ($rangeSeconds <= 0) {
            $hours = max(1, min((int) $request->integer('hours', 24), 168));
            $rangeSeconds = $hours * 3600;
        }
        $rangeSeconds = max(300, min($rangeSeconds, 86400 * 7));
        $since = now()->subSeconds($rangeSeconds);
        $projectId = $request->integer('project_id') ?: null;

        // Finer buckets for short windows so project charts look like traffic, not one flat hour.
        $bucket = $rangeSeconds <= 3600 * 6 ? 'minute' : 'hour';
        $driver = DB::connection()->getDriverName();
        $bucketExpr = match ([$driver, $bucket]) {
            ['sqlite', 'minute'] => "strftime('%Y-%m-%d %H:%M:00', occurred_at)",
            ['sqlite', 'hour'] => "strftime('%Y-%m-%d %H:00:00', occurred_at)",
            ['pgsql', 'minute'] => "to_char(date_trunc('minute', occurred_at), 'YYYY-MM-DD HH24:MI:00')",
            ['pgsql', 'hour'] => "to_char(date_trunc('hour', occurred_at), 'YYYY-MM-DD HH24:00:00')",
            ['mysql', 'minute'], ['mariadb', 'minute'] => "DATE_FORMAT(occurred_at, '%Y-%m-%d %H:%i:00')",
            default => "DATE_FORMAT(occurred_at, '%Y-%m-%d %H:00:00')",
        };

        $base = EventRecord::query()
            ->where('occurred_at', '>=', $since)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId));

        $query = (clone $base)
            ->selectRaw("{$bucketExpr} as bucket, type, COUNT(*) as aggregate, AVG(duration_ms) as avg_ms")
            ->groupByRaw('bucket, type')
            ->orderBy('bucket');

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

        $totals = (clone $base)
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $failedJobs = (clone $base)->where('type', 'job')->where('level', 'error')->count();
        $avgRequestMs = (clone $base)->where('type', 'request')->avg('duration_ms');

        return response()->json([
            'available' => true,
            'source' => 'project',
            'project_id' => $projectId,
            'hours' => (int) ceil($rangeSeconds / 3600),
            'range' => $rangeSeconds,
            'bucket' => $bucket,
            'totals' => $totals,
            'latest' => [
                'requests' => (int) ($totals['request'] ?? 0),
                'exceptions' => (int) ($totals['exception'] ?? 0),
                'jobs' => (int) ($totals['job'] ?? 0),
                'failed_jobs' => $failedJobs,
                'queries' => (int) ($totals['query'] ?? 0),
                'custom' => (int) ($totals['custom'] ?? 0),
                'system' => (int) ($totals['system'] ?? 0),
                'avg_request_ms' => $avgRequestMs !== null ? round((float) $avgRequestMs, 1) : null,
            ],
            'series' => $series,
            'avg_duration_ms' => $avgDuration,
        ]);
    }

    /**
     * Remote app-host gauges shipped by `php artisan kaveh:check`.
     */
    public function system(Request $request): JsonResponse
    {
        $rangeSeconds = (int) $request->integer('range', 3600);
        $rangeSeconds = max(300, min($rangeSeconds, 86400 * 7));
        $since = now()->subSeconds($rangeSeconds);
        $projectId = $request->integer('project_id') ?: null;

        if (! $projectId) {
            return response()->json([
                'available' => false,
                'message' => 'Select a project to view remote host stats.',
                'hosts' => [],
                'series' => (object) [],
            ]);
        }

        $rows = EventRecord::query()
            ->where('project_id', $projectId)
            ->where('type', 'system')
            ->where('name', 'system.stats')
            ->where('occurred_at', '>=', $since)
            ->orderBy('occurred_at')
            ->get(['occurred_at', 'hostname', 'context']);

        if ($rows->isEmpty()) {
            return response()->json([
                'available' => false,
                'message' => 'No remote host stats yet. Run php artisan kaveh:check on your app servers.',
                'project_id' => $projectId,
                'range' => $rangeSeconds,
                'hosts' => [],
                'series' => (object) [],
                'latest' => (object) [],
            ]);
        }

        $byHost = [];
        foreach ($rows as $row) {
            $host = $row->hostname ?: 'unknown';
            $ctx = is_array($row->context) ? $row->context : [];
            $t = optional($row->occurred_at)?->utc()->toIso8601String() ?? gmdate('c');

            $byHost[$host]['cpu'][] = ['t' => $t, 'v' => isset($ctx['cpu_percent']) ? (float) $ctx['cpu_percent'] : null];
            $byHost[$host]['memory'][] = ['t' => $t, 'v' => isset($ctx['memory_percent']) ? (float) $ctx['memory_percent'] : null];
            $byHost[$host]['memory_mb'][] = ['t' => $t, 'v' => isset($ctx['memory_used_mb']) ? (float) $ctx['memory_used_mb'] : null];
            $byHost[$host]['disk'][] = ['t' => $t, 'v' => isset($ctx['disk_percent']) ? (float) $ctx['disk_percent'] : null];
            $byHost[$host]['disk_used_gb'][] = ['t' => $t, 'v' => isset($ctx['disk_used_gb']) ? (float) $ctx['disk_used_gb'] : null];
            $byHost[$host]['load_1'][] = ['t' => $t, 'v' => isset($ctx['load_1']) ? (float) $ctx['load_1'] : null];

            $byHost[$host]['latest'] = [
                'hostname' => $host,
                'at' => $t,
                'cpu_percent' => $ctx['cpu_percent'] ?? null,
                'memory_percent' => $ctx['memory_percent'] ?? null,
                'memory_used_mb' => $ctx['memory_used_mb'] ?? null,
                'memory_total_mb' => $ctx['memory_total_mb'] ?? null,
                'disk_percent' => $ctx['disk_percent'] ?? null,
                'disk_used_gb' => $ctx['disk_used_gb'] ?? null,
                'disk_total_gb' => $ctx['disk_total_gb'] ?? null,
                'disk_free_gb' => $ctx['disk_free_gb'] ?? null,
                'load_1' => $ctx['load_1'] ?? null,
                'disks' => $ctx['disks'] ?? [],
            ];
        }

        // Prefer the most recently reporting host for the headline gauges.
        $hosts = [];
        $primary = null;
        foreach ($byHost as $host => $data) {
            $latest = $data['latest'];
            $hosts[] = $latest;
            if ($primary === null || ($latest['at'] ?? '') > ($primary['at'] ?? '')) {
                $primary = $latest;
            }
        }

        $primaryHost = $primary['hostname'] ?? array_key_first($byHost);
        $series = $byHost[$primaryHost] ?? [];
        unset($series['latest']);

        // Drop null-only points for cleaner charts.
        foreach ($series as $key => $points) {
            $series[$key] = array_values(array_map(
                static fn (array $p): array => ['t' => $p['t'], 'v' => $p['v']],
                array_filter($points, static fn (array $p): bool => $p['v'] !== null)
            ));
        }

        return response()->json([
            'available' => true,
            'source' => 'project_hosts',
            'project_id' => $projectId,
            'range' => $rangeSeconds,
            'hostname' => $primaryHost,
            'hosts' => $hosts,
            'series' => $series,
            'latest' => $primary ?? (object) [],
            'units' => [
                'cpu' => '%',
                'memory' => '%',
                'memory_mb' => 'MB',
                'disk' => '%',
                'disk_used_gb' => 'GB',
                'load_1' => 'load',
            ],
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
