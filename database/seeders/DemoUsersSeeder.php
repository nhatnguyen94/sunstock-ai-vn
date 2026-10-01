<?php

namespace Database\Seeders;

use App\Frontend\Services\PortfolioLedgerService;
use App\Frontend\Services\PortfolioService;
use App\Models\Portfolio;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\PriceUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Five verified demo accounts with a shared, deliberately simple password, each with a different kind of portfolio so
 * every portfolio screen can be exercised (long-term blue chips, growth with a partial sale, an ETF investor, a
 * concentrated position, and one account with no portfolio at all).
 *
 *   php artisan db:seed --class=DemoUsersSeeder
 *
 * LOCAL ONLY: the password is public knowledge, so the seeder refuses to run anywhere but the local/testing
 * environments. It is idempotent: accounts are found by e-mail and a portfolio is only built when the user has none.
 */
class DemoUsersSeeder extends Seeder
{
    public const PASSWORD = 'abc123456789';

    /** Reserved `.test` domain: mail can never leave the machine. */
    private const DOMAIN = 'sunstock.test';

    /** username => [display name, portfolio name, [[symbol, quantity, days ago bought], ...], optional partial sell] */
    private const ACCOUNTS = [
        'demo1' => ['Demo Dài hạn', 'Cổ phiếu ngân hàng và blue-chip', [['VCB', 400, 300], ['ACB', 1000, 240], ['TCB', 800, 180], ['MBB', 1500, 120], ['FPT', 300, 90]], null],
        'demo2' => ['Demo Tăng trưởng', 'Tăng trưởng và công nghệ', [['FPT', 500, 200], ['MWG', 600, 150], ['HPG', 1500, 100], ['VIC', 200, 60]], ['HPG', 500, 15]],
        'demo3' => ['Demo ETF', 'Đầu tư thụ động bằng ETF', [['E1VFVN30', 3000, 250], ['FUESSV30', 4000, 160], ['FUEVFVND', 2000, 100], ['VNM', 300, 40]], null],
        'demo4' => ['Demo Tập trung', 'Đặt cược lớn', [['VIC', 900, 120], ['VHM', 150, 80]], null],
        'demo5' => ['Demo Trống', null, [], null],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('DemoUsersSeeder chỉ chạy ở môi trường local/testing (mật khẩu chung là công khai).');

            return;
        }

        foreach (self::ACCOUNTS as $username => [$name, $portfolioName, $holdings, $sell]) {
            $user = $this->account($username, $name);

            if ($portfolioName !== null && ! Portfolio::where('user_id', $user->id)->exists()) {
                $this->portfolio($user, $portfolioName, $holdings, $sell);
            }

            $this->command?->info(sprintf('%-6s %s%s', $username, "{$username}@".self::DOMAIN, $portfolioName ? "  ({$portfolioName})" : '  (chưa có danh mục)'));
        }

        $this->command?->info('Mật khẩu chung của 5 tài khoản: '.self::PASSWORD);
    }

    private function account(string $username, string $name): User
    {
        $user = User::firstOrCreate(
            ['email' => "{$username}@".self::DOMAIN],
            ['name' => $name, 'password' => Hash::make(self::PASSWORD), 'email_verified_at' => now()]
        );
        if ($user->wasRecentlyCreated) {
            $user->forceFill(['status' => User::STATUS_ACTIVE])->save();   // status is not mass-assignable
        }
        UserProfile::firstOrCreate(['user_id' => $user->id], ['username' => $username, 'mobile' => null]);
        if (! $user->hasRole(Role::USER)) {
            $user->assignRole(Role::USER);
        }

        return $user;
    }

    /** @param array<int, array{0:string,1:int,2:int}> $holdings */
    private function portfolio(User $user, string $name, array $holdings, ?array $sell): void
    {
        $portfolio = app(PortfolioService::class)->createPortfolio($user->id, ['name' => $name, 'description' => 'Dữ liệu mẫu (DemoUsersSeeder)']);
        $ledger = app(PortfolioLedgerService::class);

        foreach ($holdings as [$symbol, $qty, $daysAgo]) {
            if (! ($bar = $this->closeOnOrBefore($symbol, $daysAgo))) {
                $this->command?->warn("  bỏ qua {$symbol}: chưa có giá lịch sử");

                continue;
            }
            $price = PriceUnit::toVnd($bar->close);
            $ledger->trade($portfolio->id, $user->id, [
                'type' => 'buy', 'stock_symbol' => $symbol, 'quantity' => $qty, 'price' => $price,
                'fee' => round($price * $qty * 0.0015), 'traded_at' => substr((string) $bar->date, 0, 10),
            ]);
        }

        if ($sell && ($bar = $this->closeOnOrBefore($sell[0], $sell[2]))) {
            $ledger->trade($portfolio->id, $user->id, [
                'type' => 'sell', 'stock_symbol' => $sell[0], 'quantity' => $sell[1],
                'price' => PriceUnit::toVnd($bar->close), 'fee' => 0, 'traded_at' => substr((string) $bar->date, 0, 10),
            ]);
        }

        app(PortfolioService::class)->updatePortfolioPrices($portfolio->id, $user->id);
    }

    /** Last stored close on or before `$daysAgo` days ago (feed unit) — real history, so the demo P&L is real. */
    private function closeOnOrBefore(string $symbol, int $daysAgo): ?object
    {
        return DB::table('stock_prices')
            ->join('stocks', 'stocks.id', '=', 'stock_prices.stock_id')
            ->where('stocks.symbol', $symbol)
            ->where('stock_prices.date', '<=', now()->subDays($daysAgo)->toDateString())
            ->orderByDesc('stock_prices.date')
            ->select('stock_prices.close', 'stock_prices.date')
            ->first();
    }
}
