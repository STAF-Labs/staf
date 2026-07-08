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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('display_name', 80)->nullable();
            $table->jsonb('bio')->nullable();
            $table->jsonb('website_urls')->nullable();
            $table->date('birthday')->nullable();

            $table->boolean('is_public')->default(true);
            $table->boolean('show_online_status')->default(true);
            $table->boolean('show_last_seen_at')->default(true);

            $table->timestampsTz();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
