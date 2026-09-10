<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the assistant cannot produce an answer.
 *
 * Carries a *safe* user-facing message plus the HTTP status the API should
 * return. Provider internals (keys, URLs, raw error bodies) are deliberately
 * never placed in the message — they are logged separately instead.
 */
class AssistantUnavailableException extends RuntimeException
{
    public function __construct(
        string $userMessage,
        private readonly int $status = 503,
    ) {
        parent::__construct($userMessage);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function notConfigured(): self
    {
        return new self(
            'The academic assistant is not configured on this server yet.',
            503
        );
    }

    public static function unreachable(): self
    {
        return new self(
            'The academic assistant is temporarily unavailable. Please try again in a moment.',
            503
        );
    }

    public static function badResponse(): self
    {
        return new self(
            'The academic assistant returned an unreadable response. Please try again.',
            502
        );
    }
}
