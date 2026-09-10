<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskCompletion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskCompletionController extends Controller
{
    /**
     * Mark the task complete for the authenticated student.
     * The student id always comes from the token — never from the client.
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('complete', $task);

        try {
            $completion = TaskCompletion::create([
                'task_id' => $task->id,
                'student_id' => $request->user()->id,
                'completed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // UNIQUE(task_id, student_id) is the authority here: relying on a
            // prior SELECT would leave a race window between check and insert.
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

        $deleted = TaskCompletion::where('task_id', $task->id)
            ->where('student_id', $request->user()->id)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Task is not marked as complete'], 404);
        }

        return response()->json([
            'message' => 'Completion removed',
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }
}
