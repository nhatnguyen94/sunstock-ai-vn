<?php

namespace App\Backend\Services;

use App\Models\AiRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Everything that needs an admin's attention, gathered for the bell in the top bar: a dead scheduler, late data sources, failed jobs,
 * a failing AI, a missing or old backup, a nearly full disk, debug mode in production, an address guessing passwords.
 * Computed at most once a minute (the bell is on every admin page); each alert names the permission needed to see it, so a
 * role only sees what it may open. Cheap by construction: no table is counted.
 */
class AdminAlertsService
{
    private const CACHE_KEY = 'admin:alerts';

    public function __construct(
        private readonly SystemHealthService $health,
        private readonly SyncSourcesService $sources,
        private readonly SecurityService $security,
    ) {}

    /**
     * @return array<int, array{key: string, level: string, title: string, detail: string, route: string, perm: string}>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, fn () => $this->compute());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<int, array{key: string, level: string, title: string, detail: string, route: string, perm: string}> */
    private function compute(): array
    {
        $alerts = [];
        $add = function (string $key, string $level, string $title, string $detail, string $route, string $perm) use (&$alerts) {
            $alerts[] = compact('key', 'level', 'title', 'detail', 'route', 'perm');
        };
        $guard = function (callable $check) {
            try {
                $check();
            } catch (Throwable) {
                // one failing check must never hide the others, or break the page
            }
        };

        $guard(function () use ($add) {
            $s = $this->health->scheduler();
            if ($s['state'] === 'off') {
                $add('scheduler', 'danger', 'Chưa thấy nhịp tim của scheduler', 'Không có lệnh nào chạy theo lịch nếu scheduler không chạy (schedule:work / cron).', 'admin.health', 'manage-features');
            } elseif ($s['state'] === 'bad') {
                $add('scheduler', 'danger', 'Scheduler đã ngừng', "Nhịp tim cuối cách đây {$s['minutes']} phút.", 'admin.health', 'manage-features');
            }
        });

        $guard(function () use ($add) {
            $late = collect($this->sources->build(false)['sources'])->whereIn('state', ['late', 'off']);
            if ($late->isNotEmpty()) {
                $add('sources', 'warning', $late->count().' nguồn dữ liệu trễ', $late->pluck('label')->take(4)->implode(', ').($late->count() > 4 ? '…' : ''), 'admin.sync-status', 'manage-features');
            }
        });

        $guard(function () use ($add) {
            $failed = (int) DB::table('failed_jobs')->count();
            if ($failed > 0) {
                $add('failed-jobs', 'warning', "{$failed} job thất bại", 'Cần xem lại hoặc thử lại.', 'admin.queue.index', 'manage-queue');
            }
        });

        $guard(function () use ($add) {
            $since = now()->subDay();
            $ok = AiRequest::where('created_at', '>=', $since)->where('status', AiRequest::STATUS_OK)->count();
            $error = AiRequest::where('created_at', '>=', $since)->where('status', AiRequest::STATUS_ERROR)->count();
            if ($ok + $error >= 5 && $ok / ($ok + $error) < 0.7) {
                $add('ai', 'warning', 'AI lỗi nhiều', "{$error}/".($ok + $error).' lượt gọi 24 giờ qua thất bại.', 'admin.ai.index', 'manage-features');
            }
        });

        $guard(function () use ($add) {
            $age = $this->health->backupAgeDays();
            if ($age === null) {
                $add('backup', 'warning', 'Chưa có bản sao lưu database', 'Chưa tìm thấy bản sao lưu hợp lệ nào.', 'admin.health', 'manage-features');
            } elseif ($age > 14) {
                $add('backup', 'warning', 'Bản sao lưu đã cũ', "Bản mới nhất cách đây {$age} ngày.", 'admin.health', 'manage-features');
            }
        });

        $guard(function () use ($add) {
            $disk = $this->health->disk();
            if ($disk && $disk['free_percent'] < 10) {
                $add('disk', 'danger', 'Ổ đĩa sắp đầy', 'Còn '.$disk['free_percent'].'% dung lượng trống.', 'admin.health', 'manage-features');
            }
        });

        $guard(function () use ($add) {
            if (config('app.debug') && config('app.env') === 'production') {
                $add('debug', 'danger', 'APP_DEBUG đang bật ở production', 'Lỗi hiển thị chi tiết ra ngoài, có thể lộ cấu hình.', 'admin.health', 'manage-features');
            }
        });

        $guard(function () use ($add) {
            $n = $this->security->suspiciousIps()->count();
            if ($n > 0) {
                $add('suspicious-ips', 'warning', "{$n} địa chỉ IP nghi dò mật khẩu", 'Nhiều lần đăng nhập sai trong 1 giờ qua.', 'admin.security.index', 'manage-users');
            }
        });

        return $alerts;
    }
}
