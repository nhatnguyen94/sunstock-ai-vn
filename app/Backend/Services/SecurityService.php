<?php

namespace App\Backend\Services;

use App\Http\Middleware\BlockedIps;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Sign-in history, addresses that look like password guessing, and the list of blocked addresses (Admin > Bảo mật). */
class SecurityService
{
    /** Failed attempts from ONE address within an hour that make it "suspicious". */
    public const SUSPICIOUS_FAILURES = 10;

    /** @return array{ok_24h: int, failed_24h: int, ips_failed_24h: int, suspicious: int, blocked: int} */
    public function overview(): array
    {
        $since = now()->subDay();
        $base = fn () => LoginAttempt::query()->where('created_at', '>=', $since);

        return [
            'ok_24h' => $base()->where('success', true)->count(),
            'failed_24h' => $base()->where('success', false)->count(),
            'ips_failed_24h' => (int) $base()->where('success', false)->whereNotNull('ip')->distinct()->count('ip'),
            'suspicious' => $this->suspiciousIps()->count(),
            'blocked' => BlockedIp::count(),
        ];
    }

    /**
     * Addresses with many failed attempts in the last hour that are not blocked yet.
     *
     * @return Collection<int, object{ip: string, failures: int, emails: int, last_at: string}>
     */
    public function suspiciousIps(): Collection
    {
        return LoginAttempt::query()
            ->where('success', false)
            ->whereNotNull('ip')
            ->where('created_at', '>=', now()->subHour())
            ->whereNotIn('ip', BlockedIp::query()->select('ip'))
            ->select('ip', DB::raw('count(*) as failures'), DB::raw('count(distinct email) as emails'), DB::raw('max(created_at) as last_at'))
            ->groupBy('ip')
            ->havingRaw('count(*) >= ?', [self::SUSPICIOUS_FAILURES])
            ->orderByDesc('failures')
            ->limit(20)
            ->get();
    }

    /** @return Collection<int, LoginAttempt> */
    public function recent(bool $failedOnly = false, int $limit = 50): Collection
    {
        return LoginAttempt::query()
            ->when($failedOnly, fn ($q) => $q->where('success', false))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, BlockedIp> */
    public function blocked(): Collection
    {
        return BlockedIp::query()->orderByDesc('id')->get();
    }

    /** Why this address may not be blocked, or null when it may (Vietnamese text for the admin). */
    public function blockProblem(string $ip, string $adminsOwnIp): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'Địa chỉ IP không hợp lệ.';
        }
        // private and reserved ranges: behind a proxy every visitor can share one of them, and blocking it would block everybody
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Không chặn được địa chỉ nội bộ / dành riêng (có thể là chính máy chủ hoặc proxy: chặn nó là chặn tất cả).';
        }
        if ($ip === $adminsOwnIp) {
            return 'Đó là địa chỉ của chính bạn — chặn nó sẽ khóa bạn ra khỏi trang.';
        }
        if (BlockedIp::where('ip', $ip)->exists()) {
            return 'Địa chỉ này đã bị chặn rồi.';
        }

        return null;
    }

    public function block(string $ip, ?string $reason, User $by): BlockedIp
    {
        $row = BlockedIp::create(['ip' => $ip, 'reason' => $reason !== null ? mb_substr($reason, 0, 200) : null, 'blocked_by' => $by->id, 'created_at' => now()]);
        BlockedIps::forget();

        return $row;
    }

    public function unblock(BlockedIp $row): void
    {
        $row->delete();
        BlockedIps::forget();
    }
}
