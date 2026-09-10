<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskStatisticsController extends Controller
{
    /**
     * Authoritative completion figures computed from database records.
     * Nothing is accepted from the client.
     */
    public function show(Task $task): JsonResponse
    {
        Gate::authorize('viewStatistics', $task);

        $totalStudents = User::where('batch_id', $task->batch_id)
            ->where('role', 'student')
            ->count();

        $completedStudents = DB::table('task_completions')
            ->where('task_id', $task->id)
            ->count();

        $remaining = max(0, $totalStudents - $completedStudents);

        return response()->json([
            'task_id' => $task->id,
            'task_title' => $task->title,
            'total_students' => $totalStudents,
            'completed_students' => $completedStudents,
            'remaining_students' => $remaining,
            // An empty batch reports 0% — never a division-by-zero error.
            'completion_percentage' => $totalStudents > 0
                ? (int) round(($completedStudents / $totalStudents) * 100)
                : 0,
        ]);
    }
}
