<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Weekly database backup: mysqldump → `<root>/<year>/<month>/<day>/<db>_db.zip` (the zip holds `<db>_db.sql`).
 *
 * `<root>` is a folder NEXT TO the source tree (default: `<parent of base_path()>/database_backup`), i.e. outside the
 * project and — through the compose bind mount — outside Docker's own storage, so deleting containers, images or even
 * volumes cannot take the backups with it. Month and day are not zero-padded (2026/9/20).
 *
 * Only one backup per period: a valid zip newer than `$days` days anywhere under the root means "already backed up".
 * Nothing is ever deleted here.
 */
class DatabaseBackup
{
    public function __construct(private readonly ?string $root = null) {}

    /** Configured root, or a `database_backup` folder beside the project directory. */
    public function root(): string
    {
        $root = $this->root ?? config('backup.path');

        return rtrim($root ?: dirname(base_path()) . DIRECTORY_SEPARATOR . 'database_backup', '/\\');
    }

    /** Base name shared by the zip and the sql inside it: `<database>_db`. */
    public function baseName(): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) config('database.connections.mysql.database', 'database')) . '_db';
    }

    /**
     * Newest VALID backup (a readable zip that really contains a .sql), or null.
     *
     * @return array{path: string, modified: int, size: int}|null
     */
    public function latest(): ?array
    {
        $best = null;

        foreach (glob($this->root() . '/*/*/*/*.zip') ?: [] as $zip) {
            $mtime = (int) filemtime($zip);
            if (($best === null || $mtime > $best['modified']) && $this->isValidBackup($zip)) {
                $best = ['path' => $zip, 'modified' => $mtime, 'size' => (int) filesize($zip)];
            }
        }

        return $best;
    }

    /** True when a valid backup younger than `$days` days exists. */
    public function isFresh(int $days = 7, ?int $now = null): bool
    {
        $latest = $this->latest();

        return $latest !== null && ($now ?? time()) - $latest['modified'] < $days * 86400;
    }

    /** Every backup found, newest first (for status output). @return array<int, array{path: string, modified: int, size: int}> */
    public function all(): array
    {
        $out = [];
        foreach (glob($this->root() . '/*/*/*/*.zip') ?: [] as $zip) {
            $out[] = ['path' => $zip, 'modified' => (int) filemtime($zip), 'size' => (int) filesize($zip)];
        }
        usort($out, fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $out;
    }

    /**
     * Take a backup now.
     *
     * @return array{path: string, size: int, sql_size: int, seconds: float}
     * @throws \RuntimeException when the dump, the zip or the checks fail (nothing half-written is left behind)
     */
    public function run(?\DateTimeInterface $at = null): array
    {
        $started = microtime(true);
        $at ??= now();
        $dir = $this->root() . '/' . $at->format('Y') . '/' . $at->format('n') . '/' . $at->format('j');
        $final = $dir . '/' . $this->baseName() . '.zip';
        $tmpDir = $this->root() . '/.tmp-' . getmypid() . '-' . bin2hex(random_bytes(3));
        $sql = $tmpDir . '/' . $this->baseName() . '.sql';
        $tmpZip = $tmpDir . '/' . $this->baseName() . '.zip';

        foreach ([$dir, $tmpDir] as $d) {
            if (! is_dir($d) && ! @mkdir($d, 0775, true) && ! is_dir($d)) {
                throw new \RuntimeException("Không tạo được thư mục backup: {$d} (kiểm tra thư mục đã được mount vào container chưa)");
            }
        }

        try {
            $this->dump($sql);
            $sqlSize = (int) filesize($sql);
            $this->assertCompleteDump($sql);
            $this->zip($sql, $tmpZip);

            if (! $this->isValidBackup($tmpZip)) {
                throw new \RuntimeException('File zip tạo ra không hợp lệ.');
            }
            if (! @rename($tmpZip, $final) && ! (@copy($tmpZip, $final) && @unlink($tmpZip))) {
                throw new \RuntimeException("Không ghi được file backup: {$final}");
            }
        } catch (\Throwable $e) {
            Log::error('db:backup failed', ['error' => $e->getMessage()]);
            @unlink($final);   // never leave a half-written zip that the freshness check could mistake for a backup
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        } finally {
            $this->removeDir($tmpDir);
        }

        return ['path' => $final, 'size' => (int) filesize($final), 'sql_size' => $sqlSize, 'seconds' => round(microtime(true) - $started, 1)];
    }

    // ── internals ───────────────────────────────────────────────────────────

    /** Write a consistent mysqldump of the configured database to `$target`. Protected so tests can stub the process. */
    protected function dump(string $target): void
    {
        $c = config('database.connections.mysql');
        $args = [
            $this->binary(),
            '--host=' . $c['host'], '--port=' . ($c['port'] ?? 3306), '--user=' . $c['username'],
            '--single-transaction', '--quick', '--routines', '--triggers', '--events', '--no-tablespaces',
            '--default-character-set=utf8mb4', '--result-file=' . $target,
            $c['database'],
        ];

        // Password through the environment, never on the command line (visible in `ps`)
        $process = new Process($args, null, ['MYSQL_PWD' => (string) ($c['password'] ?? '')], null, 3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysqldump lỗi: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function binary(): string
    {
        foreach (['mysqldump', 'mariadb-dump'] as $bin) {
            $probe = new Process(PHP_OS_FAMILY === 'Windows' ? ['where', $bin] : ['which', $bin]);
            $probe->run();
            if ($probe->isSuccessful()) {
                return trim(explode("\n", $probe->getOutput())[0]);
            }
        }

        throw new \RuntimeException('Không tìm thấy mysqldump (cài default-mysql-client — đã có sẵn trong docker/php/Dockerfile, cần `docker compose up -d --build`).');
    }

    /** mysqldump ends a finished dump with "-- Dump completed": without it the file is truncated. */
    protected function assertCompleteDump(string $sql): void
    {
        $size = (int) filesize($sql);
        if ($size === 0) {
            throw new \RuntimeException('File dump rỗng.');
        }

        $fh = fopen($sql, 'rb');
        fseek($fh, max(0, $size - 512));
        $tail = (string) fread($fh, 512);
        fclose($fh);

        if (! str_contains($tail, 'Dump completed')) {
            throw new \RuntimeException('File dump bị cụt (không thấy dòng "Dump completed").');
        }
    }

    private function zip(string $sql, string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Không tạo được file zip.');
        }
        $zip->addFile($sql, basename($sql));
        $zip->setCompressionName(basename($sql), ZipArchive::CM_DEFLATE, 6);
        if (! $zip->close()) {
            throw new \RuntimeException('Không ghi được file zip (hết dung lượng?).');
        }
    }

    /** A readable zip with at least one non-empty .sql entry. */
    public function isValidBackup(string $zipPath): bool
    {
        if (! is_file($zipPath) || filesize($zipPath) === 0) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return false;
        }

        $ok = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat && str_ends_with(strtolower($stat['name']), '.sql') && $stat['size'] > 0) {
                $ok = true;
                break;
            }
        }
        $zip->close();

        return $ok;
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
