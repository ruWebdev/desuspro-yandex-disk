<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add composite indexes to optimize common query patterns:
     * - Dashboard queries filtering by status and sorting by created_at
     * - Brand-specific task lists with status filtering
     * - Performer dashboards (assignee_id + status)
     * - Manager dashboards (created_by + status)
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Optimize dashboard queries with status filtering and date sorting
            $table->index(['status', 'created_at'], 'tasks_status_created_at_index');
            
            // Optimize brand-specific task queries with status filtering
            $table->index(['brand_id', 'status'], 'tasks_brand_status_index');
            
            // Optimize performer dashboard queries
            $table->index(['assignee_id', 'status'], 'tasks_assignee_status_index');
            
            // Optimize manager dashboard queries
            $table->index(['created_by', 'status'], 'tasks_created_by_status_index');
            
            // Optimize date-based filtering (already has created_at in timestamps, but explicit for clarity)
            // Note: created_at already indexed via timestamps(), so we skip standalone index
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_status_created_at_index');
            $table->dropIndex('tasks_brand_status_index');
            $table->dropIndex('tasks_assignee_status_index');
            $table->dropIndex('tasks_created_by_status_index');
        });
    }
};
