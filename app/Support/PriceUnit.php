<?php

namespace App\Support;

/**
 * The vnstock price feed (and therefore stock_prices.close/open/high/low) is quoted in THOUSANDS of VND:
 * ACB closes at 22.05 meaning 22,050 ₫. Everything a user types or sees in money terms (portfolio buy
 * price, target/stop-loss, P&L) is in whole VND. This class is the single conversion point — copying a
 * feed price straight into a VND column is what made a 85.000 ₫ buy show a "-99.97%" loss.
 */
class PriceUnit
{
    public const FEED_TO_VND = 1000;

    public static function toVnd(int|float|null $feedPrice): ?float
    {
        return $feedPrice === null ? null : round((float) $feedPrice * self::FEED_TO_VND, 2);
    }

    public static function toFeed(int|float|null $vnd): ?float
    {
        return $vnd === null ? null : (float) $vnd / self::FEED_TO_VND;
    }
}
