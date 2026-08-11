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
        Schema::table('project_releases', function (Blueprint $table): void {
            $table->string('type', 32)
                ->default('release')
                ->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_releases', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
