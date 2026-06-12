<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Guarantee at most one in-progress session per user. Concurrent auto-starts
     * could otherwise race a second active session into existence; the unique
     * partial index makes the database reject it and the transaction retry then
     * finds the surviving active session.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
UPDATE workout_sessions
SET status = 'completed', completed_at = COALESCE(completed_at, started_at)
WHERE status = 'in_progress'
  AND id NOT IN (
    SELECT MAX(id) FROM workout_sessions WHERE status = 'in_progress' GROUP BY user_id
  )
SQL);

        DB::statement("CREATE UNIQUE INDEX workout_sessions_one_active_per_user ON workout_sessions (user_id) WHERE status = 'in_progress'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX workout_sessions_one_active_per_user');
    }
};
