<?php

namespace App\Enums;

/**
 * Intents the deterministic chat parser can recognize. Kept intentionally
 * small — this is a keyword classifier for a handful of demo commands, not a
 * general NLP framework (see ChatIntentParser).
 */
enum AssistantIntentType: string
{
    case General = 'general';
    case AddTask = 'add_task';
    case CompleteTask = 'complete_task';
    case QueryUrgent = 'query_urgent';
    case QueryToday = 'query_today';
    case QueryTomorrow = 'query_tomorrow';
    case QueryWeek = 'query_week';
    case QueryOverdue = 'query_overdue';
    case QueryList = 'query_list';

    /** @return array<int, self> */
    public static function queryCases(): array
    {
        return [
            self::QueryUrgent,
            self::QueryToday,
            self::QueryTomorrow,
            self::QueryWeek,
            self::QueryOverdue,
            self::QueryList,
        ];
    }
}
