<?php

namespace Tests\Unit\Support;

use App\Support\MarketBrief;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class MarketBriefTest extends TestCase
{
    private function overview(array $over = []): array
    {
        return array_replace_recursive([
            'indices' => [
                ['code' => 'VNINDEX', 'name' => 'VN-Index', 'close' => 1737.71, 'change' => -11.59, 'percent' => -0.66],
                ['code' => 'VN30', 'name' => 'VN30', 'close' => 1875.99, 'change' => -14.58, 'percent' => -0.77],
                ['code' => 'HNXINDEX', 'name' => 'HNX-Index', 'close' => 266.75, 'change' => -1.91, 'percent' => -0.71],
            ],
            'breadth' => ['advancers' => 266, 'decliners' => 423, 'unchanged' => 227, 'ceiling' => 34, 'floor' => 35, 'total' => 916],
            'liquidity' => ['value' => 15_500_000_000_000, 'previous' => 12_300_000_000_000, 'change_percent' => 25.8],
            'movers' => ['ALL' => [
                'gainers' => [['symbol' => 'NVB', 'percent' => 9.49, 'value' => 60_000_000_000]],
                'losers' => [['symbol' => 'PNJ', 'percent' => -6.87, 'value' => 1_800_000_000_000]],
            ]],
        ], $over);
    }

    /** tiles: [symbol, industry, value, %] */
    private function heatmap(array $tiles): array
    {
        return ['items' => array_map(fn (array $t) => ['s' => $t[0], 'n' => $t[0], 'x' => 'HOSE', 'i' => $t[1], 'p' => 10000, 'c' => $t[3], 'v' => $t[2], 'q' => 1], $tiles)];
    }

    private function point(array $brief, string $key): ?array
    {
        return collect($brief['points'])->firstWhere('key', $key);
    }

    #[Group('marketBrief')]
    public function test_no_brief_without_a_vn_index(): void
    {
        $this->assertNull(MarketBrief::build([]));
        $this->assertNull(MarketBrief::build(['indices' => [['code' => 'VN30', 'close' => 1, 'change' => 0, 'percent' => 0]]]));
        $this->assertNull(MarketBrief::build(['indices' => [['code' => 'VNINDEX', 'close' => 1700]]]), 'missing change/percent');
    }

    #[Group('marketBrief')]
    public function test_the_headline_states_the_move_the_breadth_and_the_liquidity_in_vietnamese_format(): void
    {
        $b = MarketBrief::build($this->overview());

        $this->assertSame(
            'VN-Index giảm 11,59 điểm (0,66%) về 1.737,71. 266 mã tăng, 423 mã giảm. Thanh khoản 15,5 nghìn tỷ ₫ (+25,8% so với phiên trước).',
            $b['headline']
        );
        $this->assertSame('down', $b['tone']);
    }

    #[Group('marketBrief')]
    public function test_the_liquidity_comparison_is_left_out_when_there_is_none(): void
    {
        $b = MarketBrief::build($this->overview(['liquidity' => ['change_percent' => null]]));

        $this->assertStringContainsString('Thanh khoản 15,5 nghìn tỷ ₫.', $b['headline']);
        $this->assertStringNotContainsString('so với phiên trước', $b['headline']);
    }

    #[Group('marketBrief')]
    public function test_the_verb_follows_the_size_of_the_move(): void
    {
        $cases = [
            [0.0, 'gần như đi ngang'], [0.1, 'gần như đi ngang'], [-0.1, 'gần như đi ngang'],
            [0.3, 'nhích lên'], [-0.3, 'nhích xuống'],
            [0.8, 'tăng'], [-0.8, 'giảm'],
            [2.0, 'tăng mạnh'], [-3.4, 'giảm mạnh'],
        ];
        foreach ($cases as [$pct, $verb]) {
            $this->assertSame($verb, MarketBrief::verb($pct), "{$pct}%");
        }
    }

    #[Group('marketBrief')]
    public function test_direction_treats_a_tiny_move_as_flat(): void
    {
        $this->assertSame('flat', MarketBrief::direction(0.04));
        $this->assertSame('flat', MarketBrief::direction(-0.049));
        $this->assertSame('up', MarketBrief::direction(0.05));
        $this->assertSame('down', MarketBrief::direction(-0.06));
    }

    #[Group('marketBrief')]
    public function test_a_flat_session_does_not_claim_a_rise_or_a_fall(): void
    {
        $b = MarketBrief::build($this->overview(['indices' => [['code' => 'VNINDEX', 'change' => 0.35, 'percent' => 0.02, 'close' => 1700.0]]]));

        $this->assertSame('flat', $b['tone']);
        $this->assertStringStartsWith('VN-Index gần như đi ngang (+0,35 điểm), đứng ở 1.700,00.', $b['headline']);
        $this->assertStringNotContainsString('Vì sao VN-Index', $b['ask']);
    }

    #[Group('marketBrief')]
    public function test_the_ask_question_matches_the_direction_and_is_grounded_in_the_available_data(): void
    {
        $down = MarketBrief::build($this->overview())['ask'];
        $up = MarketBrief::build($this->overview(['indices' => [['code' => 'VNINDEX', 'change' => 10.0, 'percent' => 1.2]]]))['ask'];

        $this->assertSame('Vì sao VN-Index giảm 0,66% trong phiên gần nhất? Giải thích ngắn gọn dựa trên số liệu thị trường hiện có.', $down);
        $this->assertStringStartsWith('Vì sao VN-Index tăng 1,20%', $up);
    }

    #[Group('marketBrief')]
    public function test_breadth_says_who_is_in_control_and_counts_ceilings_and_floors(): void
    {
        $this->assertSame('down', $this->point(MarketBrief::build($this->overview()), 'breadth')['tone']);
        $this->assertStringContainsString('Bên bán áp đảo: 266 mã tăng, 423 mã giảm. 34 mã tăng trần, 35 mã giảm sàn.', $this->point(MarketBrief::build($this->overview()), 'breadth')['text']);

        $buy = MarketBrief::build($this->overview(['breadth' => ['advancers' => 500, 'decliners' => 200, 'ceiling' => 0, 'floor' => 0]]));
        $this->assertSame('up', $this->point($buy, 'breadth')['tone']);
        $this->assertSame('Bên mua chiếm ưu thế: 500 mã tăng, 200 mã giảm.', $this->point($buy, 'breadth')['text'], 'no ceiling/floor sentence when there are none');

        $even = MarketBrief::build($this->overview(['breadth' => ['advancers' => 300, 'decliners' => 330]]));
        $this->assertSame('flat', $this->point($even, 'breadth')['tone']);
        $this->assertStringStartsWith('Cung cầu cân bằng', $this->point($even, 'breadth')['text']);
    }

    #[Group('marketBrief')]
    public function test_sectors_name_the_strongest_and_the_weakest_among_the_big_ones(): void
    {
        $tiles = [
            ['A1', 'Ngân hàng', 100, -2.0], ['A2', 'Ngân hàng', 100, -1.0], ['A3', 'Ngân hàng', 100, -3.0],       // -2,00 weighted
            ['B1', 'Bất động sản', 100, 1.0], ['B2', 'Bất động sản', 100, 2.0], ['B3', 'Bất động sản', 100, 3.0], // +2,00
            ['C1', 'Thép', 100, 0.5], ['C2', 'Thép', 100, 0.5], ['C3', 'Thép', 100, 0.5],
        ];

        $p = $this->point(MarketBrief::build($this->overview(), $this->heatmap($tiles)), 'sector');

        $this->assertSame('Mạnh nhất: Bất động sản (+2,00%). Yếu nhất: Ngân hàng (-2,00%).', $p['text']);
    }

    #[Group('marketBrief')]
    public function test_the_sector_percentage_is_weighted_by_traded_value(): void
    {
        $tiles = [
            ['A1', 'Thép', 900, 1.0], ['A2', 'Thép', 50, -5.0], ['A3', 'Thép', 50, -5.0],                    // (900 - 250 - 250) / 1000 = +0,40
            ['B1', 'Dầu khí', 100, -1.0], ['B2', 'Dầu khí', 100, -1.0], ['B3', 'Dầu khí', 100, -1.0],
        ];

        $text = $this->point(MarketBrief::build($this->overview(), $this->heatmap($tiles)), 'sector')['text'];

        $this->assertStringContainsString('Thép (+0,40%)', $text);
    }

    #[Group('marketBrief')]
    public function test_small_or_unknown_industries_are_not_called_strongest_or_weakest(): void
    {
        $tiles = [
            ['A1', 'Ngân hàng', 1000, 1.0], ['A2', 'Ngân hàng', 1000, 1.0], ['A3', 'Ngân hàng', 1000, 1.0],
            ['B1', 'Thép', 1000, -1.0], ['B2', 'Thép', 1000, -1.0], ['B3', 'Thép', 1000, -1.0],
            ['C1', 'Chỉ hai mã', 1000, 9.0], ['C2', 'Chỉ hai mã', 1000, 9.0],                                     // fewer than 3 tiles
            ['D1', 'Khác', 1000, 8.0], ['D2', 'Khác', 1000, 8.0], ['D3', 'Khác', 1000, 8.0],                      // not an industry
            ['E1', 'Nhỏ', 1, -9.0], ['E2', 'Nhỏ', 1, -9.0], ['E3', 'Nhỏ', 1, -9.0],                               // under 2 % of the value
        ];

        $text = $this->point(MarketBrief::build($this->overview(), $this->heatmap($tiles)), 'sector')['text'];

        $this->assertSame('Mạnh nhất: Ngân hàng (+1,00%). Yếu nhất: Thép (-1,00%).', $text);
    }

    #[Group('marketBrief')]
    public function test_sector_wording_when_nothing_rose_or_nothing_fell(): void
    {
        $allDown = $this->heatmap([['A1', 'X', 100, -1.0], ['A2', 'X', 100, -1.0], ['A3', 'X', 100, -1.0], ['B1', 'Y', 100, -2.0], ['B2', 'Y', 100, -2.0], ['B3', 'Y', 100, -2.0]]);
        $allUp = $this->heatmap([['A1', 'X', 100, 1.0], ['A2', 'X', 100, 1.0], ['A3', 'X', 100, 1.0], ['B1', 'Y', 100, 2.0], ['B2', 'Y', 100, 2.0], ['B3', 'Y', 100, 2.0]]);

        $this->assertSame('Chưa có ngành lớn nào tăng điểm; yếu nhất: Y (-2,00%).', $this->point(MarketBrief::build($this->overview(), $allDown), 'sector')['text']);
        $this->assertSame('Các ngành lớn đều tăng; mạnh nhất: Y (+2,00%).', $this->point(MarketBrief::build($this->overview(), $allUp), 'sector')['text']);
    }

    #[Group('marketBrief')]
    public function test_without_a_heat_map_or_with_one_industry_there_is_no_sector_or_concentration_card(): void
    {
        $none = MarketBrief::build($this->overview());
        $this->assertNull($this->point($none, 'sector'));
        $this->assertNull($this->point($none, 'concentration'));

        $one = MarketBrief::build($this->overview(), $this->heatmap([['A1', 'X', 100, 1.0], ['A2', 'X', 100, 1.0], ['A3', 'X', 100, 1.0]]));
        $this->assertNull($this->point($one, 'sector'));
    }

    #[Group('marketBrief')]
    public function test_the_other_indices_are_listed_with_signs(): void
    {
        $p = $this->point(MarketBrief::build($this->overview()), 'indices');

        $this->assertSame('VN30 -0,77% · HNX-Index -0,71%.', $p['text']);
        $this->assertSame('down', $p['tone']);

        $this->assertNull($this->point(MarketBrief::build(['indices' => [['code' => 'VNINDEX', 'close' => 1, 'change' => 1, 'percent' => 1]]]), 'indices'));
    }

    #[Group('marketBrief')]
    public function test_movers_are_named_from_the_all_exchanges_board(): void
    {
        $p = $this->point(MarketBrief::build($this->overview()), 'movers');

        $this->assertSame('Tăng mạnh nhất: NVB +9,49%; giảm sâu nhất: PNJ -6,87% (mã có giá trị giao dịch từ 5 tỷ).', $p['text']);

        $onlyUp = $this->overview();
        $onlyUp['movers']['ALL']['losers'] = [];
        $this->assertSame('Tăng mạnh nhất: NVB +9,49% (mã có giá trị giao dịch từ 5 tỷ).', $this->point(MarketBrief::build($onlyUp), 'movers')['text']);

        $none = $this->overview();
        $none['movers'] = ['ALL' => ['gainers' => [], 'losers' => []]];
        $this->assertNull($this->point(MarketBrief::build($none), 'movers'));
    }

    #[Group('marketBrief')]
    public function test_concentration_is_the_share_of_the_five_busiest_stocks_in_the_whole_market_value(): void
    {
        $tiles = [['VIC', 'X', 4_000_000_000_000, 0.0], ['FPT', 'X', 3_000_000_000_000, 0.0], ['VCB', 'X', 2_000_000_000_000, 0.0], ['HPG', 'X', 1_000_000_000_000, 0.0], ['MSN', 'X', 500_000_000_000, 0.0], ['ZZZ', 'X', 10_000_000_000, 0.0]];

        $p = $this->point(MarketBrief::build($this->overview(['liquidity' => ['value' => 20_000_000_000_000]]), $this->heatmap($tiles)), 'concentration');

        $this->assertSame('5 mã giao dịch lớn nhất (VIC, FPT, VCB, HPG, MSN) chiếm 53% giá trị khớp lệnh.', $p['text']);   // 10.5 / 20
    }

    #[Group('marketBrief')]
    public function test_concentration_is_left_out_rather_than_wrong_when_the_inputs_disagree(): void
    {
        $tiles = array_map(fn ($s) => [$s, 'X', 9_000_000_000_000, 0.0], ['A', 'B', 'C', 'D', 'E']);

        $over = MarketBrief::build($this->overview(['liquidity' => ['value' => 10_000_000_000_000]]), $this->heatmap($tiles));   // 45 000 bn of 10 000 bn: tiles from another session
        $this->assertNull($this->point($over, 'concentration'));

        $few = MarketBrief::build($this->overview(), $this->heatmap(array_slice($tiles, 0, 4)));
        $this->assertNull($this->point($few, 'concentration'));

        $noValue = MarketBrief::build($this->overview(['liquidity' => ['value' => 0]]), $this->heatmap($tiles));
        $this->assertNull($this->point($noValue, 'concentration'));
    }

    #[Group('marketBrief')]
    public function test_every_point_has_the_fields_the_page_renders(): void
    {
        $tiles = [['A1', 'X', 100, 1.0], ['A2', 'X', 100, 1.0], ['A3', 'X', 100, 1.0], ['B1', 'Y', 100, -2.0], ['B2', 'Y', 100, -2.0], ['B3', 'Y', 100, -2.0]];

        foreach (MarketBrief::build($this->overview(), $this->heatmap($tiles))['points'] as $p) {
            $this->assertSame(['key', 'icon', 'title', 'tone', 'text'], array_keys($p));
            $this->assertContains($p['tone'], ['up', 'down', 'flat']);
            $this->assertNotSame('', $p['text']);
        }
    }

    #[Group('marketBrief')]
    public function test_the_text_never_contains_a_number_that_was_not_in_the_input(): void
    {
        // a stripped-down market: only the VN-Index. Nothing may be invented to fill the card.
        $b = MarketBrief::build(['indices' => [['code' => 'VNINDEX', 'close' => 1500.0, 'change' => 3.0, 'percent' => 0.2]]]);

        $this->assertSame([], $b['points']);
        $this->assertSame('VN-Index nhích lên 3,00 điểm (0,20%) về 1.500,00.', $b['headline']);
    }
}
