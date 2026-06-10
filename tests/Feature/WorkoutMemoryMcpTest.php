<?php

namespace Tests\Feature;

use App\Mcp\Servers\WorkoutMemoryServer;
use App\Mcp\Tools\GetCurrentWorkoutSessionTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\RememberExercisePhraseTool;
use App\Mcp\Tools\ResolveExerciseMentionsTool;
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
use Illuminate\Testing\Fluent\AssertableJson;
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

    public function test_log_workout_accepts_mismatched_entries_and_flags_assumptions(): void
    {
        $user = app(CurrentUserResolver::class)->user();
        $plank = Exercise::query()->where('name', 'Plank')->firstOrFail();

        $manualAssumption = app(WorkoutLogger::class)->log($user, [
            'raw_input' => '45 minutes of spinning',
            'exercises' => [[
                'exercise_id' => $plank->id,
                'raw_phrase' => 'spinning',
                'resolution_type' => 'manual_assumption',
                'variant_label' => 'Indoor cycling / spinning',
                'sets' => [['duration_seconds' => 2700]],
            ]],
        ]);

        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'spinning'],
        ])[0];

        $mismatchedEvidence = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'another 45 minutes of spinning',
            'exercises' => [[
                'exercise_id' => $plank->id,
                'resolution_id' => $resolution['resolution_id'],
                'raw_phrase' => 'spinning',
                'resolution_type' => $resolution['resolution'],
                'sets' => [['duration_seconds' => 2700]],
            ]],
        ]);

        $this->assertFalse($manualAssumption['refused']);
        $this->assertFalse($mismatchedEvidence['refused']);
        $this->assertSame(2, WorkoutSession::query()->count());
        $this->assertSame(2, WorkoutExercise::query()
            ->where('exercise_id', $plank->id)
            ->where('resolution_type', 'manual_assumption')
            ->count());
        $this->assertSame('assumed', $manualAssumption['resolution_outcomes'][0]['method']);
        $this->assertSame('Plank', $mismatchedEvidence['assumed_matches'][0]['exercise_name']);
        $this->assertContains(
            'Indoor Ride',
            array_column($mismatchedEvidence['assumed_matches'][0]['alternatives'], 'exercise_name'),
        );
        $this->assertDatabaseMissing('exercise_phrase_memories', [
            'user_id' => $user->id,
            'normalized_phrase' => ExerciseResolver::normalize('spinning'),
        ]);
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
            'raw_input' => 'turkish get ups 3x5 at 16kg',
            'exercises' => [[
                'raw_phrase' => 'turkish get ups',
                'sets' => array_fill(0, 3, ['reps' => 5, 'load_value' => 16, 'load_unit' => 'kg']),
            ]],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame('auto_created', $logged['resolution_outcomes'][0]['method']);
        $this->assertSame('Turkish Get Ups', $logged['auto_created_exercises'][0]['exercise']['name']);
        $this->assertTrue($logged['auto_created_exercises'][0]['needs_review']);

        $exercise = Exercise::query()->where('name', 'Turkish Get Ups')->firstOrFail();
        $this->assertSame('chatgpt_auto', $exercise->source);
        $this->assertSame((int) $user->id, (int) $exercise->user_id);
        $this->assertSame('load_reps', $exercise->tracking_mode);
        $this->assertTrue((bool) ($exercise->metadata['auto_created'] ?? false));
        $this->assertTrue((bool) ($exercise->metadata['needs_review'] ?? false));
        $this->assertDatabaseHas('exercise_aliases', [
            'exercise_id' => $exercise->id,
            'normalized_alias' => ExerciseResolver::normalize('turkish get ups'),
        ]);
        $this->assertDatabaseHas('exercise_phrase_memories', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'normalized_phrase' => ExerciseResolver::normalize('turkish get ups'),
        ]);

        $resolution = app(ExerciseResolver::class)->resolveMentions($user, [
            ['raw_phrase' => 'turkish get ups'],
        ])[0];
        $this->assertSame('Turkish Get Ups', $resolution['exercise']['name']);
    }

    public function test_log_workout_dedupes_repeated_unknown_phrase_within_request(): void
    {
        $user = app(CurrentUserResolver::class)->user();

        $logged = app(WorkoutLogger::class)->log($user, [
            'raw_input' => 'pogo hops 2x20 per side',
            'exercises' => [
                ['raw_phrase' => 'pogo hops', 'notes' => 'left side', 'sets' => [['reps' => 20]]],
                ['raw_phrase' => 'pogo hops', 'notes' => 'right side', 'sets' => [['reps' => 20]]],
            ],
        ]);

        $this->assertFalse($logged['refused']);
        $this->assertSame(1, Exercise::query()->where('name', 'Pogo Hops')->count());
        $this->assertCount(1, $logged['auto_created_exercises']);
        $this->assertSame(['auto_created', 'phrase_memory'], array_column($logged['resolution_outcomes'], 'method'));
        $this->assertSame(2, WorkoutExercise::query()->where('name_snapshot', 'Pogo Hops')->count());
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
                    'raw_phrase' => 'turkish get up',
                    'sets' => [['reps' => 5, 'load_value' => 16, 'load_unit' => 'kg']],
                ],
            ],
        ]);

        $this->assertFalse($updated['refused']);
        $this->assertSame(['resolved', 'auto_created'], array_column($updated['resolution_outcomes'], 'method'));
        $this->assertSame('Turkish Get Up', $updated['auto_created_exercises'][0]['exercise']['name']);
        $this->assertSame(
            ['Ring Dip', 'Leg Press', 'Turkish Get Up'],
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
        $this->actingAs(app(CurrentUserResolver::class)->user());

        $this->get('/')->assertOk()->assertSee('Workout Memory MCP');
        $this->get('/exercises')->assertOk()->assertSee('Weighted Ring Muscle-Up');
        $this->get('/workouts')->assertOk();
    }
}
