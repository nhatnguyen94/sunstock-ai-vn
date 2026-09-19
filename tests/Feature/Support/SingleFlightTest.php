<?php

namespace Tests\Feature\Support;

use App\Support\SingleFlight;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Feature (not Unit) because SingleFlight uses the Cache facade for its lock;
 * phpunit.xml sets CACHE_STORE=array, which supports atomic locks in-memory.
 */
class SingleFlightTest extends TestCase
{
    #[Group('companyProfile')]
    #[Group('fundCatalog')]
    public function test_a_cache_hit_never_runs_the_producer(): void
    {
        $produced = 0;

        $result = SingleFlight::run(
            'k1',
            fn () => ['value' => 'cached'],
            function () use (&$produced) {
                $produced++;

                return ['value' => 'fresh'];
            }
        );

        $this->assertSame(['value' => 'cached'], $result);
        $this->assertSame(0, $produced);
    }

    #[Group('companyProfile')]
    #[Group('fundCatalog')]
    public function test_a_miss_runs_the_producer_once_and_returns_its_result(): void
    {
        $produced = 0;

        $result = SingleFlight::run(
            'k2',
            fn () => null,
            function () use (&$produced) {
                $produced++;

                return ['value' => 'fresh'];
            }
        );

        $this->assertSame(['value' => 'fresh'], $result);
        $this->assertSame(1, $produced);
    }

    #[Group('companyProfile')]
    #[Group('fundCatalog')]
    public function test_the_cache_is_rechecked_after_the_lock_so_a_concurrent_winner_is_reused(): void
    {
        // Simulates "another request filled the cache while we waited for the lock":
        // 1st check (before the lock) misses, 2nd check (after acquiring it) hits.
        $checks = 0;
        $produced = 0;

        $result = SingleFlight::run(
            'k3',
            function () use (&$checks) {
                return ++$checks === 1 ? null : ['value' => 'filled-by-someone-else'];
            },
            function () use (&$produced) {
                $produced++;

                return ['value' => 'duplicate work'];
            }
        );

        $this->assertSame(['value' => 'filled-by-someone-else'], $result);
        $this->assertSame(0, $produced);
    }

    #[Group('companyProfile')]
    #[Group('fundCatalog')]
    public function test_the_lock_is_released_even_when_the_producer_throws(): void
    {
        try {
            SingleFlight::run('k4', fn () => null, fn () => throw new \RuntimeException('boom'));
            $this->fail('exception should propagate');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        // A later caller must be able to take the lock straight away (would hang/timeout if leaked).
        $this->assertSame(['ok' => true], SingleFlight::run('k4', fn () => null, fn () => ['ok' => true], 1));
    }
}
