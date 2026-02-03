<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all brands and users with the Performer role
        $brands = Brand::all();
        $performers = User::role('Performer')->get();
        $managers = User::role('Manager')->get();
        
        if ($brands->isEmpty() || $performers->isEmpty() || $managers->isEmpty()) {
            $this->command->warn('No brands, performers or managers found. Please run the main seeder first.');
            return;
        }

        $statuses = ['created', 'assigned', 'in_progress', 'on_review', 'accepted', 'rework', 'cancelled'];
        $priorities = ['low', 'medium', 'high', 'urgent'];
        $ownerships = ['Photographer', 'PhotoEditor'];
        
        $tasks = [];
        
        for ($i = 1; $i <= 100; $i++) {
            $brand = $brands->random();
            $status = $statuses[array_rand($statuses)];
            $assignee = $status === 'created' ? null : $performers->random();
            $priority = $priorities[array_rand($priorities)];
            $ownership = $ownerships[array_rand($ownerships)];
            $manager = $managers->random();
            
            $tasks[] = [
                'name' => "Задача #$i",
                'brand_id' => $brand->id,
                'status' => $status,
                'ownership' => $ownership,
                'assignee_id' => $assignee ? $assignee->id : null,
                'priority' => $priority,
                'comment' => 'Тестовый комментарий к задаче #' . $i,
                'created_by' => $manager->id,
                'created_at' => now()->subDays(rand(0, 30)),
                'updated_at' => now(),
            ];

            // Insert in chunks of 20 for better performance
            if ($i % 20 === 0) {
                try {
                    DB::table('tasks')->insert($tasks);
                    $this->command->info("Inserted $i tasks so far...");
                    $tasks = [];
                } catch (\Exception $e) {
                    $this->command->error("Error inserting tasks: " . $e->getMessage());
                    return;
                }
            }
        }

        // Insert any remaining tasks
        if (!empty($tasks)) {
            DB::table('tasks')->insert($tasks);
        }

        $this->command->info('Successfully seeded 100 tasks.');
    }
}
