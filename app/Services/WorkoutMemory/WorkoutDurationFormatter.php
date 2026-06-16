<?php

namespace App\Services\WorkoutMemory;

use Illuminate\Support\Str;

class WorkoutDurationFormatter
{
    public function human(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $remaining = max(0, $seconds);
        $hours = intdiv($remaining, 3600);
        $remaining %= 3600;
        $minutes = intdiv($remaining, 60);
        $seconds = $remaining % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $this->unit($hours, 'hour');
        }

        if ($minutes > 0) {
            $parts[] = $this->unit($minutes, 'minute');
        }

        if ($seconds > 0 || $parts === []) {
            $parts[] = $this->unit($seconds, 'second');
        }

        return implode(' ', $parts);
    }

    private function unit(int $value, string $unit): string
    {
        return $value.' '.Str::plural($unit, $value);
    }
}
