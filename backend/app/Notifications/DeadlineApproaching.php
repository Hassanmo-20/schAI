<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * "Something you haven't finished is due soon."
 *
 * Dispatched by `schai:notify-deadlines` for tasks inside the reminder window,
 * and only to students who have NOT completed the task — a student who is
 * already done is never nagged.
 */
class DeadlineApproaching extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly int $hoursRemaining,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, task_id: int, task_title: string,
     *               task_type: string, deadline: string, batch_id: int,
     *               hours_remaining: int, title: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deadline_approaching',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'task_type' => $this->task->type->value,
            'deadline' => $this->task->deadline->toIso8601String(),
            'batch_id' => $this->task->batch_id,
            'hours_remaining' => $this->hoursRemaining,
            'title' => 'Deadline approaching',
            'message' => "\"{$this->task->title}\" is due in {$this->hoursRemaining} hours.",
        ];
    }
}
