<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\DeadlineApproaching;
use App\Services\TaskNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sends "deadline approaching" notifications for active tasks falling due
 * inside the reminder window.
 *
 * Intended to run hourly from the scheduler. It is safe to run repeatedly:
 * a task/student pair is only notified once, enforced by looking for an
 * existing unread-or-read `deadline_approaching` row for the same task rather
 * than by keeping state on the task itself.
 */
class NotifyApproachingDeadlines extends Command
{
    protected $signature = 'schai:notify-deadlines
                            {--hours= : Override the reminder window in hours}';

    protected $description = 'Notify students about tasks due inside the reminder window';

    public function handle(TaskNotifier $notifier): int
    {
        $hours = (int) ($this->option('hours') ?: TaskNotifier::REMINDER_WINDOW_HOURS);

        if ($hours < 1) {
            $this->error('The reminder window must be at least one hour.');

            return self::FAILURE;
        }

        $now = CarbonImmutable::now(config('app.timezone'));
        $windowEnd = $now->addHours($hours);

        $tasks = Task::query()
            ->where('is_active', true)
            ->whereBetween('deadline', [$now, $windowEnd])
            ->orderBy('deadline')
            ->get();

        $notified = 0;

        foreach ($tasks as $task) {
            if ($this->alreadyReminded($task)) {
                $this->line("Skipping \"{$task->title}\" — already reminded.");

                continue;
            }

            $hoursRemaining = max(1, (int) ceil($now->diffInMinutes($task->deadline) / 60));
            $count = $notifier->deadlineApproaching($task, $hoursRemaining);
            $notified += $count;

            $this->line("Reminded {$count} student(s) about \"{$task->title}\".");
        }

        $this->info("Done. {$tasks->count()} task(s) in window, {$notified} notification(s) sent.");

        return self::SUCCESS;
    }

    /**
     * True when this task has already produced a deadline reminder, so an
     * hourly schedule does not re-notify the same students every run.
     *
     * Matched on the notification payload's own `task_id` via a JSON path
     * rather than a substring search, so the check cannot be broken by a
     * change to the order of keys in `DeadlineApproaching::toArray()`.
     */
    private function alreadyReminded(Task $task): bool
    {
        return DB::table('notifications')
            ->where('type', DeadlineApproaching::class)
            ->where('data->task_id', $task->id)
            ->exists();
    }
}
