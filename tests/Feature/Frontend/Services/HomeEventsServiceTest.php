<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Services\HomeEventsService;
use App\Jobs\SyncCompanyProfileJob;
use App\Models\CompanyProfile;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/** The "Sự kiện" tab: upcoming dividends / meetings from stored profiles, plus the visitor's own symbols. Python is never involved. */
class HomeEventsServiceTest extends TestCase
{
    use BuildsMarketPayload, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();   // a symbol without a profile queues a sync job: it must never really run here
        Cache::flush();
    }

    private function days(int $n): string
    {
        return now()->addDays($n)->toDateString();
    }

    private function profile(string $symbol, array $events): void
    {
        CompanyProfile::create(['symbol' => $symbol, 'data' => ['events' => $events, 'symbol' => $symbol], 'synced_at' => now()]);
    }

    private function dividend(string $code, int $exrightIn, string $title = 'Cổ tức bằng tiền 1.000 đồng/cp'): array
    {
        return ['code' => $code, 'name' => 'Cổ tức', 'category' => 'DIVIDEND', 'title' => $title, 'exright_date' => $this->days($exrightIn), 'record_date' => $this->days($exrightIn + 1), 'public_date' => $this->days(-5)];
    }

    private function meeting(string $code, int $recordIn): array
    {
        return ['code' => $code, 'name' => 'Họp ĐHCĐ', 'category' => 'SHAREHOLDER_MEETING', 'title' => 'Đại hội cổ đông thường niên', 'record_date' => $this->days($recordIn), 'public_date' => $this->days(-1)];
    }

    private function forHome(array $mine = []): array
    {
        return $this->app->make(HomeEventsService::class)->forHome($mine);
    }

    #[Group('homeEvents')]
    public function test_upcoming_dividends_and_meetings_are_listed_by_date_with_a_readable_label(): void
    {
        $this->profile('AAA', [$this->dividend('D1', 20), $this->meeting('M1', 5)]);
        $this->profile('BBB', [$this->dividend('D2', 3)]);

        $r = $this->forHome();

        $this->assertSame(['BBB', 'AAA', 'AAA'], array_column($r['events'], 'symbol'));
        $this->assertSame([$this->days(3), $this->days(5), $this->days(20)], array_column($r['events'], 'date'));
        $this->assertSame(['dividend', 'meeting', 'dividend'], array_column($r['events'], 'kind'));
        $this->assertSame('Giao dịch không hưởng quyền', $r['events'][0]['label']);
        $this->assertSame('Đại hội cổ đông', $r['events'][1]['label']);
        $this->assertSame('Cổ tức bằng tiền 1.000 đồng/cp', $r['events'][0]['title']);
        $this->assertSame(2, $r['covered']);
    }

    #[Group('homeEvents')]
    public function test_past_events_events_beyond_the_horizon_and_other_categories_are_left_out(): void
    {
        $this->profile('AAA', [
            $this->dividend('PAST', -3),
            $this->dividend('FAR', HomeEventsService::HORIZON_DAYS + 20),
            ['code' => 'AIS', 'name' => 'Niêm yết thêm', 'category' => 'OTHER', 'title' => 'Niêm yết bổ sung', 'issue_date' => $this->days(4)],
            $this->dividend('OK', 10),
        ]);

        $r = $this->forHome();

        $this->assertSame([$this->days(10)], array_column($r['events'], 'date'));
    }

    #[Group('homeEvents')]
    public function test_a_record_date_is_labelled_as_such_when_there_is_no_ex_rights_date(): void
    {
        $event = $this->dividend('D1', 7);
        unset($event['exright_date']);
        $this->profile('AAA', [$event]);

        $this->assertSame('Chốt quyền', $this->forHome()['events'][0]['label']);
    }

    #[Group('homeEvents')]
    public function test_the_visitors_own_symbols_are_flagged_merged_without_duplicates_and_missing_profiles_are_queued(): void
    {
        $this->profile('AAA', [$this->dividend('D1', 12)]);

        $r = $this->forHome(['AAA', 'NOPROFILE']);

        $this->assertCount(1, $r['events'], 'AAA appears once even though it is both shared and "mine"');
        $this->assertTrue($r['events'][0]['mine']);
        Queue::assertPushed(SyncCompanyProfileJob::class, fn ($job) => $job->queueSummary() === 'NOPROFILE');
    }

    #[Group('homeEvents')]
    public function test_a_guest_sees_nothing_flagged_and_no_profile_job_is_queued_for_them(): void
    {
        $this->profile('AAA', [$this->dividend('D1', 12)]);

        $r = $this->forHome();

        $this->assertFalse($r['events'][0]['mine']);
        Queue::assertNotPushed(SyncCompanyProfileJob::class);
    }

    #[Group('homeEvents')]
    public function test_at_most_twelve_events_and_at_most_fifteen_of_the_visitors_symbols_are_looked_up(): void
    {
        foreach (range(1, 20) as $i) {
            $this->profile('S'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), [$this->dividend('D'.$i, $i)]);
        }
        $many = array_map(fn ($i) => 'M'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), range(1, 40));

        $r = $this->forHome($many);

        $this->assertCount(HomeEventsService::LIMIT, $r['events']);
        Queue::assertPushed(SyncCompanyProfileJob::class, 15);
    }

    #[Group('homeEvents')]
    public function test_the_shared_list_is_cached_for_the_day(): void
    {
        $this->profile('AAA', [$this->dividend('D1', 12)]);
        $this->forHome();

        CompanyProfile::query()->delete();

        $this->assertCount(1, $this->forHome()['events'], 'served from the cache, not rebuilt');
    }

    #[Group('homeEvents')]
    public function test_with_no_profiles_at_all_the_list_is_empty_and_covered_is_zero(): void
    {
        $this->assertSame(['events' => [], 'covered' => 0], $this->forHome());
    }

    #[Group('homeEvents')]
    public function test_events_without_a_usable_date_are_skipped(): void
    {
        $this->profile('AAA', [['code' => 'X', 'category' => 'DIVIDEND', 'title' => 'No dates'], $this->dividend('OK', 9)]);

        $this->assertCount(1, $this->forHome()['events']);
    }

    #[Group('homeEvents')]
    public function test_the_nightly_seeding_warms_the_most_traded_stocks_first_so_the_calendar_covers_names_people_care_about(): void
    {
        foreach (['AAA', 'BBB', 'CCC', 'DDD'] as $symbol) {
            Stock::create(['symbol' => $symbol, 'name' => $symbol]);
        }
        $this->seedMarketSnapshot(['quotes' => [
            'AAA' => [10000, 10000, 0.0, 1, 1_000_000_000],
            'BBB' => [10000, 10000, 0.0, 1, 90_000_000_000],
            'CCC' => [10000, 10000, 0.0, 1, 5_000_000_000],
            // DDD is not in the snapshot: it comes after every traded stock
        ]]);
        $this->profile('AAA', []);   // already has a profile: not seeded again

        $this->artisan('sync:company-profiles', ['--seed' => true, '--limit' => 2, '--dispatch' => true])->assertExitCode(0);

        Queue::assertPushed(SyncCompanyProfileJob::class, 2);
        $queued = [];
        Queue::assertPushed(SyncCompanyProfileJob::class, function ($job) use (&$queued) {
            $queued[] = $job->queueSummary();

            return true;
        });
        $this->assertSame(['BBB', 'CCC'], $queued);
    }
}
