<?php

namespace Kaveh\Server\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventRecord extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'project_id',
        'uuid',
        'type',
        'name',
        'level',
        'environment',
        'hostname',
        'trace_id',
        'user',
        'context',
        'tags',
        'duration_ms',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'user' => 'array',
            'context' => 'array',
            'tags' => 'array',
            'occurred_at' => 'datetime',
            'duration_ms' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(EventEmbedding::class, 'event_id');
    }

    public function searchableText(): string
    {
        $parts = [
            $this->type,
            $this->name,
            $this->level,
            $this->environment,
            is_array($this->tags) ? implode(' ', $this->tags) : '',
            json_encode($this->context) ?: '',
        ];

        return mb_substr(implode(' ', array_filter($parts)), 0, 8000);
    }
}
