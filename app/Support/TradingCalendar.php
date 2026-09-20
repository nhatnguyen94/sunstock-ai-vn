<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Which trading session should the price history already contain? Vietnamese exchanges trade Mon–Fri
 * 09:00–15:00 (Asia/Ho_Chi_Minh). Public holidays are not known here: on one, the answer is a session too late,
 * which only costs one harmless background refresh (deduplicated) — never wrong data.
 */
final class TradingCalendar
{
    public const TZ = 'Asia/Ho_Chi_Minh';

    /** The closing auction ends 15:00 and the exchanges publish the final bar a little later. */
    public const DATA_READY = '15:15';

    /** Date (Vietnam time, midnight) of the newest session whose daily bar is final at `$now`. */
    public static function lastCompletedSession(?CarbonInterface $now = null): Carbon
    {
        $t = Carbon::instance(($now ?? Carbon::now())->toDateTime())->setTimezone(self::TZ);
        $day = $t->copy()->startOfDay();

        // Today's bar only counts once the session is over; before that the last complete one is an earlier day
        if (! ($t->isWeekday() && $t->format('H:i') >= self::DATA_READY)) {
            $day->subDay();
        }
        while ($day->isWeekend()) {
            $day->subDay();
        }

        return $day;
    }

    /** True while continuous matching + the closing auction run. */
    public static function isSessionOpen(?CarbonInterface $now = null): bool
    {
        $t = Carbon::instance(($now ?? Carbon::now())->toDateTime())->setTimezone(self::TZ);

        return $t->isWeekday() && $t->format('H:i') >= '09:00' && $t->format('H:i') < '15:00';
    }
}
