<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Extend enum to include all UI-used statuses
        DB::statement("ALTER TABLE `tasks` MODIFY COLUMN `status` ENUM('created','assigned','on_review','rework','rejected','accepted','done') NOT NULL DEFAULT 'created'");
    }

    public function down(): void
    {
        // Revert to original enum defined in the initial migration
        DB::statement("ALTER TABLE `tasks` MODIFY COLUMN `status` ENUM('created','assigned','done') NOT NULL DEFAULT 'created'");
    }
};
