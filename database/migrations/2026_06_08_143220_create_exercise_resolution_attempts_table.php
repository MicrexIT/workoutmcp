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
        Schema::create('exercise_resolution_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('resolution_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('raw_phrase');
            $table->string('normalized_phrase');
            $table->text('context')->nullable();
            $table->string('best_resolution')->default('ambiguous');
            $table->foreignId('best_exercise_id')->nullable()->constrained('exercises')->nullOnDelete();
            $table->decimal('best_confidence', 4, 2)->default(0);
            $table->string('duplicate_risk')->default('low');
            $table->json('candidates')->nullable();
            $table->string('suggested_action')->default('ask_user');
            $table->foreignId('created_exercise_id')->nullable()->constrained('exercises')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'normalized_phrase']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_resolution_attempts');
    }
};
