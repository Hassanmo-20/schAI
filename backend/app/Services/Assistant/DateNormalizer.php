<?php

namespace App\Services\Assistant;

use App\Enums\Weekday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns a natural-language date phrase into a concrete calendar date.
 *
 * This class is the single source of truth for every date the assistant
 * produces. The LLM is never asked to do date arithmetic — at most it would
 * hand over a structured phrase ("weekday: friday", "next: true") and this
 * class resolves it, which is why `resolveWeekday()` is public.
 *
 * ---------------------------------------------------------------------------
 * TIMEZONE POLICY
 * ---------------------------------------------------------------------------
 * Everything here is computed in the *application* timezone
 * (`config('app.timezone')`, `Africa/Cairo` for SchAI) — never in the server
 * machine's timezone and never in UTC. "Today" means today in Egypt, so a
 * message sent at 01:00 Cairo resolves against the Egyptian calendar day, not
 * the UTC one that is still three hours behind.
 *
 * Deadlines land at 23:59 of that local calendar day, so a task the user asked
 * for on Friday is stored on Friday and reads back as Friday.
 *
 * ---------------------------------------------------------------------------
 * WEEK CONVENTION
 * ---------------------------------------------------------------------------
 * Weeks run **Monday → Sunday** (ISO-8601). This is passed explicitly to every
 * `startOfWeek()`/`endOfWeek()` call, because Carbon's default first-day-of-week
 * follows the active locale (Sunday for `en`) and would otherwise change the
 * answer depending on `APP_LOCALE`.
 *
 * ---------------------------------------------------------------------------
 * SEMANTICS (asserted in tests/Feature/AssistantDateParsingTest.php)
 * ---------------------------------------------------------------------------
 * | Phrase                          | Resolves to                              |
 * |---------------------------------|------------------------------------------|
 * | "today", "النهارده"             | today                                    |
 * | "tomorrow", "بكرة"              | today + 1 calendar day                   |
 * | "day after tomorrow", "بعد بكرة"| today + 2 calendar days                  |
 * | "Friday", "this Friday", "الجمعة"| the UPCOMING Friday; today if today IS   |
 * |                                 | Friday                                   |
 * | "next Friday", "الجمعة الجاية"  | the Friday of the FOLLOWING Mon–Sun week;|
 * |                                 | always 7–13 days out, never today        |
 * | "next week", "الأسبوع الجاي"    | Monday of the following Mon–Sun week     |
 * | "this week", "الأسبوع ده"       | Sunday of the current Mon–Sun week       |
 * | "in 3 days", "بعد 3 ايام"       | today + 3 calendar days                  |
 * | "September 18", "18 September"  | that day this year, or next year if it   |
 * |                                 | has already passed                       |
 * | "18/9", "18/9/2026"             | day/month (Egyptian ordering)            |
 * | "2026-09-18"                    | exactly that date                        |
 */
class DateNormalizer
{
    /** Tasks added through chat are due at the end of the local calendar day. */
    public const DEADLINE_HOUR = 23;

    public const DEADLINE_MINUTE = 59;

    /** Zero-width boundaries that work for Arabic and Latin script alike. */
    private const LB = '(?<![\p{L}\p{N}])';

    private const RB = '(?![\p{L}\p{N}])';

    /** Harakat, tatweel and other combining marks that may sit between letters. */
    private const DIACRITICS = '[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{0640}]';

    /** Arabic markers meaning "the next one" — they follow the noun: "الجمعة الجاية". */
    private const AR_NEXT_MARKERS = ['الجايه', 'الجاي', 'القادمه', 'القادم', 'المقبله', 'المقبل'];

    /** Arabic demonstratives meaning "this one": "الجمعة دي". */
    private const AR_THIS_MARKERS = ['دي', 'ده', 'دا'];

    private const AR_TODAY = ['النهارده', 'اليوم'];

    private const AR_TOMORROW = ['بكره', 'غدا', 'الغد'];

    private const AR_DAY_AFTER_TOMORROW = ['بعد بكره', 'بعد غد', 'بعد الغد'];

    private const AR_WEEK = ['الاسبوع', 'اسبوع'];

    /** @var array<string, int> */
    private const MONTHS = [
        'january' => 1, 'jan' => 1,
        'february' => 2, 'feb' => 2,
        'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'may' => 5,
        'june' => 6, 'jun' => 6,
        'july' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8,
        'september' => 9, 'sept' => 9, 'sep' => 9,
        'october' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11,
        'december' => 12, 'dec' => 12,
    ];

