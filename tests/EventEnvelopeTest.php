<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use PHPUnit\Framework\TestCase;

final class EventEnvelopeTest extends TestCase
{
    public function test_round_trip(): void
    {
        $event = EventEnvelope::fromArray([
            'type' => 'custom',
            'name' => 'checkout.failed',
            'level' => 'error',
            'tags' => ['billing'],
            'context' => ['order_id' => 1],
        ]);

        $this->assertSame(EventType::Custom, $event->type);
        $this->assertSame('checkout.failed', $event->name);
        $this->assertSame('custom', $event->toArray()['type']);
    }

    public function test_validate(): void
    {
        $this->assertNotEmpty(EventEnvelope::validate([]));
        $this->assertSame([], EventEnvelope::validate([
            'type' => 'exception',
            'name' => 'RuntimeException',
        ]));
    }
}
