<?php

declare(strict_types=1);

namespace Kaveh\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Kaveh\Server\Support\DashboardFilter;
use Symfony\Component\HttpFoundation\Response;

final class ShareDashboardFilters
{
    public function handle(Request $request, Closure $next): Response
    {
        $filters = DashboardFilter::resolve($request);

        $request->attributes->set('kaveh.filters', $filters);

        View::share('kavehFilters', $filters);
        View::share('project', $filters->project);
        View::share('projects', $filters->projects);
        View::share('kavehRange', $filters->range);

        if ($this->shouldCanonicalizeFilters($request, $filters)) {
            return redirect()->to($filters->url());
        }

        return $next($request);
    }

    private function shouldCanonicalizeFilters(Request $request, DashboardFilter $filters): bool
    {
        if (! $request->isMethod('GET') || ! $filters->project) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson() || $request->routeIs('kaveh.metrics.*', 'kaveh.live.*')) {
            return false;
        }

        // Keep project + range in the URL so the topbar filter always reflects active state.
        $hasProject = $request->query->has('project_id');
        $hasRange = $request->query->has('range');

        return ! $hasProject || ! $hasRange;
    }
}
