<?php

namespace Tests\Feature;

use App\Mcp\Servers\WorkoutMemoryServer;
use App\Mcp\Tools\GetCurrentWorkoutSessionTool;
use App\Mcp\Tools\GetTrainingSummaryTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\ListRecentWorkoutsTool;
use App\Mcp\Tools\RememberExercisePhraseTool;
use App\Mcp\Tools\ResolveExerciseMentionsTool;
use App\Mcp\Tools\SearchExercisesTool;
use App\Models\Exercise;
use App\Models\ExercisePhraseMemory;
use App\Models\ExerciseResolutionAttempt;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\ExerciseCreator;
use App\Services\WorkoutMemory\ExerciseResolver;
use App\Services\WorkoutMemory\TrainingSummaryService;
use App\Services\WorkoutMemory\WorkoutLogger;
use App\Services\WorkoutMemory\WorkoutSessionManager;
use App\Services\WorkoutMemory\WorkoutUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Tests\TestCase;

class WorkoutMemoryMcpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_seed_catalog_contains_required_niche_aliases_and_buckets(): void
    {
        $this->assertGreaterThanOrEqual(150, Exercise::query()->count());
        $this->assertDatabaseHas('exercise_aliases', ['normalized_alias' => ExerciseResolver::normalize('weighted rings MU')]);
        $this->assertDatabaseHas('exercises', ['name' => 'Handstand Accessory Drill', 'granularity' => 'bucket']);
        $this->assertDatabaseHas('exercises', ['name' => 'Compression Drill', 'granularity' => 'bucket']);
        $this->assertDatabaseHas('exercises', ['name' => 'Calf Press on Leg Press', 'granularity' => 'canonical']);
        $this->assertDatabaseHas('exercise_aliases', ['normalized_alias' => ExerciseResolver::normalize('calf press')]);
        $this->assertDatabaseHas('exercise_aliases', ['normalized_alias' => ExerciseResolver::normalize('calves on leg press')]);
        $this->assertDatabaseHas('exercise_aliases', ['normalized_alias' => ExerciseResolver::normalize('weighted pistols')]);
        $this->assertDatabaseHas('exercise_aliases', ['normalized_alias' => ExerciseResolver::normalize('pistol squats')]);

        $pistol = Exercise::query()->where('name', 'Pistol Squat')->firstOrFail();
        $weightedPistol = Exercise::query()->where('name', 'Weighted Pistol Squat')->firstOrFail();
        $this->assertSame('variant', $weightedPistol->granularity);
        $this->assertSame((int) $pistol->id, (int) $weightedPistol->parent_exercise_id);
    }

    public function test_resolver_maps_weighted_rings_mu_to_existing_exercise(): void
    {
        $user = app(CurrentUserResolver::class)->user();

        $result = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'weighted rings MU'],
        ])[0];

        $this->assertSame('alias', $result['resolution']);
        $this->assertSame('use_existing', $result['suggested_action']);
        $this->assertFalse($result['should_create']);
        $this->assertSame('Weighted Ring Muscle-Up', $result['exercise']['name']);
        $this->assertDatabaseHas('exercise_resolution_attempts', ['resolution_id' => $result['resolution_id']]);
    }

    public function test_resolver_maps_common_session_phrases_to_safe_entries(): void
    {
        $user = app(CurrentUserResolver::class)->user();

        $results = collect(app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'spinning', 'context' => '45 minutes of spinning today'],
            ['raw_phrase' => 'handstand drills', 'context' => '20 minutes of handstand drills today'],
            ['raw_phrase' => 'mobility training', 'context' => '10 minutes mobility focused on legs and shoulders'],
        ]))->keyBy('raw_phrase');

        $this->assertSame('Indoor Ride', $results['spinning']['exercise']['name']);
        $this->assertSame('Handstand Accessory Drill', $results['handstand drills']['exercise']['name']);
        $this->assertSame('Mobility Flow', $results['mobility training']['exercise']['name']);
    }

    public function test_resolver_handles_loaded_leg_day_phrases(): void
    {
        $user = app(CurrentUserResolver::class)->user();

        $results = collect(app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'Leg press', 'context' => '40 x 80 kg, 30 x 120 kg, 24 x 160 kg, 20 x 200 kg, 10 x 230 kg'],
            ['raw_phrase' => 'Calves at 230 kg', 'context' => '5 sets x 15 reps x 230 kg on the leg press machine'],
            ['raw_phrase' => 'Weighted pistol squats', 'context' => '3 sets x 3 reps per leg x 10 kg'],
            ['raw_phrase' => 'Handstand drills', 'context' => '15 minutes'],
        ], 'legs + handstand drills'))->keyBy('raw_phrase');

        $this->assertSame('Leg Press', $results['Leg press']['exercise']['name']);
        $this->assertSame('Calf Press on Leg Press', $results['Calves at 230 kg']['exercise']['name']);
        $this->assertSame('Weighted Pistol Squat', $results['Weighted pistol squats']['exercise']['name']);
        $this->assertSame('Handstand Accessory Drill', $results['Handstand drills']['exercise']['name']);
    }

    public function test_resolver_returns_matches_when_evidence_persistence_fails(): void
    {
        $user = app(CurrentUserResolver::class)->user();

        DB::unprepared(<<<'SQL'
CREATE TEMP TRIGGER fail_exercise_resolution_attempt_insert
BEFORE INSERT ON exercise_resolution_attempts
BEGIN
    SELECT RAISE(ABORT, 'blocked');
END;
SQL);

        try {
            $result = app(ExerciseResolver::class)->resolveMentions($user, [
                ['raw_phrase' => 'leg press'],
            ])[0];
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_exercise_resolution_attempt_insert');
        }

        $this->assertSame('Leg Press', $result['exercise']['name']);
        $this->assertNull($result['resolution_id']);
        $this->assertFalse($result['evidence_persisted']);
        $this->assertNotNull($result['evidence_persistence_warning']);
        $this->assertSame('leg press', $result['log_entry_template']['raw_phrase']);
        $this->assertNull($result['log_entry_template']['resolution_id']);
    }

    public function test_resolver_prefers_exact_catalog_alias_over_conflicting_phrase_memory(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $plank = Exercise::query()->where('name', 'Plank')->firstOrFail();

        ExercisePhraseMemory::query()->create([
            'user_id' => $user->id,
            'exercise_id' => $plank->id,
            'phrase' => 'spinning',
            'normalized_phrase' => ExerciseResolver::normalize('spinning'),
            'variant_label' => 'Indoor cycling / spinning',
            'confidence' => 0.98,
            'usage_count' => 1,
            'last_used_at' => now(),
        ]);

        $result = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'spinning'],
        ])[0];

        $this->assertSame('alias', $result['resolution']);
        $this->assertSame('Indoor Ride', $result['exercise']['name']);
        $this->assertNull($result['variant_label']);
    }

    public function test_create_exercise_refuses_likely_duplicate_after_discovery(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'weighted rings MU'],
        ])[0];

        $result = app(ExerciseCreator::class)->create($user, [
            'name' => 'Weighted Rings MU',
            'source_phrase' => 'weighted rings MU',
            'resolution_id' => $resolution['resolution_id'],
            'creation_reason' => 'no_match',
            'category' => 'rings',
            'tracking_mode' => 'load_reps',
            'equipment' => ['rings'],
            'primary_muscles' => ['lats'],
        ]);

        $this->assertTrue($result['refused']);
        $this->assertSame('Weighted Ring Muscle-Up', $result['recommended_existing_exercise']['name']);
    }

    public function test_log_workout_requires_existing_exercises_and_is_idempotent(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $exercise = Exercise::query()->where('name', 'Weighted Ring Muscle-Up')->firstOrFail();
        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'weighted rings MU'],
        ])[0];

        $payload = [
            'name' => 'Upper rings',
            'occurred_at' => '2026-06-08T10:00:00+02:00',
            'timezone' => 'Europe/Paris',
            'kind' => 'strength',
            'idempotency_key' => 'msg-1',
            'source_message_id' => 'source-msg-1',
            'raw_input' => 'weighted rings MU 5x3 with 10kg',
            'exercises' => [[
                'exercise_id' => $exercise->id,
                'resolution_id' => $resolution['resolution_id'],
                'raw_phrase' => 'weighted rings MU',
                'resolution_type' => 'alias',
                'sets' => array_fill(0, 5, [
                    'reps' => 3,
                    'load_value' => 10,
                    'load_unit' => 'kg',
                    'load_type' => 'external',
                ]),
            ]],
        ];

        $first = app(WorkoutLogger::class)->log($user, $payload);
        $second = app(WorkoutLogger::class)->log($user, $payload);

        $this->assertFalse($first['refused']);
        $this->assertTrue($second['idempotent_replay']);
        $this->assertSame($first['saved_session']['id'], $second['saved_session']['id']);
        $this->assertSame(1, WorkoutSession::query()->count());
        $this->assertDatabaseHas('exercise_phrase_memories', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'normalized_phrase' => ExerciseResolver::normalize('weighted rings MU'),
        ]);
    }

    public function test_log_workout_overrides_uncorroborated_exercise_id_with_phrase_evidence(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $plank = Exercise::query()->where('name', 'Plank')->firstOrFail();
        $indoorRide = Exercise::query()->where('name', 'Indoor Ride')->firstOrFail();

        $withoutEvidence = app(WorkoutLogger::class)->log($user, [
            'raw_input' => '45 minutes of spinning',
            'exercises' => [[
                'exercise_id' => $plank->id,
                'raw_phrase' => 'spinning',
                'resolution_type' => 'manual_assumption',
                'sets' => [['duration_seconds' => 2700]],
            ]],
        ]);

        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'spinning'],
        ])[0];

        $withEvidence = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'another 45 minutes of spinning',
            'exercises' => [[
                'exercise_id' => $plank->id,
                'resolution_id' => $resolution['resolution_id'],
                'raw_phrase' => 'spinning',
                'resolution_type' => $resolution['resolution'],
                'sets' => [['duration_seconds' => 2700]],
            ]],
        ]);

        $this->assertFalse($withoutEvidence['refused']);
        $this->assertFalse($withEvidence['refused']);
        $this->assertSame(2, WorkoutSession::query()->count());
        $this->assertSame(2, WorkoutExercise::query()->where('exercise_id', $indoorRide->id)->count());
        $this->assertSame(0, WorkoutExercise::query()->where('exercise_id', $plank->id)->count());

        $this->assertSame('resolved', $withoutEvidence['resolution_outcomes'][0]['method']);
        $this->assertSame('phrase_memory', $withEvidence['resolution_outcomes'][0]['method']);

        foreach ([$withoutEvidence, $withEvidence] as $logged) {
            $this->assertSame([], $logged['assumed_matches']);
            $this->assertSame('Plank', $logged['ignored_exercise_hints'][0]['ignored_exercise_name']);
            $this->assertSame('Indoor Ride', $logged['ignored_exercise_hints'][0]['used_exercise_name']);
        }

        $this->assertDatabaseHas('exercise_phrase_memories', [
            'user_id' => $user->id,
            'exercise_id' => $indoorRide->id,
            'normalized_phrase' => ExerciseResolver::normalize('spinning'),
        ]);
    }

    public function test_log_workout_ignores_position_index_exercise_ids_from_model(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $ringVariantIds = Exercise::query()
            ->whereIn('name', ['Ring Muscle-Up', 'Weighted Ring Muscle-Up', 'Strict Ring Muscle-Up', 'Ring Muscle-Up Negative'])
            ->pluck('id', 'name');

        // Production incident 2026-06-11: the model echoed entry positions 1-4 as
        // exercise_ids, which happened to be the ring muscle-up variants, while every
        // raw_phrase resolved to the right exercise with >= 0.97 confidence.
        $logged = app(WorkoutLogger::class)->log($user, [
            'name' => 'Leg Press, Calves, Pistols & Handstands',
            'kind' => 'strength',
            'raw_input' => 'Leg press 40x80kg, 30x120kg, 24x160kg, 20x200kg, 10x230kg. 5x15 calves at 230kg. 3x3 per leg weighted pistol squats with 10kg. 15 minutes of handstand drills.',
            'exercises' => [
                [
                    'exercise_id' => $ringVariantIds['Ring Muscle-Up'],
                    'raw_phrase' => 'leg press',
                    'sets' => [
                        ['reps' => 40, 'load_value' => 80, 'load_unit' => 'kg'],
                        ['reps' => 30, 'load_value' => 120, 'load_unit' => 'kg'],
                        ['reps' => 24, 'load_value' => 160, 'load_unit' => 'kg'],
                        ['reps' => 20, 'load_value' => 200, 'load_unit' => 'kg'],
                        ['reps' => 10, 'load_value' => 230, 'load_unit' => 'kg'],
                    ],
                ],
                [
                    'exercise_id' => $ringVariantIds['Weighted Ring Muscle-Up'],
                    'raw_phrase' => 'calves',
                    'sets' => array_fill(0, 5, ['reps' => 15, 'load_value' => 230, 'load_unit' => 'kg']),
                ],
                [
                    'exercise_id' => $ringVariantIds['Strict Ring Muscle-Up'],
                    'raw_phrase' => 'weighted pistol squats',
                    'sets' => array_fill(0, 6, ['reps' => 3, 'load_value' => 10, 'load_unit' => 'kg', 'side' => 'alternating']),
                ],
                [
                    'exercise_id' => $ringVariantIds['Ring Muscle-Up Negative'],
                    'raw_phrase' => 'handstand drills',
                    'sets' => [['duration_seconds' => 900]],
                ],
            ],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame(
            ['Leg Press', 'Calf Press on Leg Press', 'Weighted Pistol Squat', 'Handstand Accessory Drill'],
            collect($logged['saved_session']['exercises'])->pluck('name')->all(),
        );
        $this->assertSame(['resolved', 'resolved', 'resolved', 'resolved'], array_column($logged['resolution_outcomes'], 'method'));
        $this->assertSame([], $logged['assumed_matches']);
        $this->assertSame([], $logged['auto_created_exercises']);
        $this->assertSame(
            ['Ring Muscle-Up', 'Weighted Ring Muscle-Up', 'Strict Ring Muscle-Up', 'Ring Muscle-Up Negative'],
            array_column($logged['ignored_exercise_hints'], 'ignored_exercise_name'),
        );
        $this->assertSame(
            ['Leg Press', 'Calf Press on Leg Press', 'Weighted Pistol Squat', 'Handstand Accessory Drill'],
            array_column($logged['ignored_exercise_hints'], 'used_exercise_name'),
        );
        $this->assertDatabaseMissing('workout_exercises', ['name_snapshot' => 'Ring Muscle-Up']);
        $this->assertDatabaseMissing('workout_exercises', ['name_snapshot' => 'Weighted Ring Muscle-Up']);
        $this->assertDatabaseMissing('workout_exercises', ['name_snapshot' => 'Strict Ring Muscle-Up']);
        $this->assertDatabaseMissing('workout_exercises', ['name_snapshot' => 'Ring Muscle-Up Negative']);
    }

    public function test_log_workout_ignores_resolution_ids_paired_with_the_wrong_entries(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $legPress = Exercise::query()->where('name', 'Leg Press')->firstOrFail();
        $resolutions = collect(app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'leg press'],
            ['raw_phrase' => 'handstand drills'],
        ]))->keyBy('raw_phrase');

        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'Leg press 3x10 at 100kg then 15 minutes of handstand drills.',
            'exercises' => [
                [
                    // resolution_id echoed from the other mention must not hijack this entry.
                    'resolution_id' => $resolutions['handstand drills']['resolution_id'],
                    'raw_phrase' => 'leg press',
                    'sets' => array_fill(0, 3, ['reps' => 10, 'load_value' => 100, 'load_unit' => 'kg']),
                ],
                [
                    // Wrong resolution_id and wrong exercise_id together: phrase evidence still wins.
                    'exercise_id' => $legPress->id,
                    'resolution_id' => $resolutions['leg press']['resolution_id'],
                    'raw_phrase' => 'handstand drills',
                    'sets' => [['duration_seconds' => 900]],
                ],
            ],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame(
            ['Leg Press', 'Handstand Accessory Drill'],
            collect($logged['saved_session']['exercises'])->pluck('name')->all(),
        );
        $this->assertSame([], $logged['assumed_matches']);
        $this->assertSame([], $logged['auto_created_exercises']);
        $this->assertCount(1, $logged['ignored_exercise_hints']);
        $this->assertSame('Leg Press', $logged['ignored_exercise_hints'][0]['ignored_exercise_name']);
        $this->assertSame('Handstand Accessory Drill', $logged['ignored_exercise_hints'][0]['used_exercise_name']);
    }

    public function test_log_workout_keeps_explicit_exercise_id_when_phrase_has_no_confident_match(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $plank = Exercise::query()->where('name', 'Plank')->firstOrFail();

        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'usual evening finisher, 3 minutes',
            'exercises' => [[
                'exercise_id' => $plank->id,
                'raw_phrase' => 'usual evening finisher',
                'sets' => [['duration_seconds' => 180]],
            ]],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame('Plank', $logged['resolution_outcomes'][0]['exercise_name']);
        $this->assertSame('assumed', $logged['resolution_outcomes'][0]['method']);
        $this->assertSame('Plank', $logged['assumed_matches'][0]['exercise_name']);
        $this->assertSame([], $logged['ignored_exercise_hints']);
        $this->assertSame([], $logged['auto_created_exercises']);
        $this->assertSame(0, Exercise::query()->where('source', 'chatgpt_auto')->count());
        $this->assertDatabaseHas('workout_exercises', [
            'name_snapshot' => 'Plank',
            'resolution_type' => 'manual_assumption',
        ]);
    }

    public function test_log_workout_resolves_june_9_swim_and_forearm_session_correctly(): void
    {
        // Production incident 2026-06-12 (the June 9 session): with no swimming or
        // forearm strength work in the catalog, character-level fuzz logged
        // "swimming" as Indoor Ride (via the alias "spinning", 0.63) and "reverse
        // forearm curls" as Reverse Nordic Curl (0.75), while model hints pinned
        // the other forearm phrases to Biceps Curl. The expanded catalog plus
        // corroboration and contradiction gating must land every phrase on a real
        // matching exercise and report the contradicting hints as ignored.
        $user = app(CurrentUserResolver::class)->user();
        $bicepsCurl = Exercise::query()->where('name', 'Biceps Curl')->firstOrFail();

        $logged = app(WorkoutLogger::class)->log($user, [
            'name' => 'Swim + Forearms',
            'raw_input' => 'June 9: swam 20 minutes, forearm curls 3x8 12kg/arm, reverse forearm curls 3x8 8kg/arm, forearm twists 3x15 16kg/arm.',
            'exercises' => [
                ['raw_phrase' => 'swimming', 'sets' => [['duration_seconds' => 1200]]],
                ['raw_phrase' => 'forearm curls', 'exercise_id' => $bicepsCurl->id, 'sets' => array_fill(0, 3, ['reps' => 8, 'load_value' => 12, 'load_unit' => 'kg'])],
                ['raw_phrase' => 'reverse forearm curls', 'sets' => array_fill(0, 3, ['reps' => 8, 'load_value' => 8, 'load_unit' => 'kg'])],
                ['raw_phrase' => 'forearm twists', 'exercise_id' => $bicepsCurl->id, 'sets' => array_fill(0, 3, ['reps' => 15, 'load_value' => 16, 'load_unit' => 'kg'])],
            ],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame(
            ['Swimming', 'Wrist Curl', 'Reverse Wrist Curl', 'Forearm Rotation'],
            collect($logged['saved_session']['exercises'])->pluck('name')->all(),
        );
        $this->assertSame([], $logged['auto_created_exercises']);
        $this->assertSame(
            ['Biceps Curl', 'Biceps Curl'],
            array_column($logged['ignored_exercise_hints'], 'ignored_exercise_name'),
        );
    }

    public function test_log_workout_contradicted_hint_without_match_auto_creates_instead_of_keeping_it(): void
    {
        // A hint that contradicts the phrase\'s body area or modality must never
        // be kept as a flagged assumption when the server has nothing confident:
        // that is how "forearm twists" became Biceps Curl. The phrase resolves on
        // its own (flagged auto-creation as last resort) and the hint is reported.
        $user = app(CurrentUserResolver::class)->user();
        $indoorRide = Exercise::query()->where('name', 'Indoor Ride')->firstOrFail();

        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'aqua jog intervals 20 minutes',
            'exercises' => [[
                'raw_phrase' => 'aqua jog intervals',
                'exercise_id' => $indoorRide->id,
                'sets' => [['duration_seconds' => 1200]],
            ]],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame('Aqua Jog Intervals', $logged['saved_session']['exercises'][0]['name']);
        $this->assertSame('Aqua Jog Intervals', $logged['auto_created_exercises'][0]['exercise']['name']);
        $this->assertSame('Indoor Ride', $logged['ignored_exercise_hints'][0]['ignored_exercise_name']);
    }

    public function test_resolver_blocks_cross_body_part_and_cross_modality_fuzz(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $resolver = app(ExerciseResolver::class);

        $reverseNordic = Exercise::query()->where('name', 'Reverse Nordic Curl')->firstOrFail();
        $indoorRide = Exercise::query()->where('name', 'Indoor Ride')->firstOrFail();
        $chestToBar = Exercise::query()->where('name', 'Chest-to-Bar Pull-Up')->firstOrFail();
        $sprint = Exercise::query()->where('name', 'Sprint')->firstOrFail();
        $deadlift = Exercise::query()->where('name', 'Conventional Deadlift')->firstOrFail();

        $this->assertTrue($resolver->conflictsWithPhrase('reverse forearm curls', $reverseNordic));
        $this->assertTrue($resolver->conflictsWithPhrase('swimming', $indoorRide));
        $this->assertFalse($resolver->conflictsWithPhrase('chest to bar pull ups', $chestToBar));
        $this->assertFalse($resolver->conflictsWithPhrase('bike sprint', $sprint));
        $this->assertFalse($resolver->conflictsWithPhrase('trap bar deadlift', $deadlift));

        // Character soup alone can no longer cross the assumable bar: "swimming"
        // vs the alias "spinning" scored 0.63 in production.
        $indoorRideCandidate = collect($resolver->resolveMentions($user, [['raw_phrase' => 'swimming']])[0]['candidates'])
            ->first(fn (array $candidate): bool => $candidate['exercise']['name'] === 'Indoor Ride');
        $this->assertTrue($indoorRideCandidate === null || $indoorRideCandidate['confidence'] < 0.60);
    }

    public function test_log_workout_records_spinning_handstand_and_mobility_with_resolution_evidence(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $resolutions = collect(app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'spinning', 'context' => '45 minutes of spinning today'],
            ['raw_phrase' => 'handstand drills', 'context' => '20 minutes of handstand drills today'],
            ['raw_phrase' => 'mobility training', 'context' => '10 minutes mobility focused on legs and shoulders'],
        ]))->keyBy('raw_phrase');

        $logged = app(WorkoutLogger::class)->log($user, [
            'name' => 'Spinning, handstand drills, and mobility',
            'occurred_at' => '2026-06-09T00:00:00+02:00',
            'timezone' => 'Europe/Paris',
            'kind' => 'conditioning / skill / mobility',
            'raw_input' => 'Today I did 45 minutes of spinning and then 20 minutes of handstand drills and 10 minutes of mobility training focusing on legs and shoulders.',
            'exercises' => [
                [
                    'exercise_id' => $resolutions['spinning']['exercise']['id'],
                    'resolution_id' => $resolutions['spinning']['resolution_id'],
                    'raw_phrase' => 'spinning',
                    'resolution_type' => $resolutions['spinning']['resolution'],
                    'notes' => '45 minutes of spinning.',
                    'sets' => [['duration_seconds' => 2700]],
                ],
                [
                    'exercise_id' => $resolutions['handstand drills']['exercise']['id'],
                    'resolution_id' => $resolutions['handstand drills']['resolution_id'],
                    'raw_phrase' => 'handstand drills',
                    'resolution_type' => $resolutions['handstand drills']['resolution'],
                    'variant_label' => 'handstand drills',
                    'notes' => '20 minutes of handstand drills.',
                    'sets' => [['duration_seconds' => 1200]],
                ],
                [
                    'exercise_id' => $resolutions['mobility training']['exercise']['id'],
                    'resolution_id' => $resolutions['mobility training']['resolution_id'],
                    'raw_phrase' => 'mobility training',
                    'resolution_type' => $resolutions['mobility training']['resolution'],
                    'variant_label' => 'legs and shoulders',
                    'notes' => '10 minutes mobility focused on legs and shoulders.',
                    'sets' => [['duration_seconds' => 600]],
                ],
            ],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame(
            ['Indoor Ride', 'Handstand Accessory Drill', 'Mobility Flow'],
            collect($logged['saved_session']['exercises'])->pluck('name')->all(),
        );
        $this->assertSame(['evidence', 'evidence', 'evidence'], array_column($logged['resolution_outcomes'], 'method'));
        $this->assertSame([], $logged['auto_created_exercises']);
        $this->assertSame([], $logged['assumed_matches']);
        $this->assertDatabaseMissing('workout_exercises', ['name_snapshot' => 'Plank']);
        $this->assertDatabaseMissing('workout_exercises', ['name_snapshot' => 'Pike Compression Lift']);
        $this->assertDatabaseMissing('workout_exercises', ['name_snapshot' => 'Bike Erg']);
    }

    public function test_log_workout_handles_unknown_ids_buckets_and_missing_variant_labels(): void
    {
        $user = app(CurrentUserResolver::class)->user();

        $structurallyInvalid = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'unknown movement 3x10',
            'exercises' => [[
                'exercise_id' => 99999,
                'resolution_type' => 'create_suggestion',
                'sets' => [['reps' => 10]],
            ]],
        ]);

        $this->assertTrue($structurallyInvalid['refused']);
        $this->assertContains(
            'Entry needs a raw_phrase or a known exercise_id.',
            array_column($structurallyInvalid['unresolved_or_ambiguous_items'], 'message'),
        );

        $unknownIdWithPhrase = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'leg press 3x10 at 100kg',
            'exercises' => [[
                'exercise_id' => 99999,
                'raw_phrase' => 'leg press',
                'sets' => array_fill(0, 3, ['reps' => 10, 'load_value' => 100, 'load_unit' => 'kg']),
            ]],
        ]);

        $this->assertFalse($unknownIdWithPhrase['refused']);
        $this->assertSame('Leg Press', $unknownIdWithPhrase['resolution_outcomes'][0]['exercise_name']);

        $bucketWithoutVariantDetails = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'compression work 10 minutes',
            'exercises' => [[
                'raw_phrase' => 'compression work',
                'sets' => [['duration_seconds' => 600]],
            ]],
        ]);

        $this->assertFalse($bucketWithoutVariantDetails['refused']);
        $this->assertSame('Compression Drill', $bucketWithoutVariantDetails['resolution_outcomes'][0]['exercise_name']);
        $this->assertTrue($bucketWithoutVariantDetails['resolution_outcomes'][0]['variant_label_autofilled']);
        $this->assertDatabaseHas('workout_exercises', [
            'name_snapshot' => 'Compression Drill',
            'variant_label' => 'compression work',
        ]);

        $explicitResolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'compression work'],
        ])[0];
        $bucket = Exercise::query()->findOrFail($explicitResolution['recommended_bucket_exercise']['id'] ?? $explicitResolution['exercise']['id']);

        $explicitBucketVariant = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'compression work: one leg and pancake lifts',
            'exercises' => [[
                'exercise_id' => $bucket->id,
                'resolution_id' => $explicitResolution['resolution_id'],
                'raw_phrase' => 'compression work',
                'resolution_type' => $explicitResolution['resolution'],
                'variant_label' => 'seated and pancake compression lifts',
                'variant_description' => 'one-leg seated, both legs, pancake one-leg, pancake both legs',
                'sets' => [['duration_seconds' => 600]],
            ]],
        ]);

        $this->assertFalse($explicitBucketVariant['refused']);
        $this->assertDatabaseHas('workout_exercises', [
            'exercise_id' => $bucket->id,
            'variant_label' => 'seated and pancake compression lifts',
        ]);
    }

    public function test_log_workout_regression_logs_entire_original_session_despite_sloppy_payload(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $legPress = Exercise::query()->where('name', 'Leg Press')->firstOrFail();
        $calfPress = Exercise::query()->where('name', 'Calf Press on Leg Press')->firstOrFail();
        $weightedPistol = Exercise::query()->where('name', 'Weighted Pistol Squat')->firstOrFail();

        $logged = app(WorkoutLogger::class)->log($user, [
            'name' => 'Leg day',
            'kind' => 'strength',
            'raw_input' => 'Leg press 40x80kg, 30x120kg, 24x160kg, 20x200kg, 10x230kg. Then 5 sets of 15 reps of calves at 230kg. Then 3 times 3 reps per leg of weighted pistol squats with 10kg. Finished with 15 minutes of handstand drills.',
            'exercises' => [
                [
                    // Canonical-name echo instead of the user's wording: previously fatal.
                    'exercise_id' => $legPress->id,
                    'raw_phrase' => 'Leg Press',
                    'resolution_type' => 'exact',
                    'sets' => [
                        ['reps' => 40, 'load_value' => 80, 'load_unit' => 'kg'],
                        ['reps' => 30, 'load_value' => 120, 'load_unit' => 'kg'],
                        ['reps' => 24, 'load_value' => 160, 'load_unit' => 'kg'],
                        ['reps' => 20, 'load_value' => 200, 'load_unit' => 'kg'],
                        ['reps' => 10, 'load_value' => 230, 'load_unit' => 'kg'],
                    ],
                ],
                [
                    // Bare phrase, no exercise_id, wrong resolution_type: previously fatal.
                    'raw_phrase' => 'calves',
                    'resolution_type' => 'bucket',
                    'sets' => array_fill(0, 5, ['reps' => 15, 'load_value' => 230, 'load_unit' => 'kg']),
                ],
                [
                    'raw_phrase' => 'weighted pistol squats',
                    'sets' => array_fill(0, 6, ['reps' => 3, 'load_value' => 10, 'load_unit' => 'kg', 'side' => 'alternating']),
                ],
                [
                    // Duration-only bucket entry without variant details: previously fatal.
                    'raw_phrase' => 'handstand drills',
                    'sets' => [['duration_seconds' => 900]],
                ],
            ],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame(
            ['Leg Press', 'Calf Press on Leg Press', 'Weighted Pistol Squat', 'Handstand Accessory Drill'],
            collect($logged['saved_session']['exercises'])->pluck('name')->all(),
        );
        $this->assertSame(['exact', 'resolved', 'resolved', 'resolved'], array_column($logged['resolution_outcomes'], 'method'));
        $this->assertSame([], $logged['auto_created_exercises']);
        $this->assertSame([], $logged['assumed_matches']);
        $this->assertSame(5, WorkoutSet::query()->where('reps', 15)->where('load_kg', 230)->count());
        $this->assertDatabaseHas('workout_exercises', [
            'name_snapshot' => 'Handstand Accessory Drill',
            'variant_label' => 'handstand drills',
        ]);
        $this->assertDatabaseHas('exercise_phrase_memories', [
            'user_id' => $user->id,
            'exercise_id' => $calfPress->id,
            'normalized_phrase' => ExerciseResolver::normalize('calves'),
        ]);
        $this->assertDatabaseHas('exercise_phrase_memories', [
            'user_id' => $user->id,
            'exercise_id' => $weightedPistol->id,
            'normalized_phrase' => ExerciseResolver::normalize('weighted pistol squats'),
        ]);
    }

    public function test_log_workout_auto_creates_unknown_exercise_as_flagged_last_resort(): void
    {
        $user = app(CurrentUserResolver::class)->user();

        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'banded ankle teeter 3x5 at 16kg',
            'exercises' => [[
                'raw_phrase' => 'banded ankle teeter',
                'sets' => array_fill(0, 3, ['reps' => 5, 'load_value' => 16, 'load_unit' => 'kg']),
            ]],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame('auto_created', $logged['resolution_outcomes'][0]['method']);
        $this->assertSame('Banded Ankle Teeter', $logged['auto_created_exercises'][0]['exercise']['name']);
        $this->assertTrue($logged['auto_created_exercises'][0]['needs_review']);

        $exercise = Exercise::query()->where('name', 'Banded Ankle Teeter')->firstOrFail();
        $this->assertSame('chatgpt_auto', $exercise->source);
        $this->assertSame((int) $user->id, (int) $exercise->user_id);
        $this->assertSame('load_reps', $exercise->tracking_mode);
        $this->assertTrue((bool) ($exercise->metadata['auto_created'] ?? false));
        $this->assertTrue((bool) ($exercise->metadata['needs_review'] ?? false));
        $this->assertDatabaseHas('exercise_aliases', [
            'exercise_id' => $exercise->id,
            'normalized_alias' => ExerciseResolver::normalize('banded ankle teeter'),
        ]);
        $this->assertDatabaseHas('exercise_phrase_memories', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'normalized_phrase' => ExerciseResolver::normalize('banded ankle teeter'),
        ]);

        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'banded ankle teeter'],
        ])[0];
        $this->assertSame('Banded Ankle Teeter', $resolution['exercise']['name']);
    }

    public function test_log_workout_dedupes_repeated_unknown_phrase_within_request(): void
    {
        $user = app(CurrentUserResolver::class)->user();

        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'teeter taps 2x20 per side',
            'exercises' => [
                ['raw_phrase' => 'teeter taps', 'notes' => 'left side', 'sets' => [['reps' => 20]]],
                ['raw_phrase' => 'teeter taps', 'notes' => 'right side', 'sets' => [['reps' => 20]]],
            ],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame(1, Exercise::query()->where('name', 'Teeter Taps')->count());
        $this->assertCount(1, $logged['auto_created_exercises']);
        $this->assertSame(['auto_created', 'phrase_memory'], array_column($logged['resolution_outcomes'], 'method'));
        $this->assertSame(2, WorkoutExercise::query()->where('name_snapshot', 'Teeter Taps')->count());
    }

    public function test_remember_exercise_phrase_round_trip(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $seated = Exercise::query()->where('name', 'Seated Calf Raise')->firstOrFail();

        WorkoutMemoryServer::tool(RememberExercisePhraseTool::class, [
            'phrase' => 'calves',
            'exercise_id' => $seated->id,
        ])->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('ok', true)
            ->where('phrase_memory.exercise_id', $seated->id)
            ->where('exercise.name', 'Seated Calf Raise')
            ->etc());

        $this->assertDatabaseHas('exercise_phrase_memories', [
            'user_id' => $user->id,
            'exercise_id' => $seated->id,
            'normalized_phrase' => ExerciseResolver::normalize('calves'),
        ]);

        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'calves'],
        ])[0];

        $this->assertSame('phrase_memory', $resolution['resolution']);
        $this->assertSame('Seated Calf Raise', $resolution['exercise']['name']);
    }

    public function test_resolver_dedupes_candidates_and_returns_log_entry_template(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $bucket = Exercise::query()->where('name', 'Handstand Accessory Drill')->firstOrFail();

        $result = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'handstand drills'],
        ])[0];

        $candidateIds = array_map(fn (array $candidate): int => (int) $candidate['exercise']['id'], $result['candidates']);
        $this->assertSame($candidateIds, array_values(array_unique($candidateIds)));

        $this->assertSame($result['resolution'], $result['resolution_type']);
        $this->assertTrue($result['requires_variant_details']);
        $this->assertSame([
            'raw_phrase' => 'handstand drills',
            'exercise_id' => $bucket->id,
            'resolution_id' => $result['resolution_id'],
            'variant_label' => null,
        ], $result['log_entry_template']);

        $attempt = ExerciseResolutionAttempt::query()->where('resolution_id', $result['resolution_id'])->firstOrFail();
        $this->assertTrue($attempt->expires_at->greaterThan(now()->addHours(23)));
    }

    public function test_append_workout_exercise_resolves_bare_phrase_without_exercise_id(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $manager = app(WorkoutSessionManager::class);

        $manager->start($user, [
            'name' => 'Live legs',
            'raw_input' => 'Starting legs.',
            'idempotency_key' => 'live-start-bare',
        ]);

        $payload = [
            'raw_input' => 'Log leg press 3x10 at 100kg.',
            'idempotency_key' => 'live-append-bare-leg-press',
            'exercise' => [
                'raw_phrase' => 'leg press',
                'sets' => array_fill(0, 3, [
                    'reps' => 10,
                    'load_value' => 100,
                    'load_unit' => 'kg',
                    'load_type' => 'external',
                ]),
            ],
        ];

        $appended = $manager->appendExercise($user, $payload);
        $replayed = $manager->appendExercise($user, $payload);

        $this->assertFalse($appended['refused']);
        $this->assertSame('Leg Press', $appended['resolution_outcomes'][0]['exercise_name']);
        $this->assertTrue($replayed['idempotent_replay']);
    }

    public function test_update_workout_add_exercise_resolves_and_can_auto_create(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $ringDip = Exercise::query()->where('name', 'Ring Dip')->firstOrFail();
        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'ring dips 1x8',
            'exercises' => [[
                'exercise_id' => $ringDip->id,
                'sets' => [['reps' => 8]],
            ]],
        ]);

        $updated = app(WorkoutUpdater::class)->update($user, [
            'workout_id' => $logged['saved_session']['id'],
            'operations' => [
                [
                    'type' => 'add_exercise',
                    'raw_phrase' => 'leg press',
                    'sets' => [['reps' => 10, 'load_value' => 100, 'load_unit' => 'kg']],
                ],
                [
                    'type' => 'add_exercise',
                    'raw_phrase' => 'zercher yoke carry',
                    'sets' => [['reps' => 5, 'load_value' => 16, 'load_unit' => 'kg']],
                ],
            ],
        ]);

        $this->assertFalse($updated['refused']);
        $this->assertSame(['resolved', 'auto_created'], array_column($updated['resolution_outcomes'], 'method'));
        $this->assertSame('Zercher Yoke Carry', $updated['auto_created_exercises'][0]['exercise']['name']);
        $this->assertSame(
            ['Ring Dip', 'Leg Press', 'Zercher Yoke Carry'],
            collect($updated['updated_workout']['exercises'])->pluck('name')->all(),
        );
    }

    public function test_recent_workouts_exercise_history_and_training_summary_are_returned(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $exercise = Exercise::query()->where('name', 'Ring Dip')->firstOrFail();

        app(WorkoutLogger::class)->log($user, [
            'name' => 'Push accessories',
            'kind' => 'strength',
            'raw_input' => 'ring dips 4x8',
            'exercises' => [[
                'exercise_id' => $exercise->id,
                'raw_phrase' => 'ring dips',
                'resolution_type' => 'alias',
                'sets' => array_fill(0, 4, ['reps' => 8, 'load_type' => 'bodyweight']),
            ]],
        ]);

        $summaries = app(TrainingSummaryService::class);

        $this->assertCount(1, $summaries->recentWorkouts($user));
        $this->assertSame('Ring Dip', $summaries->exerciseHistory($user, $exercise->id)['exercise']['name']);
        $this->assertSame(1, $summaries->trainingSummary($user)['recent_frequency']['session_count']);
    }

    public function test_active_session_collects_story_and_incremental_exercises_idempotently(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $legPress = Exercise::query()->where('name', 'Leg Press')->firstOrFail();
        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'leg press'],
        ])[0];
        $manager = app(WorkoutSessionManager::class);

        $started = $manager->start($user, [
            'name' => 'Leg day live',
            'kind' => 'strength',
            'raw_input' => 'Starting legs.',
            'idempotency_key' => 'live-start-1',
        ]);

        $story = $manager->appendStory($user, [
            'story' => 'Started leg press.',
            'raw_input' => 'I am doing leg press now.',
            'idempotency_key' => 'live-story-1',
        ]);

        $appendPayload = [
            'raw_input' => 'Log leg press 4x10 at 120kg.',
            'idempotency_key' => 'live-append-leg-press',
            'exercise' => [
                'exercise_id' => $legPress->id,
                'resolution_id' => $resolution['resolution_id'],
                'raw_phrase' => 'leg press',
                'resolution_type' => $resolution['resolution'],
                'sets' => array_fill(0, 4, [
                    'reps' => 10,
                    'load_value' => 120,
                    'load_unit' => 'kg',
                    'load_type' => 'external',
                ]),
            ],
        ];

        $appended = $manager->appendExercise($user, $appendPayload);
        $replayed = $manager->appendExercise($user, $appendPayload);

        $this->assertFalse($started['refused']);
        $this->assertFalse($story['refused']);
        $this->assertFalse($appended['refused']);
        $this->assertTrue($replayed['idempotent_replay']);
        $this->assertSame($appended['append_event']['id'], $replayed['append_event']['id']);
        $this->assertSame(1, WorkoutSession::query()->count());
        $this->assertDatabaseHas('workout_sessions', [
            'id' => $started['active_session']['id'],
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('workout_change_events', [
            'workout_session_id' => $started['active_session']['id'],
            'event_type' => 'story',
            'idempotency_key' => 'live-story-1',
        ]);
        $this->assertDatabaseHas('workout_change_events', [
            'workout_session_id' => $started['active_session']['id'],
            'event_type' => 'exercise_appended',
            'idempotency_key' => 'live-append-leg-press',
        ]);
        $this->assertCount(1, $appended['session']['exercises']);
        $this->assertCount(4, $appended['session']['exercises'][0]['sets']);
    }

    public function test_append_can_target_recent_completed_last_session_without_user_knowing_id(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $ringDip = Exercise::query()->where('name', 'Ring Dip')->firstOrFail();
        $legPress = Exercise::query()->where('name', 'Leg Press')->firstOrFail();
        $logged = app(WorkoutLogger::class)->log($user, [
            'name' => 'Just logged mixed session',
            'raw_input' => 'ring dips 1x8',
            'exercises' => [[
                'exercise_id' => $ringDip->id,
                'raw_phrase' => 'Ring Dip',
                'resolution_type' => 'exact',
                'sets' => [['reps' => 8]],
            ]],
        ]);
        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'leg press'],
        ])[0];

        $appended = app(WorkoutSessionManager::class)->appendExercise($user, [
            'target_session' => 'latest_completed',
            'raw_input' => 'Also add leg press 2x10 at 100kg to the last session.',
            'idempotency_key' => 'append-to-last-session',
            'exercise' => [
                'exercise_id' => $legPress->id,
                'resolution_id' => $resolution['resolution_id'],
                'raw_phrase' => 'leg press',
                'resolution_type' => $resolution['resolution'],
                'sets' => array_fill(0, 2, [
                    'reps' => 10,
                    'load_value' => 100,
                    'load_unit' => 'kg',
                    'load_type' => 'external',
                ]),
            ],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertFalse($appended['refused']);
        $this->assertSame('latest_completed', $appended['target_resolution']);
        $this->assertSame($logged['saved_session']['id'], $appended['session']['id']);
        $this->assertSame('completed', $appended['session']['status']);
        $this->assertSame(['Ring Dip', 'Leg Press'], collect($appended['session']['exercises'])->pluck('name')->all());
    }

    public function test_finish_works_on_a_stale_active_session(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $manager = app(WorkoutSessionManager::class);

        $started = $manager->start($user, [
            'name' => 'Forgotten legs',
            'occurred_at' => now()->subHours(20)->toISOString(),
            'idempotency_key' => 'stale-start',
        ]);

        $finished = $manager->finish($user, [
            'idempotency_key' => 'stale-finish',
            'perceived_effort' => 7,
        ]);

        $this->assertFalse($started['refused']);
        $this->assertFalse($finished['refused']);
        $this->assertSame($started['active_session']['id'], $finished['session']['id']);
        $this->assertSame('completed', $finished['session']['status']);
    }

    public function test_finish_without_active_session_points_to_latest_completed(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'ring dips 1x8',
            'exercises' => [['raw_phrase' => 'ring dips', 'sets' => [['reps' => 8]]]],
        ]);

        $finished = app(WorkoutSessionManager::class)->finish($user, [
            'idempotency_key' => 'finish-nothing',
        ]);

        $this->assertTrue($finished['refused']);
        $this->assertSame('No active workout session to finish.', $finished['refusal_reason']);
        $this->assertSame($logged['saved_session']['id'], $finished['latest_completed_session']['id']);
    }

    public function test_append_auto_finishes_stale_active_session_and_starts_fresh(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $manager = app(WorkoutSessionManager::class);

        $started = $manager->start($user, [
            'name' => 'Abandoned session',
            'occurred_at' => now()->subHours(20)->toISOString(),
            'idempotency_key' => 'abandoned-start',
        ]);

        $appended = $manager->appendExercise($user, [
            'raw_input' => 'Log leg press 3x10 at 100kg.',
            'idempotency_key' => 'fresh-append',
            'exercise' => [
                'raw_phrase' => 'leg press',
                'sets' => array_fill(0, 3, ['reps' => 10, 'load_value' => 100, 'load_unit' => 'kg']),
            ],
        ]);

        $this->assertFalse($appended['refused']);
        $this->assertSame('auto_started_active', $appended['target_resolution']);
        $this->assertSame($started['active_session']['id'], $appended['auto_finished_stale_session']['id']);
        $this->assertNotSame($started['active_session']['id'], $appended['session']['id']);
        $this->assertSame('in_progress', $appended['session']['status']);
        $this->assertDatabaseHas('workout_sessions', [
            'id' => $started['active_session']['id'],
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('workout_change_events', [
            'workout_session_id' => $started['active_session']['id'],
            'event_type' => 'auto_finished',
        ]);
    }

    public function test_log_workout_overlapping_active_session_requires_confirmation(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $manager = app(WorkoutSessionManager::class);
        $started = $manager->start($user, ['name' => 'Live legs', 'idempotency_key' => 'live-start']);

        $payload = [
            'raw_input' => 'Did 5x5 bench at 80kg, done for today.',
            'exercises' => [[
                'raw_phrase' => 'bench press',
                'sets' => array_fill(0, 5, ['reps' => 5, 'load_value' => 80, 'load_unit' => 'kg']),
            ]],
        ];

        $refused = app(WorkoutLogger::class)->log($user, $payload);

        $this->assertTrue($refused['refused']);
        $this->assertTrue($refused['needs_confirmation']);
        $this->assertSame($started['active_session']['id'], $refused['active_session']['id']);
        $this->assertSame(1, WorkoutSession::query()->count());

        $confirmed = app(WorkoutLogger::class)->log($user, [
            ...$payload,
            'user_confirmed_separate_workout' => true,
        ]);

        $this->assertFalse($confirmed['refused']);
        $this->assertSame(2, WorkoutSession::query()->count());
        $this->assertDatabaseHas('workout_sessions', [
            'id' => $started['active_session']['id'],
            'status' => 'in_progress',
        ]);
    }

    public function test_log_workout_predating_the_active_session_needs_no_confirmation(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        app(WorkoutSessionManager::class)->start($user, ['name' => 'Evening session', 'idempotency_key' => 'evening-start']);

        $logged = app(WorkoutLogger::class)->log($user, [
            'occurred_at' => now()->subHours(6)->toISOString(),
            'raw_input' => 'This morning: ring dips 3x8.',
            'exercises' => [['raw_phrase' => 'ring dips', 'sets' => array_fill(0, 3, ['reps' => 8])]],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame(2, WorkoutSession::query()->count());
    }

    public function test_log_workout_confirmed_separate_auto_finishes_stale_active(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $manager = app(WorkoutSessionManager::class);
        $started = $manager->start($user, [
            'name' => 'Stuck session',
            'occurred_at' => now()->subHours(20)->toISOString(),
            'idempotency_key' => 'stuck-start',
        ]);

        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'Ring dips 3x8, separate from whatever is open.',
            'user_confirmed_separate_workout' => true,
            'exercises' => [['raw_phrase' => 'ring dips', 'sets' => array_fill(0, 3, ['reps' => 8])]],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame($started['active_session']['id'], $logged['auto_finished_stale_session']['id']);
        $this->assertDatabaseHas('workout_sessions', [
            'id' => $started['active_session']['id'],
            'status' => 'completed',
        ]);
    }

    public function test_update_workout_reopen_session_continues_a_wrongly_completed_workout(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $manager = app(WorkoutSessionManager::class);
        $updater = app(WorkoutUpdater::class);

        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'leg press 3x10 at 100kg',
            'exercises' => [[
                'raw_phrase' => 'leg press',
                'sets' => array_fill(0, 3, ['reps' => 10, 'load_value' => 100, 'load_unit' => 'kg']),
            ]],
        ]);
        $workoutId = $logged['saved_session']['id'];

        $reopened = $updater->update($user, [
            'workout_id' => $workoutId,
            'reason' => 'User is still training; completion was a mistake.',
            'operations' => [['type' => 'reopen_session']],
        ]);

        $this->assertFalse($reopened['refused']);
        $this->assertSame('in_progress', $reopened['updated_workout']['status']);
        $this->assertSame($workoutId, $manager->current($user)['active_session']['id']);

        $appended = $manager->appendExercise($user, [
            'raw_input' => 'Also weighted pistol squats 3x3 at 10kg.',
            'idempotency_key' => 'reopen-append',
            'exercise' => [
                'raw_phrase' => 'weighted pistol squats',
                'sets' => array_fill(0, 3, ['reps' => 3, 'load_value' => 10, 'load_unit' => 'kg']),
            ],
        ]);

        $finished = $manager->finish($user, ['idempotency_key' => 'reopen-finish']);

        $this->assertFalse($appended['refused']);
        $this->assertSame($workoutId, $appended['session']['id']);
        $this->assertSame('active', $appended['target_resolution']);
        $this->assertFalse($finished['refused']);
        $this->assertSame($workoutId, $finished['session']['id']);
        $this->assertSame(
            ['Leg Press', 'Weighted Pistol Squat'],
            collect($finished['session']['exercises'])->pluck('name')->all(),
        );
        $this->assertSame(1, WorkoutSession::query()->count());
    }

    public function test_read_tools_surface_stale_active_session_notice(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $manager = app(WorkoutSessionManager::class);

        $started = $manager->start($user, [
            'name' => 'Open from yesterday',
            'occurred_at' => now()->subHours(20)->toISOString(),
            'idempotency_key' => 'notice-start',
        ]);
        $sessionId = $started['active_session']['id'];

        foreach ([GetUserContextTool::class, ListRecentWorkoutsTool::class, GetTrainingSummaryTool::class] as $tool) {
            WorkoutMemoryServer::tool($tool)->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('stale_active_session.session.id', $sessionId)
                ->has('stale_active_session.hours_since_last_activity')
                ->has('stale_active_session.prompt_user')
                ->etc());
        }

        $manager->finish($user, ['idempotency_key' => 'notice-finish']);

        WorkoutMemoryServer::tool(GetUserContextTool::class)->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('stale_active_session', null)
            ->etc());
    }

    public function test_update_workout_merges_wrongly_separate_workout(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $updater = app(WorkoutUpdater::class);

        $first = app(WorkoutLogger::class)->log($user, [
            'occurred_at' => now()->subHours(3)->toISOString(),
            'raw_input' => 'leg press 3x10 at 100kg',
            'exercises' => [[
                'raw_phrase' => 'leg press',
                'sets' => array_fill(0, 3, ['reps' => 10, 'load_value' => 100, 'load_unit' => 'kg']),
            ]],
        ]);

        $second = app(WorkoutLogger::class)->log($user, [
            'occurred_at' => now()->subHours(2)->toISOString(),
            'raw_input' => 'weighted pistol squats 3x3 at 10kg',
            'exercises' => [[
                'raw_phrase' => 'weighted pistol squats',
                'sets' => array_fill(0, 3, ['reps' => 3, 'load_value' => 10, 'load_unit' => 'kg']),
            ]],
        ]);

        $unconfirmed = $updater->update($user, [
            'workout_id' => $first['saved_session']['id'],
            'operations' => [['type' => 'merge_workout', 'source_workout_id' => $second['saved_session']['id']]],
        ]);

        $this->assertTrue($unconfirmed['refused']);

        $merged = $updater->update($user, [
            'workout_id' => $first['saved_session']['id'],
            'user_confirmed_destructive_change' => true,
            'reason' => 'Second log was the same session.',
            'operations' => [['type' => 'merge_workout', 'source_workout_id' => $second['saved_session']['id']]],
        ]);

        $this->assertFalse($merged['refused']);
        $this->assertSame(
            ['Leg Press', 'Weighted Pistol Squat'],
            collect($merged['updated_workout']['exercises'])->pluck('name')->all(),
        );

        $target = WorkoutSession::query()->findOrFail($first['saved_session']['id']);
        $source = WorkoutSession::withTrashed()->findOrFail($second['saved_session']['id']);
        $this->assertTrue($target->completed_at->gt($target->started_at));
        $this->assertSame('deleted', $source->status);
        $this->assertNotNull($source->deleted_at);
        $this->assertSame(0, $source->exercises()->count());
        $this->assertDatabaseHas('workout_change_events', [
            'workout_session_id' => $source->id,
            'event_type' => 'merged',
        ]);

        $selfMerge = $updater->update($user, [
            'workout_id' => $target->id,
            'user_confirmed_destructive_change' => true,
            'operations' => [['type' => 'merge_workout', 'source_workout_id' => $target->id]],
        ]);

        $this->assertTrue($selfMerge['refused']);
    }

    public function test_update_workout_remaps_exercise_and_optionally_remembers_phrase(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $plank = Exercise::query()->where('name', 'Plank')->firstOrFail();
        $ringDip = Exercise::query()->where('name', 'Ring Dip')->firstOrFail();
        $updater = app(WorkoutUpdater::class);

        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'usual evening finisher 3 minutes',
            'exercises' => [[
                'exercise_id' => $plank->id,
                'raw_phrase' => 'usual evening finisher',
                'sets' => [['duration_seconds' => 180]],
            ]],
        ]);
        $workoutExerciseId = $logged['saved_session']['exercises'][0]['id'];

        $unknownExercise = $updater->update($user, [
            'workout_id' => $logged['saved_session']['id'],
            'operations' => [[
                'type' => 'update_exercise',
                'workout_exercise_id' => $workoutExerciseId,
                'exercise_id' => 99999,
            ]],
        ]);

        $this->assertTrue($unknownExercise['refused']);

        $remapped = $updater->update($user, [
            'workout_id' => $logged['saved_session']['id'],
            'reason' => 'User says the finisher is ring dips.',
            'operations' => [[
                'type' => 'update_exercise',
                'workout_exercise_id' => $workoutExerciseId,
                'exercise_id' => $ringDip->id,
                'remember_phrase' => true,
            ]],
        ]);

        $this->assertFalse($remapped['refused']);
        $this->assertSame(['Ring Dip'], collect($remapped['updated_workout']['exercises'])->pluck('name')->all());
        $this->assertDatabaseHas('workout_exercises', [
            'id' => $workoutExerciseId,
            'exercise_id' => $ringDip->id,
            'name_snapshot' => 'Ring Dip',
            'tracking_mode_snapshot' => $ringDip->tracking_mode,
            'resolution_type' => 'manual_correction',
        ]);
        $this->assertDatabaseHas('exercise_phrase_memories', [
            'user_id' => $user->id,
            'exercise_id' => $ringDip->id,
            'normalized_phrase' => ExerciseResolver::normalize('usual evening finisher'),
        ]);

        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'usual evening finisher'],
        ])[0];
        $this->assertSame('phrase_memory', $resolution['resolution']);
        $this->assertSame('Ring Dip', $resolution['exercise']['name']);
    }

    public function test_update_workout_fixes_entry_exercise_by_corrected_phrase(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $dumbbellIncline = Exercise::query()->where('name', 'Incline Dumbbell Press')->firstOrFail();

        // Production incident 2026-06-12: "incline bench press" logged during a live
        // session, but the user actually used dumbbells; the correction must remap
        // the same entry instead of silently no-opping or appending a duplicate.
        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'Incline bench press 3x5 at 60kg.',
            'exercises' => [[
                'raw_phrase' => 'incline bench press',
                'sets' => array_fill(0, 3, ['reps' => 5, 'load_value' => 60, 'load_unit' => 'kg']),
            ]],
        ]);
        $entry = $logged['saved_session']['exercises'][0];
        $this->assertNotSame('Incline Dumbbell Press', $entry['name']);

        $fixed = app(WorkoutUpdater::class)->update($user, [
            'workout_id' => $logged['saved_session']['id'],
            'reason' => 'User actually used dumbbells.',
            'operations' => [[
                'type' => 'update_exercise',
                'workout_exercise_id' => $entry['id'],
                'raw_phrase' => 'incline dumbbell press',
                'remember_phrase' => true,
            ]],
        ]);

        $this->assertFalse($fixed['refused']);
        $this->assertSame(['Incline Dumbbell Press'], collect($fixed['updated_workout']['exercises'])->pluck('name')->all());
        $this->assertSame($entry['id'], $fixed['resolution_outcomes'][0]['corrected_workout_exercise_id']);
        $this->assertFalse($fixed['resolution_outcomes'][0]['unchanged']);
        $this->assertDatabaseHas('workout_exercises', [
            'id' => $entry['id'],
            'exercise_id' => $dumbbellIncline->id,
            'name_snapshot' => 'Incline Dumbbell Press',
            'resolution_type' => 'manual_correction',
        ]);
        $this->assertSame(3, WorkoutSet::query()->where('workout_exercise_id', $entry['id'])->count());
        $this->assertDatabaseHas('exercise_phrase_memories', [
            'user_id' => $user->id,
            'exercise_id' => $dumbbellIncline->id,
            'normalized_phrase' => ExerciseResolver::normalize('incline dumbbell press'),
        ]);
    }

    public function test_update_workout_phrase_correction_auto_creates_unknown_exercise(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'leg press 3x10 at 100kg',
            'exercises' => [[
                'raw_phrase' => 'leg press',
                'sets' => array_fill(0, 3, ['reps' => 10, 'load_value' => 100, 'load_unit' => 'kg']),
            ]],
        ]);
        $entry = $logged['saved_session']['exercises'][0];

        $fixed = app(WorkoutUpdater::class)->update($user, [
            'workout_id' => $logged['saved_session']['id'],
            'operations' => [[
                'type' => 'update_exercise',
                'workout_exercise_id' => $entry['id'],
                'raw_phrase' => 'zercher yoke carry',
            ]],
        ]);

        $this->assertFalse($fixed['refused']);
        $this->assertSame('Zercher Yoke Carry', $fixed['auto_created_exercises'][0]['exercise']['name']);
        $this->assertTrue($fixed['auto_created_exercises'][0]['needs_review']);
        $created = Exercise::query()->where('name', 'Zercher Yoke Carry')->firstOrFail();
        $this->assertSame('load_reps', $created->tracking_mode);
        $this->assertDatabaseHas('workout_exercises', [
            'id' => $entry['id'],
            'exercise_id' => $created->id,
            'resolution_type' => 'manual_correction',
        ]);
    }

    public function test_update_workout_reports_unchanged_phrase_corrections(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'leg press 3x10 at 100kg',
            'exercises' => [[
                'raw_phrase' => 'leg press',
                'sets' => array_fill(0, 3, ['reps' => 10, 'load_value' => 100, 'load_unit' => 'kg']),
            ]],
        ]);
        $entry = $logged['saved_session']['exercises'][0];

        $unchanged = app(WorkoutUpdater::class)->update($user, [
            'workout_id' => $logged['saved_session']['id'],
            'operations' => [[
                'type' => 'update_exercise',
                'workout_exercise_id' => $entry['id'],
                'raw_phrase' => 'leg press',
            ]],
        ]);

        $this->assertFalse($unchanged['refused']);
        $this->assertTrue($unchanged['resolution_outcomes'][0]['unchanged']);
        $this->assertArrayHasKey('hint', $unchanged['resolution_outcomes'][0]);
        $this->assertDatabaseMissing('workout_exercises', [
            'id' => $entry['id'],
            'resolution_type' => 'manual_correction',
        ]);
    }

    public function test_update_workout_correction_with_explicit_id_wins_over_phrase_evidence(): void
    {
        $user = app(CurrentUserResolver::class)->user();

        // Production incident 2026-06-12: straddled front-lever rows resolved to a
        // catalog entry; the user then asked for a real Front Lever Pull-Up exercise.
        // A correction carrying the new exercise_id plus the entry's old wording was
        // vetoed by the confident phrase resolution and reported as unchanged, so the
        // model appended a "superseding" duplicate instead. An explicit correction id
        // must win over phrase evidence, reporting the disagreement alongside.
        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'Plank 3x60s.',
            'exercises' => [[
                'raw_phrase' => 'plank',
                'sets' => array_fill(0, 3, ['duration_seconds' => 60]),
            ]],
        ]);
        $entry = $logged['saved_session']['exercises'][0];
        $this->assertSame('Plank', $entry['name']);

        $frontLever = app(ExerciseCreator::class)->createExercise($user, [
            'name' => 'Front Lever Pull-Up',
            'category' => 'other',
            'granularity' => 'canonical',
            'tracking_mode' => 'reps',
        ]);

        $corrected = app(WorkoutUpdater::class)->update($user, [
            'workout_id' => $logged['saved_session']['id'],
            'reason' => 'User wants this entry tracked as the new exercise.',
            'operations' => [[
                'type' => 'update_exercise',
                'workout_exercise_id' => $entry['id'],
                'exercise_id' => $frontLever->id,
                'raw_phrase' => 'plank',
            ]],
        ]);

        $this->assertFalse($corrected['refused']);
        $outcome = $corrected['resolution_outcomes'][0];
        $this->assertFalse($outcome['unchanged']);
        $this->assertSame('corrected', $outcome['method']);
        $this->assertSame($frontLever->id, $outcome['exercise_id']);
        $this->assertSame('Plank', $outcome['phrase_resolved_elsewhere']['exercise_name']);
        $this->assertSame([], $corrected['ignored_exercise_hints']);
        $this->assertSame('Front Lever Pull-Up', $corrected['correction_phrase_conflicts'][0]['used_exercise_name']);
        $this->assertSame('Plank', $corrected['correction_phrase_conflicts'][0]['phrase_exercise_name']);
        $this->assertDatabaseHas('workout_exercises', [
            'id' => $entry['id'],
            'exercise_id' => $frontLever->id,
            'name_snapshot' => 'Front Lever Pull-Up',
            'resolution_type' => 'manual_correction',
        ]);
        $this->assertSame(3, WorkoutSet::query()->where('workout_exercise_id', $entry['id'])->count());

        $repeat = app(WorkoutUpdater::class)->update($user, [
            'workout_id' => $logged['saved_session']['id'],
            'operations' => [[
                'type' => 'update_exercise',
                'workout_exercise_id' => $entry['id'],
                'exercise_id' => $frontLever->id,
                'raw_phrase' => 'plank',
            ]],
        ]);

        $this->assertFalse($repeat['refused']);
        $this->assertTrue($repeat['resolution_outcomes'][0]['unchanged']);
        $this->assertStringContainsString('already mapped', $repeat['resolution_outcomes'][0]['hint']);
    }

    public function test_log_workout_dated_in_the_past_keeps_that_date_and_duration(): void
    {
        $user = app(CurrentUserResolver::class)->user();

        $logged = app(WorkoutLogger::class)->log($user, [
            'occurred_at' => now()->subDay()->setTime(18, 30)->toISOString(),
            'completed_at' => now()->subDay()->setTime(19, 45)->toISOString(),
            'raw_input' => 'Yesterday evening: ring dips 3x8, took about 75 minutes.',
            'exercises' => [['raw_phrase' => 'ring dips', 'sets' => array_fill(0, 3, ['reps' => 8])]],
        ]);

        $this->assertFalse($logged['refused']);
        $session = WorkoutSession::query()->findOrFail($logged['saved_session']['id']);
        $this->assertSame(now()->subDay()->toDateString(), $session->started_at->toDateString());
        $this->assertSame(75, (int) $session->started_at->diffInMinutes($session->completed_at));
    }

    public function test_every_tool_schema_builds(): void
    {
        $schema = new JsonSchemaTypeFactory;

        foreach (glob(app_path('Mcp/Tools/*.php')) as $file) {
            $class = 'App\\Mcp\\Tools\\'.basename($file, '.php');
            $this->assertIsArray(app($class)->schema($schema), "{$class} schema failed to build");
        }
    }

    public function test_every_tool_has_review_ready_safety_annotations(): void
    {
        foreach (glob(app_path('Mcp/Tools/*.php')) as $file) {
            $class = 'App\\Mcp\\Tools\\'.basename($file, '.php');
            $tool = app($class)->toArray();

            $this->assertNotEmpty($tool['title'], "{$class} is missing a title.");
            $this->assertNotEmpty($tool['description'], "{$class} is missing a description.");
            $this->assertArrayHasKey('readOnlyHint', (array) $tool['annotations'], "{$class} is missing readOnlyHint.");
            $this->assertArrayHasKey('destructiveHint', (array) $tool['annotations'], "{$class} is missing destructiveHint.");
            $this->assertArrayHasKey('openWorldHint', (array) $tool['annotations'], "{$class} is missing openWorldHint.");
        }
    }

    public function test_default_tools_list_response_contains_every_registered_tool(): void
    {
        $server = app()->make(WorkoutMemoryServer::class, ['transport' => new FakeTransporter]);
        $response = (new ListTools)->handle(
            new JsonRpcRequest('tools-list', 'tools/list', []),
            $server->createContext(),
        )->toArray();

        $toolNames = collect($response['result']['tools'])->pluck('name')->all();
        $registeredToolCount = count((new \ReflectionClass(WorkoutMemoryServer::class))->getProperty('tools')->getDefaultValue());

        $this->assertCount($registeredToolCount, $toolNames);
        $this->assertContains('update_workout', $toolNames);
        $this->assertContains('delete_workout', $toolNames);
        $this->assertContains('share_workout', $toolNames);
        $this->assertArrayNotHasKey('nextCursor', $response['result']);
    }

    public function test_reopen_session_refuses_while_another_session_is_active(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'ring dips 1x8',
            'exercises' => [['raw_phrase' => 'ring dips', 'sets' => [['reps' => 8]]]],
        ]);

        app(WorkoutSessionManager::class)->start($user, ['name' => 'New live session', 'idempotency_key' => 'live-now']);

        $refused = app(WorkoutUpdater::class)->update($user, [
            'workout_id' => $logged['saved_session']['id'],
            'operations' => [['type' => 'reopen_session']],
        ]);

        $this->assertTrue($refused['refused']);
        $this->assertStringContainsString('in-progress workout session already exists', $refused['refusal_reason']);
        $this->assertDatabaseHas('workout_sessions', [
            'id' => $logged['saved_session']['id'],
            'status' => 'completed',
        ]);
    }

    public function test_append_active_or_new_refuses_old_completed_workout_context(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $legPress = Exercise::query()->where('name', 'Leg Press')->firstOrFail();
        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'leg press'],
        ])[0];

        $appended = app(WorkoutSessionManager::class)->appendExercise($user, [
            'target_session' => 'active_or_new',
            'occurred_at' => now()->subDay()->toISOString(),
            'raw_input' => 'Yesterday I did leg press 2x10 at 100kg.',
            'idempotency_key' => 'old-active-append-refused',
            'exercise' => [
                'exercise_id' => $legPress->id,
                'resolution_id' => $resolution['resolution_id'],
                'raw_phrase' => 'leg press',
                'resolution_type' => $resolution['resolution'],
                'sets' => array_fill(0, 2, [
                    'reps' => 10,
                    'load_value' => 100,
                    'load_unit' => 'kg',
                    'load_type' => 'external',
                ]),
            ],
        ]);

        $this->assertTrue($appended['refused']);
        $this->assertSame('log_workout', $appended['suggested_next_tool']);
        $this->assertSame(0, WorkoutSession::query()->count());
    }

    public function test_update_and_delete_workout_require_confirmation_for_destructive_changes(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $exercise = Exercise::query()->where('name', 'Ring Dip')->firstOrFail();
        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'ring dips 1x8',
            'exercises' => [[
                'exercise_id' => $exercise->id,
                'resolution_type' => 'exact',
                'sets' => [['reps' => 8]],
            ]],
        ]);
        $workoutId = $logged['saved_session']['id'];
        $workoutExerciseId = $logged['saved_session']['exercises'][0]['id'];

        $updater = app(WorkoutUpdater::class);

        $refusedUpdate = $updater->update($user, [
            'workout_id' => $workoutId,
            'operations' => [[
                'type' => 'remove_exercise',
                'workout_exercise_id' => $workoutExerciseId,
            ]],
        ]);

        $this->assertTrue($refusedUpdate['refused']);
        // The refusal must not be a dead end: it has to say nothing was applied,
        // steer corrections to update_exercise, and explain that the user's own
        // request counts as confirmation for a flagged retry.
        $this->assertStringContainsString('No operations were applied', $refusedUpdate['refusal_reason']);
        $this->assertStringContainsString('update_exercise', $refusedUpdate['refusal_reason']);
        $this->assertStringContainsString('counts as that confirmation', $refusedUpdate['refusal_reason']);
        $this->assertNotEmpty(WorkoutSession::query()->findOrFail($workoutId)->exercises()->whereKey($workoutExerciseId)->get());

        $confirmedRemove = $updater->update($user, [
            'workout_id' => $workoutId,
            'reason' => 'User asked to remove the duplicate entry.',
            'user_confirmed_destructive_change' => true,
            'operations' => [[
                'type' => 'remove_exercise',
                'workout_exercise_id' => $workoutExerciseId,
            ]],
        ]);

        $this->assertFalse($confirmedRemove['refused']);
        $this->assertSame([], $confirmedRemove['updated_workout']['exercises']);

        $refusedDelete = $updater->delete($user, $workoutId, 'mistake', false);
        $this->assertTrue($refusedDelete['refused']);

        $deleted = $updater->delete($user, $workoutId, 'mistake', true);
        $this->assertFalse($deleted['refused']);
        $this->assertSame('deleted', WorkoutSession::withTrashed()->findOrFail($workoutId)->status);
    }

    public function test_mcp_server_tools_return_structured_content(): void
    {
        WorkoutMemoryServer::tool(GetUserContextTool::class)
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', true)
                ->has('user_context')
                ->etc());

        WorkoutMemoryServer::tool(GetCurrentWorkoutSessionTool::class)
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', true)
                ->has('active_session')
                ->has('latest_completed_session')
                ->etc());

        WorkoutMemoryServer::tool(ResolveExerciseMentionsTool::class, [
            'mentions' => [['raw_phrase' => 'weighted rings MU']],
        ])->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('ok', true)
            ->where('results.0.exercise.name', 'Weighted Ring Muscle-Up')
            ->has('results.0.log_entry_template')
            ->etc());

        WorkoutMemoryServer::tool(SearchExercisesTool::class, [
            'query' => 'Calves at 230 kg',
            'limit' => 3,
        ])->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('ok', true)
            ->where('matches.0.name', 'Calf Press on Leg Press')
            ->etc());

        WorkoutMemoryServer::tool(RememberExercisePhraseTool::class, [
            'phrase' => 'rings MU work',
            'exercise_id' => Exercise::query()->where('name', 'Ring Muscle-Up')->firstOrFail()->id,
        ])->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('ok', true)
            ->has('phrase_memory')
            ->etc());
    }

    public function test_debug_pages_load(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $user->markEmailAsVerified();

        $this->actingAs($user);

        $this->get('/dashboard')->assertOk()->assertSee('Workout Memory MCP');
        $this->get('/exercises')->assertOk()->assertSee('Dead Hang');
        $this->get('/workouts')->assertOk();
    }
}
