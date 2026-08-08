<?php

declare(strict_types=1);

namespace Kaveh\Server\Support;

use Kaveh\Server\Models\EventRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pulse-style overview aggregates from Kaveh events (+ optional Pulse cache).
 */
final class DashboardInsights
{
    public function __construct(
        private readonly int $projectId,
        private readonly int $rangeSeconds,
        private readonly int $slowRequestMs = 1000,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $since = now()->subSeconds($this->rangeSeconds);

        return [
            'available' => true,
            'project_id' => $this->projectId,
            'range' => $this->rangeSeconds,
            'slow_request_ms' => $this->slowRequestMs,
            'servers' => $this->servers($since),
            'exceptions' => $this->exceptions($since),
            'slow_requests' => $this->slowRequests($since),
            'slow_queries' => $this->slowQueries($since),
            'slow_jobs' => $this->slowJobs($since),
            'users' => $this->users($since),
            'queues' => ['queues' => $this->queues($since)],
            'cache' => $this->cache(),
            'traffic' => $this->trafficSeries($since),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function servers(\DateTimeInterface $since): array
    {
        $rows = EventRecord::query()
            ->where('project_id', $this->projectId)
            ->where('type', 'system')
            ->where('name', 'system.stats')
            ->where('occurred_at', '>=', $since)
            ->orderBy('occurred_at')
            ->get(['occurred_at', 'hostname', 'context']);

        $byHost = [];
        foreach ($rows as $row) {
            $host = $row->hostname ?: 'unknown';
            $ctx = is_array($row->context) ? $row->context : [];
            $t = optional($row->occurred_at)?->utc()->toIso8601String() ?? gmdate('c');

            $byHost[$host]['cpu'][] = ['t' => $t, 'v' => isset($ctx['cpu_percent']) ? (float) $ctx['cpu_percent'] : null];
            $byHost[$host]['memory'][] = ['t' => $t, 'v' => isset($ctx['memory_used_mb']) ? (float) $ctx['memory_used_mb'] : null];
            $byHost[$host]['latest'] = [
                'hostname' => $host,
                'at' => $t,
                'cpu_percent' => $ctx['cpu_percent'] ?? null,
                'memory_percent' => $ctx['memory_percent'] ?? null,
                'memory_used_mb' => $ctx['memory_used_mb'] ?? null,
                'memory_total_mb' => $ctx['memory_total_mb'] ?? null,
                'disks' => is_array($ctx['disks'] ?? null) ? $ctx['disks'] : [],
                'load_1' => $ctx['load_1'] ?? null,
            ];
        }

        $out = [];
        foreach ($byHost as $host => $data) {
            $latest = $data['latest'];
            $out[] = [
                ...$latest,
                'series' => [
                    'cpu' => $this->cleanSeries($data['cpu'] ?? []),
                    'memory' => $this->cleanSeries($data['memory'] ?? []),
                ],
                'online' => isset($latest['at']) && strtotime((string) $latest['at']) >= now()->subMinutes(2)->timestamp,
            ];
        }

        usort($out, static fn ($a, $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));

        return $out;
    }

    /**
     * @return list<array{name: string, count: int, message: ?string, file: ?string}>
     */
    private function exceptions(\DateTimeInterface $since): array
    {
        $rows = EventRecord::query()
            ->where('project_id', $this->projectId)
            ->where('type', 'exception')
            ->where('occurred_at', '>=', $since)
            ->orderByDesc('occurred_at')
            ->limit(500)
            ->get(['name', 'context']);

        return $this->groupRanked($rows, static function (EventRecord $row): string {
            return (string) $row->name;
        }, static function (EventRecord $row): array {
            $ctx = is_array($row->context) ? $row->context : [];

            return [
                'message' => isset($ctx['message']) ? (string) $ctx['message'] : null,
                'file' => isset($ctx['file'])
                    ? basename((string) $ctx['file']).':'.($ctx['line'] ?? '')
                    : null,
            ];
        }, 'count', 10);
    }

    /**
     * @return list<array{name: string, count: int, slowest_ms: float, uri: ?string}>
     */
    private function slowRequests(\DateTimeInterface $since): array
    {
        $rows = EventRecord::query()
            ->where('project_id', $this->projectId)
            ->where('type', 'request')
            ->where('occurred_at', '>=', $since)
            ->where('duration_ms', '>=', $this->slowRequestMs)
            ->orderByDesc('duration_ms')
            ->limit(500)
            ->get(['name', 'duration_ms', 'context']);

        $grouped = [];
        foreach ($rows as $row) {
            $ctx = is_array($row->context) ? $row->context : [];
            $key = (string) ($ctx['uri'] ?? $row->name);
            $grouped[$key] ??= ['name' => $key, 'count' => 0, 'slowest_ms' => 0.0, 'uri' => $ctx['uri'] ?? null];
            $grouped[$key]['count']++;
            $grouped[$key]['slowest_ms'] = max($grouped[$key]['slowest_ms'], (float) ($row->duration_ms ?? 0));
        }

        $list = array_values($grouped);
        usort($list, static fn ($a, $b) => $b['slowest_ms'] <=> $a['slowest_ms']);

        return array_slice($list, 0, 10);
    }

    /**
     * @return list<array{sql: string, count: int, slowest_ms: float, location: ?string}>
     */
    private function slowQueries(\DateTimeInterface $since): array
    {
        $rows = EventRecord::query()
            ->where('project_id', $this->projectId)
            ->where('type', 'query')
            ->where('occurred_at', '>=', $since)
            ->orderByDesc('duration_ms')
            ->limit(500)
            ->get(['duration_ms', 'context']);

        $grouped = [];
        foreach ($rows as $row) {
            $ctx = is_array($row->context) ? $row->context : [];
            $sql = (string) ($ctx['sql'] ?? 'query');
            $key = mb_substr(preg_replace('/\s+/', ' ', $sql) ?? $sql, 0, 240);
            $grouped[$key] ??= [
                'sql' => $key,
                'count' => 0,
                'slowest_ms' => 0.0,
                'location' => isset($ctx['connection']) ? 'connection: '.$ctx['connection'] : null,
            ];
            $grouped[$key]['count']++;
            $grouped[$key]['slowest_ms'] = max($grouped[$key]['slowest_ms'], (float) ($row->duration_ms ?? 0));
        }

        $list = array_values($grouped);
        usort($list, static fn ($a, $b) => $b['slowest_ms'] <=> $a['slowest_ms']);

        return array_slice($list, 0, 10);
    }

    /**
     * @return list<array{name: string, count: int, slowest_ms: ?float, failed: int}>
     */
    private function slowJobs(\DateTimeInterface $since): array
    {
        $rows = EventRecord::query()
            ->where('project_id', $this->projectId)
            ->where('type', 'job')
            ->where('occurred_at', '>=', $since)
            ->orderByDesc('occurred_at')
            ->limit(800)
            ->get(['name', 'duration_ms', 'level', 'context']);

        $grouped = [];
        foreach ($rows as $row) {
            $key = (string) $row->name;
            $grouped[$key] ??= ['name' => $key, 'count' => 0, 'slowest_ms' => null, 'failed' => 0];
            $grouped[$key]['count']++;
            if ($row->level === 'error' || (($row->context['status'] ?? null) === 'failed')) {
                $grouped[$key]['failed']++;
            }
            if ($row->duration_ms !== null) {
                $grouped[$key]['slowest_ms'] = max((float) ($grouped[$key]['slowest_ms'] ?? 0), (float) $row->duration_ms);
            }
        }

        $list = array_values($grouped);
        usort($list, static function ($a, $b) {
            $sa = $a['slowest_ms'] ?? -1;
            $sb = $b['slowest_ms'] ?? -1;
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            return $b['count'] <=> $a['count'];
        });

        return array_slice($list, 0, 10);
    }

    /**
     * @return list<array{id: mixed, email: ?string, name: ?string, count: int}>
     */
    private function users(\DateTimeInterface $since): array
    {
        $rows = EventRecord::query()
            ->where('project_id', $this->projectId)
            ->where('type', 'request')
            ->where('occurred_at', '>=', $since)
            ->whereNotNull('user')
            ->orderByDesc('occurred_at')
            ->limit(1000)
            ->get(['user']);

        $grouped = [];
        foreach ($rows as $row) {
            $user = is_array($row->user) ? $row->user : [];
            if ($user === []) {
                continue;
            }
            $id = $user['id'] ?? $user['email'] ?? $user['name'] ?? null;
            if ($id === null) {
                continue;
            }
            $key = (string) $id;
            $grouped[$key] ??= [
                'id' => $user['id'] ?? $key,
                'email' => isset($user['email']) ? (string) $user['email'] : null,
                'name' => isset($user['name']) ? (string) $user['name'] : null,
                'count' => 0,
            ];
            $grouped[$key]['count']++;
        }

        $list = array_values($grouped);
        usort($list, static fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($list, 0, 10);
    }

    /**
     * @return list<array{key: string, series: array<string, list<array{t: string, v: float}>>}>
     */
    private function queues(\DateTimeInterface $since): array
    {
        $rows = EventRecord::query()
            ->where('project_id', $this->projectId)
            ->where('type', 'job')
            ->where('occurred_at', '>=', $since)
            ->orderBy('occurred_at')
            ->limit(2000)
            ->get(['occurred_at', 'context', 'level']);

        $bucketSeconds = $this->rangeSeconds <= 3600 * 6 ? 60 : 3600;
        $byQueue = [];

        foreach ($rows as $row) {
            $ctx = is_array($row->context) ? $row->context : [];
            $connection = (string) ($ctx['connection'] ?? 'default');
            $queue = (string) ($ctx['queue'] ?? 'default');
            $key = $connection.':'.$queue;
            $status = (string) ($ctx['status'] ?? ($row->level === 'error' ? 'failed' : 'processed'));
            $ts = optional($row->occurred_at)?->getTimestamp() ?? time();
            $bucket = (int) (floor($ts / $bucketSeconds) * $bucketSeconds);
            $iso = gmdate('c', $bucket);

            $byQueue[$key][$status][$iso] = ($byQueue[$key][$status][$iso] ?? 0) + 1;
        }

        $queues = [];
        foreach ($byQueue as $key => $statuses) {
            $series = [];
            foreach ($statuses as $status => $points) {
                ksort($points);
                $series[$status] = [];
                foreach ($points as $t => $v) {
                    $series[$status][] = ['t' => $t, 'v' => (float) $v];
                }
            }
            $queues[] = ['key' => $key, 'series' => $series];
        }

        usort($queues, static fn ($a, $b) => strcmp($a['key'], $b['key']));

        return array_slice($queues, 0, 12);
    }

    /**
     * @return array{available: bool, hits: float, misses: float, hit_rate: ?float, keys: list<array{key: string, hits: float, misses: float, hit_rate: ?float}>}
     */
    private function cache(): array
    {
        if (! Schema::hasTable('pulse_aggregates')) {
            return [
                'available' => false,
                'hits' => 0,
                'misses' => 0,
                'hit_rate' => null,
                'keys' => [],
                'message' => 'Install Laravel Pulse on the monitor host for cache charts, or ship cache events from clients.',
            ];
        }

        $period = match (true) {
            $this->rangeSeconds <= 3600 => 60,
            $this->rangeSeconds <= 21600 => 360,
            $this->rangeSeconds <= 86400 => 1440,
            default => 10080,
        };
        $since = now()->getTimestamp() - $this->rangeSeconds;

        $hits = (float) DB::table('pulse_aggregates')
            ->where('type', 'cache_hit')
            ->where('period', $period)
            ->where('bucket', '>=', $since)
            ->where('aggregate', 'count')
            ->sum('value');
        $misses = (float) DB::table('pulse_aggregates')
            ->where('type', 'cache_miss')
            ->where('period', $period)
            ->where('bucket', '>=', $since)
            ->where('aggregate', 'count')
            ->sum('value');

        $total = $hits + $misses;

        $keyRows = DB::table('pulse_aggregates')
            ->select('key', 'type', DB::raw('SUM(value) as value'))
            ->whereIn('type', ['cache_hit', 'cache_miss'])
            ->where('period', $period)
            ->where('bucket', '>=', $since)
            ->where('aggregate', 'count')
            ->groupBy('key', 'type')
            ->get();

        $keys = [];
        foreach ($keyRows as $row) {
            $k = (string) $row->key;
            $keys[$k] ??= ['key' => $k, 'hits' => 0.0, 'misses' => 0.0, 'hit_rate' => null];
            if ($row->type === 'cache_hit') {
                $keys[$k]['hits'] = (float) $row->value;
            } else {
                $keys[$k]['misses'] = (float) $row->value;
            }
        }
        foreach ($keys as &$k) {
            $t = $k['hits'] + $k['misses'];
            $k['hit_rate'] = $t > 0 ? round(($k['hits'] / $t) * 100, 1) : null;
        }
        unset($k);
        $keyList = array_values($keys);
        usort($keyList, static fn ($a, $b) => ($b['hits'] + $b['misses']) <=> ($a['hits'] + $a['misses']));

        return [
            'available' => true,
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 1) : null,
            'keys' => array_slice($keyList, 0, 10),
        ];
    }

    /**
     * @return array{request: list<array{t: string, v: float}>, exception: list<array{t: string, v: float}>}
     */
    private function trafficSeries(\DateTimeInterface $since): array
    {
        $driver = DB::connection()->getDriverName();
        $bucket = $this->rangeSeconds <= 3600 * 6 ? 'minute' : 'hour';
        $bucketExpr = match ([$driver, $bucket]) {
            ['sqlite', 'minute'] => "strftime('%Y-%m-%d %H:%M:00', occurred_at)",
            ['sqlite', 'hour'] => "strftime('%Y-%m-%d %H:00:00', occurred_at)",
            ['pgsql', 'minute'] => "to_char(date_trunc('minute', occurred_at), 'YYYY-MM-DD HH24:MI:00')",
            ['pgsql', 'hour'] => "to_char(date_trunc('hour', occurred_at), 'YYYY-MM-DD HH24:00:00')",
            ['mysql', 'minute'], ['mariadb', 'minute'] => "DATE_FORMAT(occurred_at, '%Y-%m-%d %H:%i:00')",
            default => "DATE_FORMAT(occurred_at, '%Y-%m-%d %H:00:00')",
        };

        $rows = EventRecord::query()
            ->selectRaw("{$bucketExpr} as bucket, type, COUNT(*) as aggregate")
            ->where('project_id', $this->projectId)
            ->whereIn('type', ['request', 'exception'])
            ->where('occurred_at', '>=', $since)
            ->groupByRaw('bucket, type')
            ->orderBy('bucket')
            ->get();

        $series = ['request' => [], 'exception' => []];
        foreach ($rows as $row) {
            $type = (string) $row->type;
            if (! isset($series[$type])) {
                continue;
            }
            $ts = strtotime((string) $row->bucket.' UTC');
            $series[$type][] = [
                't' => $ts ? gmdate('c', $ts) : (string) $row->bucket,
                'v' => (float) $row->aggregate,
            ];
        }

        return $series;
    }

    /**
     * @param  Collection<int, EventRecord>  $rows
     * @param  callable(EventRecord): string  $keyFn
     * @param  callable(EventRecord): array<string, mixed>  $metaFn
     * @return list<array<string, mixed>>
     */
    private function groupRanked(Collection $rows, callable $keyFn, callable $metaFn, string $sortKey, int $limit): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $key = $keyFn($row);
            $grouped[$key] ??= ['name' => $key, 'count' => 0, ...$metaFn($row)];
            $grouped[$key]['count']++;
        }
        $list = array_values($grouped);
        usort($list, static fn ($a, $b) => ($b[$sortKey] ?? 0) <=> ($a[$sortKey] ?? 0));

        return array_slice($list, 0, $limit);
    }

    /**
     * @param  list<array{t: string, v: ?float}>  $points
     * @return list<array{t: string, v: float}>
     */
    private function cleanSeries(array $points): array
    {
        return array_values(array_map(
            static fn (array $p): array => ['t' => $p['t'], 'v' => (float) $p['v']],
            array_filter($points, static fn (array $p): bool => $p['v'] !== null)
        ));
    }
}
