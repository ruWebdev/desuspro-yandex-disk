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
        Schema::create('manager_user_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('manager_id');
            $table->unsignedBigInteger('user_id')->index('manager_user_assignments_user_id_foreign');
            $table->timestamps();

            $table->unique(['manager_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manager_user_assignments');
    }
};
