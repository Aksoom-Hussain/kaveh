<?php

namespace Kaveh\Server\Http\Controllers;

use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Project;
use Kaveh\Server\Support\DashboardFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var DashboardFilter $filters */
        $filters = $request->attributes->get('kaveh.filters') ?? DashboardFilter::resolve($request);
        $project = $filters->project;
        $since = $filters->since();

        $base = EventRecord::query()->where('project_id', $project?->id);

        $stats = [
            'total' => (clone $base)->where('occurred_at', '>=', $since)->count(),
            'exceptions' => (clone $base)->where('occurred_at', '>=', $since)->where('type', 'exception')->count(),
            'failed_jobs' => (clone $base)->where('occurred_at', '>=', $since)->where('type', 'job')->where('level', 'error')->count(),
            'slow_queries' => (clone $base)->where('occurred_at', '>=', $since)->where('type', 'query')->count(),
            'avg_request_ms' => (clone $base)->where('occurred_at', '>=', $since)->where('type', 'request')->avg('duration_ms'),
        ];

        $byType = EventRecord::query()
            ->select('type', DB::raw('count(*) as aggregate'))
            ->where('project_id', $project?->id)
            ->where('occurred_at', '>=', $since)
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $recent = EventRecord::query()
            ->where('project_id', $project?->id)
            ->where('occurred_at', '>=', $since)
            ->latest('occurred_at')
            ->limit(25)
            ->get();

        return view('kaveh::dashboard.index', [
            'project' => $project,
            'projects' => $filters->projects,
            'stats' => $stats,
            'byType' => $byType,
            'recent' => $recent,
            'range' => $filters->range,
            'rangeLabel' => $filters->label(),
            'metricsPeriod' => $filters->period,
            'metricsRange' => $filters->seconds,
            'metricsHours' => $filters->hours,
        ]);
    }

    public function events(Request $request): View
    {
        /** @var DashboardFilter $filters */
        $filters = $request->attributes->get('kaveh.filters') ?? DashboardFilter::resolve($request);
        $project = $filters->project;

        $query = EventRecord::query()
            ->where('project_id', $project?->id)
            ->where('occurred_at', '>=', $filters->since())
            ->latest('occurred_at');

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }
        if ($level = $request->string('level')->toString()) {
            $query->where('level', $level);
        }
        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        $events = $query->paginate(50)->withQueryString();

        return view('kaveh::dashboard.events', [
            'project' => $project,
            'projects' => $filters->projects,
            'events' => $events,
            'range' => $filters->range,
            'rangeLabel' => $filters->label(),
        ]);
    }

    public function showEvent(Request $request, EventRecord $event): View
    {
        $this->authorizeEvent($event);
        /** @var DashboardFilter $filters */
        $filters = $request->attributes->get('kaveh.filters') ?? DashboardFilter::resolve($request);

        return view('kaveh::dashboard.event', [
            'event' => $event,
            'project' => $event->project,
            'projects' => $filters->projects,
        ]);
    }

    private function authorizeEvent(EventRecord $event): void
    {
        $orgIds = Auth::user()->organizations()->pluck('organizations.id');
        abort_unless(
            Project::query()->whereKey($event->project_id)->whereIn('organization_id', $orgIds)->exists(),
            403
        );
    }
}
