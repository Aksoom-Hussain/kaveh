<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Organization;
use Kaveh\Server\Models\Project;

/**
 * Pruning used to cap overflow deletion at one chunk per project per run, so a
 * backlog larger than the cap never caught up on a daily schedule.
 */
final class PruneEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::query()->create(['name' => 'Acme', 'slug' => 'acme']);
        $this->project = Project::query()->create([
            'organization_id' => $org->id,
            'name' => 'Web',
            'slug' => 'web',
            'retention_days' => 14,
            'max_events' => 5,
        ]);
    }

    private function seedEvents(int $count, string $when = '-1 minute'): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'project_id' => $this->project->id,
                'uuid' => 'evt-'.uniqid('', true),
                'type' => 'exception',
                'name' => 'RuntimeException',
                'level' => 'error',
                'occurred_at' => now()->parse($when)->subSeconds($i),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        EventRecord::query()->insert($rows);
    }

    public function test_it_drains_an_overflow_backlog_bigger_than_one_chunk(): void
    {
        // max_events = 5, chunk = 1000: a single capped pass would leave rows behind.
        $this->seedEvents(1200);

        $this->artisan('kaveh:prune-events')->assertExitCode(0);

        $this->assertSame(5, EventRecord::query()->count());
    }

    public function test_it_drains_expired_events_past_one_chunk(): void
    {
        $this->seedEvents(1100, '-30 days');

        $this->artisan('kaveh:prune-events')->assertExitCode(0);

        $this->assertSame(0, EventRecord::query()->count());
    }
}
