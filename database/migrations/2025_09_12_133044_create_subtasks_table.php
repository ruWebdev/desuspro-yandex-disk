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
        Schema::create('subtasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('task_id')->index('subtasks_task_id_foreign');
            $table->string('name')->nullable();
            $table->string('status')->default('created');
            $table->enum('ownership', ['Photographer', 'PhotoEditor'])->nullable();
            $table->unsignedBigInteger('assignee_id')->nullable()->index('subtasks_assignee_id_foreign');
            $table->string('public_link')->nullable();
            $table->boolean('highlighted')->default(false);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subtasks');
    }
};
