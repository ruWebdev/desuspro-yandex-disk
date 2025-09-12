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
        Schema::table('manager_user_assignments', function (Blueprint $table) {
            $table->foreign(['manager_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manager_user_assignments', function (Blueprint $table) {
            $table->dropForeign('manager_user_assignments_manager_id_foreign');
            $table->dropForeign('manager_user_assignments_user_id_foreign');
        });
    }
};
