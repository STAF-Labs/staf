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
        Schema::create('game_content_type_dimensions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_content_type_id')->constrained('game_content_types')->cascadeOnDelete();
            $table->foreignId('dimension_id')->constrained('dimensions')->cascadeOnDelete();

            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);

            $table->jsonb('notes')->nullable();

            $table->timestampsTz();

            $table->unique(['game_content_type_id', 'dimension_id'], 'game_content_type_dimensions_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_content_type_dimensions');
    }
};
