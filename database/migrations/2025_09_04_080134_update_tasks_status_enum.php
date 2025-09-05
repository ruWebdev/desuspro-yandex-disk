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
        // First, we need to drop the constraint, modify the column, and then re-add the constraint
        Schema::table('tasks', function (Blueprint $table) {
            // For MySQL/MariaDB
            if (config('database.default') === 'mysql' || config('database.default') === 'mariadb') {
                // Get the column type and change it to string first to avoid enum issues
                $table->string('status')->default('created')->change();
                
                // Then modify it to the new enum with all required values
                $table->enum('status', [
                    'created',
                    'assigned',
                    'in_progress',
                    'on_review',
                    'rework',
                    'question',
                    'rejected',
                    'accepted',
                    'cancelled',
                    'done'
                ])->default('created')->change();
            } else {
                // For other databases that don't support enum natively
                $table->string('status', 20)->default('created')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the original enum values if needed
        Schema::table('tasks', function (Blueprint $table) {
            if (config('database.default') === 'mysql' || config('database.default') === 'mariadb') {
                $table->enum('status', ['created', 'assigned', 'done'])->default('created')->change();
            } else {
                $table->string('status', 20)->default('created')->change();
            }
        });
    }
};
