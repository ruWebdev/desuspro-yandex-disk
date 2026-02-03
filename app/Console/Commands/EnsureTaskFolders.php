<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\YandexToken;
use App\Services\YandexDiskService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnsureTaskFolders extends Command
{
    protected $signature = 'tasks:ensure-folders {--limit=200 : Max tasks to process per run}';
    protected $description = 'Ensure Yandex.Disk folders exist for tasks where folder_created = false. Marks as true if created or already exists.';

    public function handle(YandexDiskService $disk): int
    {
        $limit = (int) $this->option('limit');
        $limit = $limit > 0 ? $limit : 200;

        $token = YandexToken::orderByDesc('updated_at')->first();
        if (!$token) {
            $this->warn('No Yandex token found; skipping');
            return self::SUCCESS;
        }
        $token = $disk->ensureValidToken($token);
        $accessToken = $token->access_token;

        $processed = 0; $created = 0; $already = 0; $errors = 0; $linked = 0;

        Task::query()
            ->with(['brand:id,name', 'type:id,name,prefix', 'article:id,name'])
            ->where('folder_created', false)
            ->orderBy('id')
            ->limit($limit)
            ->chunkById(100, function ($tasks) use ($disk, $accessToken, &$processed, &$created, &$already, &$errors, &$linked) {
                foreach ($tasks as $task) {
                    $processed++;
                    try {
                        $brandName = $this->sanitizeName(optional($task->brand)->name ?? '');
                        $typeName = $this->sanitizeName(optional($task->type)->name ?? 'Тип');
                        $articleName = $this->sanitizeName(optional($task->article)->name ?? $task->name);
                        if ($brandName === '' || $typeName === '' || $articleName === '') {
                            // Skip incomplete tasks; log and continue
                            Log::warning('EnsureTaskFolders: missing names', ['task_id' => $task->id]);
                            continue;
                        }
                        $prefix = $task->type?->prefix ?: mb_substr($typeName, 0, 1);

                        $brandPath = '/' . $brandName;
                        $typePath = $brandPath . '/' . $typeName;
                        $leaf = $typePath . '/' . $prefix . '_' . $articleName;

                        // Check if folder exists and/or has public_url
                        $meta = [];
                        try {
                            $meta = $disk->getResource($accessToken, $leaf, ['public_url','path','name']);
                        } catch (\Throwable $e) {
                            $meta = [];
                        }
                        $publicUrl = $meta['public_url'] ?? null;

                        if (!$publicUrl) {
                            // Ensure brand and type folders exist, then create leaf and publish
                            try { $disk->createFolder($accessToken, $brandPath); } catch (\Throwable $e) {}
                            try { $disk->createFolder($accessToken, $typePath); } catch (\Throwable $e) {}
                            $result = $disk->createFolderPublic($accessToken, $leaf);
                            $publicUrl = $result['public_url'] ?? null;
                            $created++;
                        } else {
                            $already++;
                        }

                        // Update task flags and link
                        $updated = false;
                        if (!$task->folder_created) { $task->folder_created = true; $updated = true; }
                        if ($publicUrl && !$task->public_link) { $task->public_link = $publicUrl; $updated = true; $linked++; }
                        if ($updated) { $task->save(); }
                    } catch (\Throwable $e) {
                        $errors++;
                        Log::error('EnsureTaskFolders failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                    }
                }
            }, 'id');

        $this->info("Processed: {$processed}, created: {$created}, already: {$already}, linked: {$linked}, errors: {$errors}");
        return self::SUCCESS;
    }

    private function sanitizeName(string $name): string
    {
        $name = preg_replace('/[\\\n\r\t]/u', ' ', $name);
        $name = str_replace('/', '-', $name);
        return trim($name);
    }
}
