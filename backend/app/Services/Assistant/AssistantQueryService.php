<?php

namespace App\Services\Assistant;

use App\Enums\AssistantIntentType;
use App\Models\Task;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Answers deadline questions ("what's due today?", "most urgent task", …)
 * directly from the database. Every answer here is generated from a real
 * Eloquent query against the caller's own batch — nothing is guessed or
 * invented, and nothing from another batch or another student is ever read.
 */
class AssistantQueryService
{
    private const LIST_LIMIT = 8;

    public function answer(AssistantIntentType $type, User $user): string
    {
        if ($user->batch_id === null) {
            return "You're not assigned to a batch yet, so I can't see any tasks.";
        }

        $isRep = $user->isRepresentative();
        $tasks = Task::forBatch($user->batch_id)->active()->orderBy('deadline')->get();
        $completedIds = $isRep
            ? collect()
            : DB::table('task_completions')->where('student_id', $user->id)->pluck('task_id')->flip();

        return match ($type) {
            AssistantIntentType::QueryUrgent => $this->urgent($tasks, $completedIds, $isRep),
            AssistantIntentType::QueryOverdue => $this->filtered(
                $tasks, $completedIds, $isRep,
                fn (Task $t) => $t->deadline->isPast() && ($isRep || ! $completedIds->has($t->id)),
                'You have no overdue tasks. Nice work staying on top of things!'
            ),
            AssistantIntentType::QueryToday => $this->filtered(
                $tasks, $completedIds, $isRep,
                fn (Task $t) => $t->deadline->isToday(),
                'You have nothing due today.'
            ),
            AssistantIntentType::QueryTomorrow => $this->filtered(
                $tasks, $completedIds, $isRep,
                fn (Task $t) => $t->deadline->isTomorrow(),
                'You have nothing due tomorrow.'
            ),
            AssistantIntentType::QueryWeek => $this->filtered(
                $tasks, $completedIds, $isRep,
                fn (Task $t) => $t->deadline->between(now(), now()->endOfWeek()),
                'You have nothing due for the rest of this week.'
            ),
            AssistantIntentType::QueryList => $this->filtered(
                $tasks, $completedIds, $isRep,
                fn (Task $t) => $isRep || ! $completedIds->has($t->id),
                'You have no active tasks right now.',
                self::LIST_LIMIT
            ),
            default => 'I can help you check deadlines, add tasks, or mark tasks as completed.',
        };
    }

    private function urgent(Collection $tasks, Collection $completedIds, bool $isRep): string
    {
        $candidate = $tasks->first(fn (Task $t) => $isRep || ! $completedIds->has($t->id));

        if (! $candidate) {
            return 'You have no pending tasks — great job! 🎉';
        }

        $status = $this->statusFor($candidate, $completedIds, $isRep);

        return "Your most urgent task is **{$candidate->title}** ({$candidate->type->value}), "
            .$this->humanDue($candidate).". Status: {$status}.";
    }

    private function filtered(
        Collection $tasks,
        Collection $completedIds,
        bool $isRep,
        Closure $filter,
        string $emptyMessage,
        ?int $limit = null
    ): string {
        $matched = $tasks->filter($filter)->values();
        if ($limit !== null) {
            $matched = $matched->take($limit);
        }

        if ($matched->isEmpty()) {
            return $emptyMessage;
        }

        return $matched
            ->map(fn (Task $t) => '- **'.$t->title.'** ('.$t->type->value.') — '
                .$this->humanDue($t).' — '.$this->statusFor($t, $completedIds, $isRep))
            ->implode("\n");
    }

    private function statusFor(Task $task, Collection $completedIds, bool $isRep): string
    {
        if ($isRep) {
            return $task->is_active ? 'Active' : 'Inactive';
        }

        if ($completedIds->has($task->id)) {
            return 'Completed';
        }

        return $task->deadline->isPast() ? 'Overdue' : 'Pending';
    }

    private function humanDue(Task $task): string
    {
        return $task->deadline->isPast()
            ? 'was due '.$task->deadline->diffForHumans()
            : 'due '.$task->deadline->diffForHumans();
    }
}
