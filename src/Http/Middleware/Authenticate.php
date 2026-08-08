<?php

declare(strict_types=1);

namespace Kaveh\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Auth redirect scoped to the Kaveh dashboard (does not override the host app login).
 */
class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('kaveh.login');
    }
}
