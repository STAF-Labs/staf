<?php

use App\Enums\CommonStatus;
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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 64);
            $table->string('slug', 100)->unique();
            $table->string('summary', 255)->nullable();
            $table->jsonb('description')->nullable();
            $table->jsonb('website_urls')->nullable();
            $table->string('contact_email')->nullable();

            $table->boolean('visibility')->default(true);
            $table->string('status', 32)->default(CommonStatus::ACTIVE)->index();
            $table->timestamp('verified_at')->nullable();

            $table->timestampsTz();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
