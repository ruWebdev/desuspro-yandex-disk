<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Создание таблиц ролей и прав (совместимо со spatie/laravel-permission).
     * Идемпотентно: проверяет наличие таблиц перед созданием.
     */
    public function up(): void
    {
        // Таблица прав
        if (! Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            Schema::create(config('permission.table_names.permissions', 'permissions'), function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name'); // например: "edit articles"
                $table->string('guard_name'); // например: "web"
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        // Таблица ролей
        if (! Schema::hasTable(config('permission.table_names.roles', 'roles'))) {
            Schema::create(config('permission.table_names.roles', 'roles'), function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name'); // например: "Admin"
                $table->string('guard_name'); // например: "web"
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        $tableNames = config('permission.table_names');

        // Связь ролей и прав
        if (! Schema::hasTable($tableNames['role_has_permissions'])) {
            Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');

                $table->foreign('permission_id')
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');
                $table->foreign('role_id')
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');

                $table->primary(['permission_id', 'role_id']);
            });
        }

        // Связь модели и прав
        if (! Schema::hasTable($tableNames['model_has_permissions'])) {
            Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');

                $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

                $table->foreign('permission_id')
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');

                $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            });
        }

        // Связь модели и ролей
        if (! Schema::hasTable($tableNames['model_has_roles'])) {
            Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');

                $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

                $table->foreign('role_id')
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');

                $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
            });
        }
    }

    /**
     * Откат изменений (идемпотентно удаляет только существующие таблицы в корректном порядке).
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        foreach ([
            $tableNames['model_has_roles'] ?? 'model_has_roles',
            $tableNames['model_has_permissions'] ?? 'model_has_permissions',
            $tableNames['role_has_permissions'] ?? 'role_has_permissions',
            $tableNames['roles'] ?? 'roles',
            $tableNames['permissions'] ?? 'permissions',
        ] as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
            }
        }
    }
};
