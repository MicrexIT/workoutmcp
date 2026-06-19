<?php

namespace App\Mcp\Tools\Concerns;

trait NormalizesWorkoutSetPayloads
{
    /**
     * Accept compact repeated-set tool payloads and turn them into the detailed
     * set rows used internally. This keeps the MCP boundary forgiving while the
     * domain services continue to receive one normalized array entry per set.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeWorkoutSetPayloads(array $input): array
    {
        if (isset($input['exercises']) && is_array($input['exercises'])) {
            $input['exercises'] = array_map(
                fn (mixed $exercise): mixed => is_array($exercise)
                    ? $this->normalizeWorkoutExerciseSets($exercise)
                    : $exercise,
                $input['exercises'],
            );
        }

        if (isset($input['exercise']) && is_array($input['exercise'])) {
            $input['exercise'] = $this->normalizeWorkoutExerciseSets($input['exercise']);
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $exercise
     * @return array<string, mixed>
     */
    private function normalizeWorkoutExerciseSets(array $exercise): array
    {
        if (array_key_exists('sets', $exercise)) {
            if (is_array($exercise['sets'])) {
                if (array_is_list($exercise['sets'])) {
                    $exercise['sets'] = array_map(
                        fn (mixed $set): mixed => is_array($set) ? $this->normalizeSetAliases($set) : $set,
                        $exercise['sets'],
                    );

                    return $exercise;
                }

                $setTemplate = $exercise['sets'];
                unset($exercise['sets']);

                foreach ($setTemplate as $key => $value) {
                    if (! array_key_exists($key, $exercise)) {
                        $exercise[$key] = $value;
                    }
                }

                if ($this->compactSetCount($exercise) === null && $this->compactSetPayload($setTemplate) !== []) {
                    $exercise['sets'] = [$this->normalizeSetAliases($setTemplate)];

                    return $exercise;
                }
            } elseif (is_int($exercise['sets']) || (is_string($exercise['sets']) && ctype_digit($exercise['sets']))) {
                $exercise['set_count'] ??= $exercise['sets'];
                unset($exercise['sets']);
            }
        }

        $setCount = $this->compactSetCount($exercise);

        if ($setCount === null) {
            return $exercise;
        }

        $exercise['sets'] = array_fill(0, $setCount, $this->compactSetPayload($exercise));

        return $exercise;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function compactSetCount(array $source): ?int
    {
        foreach (['set_count', 'sets_count', 'number_of_sets', 'num_sets', 'count'] as $field) {
            if (! array_key_exists($field, $source)) {
                continue;
            }

            $value = $source[$field];

            if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
                continue;
            }

            $count = (int) $value;

            if ($count >= 1 && $count <= 100) {
                return $count;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $set
     * @return array<string, mixed>
     */
    private function normalizeSetAliases(array $set): array
    {
        foreach ($this->compactSetPayload($set) as $key => $value) {
            if (! array_key_exists($key, $set) || $set[$key] === null || $set[$key] === '') {
                $set[$key] = $value;
            }
        }

        return $set;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function compactSetPayload(array $source): array
    {
        $set = [];

        $this->copyFirstSetValue($set, 'set_number', $source, ['set_number']);
        $this->copyFirstSetValue($set, 'reps', $source, ['reps', 'reps_per_set', 'rep_count']);
        $this->copyFirstSetValue($set, 'load_value', $source, ['load_value', 'weight_value', 'weight', 'load', 'weight_kg', 'weight_lb']);
        $this->copyFirstSetValue($set, 'load_unit', $source, ['load_unit', 'weight_unit']);
        $this->copyFirstSetValue($set, 'load_type', $source, ['load_type']);
        $this->copyFirstSetValue($set, 'duration_seconds', $source, ['duration_seconds', 'seconds']);
        $this->copyFirstSetValue($set, 'distance_value', $source, ['distance_value', 'distance', 'distance_meters', 'distance_km', 'distance_mi']);
        $this->copyFirstSetValue($set, 'distance_unit', $source, ['distance_unit']);
        $this->copyFirstSetValue($set, 'rpe', $source, ['rpe']);
        $this->copyFirstSetValue($set, 'rir', $source, ['rir']);
        $this->copyFirstSetValue($set, 'side', $source, ['side']);
        $this->copyFirstSetValue($set, 'success', $source, ['success']);
        $this->copyFirstSetValue($set, 'quality_rating', $source, ['quality_rating']);
        $this->copyFirstSetValue($set, 'is_warmup', $source, ['is_warmup']);
        $this->copyFirstSetValue($set, 'raw_set_text', $source, ['raw_set_text']);
        $this->copyFirstSetValue($set, 'custom_metrics', $source, ['custom_metrics']);
        $this->copyFirstSetValue($set, 'notes', $source, ['set_notes']);

        if (! isset($set['load_unit']) && isset($set['load_value'])) {
            if ($this->hasFilledSetValue($source, 'weight_kg')) {
                $set['load_unit'] = 'kg';
            } elseif ($this->hasFilledSetValue($source, 'weight_lb')) {
                $set['load_unit'] = 'lb';
            } elseif ($this->hasFilledSetValue($source, 'unit')) {
                $set['load_unit'] = $source['unit'];
            }
        }

        if (! isset($set['duration_seconds'])) {
            foreach (['duration_minutes', 'minutes'] as $field) {
                if ($this->hasFilledSetValue($source, $field) && is_numeric($source[$field])) {
                    $set['duration_seconds'] = (int) round(((float) $source[$field]) * 60);

                    break;
                }
            }
        }

        if (! isset($set['distance_unit']) && isset($set['distance_value'])) {
            if ($this->hasFilledSetValue($source, 'distance_km')) {
                $set['distance_unit'] = 'km';
            } elseif ($this->hasFilledSetValue($source, 'distance_mi')) {
                $set['distance_unit'] = 'mi';
            } elseif ($this->hasFilledSetValue($source, 'distance_meters')) {
                $set['distance_unit'] = 'm';
            }
        }

        return $set;
    }

    /**
     * @param  array<string, mixed>  $set
     * @param  array<string, mixed>  $source
     * @param  list<string>  $fields
     */
    private function copyFirstSetValue(array &$set, string $target, array $source, array $fields): void
    {
        foreach ($fields as $field) {
            if (! $this->hasFilledSetValue($source, $field)) {
                continue;
            }

            $set[$target] = $source[$field];

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function hasFilledSetValue(array $source, string $field): bool
    {
        return array_key_exists($field, $source) && $source[$field] !== null && $source[$field] !== '';
    }
}
