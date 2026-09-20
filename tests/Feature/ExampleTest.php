<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase, BuildsMarketPayload;

    /**
     * A basic test example.
     *
     * Seeds the couple of rows StockController::home() needs so it never
     * falls through to a real Python/vnstock subprocess call when the
     * migrated test DB is otherwise empty — that fallback exists for
     * production's first-run case, not for this test to depend on network
     * access. See docs/TESTING.md.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        HotIndustry::create(['symbol' => 'VCB', 'organ_name' => 'Vietcombank', 'icb_name3' => 'Ngân hàng']);
        ExchangeRate::create([
            'currency_code' => 'USD',
            'currency_name' => 'US DOLLAR',
            'buy_cash' => '25000',
            'buy_transfer' => '25050',
            'sell' => '25400',
            'date' => now()->format('Y-m-d'),
        ]);

        // Same reason for the market section: an empty snapshot table would make the first visit run the live script
        $this->seedMarketSnapshot();

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
