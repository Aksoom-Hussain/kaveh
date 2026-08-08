<?php

namespace Kaveh\Server\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertRule extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'metric',
        'event_name',
        'threshold',
        'window_minutes',
        'cooldown_minutes',
        'channel',
        'target',
        'enabled',
        'last_fired_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_fired_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function firings(): HasMany
    {
        return $this->hasMany(AlertFiring::class);
    }
}
