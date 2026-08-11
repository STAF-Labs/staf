<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_releases')
            ->where('status', 'draft')
            ->update(['status' => 'on_moderation']);

        DB::statement("ALTER TABLE project_releases ALTER COLUMN status SET DEFAULT 'on_moderation'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE project_releases ALTER COLUMN status DROP DEFAULT");

        DB::table('project_releases')
            ->where('status', 'on_moderation')
            ->update(['status' => 'draft']);
    }
};
