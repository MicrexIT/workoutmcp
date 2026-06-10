<?php

namespace App\Models;

use Database\Factories\ExerciseAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exercise_id', 'alias', 'normalized_alias', 'source'])]
class ExerciseAlias extends Model
{
    /** @use HasFactory<ExerciseAliasFactory> */
    use HasFactory;

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
