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
        Schema::table('workout_change_events', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable();
            $table->string('source_message_id')->nullable();
            $table->timestamp('occurred_at')->nullable();

            $table->unique(['user_id', 'idempotency_key'], 'workout_change_events_user_id_idempotency_key_unique');
            $table->index(['user_id', 'source_message_id'], 'workout_change_events_user_id_source_message_id_index');
            $table->index(['workout_session_id', 'occurred_at'], 'workout_change_events_session_occurred_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_change_events', function (Blueprint $table) {
            $table->dropUnique('workout_change_events_user_id_idempotency_key_unique');
            $table->dropIndex('workout_change_events_user_id_source_message_id_index');
            $table->dropIndex('workout_change_events_session_occurred_at_index');
            $table->dropColumn(['idempotency_key', 'source_message_id', 'occurred_at']);
        });
    }
};
