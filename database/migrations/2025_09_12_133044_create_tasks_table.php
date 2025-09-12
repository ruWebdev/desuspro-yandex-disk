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
        Schema::create('tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('brand_id')->index('tasks_brand_id_foreign');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('task_type_id')->nullable()->index('tasks_task_type_id_foreign');
            $table->unsignedBigInteger('article_id')->nullable()->index('tasks_article_id_foreign');
            $table->string('name');
            $table->enum('status', ['created', 'assigned', 'in_progress', 'on_review', 'rework', 'question', 'rejected', 'accepted', 'cancelled', 'done'])->default('created');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('ownership', ['Photographer', 'PhotoEditor']);
            $table->unsignedBigInteger('assignee_id')->nullable()->index('tasks_assignee_id_foreign');
            $table->string('public_link')->nullable();
            $table->boolean('highlighted')->default(false);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->json('source_files')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
