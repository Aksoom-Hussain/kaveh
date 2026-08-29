<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\Contracts\IngestBatch;
use Kaveh\Jobs\FlushEventsJob;
use Kaveh\Storage\LocalEventStore;
use Kaveh\Transport\EventBuffer;
use Kaveh\Transport\RemoteTransport;

/**
 * Buffering, threshold flush, and the "do not lose events" contract.
 */
final class EventBufferTest extends TestCase
{
    use RefreshDatabase;

    private function buffer(): EventBuffer
    {
        return $this->app->make(EventBuffer::class);
    }

    private function event(string $name = 'checkout.failed'): EventEnvelope
    {
        return new EventEnvelope(
            id: EventEnvelope::generateId(),
            type: EventType::Custom,
            name: $name,
            timestamp: gmdate('c'),
            environment: 'testing',
            hostname: 'test-host',
            context: ['order_id' => 1],
            tags: ['billing'],
        );
    }

    public function test_events_accumulate_and_are_not_shipped_before_the_threshold(): void
    {
        $this->fakeTransport();
        config(['kaveh.batch.size' => 5]);

        $buffer = $this->buffer();
        for ($i = 0; $i < 4; $i++) {
            $buffer->push($this->event("e$i"));
        }

        $this->assertSame([], $this->sentEvents(), 'events left early, before the batch was full');
    }

    public function test_buffer_flushes_automatically_at_the_threshold(): void
    {
        $this->fakeTransport();
        config(['kaveh.batch.size' => 3]);

        $buffer = $this->buffer();
        for ($i = 0; $i < 3; $i++) {
            $buffer->push($this->event("e$i"));
        }

        $batches = $this->sentBatches();
        $this->assertCount(1, $batches);
        $this->assertCount(3, $batches[0]['events']);
        $this->assertSame('e0', $batches[0]['events'][0]['name']);
        $this->assertNotEmpty($batches[0]['sent_at']);
    }

    public function test_manual_flush_ships_the_pending_events_and_empties_the_buffer(): void
    {
        $this->fakeTransport();
        config(['kaveh.batch.size' => 100]);

        $buffer = $this->buffer();
        $buffer->push($this->event('a'));
        $buffer->push($this->event('b'));
        $buffer->flush();
        $buffer->flush(); // second flush must be a no-op, not a duplicate send

        $this->assertCount(1, $this->sentBatches());
        $this->assertCount(2, $this->sentEvents());
    }

