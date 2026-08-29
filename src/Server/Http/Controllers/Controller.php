<?php

namespace Kaveh\Server\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Kaveh\Server\Models\Project;

abstract class Controller extends BaseController
{
    /**
     * Project ids visible to the authenticated user (via their organizations).
     *
     * @return Collection<int, int>
     */
    protected function userProjectIds(): Collection
    {
        $user = Auth::user();

        if ($user === null) {
            return collect();
        }

        return Project::query()
            ->whereIn('organization_id', $user->organizations()->pluck('kaveh_organizations.id'))
            ->pluck('id');
    }

    /**
     * Every request-supplied project_id must go through here. 403 if it is not
     * one of the caller's own projects; null passes through untouched so the
     * caller can decide what "no project selected" means.
     */
    protected function authorizeProject(?int $projectId): ?int
    {
        if ($projectId === null) {
            return null;
        }

        abort_unless($this->userProjectIds()->contains($projectId), 403);

        return $projectId;
    }
}
