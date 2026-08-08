<?php

declare(strict_types=1);

namespace Kaveh\Watchers;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\KavehManager;
use Kaveh\Support\Redactor;
use Kaveh\Support\Silencer;

final class JobWatcher
{
    public function __construct(
        private readonly KavehManager $kaveh,
        private readonly Redactor $redactor,
    ) {}

    public function register(): void
    {
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
}
