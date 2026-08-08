<?php

declare(strict_types=1);

namespace Kaveh\Server\Support;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Kaveh\Server\Models\Project;

final class DashboardFilter
{
    public const RANGES = [
        '1h' => [
            'label' => '1h',
            'seconds' => 3600,
            'hours' => 1,
            'period' => 60,
        ],
        '6h' => [
            'label' => '6h',
            'seconds' => 21600,
            'hours' => 6,
            'period' => 60,
        ],
        '24h' => [
            'label' => '24h',
            'seconds' => 86400,
            'hours' => 24,
            'period' => 360,
        ],
        '7d' => [
            'label' => '7d',
            'seconds' => 604800,
            'hours' => 168,
            'period' => 1440,
        ],
    ];

    /**
     * @param  Collection<int, Project>  $projects
     */
    public function __construct(
        public readonly Collection $projects,
        public readonly ?Project $project,
        public readonly string $range,
        public readonly int $seconds,
        public readonly int $hours,
        public readonly int $period,
    ) {}

    public static function resolve(Request $request): self
    {
        $projects = self::userProjects();

        $project = null;
        if ($projects->isNotEmpty()) {
            $requested = $request->query('project_id', $request->input('project_id'));
            $id = is_numeric($requested) ? (int) $requested : (int) session('kaveh_project_id');
            $project = $projects->first(fn (Project $p) => (int) $p->id === $id) ?: $projects->first();
            session(['kaveh_project_id' => $project->id]);
        }

        $range = (string) ($request->query('range') ?: session('kaveh_range', '24h'));
        if (! isset(self::RANGES[$range])) {
            $range = '24h';
        }
        session(['kaveh_range' => $range]);

        $meta = self::RANGES[$range];

        return new self(
            projects: $projects,
            project: $project,
            range: $range,
            seconds: $meta['seconds'],
            hours: $meta['hours'],
            period: $meta['period'],
        );
    }

    public function since(): CarbonInterface
    {
        return Carbon::now()->subSeconds($this->seconds);
    }

    public function label(): string
    {
        return self::RANGES[$this->range]['label'];
    }

    /**
     * Build a URL for the current page with updated filter query params.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function url(array $overrides = []): string
    {
        $params = array_merge(
            request()->except(['project_id', 'range']),
            [
                'project_id' => $this->project?->id,
                'range' => $this->range,
            ],
            $overrides
        );

        $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');

        return request()->url().(empty($params) ? '' : '?'.http_build_query($params));
    }

    /**
     * @return Collection<int, Project>
     */
    private static function userProjects(): Collection
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        $orgIds = $user->organizations()->pluck('organizations.id');

        return Project::query()->whereIn('organization_id', $orgIds)->orderBy('name')->get();
    }
}
