<?php

namespace App\Services\Assistant;

use App\Enums\AssistantIntentType;
use App\Enums\TaskType;
use Carbon\CarbonInterface;

/**
 * Result of classifying one chat message. Immutable value object passed from
 * ChatIntentParser to AssistantChatService.
 *
 * `$due` is typed as CarbonInterface because DateNormalizer returns
 * CarbonImmutable, which is a sibling of Carbon rather than a subclass.
 */
final class ParsedIntent
{
    public function __construct(
        public readonly AssistantIntentType $type,
        public readonly ?string $title = null,
        public readonly ?TaskType $taskType = null,
        public readonly ?CarbonInterface $due = null,
        public readonly ?string $searchTerm = null,
    ) {}
}
