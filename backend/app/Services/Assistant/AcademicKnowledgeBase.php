<?php

namespace App\Services\Assistant;

/**
 * Tiny static knowledge base for general academic/productivity questions.
 *
 * Used whenever OpenAI is unavailable, and as the default reply for messages
 * that don't match any structured intent — deliberately small and static,
 * per the "keep it simple, no over-engineering" brief. This is generic
 * advice, never claimed to be personalized data from the user's account.
 */
class AcademicKnowledgeBase
{
    public static function answer(string $message): string
    {
        $lower = mb_strtolower($message);

        if (str_contains($lower, 'pomodoro')) {
            return 'The Pomodoro Technique: work in focused 25-minute blocks, then take a 5-minute break. '
                .'After four blocks, take a longer 15–30 minute break. It helps you stay focused and avoid burnout.';
        }

        if (preg_match('/\bstudy (plan|schedule)\b/', $lower) || str_contains($lower, 'organize')) {
            return "A simple way to organize your work:\n"
                ."1. List everything that's due and sort it by deadline.\n"
                ."2. Tackle the most urgent task first, in focused blocks (try the Pomodoro technique).\n"
                .'3. Leave a buffer day before each deadline for review instead of finishing at the last minute.';
        }

        if (preg_match('/\bstudy (for|tips?)\b/', $lower) || str_contains($lower, 'how can i study')) {
            return "A few study tips:\n"
                ."- Break the material into small topics instead of studying everything at once.\n"
                ."- Use active recall — quiz yourself instead of re-reading notes.\n"
                ."- Practice past questions if any are available.\n"
                .'- Revisit tricky topics a day before the deadline rather than cramming.';
        }

        return 'I can help you add tasks, complete tasks, check deadlines, or organize your week. '
            .'Try things like "What should I work on today?", "Add a quiz for Friday", '
            .'or "I finished the database assignment".';
    }
}
