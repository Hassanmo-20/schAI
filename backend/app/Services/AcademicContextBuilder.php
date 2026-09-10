<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds the academic context handed to the assistant.
 *
 * SECURITY: the context is derived exclusively from the authenticated user —
 * nothing here is influenced by client input. Scoping mirrors TaskPolicy:
 *
 *  - Students   : active tasks in their own batch + their own completion state.
 *  - Reps       : all tasks in their own batch + aggregate completion counts
 *                 (exactly the statistics they can already read via the API).
 *
 * No other user's identity, email or individual progress is ever included,
 * for either role.
 */
class AcademicContextBuilder
{
    /** Keep the prompt small and predictable (cost + latency control). */
    private const MAX_TASKS = 25;

    private const MAX_DESCRIPTION_CHARS = 180;

    public function build(User $user): string
    {
        if ($user->batch_id === null) {
            return "STUDENT_PROFILE:\n- name: ".$this->clean($user->name)."\n- batch: none assigned\n\nTASKS:\n- (none)";
        }

        $isRep = $user->isRepresentative();

        $query = Task::query()
            ->where('batch_id', $user->batch_id)
            ->with('batch')
            ->orderBy('deadline')
            ->limit(self::MAX_TASKS);

        if (! $isRep) {
            $query->where('is_active', true);
        } else {
            $query->withCount('completions as completed_students');
        }

        $tasks = $query->get();

        $completedTaskIds = $isRep
            ? collect()
            : DB::table('task_completions')
                ->where('student_id', $user->id)
                ->pluck('task_id')
                ->flip();

        $totalStudents = $isRep
            ? User::where('batch_id', $user->batch_id)->where('role', 'student')->count()
            : 0;

        $lines = [];
        $lines[] = $isRep ? 'REPRESENTATIVE_PROFILE:' : 'STUDENT_PROFILE:';
        $lines[] = '- name: '.$this->clean($user->name);
        $lines[] = '- role: '.$user->role->value;
        $lines[] = '- batch: '.$this->clean($user->batch?->name ?? 'unknown');
        if ($isRep) {
            $lines[] = '- students_in_batch: '.$totalStudents;
        }

        $lines[] = '';
        $lines[] = 'CURRENT_DATETIME: '.now()->toIso8601String();
        $lines[] = '';

        if ($tasks->isEmpty()) {
            $lines[] = 'TASKS: (none)';

            return implode("\n", $lines);
        }

        if (! $isRep) {
            $pending = $tasks->reject(fn (Task $t) => $completedTaskIds->has($t->id))->count();
            $overdue = $tasks->filter(
                fn (Task $t) => ! $completedTaskIds->has($t->id) && $t->deadline?->isPast()
            )->count();

            $lines[] = 'SUMMARY:';
            $lines[] = '- total_visible_tasks: '.$tasks->count();
            $lines[] = '- completed: '.($tasks->count() - $pending);
            $lines[] = '- pending: '.$pending;
            $lines[] = '- overdue_and_pending: '.$overdue;
            $lines[] = '';
        }

        $lines[] = 'TASKS:';

        foreach ($tasks as $task) {
            $parts = [
                'title: '.$this->clean($task->title),
                'type: '.$task->type->value,
                'due: '.($task->deadline?->toIso8601String() ?? 'unknown'),
            ];

            if ($isRep) {
                $completed = (int) ($task->getAttribute('completed_students') ?? 0);
                $parts[] = 'active: '.($task->is_active ? 'yes' : 'no');
                $parts[] = "completed_by: {$completed}/{$totalStudents} students";
            } else {
                $parts[] = 'status: '.($completedTaskIds->has($task->id) ? 'completed' : 'pending');
            }

            if (filled($task->description)) {
                $parts[] = 'details: '.$this->clean($task->description, self::MAX_DESCRIPTION_CHARS);
            }

            $lines[] = '- '.implode(' | ', $parts);
        }

        return implode("\n", $lines);
    }

    /**
     * Neutralise untrusted database text before it enters the prompt.
     *
     * Task titles/descriptions are authored by representatives, so they are
     * treated as data: newlines are flattened (they could otherwise fake new
     * context sections) and length is capped.
     */
    private function clean(string $value, int $limit = 120): string
    {
        $value = preg_replace('/[\p{C}]+/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return mb_strimwidth($value, 0, $limit, '…');
    }
}
