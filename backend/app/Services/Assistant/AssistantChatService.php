<?php

namespace App\Services\Assistant;

use App\Enums\AssistantIntentType;
use App\Enums\TaskType;
use App\Exceptions\AssistantUnavailableException;
use App\Exceptions\TaskAlreadyCompletedException;
use App\Models\Task;
use App\Models\User;
use App\Services\AssistantPromptBuilder;
use App\Services\OpenAIService;
use App\Services\TaskCompletionService;
use App\Services\TaskCreationService;
use App\Services\TaskNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Orchestrates one assistant turn.
 *
 * Add/complete/deadline-query commands are always handled deterministically
 * by ChatIntentParser + real Eloquent queries — they never depend on OpenAI,
 * so they behave identically whether or not a provider key is configured
 * (required by the "OpenAI is optional" product rule). OpenAI is used only
 * to enrich open-ended General questions, with a local fallback when it is
 * unavailable so the user never sees a provider/config error.
 */
class AssistantChatService
{
    public function __construct(
        private readonly ChatIntentParser $parser,
        private readonly AssistantQueryService $queries,
        private readonly TaskCreationService $taskCreator,
        private readonly TaskCompletionService $taskCompleter,
        private readonly AssistantPromptBuilder $prompts,
        private readonly OpenAIService $openAI,
        private readonly TaskNotifier $notifier,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{message: string, taskChanged: bool}
     */
    public function handle(User $user, string $message, array $history = []): array
    {
        $intent = $this->parser->parse($message);

        if ($intent->type === AssistantIntentType::AddTask) {
            return $this->handleAdd($user, $intent);
        }

        if ($intent->type === AssistantIntentType::CompleteTask) {
            return $this->handleComplete($user, $intent);
        }

        if (in_array($intent->type, AssistantIntentType::queryCases(), true)) {
            return ['message' => $this->queries->answer($intent->type, $user), 'taskChanged' => false];
        }

        return ['message' => $this->handleGeneral($user, $message, $history), 'taskChanged' => false];
    }

    /**
     * @return array{message: string, taskChanged: bool}
     */
    private function handleAdd(User $user, ParsedIntent $intent): array
    {
        // Batch-wide task creation is normally representative-only
        // (TaskPolicy::create). The chat "add task" command is intentionally
        // different: it represents the user's own reminder for their batch,
        // available to any authenticated batch member — see docs/assistant.md.
        if ($user->batch_id === null) {
            return ['message' => "I can't add a task because you're not assigned to a batch yet.", 'taskChanged' => false];
        }

        if ($intent->due === null) {
            $title = $intent->title ?? 'that task';

            return [
                'message' => "Sure — when is \"{$title}\" due? You can say things like \"today\", \"tomorrow\", or \"Thursday\".",
                'taskChanged' => false,
            ];
        }

        $task = $this->taskCreator->create(
            $user,
            $intent->title ?? 'New Task',
            $intent->taskType ?? TaskType::Other,
            $intent->due,
        );

        // Only a representative's chat-added task is an announcement to the
        // group. A student using chat is writing themselves a reminder that
        // merely happens to be batch-scoped, so it stays silent.
        if ($user->isRepresentative()) {
            $this->notifier->taskPublished($task);
        }

        $when = $task->deadline->isToday()
            ? 'today'
            : ($task->deadline->isTomorrow() ? 'tomorrow' : $task->deadline->format('l, M j'));

        return [
            'message' => "Done! I added \"{$task->title}\" ({$task->type->value}) due {$when}.",
            'taskChanged' => true,
        ];
    }

    /**
     * @return array{message: string, taskChanged: bool}
     */
    private function handleComplete(User $user, ParsedIntent $intent): array
    {
        // Mirrors TaskPolicy::complete (student-only). Checked up front so we
        // never even run the search query for a role that could never complete.
        if (! $user->isStudent()) {
            return ['message' => 'Only students can mark tasks as completed.', 'taskChanged' => false];
        }

        $term = $intent->searchTerm;
        if (blank($term)) {
            return ['message' => 'Which task would you like to mark as completed?', 'taskChanged' => false];
        }

        // Scoped to the caller's own batch — a cross-batch task can never
        // appear here, regardless of what the user types.
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $term);
        $matches = Task::forBatch($user->batch_id)->active()
            ->where('title', 'like', "%{$escaped}%")
            ->orderBy('deadline')
            ->get();

        if ($matches->isEmpty()) {
            return ['message' => "I couldn't find a task matching \"{$term}\".", 'taskChanged' => false];
        }

        if ($matches->count() > 1) {
            $titles = $matches->pluck('title')->map(fn ($t) => "\"{$t}\"")->implode(', ');

            return [
                'message' => "I found more than one matching task: {$titles}. Which one do you mean?",
                'taskChanged' => false,
            ];
        }

        $task = $matches->first();

        try {
            Gate::authorize('complete', $task);
        } catch (AuthorizationException) {
            return ['message' => "You can't complete that task.", 'taskChanged' => false];
        }

        try {
            $this->taskCompleter->complete($user, $task);
        } catch (TaskAlreadyCompletedException) {
            return ['message' => "\"{$task->title}\" is already marked as completed.", 'taskChanged' => false];
        }

        return ['message' => "Done! \"{$task->title}\" is marked as completed.", 'taskChanged' => true];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function handleGeneral(User $user, string $message, array $history): string
    {
        if ($this->openAI->isConfigured()) {
            try {
                return $this->openAI->chat($this->prompts->build($user, $message, $history));
            } catch (AssistantUnavailableException) {
                // Provider is down/misconfigured — degrade to the local
                // knowledge base instead of surfacing a technical error.
            }
        }

        return $this->localGeneralAnswer($user, $message);
    }

    private function localGeneralAnswer(User $user, string $message): string
    {
        $lower = mb_strtolower($message);
        $base = AcademicKnowledgeBase::answer($message);

        // Light personalization: a study-plan request scoped to "today"/"week"
        // is grounded in the user's real tasks before the generic technique tip.
        if (str_contains($lower, 'study plan')) {
            if (str_contains($lower, 'today')) {
                return "Here's what's on your plate today:\n\n"
                    .$this->queries->answer(AssistantIntentType::QueryToday, $user)."\n\n{$base}";
            }
            if (str_contains($lower, 'week')) {
                return "Here's what's on your plate this week:\n\n"
                    .$this->queries->answer(AssistantIntentType::QueryWeek, $user)."\n\n{$base}";
            }
        }

        return $base;
    }
}
