<?php

namespace App\Support;

/**
 * "Bản tin phiên": a few plain-Vietnamese sentences that tell how the session went, written from the numbers the home
 * page already has (indices, breadth, liquidity, movers and the heat-map tiles). Pure and deterministic: every figure in
 * the text comes from the input, nothing is estimated or invented, and it costs no AI call.
 *
 * Input is MarketOverviewService::overview() plus, optionally, MarketHeatmapService::heatmap().
 */
class MarketBrief
{
    /** An industry needs at least this many tiles and this share of the shown traded value to be called "strongest/weakest". */
    private const SECTOR_MIN_TILES = 3;

    private const SECTOR_MIN_SHARE = 0.02;

    /** How many of the busiest stocks make up the "concentration" figure. */
    private const TOP_N = 5;

    /**
     * @param  array<string, mixed>  $overview
     * @param  array<string, mixed>|null  $heatmap
     * @return array{headline: string, tone: string, points: array<int, array<string, string>>, ask: string}|null null without a VN-Index
     */
    public static function build(array $overview, ?array $heatmap = null): ?array
    {
        $indices = collect($overview['indices'] ?? [])->keyBy('code');
        $vn = $indices->get('VNINDEX');
        if (! is_array($vn) || ! isset($vn['close'], $vn['change'], $vn['percent'])) {
            return null;
        }

        $pct = (float) $vn['percent'];
        $tone = self::direction($pct);
        $breadth = $overview['breadth'] ?? [];
        $liquidity = $overview['liquidity'] ?? [];

        $points = array_values(array_filter([
            self::breadthPoint($breadth),
            self::sectorPoint($heatmap),
            self::indicesPoint($indices->except('VNINDEX')->all()),
            self::moversPoint($overview['movers']['ALL'] ?? []),
            self::concentrationPoint($heatmap, (float) ($liquidity['value'] ?? 0)),
        ]));

        return [
            'headline' => self::headline($vn, $breadth, $liquidity),
            'tone' => $tone,
            'points' => $points,
            'ask' => self::question($pct),
        ];
    }

    /** 'up' | 'down' | 'flat' (a move under 0,05 % is flat). */
    public static function direction(float $pct): string
    {
        return abs($pct) < 0.05 ? 'flat' : ($pct > 0 ? 'up' : 'down');
    }

    /** The verb for the index move, graded by its size. */
    public static function verb(float $pct): string
    {
        $abs = abs($pct);
        $up = $pct > 0;

        return match (true) {
            $abs < 0.15 => 'gần như đi ngang',
            $abs < 0.5 => $up ? 'nhích lên' : 'nhích xuống',
            $abs < 1.5 => $up ? 'tăng' : 'giảm',
            default => $up ? 'tăng mạnh' : 'giảm mạnh',
        };
    }

    // ── sentences ───────────────────────────────────────────────────────────

    private static function headline(array $vn, array $breadth, array $liquidity): string
    {
        $pct = (float) $vn['percent'];
        $points = VnFormat::number(abs((float) $vn['change']), 2);
        $close = VnFormat::number((float) $vn['close'], 2);

        $signed = ((float) $vn['change'] > 0 ? '+' : ((float) $vn['change'] < 0 ? '-' : '')).$points;
        $text = self::direction($pct) === 'flat'
            ? "VN-Index gần như đi ngang ({$signed} điểm), đứng ở {$close}."
            : 'VN-Index '.self::verb($pct)." {$points} điểm (".VnFormat::percent(abs($pct)).") về {$close}.";

        if (isset($breadth['advancers'], $breadth['decliners'])) {
            $text .= ' '.VnFormat::number($breadth['advancers']).' mã tăng, '.VnFormat::number($breadth['decliners']).' mã giảm.';
        }
        if (! empty($liquidity['value'])) {
            $text .= ' Thanh khoản '.VnFormat::bigMoney($liquidity['value']);
            $text .= isset($liquidity['change_percent']) && $liquidity['change_percent'] !== null
                ? ' ('.VnFormat::percent($liquidity['change_percent'], 1, true).' so với phiên trước).'
                : '.';
        }

        return $text;
    }

    private static function question(float $pct): string
    {
        return self::direction($pct) === 'flat'
            ? 'Phiên giao dịch hôm nay của thị trường chứng khoán Việt Nam có gì đáng chú ý? Trả lời ngắn gọn dựa trên số liệu thị trường hiện có.'
            : 'Vì sao VN-Index '.($pct > 0 ? 'tăng' : 'giảm').' '.VnFormat::percent(abs($pct)).' trong phiên gần nhất? Giải thích ngắn gọn dựa trên số liệu thị trường hiện có.';
    }

    /** @return array<string, string>|null */
    private static function breadthPoint(array $b): ?array
    {
        if (! isset($b['advancers'], $b['decliners'])) {
            return null;
        }
        $adv = (int) $b['advancers'];
        $dec = (int) $b['decliners'];

        [$tone, $lead] = match (true) {
            $dec > $adv * 1.3 => ['down', 'Bên bán áp đảo'],
            $adv > $dec * 1.3 => ['up', 'Bên mua chiếm ưu thế'],
            default => ['flat', 'Cung cầu cân bằng'],
        };
        $text = "{$lead}: ".VnFormat::number($adv).' mã tăng, '.VnFormat::number($dec).' mã giảm.';
        if (! empty($b['ceiling']) || ! empty($b['floor'])) {
            $text .= ' '.VnFormat::number((int) ($b['ceiling'] ?? 0)).' mã tăng trần, '.VnFormat::number((int) ($b['floor'] ?? 0)).' mã giảm sàn.';
        }

        return self::point('breadth', 'bar-chart-steps', 'Độ rộng', $tone, $text);
    }

