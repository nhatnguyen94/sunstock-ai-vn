<?php

namespace Tests\Unit\Support;

use App\Support\TradingCalendar;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class TradingCalendarTest extends TestCase
{
    /** Vietnam time -> the session date (Y-m-d) that should already be stored. */
    private function expected(string $vietnamTime): string
    {
        return TradingCalendar::lastCompletedSession(Carbon::parse($vietnamTime, TradingCalendar::TZ))->toDateString();
    }

    #[Group('stockFreshness')]
    public function test_after_the_close_of_a_weekday_that_day_is_the_last_completed_session(): void
    {
        $this->assertSame('2026-09-18', $this->expected('2026-09-18 15:15:00'));    // Friday just after the data is final
        $this->assertSame('2026-09-18', $this->expected('2026-09-18 23:59:00'));
        $this->assertSame('2026-09-21', $this->expected('2026-09-21 18:00:00'));    // Monday evening
    }

    #[Group('stockFreshness')]
    public function test_before_the_data_is_final_the_previous_weekday_counts(): void
    {
        $this->assertSame('2026-09-17', $this->expected('2026-09-18 10:00:00'));    // Friday mid-session: Thursday is complete
        $this->assertSame('2026-09-17', $this->expected('2026-09-18 15:14:00'));    // closed at 15:00 but not published yet
        $this->assertSame('2026-09-17', $this->expected('2026-09-18 03:00:00'));    // small hours
    }

    #[Group('stockFreshness')]
    public function test_weekends_and_monday_morning_fall_back_to_friday(): void
    {
        $this->assertSame('2026-09-18', $this->expected('2026-09-19 12:00:00'));    // Saturday
        $this->assertSame('2026-09-18', $this->expected('2026-09-20 12:00:00'));    // Sunday
        $this->assertSame('2026-09-18', $this->expected('2026-09-21 08:00:00'));    // Monday before the open
        $this->assertSame('2026-09-18', $this->expected('2026-09-21 11:00:00'));    // Monday mid-session
    }

    #[Group('stockFreshness')]
    public function test_the_clock_is_read_in_vietnam_time_whatever_the_input_timezone(): void
    {
        // 09:00 UTC on Friday = 16:00 in Vietnam -> Friday's bar is final
        $this->assertSame('2026-09-18', TradingCalendar::lastCompletedSession(Carbon::parse('2026-09-18 09:00:00', 'UTC'))->toDateString());
        // 07:00 UTC = 14:00 in Vietnam -> still in session
        $this->assertSame('2026-09-17', TradingCalendar::lastCompletedSession(Carbon::parse('2026-09-18 07:00:00', 'UTC'))->toDateString());
    }

    #[Group('stockFreshness')]
    public function test_session_open_is_weekdays_9_to_15_vietnam_time(): void
    {
        $at = fn (string $t) => TradingCalendar::isSessionOpen(Carbon::parse($t, TradingCalendar::TZ));

        $this->assertTrue($at('2026-09-18 09:00:00'));
        $this->assertTrue($at('2026-09-18 14:59:00'));
        $this->assertFalse($at('2026-09-18 15:00:00'));
        $this->assertFalse($at('2026-09-18 08:59:00'));
        $this->assertFalse($at('2026-09-19 10:00:00'));   // Saturday
    }
}
