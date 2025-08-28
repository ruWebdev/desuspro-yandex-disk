<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Extend enum to support new statuses (keep 'done' for backward compatibility)
        DB::statement("ALTER TABLE `tasks` MODIFY `status` ENUM('created','assigned','review','rework','accepted','done') NOT NULL DEFAULT 'created'");
    }

    public function down(): void
    {
        // Revert to the original set
        DB::statement("ALTER TABLE `tasks` MODIFY `status` ENUM('created','assigned','done') NOT NULL DEFAULT 'created'");
    }
};
