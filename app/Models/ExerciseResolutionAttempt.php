<?php

namespace App\Models;

use Database\Factories\ExerciseResolutionAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'resolution_id',
    'user_id',
    'raw_phrase',
    'normalized_phrase',
    'context',
    'best_resolution',
    'best_exercise_id',
    'best_confidence',
    'duplicate_risk',
    'candidates',
    'suggested_action',
    'created_exercise_id',
    'expires_at',
])]
class ExerciseResolutionAttempt extends Model
{
    /** @use HasFactory<ExerciseResolutionAttemptFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'best_confidence' => 'float',
            'candidates' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bestExercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'best_exercise_id');
    }

    public function createdExercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'created_exercise_id');
    }
}
