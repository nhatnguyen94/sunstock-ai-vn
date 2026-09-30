<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\EtfRepositoryInterface;
use App\Frontend\Interfaces\PortfolioTransactionRepositoryInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Jobs\RefreshStockPricesJob;
use App\Models\Portfolio;
use App\Models\StockSymbol;
use App\Support\EtfMeta;
use App\Support\PortfolioHistory;
use App\Support\PortfolioPerformance;
use App\Support\PortfolioRisk;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * What the portfolio page shows beyond the plain holdings: a ledger-accurate value line, risk figures, the
 * comparison with the market, the split by sector, and suggestions that understand ETFs. Takes the analytics array
 * {@see PortfolioService::getPortfolioAnalytics()} already built and returns it enriched — the page keeps working
 * unchanged when a piece of this has no data.
 */
class PortfolioInsightsService
{
    /** Benchmark symbol => label. VNINDEX is the market; E1VFVN30 is the VN30 ETF you could actually have bought. */
    public const BENCHMARKS = ['VNINDEX' => 'VN-Index', 'E1VFVN30' => 'ETF VN30 (E1VFVN30)'];

    /** The market used for beta. */
    private const MARKET = 'VNINDEX';

    private const CACHE_TTL = 1800;

    /** A sector (or one non-ETF holding) above this share of the portfolio is worth a warning. */
    private const SECTOR_LIMIT = 50;
    private const POSITION_LIMIT = 30;
    private const POSITION_TARGET = 25;

    public function __construct(
        private readonly StockRepositoryInterface $stocks,
        private readonly PortfolioTransactionRepositoryInterface $transactions,
        private readonly EtfRepositoryInterface $etfs
    ) {}

    /**
     * @param  array<string, mixed> $analytics the array built by PortfolioService::getPortfolioAnalytics()
     * @return array<string, mixed>
     */
    public function enhance(array $analytics): array
    {
        /** @var Portfolio $portfolio */
        $portfolio = $analytics['portfolio'];
        $etfNames = $this->etfs->all()->keyBy('symbol');

        $analytics['holdings'] = $this->decorateHoldings($analytics['holdings'], $etfNames);
        $analytics['sectors'] = $this->sectors($analytics['holdings']);
        $analytics['suggestions'] = $this->suggestions($analytics['holdings'], $analytics['sectors'], $etfNames);

        $insights = $this->marketInsights($portfolio);
        if ($insights !== null) {
            // Replace the old reconstruction (current holdings only) with the ledger replay, ending on today's real totals
            $analytics['performance'] = $this->finishSeries($insights['chart'], $portfolio);
            $analytics['risk'] = $insights['risk'];
            $analytics['benchmarks'] = $this->compare($insights['benchmarks'], $insights['risk'], $portfolio);
        } else {
            $analytics['risk'] = null;
            $analytics['benchmarks'] = [];
        }

        return $analytics;
    }

    /**
     * Put the portfolio next to each benchmark: return difference in percentage points (time-weighted, so deposits
     * and withdrawals do not flatter or hurt it) and what the same money would be worth in the benchmark today.
     *
     * @param  array<string, array<string, mixed>> $benchmarks
     * @return array<string, array<string, mixed>>
     */
    private function compare(array $benchmarks, array $risk, Portfolio $portfolio): array
    {
        $value = (float) $portfolio->current_value;

        foreach ($benchmarks as $symbol => &$b) {
            $b['points_diff'] = ($risk['total_return'] !== null && $b['return'] !== null) ? round($risk['total_return'] - $b['return'], 2) : null;
            $b['value_diff'] = $b['final_value'] !== null ? round($value - $b['final_value']) : null;
        }
        unset($b);

        return $benchmarks;
    }

    // ── Holdings ────────────────────────────────────────────────────────────

