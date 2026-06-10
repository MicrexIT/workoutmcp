<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_exercise_id')->nullable()->constrained('exercises')->nullOnDelete();
            $table->string('source')->default('seed');
            $table->string('name');
            $table->string('canonical_name');
            $table->string('normalized_name');
            $table->string('category')->default('other');
            $table->string('granularity')->default('canonical');
            $table->json('tags')->nullable();
            $table->json('primary_muscles')->nullable();
            $table->json('secondary_muscles')->nullable();
            $table->string('primary_body_area')->nullable();
            $table->json('equipment')->nullable();
            $table->string('tracking_mode')->default('load_reps');
            $table->boolean('unilateral')->nullable();
            $table->boolean('bodyweight')->nullable();
            $table->boolean('external_load_allowed')->default(false);
            $table->text('variation_notes')->nullable();
            $table->string('default_variant_policy')->default('log_variant');
            $table->text('instructions')->nullable();
            $table->text('safety_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'normalized_name']);
            $table->index(['category', 'granularity']);
            $table->index('tracking_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
