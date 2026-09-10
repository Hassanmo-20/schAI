<?php

namespace App\Services;

use App\Exceptions\TaskAlreadyCompletedException;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Single place that writes/removes a completion row.
 *
 * Used by both `TaskCompletionController` (the REST endpoint) and the
 * assistant's "complete task" chat command, so completion semantics —
 * including the duplicate-completion race handling — exist in one spot.
 */
class TaskCompletionService
{
    /**
     * @throws TaskAlreadyCompletedException
     */
    public function complete(User $user, Task $task): TaskCompletion
    {
        try {
            return TaskCompletion::create([
                'task_id' => $task->id,
                'student_id' => $user->id,
                'completed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The unique(task_id, student_id) constraint is the authority
            // here: relying on a prior SELECT would leave a race window.
            throw new TaskAlreadyCompletedException;
        }
    }

    public function uncomplete(User $user, Task $task): bool
    {
        return TaskCompletion::where('task_id', $task->id)
            ->where('student_id', $user->id)
            ->delete() > 0;
    }
}
