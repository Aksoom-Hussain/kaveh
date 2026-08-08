<?php

namespace Kaveh\Server\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['name', 'slug'];

    public function users(): BelongsToMany
    {
        $userModel = config('kaveh.server.user_model', \App\Models\User::class);

        return $this->belongsToMany($userModel)->withPivot('role')->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
