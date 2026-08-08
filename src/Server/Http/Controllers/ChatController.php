<?php

namespace Kaveh\Server\Http\Controllers;

use Kaveh\Server\Models\Project;
use Kaveh\Server\Services\Rag\EventRagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $projects = $this->userProjects();
        $project = $this->resolveProject($request, $projects);

        return view('kaveh::chat.index', [
            'project' => $project,
            'projects' => $projects,
            'answer' => null,
            'citations' => [],
            'question' => '',
        ]);
    }

    public function ask(Request $request, EventRagService $rag): View
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $projects = $this->userProjects();
        $project = $this->resolveProject($request, $projects);
        abort_unless($project, 404);

        $result = $rag->ask($project, $data['question']);

        return view('kaveh::chat.index', [
            'project' => $project,
            'projects' => $projects,
            'answer' => $result['answer'],
            'citations' => $result['citations'],
            'question' => $data['question'],
        ]);
    }

    private function userProjects()
    {
        $orgIds = Auth::user()->organizations()->pluck('organizations.id');

        return Project::query()->whereIn('organization_id', $orgIds)->orderBy('name')->get();
    }

    private function resolveProject(Request $request, $projects): ?Project
    {
        if ($projects->isEmpty()) {
            return null;
        }
        $id = $request->integer('project_id') ?: session('kaveh_project_id');
        $project = $projects->firstWhere('id', $id) ?: $projects->first();
        session(['kaveh_project_id' => $project->id]);

        return $project;
    }
}
