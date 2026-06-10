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
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('occurred_timezone')->default('UTC');
            $table->string('status')->default('completed');
            $table->string('kind')->default('mixed');
            $table->string('source')->default('chatgpt');
            $table->unsignedTinyInteger('perceived_effort')->nullable();
            $table->decimal('bodyweight_kg', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('raw_input')->nullable();
            $table->string('source_message_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'idempotency_key']);
            $table->unique(['user_id', 'source_message_id']);
            $table->index(['user_id', 'status', 'started_at']);
            $table->index(['user_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};
