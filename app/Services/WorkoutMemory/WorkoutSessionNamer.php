<?php

namespace App\Services\WorkoutMemory;

use App\Models\WorkoutSession;
use Illuminate\Support\Str;

class WorkoutSessionNamer
{
    /**
     * @var list<string>
     */
    private const PLACEHOLDER_NAMES = [
        'logged workout',
        'workout in progress',
        'workout',
        'training session',
        'session',
    ];

    public function displayName(WorkoutSession $session): string
    {
        $storedName = $this->squishedName($session->name);

        if ($storedName !== null && ! $this->shouldReplace($storedName)) {
            return $storedName;
        }

        if ($session->status === 'in_progress') {
            return $this->fallbackName($session);
        }

        return $this->generatedName($session) ?? $this->fallbackName($session);
    }

    public function applyGeneratedNameIfPlaceholder(WorkoutSession $session): bool
    {
        if (! $this->shouldReplace($session->name)) {
            return false;
        }

        $generatedName = $this->generatedName($session);

        if ($generatedName === null) {
            return false;
        }

        $session->forceFill(['name' => $generatedName])->save();

        return true;
    }

    public function shouldReplace(?string $name): bool
    {
        $storedName = $this->squishedName($name);

        return $storedName === null
            || in_array(Str::lower($storedName), self::PLACEHOLDER_NAMES, true);
    }

    private function generatedName(WorkoutSession $session): ?string
    {
        $session->loadMissing('exercises');

        $exerciseNames = $session->exercises
            ->sortBy('sort_order')
            ->pluck('name_snapshot')
            ->map(fn (?string $name): ?string => $this->squishedName($name))
            ->filter()
            ->unique(fn (string $name): string => Str::lower($name))
            ->values();

        if ($exerciseNames->isEmpty()) {
            return null;
        }

        $name = $exerciseNames->take(3)->implode(' + ');
        $remaining = $exerciseNames->count() - 3;

        if ($remaining > 0) {
            $name .= ' + '.$remaining.' more';
        }

        return Str::limit($name, 160, '');
    }

    private function fallbackName(WorkoutSession $session): string
    {
        return $session->status === 'in_progress' ? 'Workout in progress' : 'Workout';
    }

    private function squishedName(?string $name): ?string
    {
        $squished = (string) Str::of($name ?? '')->squish();

        return $squished === '' ? null : $squished;
    }
}
