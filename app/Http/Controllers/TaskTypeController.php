<?php

namespace App\Http\Controllers;

use App\Models\TaskType;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaskTypeController extends Controller
{
    public function index(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            $items = TaskType::orderBy('name')->get(['id', 'name', 'prefix']);
            return response()->json(['data' => $items]);
        }
        return Inertia::render('Admin/TaskTypes/Index');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:task_types,name'],
            'prefix' => ['nullable', 'string', 'max:10'],
        ]);
        $item = TaskType::create($data);
        if ($request->expectsJson()) {
            return response()->json(['data' => $item], 201);
        }
        return back()->with('status', 'task-type-created');
    }

    public function update(Request $request, TaskType $taskType): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:task_types,name,' . $taskType->id],
            'prefix' => ['nullable', 'string', 'max:10'],
        ]);
        $taskType->update($data);
        if ($request->expectsJson()) {
            return response()->json(['data' => $taskType]);
        }
        return back()->with('status', 'task-type-updated');
    }

    public function destroy(Request $request, TaskType $taskType): RedirectResponse|JsonResponse
    {
        $taskType->delete();
        if ($request->expectsJson()) {
            return response()->json(['status' => 'deleted']);
        }
        return back()->with('status', 'task-type-deleted');
    }
}
