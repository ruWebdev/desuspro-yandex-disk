<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        // Ensure Performer role exists
        $performer = Role::firstOrCreate(['name' => 'Performer', 'guard_name' => 'web']);

        // Find old roles if they exist
        $photographer = Role::where('name', 'Photographer')->first();
        $photoEditor = Role::where('name', 'PhotoEditor')->first();

        // Remap assignments in model_has_roles to Performer
        $table = config('permission.table_names.model_has_roles', 'model_has_roles');
        if ($photographer) {
            DB::table($table)
                ->where('role_id', $photographer->id)
                ->update(['role_id' => $performer->id]);
        }
        if ($photoEditor) {
            DB::table($table)
                ->where('role_id', $photoEditor->id)
                ->update(['role_id' => $performer->id]);
        }

        // Remove old roles
        if ($photographer) { $photographer->delete(); }
        if ($photoEditor) { $photoEditor->delete(); }
    }

    public function down(): void
    {
        // Recreate old roles if needed
        $photographer = Role::firstOrCreate(['name' => 'Photographer', 'guard_name' => 'web']);
        $photoEditor = Role::firstOrCreate(['name' => 'PhotoEditor', 'guard_name' => 'web']);
        // We cannot safely remap users back; keep existing Performer assignments.
    }
};
