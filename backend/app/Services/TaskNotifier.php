<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DeadlineApproaching;
use App\Notifications\TaskPublished;
use App\Notifications\TaskUpdated;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Fan-out point for every task notification.
 *
 * GROUP ISOLATION: recipients are always resolved with
 * `where('batch_id', $task->batch_id)`, so a notification row can only ever be
 * created for a member of the task's own group. Nothing downstream re-checks
 * this — the read endpoints simply return the caller's own rows — which is why
 * this recipient query is the one place that must stay correct.
 *
 * The task's creator is excluded: a representative does not need to be told
 * about their own edit.
 */
class TaskNotifier
{
    /** How far ahead `schai:notify-deadlines` looks. */
    public const REMINDER_WINDOW_HOURS = 24;

    /** Notify the group's students that a new task exists. */
    public function taskPublished(Task $task): void
    {
        $recipients = $this->groupStudents($task);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TaskPublished($task));
        }
    }

    /** Notify the group's students that a task changed. */
    public function taskUpdated(Task $task, bool $deadlineChanged = false): void
    {
        $recipients = $this->groupStudents($task);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TaskUpdated($task, $deadlineChanged));
        }
    }

    /**
     * Remind the group's students about an approaching deadline, skipping
     * anyone who already completed the task.
     *
     * @return int number of students notified
     */
    public function deadlineApproaching(Task $task, int $hoursRemaining): int
    {
        $completedStudentIds = DB::table('task_completions')
            ->where('task_id', $task->id)
            ->pluck('student_id');

        $recipients = $this->groupStudents($task)
            ->reject(fn (User $student) => $completedStudentIds->contains($student->id))
            ->values();

        if ($recipients->isEmpty()) {
            return 0;
        }

        Notification::send($recipients, new DeadlineApproaching($task, $hoursRemaining));

        return $recipients->count();
    }

    /**
     * The students of the task's group — and only them.
     *
     * @return Collection<int, User>
     */
    private function groupStudents(Task $task): Collection
    {
        return User::query()
            ->where('batch_id', $task->batch_id)
            ->where('role', Role::Student->value)
            ->where('id', '!=', $task->created_by)
            ->get();
    }
}
