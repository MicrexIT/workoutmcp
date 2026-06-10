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
        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('set_number');
            $table->unsignedInteger('reps')->nullable();
            $table->decimal('load_kg', 8, 2)->nullable();
            $table->string('load_type')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->decimal('rpe', 3, 1)->nullable();
            $table->decimal('rir', 3, 1)->nullable();
            $table->string('side')->nullable();
            $table->boolean('success')->nullable();
            $table->unsignedTinyInteger('quality_rating')->nullable();
            $table->boolean('is_warmup')->default(false);
            $table->text('raw_set_text')->nullable();
            $table->json('custom_metrics')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['workout_exercise_id', 'set_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
    }
};
