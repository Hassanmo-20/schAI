<?php

namespace App\Services\Assistant;

use App\Enums\AssistantIntentType;
use App\Enums\TaskType;
use Carbon\CarbonInterface;

/**
 * Deterministic, regex-based intent classifier for the academic assistant.
 *
 * This is intentionally NOT an NLP/ML system — a small set of keyword and
 * phrase checks reliably covers the demo commands the assistant must support
 * (add task / complete task / deadline queries), and — unlike an LLM call —
 * it is free, instant, and behaves identically whether or not OpenAI is
 * configured. See AssistantChatService for how OpenAI is used instead only
 * to enrich open-ended/general questions.
 *
 * DATES: this class does no date arithmetic of its own. Every "when" is
 * resolved by {@see DateNormalizer}, which is the single source of truth for
 * weekday/date normalisation (application timezone, ISO weeks, English and
 * Arabic phrasing). An earlier copy of that logic lived here and used Carbon's
 * Sunday = 0 convention, which is what made "Friday" resolve to the wrong day.
 *
 * Classification order matters and is deliberately conservative to avoid
 * misreading free-form questions as commands:
 *   1. General-question markers ("how can I…", "pomodoro", "study plan…")
 *      are checked first so advice questions never get mistaken for a task
 *      command just because they mention a word like "assignments".
 *   2. Complete-task verbs only count if paired with a first-person marker
 *      ("I", "my") or an imperative "mark", so third-person questions like
 *      "who finished this?" are not misread as a self-completion command.
 *   3. Add-task trigger phrases.
 *   4. Deadline queries (single, low-collision keywords).
 *   5. Everything else falls through to General.
 */
class ChatIntentParser
{
    /** @var array<string, TaskType> */
    private const TYPE_KEYWORDS = [
        'assignment' => TaskType::Assignment,
        'homework' => TaskType::Assignment,
        'quiz' => TaskType::Quiz,
        'midterm' => TaskType::Midterm,
        'exam' => TaskType::Exam,
        'test' => TaskType::Exam,
        'project' => TaskType::Project,
    ];

    /** Specific phrases only — deliberately not a loose "list|show + task" regex,
     *  which would also catch unrelated sentences that merely mention a task. */
    private const QUERY_LIST_PHRASES = [
        'show my tasks', 'show tasks', 'my tasks', 'show my assignments',
        'show my upcoming', 'upcoming tasks', 'upcoming deadlines',
        'what tasks do i have', 'what do i have',
    ];

    public function __construct(private readonly DateNormalizer $dates) {}

    public function parse(string $message): ParsedIntent
    {
        $trimmed = trim($message);
        $lower = mb_strtolower($trimmed);

        if ($this->isGeneralQuestion($lower)) {
            return new ParsedIntent(AssistantIntentType::General);
        }

        if ($this->isCompleteCommand($lower)) {
            return new ParsedIntent(
                AssistantIntentType::CompleteTask,
                searchTerm: $this->extractCompletionTerm($trimmed) ?: null,
            );
        }

        if ($this->isAddCommand($lower)) {
            [$title, $taskType] = $this->extractTitleAndType($trimmed, $lower);

            return new ParsedIntent(
                AssistantIntentType::AddTask,
                title: $title,
                taskType: $taskType,
                due: $this->extractDueDate($trimmed),
            );
        }

        if ($queryType = $this->matchQuery($lower)) {
            return new ParsedIntent($queryType);
        }

        return new ParsedIntent(AssistantIntentType::General);
    }

    private function isGeneralQuestion(string $lower): bool
    {
        return (bool) preg_match(
            '/\bhow (can|do|should) i\b|\bpomodoro\b|\bstudy (plan|schedule|tips?|for)\b|\bgive me a\b|\borganize\b/',
            $lower
        );
    }

    private function isCompleteCommand(string $lower): bool
    {
        if (! preg_match('/\b(finished|completed|complete|done|mark)\b/', $lower)) {
            return false;
        }

        // Require a first-person marker or an imperative "mark" so third-person
        // questions ("who finished this?") are treated as General, not a command.
        return (bool) preg_match('/\b(i|my|i\'ve|i\'m)\b/', $lower) || str_starts_with($lower, 'mark');
    }

