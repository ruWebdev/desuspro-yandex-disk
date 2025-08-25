<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1) Ensure roles exist
        $roles = [
            'Manager',
            'PhotoEditor',
            'Photographer',
        ];

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName);
        }

        // 2) Create a sample manager
        $manager = User::factory()->create([
            'name' => 'Manager One',
            'email' => 'manager@example.com',
        ]);
        $manager->assignRole('Manager');

        // 3) Create photographers and photo editors
        $photographerA = User::factory()->create([
            'name' => 'Photographer A',
            'email' => 'photographer.a@example.com',
        ]);
        $photographerA->assignRole('Photographer');

        $photographerB = User::factory()->create([
            'name' => 'Photographer B',
            'email' => 'photographer.b@example.com',
        ]);
        $photographerB->assignRole('Photographer');

        $editorA = User::factory()->create([
            'name' => 'Photo Editor A',
            'email' => 'editor.a@example.com',
        ]);
        $editorA->assignRole('PhotoEditor');

        $editorB = User::factory()->create([
            'name' => 'Photo Editor B',
            'email' => 'editor.b@example.com',
        ]);
        $editorB->assignRole('PhotoEditor');

        // 4) Assign users to the manager (many-to-many, intersections allowed)
        $manager->managedUsers()->syncWithoutDetaching([
            $photographerA->id,
            $photographerB->id,
            $editorA->id,
        ]);

        // 5) Demonstrate intersection: assign one user to another manager too
        $manager2 = User::factory()->create([
            'name' => 'Manager Two',
            'email' => 'manager2@example.com',
        ]);
        $manager2->assignRole('Manager');
        $manager2->managedUsers()->syncWithoutDetaching([
            $photographerA->id, // intersection with manager one
            $editorB->id,
        ]);
    }
}
