<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * "A task you follow changed." Sent when a representative edits a task's
 * details, and — with `deadlineChanged` — called out explicitly when the
 * change is the deadline, because that is the one edit students must act on.
 */
class TaskUpdated extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly bool $deadlineChanged = false,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, task_id: int, task_title: string,
     *               task_type: string, deadline: string, batch_id: int,
     *               deadline_changed: bool, title: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_updated',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'task_type' => $this->task->type->value,
            'deadline' => $this->task->deadline->toIso8601String(),
            'batch_id' => $this->task->batch_id,
            'deadline_changed' => $this->deadlineChanged,
            'title' => $this->deadlineChanged ? 'Deadline changed' : 'Task updated',
            'message' => $this->deadlineChanged
                ? "\"{$this->task->title}\" is now due {$this->task->deadline->format('D, M j')}."
                : "\"{$this->task->title}\" was updated by your representative.",
        ];
    }
}