    /**
     * The application timezone every calculation is anchored to.
     *
     * Read from config on each call rather than cached in the constructor so
     * tests can rebind it, and so the value is never inherited from the
     * server's `date.timezone`.
     */
    public function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /** Midnight of the current calendar day, in the application timezone. */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }

    /**
     * Resolve the date phrase inside a free-form message.
     *
     * Returns midnight of the resolved calendar day, or null when the message
     * contains no date phrase at all (the caller then asks the user for one).
     */
    public function resolveDate(string $message): ?CarbonImmutable
    {
        $text = $this->fold($message);

        return $this->matchExplicitDate($text)
            ?? $this->matchRelativeOffset($text)
            ?? $this->matchNamedDay($text)
            ?? $this->matchWeekdayPhrase($text)
            ?? $this->matchWeekPhrase($text);
    }

    /**
     * The same resolution, returned as a storable deadline: 23:59 on the
     * resolved calendar day, in the application timezone.
     */
    public function resolveDeadline(string $message): ?CarbonImmutable
    {
        return $this->resolveDate($message)?->setTime(self::DEADLINE_HOUR, self::DEADLINE_MINUTE, 0);
    }

    /** Turn any resolved calendar date into the end-of-day deadline instant. */
    public function toDeadline(CarbonImmutable $date): CarbonImmutable
    {
        return $date->setTime(self::DEADLINE_HOUR, self::DEADLINE_MINUTE, 0);
    }

    /**
     * Deterministically resolve a weekday. This is the method that must be
     * used if an LLM ever supplies a structured intent such as
     * `{"weekday": "friday", "next": true}` — the model names the day, this
     * code decides the date.
     *
     * @param  bool  $nextWeek  false → the upcoming occurrence (today counts);
     *                          true  → that weekday in the following Mon–Sun week
     */
    public function resolveWeekday(Weekday $weekday, bool $nextWeek = false): CarbonImmutable
    {
        $today = $this->today();

        if (! $nextWeek) {
            // "Friday" = the upcoming Friday. If today is already Friday, the
            // user means today — they would have said "next Friday" otherwise.
            return $today->dayOfWeekIso === $weekday->value
                ? $today
                : $today->next($weekday->carbonConstant());
        }

        // "next Friday" = the Friday of the following week, which is always
        // 7–13 days away and therefore never today, whatever day it is now.
        $mondayNextWeek = $today->startOfWeek(CarbonInterface::MONDAY)->addWeek();

        return $mondayNextWeek->dayOfWeekIso === $weekday->value
            ? $mondayNextWeek
            : $mondayNextWeek->next($weekday->carbonConstant());
    }

    /**
     * Remove the date phrase from a message so what remains can become a task
     * title. Operates on the original (unfolded) text to preserve the user's
     * own spelling, using the same vocabulary `resolveDate()` matches on.
     */
    public function stripDatePhrase(string $original): string
    {
        $stripped = (string) preg_replace($this->stripPattern(), ' ', $original);

        // Clean up prepositions/particles left dangling by the removal.
        $stripped = (string) preg_replace(
            '/'.self::LB.'(?:on|for|due|by|at|in|يوم|في|ليوم|بتاع|بتاعت)'.self::RB.'\s*$/ui',
            '',
            trim($stripped)
        );

        return trim((string) preg_replace('/\s{2,}/u', ' ', $stripped), " \t\n\r\0\x0B.,!?،؟-");
    }

    /**
     * Normalise text for matching: lowercase, Arabic-Indic digits to Latin,
     * Arabic letter forms folded (أ/إ/آ → ا, ة → ه, ى → ي), diacritics and
     * tatweel removed, whitespace collapsed.
     *
     * Everything matched by this class is written in this folded form, so
     * "الجمعة", "الجُمعة" and "الجمعه" are all the same string here.
     */
    public function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        $text = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        $text = (string) preg_replace('/'.self::DIACRITICS.'/u', '', $text);

        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ی' => 'ي', 'ک' => 'ك',
        ]);

        return (string) preg_replace('/\s+/u', ' ', $text);
    }

    // ------------------------------------------------------------ matchers

    /** "2026-09-18", "September 18", "18 September", "18/9", "18/9/2026". */
    private function matchExplicitDate(string $text): ?CarbonImmutable
    {
        if (preg_match('/'.self::LB.'(\d{4})-(\d{1,2})-(\d{1,2})'.self::RB.'/u', $text, $m)) {
            return $this->makeDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        $months = implode('|', array_keys(self::MONTHS));

        // "September 18" / "Sep 18, 2026"
        if (preg_match(
            '/'.self::LB.'('.$months.')\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s*,?\s*(\d{4}))?'.self::RB.'/u',
            $text,
            $m
        )) {
            return $this->makeMonthDay(self::MONTHS[$m[1]], (int) $m[2], isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null);
        }

        // "18 September" / "18th of September 2026"
        if (preg_match(
            '/'.self::LB.'(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?('.$months.')(?:\s*,?\s*(\d{4}))?'.self::RB.'/u',
            $text,
            $m
        )) {
            return $this->makeMonthDay(self::MONTHS[$m[2]], (int) $m[1], isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null);
        }

        // "18/9" or "18/9/2026" — day first, the convention in Egypt.
        if (preg_match('/'.self::LB.'(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?'.self::RB.'/u', $text, $m)) {
            $year = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;

            if ($year !== null && $year < 100) {
                $year += 2000;
            }

            return $this->makeMonthDay((int) $m[2], (int) $m[1], $year);
        }

        return null;
    }

    /** "in 3 days", "in 2 weeks", "بعد 3 ايام", "بعد اسبوعين". */
    private function matchRelativeOffset(string $text): ?CarbonImmutable
    {
        if (preg_match('/'.self::LB.'in\s+(\d{1,3})\s+days?'.self::RB.'/u', $text, $m)) {
            return $this->today()->addDays((int) $m[1]);
        }

        if (preg_match('/'.self::LB.'in\s+(\d{1,3})\s+weeks?'.self::RB.'/u', $text, $m)) {
            return $this->today()->addWeeks((int) $m[1]);
        }

        if (preg_match('/'.self::LB.'بعد\s+(\d{1,3})\s+(?:يوم|ايام|يوما)'.self::RB.'/u', $text, $m)) {
            return $this->today()->addDays((int) $m[1]);
        }

        if (preg_match('/'.self::LB.'بعد\s+(\d{1,3})\s+(?:اسبوع|اسابيع)'.self::RB.'/u', $text, $m)) {
            return $this->today()->addWeeks((int) $m[1]);
        }

        if (preg_match('/'.self::LB.'بعد\s+يومين'.self::RB.'/u', $text)) {
            return $this->today()->addDays(2);
        }

        if (preg_match('/'.self::LB.'بعد\s+اسبوعين'.self::RB.'/u', $text)) {
            return $this->today()->addWeeks(2);
        }

        return null;
    }

    /** "today", "tomorrow", "day after tomorrow" and their Arabic equivalents. */
    private function matchNamedDay(string $text): ?CarbonImmutable
    {
        // Checked before "tomorrow"/"بكرة" because both phrases contain them.
        if ($this->matchesAny($text, array_merge(['day after tomorrow'], self::AR_DAY_AFTER_TOMORROW))) {
            return $this->today()->addDays(2);
        }

        if ($this->matchesAny($text, array_merge(['today', 'tonight'], self::AR_TODAY))) {
            return $this->today();
        }

        if ($this->matchesAny($text, array_merge(['tomorrow'], self::AR_TOMORROW))) {
            return $this->today()->addDay();
        }

        return null;
    }

    /** "Friday", "next Friday", "الجمعة", "الجمعة الجاية". */
    private function matchWeekdayPhrase(string $text): ?CarbonImmutable
    {
        if (! preg_match($this->weekdayPattern(), $text, $m)) {
            return null;
        }

        $weekday = Weekday::aliasMap()[$m['day']] ?? null;

        if ($weekday === null) {
            return null;
        }

        $isNextWeek = ($m['pre'] ?? '') === 'next'
            || in_array($m['post'] ?? '', self::AR_NEXT_MARKERS, true);

        return $this->resolveWeekday($weekday, $isNextWeek);
    }

    /** "next week" / "this week" with no weekday named. */
    private function matchWeekPhrase(string $text): ?CarbonImmutable
    {
        $arWeek = implode('|', self::AR_WEEK);
        $arNext = implode('|', self::AR_NEXT_MARKERS);
        $arThis = implode('|', self::AR_THIS_MARKERS);

        $isNextWeek = preg_match('/'.self::LB.'next\s+week'.self::RB.'/u', $text)
            || preg_match('/'.self::LB.'('.$arWeek.')\s+('.$arNext.')'.self::RB.'/u', $text);

        if ($isNextWeek) {
            // Monday of the following week: the earliest day of "next week",
            // so a task placed there is never quietly pushed past its window.
            return $this->today()->startOfWeek(CarbonInterface::MONDAY)->addWeek();
        }

        $isThisWeek = preg_match('/'.self::LB.'this\s+week'.self::RB.'/u', $text)
            || preg_match('/'.self::LB.'(هذا\s+)?('.$arWeek.')(\s+('.$arThis.'))?'.self::RB.'/u', $text);

        if ($isThisWeek) {
            // Sunday: the last day of the current Mon–Sun week.
            return $this->today()->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();
        }

        return null;
    }

    // ------------------------------------------------------------- helpers

    /** @param array<int, string> $needles */
    private function matchesAny(string $text, array $needles): bool
    {
        $alternation = implode('|', array_map(fn (string $n) => preg_quote($n, '/'), $needles));

        return (bool) preg_match('/'.self::LB.'(?:'.$alternation.')'.self::RB.'/u', $text);
    }

    private function weekdayPattern(): string
    {
        $days = implode('|', array_map(
            fn (string $alias) => preg_quote($alias, '/'),
            array_keys(Weekday::aliasMap())
        ));

        $markers = implode('|', array_map(
            fn (string $marker) => preg_quote($marker, '/'),
            array_merge(self::AR_NEXT_MARKERS, self::AR_THIS_MARKERS)
        ));

        return '/'.self::LB
            .'(?:(?P<pre>next|this|coming|upcoming)\s+)?'
            .'(?P<day>'.$days.')'
            .'(?:\s+(?P<post>'.$markers.'))?'
            .self::RB.'/u';
    }

    /**
     * A day/month with no year: this year, or next year if it already passed.
     * An explicit year is always honoured as given.
     */
    private function makeMonthDay(int $month, int $day, ?int $year): ?CarbonImmutable
    {
        if ($year !== null) {
            return $this->makeDate($year, $month, $day);
        }

        $today = $this->today();
        $candidate = $this->makeDate($today->year, $month, $day);

        if ($candidate === null) {
            return null;
        }

        return $candidate->lessThan($today)
            ? $this->makeDate($today->year + 1, $month, $day)
            : $candidate;
    }

    /** Build a date, rejecting impossible ones ("February 31") instead of rolling over. */
    private function makeDate(int $year, int $month, int $day): ?CarbonImmutable
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || ! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, $this->timezone());
    }

    /**
     * One pattern covering every date phrase, tolerant of the Arabic spelling
     * variants that `fold()` collapses, so it can be applied to raw input.
     */
    private function stripPattern(): string
    {
        $months = implode('|', array_keys(self::MONTHS));

        // Longer phrases first: "بعد بكرة" must be consumed whole rather than
        // leaving a stray "بعد" behind once "بكرة" is stripped out of it.
        $words = array_merge(
            array_keys(Weekday::aliasMap()),
            self::AR_DAY_AFTER_TOMORROW,
            self::AR_TODAY,
            self::AR_TOMORROW,
            self::AR_WEEK,
            ['day after tomorrow', 'today', 'tonight', 'tomorrow', 'week']
        );

        $wordAlternation = implode('|', array_map(
            fn (string $word) => $this->flexible($word),
            $words
        ));

        $markers = implode('|', array_map(
            fn (string $marker) => $this->flexible($marker),
            array_merge(self::AR_NEXT_MARKERS, self::AR_THIS_MARKERS)
        ));

        $numeric = '\d{4}-\d{1,2}-\d{1,2}'
            .'|\d{1,2}\/\d{1,2}(?:\/\d{2,4})?'
            .'|(?:'.$months.')\s+\d{1,2}(?:st|nd|rd|th)?(?:\s*,?\s*\d{4})?'
            .'|\d{1,2}(?:st|nd|rd|th)?\s+(?:of\s+)?(?:'.$months.')(?:\s*,?\s*\d{4})?'
            .'|in\s+\d{1,3}\s+(?:days?|weeks?)'
            .'|'.$this->flexible('بعد').'\s+(?:\d{1,3}\s+)?'
                .'(?:'.$this->flexible('يوم').'|'.$this->flexible('ايام').'|'.$this->flexible('يومين')
                .'|'.$this->flexible('اسبوع').'|'.$this->flexible('اسابيع').'|'.$this->flexible('اسبوعين').')';

        // Optional leading preposition/particle, then either a numeric date
        // form or a named day/week optionally wrapped in next/this markers.
        return '/'.self::LB
            .'(?:(?:on|for|due|by|at|in|'.$this->flexible('يوم').'|'.$this->flexible('في')
                .'|'.$this->flexible('ليوم').')\s+)?'
            .'(?:(?:next|this|coming|upcoming|'.$this->flexible('هذا').')\s+)?'
            .'(?:'.$numeric.'|(?:'.$wordAlternation.')(?:\s+(?:'.$markers.'))?)'
            .self::RB.'/ui';
    }

    /**
     * Expand a folded word into a pattern that also matches its unfolded
     * spellings — أ/إ/آ for ا, ة for ه, ى for ي — with optional diacritics
     * between letters.
     */
    private function flexible(string $folded): string
    {
        $chars = preg_split('//u', $folded, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';

        foreach ($chars as $char) {
            if ($char === ' ') {
                $out .= '\s+';

                continue;
            }

            $out .= match ($char) {
                'ا' => '[اأإآٱ]',
                'ه' => '[هة]',
                'ي' => '[يىی]',
                'ك' => '[كک]',
                default => preg_quote($char, '/'),
            }.self::DIACRITICS.'*';
        }

        return $out;
    }
}
