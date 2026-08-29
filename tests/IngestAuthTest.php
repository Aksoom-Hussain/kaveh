<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Kaveh\Server\Models\ApiKey;
use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Organization;
use Kaveh\Server\Models\Project;

/**
 * The public ingest endpoint is the package's only unauthenticated-by-default
 * surface. Everything here is about who gets through AuthenticateApiKey.
 */
final class IngestAuthTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private string $plainKey;

    private ApiKey $apiKey;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // registerUserRelations() runs at boot against this model, so the tests
        // below exercise the package's own organizations() relation.
        $app['config']->set('kaveh.server.user_model', MetricsTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $org = Organization::query()->create(['name' => 'Acme', 'slug' => 'acme']);
        $this->project = Project::query()->create([
            'organization_id' => $org->id,
            'name' => 'Web',
            'slug' => 'web',
        ]);

        $issued = ApiKey::issue($this->project, 'ci');
        $this->plainKey = $issued['plain'];
        $this->apiKey = $issued['model'];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function ingest(array $headers): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/ingest', [
            'events' => [[
                'id' => 'evt-1',
                'type' => 'exception',
                'name' => 'RuntimeException',
                'context' => ['message' => 'boom'],
            ]],
            'sent_at' => gmdate('c'),
        ], $headers);
    }

    public function test_a_valid_key_is_accepted_and_the_event_is_stored_against_its_project(): void
    {
        $response = $this->ingest(['Authorization' => 'Bearer '.$this->plainKey]);

        $response->assertStatus(202)->assertJson(['accepted' => 1, 'duplicates' => 0]);
        $this->assertSame(1, EventRecord::query()->where('project_id', $this->project->id)->count());
    }

    public function test_a_missing_authorization_header_is_rejected(): void
    {
        $this->ingest([])
            ->assertStatus(401)
            ->assertJson(['message' => 'Missing API key']);

        $this->assertSame(0, EventRecord::query()->count());
    }

    public function test_a_non_bearer_scheme_is_rejected(): void
    {
        $this->ingest(['Authorization' => 'Basic '.base64_encode('a:b')])
            ->assertStatus(401)
            ->assertJson(['message' => 'Missing API key']);
    }

    public function test_an_empty_bearer_token_is_rejected(): void
    {
        $this->ingest(['Authorization' => 'Bearer '])
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid API key']);
    }

    public function test_an_unknown_key_is_rejected(): void
    {
        $this->ingest(['Authorization' => 'Bearer kv_not_a_real_key'])
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid API key']);

        $this->assertSame(0, EventRecord::query()->count());
    }

    public function test_a_revoked_key_is_rejected(): void
    {
        $this->apiKey->forceFill(['revoked_at' => now()->subMinute()])->save();

        $this->ingest(['Authorization' => 'Bearer '.$this->plainKey])
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid API key']);

        $this->assertSame(0, EventRecord::query()->count());
    }

    public function test_the_plaintext_key_is_never_persisted(): void
    {
        $this->assertDatabaseMissing('kaveh_api_keys', ['key_hash' => $this->plainKey]);
        $this->assertSame(hash('sha256', $this->plainKey), $this->apiKey->key_hash);
        $this->assertSame(substr($this->plainKey, 0, 10), $this->apiKey->key_prefix);
    }

    public function test_a_successful_call_stamps_last_used_at(): void
    {
        $this->assertNull($this->apiKey->last_used_at);

        $this->ingest(['Authorization' => 'Bearer '.$this->plainKey])->assertStatus(202);

        $this->assertNotNull($this->apiKey->fresh()->last_used_at);
    }

    public function test_one_projects_key_cannot_write_into_another_project(): void
    {
        $otherOrg = Organization::query()->create(['name' => 'Other', 'slug' => 'other']);
        $otherProject = Project::query()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Web',
            'slug' => 'web',
        ]);

        $this->postJson('/api/v1/ingest', [
            'events' => [[
                'id' => 'evt-cross',
                'type' => 'custom',
                'name' => 'cross.tenant',
                // Client-supplied project_id must not be trusted.
                'project_id' => $otherProject->id,
            ]],
        ], ['Authorization' => 'Bearer '.$this->plainKey])->assertStatus(202);

        $this->assertSame(1, EventRecord::query()->where('project_id', $this->project->id)->count());
        $this->assertSame(0, EventRecord::query()->where('project_id', $otherProject->id)->count());
    }

    public function test_duplicate_event_ids_are_deduplicated_not_rejected(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->plainKey];

        $this->ingest($headers)->assertStatus(202)->assertJson(['accepted' => 1]);
        $this->ingest($headers)->assertStatus(202)->assertJson(['accepted' => 0, 'duplicates' => 1]);

        $this->assertSame(1, EventRecord::query()->count());
    }

    public function test_an_invalid_event_is_reported_without_dropping_the_valid_ones(): void
    {
        $response = $this->postJson('/api/v1/ingest', [
            'events' => [
                ['id' => 'ok-1', 'type' => 'custom', 'name' => 'fine'],
                ['id' => 'bad-1', 'type' => 'not-a-type', 'name' => 'nope'],
                ['id' => 'bad-2', 'type' => 'custom'],
            ],
        ], ['Authorization' => 'Bearer '.$this->plainKey]);

        $response->assertStatus(207)->assertJson(['accepted' => 1]);
        $this->assertCount(2, $response->json('errors'));
        $this->assertSame(1, EventRecord::query()->count());
    }

    public function test_a_payload_without_an_events_array_is_unprocessable(): void
    {
        $this->postJson('/api/v1/ingest', ['nope' => true], [
            'Authorization' => 'Bearer '.$this->plainKey,
        ])->assertStatus(422)->assertJson(['message' => 'Invalid payload']);
    }

    public function test_oversized_batches_are_refused(): void
    {
        $events = [];
        for ($i = 0; $i <= 500; $i++) {
            $events[] = ['id' => "e$i", 'type' => 'custom', 'name' => 'bulk'];
        }

        $this->postJson('/api/v1/ingest', ['events' => $events], [
            'Authorization' => 'Bearer '.$this->plainKey,
        ])->assertStatus(422)->assertJson(['message' => 'Too many events']);

        $this->assertSame(0, EventRecord::query()->count());
    }

    public function test_a_gzipped_body_from_the_client_transport_is_accepted(): void
    {
        $payload = (string) json_encode([
            'events' => [['id' => 'gz-1', 'type' => 'custom', 'name' => 'gzipped']],
            'sent_at' => gmdate('c'),
        ]);

        $this->call(
            'POST',
            '/api/v1/ingest',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->plainKey,
                'HTTP_CONTENT_ENCODING' => 'gzip',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: (string) gzencode($payload, 6)
        )->assertStatus(202);

        $this->assertSame('gzipped', EventRecord::query()->value('name'));
    }

    public function test_a_gzip_bomb_is_rejected_before_it_is_fully_decompressed(): void
    {
        config(['kaveh.server.max_decompressed_bytes' => 1024]);

        $payload = (string) json_encode([
            'events' => [[
                'id' => 'bomb-1',
                'type' => 'custom',
                'name' => 'bomb',
                'context' => ['blob' => str_repeat('a', 4096)],
            ]],
        ]);

        $this->call(
            'POST',
            '/api/v1/ingest',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->plainKey,
                'HTTP_CONTENT_ENCODING' => 'gzip',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: (string) gzencode($payload, 9)
        )->assertStatus(413);

        $this->assertSame(0, EventRecord::query()->count());
    }

    public function test_an_unparseable_timestamp_does_not_destroy_the_rest_of_the_batch(): void
    {
        $response = $this->postJson('/api/v1/ingest', [
            'events' => [
                ['id' => 'good-1', 'type' => 'custom', 'name' => 'before'],
                ['id' => 'bad-ts', 'type' => 'custom', 'name' => 'boom', 'timestamp' => 'not-a-date'],
                ['id' => 'good-2', 'type' => 'custom', 'name' => 'after'],
            ],
        ], ['Authorization' => 'Bearer '.$this->plainKey]);

        $response->assertStatus(207)->assertJson(['accepted' => 2]);
        $this->assertCount(1, $response->json('errors'));
        $this->assertSame(2, EventRecord::query()->count());
        $this->assertSame(0, EventRecord::query()->where('uuid', 'bad-ts')->count());
    }

    // --- Dashboard tenant isolation -----------------------------------------

    /**
     * @return array{0: \Kaveh\Tests\MetricsTestUser, 1: Project}
     */
    private function foreignTenant(): array
    {
        $otherOrg = Organization::query()->create(['name' => 'Other', 'slug' => 'other']);
        $otherProject = Project::query()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Web',
            'slug' => 'web',
        ]);

        // The host app owns the dashboard gate; KavehServiceProvider's default
        // never registers (it hooks the Gate *facade* class, not the contract).
        Gate::define('viewKaveh', fn ($user = null) => $user !== null);

        // The dashboard routes run the `web` group, which needs an app key.
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        // Testbench ships no users table; the host app owns it in real installs.
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        $myOrg = Organization::query()->find($this->project->organization_id);
        $user = MetricsTestUser::query()->create([
            'name' => 'Dash',
            'email' => 'dash@example.com',
            'password' => bcrypt('secret'),
        ]);
        $user->organizations()->attach($myOrg->id, ['role' => 'owner']);

        return [$user, $otherProject];
    }

    public static function metricsEndpoints(): array
    {
        return [['events'], ['system'], ['insights']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('metricsEndpoints')]
    public function test_metrics_endpoints_reject_another_tenants_project_id(string $endpoint): void
    {
        [$user, $otherProject] = $this->foreignTenant();

        $this->actingAs($user)
            ->getJson("/kaveh/api/metrics/{$endpoint}?project_id={$otherProject->id}")
            ->assertStatus(403);
    }

    public function test_metrics_events_without_a_project_id_only_counts_the_callers_projects(): void
    {
        [$user, $otherProject] = $this->foreignTenant();

        foreach ([$this->project->id, $otherProject->id, $otherProject->id] as $i => $projectId) {
            EventRecord::query()->create([
                'project_id' => $projectId,
                'uuid' => "seed-$i",
                'type' => 'custom',
                'name' => 'seed',
                'level' => 'info',
                'occurred_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)->getJson('/kaveh/api/metrics/events');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('totals.custom'));
    }
}

/**
 * The host app owns the User model; the package supplies `organizations()` via
 * KavehServiceProvider::registerUserRelations(). Declaring the relation here
 * would shadow the package's and hide bugs in it (KV-21), so it is not declared.
 */
final class MetricsTestUser extends \Illuminate\Foundation\Auth\User
{
    protected $table = 'users';

    protected $guarded = [];
}
