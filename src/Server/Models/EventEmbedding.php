<?php

namespace Kaveh\Server\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventEmbedding extends Model
{
    protected $fillable = [
        'event_id',
        'project_id',
        'content',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventRecord::class, 'event_id');
    }
}
