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
        Schema::table('task_types', function (Blueprint $table) {
            $table->boolean('create_empty_folder')->default(false)->after('prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_types', function (Blueprint $table) {
            $table->dropColumn('create_empty_folder');
        });
    }
};
