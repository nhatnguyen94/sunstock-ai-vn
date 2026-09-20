<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--force : Back up now even if a backup from the last period exists}
        {--days=7 : A valid backup younger than this many days means "already done"}
        {--list : Only list the backups that exist}';

    protected $description = 'Weekly mysqldump of the database into <parent of the project>/database_backup/<year>/<month>/<day>/<db>_db.zip (skipped when one exists from the last 7 days).';

    public function handle(DatabaseBackup $backup): int
    {
        $this->line('Thư mục backup: ' . $backup->root());

        if ($this->option('list')) {
            $all = $backup->all();
            if (! $all) {
                $this->warn('Chưa có bản backup nào.');

                return 0;
            }
            foreach ($all as $b) {
                $this->line(sprintf('  %s  %s  %s', date('Y-m-d H:i', $b['modified']), str_pad($this->size($b['size']), 9), $b['path']));
            }

            return 0;
        }

        $days = max(1, (int) $this->option('days'));

        if (! $this->option('force') && $backup->isFresh($days)) {
            $latest = $backup->latest();
            $this->info(sprintf('Đã có backup trong %d ngày qua (%s, %s) — bỏ qua.', $days, date('Y-m-d H:i', $latest['modified']), $this->size($latest['size'])));

            return 0;
        }

        $this->info('Đang backup database…');

        try {
            $r = $backup->run();
        } catch (\Throwable $e) {
            $this->error('Backup thất bại: ' . $e->getMessage());

            return 1;
        }

        $this->info(sprintf('Đã backup: %s (%s, SQL gốc %s, %s giây)', $r['path'], $this->size($r['size']), $this->size($r['sql_size']), $r['seconds']));

        return 0;
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : number_format($bytes / 1024, 0) . ' KB';
    }
}
