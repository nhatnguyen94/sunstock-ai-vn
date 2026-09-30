<?php

namespace Tests\Feature\Database;

use App\Models\Portfolio;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\User;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class DemoUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    #[Group('demoUsers')]
    public function test_it_creates_five_verified_accounts_with_the_shared_password_and_the_user_role(): void
    {
        $this->seed(DemoUsersSeeder::class);

        $users = User::where('email', 'like', 'demo%@sunstock.test')->get();
        $this->assertCount(5, $users);
        foreach ($users as $u) {
            $this->assertNotNull($u->email_verified_at);
            $this->assertTrue(Hash::check(DemoUsersSeeder::PASSWORD, $u->password));
            $this->assertTrue($u->hasRole(Role::USER));
            $this->assertTrue(\App\Models\UserProfile::where('user_id', $u->id)->exists());
        }
        $this->assertSame('abc123456789', DemoUsersSeeder::PASSWORD);
    }

    #[Group('demoUsers')]
    public function test_running_it_twice_changes_nothing(): void
    {
        $this->seed(DemoUsersSeeder::class);
        $hash = User::where('email', 'demo1@sunstock.test')->value('password');
        $this->seed(DemoUsersSeeder::class);

        $this->assertSame(5, User::where('email', 'like', 'demo%@sunstock.test')->count());
        $this->assertSame($hash, User::where('email', 'demo1@sunstock.test')->value('password'));
    }

    #[Group('demoUsers')]
    public function test_a_portfolio_is_built_from_real_price_history_once_and_the_last_account_has_none(): void
    {
        $stock = Stock::create(['symbol' => 'VCB']);
        foreach ([now()->subDays(400), now()->subDays(1)] as $d) {
            StockPrice::create(['stock_id' => $stock->id, 'date' => $d->toDateString(), 'open' => 60, 'high' => 60, 'low' => 60, 'close' => 60, 'volume' => 1]);
        }

        $this->seed(DemoUsersSeeder::class);
        $this->seed(DemoUsersSeeder::class);

        $demo1 = User::where('email', 'demo1@sunstock.test')->first();
        $this->assertSame(1, Portfolio::where('user_id', $demo1->id)->count());
        $portfolio = Portfolio::where('user_id', $demo1->id)->first();
        $this->assertSame(['VCB'], $portfolio->items->pluck('stock_symbol')->all());   // the other symbols have no prices: skipped, not faked
        $this->assertEqualsWithDelta(60_000.0, (float) $portfolio->items->first()->buy_price, 100);
        $this->assertSame(0, Portfolio::where('user_id', User::where('email', 'demo5@sunstock.test')->value('id'))->count());
    }

    #[Group('demoUsers')]
    public function test_it_refuses_to_run_outside_local_and_testing_because_the_password_is_public(): void
    {
        $this->app['env'] = 'production';

        // run() directly: `db:seed` itself asks for a confirmation in production, which is a different safeguard
        (new DemoUsersSeeder)->run();

        $this->assertSame(0, User::where('email', 'like', 'demo%@sunstock.test')->count());
    }
}
