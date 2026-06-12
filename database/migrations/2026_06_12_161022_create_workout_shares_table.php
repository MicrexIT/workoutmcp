<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 32)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['workout_session_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_shares');
    }
};
