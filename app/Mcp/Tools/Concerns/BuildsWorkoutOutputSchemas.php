<?php

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;

trait BuildsWorkoutOutputSchemas
{
    /**
     * @return array<string, Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->baseOutputSchema($schema);
    }

    /**
     * @param  array<string, Type>  $properties
     * @return array<string, Type>
     */
    protected function baseOutputSchema(JsonSchema $schema, array $properties = []): array
    {
        return [
            'ok' => $schema->boolean()->required()->description('Whether the tool completed successfully.'),
            'message' => $schema->string()->required()->description('Short status message for the tool result.'),
            'refused' => $schema->boolean()->description('Whether the requested operation was refused.'),
            'refusal_reason' => $schema->string()->nullable()->description('Reason the requested operation was refused, when present.'),
            ...$properties,
        ];
    }

    protected function workoutSessionSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'kind' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'source' => $schema->string()->required()->nullable(),
            'started_at' => $schema->string()->required()->nullable()->format('date-time'),
            'completed_at' => $schema->string()->required()->nullable()->format('date-time'),
            'timezone' => $schema->string()->required()->nullable(),
            'perceived_effort' => $schema->integer()->required()->nullable(),
            'bodyweight_kg' => $schema->number()->required()->nullable(),
            'notes' => $schema->string()->required()->nullable(),
            'raw_input' => $schema->string()->required()->nullable(),
            'events' => $schema->array()->required()->items($this->workoutEventSchema($schema)),
            'exercises' => $schema->array()->required()->items($this->workoutExerciseSchema($schema)),
            'set_count' => $schema->integer()->required(),
        ]);
    }

    protected function workoutEventSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->integer()->required(),
            'event_type' => $schema->string()->required(),
            'reason' => $schema->string()->required()->nullable(),
            'occurred_at' => $schema->string()->required()->nullable()->format('date-time'),
        ]);
    }

    protected function workoutExerciseSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->integer()->required(),
            'exercise_id' => $schema->integer()->required()->nullable(),
            'sort_order' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'tracking_mode' => $schema->string()->required()->nullable(),
            'raw_phrase' => $schema->string()->required()->nullable(),
            'resolution_type' => $schema->string()->required()->nullable(),
            'variant_label' => $schema->string()->required()->nullable(),
            'variant_description' => $schema->string()->required()->nullable(),
            'notes' => $schema->string()->required()->nullable(),
            'sets' => $schema->array()->required()->items($this->workoutSetSchema($schema)),
        ]);
    }

    protected function workoutSetSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->integer()->required(),
            'set_number' => $schema->integer()->required(),
            'reps' => $schema->integer()->required()->nullable(),
            'load_kg' => $schema->number()->required()->nullable(),
            'load_type' => $schema->string()->required()->nullable(),
            'duration_seconds' => $schema->integer()->required()->nullable(),
            'duration_display' => $schema->string()->required()->nullable(),
            'distance_meters' => $schema->number()->required()->nullable(),
            'rpe' => $schema->number()->required()->nullable(),
            'rir' => $schema->number()->required()->nullable(),
            'side' => $schema->string()->required()->nullable(),
            'success' => $schema->boolean()->required()->nullable(),
            'quality_rating' => $schema->integer()->required()->nullable(),
            'is_warmup' => $schema->boolean()->required(),
            'raw_set_text' => $schema->string()->required()->nullable(),
            'notes' => $schema->string()->required()->nullable(),
        ]);
    }

    protected function staleActiveSessionSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'session' => $this->workoutSessionSchema($schema)->required(),
            'hours_since_last_activity' => $schema->integer()->required()->nullable(),
            'prompt_user' => $schema->string()->required(),
        ]);
    }

    protected function workoutEventOutputSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->integer()->required(),
            'event_type' => $schema->string()->required(),
            'reason' => $schema->string()->required()->nullable(),
            'occurred_at' => $schema->string()->required()->nullable()->format('date-time'),
        ]);
    }

    protected function normalizedSummarySchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'workout_id' => $schema->integer()->required(),
            'exercise_count' => $schema->integer()->required(),
            'set_count' => $schema->integer()->required(),
            'loaded_volume_kg_reps' => $schema->number()->required(),
            'duration_seconds' => $schema->integer()->required(),
            'distance_meters' => $schema->number()->required(),
        ]);
    }

    protected function sharingSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'share_available' => $schema->boolean()->required(),
            'hint' => $schema->string()->required(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    protected function resolutionOutcomeProperties(JsonSchema $schema): array
    {
        return [
            'resolution_outcomes' => $schema->array()->items($this->resolutionOutcomeSchema($schema)),
            'auto_created_exercises' => $schema->array()->items($schema->object([
                'exercise' => $this->exerciseSummarySchema($schema)->nullable(),
                'created_from_phrase' => $schema->string()->nullable(),
                'needs_review' => $schema->boolean()->required(),
            ])),
            'assumed_matches' => $schema->array()->items($schema->object([
                'raw_phrase' => $schema->string()->nullable(),
                'exercise_id' => $schema->integer()->required(),
                'exercise_name' => $schema->string()->required(),
                'confidence' => $schema->number()->required(),
                'alternatives' => $schema->array()->items($this->exerciseAlternativeSchema($schema)),
            ])),
            'ignored_exercise_hints' => $schema->array()->items($schema->object([
                'raw_phrase' => $schema->string()->nullable(),
                'ignored_exercise_id' => $schema->integer()->required(),
                'ignored_exercise_name' => $schema->string()->required(),
                'used_exercise_id' => $schema->integer()->required(),
                'used_exercise_name' => $schema->string()->required(),
                'reason' => $schema->string()->required(),
            ])),
            'correction_phrase_conflicts' => $schema->array()->items($schema->object([
                'raw_phrase' => $schema->string()->nullable(),
                'used_exercise_id' => $schema->integer()->required(),
                'used_exercise_name' => $schema->string()->required(),
                'phrase_exercise_id' => $schema->integer()->required(),
                'phrase_exercise_name' => $schema->string()->required(),
                'reason' => $schema->string()->required(),
            ])),
        ];
    }

    protected function resolutionOutcomeSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'raw_phrase' => $schema->string()->nullable(),
            'resolution_type' => $schema->string()->nullable(),
            'method' => $schema->string()->nullable(),
            'confidence' => $schema->number()->nullable(),
            'exercise_id' => $schema->integer()->nullable(),
            'exercise_name' => $schema->string()->nullable(),
            'exercise_summary' => $this->exerciseSummarySchema($schema)->nullable(),
            'alternatives' => $schema->array()->items($this->exerciseAlternativeSchema($schema)),
            'variant_label' => $schema->string()->nullable(),
            'variant_description' => $schema->string()->nullable(),
            'auto_created' => $schema->boolean(),
            'created_from_phrase' => $schema->string()->nullable(),
            'corrected_workout_exercise_id' => $schema->integer()->nullable(),
            'unchanged' => $schema->boolean(),
            'hint' => $schema->string()->nullable(),
        ]);
    }

    protected function exerciseAlternativeSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'exercise_id' => $schema->integer()->required(),
            'exercise_name' => $schema->string()->required(),
            'confidence' => $schema->number()->required(),
            'resolution' => $schema->string()->required(),
        ]);
    }

    protected function exerciseSummarySchema(JsonSchema $schema): ObjectType
    {
        return $schema->object($this->exerciseSummaryProperties($schema));
    }

    /**
     * @return array<string, Type>
     */
    protected function exerciseSummaryProperties(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'canonical_name' => $schema->string()->required()->nullable(),
            'aliases' => $schema->array()->required()->items($schema->string()),
            'source' => $schema->string()->required()->nullable(),
            'category' => $schema->string()->required()->nullable(),
            'granularity' => $schema->string()->required()->nullable(),
            'tracking_mode' => $schema->string()->required()->nullable(),
            'equipment' => $schema->array()->required()->items($schema->string()),
            'primary_muscles' => $schema->array()->required()->items($schema->string()),
            'primary_body_area' => $schema->string()->required()->nullable(),
            'external_load_allowed' => $schema->boolean()->required(),
            'parent_exercise' => $schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
            ])->required()->nullable(),
            'usage_count' => $schema->integer()->required()->nullable(),
            'last_used_at' => $schema->string()->required()->nullable()->format('date-time'),
        ];
    }

    protected function detailedExerciseSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            ...$this->exerciseSummaryProperties($schema),
            'tags' => $schema->array()->required()->items($schema->string()),
            'secondary_muscles' => $schema->array()->required()->items($schema->string()),
            'unilateral' => $schema->boolean()->required()->nullable(),
            'bodyweight' => $schema->boolean()->required()->nullable(),
            'variation_notes' => $schema->string()->required()->nullable(),
            'default_variant_policy' => $schema->string()->required()->nullable(),
            'instructions' => $schema->string()->required()->nullable(),
            'safety_notes' => $schema->string()->required()->nullable(),
            'created_at' => $schema->string()->required()->nullable()->format('date-time'),
            'updated_at' => $schema->string()->required()->nullable()->format('date-time'),
        ]);
    }

    protected function resolverCandidateSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'resolution' => $schema->string()->required(),
            'confidence' => $schema->number()->required(),
            'why_matched' => $schema->string()->required(),
            'exercise' => $this->exerciseSummarySchema($schema)->required(),
        ]);
    }

    protected function exerciseResolutionResultSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'resolution_id' => $schema->string()->required()->nullable(),
            'raw_phrase' => $schema->string()->required(),
            'resolution' => $schema->string()->required(),
            'resolution_type' => $schema->string()->required(),
            'exercise' => $this->exerciseSummarySchema($schema)->required()->nullable(),
            'variant_label' => $schema->string()->required()->nullable(),
            'variant_description' => $schema->string()->required()->nullable(),
            'confidence' => $schema->number()->required(),
            'duplicate_risk' => $schema->string()->required(),
            'should_create' => $schema->boolean()->required(),
            'should_ask_user' => $schema->boolean()->required(),
            'suggested_action' => $schema->string()->required(),
            'recommended_bucket_exercise' => $this->exerciseSummarySchema($schema)->required()->nullable(),
            'requires_variant_details' => $schema->boolean()->required(),
            'evidence_persisted' => $schema->boolean()->required(),
            'evidence_persistence_warning' => $schema->string()->required()->nullable(),
            'log_entry_template' => $schema->object([
                'raw_phrase' => $schema->string()->required(),
                'exercise_id' => $schema->integer()->required()->nullable(),
                'resolution_id' => $schema->string()->required()->nullable(),
                'variant_label' => $schema->string()->required()->nullable(),
            ])->required(),
            'candidates' => $schema->array()->required()->items($this->resolverCandidateSchema($schema)),
        ]);
    }

    protected function recentWorkoutSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'kind' => $schema->string()->required(),
            'started_at' => $schema->string()->required()->nullable()->format('date-time'),
            'completed_at' => $schema->string()->required()->nullable()->format('date-time'),
            'exercise_names' => $schema->array()->required()->items($schema->string()),
            'set_count' => $schema->integer()->required(),
            'top_level_volume_signals' => $schema->object([
                'loaded_reps' => $schema->integer()->required(),
                'bodyweight_reps' => $schema->integer()->required(),
                'duration_seconds' => $schema->integer()->required(),
                'duration_display' => $schema->string()->required()->nullable(),
                'distance_meters' => $schema->number()->required(),
            ])->required(),
        ]);
    }

    protected function trainingSummarySchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'since' => $schema->string()->required()->format('date-time'),
            'focus' => $schema->string()->required()->nullable(),
            'recent_frequency' => $schema->object([
                'session_count' => $schema->integer()->required(),
                'by_kind' => $schema->object()
                    ->required()
                    ->description('Map of workout kind to number of sessions in the summary window.'),
                'last_session_at' => $schema->string()->required()->nullable()->format('date-time'),
            ])->required(),
            'muscle_exposure' => $schema->object()
                ->required()
                ->description('Map of muscle name to exposure count in the summary window.'),
            'equipment_exposure' => $schema->object()
                ->required()
                ->description('Map of equipment name to exposure count in the summary window.'),
            'notable_gaps' => $schema->array()->required()->items($schema->string()),
            'recent_hard_sessions' => $schema->array()->required()->items($schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
                'kind' => $schema->string()->required(),
                'started_at' => $schema->string()->required()->nullable()->format('date-time'),
                'perceived_effort' => $schema->integer()->required()->nullable(),
            ])),
            'exercises_to_avoid_repeating_too_soon' => $schema->array()->required()->items($schema->string()),
            'recent_skill_practice_and_bucketed_drill_notes' => $schema->array()->required()->items($schema->object([
                'name' => $schema->string()->required(),
                'variant_label' => $schema->string()->required()->nullable(),
                'variant_description' => $schema->string()->required()->nullable(),
                'notes' => $schema->string()->required()->nullable(),
            ])),
        ]);
    }

    protected function exerciseHistorySchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'exercise' => $schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
                'granularity' => $schema->string()->required()->nullable(),
            ])->required(),
            'variant_label' => $schema->string()->required()->nullable(),
            'last_performed_at' => $schema->string()->required()->nullable()->format('date-time'),
            'recent_performances' => $schema->array()->required()->items($schema->object([
                'workout_id' => $schema->integer()->required(),
                'workout_name' => $schema->string()->required(),
                'started_at' => $schema->string()->required()->nullable()->format('date-time'),
                'variant_label' => $schema->string()->required()->nullable(),
                'variant_description' => $schema->string()->required()->nullable(),
                'notes' => $schema->string()->required()->nullable(),
                'sets' => $schema->array()->required()->items($schema->object([
                    'set_number' => $schema->integer()->required(),
                    'reps' => $schema->integer()->required()->nullable(),
                    'load_kg' => $schema->number()->required()->nullable(),
                    'duration_seconds' => $schema->integer()->required()->nullable(),
                    'duration_display' => $schema->string()->required()->nullable(),
                    'distance_meters' => $schema->number()->required()->nullable(),
                    'rpe' => $schema->number()->required()->nullable(),
                    'quality_rating' => $schema->integer()->required()->nullable(),
                ])),
            ])),
            'best_set' => $schema->object([
                'reps' => $schema->integer()->required()->nullable(),
                'load_kg' => $schema->number()->required()->nullable(),
                'duration_seconds' => $schema->integer()->required()->nullable(),
                'duration_display' => $schema->string()->required()->nullable(),
                'distance_meters' => $schema->number()->required()->nullable(),
            ])->required()->nullable(),
            'estimated_volume' => $schema->object([
                'loaded_volume_kg_reps' => $schema->number()->required(),
                'total_reps' => $schema->integer()->required(),
                'total_duration_seconds' => $schema->integer()->required(),
                'total_duration_display' => $schema->string()->required()->nullable(),
                'total_distance_meters' => $schema->number()->required(),
            ])->required(),
            'trend_hints' => $schema->array()->required()->items($schema->string()),
            'matching_bucketed_variants' => $schema->array()->required()->items($schema->string()),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    protected function userContextProperties(JsonSchema $schema): array
    {
        return [
            'stale_active_session' => $this->staleActiveSessionSchema($schema)->required()->nullable(),
            'onboarding' => $schema->object([
                'is_new_user' => $schema->boolean()->required(),
                'profile_needs_setup' => $schema->boolean()->required(),
                'help_tool' => $schema->string()->required(),
                'suggested_next_actions' => $schema->array()->required()->items($schema->string()),
            ])->required(),
            'user_context' => $schema->object([
                'name' => $schema->string()->required(),
                'email' => $schema->string()->required()->nullable(),
                'preferred_weight_unit' => $schema->string()->required(),
                'preferred_distance_unit' => $schema->string()->required(),
                'timezone' => $schema->string()->required(),
                'goals' => $schema->string()->required()->nullable(),
                'injuries_constraints' => $schema->string()->required()->nullable(),
                'available_equipment' => $schema->array()->required()->items($schema->string()),
                'notes' => $schema->string()->required()->nullable(),
            ])->required(),
        ];
    }

    protected function validationIssueSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'raw_phrase' => $schema->string()->nullable(),
            'message' => $schema->string()->required(),
        ]);
    }
}
