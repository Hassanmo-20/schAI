<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Batch scoping happens in the controller; this only confirms the user
     * is attached to a batch at all.
     */
    public function viewAny(User $user): bool
    {
        return $user->batch_id !== null;
    }

    public function view(User $user, Task $task): bool
    {
        if ($task->batch_id !== $user->batch_id) {
            return false;
        }

        // Inactive tasks are hidden from students but remain visible to their rep.
        if (! $task->is_active && ! $user->isRepresentative()) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->isRepresentative() && $user->batch_id !== null;
    }

    public function update(User $user, Task $task): bool
    {
        return $user->isRepresentative() && $task->batch_id === $user->batch_id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->isRepresentative() && $task->batch_id === $user->batch_id;
    }

    public function complete(User $user, Task $task): bool
    {
        return $user->isStudent()
            && $task->batch_id === $user->batch_id
            && $task->is_active;
    }

    public function viewStatistics(User $user, Task $task): bool
    {
        return $user->isRepresentative() && $task->batch_id === $user->batch_id;
    }

    public function downloadAttachment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
