<?php

namespace Tests\Unit\Support;

use App\Support\MarketHeatmap;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class MarketHeatmapTest extends TestCase
{
    /** quote = [price, reference, %, volume, value, ceiling, floor, open, high, low, exchange] */
    private function q(int $price, float $pct, int $volume, int $value, ?string $exchange = 'HOSE'): array
    {
        return [$price, $price, $pct, $volume, $value, null, null, null, null, null, $exchange];
    }

    #[Group('marketHeatmap')]
    public function test_the_busiest_symbols_come_first_and_the_limit_is_respected(): void
    {
        $quotes = ['AAA' => $this->q(10000, 1.0, 1, 100), 'BBB' => $this->q(10000, 1.0, 1, 500), 'CCC' => $this->q(10000, 1.0, 1, 300)];

        $this->assertSame(['BBB', 'CCC', 'AAA'], MarketHeatmap::topSymbols($quotes, 10));
        $this->assertSame(['BBB', 'CCC'], MarketHeatmap::topSymbols($quotes, 2));
        $this->assertSame([], MarketHeatmap::topSymbols($quotes, 0));
        $this->assertSame([], MarketHeatmap::topSymbols([], 5));
    }

    #[Group('marketHeatmap')]
    public function test_a_stock_that_did_not_trade_or_has_broken_numbers_gets_no_tile(): void
    {
        $quotes = [
            'OK' => $this->q(10000, 1.0, 1, 100),
            'ZERO' => $this->q(10000, 0.0, 0, 0),                          // no traded value
            'NULLVAL' => [10000, 10000, 0.5, 5, null],
            'TEXT' => ['abc', 10000, 0.5, 5, 100],
            'SHORT' => [10000],
            'NOTARRAY' => 'x',
        ];

        $this->assertSame(['OK'], MarketHeatmap::topSymbols($quotes, 10));
    }

    #[Group('marketHeatmap')]
    public function test_each_tile_carries_what_the_page_needs(): void
    {
        $quotes = ['FPT' => $this->q(71700, -3.5, 15_500_700, 1_129_620_000_000, 'HOSE')];
        $info = ['FPT' => ['name' => 'FPT Corp', 'exchange' => 'HSX', 'industry' => 'Công nghệ và thông tin']];

        $out = MarketHeatmap::build($quotes, $info);

        $this->assertSame([[
            's' => 'FPT', 'n' => 'FPT Corp', 'x' => 'HOSE', 'i' => 'Công nghệ và thông tin',
            'p' => 71700, 'c' => -3.5, 'v' => 1_129_620_000_000, 'q' => 15_500_700,
        ]], $out['items']);
    }

    #[Group('marketHeatmap')]
    public function test_the_exchange_comes_from_the_quote_not_from_the_unreliable_symbol_list(): void
    {
        $quotes = ['NVB' => $this->q(12800, 9.4, 1, 27_000_000_000, 'HNX')];
        $info = ['NVB' => ['name' => 'NCB', 'exchange' => 'HSX', 'industry' => 'Ngân hàng']];   // the symbol list says HSX for everything

        $this->assertSame('HNX', MarketHeatmap::build($quotes, $info)['items'][0]['x']);
    }

    #[Group('marketHeatmap')]
    public function test_an_older_snapshot_without_an_exchange_gives_an_empty_one_not_a_guess(): void
    {
        $quotes = ['OLD' => [10000, 10000, 1.0, 10, 100]];   // 5 elements, as stored before the exchange was added

        $this->assertSame('', MarketHeatmap::build($quotes, [])['items'][0]['x']);
        $this->assertSame('', MarketHeatmap::build(['N' => $this->q(1000, 0.1, 1, 10, null)], [])['items'][0]['x']);
    }

    #[Group('marketHeatmap')]
    public function test_an_unknown_or_blank_industry_falls_into_khac(): void
    {
        $quotes = ['AAA' => $this->q(10000, 1.0, 1, 100), 'BBB' => $this->q(10000, 1.0, 1, 90), 'CCC' => $this->q(10000, 1.0, 1, 80)];
        $info = ['AAA' => ['name' => 'A', 'industry' => '   '], 'BBB' => ['name' => 'B', 'industry' => null]];

        $items = MarketHeatmap::build($quotes, $info)['items'];

        $this->assertSame(['Khác', 'Khác', 'Khác'], array_column($items, 'i'));
        $this->assertSame(MarketHeatmap::OTHER, 'Khác');
        $this->assertSame('', $items[2]['n'], 'a symbol missing from the list has an empty name');
    }

    #[Group('marketHeatmap')]
    public function test_totals_and_direction_counts_describe_what_is_shown(): void
    {
        $quotes = [
            'UP' => $this->q(10000, 2.5, 1, 300), 'UP2' => $this->q(10000, 0.1, 1, 200),
            'DN' => $this->q(10000, -1.0, 1, 100), 'FLAT' => $this->q(10000, 0.0, 1, 50),
        ];

        $out = MarketHeatmap::build($quotes, []);

        $this->assertSame(4, $out['shown']);
        $this->assertSame(650, $out['total_value']);
        $this->assertSame([2, 1, 1], [$out['advancers'], $out['decliners'], $out['flat']]);
    }

    #[Group('marketHeatmap')]
    public function test_percentages_are_rounded_to_two_places_and_numbers_are_integers(): void
    {
        $out = MarketHeatmap::build(['A' => ['10000.4', 10000, '1.23456', '55', '1000.7', null, null, null, null, null, 'HNX']], []);

        $this->assertSame(10000, $out['items'][0]['p']);
        $this->assertSame(1.23, $out['items'][0]['c']);
        $this->assertSame(1000, $out['items'][0]['v']);
        $this->assertSame(55, $out['items'][0]['q']);
    }

    #[Group('marketHeatmap')]
    public function test_an_empty_market_builds_an_empty_map(): void
    {
        $out = MarketHeatmap::build([], []);

        $this->assertSame([], $out['items']);
        $this->assertSame([0, 0, 0, 0, 0], [$out['shown'], $out['total_value'], $out['advancers'], $out['decliners'], $out['flat']]);
    }
}
