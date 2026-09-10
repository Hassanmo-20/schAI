<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a student tries to complete a task they have already completed.
 * `TaskCompletion`'s unique(task_id, student_id) constraint is the real
 * authority here — this exception just gives callers a typed way to react.
 */
class TaskAlreadyCompletedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Task already marked as complete');
    }
}
