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
        Schema::create('subtask_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('subtask_id')->index('subtask_comments_subtask_id_foreign');
            $table->unsignedBigInteger('user_id')->index('subtask_comments_user_id_foreign');
            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subtask_comments');
    }
};
