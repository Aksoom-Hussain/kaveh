<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kaveh\Server\Models\Organization;
use Kaveh\Server\Models\Project;

/**
 * A real authenticated request to the dashboard, using the `organizations()`
 * relation the *package* registers (KavehServiceProvider::registerUserRelations)
 * rather than a fixture that declares its own. The fixture user deliberately has
 * no organizations() method, so a wrong pivot table in the package relation
 * (KV-21: derived `organization_user` vs. the real `kaveh_organization_user`)
 * surfaces here as a 500 instead of staying invisible.
 */
final class DashboardHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // registerUserRelations() runs at boot against this model.
        $app['config']->set('kaveh.server.user_model', DashboardTestUser::class);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function dashboardRoutes(): array
    {
        return [
            ['/kaveh/dashboard'],
            ['/kaveh/events'],
            ['/kaveh/projects'],
            ['/kaveh/alerts'],
            ['/kaveh/api/metrics/system'],
            ['/kaveh/api/metrics/events'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dashboardRoutes')]
    public function test_dashboard_routes_are_200_for_a_member_of_an_organization(string $uri): void
    {
        $user = $this->member();

        // ShareDashboardFilters canonicalizes the query string with a redirect,
        // and it is that middleware that resolves the user's organizations.
        $this->actingAs($user)->followingRedirects()->get($uri)->assertStatus(200);
    }

    public function test_the_package_registers_the_organizations_relation_on_the_host_user_model(): void
    {
        $user = $this->member();

        // No organizations() method on the fixture — this is the package's relation.
        $this->assertFalse(method_exists(DashboardTestUser::class, 'organizations'));
        $this->assertSame(
            'kaveh_organization_user',
            $user->organizations()->getTable()
        );
        $this->assertSame([1], $user->organizations()->pluck('kaveh_organizations.id')->all());
    }

    private function member(): DashboardTestUser
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
        Project::query()->create([
            'organization_id' => $org->id,
            'name' => 'Web',
            'slug' => 'web',
        ]);

        $user = DashboardTestUser::query()->create([
            'name' => 'Dash',
            'email' => 'dash@example.com',
            'password' => bcrypt('secret'),
        ]);
        // Seeded through the query builder on purpose: the relation under test
        // must not be needed to build the fixture, or a broken relation would
        // blow up in setup instead of in the HTTP request.
        DB::table('kaveh_organization_user')->insert([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}

/**
 * Host app user model with *no* Kaveh relations of its own — the package must
 * supply `organizations()` itself.
 */
final class DashboardTestUser extends \Illuminate\Foundation\Auth\User
{
    protected $table = 'users';

    protected $guarded = [];
}
