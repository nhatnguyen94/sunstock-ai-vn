<?php

namespace App\Backend\Services;

use App\Backend\Interfaces\UserRepositoryInterface;
use App\Models\User;
use App\Support\AdminGuard;
use Generator;

/**
 * Changes made to several accounts at once (Admin > Users) and the CSV export. Every account goes through the same rules as a single edit:
 * AdminGuard decides per account, so one refused account (yourself, the last admin) never blocks or hides the others.
 */
class UserBulkService
{
    /** action => label (the label is what the audit trail and the flash message say) */
    public const ACTIONS = [
        'verify' => 'xác thực / kích hoạt',
        'block' => 'chặn',
        'unblock' => 'mở chặn (kích hoạt lại)',
        'ai-block' => 'khóa AI',
        'ai-unblock' => 'mở khóa AI',
    ];

    public const MAX_IDS = 100;

    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * @param  array<int, int|string>  $ids
     * @return array{done: array<int, int>, skipped: array<int, array{id: int, email: string, why: string}>}
     */
    public function apply(string $action, array $ids, User $actor): array
    {
        $done = [];
        $skipped = [];

        foreach (User::query()->whereIn('id', array_slice(array_unique(array_map('intval', $ids)), 0, self::MAX_IDS))->get() as $user) {
            $why = $this->change($action, $user, $actor);
            if ($why === null) {
                $done[] = $user->id;
            } else {
                $skipped[] = ['id' => $user->id, 'email' => $user->email, 'why' => $why];
            }
        }

        return compact('done', 'skipped');
    }

    /** The reason this account was left alone, or null when it was changed. */
    private function change(string $action, User $user, User $actor): ?string
    {
        if (str_starts_with($action, 'ai-')) {
            $user->forceFill(['ai_blocked_at' => $action === 'ai-block' ? now() : null])->save();

            return null;
        }

        $status = $action === 'block' ? User::STATUS_BLOCKED : User::STATUS_ACTIVE;
        if ($problem = AdminGuard::userChangeProblem($actor, $user, $user->getRoleNames(), $status)) {
            return $problem;
        }

        $user->applyStatus($status);
        $user->save();

        return null;
    }

    /**
     * One CSV row per account matching the list's filters, streamed in chunks (no 10 000-row array in memory).
     *
     * @return Generator<int, array<int, string>>
     */
    public function csvRows(array $filters): Generator
    {
        yield ['ID', 'Tên', 'E-mail', 'Trạng thái', 'Vai trò', 'Xác thực e-mail lúc', 'Ngày tạo'];

        foreach ($this->users->filtered($filters)->with('roles')->orderBy('id')->lazyById(500) as $user) {
            yield array_map([self::class, 'safeCell'], [
                (string) $user->id,
                $user->name,
                $user->email,
                $user->statusLabel(),
                $user->roles->pluck('name')->implode(', '),
                $user->email_verified_at?->format('Y-m-d H:i:s') ?? '',
                $user->created_at->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /** A spreadsheet runs a cell that starts with = + - @ (or a tab / CR) as a formula: a name like "=HYPERLINK(...)" would execute on open. */
    public static function safeCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
