<?php

namespace App\Enums;

/**
 * The graduation batches SchAI serves.
 *
 * Half of a group's identity — the other half is {@see Department}. A group
 * is always the *pair* (batch year + department), never the year alone: the
 * 2027 CCE students and the 2027 CSE students are separate groups that must
 * never see each other's tasks.
 *
 * "2028++" is a real, distinct batch (repeat/extended-study students who
 * graduate alongside 2028 but follow their own schedule), not a typo for 2028.
 */
enum BatchYear: string
{
    case Y2027 = '2027';
    case Y2028 = '2028';
    case Y2028Plus = '2028++';
    case Y2029 = '2029';
    case Y2030 = '2030';

    /** Human label for dropdowns; identical to the stored value. */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Every batch year, in the order the registration form should show them.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
