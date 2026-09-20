<?php

namespace Tests\Feature\Support;

use App\Support\DatabaseBackup;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use ZipArchive;

/**
 * The real folder layout, zip creation, validation and the "once a week" rule run against a temp directory. Only the
 * mysqldump process is faked (a stub writes what mysqldump would: SQL ending in "-- Dump completed").
 */
class DatabaseBackupTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/dbbackup-' . bin2hex(random_bytes(4));
        config(['database.connections.mysql.database' => 'stock_app']);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
            is_dir("$dir/$f") ? $this->rrmdir("$dir/$f") : unlink("$dir/$f");
        }
        rmdir($dir);
    }

    /** A DatabaseBackup whose "mysqldump" writes `$sql` (or fails). */
    private function backup(string $sql = "-- MySQL dump\nCREATE TABLE t (id int);\nINSERT INTO t VALUES (1);\n-- Dump completed on 2026-09-20  7:52:44\n", bool $fail = false): DatabaseBackup
    {
        return new class($this->root, $sql, $fail) extends DatabaseBackup {
            public int $dumps = 0;

            public function __construct(string $root, private string $sql, private bool $fail)
            {
                parent::__construct($root);
            }

            protected function dump(string $target): void
            {
                $this->dumps++;
                if ($this->fail) {
                    throw new \RuntimeException('mysqldump lỗi: Access denied');
                }
                file_put_contents($target, $this->sql);
            }
        };
    }

    private function place(string $relative, int $ageDays, bool $validSql = true): string
    {
        $path = $this->root . '/' . $relative;
        @mkdir(dirname($path), 0775, true);
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('stock_app_db.sql', $validSql ? '-- data' : '');
        $zip->close();
        touch($path, time() - $ageDays * 86400);

        return $path;
    }

    // ── layout ──────────────────────────────────────────────────────────────

    #[Group('dbBackup')]
    public function test_a_backup_lands_in_year_month_day_without_zero_padding_and_the_zip_holds_the_sql(): void
    {
        $r = $this->backup()->run(Carbon::parse('2026-09-05 10:00:00'));

        $this->assertSame($this->root . '/2026/9/5/stock_app_db.zip', $r['path']);
        $this->assertFileExists($r['path']);

        $zip = new ZipArchive;
        $zip->open($r['path']);
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame('stock_app_db.sql', $zip->getNameIndex(0));
        $this->assertStringContainsString('INSERT INTO t VALUES (1)', $zip->getFromName('stock_app_db.sql'));
        $zip->close();
    }

    #[Group('dbBackup')]
    public function test_the_zip_is_named_after_the_configured_database(): void
    {
        config(['database.connections.mysql.database' => 'shop_prod']);

        $r = $this->backup()->run(Carbon::parse('2026-01-02'));

        $this->assertSame($this->root . '/2026/1/2/shop_prod_db.zip', $r['path']);
    }

    #[Group('dbBackup')]
    public function test_no_temporary_files_are_left_behind(): void
    {
        $this->backup()->run(Carbon::parse('2026-09-20'));

        $this->assertSame(['2026'], array_values(array_diff(scandir($this->root), ['.', '..'])));   // no .tmp-* folder
    }

    #[Group('dbBackup')]
    public function test_the_default_root_is_a_database_backup_folder_beside_the_project(): void
    {
        config(['backup.path' => null]);

        $this->assertSame(dirname(base_path()) . DIRECTORY_SEPARATOR . 'database_backup', (new DatabaseBackup)->root());

        config(['backup.path' => '/custom/place/']);
        $this->assertSame('/custom/place', (new DatabaseBackup)->root());
    }

    // ── once a week ─────────────────────────────────────────────────────────

    #[Group('dbBackup')]
    public function test_a_valid_backup_from_the_last_week_counts_as_fresh(): void
    {
        $this->place('2026/9/18/stock_app_db.zip', 2);

        $b = $this->backup();

        $this->assertTrue($b->isFresh(7));
        $this->assertSame($this->root . '/2026/9/18/stock_app_db.zip', $b->latest()['path']);
    }

    #[Group('dbBackup')]
    public function test_a_backup_older_than_a_week_is_stale_and_the_newest_one_is_the_reference(): void
    {
        $this->place('2026/9/1/stock_app_db.zip', 19);
        $this->place('2026/9/10/stock_app_db.zip', 10);

        $b = $this->backup();

        $this->assertFalse($b->isFresh(7));
        $this->assertSame($this->root . '/2026/9/10/stock_app_db.zip', $b->latest()['path']);
        $this->assertTrue($b->isFresh(14));
    }

    #[Group('dbBackup')]
    public function test_broken_or_empty_zips_never_count_as_a_backup(): void
    {
        $this->place('2026/9/19/stock_app_db.zip', 1, false);                   // zip with an empty .sql
        @mkdir($this->root . '/2026/9/20', 0775, true);
        file_put_contents($this->root . '/2026/9/20/stock_app_db.zip', 'not a zip at all');
        file_put_contents($this->root . '/2026/9/17_stray.zip', 'x');            // wrong depth: ignored

        $b = $this->backup();

        $this->assertNull($b->latest());
        $this->assertFalse($b->isFresh(7));
    }

    #[Group('dbBackup')]
    public function test_no_backup_folder_at_all_is_simply_stale(): void
    {
        $this->assertNull($this->backup()->latest());
        $this->assertFalse($this->backup()->isFresh());
        $this->assertSame([], $this->backup()->all());
    }

    // ── failure handling ────────────────────────────────────────────────────

    #[Group('dbBackup')]
    public function test_a_truncated_dump_is_rejected_and_leaves_nothing_that_looks_like_a_backup(): void
    {
        $b = $this->backup("CREATE TABLE t (id int);\nINSERT INTO t VALUES (1);\n");   // no "Dump completed"

        try {
            $b->run(Carbon::parse('2026-09-20'));
            $this->fail('a truncated dump must not be accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('bị cụt', $e->getMessage());
        }

        $this->assertNull($b->latest());
        $this->assertFalse(file_exists($this->root . '/2026/9/20/stock_app_db.zip'));
        $this->assertSame([], glob($this->root . '/.tmp-*') ?: []);
    }

    #[Group('dbBackup')]
    public function test_an_empty_dump_is_an_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rỗng');

        $this->backup('')->run(Carbon::parse('2026-09-20'));
    }

    #[Group('dbBackup')]
    public function test_a_failing_mysqldump_is_an_error_with_its_message(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $this->backup(fail: true)->run(Carbon::parse('2026-09-20'));
    }

    #[Group('dbBackup')]
    public function test_a_failed_run_does_not_destroy_the_previous_backup(): void
    {
        $old = $this->place('2026/9/20/stock_app_db.zip', 0);
        $before = md5_file($old);

        try {
            $this->backup(fail: true)->run(Carbon::parse('2026-09-21'));
        } catch (\RuntimeException) {
        }

        $this->assertFileExists($old);
        $this->assertSame($before, md5_file($old));
    }

    // ── command ─────────────────────────────────────────────────────────────

    #[Group('dbBackup')]
    public function test_the_command_skips_when_a_backup_from_the_last_week_exists(): void
    {
        $stub = $this->backup();
        $this->app->instance(DatabaseBackup::class, $stub);
        $this->place('2026/9/18/stock_app_db.zip', 2);

        $this->artisan('db:backup')->expectsOutputToContain('bỏ qua')->assertExitCode(0);

        $this->assertSame(0, $stub->dumps);
    }

    #[Group('dbBackup')]
    public function test_the_command_backs_up_when_stale_and_force_overrides_the_weekly_rule(): void
    {
        $stub = $this->backup();
        $this->app->instance(DatabaseBackup::class, $stub);

        $this->artisan('db:backup')->expectsOutputToContain('Đã backup')->assertExitCode(0);
        $this->assertSame(1, $stub->dumps);

        $this->artisan('db:backup')->expectsOutputToContain('bỏ qua')->assertExitCode(0);     // now fresh
        $this->assertSame(1, $stub->dumps);

        $this->artisan('db:backup', ['--force' => true])->expectsOutputToContain('Đã backup')->assertExitCode(0);
        $this->assertSame(2, $stub->dumps);
    }

    #[Group('dbBackup')]
    public function test_the_command_exits_with_an_error_when_the_dump_fails(): void
    {
        $this->app->instance(DatabaseBackup::class, $this->backup(fail: true));

        $this->artisan('db:backup')->expectsOutputToContain('Backup thất bại')->assertExitCode(1);
    }

    #[Group('dbBackup')]
    public function test_the_list_option_shows_existing_backups_without_touching_anything(): void
    {
        $stub = $this->backup();
        $this->app->instance(DatabaseBackup::class, $stub);
        $this->place('2026/9/10/stock_app_db.zip', 10);

        $this->artisan('db:backup', ['--list' => true])->expectsOutputToContain('2026/9/10/stock_app_db.zip')->assertExitCode(0);

        $this->assertSame(0, $stub->dumps);
    }

    #[Group('dbBackup')]
    public function test_the_backup_is_scheduled_hourly_and_the_entrypoint_runs_it_first(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('db:backup')->assertExitCode(0);

        $event = collect($this->app->make(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'db:backup'));

        $this->assertNotNull($event, 'db:backup must be scheduled');
        $this->assertSame('0 * * * *', $event->expression);

        $entry = file_get_contents(base_path('docker/php/scheduler-entrypoint.sh'));
        $this->assertLessThan(strpos($entry, 'sync:stock-data'), strpos($entry, 'db:backup'));
    }
}
