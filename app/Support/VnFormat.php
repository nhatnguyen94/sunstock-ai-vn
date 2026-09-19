<?php

namespace App\Support;

/**
 * Vietnamese-style number/date formatting for Blade views (1.234,5 — d/m/Y).
 * Every method is null-safe and returns an em dash for "no data", so templates
 * never have to guard each value.
 */
class VnFormat
{
    public const EMPTY = '—';

    public static function number(int|float|null $v, int $decimals = 0): string
    {
        return $v === null ? self::EMPTY : number_format((float) $v, $decimals, ',', '.');
    }

    public static function percent(int|float|null $v, int $decimals = 2, bool $signed = false): string
    {
        if ($v === null) {
            return self::EMPTY;
        }

        return ($signed && $v > 0 ? '+' : '') . number_format((float) $v, $decimals, ',', '.') . '%';
    }

    /** VND amount in the unit people actually read: nghìn tỷ / tỷ / triệu. */
    public static function bigMoney(int|float|null $v): string
    {
        if ($v === null) {
            return self::EMPTY;
        }

        $abs = abs((float) $v);

        return match (true) {
            $abs >= 1e12 => self::number($v / 1e12, 1) . ' nghìn tỷ ₫',
            $abs >= 1e9  => self::number($v / 1e9, 0) . ' tỷ ₫',
            $abs >= 1e6  => self::number($v / 1e6, 0) . ' triệu ₫',
            default      => self::number($v) . ' ₫',
        };
    }

    /**
     * ISO / Y-m-d[...] string -> d/m/Y. Anything that does not start with a Y-m-d date
     * is returned as-is (Carbon would otherwise silently fall back to "now").
     */
    public static function date(?string $value): string
    {
        if ($value === null || $value === '') {
            return self::EMPTY;
        }

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $value;
        }

        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }

    /** CSS class for a signed return: up / down / flat. */
    public static function trendClass(int|float|null $v): string
    {
        return match (true) {
            $v === null => 'is-na',
            $v > 0      => 'is-up',
            $v < 0      => 'is-down',
            default     => 'is-flat',
        };
    }
}
