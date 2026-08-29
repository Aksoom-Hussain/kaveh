<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kaveh\Server\Models\ApiKey;
use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Organization;
use Kaveh\Server\Models\Project;

final class ProjectDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('kaveh.server.user_model', ProjectDeleteTestUser::class);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_authenticated_user_can_delete_their_project(): void
    {
        [$user, $project] = $this->fixture();

        EventRecord::query()->create([
            'project_id' => $project->id,
            'uuid' => 'evt-1',
            'type' => 'request',
            'name' => 'GET /',
            'occurred_at' => now(),
        ]);
        ApiKey::issue($project, 'default');

        $this->actingAs($user)
            ->delete(route('kaveh.projects.destroy', $project))
            ->assertRedirect(route('kaveh.projects.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('kaveh_projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('kaveh_events', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('kaveh_api_keys', ['project_id' => $project->id]);
    }

    public function test_user_cannot_delete_another_organizations_project(): void
    {
        [$user, $project] = $this->fixture();

        $otherOrg = Organization::query()->create(['name' => 'Other', 'slug' => 'other']);
        $otherProject = Project::query()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Foreign',
            'slug' => 'foreign',
        ]);

        $this->actingAs($user)
            ->delete(route('kaveh.projects.destroy', $otherProject))
            ->assertForbidden();

        $this->assertDatabaseHas('kaveh_projects', ['id' => $otherProject->id]);
    }

    /**
     * @return array{0: ProjectDeleteTestUser, 1: Project}
     */
    private function fixture(): array
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        $org = Organization::query()->create(['name' => 'Acme', 'slug' => 'acme']);
        $project = Project::query()->create([
            'organization_id' => $org->id,
            'name' => 'Web',
            'slug' => 'web',
        ]);

        $user = ProjectDeleteTestUser::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('secret'),
        ]);

        DB::table('kaveh_organization_user')->insert([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $project];
    }
}

final class ProjectDeleteTestUser extends \Illuminate\Foundation\Auth\User
{
    protected $table = 'users';

    protected $guarded = [];
}
