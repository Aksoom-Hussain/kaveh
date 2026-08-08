<?php

namespace Kaveh\Server\Support;

use Kaveh\Server\Models\ApiKey;
use Kaveh\Server\Models\Project;
use Illuminate\Support\Facades\Cache;

class ProjectOnboarding
{
    public static function cacheKey(int $userId): string
    {
        return "kaveh.onboarded.{$userId}";
    }

    public static function markSeen(int $userId): void
    {
        Cache::forever(self::cacheKey($userId), true);
    }

    public static function wasSeen(int $userId): bool
    {
        return (bool) Cache::get(self::cacheKey($userId), false);
    }

    /**
     * @return array{project_id: int, project_name: string, api_key: string, server_url: string, package: string}
     */
    public static function payload(Project $project, string $plainApiKey): array
    {
        return [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'api_key' => $plainApiKey,
            'server_url' => rtrim((string) config('app.url'), '/'),
            'package' => 'aksoom-hussain/kaveh',
        ];
    }

    /**
     * Issue a setup key and put onboarding data in the session.
     *
     * @return array{project_id: int, project_name: string, api_key: string, server_url: string, package: string}
     */
    public static function flash(Project $project, string $keyName = 'default'): array
    {
        $issued = ApiKey::issue($project, $keyName);
        $payload = self::payload($project, $issued['plain']);
        session()->flash('kaveh_onboarding', $payload);

        return $payload;
    }

    public static function flashExisting(Project $project, string $plainApiKey): void
    {
        session()->flash('kaveh_onboarding', self::payload($project, $plainApiKey));
    }
}