    /**
     * ETFs have no name in the symbol list, so their rows showed the ticker twice. Names come from the ETF roster;
     * every row also gets its sector.
     *
     * @param  array<int, array<string, mixed>> $holdings
     * @return array<int, array<string, mixed>>
     */
    private function decorateHoldings(array $holdings, $etfNames): array
    {
        $industry = StockSymbol::whereIn('symbol', array_column($holdings, 'symbol'))->pluck('industry', 'symbol');

        return array_map(function (array $h) use ($etfNames, $industry) {
            $etf = $etfNames->get($h['symbol']);
            if ($etf && ($h['name'] === null || $h['name'] === $h['symbol'])) {
                $h['name'] = EtfMeta::shortName($etf->name) ?? $h['name'];
            }
            $h['is_etf'] = (bool) $etf;
            $h['sector'] = $etf ? 'Quỹ ETF / quỹ niêm yết' : (($industry[$h['symbol']] ?? null) ?: 'Chưa phân ngành');

            return $h;
        }, $holdings);
    }

    /**
     * @param  array<int, array<string, mixed>> $holdings
     * @return array<int, array{name: string, value: float, percent: float, symbols: string[]}> largest first
     */
    private function sectors(array $holdings): array
    {
        $total = array_sum(array_column($holdings, 'value'));
        if ($total <= 0) {
            return [];
        }

        $groups = [];
        foreach ($holdings as $h) {
            $g = &$groups[$h['sector']];
            $g['name'] = $h['sector'];
            $g['value'] = ($g['value'] ?? 0) + $h['value'];
            $g['symbols'][] = $h['symbol'];
            unset($g);
        }
        foreach ($groups as &$g) {
            $g['percent'] = round($g['value'] / $total * 100, 1);
        }
        unset($g);

        usort($groups, fn ($a, $b) => $b['value'] <=> $a['value']);

        return array_values($groups);
    }

    /**
     * Suggestions that do not punish an ETF for being one holding (it is already a basket), but notice when several
     * ETFs track the same index, and when one sector dominates.
     *
     * @return array<int, array<string, mixed>>
     */
    private function suggestions(array $holdings, array $sectors, $etfNames): array
    {
        $out = [];

        foreach ($holdings as $h) {
            if (! $h['is_etf'] && $h['weight'] > self::POSITION_LIMIT) {
                $out[] = [
                    'type' => 'reduce', 'symbol' => $h['symbol'], 'current_percent' => round($h['weight'], 1), 'suggested_percent' => self::POSITION_TARGET,
                    'title' => "{$h['symbol']} chiếm " . number_format($h['weight'], 1, ',', '.') . '% danh mục',
                    'reason' => 'Một mã chiếm tỷ trọng quá cao, nên giảm để đa dạng hóa rủi ro (gợi ý dưới ' . self::POSITION_TARGET . '%).',
                ];
            }
        }

        foreach ($sectors as $s) {
            if ($s['percent'] > self::SECTOR_LIMIT && $s['name'] !== 'Quỹ ETF / quỹ niêm yết' && $s['name'] !== 'Chưa phân ngành') {
                $out[] = [
                    'type' => 'sector', 'symbol' => $s['name'], 'current_percent' => $s['percent'], 'suggested_percent' => null,
                    'title' => "Ngành {$s['name']} chiếm " . number_format($s['percent'], 1, ',', '.') . '% danh mục',
                    'reason' => 'Các mã cùng ngành thường tăng giảm cùng nhau; nên có thêm mã ở ngành khác để giảm rủi ro.',
                ];
            }
        }

        $byIndex = [];
        foreach ($holdings as $h) {
            $index = $h['is_etf'] ? ($etfNames->get($h['symbol'])?->tracked_index) : null;
            if ($index) {
                $byIndex[$index][] = $h['symbol'];
            }
        }
        foreach ($byIndex as $index => $symbols) {
            if (count($symbols) >= 2) {
                $out[] = [
                    'type' => 'overlap', 'symbol' => implode(', ', $symbols), 'current_percent' => null, 'suggested_percent' => null,
                    'title' => count($symbols) . " quỹ ETF cùng bám chỉ số {$index}",
                    'reason' => implode(', ', $symbols) . ' có danh mục gần như giống nhau; giữ nhiều quỹ cùng chỉ số không giúp đa dạng hóa, chỉ thêm phí. Cân nhắc gộp lại vào quỹ thanh khoản tốt nhất.',
                ];
            }
        }

        return $out;
    }

