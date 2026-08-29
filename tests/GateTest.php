<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Contracts\Auth\Access\Gate;

/**
 * The package must ship a working `viewKaveh` default — the provider previously
 * hung it off the Gate *facade* class, which never resolves from the container,
 * so the gate was never defined and the dashboard denied everyone.
 */
final class GateTest extends TestCase
{
    public function test_it_defines_a_default_view_kaveh_gate(): void
    {
        $gate = $this->app->make(Gate::class);

        $this->assertTrue($gate->has('viewKaveh'));
    }

    public function test_the_default_gate_allows_an_authenticated_user(): void
    {
        $gate = $this->app->make(Gate::class);

        $this->assertTrue($gate->forUser(new \Illuminate\Foundation\Auth\User)->allows('viewKaveh'));
    }

    public function test_it_does_not_clobber_a_gate_the_host_app_defined(): void
    {
        $gate = $this->app->make(Gate::class);
        // Host app (published App\Providers\KavehServiceProvider) locked it down.
        $gate->define('viewKaveh', fn ($user = null) => false);

        // Re-running the package's registration must leave that definition alone.
        $provider = new \Kaveh\KavehServiceProvider($this->app);
        (fn () => $this->registerAuthorization())->call($provider);

        $this->assertFalse($gate->forUser(new \Illuminate\Foundation\Auth\User)->allows('viewKaveh'));
    }
}
