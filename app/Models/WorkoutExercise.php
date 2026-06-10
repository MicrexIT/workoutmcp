<?php

namespace App\Models;

use Database\Factories\WorkoutExerciseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workout_session_id',
    'exercise_id',
    'sort_order',
    'name_snapshot',
    'tracking_mode_snapshot',
    'exercise_resolution_attempt_id',
    'raw_phrase',
    'resolution_type',
    'variant_label',
    'variant_description',
    'prescription',
    'notes',
])]
class WorkoutExercise extends Model
{
    /** @use HasFactory<WorkoutExerciseFactory> */
    use HasFactory;

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function resolutionAttempt(): BelongsTo
    {
        return $this->belongsTo(ExerciseResolutionAttempt::class, 'exercise_resolution_attempt_id');
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)->orderBy('set_number');
    }
}
