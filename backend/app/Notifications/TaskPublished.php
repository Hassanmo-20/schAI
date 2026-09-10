<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * "A new task was published for your group."
 *
 * Sent to the students of the task's batch only — the fan-out in
 * {@see \App\Services\TaskNotifier} is what enforces group isolation, so a
 * notification row can only ever exist for a member of that group.
 */
class TaskPublished extends Notification
{
    use Queueable;

    public function __construct(private readonly Task $task) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, task_id: int, task_title: string,
     *               task_type: string, deadline: string, batch_id: int,
     *               title: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_published',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'task_type' => $this->task->type->value,
            'deadline' => $this->task->deadline->toIso8601String(),
            'batch_id' => $this->task->batch_id,
            'title' => 'New '.$this->task->type->value.' posted',
            'message' => "\"{$this->task->title}\" is due {$this->task->deadline->format('D, M j')}.",
        ];
    }
}
