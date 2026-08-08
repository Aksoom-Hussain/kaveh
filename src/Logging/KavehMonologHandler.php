<?php

declare(strict_types=1);

namespace Kaveh\Logging;

use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\KavehManager;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;

/**
 * Ships log records to Kaveh then drops them from local disk growth
 * when used as the sole handler for a channel.
 */
final class KavehMonologHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly KavehManager $kaveh,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $this->kaveh->record(new EventEnvelope(
            id: EventEnvelope::generateId(),
            type: EventType::Log,
            name: 'log.'.$record->level->toPsrLogLevel(),
            timestamp: $record->datetime->format('c'),
            environment: (string) config('kaveh.environment'),
            hostname: gethostname() ?: 'unknown',
            context: [
                'message' => $record->message,
                'channel' => $record->channel,
                'context' => $record->context,
                'extra' => $record->extra,
            ],
            tags: ['log', 'monolog'],
            level: $record->level->toPsrLogLevel(),
        ));
    }
}
