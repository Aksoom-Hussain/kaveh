<?php

namespace Kaveh\Server\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'key_prefix',
        'key_hash',
        'last_used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return array{plain: string, model: self}
     */
    public static function issue(Project $project, string $name): array
    {
        $plain = 'kv_'.Str::random(40);
        $model = self::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'key_prefix' => substr($plain, 0, 10),
            'key_hash' => hash('sha256', $plain),
        ]);

        return ['plain' => $plain, 'model' => $model];
    }

    public static function findActiveByPlain(string $plain): ?self
    {
        return self::query()
            ->where('key_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->first();
    }
}
