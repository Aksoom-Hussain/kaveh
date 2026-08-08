<?php

declare(strict_types=1);

namespace Kaveh\Watchers;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\KavehManager;
use Kaveh\Support\Redactor;
use Kaveh\Support\Silencer;

final class JobWatcher
{
    /** @var array<string, float> */
    private array $startedAt = [];

    public function __construct(
        private readonly KavehManager $kaveh,
        private readonly Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            Silencer::run(function () use ($event): void {
                $id = $this->jobKey($event->job);
                if ($id !== null) {
                    $this->startedAt[$id] = microtime(true);
                }
            });
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event): void {
            Silencer::run(function () use ($event): void {
                if ($this->shouldIgnore($event->job->resolveName())) {
                    return;
                }

                $this->kaveh->record(new EventEnvelope(
                    id: EventEnvelope::generateId(),
                    type: EventType::Job,
                    name: $event->job->resolveName(),
                    timestamp: gmdate('c'),
                    environment: (string) config('kaveh.environment'),
                    hostname: gethostname() ?: 'unknown',
                    context: $this->redactor->redact([
                        'connection' => $event->connectionName,
                        'queue' => $event->job->getQueue(),
                        'status' => 'processed',
                    ]),
                    tags: ['job', 'processed'],
                    level: 'info',
                    durationMs: $this->durationMs($event->job),
                ));
            });
        });

        Event::listen(JobFailed::class, function (JobFailed $event): void {
            Silencer::run(function () use ($event): void {
                if ($this->shouldIgnore($event->job->resolveName())) {
                    return;
                }

                $this->kaveh->record(new EventEnvelope(
                    id: EventEnvelope::generateId(),
                    type: EventType::Job,
                    name: $event->job->resolveName(),
                    timestamp: gmdate('c'),
                    environment: (string) config('kaveh.environment'),
                    hostname: gethostname() ?: 'unknown',
                    context: $this->redactor->redact([
                        'connection' => $event->connectionName,
                        'queue' => $event->job->getQueue(),
                        'status' => 'failed',
                        'exception' => $event->exception->getMessage(),
                    ]),
                    tags: ['job', 'failed'],
                    level: 'error',
                    durationMs: $this->durationMs($event->job),
                ));
            });
        });
    }

    private function shouldIgnore(string $jobName): bool
    {
        return str_starts_with($jobName, 'Kaveh\\')
            || str_contains($jobName, 'FlushEventsJob')
            || str_contains($jobName, 'EmbedEventJob');
    }

    private function jobKey(object $job): ?string
    {
        try {
            if (method_exists($job, 'uuid') && $job->uuid()) {
                return (string) $job->uuid();
            }
            if (method_exists($job, 'getJobId') && $job->getJobId()) {
                return (string) $job->getJobId();
            }
        } catch (\Throwable) {
            return null;
        }

        return spl_object_hash($job);
    }

    private function durationMs(object $job): ?float
    {
        $id = $this->jobKey($job);
        if ($id === null || ! isset($this->startedAt[$id])) {
            return null;
        }

        $ms = (microtime(true) - $this->startedAt[$id]) * 1000;
        unset($this->startedAt[$id]);

        return round($ms, 2);
    }
}
