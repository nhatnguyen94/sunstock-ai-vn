<?php

namespace Tests\Unit\Frontend\Services;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Frontend\Services\ExchangeRateService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for ExchangeRateService::parsePythonOutput() — no Laravel
 * app booted, no real Python process invoked. See tests/README (docs/TESTING.md).
 *
 * Regression coverage for the bug where the exchange-rate page always showed
 * "no data": the code used to implode() every stdout line from the Python
 * script (including vnstock's promo banner / version-notice lines printed
 * before the JSON) into one string and feed that straight to json_decode(),
 * which is never valid JSON — so parsing silently failed on every call.
 */
class ExchangeRateServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function service(): ExchangeRateService
    {
        return new ExchangeRateService(\Mockery::mock(ExchangeRateRepositoryInterface::class));
    }

    #[Group('exchangeRate')]
    public function test_parses_multi_day_json_even_with_banner_noise_before_it(): void
    {
        $output = [
            '📦 Vnstock 4.0.7 is available',
            'Current: 4.0.4 (Python 3.13 (venv))',
            '┃  🚀 VNSTOCK INSIDERS PROGRAM - NÂNG TẦM TRẢI NGHIỆM CỦA BẠN! 🚀  ┃',
            '┃  🔗 Tham gia ngay: https://vnstocks.com/insiders-program.          ┃',
            '[{"date":"2026-09-14","rates":[{"currency_code":"USD","sell":"26,180.00"}]},{"date":"2026-09-13","rates":[{"currency_code":"USD","sell":"26,110.00"}]}]',
        ];

        $result = $this->service()->parsePythonOutput($output, 3);

        $this->assertSame(['2026-09-14', '2026-09-13'], array_keys($result));
        $this->assertSame('USD', $result['2026-09-14'][0]['currency_code']);
    }

    #[Group('exchangeRate')]
    public function test_parses_single_date_json_when_daysordate_is_a_date_string(): void
    {
        $output = [
            '📦 Vnstock 4.0.7 is available',
            '[{"currency_code":"USD","currency_name":"US DOLLAR","sell":"26,180.00","date":"2026-09-10"}]',
        ];

        $result = $this->service()->parsePythonOutput($output, '2026-09-10');

        $this->assertSame(['2026-09-10'], array_keys($result));
        $this->assertSame('USD', $result['2026-09-10'][0]['currency_code']);
    }

    #[Group('exchangeRate')]
    public function test_returns_empty_array_when_output_is_only_banner_noise(): void
    {
        // The exact failure mode of the original bug: no JSON line present at all
        // (e.g. the script crashed before printing, or VCB had no data for the date).
        $output = [
            '📦 Vnstock 4.0.7 is available',
            'Current: 4.0.4 (Python 3.13 (venv))',
            'Release: https://vnstocks.com/docs/tai-lieu/lich-su-phien-ban',
        ];

        $this->assertSame([], $this->service()->parsePythonOutput($output, '2026-09-10'));
    }

    #[Group('exchangeRate')]
    public function test_returns_empty_array_for_empty_output(): void
    {
        $this->assertSame([], $this->service()->parsePythonOutput([], 3));
    }

    #[Group('exchangeRate')]
    public function test_returns_empty_array_for_malformed_json_on_the_last_line(): void
    {
        $output = ['📦 banner', '{not valid json'];

        $this->assertSame([], $this->service()->parsePythonOutput($output, 3));
    }

    #[Group('exchangeRate')]
    public function test_fetch_rates_from_python_rejects_invalid_input_without_running_a_process(): void
    {
        // Neither numeric (days) nor a Y-m-d date string — must short-circuit to [].
        $this->assertSame([], $this->service()->fetchRatesFromPython('not-a-valid-input; rm -rf /'));
    }
}
