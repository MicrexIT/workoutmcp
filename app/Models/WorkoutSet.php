<?php

namespace App\Models;

use Database\Factories\WorkoutSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workout_exercise_id',
    'set_number',
    'reps',
    'load_kg',
    'load_type',
    'duration_seconds',
    'distance_meters',
    'rpe',
    'rir',
    'side',
    'success',
    'quality_rating',
    'is_warmup',
    'raw_set_text',
    'custom_metrics',
    'notes',
])]
class WorkoutSet extends Model
{
    /** @use HasFactory<WorkoutSetFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'load_kg' => 'float',
            'distance_meters' => 'float',
            'rpe' => 'float',
            'rir' => 'float',
            'success' => 'boolean',
            'is_warmup' => 'boolean',
            'custom_metrics' => 'array',
        ];
    }

    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class);
    }
}
