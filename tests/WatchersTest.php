<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Kaveh\KavehManager;
use Kaveh\Watchers\ExceptionWatcher;
use Kaveh\Watchers\JobWatcher;
use Kaveh\Watchers\LogWatcher;
use Kaveh\Watchers\QueryWatcher;
use Kaveh\Watchers\RequestWatcher;
use Mockery;
use RuntimeException;

/**
 * One smoke test per watcher: it records, and the envelope has the shape the
 * dashboard and the ingest schema expect.
 */
final class WatchersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTransport();
        // Every push ships immediately so assertions can read the wire payload.
        config(['kaveh.batch.size' => 1, 'kaveh.batch.use_queue' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function onlyEvent(): array
    {
        $events = $this->sentEvents();
        $this->assertCount(1, $events, 'expected exactly one recorded event');

        return $events[0];
    }

    private function watcher(string $class): object
    {
        $watcher = $this->app->make($class);
        $watcher->register();

        return $watcher;
    }

    public function test_request_watcher_records_an_http_request(): void
    {
        $this->watcher(RequestWatcher::class);

        $request = Request::create('/orders/17?ref=email', 'POST', ['password' => 'hunter2', 'qty' => 2]);
        $request->headers->set('Authorization', 'Bearer leaky');
        $response = new Response('{"ok":true}', 201, ['Content-Type' => 'application/json']);

        event(new RequestHandled($request, $response));

        $event = $this->onlyEvent();
        $this->assertSame('request', $event['type']);
        $this->assertSame('POST orders/17', $event['name']);
        $this->assertSame('info', $event['level']);
        $this->assertContains('request', $event['tags']);
        $this->assertContains('status:201', $event['tags']);
        $this->assertContains('method:POST', $event['tags']);
        $this->assertSame(201, $event['context']['response_status']);
        $this->assertSame('POST', $event['context']['method']);
        $this->assertSame(['ok' => true], $event['context']['response']);

        // Secrets must not ride along on a request event.
        $this->assertSame('[REDACTED]', $event['context']['payload']['password']);
        $this->assertSame(2, $event['context']['payload']['qty']);
        $this->assertSame('[REDACTED]', $event['context']['headers']['authorization']);
        $this->assertStringNotContainsString('hunter2', (string) json_encode($event));
        $this->assertStringNotContainsString('Bearer leaky', (string) json_encode($event));
    }

    public function test_request_watcher_does_not_dump_the_session(): void
    {
        $this->watcher(RequestWatcher::class);

        $request = Request::create('/dashboard');
        $request->setLaravelSession($this->app['session']->driver('array'));
        $request->session()->put('oauth_access_token', 'gho_leaky');
        $request->session()->put('cart', ['sku-1']);
        $request->session()->put('locale', 'en');

        event(new RequestHandled($request, new Response('', 200)));

        $event = $this->onlyEvent();
        $this->assertSame([], $event['context']['session'], 'the session shipped by default');
        $this->assertStringNotContainsString('gho_leaky', (string) json_encode($event));
        $this->assertStringNotContainsString('sku-1', (string) json_encode($event));
    }

    public function test_request_watcher_ships_only_allowlisted_session_keys(): void
    {
        config(['kaveh.watchers.'.RequestWatcher::class => ['session_keys' => ['locale']]]);
        $this->watcher(RequestWatcher::class);

        $request = Request::create('/dashboard');
        $request->setLaravelSession($this->app['session']->driver('array'));
        $request->session()->put('oauth_access_token', 'gho_leaky');
        $request->session()->put('locale', 'en');

        event(new RequestHandled($request, new Response('', 200)));

        $event = $this->onlyEvent();
        $this->assertSame(['locale' => 'en'], $event['context']['session']);
        $this->assertStringNotContainsString('gho_leaky', (string) json_encode($event));
    }

    public function test_request_watcher_masks_secrets_with_one_consistent_marker(): void
    {
        $this->watcher(RequestWatcher::class);

        $request = Request::create('/checkout', 'POST', [
            'password' => 'hunter2',
            'stripe_secret' => 'sk_live_leaky',
            'qty' => 2,
        ]);

        event(new RequestHandled($request, new Response('', 200)));

        $payload = $this->onlyEvent()['context']['payload'];
        $this->assertSame('[REDACTED]', $payload['password']);
        $this->assertSame('[REDACTED]', $payload['stripe_secret']);
        $this->assertSame(2, $payload['qty']);
        $this->assertStringNotContainsString('********', (string) json_encode($this->sentEvents()));
    }

    public function test_request_watcher_levels_server_errors(): void
    {
        $this->watcher(RequestWatcher::class);

        event(new RequestHandled(Request::create('/boom'), new Response('', 500)));

        $this->assertSame('error', $this->onlyEvent()['level']);
    }

    public function test_request_watcher_ignores_its_own_ingest_endpoint(): void
    {
        $this->watcher(RequestWatcher::class);

        event(new RequestHandled(Request::create('/api/v1/ingest', 'POST'), new Response('', 202)));
        event(new RequestHandled(Request::create('/kaveh/dashboard'), new Response('', 200)));

        $this->assertSame([], $this->sentEvents(), 'Kaveh recorded its own traffic (feedback loop)');
    }

    public function test_query_watcher_records_only_slow_queries(): void
    {
        config(['kaveh.slow_query_ms' => 100]);
        $this->watcher(QueryWatcher::class);
        $connection = $this->app['db']->connection();

        event(new QueryExecuted('select 1', [], 5.0, $connection));
        $this->assertSame([], $this->sentEvents(), 'a fast query was recorded');

        event(new QueryExecuted('select * from orders where id = ?', [17], 250.5, $connection));

        $event = $this->onlyEvent();
        $this->assertSame('query', $event['type']);
        $this->assertSame('slow_query', $event['name']);
        $this->assertSame('warning', $event['level']);
        $this->assertSame(250.5, $event['duration_ms']);
        $this->assertSame('select * from orders where id = ?', $event['context']['sql']);
        // Bindings are counted, never shipped — they routinely contain PII.
        $this->assertSame(1, $event['context']['bindings_count']);
        $this->assertArrayNotHasKey('bindings', $event['context']);
        $this->assertSame(['query', 'slow'], $event['tags']);
    }

    public function test_job_watcher_records_a_processed_job(): void
    {
        $this->watcher(JobWatcher::class);

        event(new JobProcessed('redis', $this->fakeJob('App\\Jobs\\SendInvoice', 'mail')));

        $event = $this->onlyEvent();
        $this->assertSame('job', $event['type']);
        $this->assertSame('App\\Jobs\\SendInvoice', $event['name']);
        $this->assertSame('info', $event['level']);
        $this->assertSame('processed', $event['context']['status']);
        $this->assertSame('redis', $event['context']['connection']);
        $this->assertSame('mail', $event['context']['queue']);
        $this->assertSame(['job', 'processed'], $event['tags']);
    }

    public function test_job_watcher_records_a_failed_job_at_error_level(): void
    {
        $this->watcher(JobWatcher::class);

        event(new JobFailed('redis', $this->fakeJob('App\\Jobs\\SendInvoice', 'mail'), new RuntimeException('smtp down')));

        $event = $this->onlyEvent();
        $this->assertSame('error', $event['level']);
        $this->assertSame('failed', $event['context']['status']);
        $this->assertSame('smtp down', $event['context']['exception']);
        $this->assertSame(['job', 'failed'], $event['tags']);
    }

    public function test_job_watcher_ignores_kavehs_own_jobs(): void
    {
        $this->watcher(JobWatcher::class);

        event(new JobProcessed('sync', $this->fakeJob('Kaveh\\Jobs\\FlushEventsJob', 'default')));

        $this->assertSame([], $this->sentEvents(), 'Kaveh recorded its own flush job (feedback loop)');
    }

    public function test_log_watcher_records_a_log_message(): void
    {
        $this->watcher(LogWatcher::class);

        event(new MessageLogged('warning', 'disk almost full', ['free_gb' => 2, 'token' => 'tok_live']));

        $event = $this->onlyEvent();
        $this->assertSame('log', $event['type']);
        $this->assertSame('log.warning', $event['name']);
        $this->assertSame('warning', $event['level']);
        $this->assertSame(['log', 'warning'], $event['tags']);
        $this->assertSame('disk almost full', $event['context']['message']);
        $this->assertSame(2, $event['context']['context']['free_gb']);
        $this->assertSame('[REDACTED]', $event['context']['context']['token']);
    }

    public function test_log_watcher_defers_exception_logs_to_the_exception_watcher(): void
    {
        $this->watcher(LogWatcher::class);

        event(new MessageLogged('error', 'boom', ['exception' => new RuntimeException('boom')]));

        $this->assertSame([], $this->sentEvents());
    }

    public function test_exception_watcher_records_a_thrown_exception(): void
    {
        /** @var ExceptionWatcher $watcher */
        $watcher = $this->app->make(ExceptionWatcher::class);

        $watcher->record(new RuntimeException('payment gateway exploded', 42));

        $event = $this->onlyEvent();
        $this->assertSame('exception', $event['type']);
        $this->assertSame(RuntimeException::class, $event['name']);
        $this->assertSame('error', $event['level']);
        $this->assertSame(['exception'], $event['tags']);
        $this->assertSame('payment gateway exploded', $event['context']['message']);
        $this->assertSame(42, $event['context']['code']);
        $this->assertIsArray($event['context']['trace']);
        $this->assertNotEmpty($event['context']['file']);
    }

    public function test_exception_watcher_picks_up_error_level_logs_carrying_an_exception(): void
    {
        $this->watcher(ExceptionWatcher::class);

        event(new MessageLogged('info', 'ignored', ['exception' => new RuntimeException('not an error level')]));
        $this->assertSame([], $this->sentEvents());

        event(new MessageLogged('critical', 'boom', ['exception' => new RuntimeException('real one')]));

        $event = $this->onlyEvent();
        $this->assertSame('exception', $event['type']);
        $this->assertSame('real one', $event['context']['message']);
    }

    /**
     * `track()` used to hand the raw user straight to the envelope while
     * `record()` masked it — the two entry points must agree.
     */
    public function test_track_masks_the_user_email_exactly_like_record(): void
    {
        $this->actingAs(new GenericUser(['id' => 42, 'name' => 'Ada', 'email' => 'ada@example.com']));

        $this->app->make(KavehManager::class)->track('checkout.completed', ['order_id' => 7]);

        $event = $this->onlyEvent();
        $this->assertSame('a***@example.com', $event['user']['email']);
        $this->assertSame(42, $event['user']['id']);
        $this->assertStringNotContainsString('ada@example.com', (string) json_encode($event));
    }

    private function fakeJob(string $name, string $queue): JobContract
    {
        $job = Mockery::mock(JobContract::class);
        $job->shouldReceive('resolveName')->andReturn($name);
        $job->shouldReceive('getQueue')->andReturn($queue);
        $job->shouldReceive('uuid')->andReturn('job-uuid-'.md5($name));
        $job->shouldReceive('getJobId')->andReturn('1');

        return $job;
    }
}
