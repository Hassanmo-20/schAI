<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Weekday;
use App\Models\Batch;
use App\Models\Task;
use App\Models\User;
use App\Services\Assistant\DateNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Weekday/date normalisation — the behaviour DateNormalizer's docblock
 * promises, in English and Egyptian Arabic.
 *
 * Every case is anchored to a fixed "now" of Thursday 2026-09-10 10:00 in
 * Africa/Cairo, so the expected dates are literal and the suite cannot start
 * failing on a different day of the week.
 *
 * The headline rule: a named weekday resolves to THAT weekday. The old parser
 * mixed ISO day numbers (Monday = 1) with Carbon's `dayOfWeek` (Sunday = 0),
 * which shifted every answer by one day.
 */
class AssistantDateParsingTest extends TestCase
{
    use RefreshDatabase;

    /** Thursday. */
    private const NOW = '2026-09-10 10:00:00';

    private DateNormalizer $dates;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.timezone', 'Africa/Cairo');
        config()->set('services.openai.key', null);
        Http::preventStrayRequests();

        CarbonImmutable::setTestNow(CarbonImmutable::parse(self::NOW, 'Africa/Cairo'));

        $this->dates = app(DateNormalizer::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function assertResolvesTo(string $expectedDate, string $phrase): void
    {
        $resolved = $this->dates->resolveDate($phrase);

        $this->assertNotNull($resolved, "\"{$phrase}\" was not recognised as a date at all.");
        $this->assertSame(
            $expectedDate,
            $resolved->format('Y-m-d'),
            "\"{$phrase}\" resolved to {$resolved->format('Y-m-d l')}, expected {$expectedDate}."
        );
    }

    // ------------------------------------------------- the headline guarantee

    /**
     * Friday resolves to a Friday, Saturday to a Saturday, and so on — for
     * every weekday, in both languages, in both the "upcoming" and "next week"
     * forms.
     */
    public function test_every_weekday_name_resolves_to_that_same_weekday(): void
    {
        foreach (Weekday::cases() as $weekday) {
            foreach ($weekday->aliases() as $alias) {
                foreach (['%s', 'next %s', 'this %s', '%s الجايه'] as $template) {
                    $phrase = sprintf($template, $alias);
                    $resolved = $this->dates->resolveDate($phrase);

                    $this->assertNotNull($resolved, "\"{$phrase}\" was not recognised.");
                    $this->assertSame(
                        $weekday->value,
                        $resolved->dayOfWeekIso,
                        "\"{$phrase}\" resolved to {$resolved->format('l')}, expected {$weekday->label()}."
                    );
                }
            }
        }
    }

    public function test_friday_resolves_to_the_upcoming_friday(): void
    {
        // Now = Thursday 2026-09-10, so "Friday" is tomorrow.
        $this->assertResolvesTo('2026-09-11', 'Friday');
        $this->assertResolvesTo('2026-09-11', 'this friday');
        $this->assertResolvesTo('2026-09-12', 'Saturday');
        $this->assertResolvesTo('2026-09-13', 'sunday');
        $this->assertResolvesTo('2026-09-14', 'Monday');
    }

    public function test_todays_own_weekday_means_today_not_a_week_away(): void
    {
        // Now IS Thursday: someone saying "Thursday" means today.
        $this->assertResolvesTo('2026-09-10', 'Thursday');
    }

    public function test_next_weekday_is_the_following_week_never_today(): void
    {
        // Monday of the following Mon–Sun week is 2026-09-14.
        $this->assertResolvesTo('2026-09-17', 'next Thursday');
        $this->assertResolvesTo('2026-09-18', 'next Friday');
        $this->assertResolvesTo('2026-09-14', 'next Monday');

        // "Friday" and "next Friday" must not be the same day — the old parser
        // treated the "next" prefix as decoration and produced one answer.
        $this->assertNotEquals(
            $this->dates->resolveDate('Friday')->format('Y-m-d'),
            $this->dates->resolveDate('next Friday')->format('Y-m-d'),
        );
    }

    // ------------------------------------------------------------- English

    public function test_named_days_in_english(): void
    {
        $this->assertResolvesTo('2026-09-10', 'today');
        $this->assertResolvesTo('2026-09-10', 'tonight');
        $this->assertResolvesTo('2026-09-11', 'tomorrow');
        $this->assertResolvesTo('2026-09-12', 'day after tomorrow');
    }

    public function test_relative_offsets_in_english(): void
    {
        $this->assertResolvesTo('2026-09-13', 'in 3 days');
        $this->assertResolvesTo('2026-09-24', 'in 2 weeks');
    }

    public function test_week_phrases_in_english(): void
    {
        // Weeks are ISO (Mon–Sun): this week ends Sunday 2026-09-13, and next
        // week starts Monday 2026-09-14.
        $this->assertResolvesTo('2026-09-13', 'this week');
        $this->assertResolvesTo('2026-09-14', 'next week');
    }

    public function test_explicit_dates(): void
    {
        $this->assertResolvesTo('2026-09-18', '2026-09-18');
        $this->assertResolvesTo('2026-09-18', 'September 18');
        $this->assertResolvesTo('2026-09-18', 'Sep 18, 2026');
        $this->assertResolvesTo('2026-09-18', '18 September');
        $this->assertResolvesTo('2026-09-18', '18/9');
        $this->assertResolvesTo('2026-09-18', '18/9/2026');

        // A day/month that has already gone by means next year.
        $this->assertResolvesTo('2027-01-05', 'January 5');
    }

    public function test_impossible_dates_are_rejected_rather_than_rolled_over(): void
    {
        $this->assertNull($this->dates->resolveDate('February 31'));
        $this->assertNull($this->dates->resolveDate('2026-13-01'));
    }

    public function test_a_message_with_no_date_resolves_to_null(): void
    {
        $this->assertNull($this->dates->resolveDate('Add a database assignment'));
    }

    // -------------------------------------------------------------- Arabic

    public function test_named_days_in_arabic(): void
    {
        $this->assertResolvesTo('2026-09-10', 'النهارده');
        $this->assertResolvesTo('2026-09-10', 'اليوم');
        $this->assertResolvesTo('2026-09-11', 'بكرة');
        $this->assertResolvesTo('2026-09-11', 'بكره');
        $this->assertResolvesTo('2026-09-12', 'بعد بكرة');
        $this->assertResolvesTo('2026-09-12', 'بعد غد');
    }

    public function test_weekdays_in_arabic(): void
    {
        $this->assertResolvesTo('2026-09-11', 'الجمعة');
        $this->assertResolvesTo('2026-09-11', 'الجمعه');
        $this->assertResolvesTo('2026-09-12', 'السبت');
        $this->assertResolvesTo('2026-09-13', 'الاحد');
        $this->assertResolvesTo('2026-09-14', 'الاثنين');
        $this->assertResolvesTo('2026-09-14', 'الاتنين');
    }

    public function test_arabic_diacritics_and_spelling_variants_fold_to_the_same_day(): void
    {
        // Harakat, tatweel, and the أ/ا and ة/ه variants must not change the answer.
        $this->assertResolvesTo('2026-09-11', 'الجُمعة');
        $this->assertResolvesTo('2026-09-12', 'السَبت');
        $this->assertResolvesTo('2026-09-14', 'الإثنين');
    }

    public function test_arabic_next_marker_moves_to_the_following_week(): void
    {
        $this->assertResolvesTo('2026-09-18', 'الجمعة الجاية');
        $this->assertResolvesTo('2026-09-18', 'الجمعه الجايه');
        $this->assertResolvesTo('2026-09-19', 'السبت القادم');
    }

    public function test_arabic_relative_offsets(): void
    {
        $this->assertResolvesTo('2026-09-13', 'بعد 3 ايام');
        $this->assertResolvesTo('2026-09-13', 'بعد ٣ ايام'); // Arabic-Indic digits
        $this->assertResolvesTo('2026-09-12', 'بعد يومين');
        $this->assertResolvesTo('2026-09-24', 'بعد اسبوعين');
    }

    public function test_arabic_week_phrases(): void
    {
        $this->assertResolvesTo('2026-09-14', 'الاسبوع الجاي');
        $this->assertResolvesTo('2026-09-13', 'الاسبوع ده');
    }

    // ------------------------------------------------------------ deadlines

    public function test_deadlines_land_at_end_of_the_local_day(): void
    {
        $deadline = $this->dates->resolveDeadline('Friday');

        $this->assertNotNull($deadline);
        $this->assertSame('2026-09-11 23:59:00', $deadline->format('Y-m-d H:i:s'));
        $this->assertSame('Africa/Cairo', $deadline->timezone->getName());
    }

    /**
     * The bug this guards against: computing "today" in UTC while the user is
     * in Cairo. At 01:00 Cairo it is still the previous day in UTC, so a
     * UTC-based "today" would be off by one.
     */
    public function test_today_is_the_local_calendar_day_not_the_utc_one(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-11 01:00:00', 'Africa/Cairo'));

        $this->assertSame('2026-09-11', $this->dates->today()->format('Y-m-d'));
        $this->assertResolvesTo('2026-09-11', 'today');
        $this->assertSame('2026-09-10', CarbonImmutable::now('UTC')->format('Y-m-d'));
    }

    // ------------------------------------------------- title/date separation

    public function test_the_date_phrase_is_removed_from_the_task_title(): void
    {
        $this->assertSame('Database assignment', $this->dates->stripDatePhrase('Database assignment on Thursday'));
        $this->assertSame('Database assignment', $this->dates->stripDatePhrase('Database assignment due next Friday'));
        $this->assertSame('OOP quiz', $this->dates->stripDatePhrase('OOP quiz tomorrow'));
        $this->assertSame('Networks project', $this->dates->stripDatePhrase('Networks project in 3 days'));
        $this->assertSame('امتحان الشبكات', $this->dates->stripDatePhrase('امتحان الشبكات يوم الجمعة'));
        $this->assertSame('واجب البرمجة', $this->dates->stripDatePhrase('واجب البرمجة بعد بكرة'));
    }

    // ----------------------------------------------------------- end to end

    public function test_adding_a_task_for_friday_through_chat_really_stores_a_friday(): void
    {
        $batch = Batch::factory()->create();
        $student = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Student]);
        Sanctum::actingAs($student);

        $this->postJson('/api/assistant/chat', [
            'message' => 'Add an OOP quiz on Friday',
        ])->assertOk();

        $task = Task::where('title', 'Oop Quiz')->firstOrFail();

        $this->assertSame('Friday', $task->deadline->format('l'));
        $this->assertSame('2026-09-11 23:59:00', $task->deadline->format('Y-m-d H:i:s'));
    }

    public function test_adding_a_task_in_arabic_through_chat_resolves_the_right_day(): void
    {
        $batch = Batch::factory()->create();
        $student = User::factory()->create(['batch_id' => $batch->id, 'role' => Role::Student]);
        Sanctum::actingAs($student);

        $this->postJson('/api/assistant/chat', [
            'message' => 'عندي امتحان الشبكات يوم السبت',
        ])->assertOk();

        $task = Task::query()->firstOrFail();

        $this->assertSame('Saturday', $task->deadline->format('l'));
        $this->assertSame('2026-09-12', $task->deadline->format('Y-m-d'));
        $this->assertStringNotContainsString('السبت', $task->title);
    }
}
