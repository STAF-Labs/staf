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
        Schema::create('dimension_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dimension_id')->constrained('dimensions')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('dimension_values')->nullOnDelete();

            $table->string('name', 64);
            $table->integer('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dimension_values');
    }
};
