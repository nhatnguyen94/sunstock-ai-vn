<?php

namespace Tests;

use App\Jobs\BuildStockSignalsJob;
use App\Jobs\SyncWorldMarketsJob;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The home page asks for these two background builds whenever its cache is empty. The test queue connection is "sync", so without
        // this they would run for real inside any test that renders the page: a Python call to the network and a heavy query. Only these two
        // are faked (every other job still runs as before); tests of the jobs and services call the services directly.
        Queue::fake([SyncWorldMarketsJob::class, BuildStockSignalsJob::class]);
    }
}
