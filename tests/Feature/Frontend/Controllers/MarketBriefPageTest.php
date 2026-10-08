<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use App\Models\MarketSnapshot;
use App\Models\StockSymbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/** "Bản tin phiên" on the home page and in the poll: real routes and DB, the snapshot seeded (no Python). */
class MarketBriefPageTest extends TestCase
{
    use BuildsMarketPayload, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();
        HotIndustry::create(['symbol' => 'VCB', 'organ_name' => 'Vietcombank', 'icb_name3' => 'Ngân hàng']);
        ExchangeRate::create(['currency_code' => 'USD', 'currency_name' => 'US DOLLAR', 'buy_cash' => '25000', 'buy_transfer' => '25050', 'sell' => '25400', 'date' => now()->format('Y-m-d')]);
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT Corp', 'exchange' => 'HSX', 'industry' => 'Công nghệ và thông tin']);
        $this->seedMarketSnapshot();
    }

    #[Group('marketBrief')]
    public function test_home_shows_the_brief_between_the_index_cards_and_the_heat_map_or_chart(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="mkBrief"', $html);
        $this->assertStringContainsString('Bản tin phiên', $html);
        $this->assertStringContainsString('VN-Index nhích xuống 7,11 điểm (0,39%) về 1.815,66.', $html);
        $this->assertStringContainsString('data-key="breadth"', $html);

        $indices = strpos($html, 'class="mk-indices"');
        $brief = strpos($html, 'id="mkBrief"');
        $chart = strpos($html, 'id="mkChart"');
        $this->assertTrue($indices < $brief && $brief < $chart);
    }

    #[Group('marketBrief')]
    public function test_the_ask_button_carries_a_question_built_from_the_data(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="mkBriefAsk"', $html);
        $this->assertStringContainsString('data-ask="Vì sao VN-Index giảm 0,39% trong phiên gần nhất?', $html);
    }

    #[Group('marketBrief')]
    public function test_the_poll_carries_the_brief_with_the_same_wording(): void
    {
        $page = $this->get('/')->assertOk()->getContent();
        $json = $this->getJson('/market/data')->assertOk()->json();

        $this->assertSame('down', $json['brief']['tone']);
        $this->assertStringContainsString($json['brief']['headline'], html_entity_decode($page, ENT_QUOTES));
        $this->assertSame(['key', 'icon', 'title', 'tone', 'text'], array_keys($json['brief']['points'][0]));
    }

    #[Group('marketBrief')]
    public function test_without_market_data_there_is_no_brief_and_the_page_still_renders(): void
    {
        MarketSnapshot::query()->delete();
        Cache::flush();
        $this->seedMarketSnapshot(['indices' => []]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="mkBrief"', $html);
    }

    #[Group('marketBrief')]
    public function test_hostile_industry_or_symbol_names_are_escaped_in_the_brief(): void
    {
        $evil = '<img src=x onerror=alert(1)>';
        foreach (['EV1', 'EV2', 'EV3'] as $s) {
            StockSymbol::create(['symbol' => $s, 'name' => $s, 'exchange' => 'HSX', 'industry' => $evil]);
        }
        foreach (['GD1', 'GD2', 'GD3'] as $s) {
            StockSymbol::create(['symbol' => $s, 'name' => $s, 'exchange' => 'HSX', 'industry' => 'Ngân hàng']);
        }
        $q = fn (float $pct) => [10000, 10000, $pct, 1_000_000, 900_000_000_000, null, null, null, null, null, 'HOSE'];
        MarketSnapshot::query()->delete();
        Cache::flush();
        $this->seedMarketSnapshot(['quotes' => ['EV1' => $q(3.0), 'EV2' => $q(3.0), 'EV3' => $q(3.0), 'GD1' => $q(-2.0), 'GD2' => $q(-2.0), 'GD3' => $q(-2.0)]]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
    }

    #[Group('marketBrief')]
    public function test_asking_the_ai_opens_the_chat_after_the_click_has_finished(): void
    {
        $chat = file_get_contents(resource_path('frontend/js/shared/ai-chat.js'));
        $palette = file_get_contents(resource_path('frontend/js/shared/palette.js'));
        $home = file_get_contents(resource_path('frontend/js/market/home.js'));

        // the chat closes itself on any click outside its bubble: opening inside the same click would be undone at once
        $this->assertMatchesRegularExpression('/export function askAi.*setTimeout\(\(\) => \{\s*if \(open\.getAttribute\(\'aria-expanded\'\)/s', $chat);
        $this->assertStringContainsString("import { askAi } from './ai-chat.js'", $palette);
        $this->assertStringNotContainsString('function openAi', $palette, 'one implementation, not two');
        $this->assertStringContainsString('askAi(e.currentTarget.dataset.ask, { send: true })', $home);
        $this->assertStringContainsString('textContent', $home);
    }
}
