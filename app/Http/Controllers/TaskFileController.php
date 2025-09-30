<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Brand;
use App\Models\TaskFileThumbnail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class TaskFileController extends Controller
{
    // GET /brands/{brand}/tasks/{task}/files
    public function index(Brand $brand, Task $task)
    {
        // Return list of thumbnails for this task: [{ name, thumbnail_url }]
        $items = TaskFileThumbnail::query()
            ->where('task_id', $task->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'thumbnail_url' => Storage::url($r->thumbnail_path),
            ])->values();

        return response()->json($items);
    }

    // POST /brands/{brand}/tasks/{task}/files/thumbnail
    public function thumbnail(Request $request, Brand $brand, Task $task)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,bmp'],
            'name' => ['nullable', 'string', 'max:255'],
            'max'  => ['nullable', 'integer', 'min:100', 'max:4000'],
        ]);

        $file = $request->file('file');
        $originalName = $request->string('name')->toString() ?: ($file?->getClientOriginalName() ?? 'image');
        $max = (int)($request->input('max', 1000));

        // Sanitize filename
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $base = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        if ($base === '') { $base = 'image'; }
        $filename = $base.'.'.$ext;

        // Build storage path under public disk
        $dir = "tasks/{$task->id}/thumbnails";
        $path = $dir.'/'.$filename;

        // Ensure unique
        $counter = 1;
        while (Storage::disk('public')->exists($path)) {
            $filename = $base.'-'.$counter.'.'.$ext;
            $path = $dir.'/'.$filename;
            $counter++;
        }

        // Create thumbnail with max side = $max
        $manager = new ImageManager(new ImagickDriver());
        $image = $manager->read($file->getRealPath());
        $w = $image->width();
        $h = $image->height();
        if ($w > $h) {
            $image->scale($max, null);
        } else {
            $image->scale(null, $max);
        }
        // Ensure directory exists and save to public disk
        Storage::disk('public')->makeDirectory($dir);
        $stream = $image->toJpeg(85);
        Storage::disk('public')->put($path, $stream);

        // Upsert DB record by original provided name (not unique-path), keep latest path
        $record = TaskFileThumbnail::updateOrCreate(
            ['task_id' => $task->id, 'name' => $originalName],
            ['thumbnail_path' => $path]
        );

        return response()->json([
            'name' => $record->name,
            'thumbnail_url' => Storage::url($record->thumbnail_path),
        ]);
    }

    // DELETE /brands/{brand}/tasks/{task}/files
    public function destroy(Request $request, Brand $brand, Task $task)
    {
        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
        ]);

        $filename = $request->input('filename');

        $record = TaskFileThumbnail::query()
            ->where('task_id', $task->id)
            ->where('name', $filename)
            ->first();

        if ($record) {
            // Delete file from storage
            if (Storage::disk('public')->exists($record->thumbnail_path)) {
                Storage::disk('public')->delete($record->thumbnail_path);
            }
            // Delete DB record
            $record->delete();
        }

        return response()->json(['success' => true]);
    }
}
