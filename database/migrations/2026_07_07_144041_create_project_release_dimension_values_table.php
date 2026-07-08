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
        Schema::create('project_release_dimension_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_release_id')->constrained('project_releases')->cascadeOnDelete();
            $table->foreignId('dimension_value_id')->constrained('dimension_values')->cascadeOnDelete();

            $table->timestampsTz();

            $table->unique(['project_release_id', 'dimension_value_id'], 'project_release_dimension_values_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_release_dimension_values');
    }
};
