<?php

namespace App\Services;

use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Single place that inserts a task row.
 *
 * Used by both the representative-only REST endpoint (TaskController::store)
 * and the assistant's "add task" chat command, so the actual creation logic
 * (and its ownership rules) is never duplicated between the two entry points.
 *
 * The deadline is typed as CarbonInterface so a CarbonImmutable from
 * DateNormalizer is accepted alongside the mutable Carbon the REST path parses.
 */
class TaskCreationService
{
    public function create(User $user, string $title, TaskType $type, CarbonInterface $deadline, ?string $description = null): Task
    {
        return Task::create([
            'title' => $title,
            'description' => $description,
            'type' => $type->value,
            'deadline' => $deadline,
            // Ownership is always derived from the acting user — never from
            // client input, whether the request came from the REST form or chat.
            'batch_id' => $user->batch_id,
            'created_by' => $user->id,
            'is_active' => true,
        ]);
    }
}
