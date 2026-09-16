<?php

namespace Tests\Feature\Backend\Controllers;

use App\Models\Permission;
use App\Models\QueueJobLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real end-to-end (RefreshDatabase + a real Redis connection, already
 * available in this environment) — genuinely needed: queue stats come from
 * the actual Redis connection (Queue::connection('redis')->pendingSize()
 * etc.), and retry/delete need to round-trip real `failed_jobs` rows through
 * the real `queue:retry`/`queue:forget` artisan commands. See docs/TESTING.md.
 *
 * Fixture failed jobs use `queue => 'test'` (not `high`/`default`, the only
 * two queues the real supervisor workers poll — see docker/php/supervisord.conf)
 * so a retried job sits harmlessly in Redis instead of being picked up and
 * actually executed by the live queue workers running alongside these tests.
 */
class QueueMonitorControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @see RoleControllerTest::actingAsUserWithPermissions() for why the role name matters (backend-access role). */
    private function actingAsUserWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => Role::WEBADMIN, 'display_name' => 'Web Admin']);

        if (!empty($permissionNames)) {
            $ids = collect($permissionNames)->map(
                fn ($name) => Permission::create(['name' => $name, 'display_name' => $name])->id
            );
            $role->permissions()->sync($ids);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        return $user;
    }

    private function insertFailedJob(): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'redis',
            'queue' => 'test',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\SyncCompanyFinancialJob', 'job' => 'x', 'data' => []]),
            'exception' => "Illuminate\\Queue\\MaxAttemptsExceededException: test\nline2",
            'failed_at' => now(),
        ]);

        return $uuid;
    }

    #[Group('queueMonitor')]
    public function test_user_without_manage_queue_permission_is_forbidden(): void
    {
        $this->actingAsUserWithPermissions([]);

        $response = $this->get('/admin/queue');

        $response->assertForbidden();
    }

    #[Group('queueMonitor')]
    public function test_index_shows_queue_names_and_failed_jobs_for_permitted_user(): void
    {
        $this->actingAsUserWithPermissions(['manage-queue']);
        $this->insertFailedJob();

        $response = $this->get('/admin/queue');

        $response->assertOk();
        $response->assertSee('high');
        $response->assertSee('default');
        $response->assertSee('SyncCompanyFinancialJob');
    }

    #[Group('queueMonitor')]
    public function test_stats_endpoint_returns_json_with_pending_reserved_delayed_per_queue(): void
    {
        $this->actingAsUserWithPermissions(['manage-queue']);

        $response = $this->get('/admin/queue/stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'queues' => [['name', 'pending', 'delayed', 'reserved']],
            'processing',
            'recent',
            'processedToday',
        ]);
        $names = collect($response->json('queues'))->pluck('name');
        $this->assertTrue($names->contains('high'));
        $this->assertTrue($names->contains('default'));
    }

    #[Group('queueMonitor')]
    public function test_stats_endpoint_reports_currently_processing_and_recently_finished_jobs(): void
    {
        $this->actingAsUserWithPermissions(['manage-queue']);
        QueueJobLog::create([
            'job_id' => 'p1', 'job_class' => 'App\\Jobs\\SyncCompanyFinancialJob', 'queue' => 'default',
            'summary' => 'VCB', 'status' => 'processing', 'started_at' => now()->subSeconds(10),
        ]);
        QueueJobLog::create([
            'job_id' => 'p2', 'job_class' => 'App\\Jobs\\SyncCompanyFinancialJob', 'queue' => 'default',
            'summary' => 'ACB', 'status' => 'completed',
            'started_at' => now()->subSeconds(20), 'finished_at' => now()->subSeconds(5), 'duration_ms' => 15000,
        ]);

        $response = $this->get('/admin/queue/stats');

        $response->assertOk();
        $response->assertJsonFragment(['summary' => 'VCB']);
        $response->assertJsonFragment(['summary' => 'ACB', 'status' => 'completed']);
        $this->assertSame(1, $response->json('processedToday'));
    }

    #[Group('queueMonitor')]
    public function test_index_page_shows_currently_processing_job(): void
    {
        $this->actingAsUserWithPermissions(['manage-queue']);
        QueueJobLog::create([
            'job_id' => 'p3', 'job_class' => 'App\\Jobs\\SyncCompanyFinancialJob', 'queue' => 'default',
            'summary' => 'HPG', 'status' => 'processing', 'started_at' => now(),
        ]);

        $response = $this->get('/admin/queue');

        $response->assertOk();
        // The processing table itself is filled client-side via JS from /admin/queue/stats,
        // so we only assert the page loaded with the data the JS will fetch — see the
        // stats-endpoint tests above for the actual content assertions.
        $response->assertSee('Đang xử lý (real-time)');
    }

    #[Group('queueMonitor')]
    public function test_retry_requeues_a_failed_job_and_removes_it_from_the_failed_list(): void
    {
        $this->actingAsUserWithPermissions(['manage-queue']);
        $uuid = $this->insertFailedJob();

        $response = $this->post("/admin/queue/failed/{$uuid}/retry");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }

    #[Group('queueMonitor')]
    public function test_retry_returns_404_for_unknown_uuid(): void
    {
        $this->actingAsUserWithPermissions(['manage-queue']);

        $response = $this->post('/admin/queue/failed/not-a-real-uuid/retry');

        $response->assertNotFound();
        $response->assertJson(['success' => false]);
    }

    #[Group('queueMonitor')]
    public function test_destroy_deletes_a_failed_job(): void
    {
        $this->actingAsUserWithPermissions(['manage-queue']);
        $uuid = $this->insertFailedJob();

        $response = $this->delete("/admin/queue/failed/{$uuid}");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }

    #[Group('queueMonitor')]
    public function test_destroy_returns_404_for_unknown_uuid(): void
    {
        $this->actingAsUserWithPermissions(['manage-queue']);

        $response = $this->delete('/admin/queue/failed/not-a-real-uuid');

        $response->assertNotFound();
    }

    #[Group('queueMonitor')]
    public function test_retry_all_requeues_every_failed_job(): void
    {
        $this->actingAsUserWithPermissions(['manage-queue']);
        $this->insertFailedJob();
        $this->insertFailedJob();

        $response = $this->post('/admin/queue/failed/retry-all');

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseCount('failed_jobs', 0);
    }
}
