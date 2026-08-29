<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Kaveh\KavehManager;
use Kaveh\KavehServiceProvider;
use Kaveh\Storage\LocalEventStore;
use Kaveh\Transport\EventBuffer;
use Kaveh\Transport\RemoteTransport;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @var list<array<string, mixed>> Guzzle history container for the faked transport. */
    protected array $transportHistory = [];

    protected function getPackageProviders($app): array
    {
        return [KavehServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('kaveh.enabled', true);
        // 'both' so client + server migrations, routes and models are all available.
        $app['config']->set('kaveh.role', 'both');
        $app['config']->set('kaveh.mode', 'remote');
        $app['config']->set('kaveh.environment', 'testing');
        $app['config']->set('kaveh.server_url', 'https://kaveh.test');
        $app['config']->set('kaveh.api_key', 'kv_test_key');
        $app['config']->set('kaveh.sample_rate', 1.0);
        $app['config']->set('kaveh.fail_silently', true);
        $app['config']->set('kaveh.debug', false);
        $app['config']->set('kaveh.batch.use_queue', false);
        $app['config']->set('kaveh.batch.size', 50);
        // Watchers are registered explicitly by the tests that exercise them.
        $app['config']->set('kaveh.watchers', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->artisan('migrate')->run();
    }

    /**
     * Swap RemoteTransport (and the buffer that holds it) for one backed by a
     * Guzzle mock handler, so outgoing ingest payloads can be asserted on.
     */
    protected function fakeTransport(int $status = 202): RemoteTransport
    {
        $this->transportHistory = [];

        $stack = HandlerStack::create(new MockHandler(
            array_fill(0, 500, new GuzzleResponse($status, [], '{"accepted":1}'))
        ));
        $stack->push(Middleware::history($this->transportHistory));

        $transport = new RemoteTransport(new Client(['handler' => $stack]));

        $this->app->instance(RemoteTransport::class, $transport);
        $this->app->instance(
            EventBuffer::class,
            new EventBuffer($transport, $this->app->make(LocalEventStore::class))
        );
        $this->app->forgetInstance(KavehManager::class);

        return $transport;
    }

    /**
     * Every event body pushed through the faked transport, flattened.
     *
     * @return list<array<string, mixed>>
     */
    protected function sentEvents(): array
    {
        $events = [];
        foreach ($this->sentBatches() as $batch) {
            foreach ($batch['events'] ?? [] as $event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function sentBatches(): array
    {
        $batches = [];
        foreach ($this->transportHistory as $entry) {
            $body = (string) $entry['request']->getBody();
            if (strtolower($entry['request']->getHeaderLine('Content-Encoding')) === 'gzip') {
                $body = (string) gzdecode($body);
            }
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $batches[] = $decoded;
            }
        }

        return $batches;
    }
}
