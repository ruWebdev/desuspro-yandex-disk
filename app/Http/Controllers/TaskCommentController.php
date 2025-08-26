<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaskCommentController extends Controller
{
    public function index(Brand $brand, Task $task): JsonResponse
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $comments = TaskComment::with('user:id,name')
            ->where('task_id', $task->id)
            ->orderBy('created_at')
            ->get(['id','task_id','user_id','content','created_at']);
        return response()->json($comments);
    }

    public function store(Request $request, Brand $brand, Task $task): Response
    {
        abort_unless($task->brand_id === $brand->id, 404);
        $data = $request->validate([
            'content' => ['required','string'],
        ]);
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);
        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'comment' => $comment->load('user:id,name')]);
        }
        return back()->with('status', 'task-comment-created');
    }

    public function destroy(Request $request, Brand $brand, Task $task, TaskComment $comment): Response
    {
        abort_unless($task->brand_id === $brand->id && $comment->task_id === $task->id, 404);
        $comment->delete();
        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok']);
        }
        return back()->with('status', 'task-comment-deleted');
    }
}
