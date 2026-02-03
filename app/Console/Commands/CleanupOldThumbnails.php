<?php

namespace App\Console\Commands;

use App\Models\TaskFileThumbnail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CleanupOldThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'thumbnails:cleanup
                            {--days=14 : Number of days after accepted_at to keep thumbnails}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old task file thumbnails that have been marked as accepted for more than specified days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = now()->subDays($days);
        
        $this->info("Looking for thumbnails marked as accepted before: {$cutoffDate->toDateTimeString()}");
        
        // Find thumbnails that are older than the cutoff date
        $thumbnails = TaskFileThumbnail::query()
            ->whereNotNull('accepted_at')
            ->where('accepted_at', '<=', $cutoffDate)
            ->get();
        
        if ($thumbnails->isEmpty()) {
            $this->info('No thumbnails found for cleanup.');
            return self::SUCCESS;
        }
        
        $this->info("Found {$thumbnails->count()} thumbnail(s) to clean up.");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
            $this->table(
                ['ID', 'Task ID', 'Name', 'Accepted At', 'Days Old'],
                $thumbnails->map(fn($t) => [
                    $t->id,
                    $t->task_id,
                    $t->name,
                    $t->accepted_at->toDateTimeString(),
                    $t->accepted_at->diffInDays(now())
                ])
            );
            return self::SUCCESS;
        }
        
        $deletedFiles = 0;
        $deletedRecords = 0;
        $errors = 0;
        
        // Group by task_id to potentially delete entire directories
        $byTask = $thumbnails->groupBy('task_id');
        
        foreach ($byTask as $taskId => $taskThumbnails) {
            $this->line("Processing task #{$taskId} ({$taskThumbnails->count()} thumbnails)...");
            
            // Check if ALL thumbnails for this task are marked for deletion
            $allTaskThumbnails = TaskFileThumbnail::where('task_id', $taskId)->get();
            $allMarkedForDeletion = $allTaskThumbnails->every(function ($thumb) use ($cutoffDate) {
                return $thumb->accepted_at && $thumb->accepted_at <= $cutoffDate;
            });
            
            if ($allMarkedForDeletion) {
                // Delete entire directory
                try {
                    $dir = "tasks/{$taskId}/thumbnails";
                    if (Storage::disk('public')->exists($dir)) {
                        Storage::disk('public')->deleteDirectory($dir);
                        $this->info("  ✓ Deleted entire directory: {$dir}");
                        $deletedFiles += $taskThumbnails->count();
                    }
                } catch (\Throwable $e) {
                    $this->error("  ✗ Failed to delete directory for task #{$taskId}: {$e->getMessage()}");
                    Log::error('Failed to delete thumbnails directory', [
                        'task_id' => $taskId,
                        'error' => $e->getMessage()
                    ]);
                    $errors++;
                    continue;
                }
            } else {
                // Delete individual files
                foreach ($taskThumbnails as $thumbnail) {
                    try {
                        if (Storage::disk('public')->exists($thumbnail->thumbnail_path)) {
                            Storage::disk('public')->delete($thumbnail->thumbnail_path);
                            $this->info("  ✓ Deleted file: {$thumbnail->thumbnail_path}");
                            $deletedFiles++;
                        }
                    } catch (\Throwable $e) {
                        $this->error("  ✗ Failed to delete file {$thumbnail->thumbnail_path}: {$e->getMessage()}");
                        Log::error('Failed to delete thumbnail file', [
                            'thumbnail_id' => $thumbnail->id,
                            'path' => $thumbnail->thumbnail_path,
                            'error' => $e->getMessage()
                        ]);
                        $errors++;
                    }
                }
            }
            
            // Delete database records
            try {
                $count = TaskFileThumbnail::query()
                    ->where('task_id', $taskId)
                    ->whereNotNull('accepted_at')
                    ->where('accepted_at', '<=', $cutoffDate)
                    ->delete();
                
                $deletedRecords += $count;
                $this->info("  ✓ Deleted {$count} database record(s)");
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed to delete database records for task #{$taskId}: {$e->getMessage()}");
                Log::error('Failed to delete thumbnail DB records', [
                    'task_id' => $taskId,
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }
        
        $this->newLine();
        $this->info("Cleanup completed:");
        $this->line("  - Files deleted: {$deletedFiles}");
        $this->line("  - Database records deleted: {$deletedRecords}");
        
        if ($errors > 0) {
            $this->warn("  - Errors encountered: {$errors}");
            Log::warning('Thumbnail cleanup completed with errors', [
                'deleted_files' => $deletedFiles,
                'deleted_records' => $deletedRecords,
                'errors' => $errors
            ]);
            return self::FAILURE;
        }
        
        Log::info('Thumbnail cleanup completed successfully', [
            'deleted_files' => $deletedFiles,
            'deleted_records' => $deletedRecords
        ]);
        
        return self::SUCCESS;
    }
}
