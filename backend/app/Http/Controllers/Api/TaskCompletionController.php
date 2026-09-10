<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TaskAlreadyCompletedException;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\TaskCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskCompletionController extends Controller
{
    public function __construct(private readonly TaskCompletionService $completions) {}

    /**
     * Mark the task complete for the authenticated student.
     * The student id always comes from the token — never from the client.
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('complete', $task);

        try {
            $completion = $this->completions->complete($request->user(), $task);
        } catch (TaskAlreadyCompletedException) {
            return response()->json(['message' => 'Task already marked as complete'], 409);
        }

        return response()->json([
            'message' => 'Task marked as complete',
            'is_completed' => true,
            'completed_at' => $completion->completed_at->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('complete', $task);

        if (! $this->completions->uncomplete($request->user(), $task)) {
            return response()->json(['message' => 'Task is not marked as complete'], 404);
        }

        return response()->json([
            'message' => 'Completion removed',
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }
}
