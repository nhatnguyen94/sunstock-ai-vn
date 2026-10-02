<?php

namespace Tests\Unit\Support;

use App\Support\VnFormat;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class VnFormatTest extends TestCase
{
    #[Group('companyProfile')]
    #[Group('fundCatalog')]
    public function test_numbers_use_vietnamese_separators_and_null_becomes_a_dash(): void
    {
        $this->assertSame('1.234.567', VnFormat::number(1234567));
        $this->assertSame('1.234,50', VnFormat::number(1234.5, 2));
        $this->assertSame('—', VnFormat::number(null));
        $this->assertSame('0', VnFormat::number(0));
    }

    #[Group('companyProfile')]
    #[Group('fundCatalog')]
    public function test_percent_optionally_shows_a_plus_sign_only_for_positive_values(): void
    {
        $this->assertSame('+3,46%', VnFormat::percent(3.456, 2, true));
        $this->assertSame('3,46%', VnFormat::percent(3.456));
        $this->assertSame('-1,20%', VnFormat::percent(-1.2, 2, true));
        $this->assertSame('0,00%', VnFormat::percent(0, 2, true));
        $this->assertSame('—', VnFormat::percent(null));
    }

    #[Group('companyProfile')]
    public function test_big_money_picks_the_unit_people_actually_read(): void
    {
        $this->assertSame('122,9 nghìn tỷ ₫', VnFormat::bigMoney(122_917_204_457_400));
        $this->assertSame('8,5 nghìn tỷ ₫', VnFormat::bigMoney(8_501_000_000_000));
        $this->assertSame('850 tỷ ₫', VnFormat::bigMoney(850_100_000_000));
        $this->assertSame('130 tỷ ₫', VnFormat::bigMoney(130_000_000_000));
        $this->assertSame('25 triệu ₫', VnFormat::bigMoney(25_000_000));
        $this->assertSame('500 ₫', VnFormat::bigMoney(500));
        $this->assertSame('—', VnFormat::bigMoney(null));
    }

    #[Group('companyProfile')]
    #[Group('fundCatalog')]
    public function test_date_formats_iso_strings_and_survives_garbage(): void
    {
        $this->assertSame('19/09/2026', VnFormat::date('2026-09-19'));
        $this->assertSame('31/12/2025', VnFormat::date('2025-12-31T00:00:00'));
        $this->assertSame('—', VnFormat::date(null));
        $this->assertSame('—', VnFormat::date(''));
        $this->assertSame('not a date', VnFormat::date('not a date'));
        $this->assertSame('2026-13-45', VnFormat::date('2026-13-45'));   // well-formed shape, impossible date
    }

    #[Group('fundCatalog')]
    public function test_trend_class(): void
    {
        $this->assertSame('is-up', VnFormat::trendClass(0.01));
        $this->assertSame('is-down', VnFormat::trendClass(-0.01));
        $this->assertSame('is-flat', VnFormat::trendClass(0));
        $this->assertSame('is-na', VnFormat::trendClass(null));
    }

    #[Group('stockPageUx')]
    public function test_signed_shows_an_explicit_plus_and_keeps_the_minus(): void
    {
        $this->assertSame('+600', VnFormat::signed(600));
        $this->assertSame('-600', VnFormat::signed(-600));
        $this->assertSame('0', VnFormat::signed(0));
        $this->assertSame('+1.234,50', VnFormat::signed(1234.5, 2));
        $this->assertSame('—', VnFormat::signed(null));
    }

    #[Group('stockPageUx')]
    public function test_volume_uses_the_spoken_units(): void
    {
        $this->assertSame('3,2 tr', VnFormat::volume(3_199_600));
        $this->assertSame('45,3K', VnFormat::volume(45_300));
        $this->assertSame('700', VnFormat::volume(700));
        $this->assertSame('1,5 tỷ', VnFormat::volume(1_500_000_000));
        $this->assertSame('—', VnFormat::volume(null));
        $this->assertSame('0', VnFormat::volume(0));
    }

    #[Group('stockPageUx')]
    public function test_exchange_names_follow_what_people_call_them(): void
    {
        $this->assertSame('HOSE', VnFormat::exchange('HSX'));
        $this->assertSame('UPCoM', VnFormat::exchange('upcom'));
        $this->assertSame('HNX', VnFormat::exchange(' hnx '));
        $this->assertSame('', VnFormat::exchange(null));
        $this->assertSame('', VnFormat::exchange('  '));
    }
}
