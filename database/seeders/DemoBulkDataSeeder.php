<?php

namespace Database\Seeders;

use App\Frontend\Services\PortfolioLedgerService;
use App\Frontend\Services\PortfolioService;
use App\Models\MarketSnapshot;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\User;
use App\Support\PriceUnit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A few hundred realistic records for every feature, so the site looks alive and every list, chart, filter and page has something to chew on:
 *
 *   php artisan db:seed --class=DemoBulkDataSeeder          (undo: --class=DemoBulkDataCleanupSeeder)
 *
 * ~320 Vietnamese-looking members (sign-ups spread over 5 months, growing; a few pending / inactive / blocked) · ~220 portfolios of eight
 * investing styles whose buys and partial sells use the REAL closing prices stored in `stock_prices` on the REAL dates, so the profit / loss
 * on the pages is real · watchlists · a few thousand activity events and sign-ins (with typos, scanners, three password-guessers) · ~1,000 AI
 * calls from a handful of heavy users, three blocked from the AI · 1,500 sync runs and 600 queue jobs over the last week · blocked IPs.
 *
 * LOCAL ONLY. Deterministic (fixed random seed) and idempotent: it removes its own previous rows first, so running it again gives the same data.
 * Everything is tied to `@bulk.sunstock.test` addresses or marked "bulk", so DemoBulkDataCleanupSeeder removes exactly that. Shared demo password.
 */
class DemoBulkDataSeeder extends Seeder
{
    public const DOMAIN = 'bulk.sunstock.test';

    public const MARK = 'bulk-seed';

    public const UA = 'BulkSeed/1.0';

    /** Number of members: the test lowers it. */
    public static int $members = 320;

    private const SURNAMES = ['Nguyễn' => 38, 'Trần' => 11, 'Lê' => 9, 'Phạm' => 7, 'Hoàng' => 4, 'Huỳnh' => 3, 'Phan' => 3, 'Vũ' => 3, 'Võ' => 3, 'Đặng' => 3, 'Bùi' => 3, 'Đỗ' => 2, 'Hồ' => 2, 'Ngô' => 2, 'Dương' => 2, 'Lý' => 2, 'Đinh' => 1, 'Trịnh' => 1, 'Mai' => 1];

    private const MALE = ['Anh', 'Bình', 'Cường', 'Dũng', 'Đạt', 'Hải', 'Hiếu', 'Hoàng', 'Hùng', 'Huy', 'Khang', 'Khoa', 'Kiên', 'Long', 'Minh', 'Nam', 'Nghĩa', 'Phong', 'Phúc', 'Quân', 'Quang', 'Sơn', 'Tâm', 'Thắng', 'Thành', 'Thịnh', 'Trung', 'Tuấn', 'Việt', 'Vinh'];

    private const FEMALE = ['Anh', 'Chi', 'Dung', 'Giang', 'Hà', 'Hạnh', 'Hiền', 'Hoa', 'Hương', 'Lan', 'Linh', 'Loan', 'Mai', 'My', 'Ngân', 'Ngọc', 'Nhung', 'Phương', 'Quỳnh', 'Thảo', 'Thu', 'Thủy', 'Trang', 'Trâm', 'Uyên', 'Vân', 'Yến'];

    private const MIDDLE_MALE = ['Văn', 'Hữu', 'Đức', 'Minh', 'Quang', 'Gia', 'Anh', 'Thanh', 'Bảo', 'Công', 'Ngọc', 'Xuân'];

    private const MIDDLE_FEMALE = ['Thị', 'Ngọc', 'Thu', 'Thanh', 'Kim', 'Bảo', 'Mai', 'Minh', 'Diệu', 'Hồng', 'Khánh'];

