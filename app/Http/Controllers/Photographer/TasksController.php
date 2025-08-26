<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\Subtask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TasksController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $subtasks = Subtask::query()
            ->where('assignee_id', $user->id)
            ->where('ownership', 'Photographer')
            ->with(['task:id,brand_id,name', 'task.brand:id,name'])
            ->orderByDesc('created_at')
            ->get(['id','task_id','name','status','ownership','assignee_id','public_link','highlighted','comment','size','created_at']);

        return Inertia::render('Photographer/Tasks', [
            'subtasks' => $subtasks,
        ]);
    }
}
