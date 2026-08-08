<?php

namespace Kaveh\Server\Http\Controllers;

use Kaveh\Server\Models\ApiKey;
use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Project;
use Kaveh\Server\Support\ProjectOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $orgIds = Auth::user()->organizations()->pluck('organizations.id');
        $projects = Project::query()->whereIn('organization_id', $orgIds)->with('apiKeys')->get();

        return view('kaveh::projects.index', compact('projects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $org = Auth::user()->organizations()->firstOrFail();

        $project = Project::query()->create([
            'organization_id' => $org->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'retention_days' => $data['retention_days'] ?? 14,
        ]);

        ProjectOnboarding::flash($project, 'default');

        return redirect()
            ->route('kaveh.projects.index')
            ->with('status', 'Project created. Copy the install commands below.');
    }

    public function createKey(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($project);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $issued = ApiKey::issue($project, $data['name']);
        ProjectOnboarding::flashExisting($project, $issued['plain']);

        return back()->with('status', 'API key issued. Copy the install commands below.');
    }

    public function revokeKey(Project $project, ApiKey $apiKey): RedirectResponse
    {
        $this->authorizeProject($project);
        abort_unless($apiKey->project_id === $project->id, 404);
        $apiKey->forceFill(['revoked_at' => now()])->save();

        return back()->with('status', 'API key revoked.');
    }

    public function destroyKey(Project $project, ApiKey $apiKey): RedirectResponse
    {
        $this->authorizeProject($project);
        abort_unless($apiKey->project_id === $project->id, 404);
        abort_unless($apiKey->revoked_at !== null, 422, 'Revoke the key before deleting it.');

        $apiKey->delete();

        return back()->with('status', 'API key deleted.');
    }

    public function dismissOnboarding(): RedirectResponse
    {
        ProjectOnboarding::markSeen((int) Auth::id());
        session()->forget('kaveh_onboarding');

        return back();
    }

    /**
     * First dashboard visit: show setup if the org has no shipped events yet.
     */
    public static function maybeFlashFirstLoginOnboarding(): void
    {
        $user = Auth::user();
        if (! $user || ProjectOnboarding::wasSeen((int) $user->id) || session()->has('kaveh_onboarding')) {
            return;
        }

        $orgIds = $user->organizations()->pluck('organizations.id');
        if ($orgIds->isEmpty()) {
            return;
        }

        $projects = Project::query()->whereIn('organization_id', $orgIds)->orderBy('id')->get();
        if ($projects->isEmpty()) {
            return;
        }

        $projectIds = $projects->pluck('id');
        if (EventRecord::query()->whereIn('project_id', $projectIds)->exists()) {
            ProjectOnboarding::markSeen((int) $user->id);

            return;
        }

        $project = $projects->first();
        ProjectOnboarding::flash($project, 'onboarding');
        ProjectOnboarding::markSeen((int) $user->id);
    }

    private function authorizeProject(Project $project): void
    {
        $orgIds = Auth::user()->organizations()->pluck('organizations.id');
        abort_unless($orgIds->contains($project->organization_id), 403);
    }
}
