<?php

declare(strict_types=1);

namespace Kaveh\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Swallow client-side failures so Kaveh never takes down the host app.
 */
final class Silencer
{
    /**
     * Run a callback; on failure return $default (never throw when fail_silently).
     *
     * @template T
     * @param  callable(): T  $callback
     * @param  T  $default
     * @return T
     */
    public static function run(callable $callback, mixed $default = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            self::report($e);

            if (! (bool) config('kaveh.fail_silently', true)) {
                throw $e;
            }

            return $default;
        }
    }

    public static function report(Throwable $e): void
    {
        if (! (bool) config('kaveh.debug', false)) {
            return;
        }

        try {
            Log::debug('Kaveh swallowed exception', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        } catch (Throwable) {
            // Logging itself must never break the host.
        }
    }
}