    // ── History, risk, benchmarks ───────────────────────────────────────────

    /**
     * @return array{chart: array<int, array<string, mixed>>, risk: array<string, mixed>, benchmarks: array<string, array<string, mixed>>}|null
     *         null when the ledger is empty (nothing to replay)
     */
    private function marketInsights(Portfolio $portfolio): ?array
    {
        $txs = $this->transactions->forPortfolio($portfolio->id, 5000);
        if ($txs->isEmpty()) {
            return null;
        }

        $signature = md5($txs->count() . ':' . $txs->max('id') . ':' . $txs->max('updated_at'));

        return Cache::remember("portfolio-insights:{$portfolio->id}:{$signature}:" . now()->format('YmdH'), self::CACHE_TTL, function () use ($txs, $portfolio) {
            $history = $txs->reverse()->values()->map(fn ($t) => [
                'date' => $t->traded_at->toDateString(), 'type' => $t->type, 'symbol' => $t->stock_symbol,
                'quantity' => $t->quantity, 'price' => $t->price, 'fee' => $t->fee,
            ])->all();

            $symbols = array_values(array_unique(array_column($history, 'symbol')));
            $from = min(array_column($history, 'date'));
            // A few days before the first trade: a benchmark return needs the close on or BEFORE the start date
            // (the first trade can be a weekend), and PortfolioHistory ignores closes earlier than the first trade anyway.
            $closes = $this->stocks->getCloseHistory(array_merge($symbols, array_keys(self::BENCHMARKS)), date('Y-m-d', strtotime($from . ' -10 days')));

            $series = PortfolioHistory::series($history, $closes);
            if (count($series) < 2) {
                return null;
            }

            $returns = PortfolioRisk::dailyReturns($series);
            $risk = PortfolioRisk::stats($returns) + ['beta' => PortfolioRisk::beta($returns, PortfolioRisk::priceReturns($closes[self::MARKET] ?? []))];

            $benchmarks = [];
            foreach (self::BENCHMARKS as $symbol => $label) {
                $sim = PortfolioRisk::simulate($series, $closes[$symbol] ?? []);
                if ($sim['series'] === []) {
                    continue;
                }
                if (! $sim['covered']) {
                    $this->queueBackfill($symbol, $from);
                }
                $first = $series[0]['date'];
                $last = end($series)['date'];
                $benchReturn = PortfolioRisk::priceReturnBetween($closes[$symbol] ?? [], $first, $last);

                $benchmarks[$symbol] = [
                    'label' => $label,
                    'covered' => $sim['covered'],
                    'series' => PortfolioPerformance::thin($sim['series']),
                    'final_value' => $sim['final_value'],
                    'return' => $benchReturn,
                    'history_from' => array_key_first($closes[$symbol] ?? []),
                ];
            }

            return ['chart' => PortfolioHistory::forChart($series), 'risk' => $risk, 'benchmarks' => $benchmarks];
        });
    }

    /** One background backfill a day per benchmark whose stored history starts after the portfolio's first trade. */
    private function queueBackfill(string $symbol, string $from): void
    {
        try {
            if (Cache::add("pf-benchmark-backfill:{$symbol}", 1, 86400)) {
                RefreshStockPricesJob::dispatch([$symbol], date('Y-m-d', strtotime($from . ' -10 days')));
            }
        } catch (\Throwable $e) {
            Log::warning('PortfolioInsights: benchmark backfill not queued', ['symbol' => $symbol, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Finish the chart on today's real totals, like the page's KPI cards (holdings priced after the last synced
     * close still show up instead of the line ending short).
     *
     * @param  array<int, array<string, mixed>> $chart
     * @return array<int, array<string, mixed>>
     */
    private function finishSeries(array $chart, Portfolio $portfolio): array
    {
        $current = ['date' => end($chart)['date'], 'value' => round((float) $portfolio->current_value, 2), 'invested' => round((float) $portfolio->total_invested, 2)];
        $chart[count($chart) - 1] = $current;

        return $chart;
    }
}