    private function isAddCommand(string $lower): bool
    {
        // Anchored to the start: real add-commands are opening statements
        // ("I have a...", "Add a..."), whereas "i have" can also appear mid
        // -question ("What do I have this week?"), which must stay a query.
        if (preg_match('/^(?:add|create|i have|i\'ve got|schedule|new task)\b/', $lower)) {
            return true;
        }

        // Arabic openers: "ضيف/أضف …" (add), "عندي …" (I have). Folded first so
        // spelling variants (أضف / اضف) all reach the same test.
        return (bool) preg_match(
            '/^(?:ضيف|اضف|اضيف|سجل|عندي|عندنا|في عندي)(?![\p{L}])/u',
            $this->dates->fold($lower)
        );
    }

    private function matchQuery(string $lower): ?AssistantIntentType
    {
        return match (true) {
            (bool) preg_match('/\burgent\b|\bnext\b/', $lower) => AssistantIntentType::QueryUrgent,
            (bool) preg_match('/\boverdue\b/', $lower) => AssistantIntentType::QueryOverdue,
            (bool) preg_match('/\btoday\b/', $lower) => AssistantIntentType::QueryToday,
            (bool) preg_match('/\btomorrow\b/', $lower) => AssistantIntentType::QueryTomorrow,
            (bool) preg_match('/\bweek\b/', $lower) => AssistantIntentType::QueryWeek,
            $this->containsAny($lower, self::QUERY_LIST_PHRASES) => AssistantIntentType::QueryList,
            default => null,
        };
    }

    /** @param array<int, string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The concrete due datetime (23:59 local) for the date phrase in the
     * message, or null when it contains none — the caller then asks the user
     * for a date rather than guessing one.
     */
    private function extractDueDate(string $original): ?CarbonInterface
    {
        return $this->dates->resolveDeadline($original);
    }

    /**
     * @return array{0: string, 1: ?TaskType}
     */
    private function extractTitleAndType(string $original, string $lower): array
    {
        $taskType = null;
        foreach (self::TYPE_KEYWORDS as $keyword => $enumCase) {
            if (preg_match('/\b'.$keyword.'\b/', $lower)) {
                $taskType = $enumCase;
                break;
            }
        }

        // Strip a leading trigger phrase ("I have", "Add a", "Schedule a", …).
        $stripped = (string) preg_replace(
            '/^(?:i\'ve got|i have|i need to (?:do|submit|finish)|add(?:ing)?|create(?: an| a)?|schedule(?:d)?(?: an| a)?|new task)\b\s*/iu',
            '',
            $original
        );
        $stripped = (string) preg_replace(
            '/^(?:ضيف|اضف|أضف|اضيف|أضيف|سجل|عندي|عندنا|في\s+عندي)\s*/u',
            '',
            $stripped
        );
        $stripped = (string) preg_replace('/^(?:a|an|the|my)\s+/iu', '', trim($stripped));

        // Strip the date phrase using the same vocabulary DateNormalizer
        // matched on, so title and deadline can never disagree about which
        // words were the date.
        $stripped = $this->dates->stripDatePhrase($stripped);
        $stripped = trim($stripped, " .!\t\n\r\0\x0B،؟");

        $title = $stripped !== ''
            ? $this->titleCase($stripped)
            : ($taskType !== null ? ucfirst($taskType->value) : 'New Task');

        return [mb_substr($title, 0, 180), $taskType];
    }

    private function extractCompletionTerm(string $original): string
    {
        $term = (string) preg_replace(
            '/^(?:i\'ve\s+|i\'m\s+|i\s+)?(?:finished|completed|complete|done(?:\s+with)?)\s+(?:with\s+)?/i',
            '',
            $original
        );
        $term = (string) preg_replace('/^mark\s+(?:my\s+)?/i', '', $term);
        $term = (string) preg_replace('/\s+as\s+(?:completed|complete|done)\.?$/i', '', $term);
        $term = (string) preg_replace('/^(?:the|my|a|an)\s+/i', '', trim($term));

        return trim($term, " .!?\t\n\r\"'");
    }

    private function titleCase(string $value): string
    {
        return mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8');
    }
}
