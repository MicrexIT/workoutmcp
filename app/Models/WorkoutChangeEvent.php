<?php

namespace App\Models;

use Database\Factories\WorkoutChangeEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'workout_session_id',
    'event_type',
    'reason',
    'metadata',
    'idempotency_key',
    'source_message_id',
    'occurred_at',
])]
class WorkoutChangeEvent extends Model
{
    /** @use HasFactory<WorkoutChangeEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }
}
