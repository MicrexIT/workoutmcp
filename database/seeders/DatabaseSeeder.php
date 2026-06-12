<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Services\WorkoutMemory\ExerciseCatalogSeederData;
use App\Services\WorkoutMemory\ExerciseResolver;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(ExerciseCatalogSeederData $catalog): void
    {
        $pendingParents = [];

        foreach ($catalog->exercises() as $exerciseData) {
            $exercise = Exercise::query()->updateOrCreate(
                [
                    'user_id' => null,
                    'normalized_name' => self::normalize((string) $exerciseData['name']),
                ],
                [
                    'source' => 'seed',
                    'name' => $exerciseData['name'],
                    'canonical_name' => $exerciseData['name'],
                    'category' => $exerciseData['category'],
                    'granularity' => $exerciseData['granularity'],
                    'tags' => $exerciseData['tags'],
                    'primary_muscles' => $exerciseData['primary_muscles'],
                    'secondary_muscles' => $exerciseData['secondary_muscles'],
                    'primary_body_area' => $exerciseData['primary_body_area'],
                    'equipment' => $exerciseData['equipment'],
                    'tracking_mode' => $exerciseData['tracking_mode'],
                    'unilateral' => $exerciseData['unilateral'],
                    'bodyweight' => $exerciseData['bodyweight'],
                    'external_load_allowed' => $exerciseData['external_load_allowed'],
                    'default_variant_policy' => $exerciseData['default_variant_policy'],
                    'metadata' => $exerciseData['metadata'] ?? ['seed_version' => 'mvp-1'],
                ],
            );

            foreach (array_unique([$exerciseData['name'], ...$exerciseData['aliases']]) as $alias) {
                $exercise->aliases()->updateOrCreate(
                    ['normalized_alias' => self::normalize((string) $alias)],
                    [
                        'alias' => $alias,
                        'source' => $alias === $exerciseData['name'] ? 'seed_name' : 'seed',
                    ],
                );
            }

            if ($exerciseData['parent'] !== null) {
                $pendingParents[$exercise->id] = $exerciseData['parent'];
            }
        }

        foreach ($pendingParents as $exerciseId => $parentName) {
            $parent = Exercise::query()
                ->whereNull('user_id')
                ->where('normalized_name', self::normalize((string) $parentName))
                ->first();

            if ($parent !== null) {
                Exercise::query()->whereKey($exerciseId)->update(['parent_exercise_id' => $parent->id]);
            }
        }
    }

    private static function normalize(string $value): string
    {
        return ExerciseResolver::normalize($value);
    }
}
