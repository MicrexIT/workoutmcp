<?php

namespace App\Models;

use Database\Factories\ExercisePhraseMemoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'exercise_id',
    'phrase',
    'normalized_phrase',
    'variant_label',
    'variant_description',
    'confidence',
    'usage_count',
    'last_used_at',
])]
class ExercisePhraseMemory extends Model
{
    /** @use HasFactory<ExercisePhraseMemoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
