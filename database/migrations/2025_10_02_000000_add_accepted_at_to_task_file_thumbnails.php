<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('task_file_thumbnails', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('thumbnail_path');
            $table->index('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('task_file_thumbnails', function (Blueprint $table) {
            $table->dropIndex(['accepted_at']);
            $table->dropColumn('accepted_at');
        });
    }
};
