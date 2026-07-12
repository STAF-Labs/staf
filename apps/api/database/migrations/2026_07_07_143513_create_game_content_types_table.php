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
        Schema::create('game_content_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('content_type_id')->constrained('content_types')->cascadeOnDelete();
            $table->jsonb('notes')->nullable();

            $table->timestampsTz();

            $table->unique(['game_id', 'content_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_content_types');
    }
};
