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
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign(['article_id'])->references(['id'])->on('articles')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['assignee_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['brand_id'])->references(['id'])->on('brands')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['created_by'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['task_type_id'])->references(['id'])->on('task_types')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign('tasks_article_id_foreign');
            $table->dropForeign('tasks_assignee_id_foreign');
            $table->dropForeign('tasks_brand_id_foreign');
            $table->dropForeign('tasks_created_by_foreign');
            $table->dropForeign('tasks_task_type_id_foreign');
        });
    }
};