    public function test_ingest_request_is_authenticated_gzipped_and_json(): void
    {
        $this->fakeTransport();
        config(['kaveh.batch.size' => 1]);

        $this->buffer()->push($this->event());

        $request = $this->transportHistory[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://kaveh.test/api/v1/ingest', (string) $request->getUri());
        $this->assertSame('Bearer kv_test_key', $request->getHeaderLine('Authorization'));
        $this->assertSame('gzip', $request->getHeaderLine('Content-Encoding'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function test_local_mode_persists_events_and_does_not_ship_them(): void
    {
        $this->fakeTransport();
        config(['kaveh.mode' => 'local', 'kaveh.batch.size' => 1]);

        $this->buffer()->push($this->event('local.only'));

        $this->assertSame([], $this->sentEvents());
        $row = DB::table('kaveh_client_events')->where('name', 'local.only')->first();
        $this->assertNotNull($row);
        $this->assertSame('custom', $row->type);
        $this->assertSame(['order_id' => 1], json_decode((string) $row->context, true));
    }

    public function test_both_mode_persists_and_ships(): void
    {
        $this->fakeTransport();
        config(['kaveh.mode' => 'both', 'kaveh.batch.size' => 1]);

        $this->buffer()->push($this->event('both.modes'));

        $this->assertSame(1, DB::table('kaveh_client_events')->where('name', 'both.modes')->count());
        $this->assertCount(1, $this->sentEvents());
    }

    public function test_local_storage_failure_does_not_prevent_the_remote_ship(): void
    {
        $this->fakeTransport();
        config(['kaveh.mode' => 'both', 'kaveh.batch.size' => 1]);
        Schema::drop('kaveh_client_events');

        $this->buffer()->push($this->event('survives.local.failure'));

        $events = $this->sentEvents();
        $this->assertCount(1, $events, 'a local DB failure swallowed the remote ship');
        $this->assertSame('survives.local.failure', $events[0]['name']);
    }

    public function test_transport_failure_never_bubbles_into_the_host_app(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new \RuntimeException('connection refused'),
            new \RuntimeException('connection refused'),
        ]));
        $transport = new RemoteTransport(new Client(['handler' => $stack]));
        $buffer = new EventBuffer($transport, $this->app->make(LocalEventStore::class));
        config(['kaveh.batch.size' => 1]);

        $buffer->push($this->event('doomed'));
        $buffer->flush();

        $this->assertTrue(true, 'the host app was not taken down by an unreachable ingest server');
    }

    public function test_a_failed_send_spills_the_batch_to_the_local_store(): void
    {
        $stack = HandlerStack::create(new MockHandler([new GuzzleResponse(500, [], 'nope')]));
        $transport = new RemoteTransport(new Client(['handler' => $stack]));
        $buffer = new EventBuffer($transport, $this->app->make(LocalEventStore::class));
        config(['kaveh.mode' => 'remote', 'kaveh.batch.size' => 1]);

        $buffer->push($this->event('must.not.vanish'));

        $this->assertSame(
            1,
            DB::table('kaveh_client_events')->where('name', 'must.not.vanish')->count(),
            'a failed send discarded the batch instead of preserving it locally'
        );
    }

    public function test_a_failed_send_keeps_the_batch_for_the_next_flush(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(500, [], 'nope'),
            new GuzzleResponse(202, [], '{"accepted":2}'),
        ]));
        $stack->push(Middleware::history($history));
        $transport = new RemoteTransport(new Client(['handler' => $stack]));
        $buffer = new EventBuffer($transport, $this->app->make(LocalEventStore::class));
        config(['kaveh.mode' => 'remote', 'kaveh.batch.size' => 100]);

        $buffer->push($this->event('kept.a'));
        $buffer->push($this->event('kept.b'));
        $buffer->flush(); // 500 — batch must be retained, not dropped
        $buffer->flush(); // 202 — the same events finally ship

        $this->transportHistory = $history;
        $names = array_column($this->sentEvents(), 'name');
        $this->assertSame(
            ['kept.a', 'kept.b', 'kept.a', 'kept.b'],
            $names,
            'events failed on the first attempt were never retried'
        );
    }

    public function test_flush_job_fails_so_the_queue_can_retry_it(): void
    {
        $stack = HandlerStack::create(new MockHandler([new GuzzleResponse(500, [], 'nope')]));
        $transport = new RemoteTransport(new Client(['handler' => $stack]));

        $job = new FlushEventsJob([$this->event('retryable')->toArray()]);
        $this->assertSame(5, $job->tries);
        $this->assertNotEmpty($job->backoff);

        $this->expectException(\RuntimeException::class);
        $job->handle($transport);
    }

    public function test_prune_never_throws_when_the_local_table_is_missing(): void
    {
        Schema::drop('kaveh_client_events');

        $this->assertSame(0, $this->app->make(LocalEventStore::class)->prune());
    }

    public function test_a_non_2xx_ingest_response_is_reported_as_a_failed_send(): void
    {
        $stack = HandlerStack::create(new MockHandler([new GuzzleResponse(500, [], 'nope')]));
        $transport = new RemoteTransport(new Client(['handler' => $stack]));

        $this->assertFalse($transport->send(new IngestBatch([$this->event()], gmdate('c'))));
    }

    public function test_transport_is_inert_without_credentials(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new GuzzleResponse(202)]));
        $stack->push(Middleware::history($history));
        $transport = new RemoteTransport(new Client(['handler' => $stack]));

        config(['kaveh.api_key' => '']);
        $this->assertFalse($transport->send(new IngestBatch([$this->event()], gmdate('c'))));

        config(['kaveh.api_key' => 'kv_test_key', 'kaveh.server_url' => '']);
        $this->assertFalse($transport->send(new IngestBatch([$this->event()], gmdate('c'))));

        $this->assertSame([], $history, 'an unconfigured client still made a network call');
    }

    public function test_queued_mode_hands_the_batch_to_the_flush_job(): void
    {
        $this->fakeTransport();
        Queue::fake();
        config([
            'kaveh.batch.use_queue' => true,
            'kaveh.batch.queue' => 'monitoring',
            'kaveh.batch.size' => 2,
        ]);

        $buffer = $this->buffer();
        $buffer->push($this->event('queued.a'));
        $buffer->push($this->event('queued.b'));

        $this->assertSame([], $this->sentEvents(), 'queued mode sent synchronously');
        Queue::assertPushed(FlushEventsJob::class, function (FlushEventsJob $job) {
            return count($job->events) === 2
                && $job->events[0]['name'] === 'queued.a'
                && $job->events[1]['name'] === 'queued.b'
                && $job->queue === 'monitoring';
        });
    }

    public function test_flush_job_ships_every_event_it_was_given(): void
    {
        $transport = $this->fakeTransport();

        (new FlushEventsJob([
            $this->event('job.a')->toArray(),
            $this->event('job.b')->toArray(),
        ]))->handle($transport);

        $names = array_column($this->sentEvents(), 'name');
        $this->assertSame(['job.a', 'job.b'], $names);
    }

    public function test_flush_job_drops_only_the_malformed_event_not_the_whole_batch(): void
    {
        $transport = $this->fakeTransport();

        (new FlushEventsJob([
            $this->event('good.one')->toArray(),
            ['type' => 'nonsense-type', 'name' => 'still.kept'],
            $this->event('good.two')->toArray(),
        ]))->handle($transport);

        $events = $this->sentEvents();
        $this->assertCount(3, $events);
        // Unknown types degrade to 'custom' rather than dropping the event on the floor.
        $this->assertSame('custom', $events[1]['type']);
        $this->assertSame(['good.one', 'still.kept', 'good.two'], array_column($events, 'name'));
    }

    public function test_flush_job_with_no_usable_events_makes_no_request(): void
    {
        $transport = $this->fakeTransport();

        (new FlushEventsJob([]))->handle($transport);

        $this->assertSame([], $this->transportHistory);
    }

    public function test_local_store_prunes_only_events_past_retention(): void
    {
        config(['kaveh.local.retention_hours' => 24]);
        $store = $this->app->make(LocalEventStore::class);

        $store->store($this->event('fresh'));
        DB::table('kaveh_client_events')->insert([
            'uuid' => 'old-uuid',
            'type' => 'custom',
            'name' => 'stale',
            'level' => 'info',
            'occurred_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $this->assertSame(1, $store->prune());
        $this->assertSame(1, DB::table('kaveh_client_events')->count());
        $this->assertSame('fresh', DB::table('kaveh_client_events')->value('name'));
    }
}
