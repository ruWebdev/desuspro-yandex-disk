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
        Schema::table('subtask_comments', function (Blueprint $table) {
            $table->foreign(['subtask_id'])->references(['id'])->on('subtasks')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subtask_comments', function (Blueprint $table) {
            $table->dropForeign('subtask_comments_subtask_id_foreign');
            $table->dropForeign('subtask_comments_user_id_foreign');
        });
    }
};