    /** @return array<string, string>|null */
    private static function sectorPoint(?array $heatmap): ?array
    {
        $sectors = self::sectors($heatmap);
        if (count($sectors) < 2) {
            return null;
        }
        $best = $sectors[0];
        $worst = $sectors[count($sectors) - 1];
        $fmt = fn (array $s) => $s['name'].' ('.VnFormat::percent($s['percent'], 2, true).')';

        if ($best['percent'] <= 0) {
            return self::point('sector', 'diagram-3', 'Ngành', 'down', 'Chưa có ngành lớn nào tăng điểm; yếu nhất: '.$fmt($worst).'.');
        }
        if ($worst['percent'] >= 0) {
            return self::point('sector', 'diagram-3', 'Ngành', 'up', 'Các ngành lớn đều tăng; mạnh nhất: '.$fmt($best).'.');
        }

        return self::point('sector', 'diagram-3', 'Ngành', 'flat', 'Mạnh nhất: '.$fmt($best).'. Yếu nhất: '.$fmt($worst).'.');
    }

    /** @param  array<string, array<string, mixed>>  $others */
    private static function indicesPoint(array $others): ?array
    {
        $parts = [];
        foreach ($others as $i) {
            if (isset($i['name'], $i['percent'])) {
                $parts[] = $i['name'].' '.VnFormat::percent((float) $i['percent'], 2, true);
            }
        }
        if ($parts === []) {
            return null;
        }
        $first = reset($others);

        return self::point('indices', 'activity', 'Các chỉ số', self::direction((float) ($first['percent'] ?? 0)), implode(' · ', $parts).'.');
    }

    /** @param  array<string, mixed>  $board */
    private static function moversPoint(array $board): ?array
    {
        $up = $board['gainers'][0] ?? null;
        $down = $board['losers'][0] ?? null;
        $parts = [];
        if (is_array($up) && isset($up['symbol'], $up['percent'])) {
            $parts[] = "Tăng mạnh nhất: {$up['symbol']} ".VnFormat::percent((float) $up['percent'], 2, true);
        }
        if (is_array($down) && isset($down['symbol'], $down['percent'])) {
            $parts[] = "giảm sâu nhất: {$down['symbol']} ".VnFormat::percent((float) $down['percent'], 2, true);
        }
        if ($parts === []) {
            return null;
        }

        return self::point('movers', 'lightning-charge', 'Nổi bật', 'flat', ucfirst(implode('; ', $parts)).' (mã có giá trị giao dịch từ 5 tỷ).');
    }

    /** @return array<string, string>|null */
    private static function concentrationPoint(?array $heatmap, float $marketValue): ?array
    {
        $items = $heatmap['items'] ?? [];
        if (count($items) < self::TOP_N || $marketValue <= 0) {
            return null;
        }
        $top = array_slice($items, 0, self::TOP_N);   // already busiest first
        $share = array_sum(array_column($top, 'v')) / $marketValue * 100;
        if ($share <= 0 || $share > 100) {
            return null;   // inconsistent inputs (e.g. tiles from another session): say nothing rather than a wrong figure
        }

        return self::point(
            'concentration',
            'pie-chart',
            'Dòng tiền',
            'flat',
            self::TOP_N.' mã giao dịch lớn nhất ('.implode(', ', array_column($top, 's')).') chiếm '.VnFormat::number($share, 0).'% giá trị khớp lệnh.'
        );
    }

    /**
     * Industries big enough to talk about, strongest first (value-weighted % change).
     *
     * @return array<int, array{name: string, percent: float}>
     */
    private static function sectors(?array $heatmap): array
    {
        $items = $heatmap['items'] ?? [];
        $total = array_sum(array_column($items, 'v'));
        if ($total <= 0) {
            return [];
        }

        $groups = [];
        foreach ($items as $it) {
            $name = (string) ($it['i'] ?? '');
            if ($name === '' || $name === MarketHeatmap::OTHER) {
                continue;
            }
            $g = &$groups[$name];
            $g['n'] = ($g['n'] ?? 0) + 1;
            $g['v'] = ($g['v'] ?? 0) + (float) $it['v'];
            $g['pv'] = ($g['pv'] ?? 0) + (float) $it['v'] * (float) $it['c'];
            unset($g);
        }

        $out = [];
        foreach ($groups as $name => $g) {
            if ($g['n'] >= self::SECTOR_MIN_TILES && $g['v'] / $total >= self::SECTOR_MIN_SHARE) {
                $out[] = ['name' => $name, 'percent' => round($g['pv'] / $g['v'], 2)];
            }
        }
        usort($out, fn (array $a, array $b) => $b['percent'] <=> $a['percent']);

        return $out;
    }

    /** @return array<string, string> */
    private static function point(string $key, string $icon, string $title, string $tone, string $text): array
    {
        return ['key' => $key, 'icon' => $icon, 'title' => $title, 'tone' => $tone, 'text' => $text];
    }
}
