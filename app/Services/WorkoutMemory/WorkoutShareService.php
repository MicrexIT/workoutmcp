<?php

namespace App\Services\WorkoutMemory;

use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutShare;
use Illuminate\Support\Str;

class WorkoutShareService
{
    public function __construct(private TrainingSummaryService $summaries) {}

    /**
     * Create a public share link for a completed workout, reusing the active
     * link when the workout is already shared.
     *
     * @return array<string, mixed>
     */
    public function share(User $user, ?int $workoutSessionId = null): array
    {
        $session = $workoutSessionId !== null
            ? WorkoutSession::query()->where('user_id', $user->id)->find($workoutSessionId)
            : WorkoutSession::query()
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->latest('started_at')
                ->first();

        if ($session === null) {
            return [
                'refused' => true,
                'refusal_reason' => $workoutSessionId !== null
                    ? 'Workout not found.'
                    : 'No completed workout to share yet.',
            ];
        }

        if ($session->status !== 'completed') {
            return [
                'refused' => true,
                'refusal_reason' => 'Only completed workouts can be shared.',
                'confirmation_hint' => 'Finish the live session with finish_workout_session first, then share it.',
            ];
        }

        $share = $this->activeShareFor($session);
        $created = false;

        if ($share === null) {
            $share = WorkoutShare::query()->create([
                'user_id' => $user->id,
                'workout_session_id' => $session->id,
                'slug' => $this->uniqueSlug(),
            ]);
            $created = true;
        }

        $summary = $this->summaries->workout($session);

        return [
            'refused' => false,
            'created' => $created,
            'share_url' => $share->publicUrl(),
            'share_text' => $this->shareText($summary, $share->publicUrl()),
            'shared_workout' => [
                'id' => $summary['id'],
                'name' => $summary['name'] ?? null,
            ],
            'note' => 'The link is public for anyone who has it. It can be revoked from the web dashboard and dies if the workout is deleted.',
        ];
    }

    public function activeShareFor(WorkoutSession $session): ?WorkoutShare
    {
        return WorkoutShare::query()
            ->where('workout_session_id', $session->id)
            ->active()
            ->latest('id')
            ->first();
    }

    public function revoke(WorkoutSession $session): bool
    {
        return WorkoutShare::query()
            ->where('workout_session_id', $session->id)
            ->active()
            ->update(['revoked_at' => now()]) > 0;
    }

    /**
     * One compact poster line per exercise, e.g. "5×5 · 80 kg" or "12 / 10 / 8".
     *
     * @param  array{sets?: list<array<string, mixed>>}  $exerciseSummary
     */
    public function compactSetsLine(array $exerciseSummary): string
    {
        $sets = collect($exerciseSummary['sets'] ?? []);

        if ($sets->isEmpty()) {
            return '';
        }

        $reps = $sets->pluck('reps');
        $loads = $sets->pluck('load_kg');
        $durations = $sets->pluck('duration_seconds');

        if ($reps->filter(fn ($value): bool => $value !== null)->isEmpty()
            && $durations->contains(fn ($value): bool => $value !== null)) {
            $totalMinutes = (int) round($durations->sum() / 60);

            return $totalMinutes > 0 ? "{$totalMinutes} min" : $sets->count().' sets';
        }

        $uniformReps = $reps->unique()->count() === 1 && $reps->first() !== null;
        $uniformLoad = $loads->unique()->count() === 1;
        $load = $loads->first();

        if ($uniformReps && $uniformLoad) {
            $line = $sets->count().'×'.$reps->first();

            return $load !== null ? $line.' · '.$this->formatLoad((float) $load).' kg' : $line;
        }

        if ($uniformLoad && $load === null) {
            return $reps->map(fn ($value) => $value ?? '?')->implode(' / ');
        }

        return $sets
            ->map(function (array $set): string {
                $part = (string) ($set['reps'] ?? '?');

                return ($set['load_kg'] ?? null) !== null
                    ? $part.'×'.$this->formatLoad((float) $set['load_kg']).'kg'
                    : $part;
            })
            ->implode(', ');
    }

    /**
     * A ready-to-paste message the assistant can hand to the user.
     *
     * @param  array<string, mixed>  $workoutSummary
     */
    public function shareText(array $workoutSummary, string $url): string
    {
        $lines = collect($workoutSummary['exercises'] ?? [])
            ->map(fn (array $exercise): string => trim($exercise['name'].' '.$this->compactSetsLine($exercise)));

        $extra = $lines->count() > 4 ? ' and '.($lines->count() - 4).' more' : '';

        return ($workoutSummary['name'] ?? 'Workout').': '
            .$lines->take(4)->implode(', ')
            .$extra
            .'. Full log: '.$url;
    }

    private function uniqueSlug(): string
    {
        do {
            $slug = strtolower(Str::random(14));
        } while (WorkoutShare::query()->where('slug', $slug)->exists());

        return $slug;
    }

    private function formatLoad(float $kg): string
    {
        return rtrim(rtrim(number_format($kg, 1, '.', ''), '0'), '.');
    }
}