    private const AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36 Edg/129.0',
    ];

    /** style => [portfolio names, symbol pool, positions min, positions max, invested (VND) min, max] */
    private const STYLES = [
        'bank' => [['Cổ phiếu ngân hàng và blue-chip', 'Ngân hàng dài hạn', 'Danh mục an toàn'], ['VCB', 'BID', 'CTG', 'TCB', 'MBB', 'ACB', 'VPB', 'STB', 'HDB', 'TPB', 'VIB', 'LPB', 'MSB', 'SHB', 'VNM', 'FPT'], 4, 8, 60_000_000, 900_000_000],
        'growth' => [['Tăng trưởng và công nghệ', 'Growth 2026', 'Công nghệ và bán lẻ'], ['FPT', 'CMG', 'MWG', 'PNJ', 'FRT', 'DGW', 'HPG', 'GMD', 'VHC', 'DBC', 'REE'], 3, 6, 40_000_000, 600_000_000],
        'realestate' => [['Bất động sản', 'BĐS khu công nghiệp', 'Địa ốc chờ sóng'], ['VHM', 'VIC', 'NVL', 'DXG', 'KDH', 'NLG', 'PDR', 'DIG', 'CEO', 'KBC', 'IDC', 'SZC', 'VRE'], 3, 6, 50_000_000, 700_000_000],
        'broker' => [['Chứng khoán đầu cơ', 'Nhóm chứng khoán', 'Beta cao'], ['SSI', 'VND', 'VCI', 'HCM', 'MBS', 'FTS', 'VIX', 'SHS', 'BSI', 'CTS'], 3, 6, 30_000_000, 400_000_000],
        'etf' => [['Đầu tư thụ động bằng ETF', 'ETF VN30', 'Tích sản đều đặn'], ['E1VFVN30', 'FUESSV30', 'FUEVFVND', 'FUEMAV30', 'FUEKIV30', 'VNM'], 2, 3, 20_000_000, 300_000_000],
        'dividend' => [['Cổ tức và phòng thủ', 'Thu nhập thụ động', 'Quỹ hưu trí'], ['VNM', 'GAS', 'PLX', 'SAB', 'REE', 'POW', 'BMP', 'NT2', 'PPC', 'DHG', 'TLG'], 3, 5, 80_000_000, 800_000_000],
        'trader' => [['Lướt sóng T+', 'Giao dịch ngắn hạn', 'Đánh nhanh rút gọn'], [], 5, 12, 10_000_000, 150_000_000],
        'mixed' => [['Đa dạng hóa', 'Danh mục của tôi', 'Tích sản cho con'], [], 5, 9, 40_000_000, 500_000_000],
    ];

    private const QUESTIONS = [
        'VN-Index hôm nay có gì đáng chú ý?', 'Nên phân bổ danh mục thế nào khi lãi suất giảm?', 'FPT có đáng nắm giữ dài hạn không?', 'So sánh VCB và TCB về hiệu quả sinh lời',
        'RSI quá bán nghĩa là gì?', 'Khối ngoại bán ròng ảnh hưởng thế nào đến thị trường?', 'Cổ phiếu ngân hàng còn hấp dẫn không?', 'Cách đọc bản đồ nhiệt của thị trường',
        'Thanh khoản giảm 10% là tín hiệu gì?', 'ETF VN30 phù hợp với ai?', 'Khi nào nên cắt lỗ?', 'Dự báo ngành bất động sản quý tới', 'P/E bao nhiêu là rẻ?',
        'HPG có nên mua ở vùng giá này?', 'Phân tích kỹ thuật cơ bản cho người mới', 'Margin là gì và rủi ro ra sao?', 'Cổ tức bằng cổ phiếu có lợi không?', 'ROE cao có luôn tốt?',
        'Nên mua cổ phiếu lúc nào trong phiên?', 'Vàng và chứng khoán, kênh nào tốt hơn?', 'Giải thích chỉ số Beta', 'Ngành thép có chu kỳ như thế nào?', 'Tỷ giá ảnh hưởng đến nhóm xuất khẩu ra sao?',
        'Đa dạng hóa danh mục bao nhiêu mã là đủ?', 'VCB hay BID?', 'Có nên mua đuổi khi cổ phiếu tăng trần?', 'Quỹ mở và ETF khác nhau thế nào?', 'Báo cáo tài chính cần xem những mục nào?',
        'Dòng tiền thông minh là gì?', 'MWG có còn tăng trưởng không?', 'Thuế khi bán cổ phiếu tính thế nào?', 'T+2 nghĩa là gì?', 'Sóng ngành chứng khoán khi nào đến?',
        'Điểm mua theo đường MA20', 'Cổ phiếu nào đang có tín hiệu vượt đỉnh 52 tuần?', 'Lãi suất tăng ảnh hưởng gì đến bất động sản?', 'Tóm tắt phiên hôm nay giúp tôi', 'VNM chia cổ tức khi nào?',
        'Nên chốt lời bao nhiêu phần trăm?', 'Đánh giá rủi ro danh mục của tôi', 'Giá mục tiêu là gì và đặt thế nào?',
    ];

    private const IPS = ['203.0.113.', '198.51.100.', '192.0.2.'];

    /** @var array<string, array{0: array<int, string>, 1: array<int, float>}> symbol => [dates, closes (feed units)] */
    private array $series = [];

    /** @var array<int, string> */
    private array $universe = [];

    private string $passwordHash = '';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('DemoBulkDataSeeder chỉ chạy ở môi trường local/testing.');

            return;
        }

        mt_srand(20261010);
        DemoBulkDataCleanupSeeder::purge();
        $this->call([RoleSeeder::class, PermissionSeeder::class]);
        $this->passwordHash = Hash::make(DemoUsersSeeder::PASSWORD);

        $this->loadPrices();
        $members = $this->members();
        $this->portfolios($members);
        $this->watchlists($members);
        $this->aiBlocks($members);
        $logins = $this->logins($members);
        $this->activity($members, $logins);
        $this->ai($members);
        $this->securityNoise();
        $this->syncRuns();
        $this->queueJobs();

        $this->summary($members);
    }

    // ── random helpers (deterministic through mt_srand) ─────────────────────

    private function rand(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    private function unit(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    private function chance(float $p): bool
    {
        return $this->unit() < $p;
    }

    private function pick(array $list): mixed
    {
        return $list[mt_rand(0, count($list) - 1)];
    }

    /** @param array<string|int, int|float> $weights */
    private function weighted(array $weights): string|int
    {
        $roll = $this->unit() * array_sum($weights);
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /** A moment between two instants that falls in Vietnamese waking hours, with a morning and an evening peak. */
    private function moment(Carbon $from, Carbon $to): Carbon
    {
        $hours = [6 => 1, 7 => 3, 8 => 6, 9 => 10, 10 => 10, 11 => 7, 12 => 4, 13 => 6, 14 => 8, 15 => 6, 16 => 4, 17 => 3, 18 => 3, 19 => 5, 20 => 8, 21 => 9, 22 => 6, 23 => 2];
        $spanDays = max(0, (int) $from->diffInDays($to));
        for ($i = 0; $i < 6; $i++) {
            $at = $from->copy()->timezone('Asia/Ho_Chi_Minh')->startOfDay()->addDays($this->rand(0, $spanDays))->setTime((int) $this->weighted($hours), $this->rand(0, 59), $this->rand(0, 59))->utc();
            if ($at->gte($from) && $at->lte($to)) {
                return $at;
            }
        }

        return $from->copy()->addSeconds($this->rand(0, max(1, (int) $from->diffInSeconds($to))));
    }

    private function ip(): string
    {
        return $this->pick(self::IPS).$this->rand(2, 250);
    }

    // ── real prices ─────────────────────────────────────────────────────────

    /** The most traded stocks of the latest snapshot plus the ETFs, each with its stored daily closes of the last ~200 days. */
    private function loadPrices(): void
    {
        $quotes = (array) (MarketSnapshot::query()->latest('id')->first()?->quotes ?? []);
        uasort($quotes, fn ($a, $b) => ($b[4] ?? 0) <=> ($a[4] ?? 0));
        $symbols = array_values(array_unique(array_merge(array_slice(array_keys($quotes), 0, 170), ...array_values(array_map(fn ($s) => $s[1], self::STYLES)))));

        $rows = DB::table('stock_prices')
            ->join('stocks', 'stocks.id', '=', 'stock_prices.stock_id')
            ->whereIn('stocks.symbol', $symbols)
            ->where('stock_prices.date', '>=', now()->subDays(200)->toDateString())
            ->where('stock_prices.close', '>', 0)
            ->orderBy('stock_prices.date')
            ->get(['stocks.symbol', 'stock_prices.date', 'stock_prices.close']);

        foreach ($rows as $row) {
            $this->series[$row->symbol][0][] = substr((string) $row->date, 0, 10);
            $this->series[$row->symbol][1][] = (float) $row->close;
        }
        $this->series = array_filter($this->series, fn ($s) => count($s[0]) >= 40);

        // popularity order: most traded first (ETFs and unknown-to-snapshot symbols after)
        $this->universe = array_values(array_filter(array_unique(array_merge(array_keys($quotes), array_keys($this->series))), fn ($s) => isset($this->series[$s])));
        $this->universe = array_slice($this->universe, 0, 190);
    }

    /** Close (feed unit) on or before a date, or null when the stored history starts later. */
    private function closeOn(string $symbol, string $date): ?float
    {
        [$dates, $closes] = $this->series[$symbol] ?? [[], []];
        for ($i = count($dates) - 1; $i >= 0; $i--) {
            if ($dates[$i] <= $date) {
                return $closes[$i];
            }
        }

        return null;
    }

    /** The first stored trading day on or after a date (a real day the stock traded), or null. */
    private function tradingDayFrom(string $symbol, string $date): ?string
    {
        foreach ($this->series[$symbol][0] ?? [] as $d) {
            if ($d >= $date) {
                return $d;
            }
        }

        return null;
    }

    // ── members ─────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function members(): array
    {
        $rows = [];
        $profiles = [];
        $seen = [];

        foreach (range(1, self::$members) as $i) {
            $female = $this->chance(0.48);
            $surname = (string) $this->weighted(self::SURNAMES);
            $middle = $this->pick($female ? self::MIDDLE_FEMALE : self::MIDDLE_MALE);
            $given = $this->pick($female ? self::FEMALE : self::MALE);
            $name = "{$surname} {$middle} {$given}";
            $slug = Str::slug($given.'.'.$surname, '.').'.'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $email = "{$slug}@".self::DOMAIN;
            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;

            $roll = $this->unit();
            $status = $roll < 0.86 ? User::STATUS_ACTIVE : ($roll < 0.93 ? User::STATUS_PENDING : ($roll < 0.97 ? User::STATUS_INACTIVE : User::STATUS_BLOCKED));
            $created = now()->subDays((int) floor(150 * ($this->unit() ** 1.6)))->subMinutes($this->rand(0, 1380));
            $verified = $status === User::STATUS_PENDING ? null : $created->copy()->addMinutes($this->rand(2, 600));

            $rows[] = [
                'name' => $name, 'email' => $email, 'email_verified_at' => $verified, 'password' => $this->passwordHash, 'status' => $status,
                'created_at' => $created, 'updated_at' => $created,
            ];
            $profiles[$email] = [
                'username' => Str::slug($given.$surname, '').$this->rand(10, 99), 'gender' => $female ? 'female' : 'male',
                'mobile' => $this->chance(0.6) ? '09'.$this->rand(0, 9).'5550'.str_pad((string) $this->rand(0, 999), 3, '0', STR_PAD_LEFT) : null,
                'birthday' => $this->chance(0.7) ? now()->subYears($this->rand(22, 58))->subDays($this->rand(0, 364))->toDateString() : null,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        $userRole = Role::firstWhere('name', Role::USER)->id;
        $members = [];
        foreach (User::query()->where('email', 'like', '%@'.self::DOMAIN)->orderBy('id')->get() as $user) {
            $propensity = exp(($this->unit() + $this->unit() + $this->unit() - 1.5) * 1.7);   // most members are quiet, a few are very active
            $members[] = [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'status' => $user->status, 'created' => $user->created_at,
                'ip' => $this->ip(), 'ua' => $this->pick(self::AGENTS), 'propensity' => $propensity, 'profile' => $profiles[$user->email] ?? [],
            ];
        }

        DB::table('role_user')->insert(array_map(fn ($m) => ['user_id' => $m['id'], 'role_id' => $userRole, 'created_at' => now(), 'updated_at' => now()], $members));
        DB::table('user_profiles')->insert(array_map(fn ($m) => [
            'user_id' => $m['id'], 'username' => $m['profile']['username'] ?? null, 'gender' => $m['profile']['gender'] ?? null, 'mobile' => $m['profile']['mobile'] ?? null,
            'birthday' => $m['profile']['birthday'] ?? null, 'created_at' => $m['created'], 'updated_at' => $m['created'],
        ], $members));

        return $members;
    }

    private function canUse(array $member): bool
    {
        return $member['status'] === User::STATUS_ACTIVE;
    }

    // ── portfolios, trades (real closes on real dates) ──────────────────────

    /** @param array<int, array<string, mixed>> $members */
    private function portfolios(array $members): void
    {
        $ledger = app(PortfolioLedgerService::class);
        $service = app(PortfolioService::class);
        $styles = array_keys(self::STYLES);
        $styleWeights = ['bank' => 20, 'growth' => 16, 'realestate' => 10, 'broker' => 8, 'etf' => 10, 'dividend' => 10, 'trader' => 14, 'mixed' => 12];

        foreach ($members as $member) {
            if (! $this->canUse($member) || ! $this->chance(0.64)) {
                continue;
            }
            $count = $this->weighted([1 => 70, 2 => 22, 3 => 8]);
            $used = [];
            foreach (range(1, (int) $count) as $n) {
                $style = (string) $this->weighted($styleWeights);
                [$names, $pool, $minPos, $maxPos, $minVnd, $maxVnd] = self::STYLES[$style];
                $name = $this->pick($names).($count > 1 || isset($used[$names[0]]) ? ' '.$n : '');
                $used[$names[0]] = true;
                $this->portfolio($member, $name, $style, $pool, $this->rand($minPos, $maxPos), $minVnd, $maxVnd, $ledger, $service);
            }
        }
    }

    private function portfolio(array $member, string $name, string $style, array $pool, int $positions, int $minVnd, int $maxVnd, PortfolioLedgerService $ledger, PortfolioService $service): void
    {
        $available = $pool ? array_values(array_filter($pool, fn ($s) => isset($this->series[$s]))) : array_slice($this->universe, 0, $style === 'trader' ? 120 : 80);
        if (count($available) < 2) {
            return;
        }
        shuffle($available);
        $chosen = array_slice($available, 0, $positions);

        $portfolio = $service->createPortfolio($member['id'], ['name' => $name, 'description' => 'Dữ liệu mẫu (DemoBulkDataSeeder)']);
        $first = null;
        $budget = exp(log($minVnd) + $this->unit() * (log($maxVnd) - log($minVnd)));
        $each = $budget / count($chosen);

        foreach ($chosen as $symbol) {
            $earliest = max($member['created']->copy()->addDay(), now()->subDays(185))->toDateString();
            $day = $this->tradingDayFrom($symbol, Carbon::parse($earliest)->addDays($this->rand(0, max(0, (int) Carbon::parse($earliest)->diffInDays(now()->subDays(2)))))->toDateString());
            $close = $day ? $this->closeOn($symbol, $day) : null;
            if (! $day || ! $close) {
                continue;
            }
            $price = PriceUnit::toVnd($close);
            $qty = max(100, (int) (round($each * (0.5 + $this->unit()) / $price / 100) * 100));
            if ($style === 'etf') {
                $qty = max(100, (int) (round($qty / 100) * 100));
            }

            $ledger->trade($portfolio->id, $member['id'], ['type' => 'buy', 'stock_symbol' => $symbol, 'quantity' => $qty, 'price' => $price, 'fee' => round($price * $qty * 0.0015), 'traded_at' => $day]);
            $first = $first === null || $day < $first ? $day : $first;

            // a top-up of the position or a partial sell later on
            if ($qty >= 300 && $this->chance(0.18)) {
                $sellDay = $this->tradingDayFrom($symbol, Carbon::parse($day)->addDays($this->rand(12, 70))->toDateString());
                $sellClose = $sellDay && $sellDay < now()->subDay()->toDateString() ? $this->closeOn($symbol, $sellDay) : null;
                if ($sellClose) {
                    $sellQty = max(100, (int) (round($qty * (0.3 + $this->unit() * 0.3) / 100) * 100));
                    $ledger->trade($portfolio->id, $member['id'], ['type' => 'sell', 'stock_symbol' => $symbol, 'quantity' => min($sellQty, $qty - 100), 'price' => PriceUnit::toVnd($sellClose), 'fee' => 0, 'traded_at' => $sellDay]);
                }
            } elseif ($this->chance(0.12)) {
                $topDay = $this->tradingDayFrom($symbol, Carbon::parse($day)->addDays($this->rand(8, 60))->toDateString());
                $topClose = $topDay && $topDay < now()->subDay()->toDateString() ? $this->closeOn($symbol, $topDay) : null;
                if ($topClose) {
                    $ledger->trade($portfolio->id, $member['id'], ['type' => 'buy', 'stock_symbol' => $symbol, 'quantity' => max(100, (int) (round($qty * 0.4 / 100) * 100)), 'price' => PriceUnit::toVnd($topClose), 'fee' => 0, 'traded_at' => $topDay]);
                }
            }
        }

        $items = PortfolioItem::query()->where('portfolio_id', $portfolio->id)->get();
        if ($items->isEmpty()) {
            DB::table('portfolios')->where('id', $portfolio->id)->delete();

            return;
        }
        foreach ($items as $item) {
            if ($this->chance(0.28)) {
                $item->forceFill(['target_price' => round($item->buy_price * (1.12 + $this->unit() * 0.2), 0), 'stop_loss_price' => round($item->buy_price * (0.88 + $this->unit() * 0.04), 0)])->save();
            }
        }
        $service->updatePortfolioPrices($portfolio->id, $member['id']);

        $createdAt = $first ? Carbon::parse($first, 'Asia/Ho_Chi_Minh')->setTime($this->rand(8, 21), $this->rand(0, 59))->utc() : $member['created'];
        $createdAt = $createdAt->lt($member['created']) ? $member['created']->copy()->addHour() : $createdAt;
        DB::table('portfolios')->where('id', $portfolio->id)->update(['created_at' => $createdAt, 'updated_at' => now()->subMinutes($this->rand(5, 4000)), 'is_active' => $this->chance(0.92)]);
        // the ledger stamps rows with "now": move the rows to the dates they belong to
        DB::table('portfolio_transactions')->where('portfolio_id', $portfolio->id)->update(['created_at' => DB::raw('traded_at'), 'updated_at' => DB::raw('traded_at')]);
    }

    // ── watchlists ──────────────────────────────────────────────────────────

    /** @param array<int, array<string, mixed>> $members */
    private function watchlists(array $members): void
    {
        $rows = [];
        $zipf = [];
        foreach ($this->universe as $rank => $symbol) {
            $zipf[$symbol] = 1 / (($rank + 1) ** 0.7);
        }

        foreach ($members as $member) {
            if (! $this->canUse($member) || ! $this->chance(0.78) || ! $zipf) {
                continue;
            }
            $size = min(count($zipf), $this->weighted([3 => 18, 5 => 22, 8 => 22, 12 => 16, 18 => 12, 25 => 7, 40 => 3]));
            $chosen = [];
            for ($tries = 0; count($chosen) < $size && $tries < $size * 6; $tries++) {
                $chosen[$this->weighted($zipf)] = true;
            }
            foreach (array_keys($chosen) as $symbol) {
                $at = $this->moment($member['created'], now());
                $rows[] = ['user_id' => $member['id'], 'symbol' => $symbol, 'created_at' => $at, 'updated_at' => $at];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('watchlist_items')->insert($chunk);
        }
    }

    // ── sign-ins and the activity feed ──────────────────────────────────────

    /** @param array<int, array<string, mixed>> $members */
    private function aiBlocks(array $members): void
    {
        $heavy = collect($members)->filter(fn ($m) => $this->canUse($m))->sortByDesc('propensity')->take(40)->values();
        foreach ($heavy->shuffle()->take(3) as $m) {
            DB::table('users')->where('id', $m['id'])->update(['ai_blocked_at' => now()->subDays($this->rand(1, 9))]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @return array<int, array{user: array<string, mixed>, at: Carbon}>
     */
    private function logins(array $members): array
    {
        $rows = [];
        $logins = [];

        foreach ($members as $member) {
            if ($member['status'] === User::STATUS_PENDING) {
                continue;
            }
            $count = $member['status'] === User::STATUS_ACTIVE ? max(1, (int) round($member['propensity'] * 7)) : $this->rand(1, 3);
            foreach (range(1, min($count, 40)) as $i) {
                $at = $this->moment($member['created']->copy()->addMinutes(5), now());
                $logins[] = ['user' => $member, 'at' => $at];
                $rows[] = ['user_id' => $member['id'], 'email' => $member['email'], 'ip' => $member['ip'], 'user_agent' => self::UA.' '.$member['ua'], 'success' => true, 'door' => 'web', 'created_at' => $at];
                if ($this->chance(0.1)) {   // a typo before getting it right
                    $rows[] = ['user_id' => $member['id'], 'email' => $member['email'], 'ip' => $member['ip'], 'user_agent' => self::UA.' '.$member['ua'], 'success' => false, 'door' => 'web', 'created_at' => $at->copy()->subSeconds($this->rand(8, 40))];
                }
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('login_attempts')->insert($chunk);
        }

        return $logins;
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @param  array<int, array{user: array<string, mixed>, at: Carbon}>  $logins
     */
    private function activity(array $members, array $logins): void
    {
        $rows = [];
        $add = function (array $m, string $type, string $description, $at, ?array $props = null) use (&$rows) {
            $rows[] = ['user_id' => $m['id'], 'user_name' => $m['name'], 'event_type' => $type, 'description' => $description, 'properties' => $props ? json_encode($props) : null, 'ip_address' => $m['ip'], 'created_at' => $at];
        };
        $byId = array_column($members, null, 'id');

        foreach ($members as $m) {
            $add($m, 'user_register', "User đăng ký: {$m['email']}", $m['created']);
        }
        foreach ($logins as $l) {
            $add($l['user'], 'user_login', 'Đăng nhập: '.$l['user']['name'], $l['at']);
        }
        foreach (DB::table('portfolios')->where('description', 'like', '%DemoBulkDataSeeder%')->get(['id', 'user_id', 'name', 'created_at']) as $p) {
            $m = $byId[$p->user_id] ?? null;
            if ($m) {
                $add($m, 'portfolio_created', "Tạo danh mục {$p->name}", $p->created_at, ['portfolio_id' => $p->id]);
            }
        }
        foreach (DB::table('portfolio_transactions')->join('portfolios', 'portfolios.id', '=', 'portfolio_transactions.portfolio_id')->where('portfolios.description', 'like', '%DemoBulkDataSeeder%')
            ->get(['portfolios.user_id', 'portfolio_transactions.type', 'portfolio_transactions.stock_symbol', 'portfolio_transactions.quantity', 'portfolio_transactions.traded_at']) as $t) {
            $m = $byId[$t->user_id] ?? null;
            if ($m) {
                $at = Carbon::parse($t->traded_at, 'Asia/Ho_Chi_Minh')->setTime($this->rand(9, 14), $this->rand(0, 59))->utc();
                $add($m, 'portfolio_trade', ($t->type === 'buy' ? 'Mua ' : 'Bán ').number_format($t->quantity).' '.$t->stock_symbol, $at, ['symbol' => $t->stock_symbol, 'type' => $t->type]);
            }
        }
        foreach (DB::table('watchlist_items')->whereIn('user_id', array_keys($byId))->get(['user_id', 'symbol', 'created_at']) as $w) {
            $add($byId[$w->user_id], 'watchlist_added', "Theo dõi {$w->symbol}", $w->created_at, ['symbol' => $w->symbol]);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('activity_logs')->insert($chunk);
        }
    }

    // ── AI ──────────────────────────────────────────────────────────────────

    /** @param array<int, array<string, mixed>> $members */
    private function ai(array $members): void
    {
        $users = array_values(array_filter($members, fn ($m) => $this->canUse($m)));
        $weights = [];
        foreach ($users as $i => $u) {
            $weights[$i] = $u['propensity'] ** 1.6;
        }
        $blocked = DB::table('users')->whereIn('id', array_column($members, 'id'))->whereNotNull('ai_blocked_at')->pluck('id')->all();
        $models = ['openai/gpt-oss-120b' => 62, 'openai/gpt-oss-20b' => 24, 'groq/compound-mini' => 14];

        $rows = [];
        $total = (int) round(count($users) * 3.4);
        for ($n = 0; $n < $total; $n++) {
            $u = $users[$this->weighted($weights)];
            $at = $this->moment(max($u['created'], now()->subDays(21)), now());
            $kind = $this->chance(0.1) ? 'predict' : 'chat';
            $status = $this->chance(0.9) ? 'ok' : 'error';
            $rows[] = [
                'user_id' => $u['id'], 'kind' => $kind, 'status' => $status, 'model' => $status === 'ok' ? (string) $this->weighted($models) : null,
                'duration_ms' => $status === 'ok' ? $this->rand(900, 6800) : $this->rand(400, 2600), 'question' => $kind === 'chat' ? $this->pick(self::QUESTIONS) : null, 'created_at' => $at,
            ];
        }
        foreach ($blocked as $id) {   // the blocked accounts kept trying
            foreach (range(1, $this->rand(4, 14)) as $i) {
                $rows[] = ['user_id' => $id, 'kind' => 'chat', 'status' => 'refused', 'model' => null, 'duration_ms' => 3, 'question' => $this->pick(self::QUESTIONS), 'created_at' => $this->moment(now()->subDays(8), now())];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ai_requests')->insert($chunk);
        }
    }

    // ── scanners, guessers and blocked addresses ────────────────────────────

    private function securityNoise(): void
    {
        $rows = [];
        $emails = ['admin', 'administrator', 'root', 'test', 'user', 'info', 'support', 'contact', 'sales', 'hr', 'ceo', 'demo', 'guest', 'webmaster', 'postmaster', 'service', 'office', 'manager', 'account', 'finance'];
        $attempt = fn (string $ip, string $email, $at, string $door = 'web') => ['user_id' => null, 'email' => $email, 'ip' => $ip, 'user_agent' => self::UA.' scanner', 'success' => false, 'door' => $door, 'created_at' => $at];

        // background noise: 45 addresses trying a few times over the month
        foreach (range(1, 45) as $i) {
            $ip = '198.51.100.'.(100 + $i);
            foreach (range(1, $this->rand(1, 6)) as $k) {
                $rows[] = $attempt($ip, $this->pick($emails).'@example.com', $this->moment(now()->subDays(30), now()), $this->chance(0.25) ? 'admin' : 'web');
            }
        }
        // four real guessers on earlier days (long bursts) …
        foreach (range(1, 4) as $g) {
            $ip = '203.0.113.'.(200 + $g);
            $day = now()->subDays($this->rand(2, 20));
            foreach (range(1, $this->rand(25, 70)) as $k) {
                $rows[] = $attempt($ip, $emails[($k - 1) % count($emails)].$g.'@example.com', $day->copy()->addSeconds($k * $this->rand(6, 20)), $this->chance(0.3) ? 'admin' : 'web');
            }
        }
        // … and three at work right now (>= 10 failures in the last hour: the page flags them)
        foreach (range(1, 3) as $g) {
            $ip = '192.0.2.'.(240 + $g);
            foreach (range(1, $this->rand(12, 30)) as $k) {
                $rows[] = $attempt($ip, $emails[($k + $g) % count($emails)].'@example.com', now()->subMinutes($this->rand(1, 55))->subSeconds($this->rand(0, 59)));
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('login_attempts')->insert($chunk);
        }

        // twelve addresses an admin already turned away
        $by = User::firstWhere('email', 'admin@sunstock.test')?->id;
        foreach (range(1, 12) as $i) {
            DB::table('blocked_ips')->insert(['ip' => '198.51.100.'.(10 + $i), 'reason' => 'BULK: '.$this->pick(['dò mật khẩu', 'quét lỗ hổng', 'spam đăng ký', 'tấn công từ chối dịch vụ', 'truy cập bất thường']), 'blocked_by' => $by, 'created_at' => now()->subDays($this->rand(1, 40))]);
        }
    }

    // ── sync history and the queue ──────────────────────────────────────────

    private function syncRuns(): void
    {
        $rows = [];
        $add = function (string $command, Carbon $at, int $minMs, int $maxMs, float $failRate = 0.025) use (&$rows) {
            if ($at->gt(now()->subMinutes(3))) {
                return;
            }
            $rows[] = ['command' => $command, 'ok' => ! $this->chance($failRate), 'duration_ms' => $this->rand($minMs, $maxMs), 'output' => self::MARK, 'ran_at' => $at];
        };

        foreach (range(0, 7) as $d) {
            $day = now('Asia/Ho_Chi_Minh')->subDays($d)->startOfDay();
            $weekday = $day->isWeekday();
            foreach (range(0, 47) as $slot) {
                $at = $day->copy()->addMinutes($slot * 30 + $this->rand(0, 2))->utc();
                $add('sync:news', $at, 1800, 7200);
                $add('sync:world-markets', $at, 6000, 21000, 0.04);
            }
            foreach (range(0, 95) as $slot) {
                $add('sync:gold-prices', $day->copy()->addMinutes($slot * 15 + $this->rand(0, 2))->utc(), 900, 5200, 0.05);
            }
            if ($weekday) {
                foreach (range(0, 73) as $slot) {
                    $add('sync:market-overview', $day->copy()->setTime(9, 0)->addMinutes($slot * 5 + $this->rand(0, 1))->utc(), 3200, 6800);
                }
                foreach (range(0, 13) as $slot) {
                    $add('signals:build', $day->copy()->setTime(9, 10)->addMinutes($slot * 30)->utc(), 900, 2600, 0.01);
                }
                $add('sync:stock-prices', $day->copy()->setTime(15, 30)->utc(), 90000, 230000, 0.1);
            }
            $add('sync:exchange-rates', $day->copy()->setTime(7, 30)->utc(), 2500, 7000, 0.05);
            $add('sync:funds', $day->copy()->setTime(18, 30)->utc(), 4000, 12000, 0.08);
            $add('sync:hot-industries', $day->copy()->setTime(7, 45)->utc(), 15000, 60000, 0.06);
            $add('sync:company-profiles', $day->copy()->setTime(3, 0)->utc(), 8000, 31000, 0.07);
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('sync_runs')->insert($chunk);
        }
    }

    private function queueJobs(): void
    {
        $classes = [
            'App\Jobs\SyncCompanyProfileJob' => [40, 8000, 31000], 'App\Jobs\ProcessStockPriceSync' => [22, 20000, 190000], 'App\Jobs\SyncMarketOverviewJob' => [18, 3000, 7000],
            'App\Jobs\BuildStockSignalsJob' => [10, 1000, 2800], 'App\Jobs\SyncCompanyFinancialJob' => [6, 30000, 120000], 'App\Jobs\RefreshStockPricesJob' => [4, 4000, 15000],
        ];
        $rows = [];
        foreach (range(1, 600) as $n) {
            $class = (string) $this->weighted(array_map(fn ($c) => $c[0], $classes));
            [, $min, $max] = $classes[$class];
            $started = $this->moment(now()->subDays(7), now()->subMinutes(2));
            $roll = $this->unit();
            $status = $roll < 0.94 ? 'completed' : ($roll < 0.98 ? 'failed' : 'stale');
            $ms = $this->rand($min, $max);
            $symbol = $this->universe ? $this->universe[$this->rand(0, count($this->universe) - 1)] : 'FPT';
            $rows[] = [
                'job_id' => 'bulk-'.$n, 'job_class' => $class, 'queue' => 'default', 'summary' => in_array($class, ['App\Jobs\SyncCompanyProfileJob', 'App\Jobs\SyncCompanyFinancialJob', 'App\Jobs\RefreshStockPricesJob'], true) ? $symbol : null,
                'status' => $status, 'started_at' => $started, 'finished_at' => $status === 'stale' ? null : $started->copy()->addMilliseconds($ms), 'duration_ms' => $status === 'stale' ? null : $ms, 'created_at' => $started, 'updated_at' => $started,
            ];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('queue_job_logs')->insert($chunk);
        }
    }

    // ── report ──────────────────────────────────────────────────────────────

    /** @param array<int, array<string, mixed>> $members */
    private function summary(array $members): void
    {
        $out = $this->command;
        if (! $out) {
            return;
        }
        $ids = array_column($members, 'id');
        $out->info('Đã tạo dữ liệu mẫu lớn:');
        $table = [
            ['Thành viên', count($members)], ['  hoạt động / chờ xác thực / ngưng / bị chặn', implode(' / ', array_map(fn ($s) => count(array_filter($members, fn ($m) => $m['status'] === $s)), [User::STATUS_ACTIVE, User::STATUS_PENDING, User::STATUS_INACTIVE, User::STATUS_BLOCKED]))],
            ['Danh mục', DB::table('portfolios')->whereIn('user_id', $ids)->count()], ['Mã trong danh mục', DB::table('portfolio_items')->join('portfolios', 'portfolios.id', '=', 'portfolio_items.portfolio_id')->whereIn('portfolios.user_id', $ids)->count()],
            ['Giao dịch mua/bán', DB::table('portfolio_transactions')->join('portfolios', 'portfolios.id', '=', 'portfolio_transactions.portfolio_id')->whereIn('portfolios.user_id', $ids)->count()],
            ['Mã đang theo dõi', DB::table('watchlist_items')->whereIn('user_id', $ids)->count()], ['Hoạt động', DB::table('activity_logs')->whereIn('user_id', $ids)->count()],
            ['Lượt đăng nhập (kể cả sai)', DB::table('login_attempts')->where('user_agent', 'like', self::UA.'%')->count()], ['Lượt gọi AI', DB::table('ai_requests')->whereIn('user_id', $ids)->count()],
            ['Lần chạy đồng bộ', DB::table('sync_runs')->where('output', self::MARK)->count()], ['Job hàng đợi', DB::table('queue_job_logs')->where('job_id', 'like', 'bulk-%')->count()],
            ['IP bị chặn', DB::table('blocked_ips')->where('reason', 'like', 'BULK:%')->count()],
        ];
        $out->table(['Dữ liệu', 'Số lượng'], $table);

        $out->line('Đăng nhập thử (mật khẩu chung: '.DemoUsersSeeder::PASSWORD.') — vài tài khoản có danh mục lớn nhất:');
        $top = DB::table('portfolios')->join('users', 'users.id', '=', 'portfolios.user_id')->whereIn('portfolios.user_id', $ids)->where('users.status', User::STATUS_ACTIVE)
            ->groupBy('users.id', 'users.name', 'users.email')->orderByRaw('count(*) desc')->limit(5)->get(['users.name', 'users.email']);
        foreach ($top as $u) {
            $out->line("  {$u->email}   ({$u->name})");
        }
        $out->warn('Xong thì dọn: php artisan db:seed --class=DemoBulkDataCleanupSeeder');
    }
}
