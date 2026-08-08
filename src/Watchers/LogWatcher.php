<?php

declare(strict_types=1);

namespace Kaveh\Watchers;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\KavehManager;
use Kaveh\Support\Redactor;

final class LogWatcher
{
    public function __construct(
        private readonly KavehManager $kaveh,
        private readonly Redactor $redactor,
    ) {}

    public function register(): void
    {
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            if (isset($event->context['exception'])) {
                return;
            }

            $this->kaveh->record(new EventEnvelope(
                id: EventEnvelope::generateId(),
                type: EventType::Log,
                name: 'log.'.$event->level,
                timestamp: gmdate('c'),
                environment: (string) config('kaveh.environment'),
                hostname: gethostname() ?: 'unknown',
                context: $this->redactor->redact([
                    'message' => $event->message,
                    'context' => $event->context,
                ]),
                tags: ['log', $event->level],
                level: $event->level,
            ));
        });
    }
}
