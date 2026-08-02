<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dimension_values', function (Blueprint $table) {
            $table->unique(['dimension_id', 'name'], 'dimension_values_dimension_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dimension_values', function (Blueprint $table) {
            $table->dropUnique('dimension_values_dimension_name_unique');
        });
    }
};
