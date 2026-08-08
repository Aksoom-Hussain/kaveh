<?php

namespace Kaveh\Server\Http\Controllers;

use Kaveh\Server\Models\AlertRule;
use Kaveh\Server\Models\Project;
use Kaveh\Server\Support\DashboardFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        /** @var DashboardFilter $filters */
        $filters = $request->attributes->get('kaveh.filters') ?? DashboardFilter::resolve($request);
        $project = $filters->project;
        $rules = AlertRule::query()->where('project_id', $project?->id)->latest()->get();

        return view('kaveh::alerts.index', [
            'rules' => $rules,
            'project' => $project,
            'projects' => $filters->projects,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var DashboardFilter $filters */
        $filters = $request->attributes->get('kaveh.filters') ?? DashboardFilter::resolve($request);
        $project = $filters->project;
        abort_unless($project, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'metric' => ['required', 'in:exception_rate,failed_jobs,custom_event'],
            'event_name' => ['nullable', 'string', 'max:180'],
            'threshold' => ['required', 'integer', 'min:1'],
            'window_minutes' => ['required', 'integer', 'min:1'],
            'cooldown_minutes' => ['required', 'integer', 'min:1'],
            'channel' => ['required', 'in:webhook,email'],
            'target' => ['required', 'string', 'max:255'],
        ]);

        AlertRule::query()->create([
            ...$data,
            'project_id' => $project->id,
            'enabled' => true,
        ]);

        return back()->with('status', 'Alert rule created.');
    }

    public function toggle(AlertRule $alert): RedirectResponse
    {
        $this->authorizeRule($alert);
        $alert->forceFill(['enabled' => ! $alert->enabled])->save();

        return back();
    }

    private function authorizeRule(AlertRule $rule): void
    {
        $orgIds = Auth::user()->organizations()->pluck('organizations.id');
        abort_unless(
            Project::query()->whereKey($rule->project_id)->whereIn('organization_id', $orgIds)->exists(),
            403
        );
    }
}
