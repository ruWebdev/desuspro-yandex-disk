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
        // 1) Ensure roles exist (Manager, Performer)
        $roles = [
            'Manager',
            'Performer',
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

        // 3) Create performers
        $performerA = User::factory()->create([
            'name' => 'Performer A',
            'email' => 'performer.a@example.com',
        ]);
        $performerA->assignRole('Performer');

        $performerB = User::factory()->create([
            'name' => 'Performer B',
            'email' => 'performer.b@example.com',
        ]);
        $performerB->assignRole('Performer');

        // 4) Assign users to the manager (many-to-many, intersections allowed)
        $manager->managedUsers()->syncWithoutDetaching([
            $performerA->id,
            $performerB->id,
        ]);

        // 5) Demonstrate intersection: assign one user to another manager too
        $manager2 = User::factory()->create([
            'name' => 'Manager Two',
            'email' => 'manager2@example.com',
        ]);
        $manager2->assignRole('Manager');
        $manager2->managedUsers()->syncWithoutDetaching([
            $performerA->id, // intersection with manager one
        ]);
    }
}
