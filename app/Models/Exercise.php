<?php

namespace App\Models;

use Database\Factories\ExerciseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'parent_exercise_id',
    'source',
    'name',
    'canonical_name',
    'normalized_name',
    'category',
    'granularity',
    'tags',
    'primary_muscles',
    'secondary_muscles',
    'primary_body_area',
    'equipment',
    'tracking_mode',
    'unilateral',
    'bodyweight',
    'external_load_allowed',
    'variation_notes',
    'default_variant_policy',
    'instructions',
    'safety_notes',
    'metadata',
])]
class Exercise extends Model
{
    /** @use HasFactory<ExerciseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'primary_muscles' => 'array',
            'secondary_muscles' => 'array',
            'equipment' => 'array',
            'unilateral' => 'boolean',
            'bodyweight' => 'boolean',
            'external_load_allowed' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'parent_exercise_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Exercise::class, 'parent_exercise_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ExerciseAlias::class);
    }

    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class);
    }
}
