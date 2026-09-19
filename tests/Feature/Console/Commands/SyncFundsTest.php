<?php

namespace Tests\Feature\Console\Commands;

use App\Frontend\Services\FundService;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class SyncFundsTest extends TestCase
{
    #[Group('fundCatalog')]
    public function test_command_reports_how_many_funds_were_synced(): void
    {
        $mock = Mockery::mock(FundService::class);
        $mock->shouldReceive('syncAll')->once()->andReturn(['count' => 68]);
        $this->app->instance(FundService::class, $mock);

        $this->artisan('sync:funds')
            ->expectsOutputToContain('Synced 68 funds')
            ->assertExitCode(0);
    }

    #[Group('fundCatalog')]
    public function test_command_fails_with_a_non_zero_exit_code_when_fmarket_is_unreachable(): void
    {
        $mock = Mockery::mock(FundService::class);
        $mock->shouldReceive('syncAll')->once()->andReturn(['error' => 'Fmarket down']);
        $this->app->instance(FundService::class, $mock);

        $this->artisan('sync:funds')
            ->expectsOutputToContain('Fmarket down')
            ->assertExitCode(1);
    }
}
