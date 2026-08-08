<?php

declare(strict_types=1);

namespace Kaveh\Contracts;

final class IngestBatch
{
    public const MAX_EVENTS = 500;

    /**
     * @param  list<EventEnvelope>  $events
     */
    public function __construct(
        public readonly array $events,
        public readonly ?string $sentAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $rawEvents = $payload['events'] ?? [];
        if (! is_array($rawEvents)) {
            $rawEvents = [];
        }

        $events = [];
        foreach (array_slice($rawEvents, 0, self::MAX_EVENTS) as $raw) {
            if (is_array($raw)) {
                $events[] = EventEnvelope::fromArray($raw);
            }
        }

        return new self(
            events: $events,
            sentAt: isset($payload['sent_at']) ? (string) $payload['sent_at'] : gmdate('c'),
        );
    }

    /**
     * @return array{events: list<array<string, mixed>>, sent_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'events' => array_map(
                static fn (EventEnvelope $e): array => $e->toArray(),
                $this->events
            ),
            'sent_at' => $this->sentAt,
        ];
    }
}
