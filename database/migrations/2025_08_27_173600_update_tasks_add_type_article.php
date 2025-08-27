<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_type_id')->nullable()->after('brand_id')->constrained('task_types')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->after('task_type_id')->constrained('articles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('article_id');
            $table->dropConstrainedForeignId('task_type_id');
        });
    }
};
