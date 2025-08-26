<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Task;
use App\Models\Subtask;
use App\Models\SubtaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SubtaskCommentController extends Controller
{
    public function index(Brand $brand, Task $task, Subtask $subtask): JsonResponse
    {
        abort_unless($task->brand_id === $brand->id && $subtask->task_id === $task->id, 404);
        $comments = SubtaskComment::with('user:id,name')
            ->where('subtask_id', $subtask->id)
            ->orderBy('created_at')
            ->get(['id','subtask_id','user_id','content','created_at']);
        return response()->json($comments);
    }

    public function store(Request $request, Brand $brand, Task $task, Subtask $subtask): Response
    {
        abort_unless($task->brand_id === $brand->id && $subtask->task_id === $task->id, 404);
        $data = $request->validate([
            'content' => ['required','string'],
        ]);
        $comment = SubtaskComment::create([
            'subtask_id' => $subtask->id,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);
        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'comment' => $comment->load('user:id,name')]);
        }
        return back()->with('status', 'subtask-comment-created');
    }

    public function destroy(Request $request, Brand $brand, Task $task, Subtask $subtask, SubtaskComment $comment): Response
    {
        abort_unless($task->brand_id === $brand->id && $subtask->task_id === $task->id && $comment->subtask_id === $subtask->id, 404);
        $comment->delete();
        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok']);
        }
        return back()->with('status', 'subtask-comment-deleted');
    }
}
