<?php

namespace App\Services\WorkoutMemory;

class UnitNormalizer
{
    public function normalizeLoad(?float $value, ?string $unit): ?float
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower((string) $unit)) {
            'lb', 'lbs', 'pound', 'pounds' => round($value * 0.45359237, 2),
            default => round($value, 2),
        };
    }

    public function normalizeDistance(?float $value, ?string $unit): ?float
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower((string) $unit)) {
            'km', 'kilometer', 'kilometers' => round($value * 1000, 2),
            'mi', 'mile', 'miles' => round($value * 1609.344, 2),
            default => round($value, 2),
        };
    }

    public function normalizeBodyweight(?float $value, ?string $unit): ?float
    {
        return $this->normalizeLoad($value, $unit);
    }
}
