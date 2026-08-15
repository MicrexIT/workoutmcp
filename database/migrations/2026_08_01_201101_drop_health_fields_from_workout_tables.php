<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the two fields that made this connector a handler of personal health data.
     *
     * Stored values are destroyed; the down migration restores the columns empty.
     */
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropColumn('bodyweight_kg');
        });

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn('injuries_constraints');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->decimal('bodyweight_kg', 8, 2)->nullable();
        });

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->text('injuries_constraints')->nullable();
        });
    }
};
