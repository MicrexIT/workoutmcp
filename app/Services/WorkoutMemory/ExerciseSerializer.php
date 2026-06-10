<?php

namespace App\Services\WorkoutMemory;

use App\Models\Exercise;

class ExerciseSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Exercise $exercise, ?int $usageCount = null, ?string $lastUsedAt = null): array
    {
        $exercise->loadMissing(['aliases', 'parent']);

        return [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'canonical_name' => $exercise->canonical_name,
            'aliases' => $exercise->aliases->pluck('alias')->values()->all(),
            'source' => $exercise->source,
            'category' => $exercise->category,
            'granularity' => $exercise->granularity,
            'tracking_mode' => $exercise->tracking_mode,
            'equipment' => $exercise->equipment ?? [],
            'primary_muscles' => $exercise->primary_muscles ?? [],
            'primary_body_area' => $exercise->primary_body_area,
            'external_load_allowed' => $exercise->external_load_allowed,
            'parent_exercise' => $exercise->parent ? [
                'id' => $exercise->parent->id,
                'name' => $exercise->parent->name,
            ] : null,
            'usage_count' => $usageCount,
            'last_used_at' => $lastUsedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailed(Exercise $exercise): array
    {
        return [
            ...$this->summary($exercise),
            'tags' => $exercise->tags ?? [],
            'secondary_muscles' => $exercise->secondary_muscles ?? [],
            'unilateral' => $exercise->unilateral,
            'bodyweight' => $exercise->bodyweight,
            'variation_notes' => $exercise->variation_notes,
            'default_variant_policy' => $exercise->default_variant_policy,
            'instructions' => $exercise->instructions,
            'safety_notes' => $exercise->safety_notes,
            'metadata' => $exercise->metadata ?? [],
            'created_at' => $exercise->created_at?->toISOString(),
            'updated_at' => $exercise->updated_at?->toISOString(),
        ];
    }
}
