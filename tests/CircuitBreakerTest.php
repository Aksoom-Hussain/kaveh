<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\Contracts\IngestBatch;
use Kaveh\Transport\RemoteTransport;

final class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RemoteTransport::resetCircuitBreaker();
    }

    protected function tearDown(): void
    {
        RemoteTransport::resetCircuitBreaker();
        parent::tearDown();
    }

    private function envelope(): EventEnvelope
    {
        return new EventEnvelope(
            id: EventEnvelope::generateId(),
            type: EventType::Custom,
            name: 'test.event',
            timestamp: gmdate('c'),
            environment: 'testing',
            hostname: 'test-host',
            level: 'info',
            context: ['foo' => 'bar'],
        );
    }

    public function test_circuit_trips_open_after_consecutive_failures(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(500, [], 'fail 1'),
            new GuzzleResponse(502, [], 'fail 2'),
            new GuzzleResponse(504, [], 'fail 3'),
            new GuzzleResponse(202, [], '{"accepted":1}'), // Should not be reached
        ]));
        $stack->push(Middleware::history($history));

        config([
            'kaveh.api_key' => 'kv_test',
            'kaveh.server_url' => 'https://example.com',
            'kaveh.circuit_breaker.enabled' => true,
            'kaveh.circuit_breaker.threshold' => 3,
            'kaveh.circuit_breaker.cooldown' => 60,
        ]);

        $transport = new RemoteTransport(new Client(['handler' => $stack]));

        $batch = new IngestBatch([$this->envelope()], gmdate('c'));

        $this->assertFalse($transport->send($batch));
        $this->assertSame(1, RemoteTransport::consecutiveFailures());
        $this->assertFalse(RemoteTransport::isCircuitOpen());

        $this->assertFalse($transport->send($batch));
        $this->assertSame(2, RemoteTransport::consecutiveFailures());
        $this->assertFalse(RemoteTransport::isCircuitOpen());

        $this->assertFalse($transport->send($batch));
        $this->assertSame(3, RemoteTransport::consecutiveFailures());
        $this->assertTrue(RemoteTransport::isCircuitOpen());

        // 4th send: circuit is open. Must return false immediately without making an HTTP request!
        $this->assertFalse($transport->send($batch));
        $this->assertStringContainsString('Circuit breaker open', (string) $transport->lastFailure());

        // Exactly 3 HTTP calls were made, the 4th was short-circuited.
        $this->assertCount(3, $history);
    }

    public function test_successful_request_resets_circuit_counter(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(500, [], 'fail 1'),
            new GuzzleResponse(500, [], 'fail 2'),
            new GuzzleResponse(202, [], '{"accepted":1}'),
        ]));

        config([
            'kaveh.api_key' => 'kv_test',
            'kaveh.server_url' => 'https://example.com',
            'kaveh.circuit_breaker.enabled' => true,
            'kaveh.circuit_breaker.threshold' => 3,
        ]);

        $transport = new RemoteTransport(new Client(['handler' => $stack]));
        $batch = new IngestBatch([$this->envelope()], gmdate('c'));

        $this->assertFalse($transport->send($batch));
        $this->assertSame(1, RemoteTransport::consecutiveFailures());

        $this->assertFalse($transport->send($batch));
        $this->assertSame(2, RemoteTransport::consecutiveFailures());

        $this->assertTrue($transport->send($batch));
        $this->assertSame(0, RemoteTransport::consecutiveFailures());
        $this->assertFalse(RemoteTransport::isCircuitOpen());
    }
}
