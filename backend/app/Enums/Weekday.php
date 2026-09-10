<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * SchAI's ONE canonical weekday mapping: ISO-8601, Monday = 1 … Sunday = 7.
 *
 * This deliberately matches Carbon's `dayOfWeekIso` (and PHP's `N` format
 * character) — never `dayOfWeek`, which is Sunday = 0 … Saturday = 6. Mixing
 * those two conventions was the source of the off-by-one weekday bugs in the
 * assistant, so every weekday decision in the app goes through this enum and
 * no other code hand-rolls day numbers.
 *
 * Carbon's own day constants use the Sunday = 0 convention, so the single
 * translation between the two lives in `carbonConstant()` and nowhere else.
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    /**
     * Build from an ISO day number (1–7), the value Carbon's `dayOfWeekIso`
     * and MySQL/SQLite `strftime('%u')` both return.
     */
    public static function fromIso(int $isoDayNumber): self
    {
        return self::from($isoDayNumber);
    }

    /**
     * The matching Carbon day constant (Sunday = 0 … Saturday = 6), for use
     * with `Carbon::next()` / `Carbon::startOfWeek()`.
     *
     * Written as an explicit map rather than arithmetic so the two conventions
     * can never silently drift.
     */
    public function carbonConstant(): int
    {
        return match ($this) {
            self::Monday => CarbonInterface::MONDAY,
            self::Tuesday => CarbonInterface::TUESDAY,
            self::Wednesday => CarbonInterface::WEDNESDAY,
            self::Thursday => CarbonInterface::THURSDAY,
            self::Friday => CarbonInterface::FRIDAY,
            self::Saturday => CarbonInterface::SATURDAY,
            self::Sunday => CarbonInterface::SUNDAY,
        };
    }

    /** English weekday name, as PHP's `l` format character produces it. */
    public function label(): string
    {
        return $this->name;
    }

    /**
     * Spellings recognised in chat input, English + Egyptian Arabic.
     *
     * Arabic entries are written in the folded form produced by
     * `DateNormalizer::fold()` (diacritics stripped, أ/إ/آ → ا, ة → ه,
     * ى → ي), so each spelling only needs listing once — "الجمعة",
     * "الجُمعة" and "الجمعه" all reduce to "الجمعه".
     *
     * Bare Arabic forms are listed only where they cannot collide with a
     * common non-date word: "الأحد" needs its article because bare "أحد"
     * also means "someone".
     *
     * @return array<int, string>
     */
    public function aliases(): array
    {
        return match ($this) {
            self::Monday => ['monday', 'الاثنين', 'الاتنين', 'اثنين', 'اتنين'],
            self::Tuesday => ['tuesday', 'الثلاثاء', 'الثلاثا', 'الثلاث', 'التلات', 'ثلاثاء'],
            self::Wednesday => ['wednesday', 'الاربعاء', 'الاربعا', 'الاربع', 'اربعاء'],
            self::Thursday => ['thursday', 'الخميس', 'خميس'],
            self::Friday => ['friday', 'الجمعه', 'جمعه'],
            self::Saturday => ['saturday', 'السبت', 'سبت'],
            self::Sunday => ['sunday', 'الاحد'],
        };
    }

    /**
     * Every alias across every weekday, longest first so a longer spelling is
     * never shadowed by a shorter one that is its prefix.
     *
     * @return array<string, self> alias => weekday
     */
    public static function aliasMap(): array
    {
        $map = [];

        foreach (self::cases() as $weekday) {
            foreach ($weekday->aliases() as $alias) {
                $map[$alias] = $weekday;
            }
        }

        uksort($map, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return $map;
    }
}
