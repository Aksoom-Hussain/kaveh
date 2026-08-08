<?php

declare(strict_types=1);

namespace Kaveh\Watchers;

use Illuminate\Support\Facades\Event;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\KavehManager;
use Kaveh\Support\Silencer;
use Kaveh\Support\Redactor;
use Throwable;

final class ExceptionWatcher
{
    public function __construct(
        private readonly KavehManager $kaveh,
        private readonly Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen('Illuminate\Log\Events\MessageLogged', function ($event): void {
            Silencer::run(function () use ($event): void {
                if (($event->level ?? '') !== 'error' && ($event->level ?? '') !== 'critical') {
                    return;
                }
                $exception = $event->context['exception'] ?? null;
                if ($exception instanceof Throwable) {
                    $this->record($exception);
                }
            });
        });
    }

    public function record(Throwable $e): void
    {
        static $recording = false;
        if ($recording) {
            return;
        }
        $recording = true;

        try {
            $this->kaveh->record(new EventEnvelope(
                id: EventEnvelope::generateId(),
                type: EventType::Exception,
                name: $e::class,
                timestamp: gmdate('c'),
                environment: (string) config('kaveh.environment'),
                hostname: gethostname() ?: 'unknown',
                context: $this->redactor->redact([
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'trace' => collect($e->getTrace())->take(20)->map(fn ($frame) => [
                        'file' => $frame['file'] ?? null,
                        'line' => $frame['line'] ?? null,
                        'function' => $frame['function'] ?? null,
                        'class' => $frame['class'] ?? null,
                    ])->all(),
                ]),
                tags: ['exception'],
                level: 'error',
            ));
        } catch (Throwable) {
            // Never rethrow into the host exception handler.
        } finally {
            $recording = false;
        }
    }
}
