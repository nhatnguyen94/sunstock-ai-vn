<?php

namespace Tests\Feature\Support;

use App\Jobs\SyncCompanyFinancialJob;
use App\Models\QueueJobLog;
use App\Support\QueueJobLogger;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * "Feature" because it writes through the real Eloquent model to the real
 * (RefreshDatabase) `queue_job_logs` table. The `Illuminate\Contracts\Queue\Job`
 * is faked with a plain anonymous class (not Mockery) since it needs to
 * return a genuinely serialized job object from payload() — extractSummary()
 * really unserializes it and calls queueSummary(), the exact same code path
 * a live queue worker runs.
 */
class QueueJobLoggerTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private function fakeJob(string $jobId, string $class, string $queue, ?object $command): object
    {
        return new class($jobId, $class, $queue, $command) implements \Illuminate\Contracts\Queue\Job {
            public function __construct(
                private string $jobId,
                private string $class,
                private string $queue,
                private ?object $command,
            ) {}

            public function uuid() { return $this->jobId; }
            public function getJobId() { return $this->jobId; }
            public function payload()
            {
                return [
                    'displayName' => $this->class,
                    'data' => ['command' => $this->command ? serialize($this->command) : null],
                ];
            }
            public function fire() {}
            public function release($delay = 0) {}
            public function isReleased() { return false; }
            public function delete() {}
            public function isDeleted() { return false; }
            public function isDeletedOrReleased() { return false; }
            public function attempts() { return 1; }
            public function hasFailed() { return false; }
            public function markAsFailed() {}
            public function fail($e = null) {}
            public function maxTries() { return null; }
            public function maxExceptions() { return null; }
            public function timeout() { return null; }
            public function retryUntil() { return null; }
            public function getName() { return $this->class; }
            public function resolveName() { return $this->class; }
            public function resolveQueuedJobClass() { return $this->class; }
            public function getConnectionName() { return 'redis'; }
            public function getQueue() { return $this->queue; }
            public function getRawBody() { return ''; }
        };
    }

    #[Group('queueMonitor')]
    public function test_processing_creates_a_log_row_with_extracted_summary(): void
    {
        $job = $this->fakeJob('job-1', SyncCompanyFinancialJob::class, 'default', new SyncCompanyFinancialJob('VCB'));

        QueueJobLogger::processing(new JobProcessing('redis', $job));

        $this->assertDatabaseHas('queue_job_logs', [
            'job_id' => 'job-1',
            'job_class' => SyncCompanyFinancialJob::class,
            'queue' => 'default',
            'summary' => 'VCB',
            'status' => 'processing',
        ]);
    }

    #[Group('queueMonitor')]
    public function test_processing_falls_back_to_null_summary_when_job_has_no_queue_summary_method(): void
    {
        $job = $this->fakeJob('job-2', 'App\\Jobs\\SomeJobWithoutSummary', 'high', null);

        QueueJobLogger::processing(new JobProcessing('redis', $job));

        $this->assertDatabaseHas('queue_job_logs', ['job_id' => 'job-2', 'summary' => null]);
    }

    #[Group('queueMonitor')]
    public function test_processed_marks_the_matching_log_completed_with_duration(): void
    {
        $job = $this->fakeJob('job-3', SyncCompanyFinancialJob::class, 'default', new SyncCompanyFinancialJob('ACB'));
        QueueJobLogger::processing(new JobProcessing('redis', $job));

        QueueJobLogger::processed(new JobProcessed('redis', $job));

        $log = DB::table('queue_job_logs')->where('job_id', 'job-3')->first();
        $this->assertSame('completed', $log->status);
        $this->assertNotNull($log->finished_at);
        $this->assertNotNull($log->duration_ms);
    }

    #[Group('queueMonitor')]
    public function test_failed_marks_the_matching_log_failed(): void
    {
        $job = $this->fakeJob('job-4', SyncCompanyFinancialJob::class, 'default', new SyncCompanyFinancialJob('FPT'));
        QueueJobLogger::processing(new JobProcessing('redis', $job));

        QueueJobLogger::failed(new JobFailed('redis', $job, new \Exception('boom')));

        $this->assertDatabaseHas('queue_job_logs', ['job_id' => 'job-4', 'status' => 'failed']);
    }

    #[Group('queueMonitor')]
    public function test_processed_for_an_unknown_job_id_does_not_throw_or_create_a_row(): void
    {
        $job = $this->fakeJob('never-started', SyncCompanyFinancialJob::class, 'default', new SyncCompanyFinancialJob('HPG'));

        QueueJobLogger::processed(new JobProcessed('redis', $job));

        $this->assertDatabaseCount('queue_job_logs', 0);
    }

    #[Group('queueMonitor')]
    public function test_queue_summary_extraction_for_all_three_job_types(): void
    {
        $stockChunkJob = $this->fakeJob('job-5', \App\Jobs\ProcessStockPriceSync::class, 'default', new \App\Jobs\ProcessStockPriceSync([
            ['symbol' => 'VCB'], ['symbol' => 'ACB'],
        ]));
        QueueJobLogger::processing(new JobProcessing('redis', $stockChunkJob));
        $this->assertDatabaseHas('queue_job_logs', ['job_id' => 'job-5', 'summary' => '2 mã (VCB, ACB)']);

        $backfillJob = $this->fakeJob('job-6', \App\Jobs\BackfillStockPriceChunk::class, 'default', new \App\Jobs\BackfillStockPriceChunk(
            [['symbol' => 'VCB']], '2026-01-01', '2026-01-31'
        ));
        QueueJobLogger::processing(new JobProcessing('redis', $backfillJob));
        $this->assertDatabaseHas('queue_job_logs', ['job_id' => 'job-6', 'summary' => '1 mã (VCB) 2026-01-01→2026-01-31']);
    }
}
