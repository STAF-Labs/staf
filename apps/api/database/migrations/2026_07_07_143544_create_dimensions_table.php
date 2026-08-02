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
        Schema::create('dimensions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_content_type_id')->constrained('game_content_types')->cascadeOnDelete();

            $table->string('name', 64);
            $table->string('slug', 100);

            $table->string('selection_mode')->default('single');
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->jsonb('notes')->nullable();

            $table->timestampsTz();

            $table->unique(['game_content_type_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dimensions');
    }
};
