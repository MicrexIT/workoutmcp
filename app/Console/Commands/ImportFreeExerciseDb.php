<?php

namespace App\Console\Commands;

use App\Services\WorkoutMemory\ExerciseCatalogSeederData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:import-free-exercise-db
    {source : Path to the free-exercise-db dist/exercises.json file}
    {--output=database/seeders/data/imported-exercise-catalog.json : Where to write the transformed catalog}')]
#[Description('Transform the public-domain free-exercise-db dataset into the seeder catalog shape (run once per dataset update; the output JSON is committed)')]
class ImportFreeExerciseDb extends Command
{
    private const CATEGORIES = [
        'strength' => 'strength',
        'powerlifting' => 'strength',
        'olympic weightlifting' => 'strength',
        'strongman' => 'conditioning',
        'plyometrics' => 'conditioning',
        'stretching' => 'mobility',
    ];

    private const EQUIPMENT = [
        'body only' => 'bodyweight',
        'dumbbell' => 'dumbbells',
        'e-z curl bar' => 'ez bar',
        'bands' => 'band',
        'foam roll' => 'foam roller',
    ];

    private const MUSCLES = [
        'abdominals' => 'core',
        'quadriceps' => 'quads',
        'middle back' => 'upper back',
    ];

    public function handle(ExerciseCatalogSeederData $catalog): int
    {
        $sourcePath = (string) $this->argument('source');
        $source = is_file($sourcePath) ? json_decode((string) file_get_contents($sourcePath), true) : null;

        if (! is_array($source) || $source === []) {
            $this->error("Could not read a non-empty exercise list from {$sourcePath}.");

            return self::FAILURE;
        }

        $taken = collect($catalog->curated())
            ->flatMap(fn (array $exercise): array => [$exercise['name'], ...$exercise['aliases']])
            ->map(fn (string $name): string => ExerciseCatalogSeederData::dedupeKey($name))
            ->flip();

        $skippedUnmapped = 0;
        $skippedDuplicates = 0;
        $imported = [];

        foreach ($source as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));
            $category = (string) ($entry['category'] ?? '');

            if ($name === '' || ! array_key_exists($category, self::CATEGORIES)) {
                $skippedUnmapped++;

                continue;
            }

            // Foam-rolling rows ("Calves-SMR", "IT Band-SMR", ...) are recovery
            // work under acronym names nobody logs by; their muscle-word names
            // also collide with bare phrases like "calves".
            if (($entry['equipment'] ?? '') === 'foam roll' || str_contains($name, 'SMR')) {
                $skippedUnmapped++;

                continue;
            }

            $key = ExerciseCatalogSeederData::dedupeKey($name);

            if ($taken->has($key)) {
                $skippedDuplicates++;

                continue;
            }

            $taken->put($key, true);
            $imported[] = $this->transform($entry, $name, self::CATEGORIES[$category]);
        }

        $outputPath = base_path((string) $this->option('output'));

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        file_put_contents(
            $outputPath,
            json_encode($imported, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
        );

        $this->info(sprintf(
            'Wrote %d exercises to %s (skipped %d cardio/unmapped entries and %d duplicates of curated entries).',
            count($imported),
            (string) $this->option('output'),
            $skippedUnmapped,
            $skippedDuplicates,
        ));

        return self::SUCCESS;
    }

    /**
     * Cardio is intentionally unmapped: the dataset's 14 cardio rows use
     * comma names like "Bicycling, Stationary" while the curated catalog
     * already covers those modalities with log-friendly names and aliases.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function transform(array $entry, string $name, string $category): array
    {
        $equipment = collect([(string) ($entry['equipment'] ?? '')])
            ->filter()
            ->map(fn (string $value): string => self::EQUIPMENT[$value] ?? $value)
            ->values()
            ->all();

        $mapMuscles = fn (array $muscles): array => collect($muscles)
            ->map(fn (string $muscle): string => self::MUSCLES[$muscle] ?? $muscle)
            ->values()
            ->all();

        $primaryMuscles = $mapMuscles($entry['primaryMuscles'] ?? []);
        $isBodyweight = $equipment === ['bodyweight'] || $equipment === [];

        return [
            'name' => $name,
            'aliases' => [],
            'source' => 'seed',
            'category' => $category,
            'granularity' => 'canonical',
            'tags' => [$category],
            'primary_muscles' => $primaryMuscles,
            'secondary_muscles' => $mapMuscles($entry['secondaryMuscles'] ?? []),
            'primary_body_area' => $primaryMuscles[0] ?? null,
            'equipment' => $equipment,
            'tracking_mode' => match (true) {
                $category === 'mobility' => 'hold',
                $category === 'conditioning' => 'mixed',
                $isBodyweight => 'reps',
                default => 'load_reps',
            },
            'unilateral' => preg_match('/single-arm|one-arm|single-leg|one-leg|alternat/i', $name) === 1,
            'bodyweight' => $isBodyweight,
            'external_load_allowed' => ! $isBodyweight,
            'parent' => null,
            'default_variant_policy' => 'log_variant',
            'metadata' => [
                'seed_version' => 'free-exercise-db-1',
                'import_source' => 'free-exercise-db',
                'import_id' => (string) ($entry['id'] ?? ''),
            ],
        ];
    }
}
