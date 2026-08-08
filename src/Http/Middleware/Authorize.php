<?php

declare(strict_types=1);

namespace Kaveh\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;

class Authorize
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(protected Gate $gate)
    {
        //
    }

    /**
     * Handle the incoming request.
     *
     * Same pattern as Laravel Pulse Authorize + viewPulse gate.
     *
     * @see https://github.com/laravel/pulse
     * @see https://github.com/laravel/telescope
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $this->gate->authorize('viewKaveh');

        return $next($request);
    }
}
