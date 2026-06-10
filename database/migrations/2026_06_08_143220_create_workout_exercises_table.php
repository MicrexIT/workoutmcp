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
        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('name_snapshot');
            $table->string('tracking_mode_snapshot');
            $table->foreignId('exercise_resolution_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_phrase')->nullable();
            $table->string('resolution_type')->default('exact');
            $table->string('variant_label')->nullable();
            $table->text('variant_description')->nullable();
            $table->text('prescription')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['workout_session_id', 'sort_order']);
            $table->index(['exercise_id', 'variant_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_exercises');
    }
};
