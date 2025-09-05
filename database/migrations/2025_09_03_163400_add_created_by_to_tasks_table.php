<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('brand_id')
                ->constrained('users')->nullOnDelete();
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            // If the platform requires explicit index drop (older versions)
            if (Schema::hasColumn('tasks', 'created_by')) {
                $table->dropIndex(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
