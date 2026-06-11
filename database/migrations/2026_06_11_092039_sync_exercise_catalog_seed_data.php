<?php

use App\Services\WorkoutMemory\ExerciseCatalogSeederData;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(DatabaseSeeder::class)->run(app(ExerciseCatalogSeederData::class));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Forward-only catalog sync: do not remove exercises that may be referenced by workout history.
    }
};
