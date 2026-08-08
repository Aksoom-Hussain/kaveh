<?php

namespace Kaveh\Server\Http\Controllers;

use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $project = $this->resolveProject($request);

        $since = now()->subHours(24);
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
            ->latest('occurred_at')
            ->limit(25)
            ->get();

        $projects = $this->userProjects();

        return view('kaveh::dashboard.index', compact('project', 'projects', 'stats', 'byType', 'recent'));
    }

    public function events(Request $request): View
    {
        $project = $this->resolveProject($request);
        $query = EventRecord::query()->where('project_id', $project?->id)->latest('occurred_at');

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
        $projects = $this->userProjects();

        return view('kaveh::dashboard.events', compact('project', 'projects', 'events'));
    }

    public function showEvent(Request $request, EventRecord $event): View
    {
        $this->authorizeEvent($event);
        $projects = $this->userProjects();
        $project = $event->project;

        return view('kaveh::dashboard.event', compact('event', 'project', 'projects'));
    }

    private function userProjects()
    {
        $orgIds = Auth::user()->organizations()->pluck('organizations.id');

        return Project::query()->whereIn('organization_id', $orgIds)->orderBy('name')->get();
    }

    private function resolveProject(Request $request): ?Project
    {
        $projects = $this->userProjects();
        if ($projects->isEmpty()) {
            return null;
        }

        $id = $request->integer('project_id') ?: session('kaveh_project_id');
        $project = $projects->firstWhere('id', $id) ?: $projects->first();
        session(['kaveh_project_id' => $project->id]);

        return $project;
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
