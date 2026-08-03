<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_dimension_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('dimension_value_id')->constrained('dimension_values')->cascadeOnDelete();

            $table->timestampsTz();

            $table->unique(
                ['project_id', 'dimension_value_id'],
                'project_dimension_values_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_dimension_values');
    }
};
