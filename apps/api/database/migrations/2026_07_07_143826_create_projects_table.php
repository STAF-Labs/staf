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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->morphs('ownerable');
            $table->foreignId('game_content_type_id')->constrained('game_content_types')->restrictOnDelete();

            $table->string('title', 128);
            $table->string('slug', 128)->unique();
            $table->jsonb('summary')->nullable();
            $table->jsonb('description')->nullable();
            $table->jsonb('tags')->nullable();
            $table->jsonb('website_urls')->nullable();
            $table->string('licence_name', 128)->nullable();

            $table->unsignedTinyInteger('percentage_complete')->default(0);
            $table->string('publication_status', 32)->default('private')->index();
            $table->string('status', 32)->default('draft')->index();

            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
