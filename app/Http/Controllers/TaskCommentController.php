<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TaskCommentController extends Controller
{
    public function index(Brand $brand, Task $task): JsonResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $comments = TaskComment::with('user:id,name')
            ->where('task_id', $task->id)
            ->orderBy('created_at')
            ->get(['id','task_id','user_id','content','image_path','created_at']);
        return response()->json($comments);
    }

    public function store(Request $request, Brand $brand, Task $task): Response
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $data = $request->validate([
            'content' => ['nullable','string'],
            'image' => ['nullable','image','max:5120'], // 5MB per image
        ]);

        // Ensure at least content or image is provided
        if (empty(trim($data['content'] ?? '')) && !$request->hasFile('image')) {
            return response()->json(['errors' => ['content' => ['Комментарий или изображение обязательно']]], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('comment-images', 'public');
        }

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
            'image_path' => $imagePath,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'comment' => $comment->load('user:id,name')]);
        }
        return back()->with('status', 'task-comment-created');
    }

    public function destroy(Request $request, Brand $brand, Task $task, TaskComment $comment): Response
    {
        abort_unless($task->brand_id === $brand->id && $comment->task_id === $task->id, 404);

        // Delete the image file if it exists
        if ($comment->image_path) {
            Storage::disk('public')->delete($comment->image_path);
        }

        $comment->delete();
        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok']);
        }
        return back()->with('status', 'task-comment-deleted');
    }
}
