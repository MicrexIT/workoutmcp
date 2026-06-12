<?php

namespace App\Models;

use Database\Factories\WorkoutShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'workout_session_id',
    'slug',
    'revoked_at',
])]
class WorkoutShare extends Model
{
    /** @use HasFactory<WorkoutShareFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function publicUrl(): string
    {
        return rtrim((string) config('workout_memory.oauth.public_url'), '/').'/w/'.$this->slug;
    }
}
