<?php

declare(strict_types=1);

namespace Kaveh\Support;

/**
 * Collect host CPU / memory / disk like Laravel Pulse's servers recorder.
 */
final class SystemStats
{
    /**
     * @param  list<string>|null  $disks
     * @return array<string, mixed>
     */
    public static function collect(?array $disks = null): array
    {
        $disks ??= (array) config('kaveh.check.disks', ['/']);
        $diskStats = [];

        foreach ($disks as $path) {
            $path = (string) $path;
            if ($path === '' || ! is_dir($path)) {
                continue;
            }

            $total = @disk_total_space($path);
            $free = @disk_free_space($path);
            if ($total === false || $free === false || $total <= 0) {
                continue;
            }

            $used = $total - $free;
            $diskStats[] = [
                'path' => $path,
                'total_bytes' => (int) $total,
                'used_bytes' => (int) $used,
                'free_bytes' => (int) $free,
                'total_gb' => round($total / 1024 / 1024 / 1024, 2),
                'used_gb' => round($used / 1024 / 1024 / 1024, 2),
                'free_gb' => round($free / 1024 / 1024 / 1024, 2),
                'percent' => round(($used / $total) * 100, 2),
            ];
        }

        $memory = self::memory();
        $cpu = self::cpuPercent();
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        return [
            'cpu_percent' => $cpu,
            'memory_total_bytes' => $memory['total'],
            'memory_used_bytes' => $memory['used'],
            'memory_free_bytes' => $memory['free'],
            'memory_total_mb' => $memory['total'] !== null ? round($memory['total'] / 1024 / 1024, 2) : null,
            'memory_used_mb' => $memory['used'] !== null ? round($memory['used'] / 1024 / 1024, 2) : null,
            'memory_percent' => $memory['percent'],
            'load_1' => is_array($load) ? round((float) $load[0], 2) : null,
            'load_5' => is_array($load) ? round((float) $load[1], 2) : null,
            'load_15' => is_array($load) ? round((float) $load[2], 2) : null,
            'disks' => $diskStats,
            'disk_percent' => $diskStats[0]['percent'] ?? null,
            'disk_used_gb' => $diskStats[0]['used_gb'] ?? null,
            'disk_total_gb' => $diskStats[0]['total_gb'] ?? null,
            'disk_free_gb' => $diskStats[0]['free_gb'] ?? null,
            'php_version' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
        ];
    }

    /**
     * @return array{total: ?int, used: ?int, free: ?int, percent: ?float}
     */
    private static function memory(): array
    {
        if (is_readable('/proc/meminfo')) {
            $raw = @file_get_contents('/proc/meminfo');
            if (is_string($raw) && $raw !== '') {
                $values = [];
                foreach (explode("\n", $raw) as $line) {
                    if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                        $values[$m[1]] = (int) $m[2] * 1024; // kB → bytes
                    }
                }
                $total = $values['MemTotal'] ?? null;
                $available = $values['MemAvailable'] ?? ($values['MemFree'] ?? null);
                if ($total && $available !== null) {
                    $used = max(0, $total - $available);

                    return [
                        'total' => $total,
                        'used' => $used,
                        'free' => $available,
                        'percent' => round(($used / $total) * 100, 2),
                    ];
                }
            }
        }

        // macOS / fallback via `memory_pressure` is noisy; use PHP memory as last resort signal.
        $limit = self::phpMemoryLimitBytes();
        $used = memory_get_usage(true);

        if ($limit !== null && $limit > 0) {
            return [
                'total' => $limit,
                'used' => $used,
                'free' => max(0, $limit - $used),
                'percent' => round(($used / $limit) * 100, 2),
            ];
        }

        return [
            'total' => null,
            'used' => $used,
            'free' => null,
            'percent' => null,
        ];
    }

    private static function phpMemoryLimitBytes(): ?int
    {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '' || $limit === '-1') {
            return null;
        }

        if (preg_match('/^(\d+)\s*([KMG])?$/i', trim($limit), $m)) {
            $n = (int) $m[1];
            $unit = strtoupper($m[2] ?? '');

            return match ($unit) {
                'G' => $n * 1024 * 1024 * 1024,
                'M' => $n * 1024 * 1024,
                'K' => $n * 1024,
                default => $n,
            };
        }

        return null;
    }

    /**
     * Sample /proc/stat over a short window (Pulse-style).
     */
    private static function cpuPercent(): ?float
    {
        if (! is_readable('/proc/stat')) {
            return null;
        }

        $first = self::readProcStat();
        if ($first === null) {
            return null;
        }

        usleep(200_000); // 200ms
        $second = self::readProcStat();
        if ($second === null) {
            return null;
        }

        $idle = $second['idle'] - $first['idle'];
        $total = $second['total'] - $first['total'];
        if ($total <= 0) {
            return 0.0;
        }

        return round((1 - ($idle / $total)) * 100, 2);
    }

    /**
     * @return array{idle: float, total: float}|null
     */
    private static function readProcStat(): ?array
    {
        $line = @file('/proc/stat', FILE_IGNORE_NEW_LINES);
        if (! is_array($line) || $line === []) {
            return null;
        }

        foreach ($line as $row) {
            if (! str_starts_with($row, 'cpu ')) {
                continue;
            }
            $parts = preg_split('/\s+/', trim($row)) ?: [];
            $nums = array_map('floatval', array_slice($parts, 1));
            if (count($nums) < 4) {
                return null;
            }
            $idle = ($nums[3] ?? 0) + ($nums[4] ?? 0); // idle + iowait
            $total = array_sum($nums);

            return ['idle' => $idle, 'total' => $total];
        }

        return null;
    }
}
