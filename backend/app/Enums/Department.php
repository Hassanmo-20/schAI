<?php

namespace App\Enums;

/**
 * The departments SchAI serves.
 *
 * Half of a group's identity — the other half is {@see BatchYear}. See
 * {@see \App\Models\Batch} for how the pair becomes one addressable group.
 */
enum Department: string
{
    case CCE = 'CCE';
    case CSE = 'CSE';

    /** Full name, for tooltips and the group description line. */
    public function label(): string
    {
        return match ($this) {
            self::CCE => 'Computer & Communication Engineering',
            self::CSE => 'Computer Science & Engineering',
        };
    }

    /**
     * Every department, in the order the registration form should show them.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
