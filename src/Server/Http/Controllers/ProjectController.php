<?php

namespace Kaveh\Server\Http\Controllers;

use Kaveh\Server\Models\ApiKey;
use Kaveh\Server\Models\Project;
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

        Project::query()->create([
            'organization_id' => $org->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'retention_days' => $data['retention_days'] ?? 14,
        ]);

        return back()->with('status', 'Project created.');
    }

    public function createKey(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($project);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $issued = ApiKey::issue($project, $data['name']);

        return back()->with('api_key_plain', $issued['plain']);
    }

    public function revokeKey(Project $project, ApiKey $apiKey): RedirectResponse
    {
        $this->authorizeProject($project);
        abort_unless($apiKey->project_id === $project->id, 404);
        $apiKey->forceFill(['revoked_at' => now()])->save();

        return back()->with('status', 'API key revoked.');
    }

    private function authorizeProject(Project $project): void
    {
        $orgIds = Auth::user()->organizations()->pluck('organizations.id');
        abort_unless($orgIds->contains($project->organization_id), 403);
    }
}
