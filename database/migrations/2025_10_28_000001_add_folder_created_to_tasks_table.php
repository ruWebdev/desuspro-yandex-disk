<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('folder_created')->default(false)->after('public_link');
            $table->index('folder_created');
        });

        // Backfill: if public_link already set, mark folder_created = true
        try {
            DB::table('tasks')
                ->whereNotNull('public_link')
                ->update(['folder_created' => true]);
        } catch (\Throwable $e) {
            // ignore errors during backfill
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['folder_created']);
            $table->dropColumn('folder_created');
        });
    }
};
