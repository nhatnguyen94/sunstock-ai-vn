<?php

namespace Tests\Unit\Support;

use App\Support\MarketSentiment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class MarketSentimentTest extends TestCase
{
    private function market(array $over = []): array
    {
        return array_replace([
            'breadth' => ['advancers' => 300, 'decliners' => 300, 'ceiling' => 10, 'floor' => 10],
            'indices' => [['code' => 'VNINDEX', 'percent' => 0.0]],
            'foreign' => ['buy_value' => 1_000, 'sell_value' => 1_000],
        ], $over);
    }

    private function part(array $result, string $key): ?array
    {
        return collect($result['components'])->firstWhere('key', $key);
    }

    #[Group('marketSentiment')]
    public function test_a_balanced_market_reads_neutral(): void
    {
        $r = MarketSentiment::compute($this->market());

        $this->assertSame(50, $r['score']);
        $this->assertSame('Trung lập', $r['label']);
        $this->assertSame('flat', $r['tone']);
    }

    #[Group('marketSentiment')]
    public function test_a_broad_rally_reads_greedy_and_a_broad_sell_off_fearful(): void
    {
        $up = MarketSentiment::compute($this->market([
            'breadth' => ['advancers' => 700, 'decliners' => 100, 'ceiling' => 60, 'floor' => 2],
            'indices' => [['code' => 'VNINDEX', 'percent' => 2.0]],
            'foreign' => ['buy_value' => 3_000, 'sell_value' => 1_000],
        ]));
        $down = MarketSentiment::compute($this->market([
            'breadth' => ['advancers' => 80, 'decliners' => 700, 'ceiling' => 1, 'floor' => 80],
            'indices' => [['code' => 'VNINDEX', 'percent' => -2.5]],
            'foreign' => ['buy_value' => 500, 'sell_value' => 2_500],
        ]));

        $this->assertGreaterThanOrEqual(80, $up['score']);
        $this->assertSame('Tham lam cực độ', $up['label']);
        $this->assertSame('up', $up['tone']);
        $this->assertLessThanOrEqual(20, $down['score']);
        $this->assertSame('Sợ hãi cực độ', $down['label']);
        $this->assertSame('down', $down['tone']);
    }

    #[Group('marketSentiment')]
    public function test_the_score_is_always_between_0_and_100(): void
    {
        $extreme = MarketSentiment::compute($this->market([
            'breadth' => ['advancers' => 1, 'decliners' => 9_999, 'ceiling' => 0, 'floor' => 500],
            'indices' => [['code' => 'VNINDEX', 'percent' => -50.0]],
            'foreign' => ['buy_value' => 1, 'sell_value' => 1_000_000],
        ]));

        $this->assertGreaterThanOrEqual(0, $extreme['score']);
        $this->assertLessThanOrEqual(100, $extreme['score']);
        foreach ($extreme['components'] as $c) {
            $this->assertGreaterThanOrEqual(0, $c['score']);
            $this->assertLessThanOrEqual(100, $c['score']);
        }
    }

    #[Group('marketSentiment')]
    public function test_the_bands_have_exact_boundaries(): void
    {
        $cases = [[0, 'Sợ hãi cực độ'], [19, 'Sợ hãi cực độ'], [20, 'Sợ hãi'], [39, 'Sợ hãi'], [40, 'Trung lập'], [59, 'Trung lập'], [60, 'Tham lam'], [79, 'Tham lam'], [80, 'Tham lam cực độ'], [100, 'Tham lam cực độ']];
        foreach ($cases as [$score, $label]) {
            $this->assertSame($label, MarketSentiment::band($score)[0], (string) $score);
        }
    }

    #[Group('marketSentiment')]
    public function test_weights_add_up_and_each_component_reports_its_share_and_a_readable_detail(): void
    {
        $this->assertEqualsWithDelta(1.0, array_sum(MarketSentiment::WEIGHTS), 1e-9);

        $r = MarketSentiment::compute($this->market(['breadth' => ['advancers' => 322, 'decliners' => 352, 'ceiling' => 34, 'floor' => 20]]));

        $this->assertSame(['breadth', 'momentum', 'limits', 'foreign'], array_column($r['components'], 'key'));
        $this->assertSame(100, array_sum(array_column($r['components'], 'weight')));
        $this->assertSame('322 mã tăng / 352 mã giảm', $this->part($r, 'breadth')['detail']);
        $this->assertSame('34 mã trần / 20 mã sàn', $this->part($r, 'limits')['detail']);
        $this->assertSame('VN-Index 0,00%', $this->part($r, 'momentum')['detail']);
    }

    #[Group('marketSentiment')]
    public function test_a_component_without_data_is_left_out_and_the_others_are_rescaled(): void
    {
        $r = MarketSentiment::compute($this->market(['foreign' => null, 'breadth' => ['advancers' => 300, 'decliners' => 300, 'ceiling' => 1, 'floor' => 2]]));

        $this->assertSame(['breadth', 'momentum'], array_column($r['components'], 'key'), 'too few limit-stocks and no foreign data');
        $this->assertSame(100, array_sum(array_column($r['components'], 'weight')));
        $this->assertSame(54, $this->part($r, 'breadth')['weight'], '0,35 of 0,65 re-scaled');
    }

    #[Group('marketSentiment')]
    public function test_fewer_than_two_components_gives_no_reading(): void
    {
        $this->assertNull(MarketSentiment::compute([]));
        $this->assertNull(MarketSentiment::compute(['indices' => [['code' => 'VNINDEX', 'percent' => 1.0]]]));
        $this->assertNotNull(MarketSentiment::compute(['indices' => [['code' => 'VNINDEX', 'percent' => 1.0]], 'breadth' => ['advancers' => 5, 'decliners' => 3]]));
    }

    #[Group('marketSentiment')]
    public function test_the_foreign_component_is_a_clearly_labelled_estimate_and_follows_the_net_flow(): void
    {
        $buying = $this->part(MarketSentiment::compute($this->market(['foreign' => ['buy_value' => 3_000_000_000_000, 'sell_value' => 1_000_000_000_000]])), 'foreign');
        $selling = $this->part(MarketSentiment::compute($this->market(['foreign' => ['buy_value' => 1_000_000_000_000, 'sell_value' => 3_000_000_000_000]])), 'foreign');

        $this->assertGreaterThan(50, $buying['score']);
        $this->assertLessThan(50, $selling['score']);
        $this->assertStringContainsString('ước tính', $buying['detail']);
        $this->assertStringContainsString('2 nghìn tỷ', str_replace(',0', '', $buying['detail']));
    }

    #[Group('marketSentiment')]
    public function test_the_note_says_it_is_our_own_indicator_and_not_advice(): void
    {
        $note = MarketSentiment::compute($this->market())['note'];

        $this->assertStringContainsString('tự tính', $note);
        $this->assertStringContainsString('không phải chỉ số chính thức', $note);
        $this->assertStringContainsString('không phải khuyến nghị', $note);
    }

    #[Group('marketSentiment')]
    public function test_a_calm_day_with_a_few_ceilings_does_not_swing_the_reading_on_noise(): void
    {
        $calm = MarketSentiment::compute($this->market(['breadth' => ['advancers' => 300, 'decliners' => 300, 'ceiling' => 3, 'floor' => 0]]));

        $this->assertNull($this->part($calm, 'limits'), 'fewer than 5 limit-stocks is noise');
    }
}
