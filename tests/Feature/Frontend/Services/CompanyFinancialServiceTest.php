<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Interfaces\CompanyFinancialRepositoryInterface;
use App\Frontend\Services\CompanyFinancialService;
use App\Models\CompanyFinancial;
use Illuminate\Database\Eloquent\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * "Feature" only because screenStocks() uses the Cache facade internally
 * (booted app required); phpunit.xml sets CACHE_STORE=array so this is
 * pure in-memory, no Redis/DB involved. Repository is mocked.
 */
class CompanyFinancialServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function ratioRecord(string $symbol, array $items): CompanyFinancial
    {
        $record = new CompanyFinancial([
            'symbol' => $symbol,
            'type' => 'ratio',
            'period' => 'year',
            'raw_data' => ['data' => $items],
            'synced_at' => now(),
        ]);

        return $record;
    }

    private function row(string $item, array $yearValues): array
    {
        return array_merge(['item' => $item, 'unit' => '', 'levels' => 1], $yearValues);
    }

    #[Group('stock-screener')]
    public function test_screen_stocks_extracts_the_latest_year_column(): void
    {
        $record = $this->ratioRecord('ACB', [
            $this->row('Chỉ số giá thị trường trên thu nhập (P/E)', [
                '2022-Năm' => 8.0,
                '2023-Năm' => 7.5,
                '2024-Năm' => 7.0,
                '2025-Năm' => 6.5,
            ]),
        ]);

        $repo = \Mockery::mock(CompanyFinancialRepositoryInterface::class);
        $repo->shouldReceive('getAllRatiosByPeriod')->once()->with('year')->andReturn(new Collection([$record]));

        $service = new CompanyFinancialService($repo);
        $rows = $service->screenStocks([]);

        $this->assertCount(1, $rows);
        $this->assertSame('ACB', $rows[0]['symbol']);
        $this->assertSame(6.5, $rows[0]['pe']); // must pick 2025, not an earlier year
    }

    #[Group('stock-screener')]
    public function test_screen_stocks_reads_roea_not_the_always_zero_quarterly_label(): void
    {
        // Regression test: vnstock's "ROE bình quân 4 quý gần nhất" is always 0
        // for the "year" period — the real annual figure is under "ROEA".
        $record = $this->ratioRecord('A32', [
            $this->row('Tỷ suất lợi nhuận trên vốn chủ sở hữu bình quân (ROEA)', ['2025-Năm' => 22.51]),
            $this->row('ROE bình quân 4 quý gần nhất', ['2025-Năm' => 0]),
        ]);

        $repo = \Mockery::mock(CompanyFinancialRepositoryInterface::class);
        $repo->shouldReceive('getAllRatiosByPeriod')->once()->andReturn(new Collection([$record]));

        $service = new CompanyFinancialService($repo);
        $rows = $service->screenStocks([]);

        $this->assertSame(22.51, $rows[0]['roe']);
    }

    #[Group('stock-screener')]
    public function test_screen_stocks_filters_by_pe_range(): void
    {
        $cheap = $this->ratioRecord('CHEAP', [$this->row('Chỉ số giá thị trường trên thu nhập (P/E)', ['2025-Năm' => 5.0])]);
        $expensive = $this->ratioRecord('EXP', [$this->row('Chỉ số giá thị trường trên thu nhập (P/E)', ['2025-Năm' => 40.0])]);

        $repo = \Mockery::mock(CompanyFinancialRepositoryInterface::class);
        $repo->shouldReceive('getAllRatiosByPeriod')->once()->andReturn(new Collection([$cheap, $expensive]));

        $service = new CompanyFinancialService($repo);
        $rows = $service->screenStocks(['pe_min' => '0', 'pe_max' => '20']);

        $this->assertCount(1, $rows);
        $this->assertSame('CHEAP', $rows[0]['symbol']);
    }

    #[Group('stock-screener')]
    public function test_screen_stocks_filters_by_roe_min(): void
    {
        $strong = $this->ratioRecord('STRONG', [$this->row('Tỷ suất lợi nhuận trên vốn chủ sở hữu bình quân (ROEA)', ['2025-Năm' => 25.0])]);
        $weak = $this->ratioRecord('WEAK', [$this->row('Tỷ suất lợi nhuận trên vốn chủ sở hữu bình quân (ROEA)', ['2025-Năm' => 3.0])]);

        $repo = \Mockery::mock(CompanyFinancialRepositoryInterface::class);
        $repo->shouldReceive('getAllRatiosByPeriod')->once()->andReturn(new Collection([$strong, $weak]));

        $service = new CompanyFinancialService($repo);
        $rows = $service->screenStocks(['roe_min' => '15']);

        $this->assertCount(1, $rows);
        $this->assertSame('STRONG', $rows[0]['symbol']);
    }

    #[Group('stock-screener')]
    public function test_screen_stocks_sorts_by_requested_column_and_direction(): void
    {
        $low = $this->ratioRecord('LOW', [$this->row('Chỉ số giá thị trường trên thu nhập (P/E)', ['2025-Năm' => 5.0])]);
        $mid = $this->ratioRecord('MID', [$this->row('Chỉ số giá thị trường trên thu nhập (P/E)', ['2025-Năm' => 10.0])]);
        $high = $this->ratioRecord('HIGH', [$this->row('Chỉ số giá thị trường trên thu nhập (P/E)', ['2025-Năm' => 20.0])]);

        $repo = \Mockery::mock(CompanyFinancialRepositoryInterface::class);
        $repo->shouldReceive('getAllRatiosByPeriod')->once()->andReturn(new Collection([$high, $low, $mid]));

        $service = new CompanyFinancialService($repo);
        $rows = $service->screenStocks(['sort' => 'pe', 'dir' => 'desc']);

        $this->assertSame(['HIGH', 'MID', 'LOW'], array_column($rows, 'symbol'));
    }

    #[Group('stock-screener')]
    public function test_screen_stocks_skips_records_with_no_recognized_metric(): void
    {
        $usable = $this->ratioRecord('OK', [$this->row('Chỉ số giá thị trường trên thu nhập (P/E)', ['2025-Năm' => 10.0])]);
        $unusable = $this->ratioRecord('EMPTY', [$this->row('Some unrelated metric vnstock might add later', ['2025-Năm' => 99.0])]);

        $repo = \Mockery::mock(CompanyFinancialRepositoryInterface::class);
        $repo->shouldReceive('getAllRatiosByPeriod')->once()->andReturn(new Collection([$usable, $unusable]));

        $service = new CompanyFinancialService($repo);
        $rows = $service->screenStocks([]);

        $this->assertCount(1, $rows);
        $this->assertSame('OK', $rows[0]['symbol']);
    }
}
