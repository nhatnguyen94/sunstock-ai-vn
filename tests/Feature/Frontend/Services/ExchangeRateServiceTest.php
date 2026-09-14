<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Frontend\Services\ExchangeRateService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * "Feature" only because getLatestRates()/getRatesByDate() use the Cache
 * facade (booted app required); phpunit.xml sets CACHE_STORE=array so this
 * is pure in-memory, no Redis/DB involved. Repository is mocked, and
 * fetchRatesFromPython() is stubbed via a partial mock so no real Python
 * process runs — see tests/Unit/.../ExchangeRateServiceTest.php for the
 * parsing-logic tests that don't need any of that.
 */
class ExchangeRateServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Group('exchangeRate')]
    public function test_get_latest_rates_returns_db_data_without_calling_python(): void
    {
        $repo = \Mockery::mock(ExchangeRateRepositoryInterface::class);
        $dbData = ['2026-09-14' => [['currency_code' => 'USD', 'sell' => '26,180.00']]];
        $repo->shouldReceive('getLatestRates')->once()->with(3)->andReturn($dbData);
        $repo->shouldNotReceive('saveRate');

        /** @var ExchangeRateService $service */
        $service = \Mockery::mock(ExchangeRateService::class, [$repo])->makePartial();
        $service->shouldNotReceive('fetchRatesFromPython');

        $this->assertSame($dbData, $service->getLatestRates(3));
    }

    #[Group('exchangeRate')]
    public function test_get_latest_rates_falls_back_to_python_and_persists_when_db_is_empty(): void
    {
        $repo = \Mockery::mock(ExchangeRateRepositoryInterface::class);
        $repo->shouldReceive('getLatestRates')->once()->with(3)->andReturn([]);

        $pythonData = [
            '2026-09-14' => [
                ['currency_code' => 'USD', 'sell' => '26,180.00', 'date' => '2026-09-14'],
                ['currency_code' => 'EUR', 'sell' => '30,800.59', 'date' => '2026-09-14'],
            ],
        ];
        $repo->shouldReceive('saveRate')->twice(); // one per item across all dates

        /** @var ExchangeRateService $service */
        $service = \Mockery::mock(ExchangeRateService::class, [$repo])->makePartial();
        $service->shouldReceive('fetchRatesFromPython')->once()->with(3)->andReturn($pythonData);

        $this->assertSame($pythonData, $service->getLatestRates(3));
    }

    #[Group('exchangeRate')]
    public function test_get_rates_by_date_returns_db_data_without_calling_python(): void
    {
        $repo = \Mockery::mock(ExchangeRateRepositoryInterface::class);
        $dbData = [['currency_code' => 'USD', 'sell' => '26,180.00']];
        $repo->shouldReceive('getRatesByDate')->once()->with('2026-09-10')->andReturn($dbData);
        $repo->shouldNotReceive('saveRate');

        /** @var ExchangeRateService $service */
        $service = \Mockery::mock(ExchangeRateService::class, [$repo])->makePartial();
        $service->shouldNotReceive('fetchRatesFromPython');

        $this->assertSame($dbData, $service->getRatesByDate('2026-09-10'));
    }

    #[Group('exchangeRate')]
    public function test_get_rates_by_date_falls_back_to_python_then_re_reads_from_db(): void
    {
        $repo = \Mockery::mock(ExchangeRateRepositoryInterface::class);
        // First DB check: empty (this is the bug's real-world starting state).
        $repo->shouldReceive('getRatesByDate')->once()->with('2026-09-10')->ordered()->andReturn([]);

        $pythonData = ['2026-09-10' => [['currency_code' => 'USD', 'sell' => '26,180.00', 'date' => '2026-09-10']]];
        $repo->shouldReceive('saveRate')->once();

        // After persisting, the service re-reads from DB "for certainty".
        $freshDbData = [['currency_code' => 'USD', 'sell' => '26,180.00', 'date' => '2026-09-10']];
        $repo->shouldReceive('getRatesByDate')->once()->with('2026-09-10')->ordered()->andReturn($freshDbData);

        /** @var ExchangeRateService $service */
        $service = \Mockery::mock(ExchangeRateService::class, [$repo])->makePartial();
        $service->shouldReceive('fetchRatesFromPython')->once()->with('2026-09-10')->andReturn($pythonData);

        $this->assertSame($freshDbData, $service->getRatesByDate('2026-09-10'));
    }
}
