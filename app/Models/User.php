<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Account lifecycle (users.status). Only STATUS_ACTIVE may sign in or keep a session. */
    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    /** Registered, waiting for the e-mail address to be confirmed. */
    public const STATUS_PENDING = 2;

    public const STATUS_BLOCKED = 4;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'integer',
        ];
    }

    /**
     * Get the user's profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get the user's portfolios.
     */
    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    /**
     * Get the user's active portfolios.
     */
    public function activePortfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class)->where('is_active', true);
    }

    /**
     * Quan hệ many-to-many với Role
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Kiểm tra user có role cụ thể không
     */
    public function hasRole(string $role): bool
    {
        // `$this->roles` (property, not roles()) reuses an already-loaded
        // relation instead of always issuing a fresh query — also what
        // makes this testable by constructing a User with setRelation().
        return $this->roles->contains('name', $role);
    }

    /**
     * Kiểm tra user có ít nhất một trong các roles không
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    /**
     * Kiểm tra user có permission cụ thể không (qua bất kỳ role nào đang gán).
     * Đây là nguồn kiểm tra quyền duy nhất cho Gate::before() trong
     * AppServiceProvider — mọi permission mới tạo qua backend (Admin >
     * Vai trò / Quyền hạn) tự động hoạt động với can:<permission-name>,
     * không cần sửa code hay deploy lại.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->hasPermission($permission));
    }

    /**
     * Kiểm tra user có quyền truy cập Backend không
     * Chỉ Admin, Webadmin, AdminSupport mới được truy cập
     */
    public function canAccessBackend(): bool
    {
        return $this->hasAnyRole([
            Role::ADMIN,
            Role::WEBADMIN,
            Role::ADMIN_SUPPORT,
        ]);
    }

    /**
     * Gán role cho user (multiple roles supported)
     *
     * @param  string|array  $roleNames
     */
    public function assignRole($roleNames): void
    {
        if (is_string($roleNames)) {
            $roleNames = [$roleNames];
        }

        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->toArray();
        if (! empty($roleIds)) {
            $this->roles()->syncWithoutDetaching($roleIds);
        }
    }

    /**
     * Gỡ bỏ role khỏi user
     *
     * @param  string|array  $roleNames
     */
    public function removeRole($roleNames): void
    {
        if (is_string($roleNames)) {
            $roleNames = [$roleNames];
        }

        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->toArray();
        if (! empty($roleIds)) {
            $this->roles()->detach($roleIds);
        }
    }

    /**
     * Sync roles cho user (replace all current roles)
     */
    public function syncRoles(array $roleNames): void
    {
        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->toArray();
        $this->roles()->sync($roleIds);
    }

    /**
     * Lấy tất cả role names của user
     */
    public function getRoleNames(): array
    {
        return $this->roles->pluck('name')->toArray();
    }

    /** @return array<int, string> status => label, in display order */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Hoạt động',
            self::STATUS_PENDING => 'Chờ xác thực',
            self::STATUS_INACTIVE => 'Ngưng hoạt động',
            self::STATUS_BLOCKED => 'Bị chặn',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? 'Không rõ';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Why this account cannot sign in (shown only to someone who already proved the password, or on a live session). */
    public function accessDeniedMessage(): string
    {
        return match (true) {
            $this->status === self::STATUS_BLOCKED => 'Tài khoản đã bị chặn. Vui lòng liên hệ quản trị viên.',
            $this->status === self::STATUS_INACTIVE => 'Tài khoản đang ngưng hoạt động. Vui lòng liên hệ quản trị viên.',
            default => 'Bạn cần xác thực email trước khi đăng nhập. Kiểm tra hòm thư để xác thực tài khoản.',
        };
    }

    /** May sign in: active status AND a confirmed e-mail address. */
    public function canSignIn(): bool
    {
        return $this->isActive() && $this->hasVerifiedEmail();
    }

    /** Sets the status and keeps the e-mail confirmation consistent with it (see UserStatus rules in docs/RBAC.md). */
    public function applyStatus(int $status): void
    {
        $this->status = $status;

        if ($status === self::STATUS_ACTIVE && ! $this->hasVerifiedEmail()) {
            $this->email_verified_at = now();   // an admin activating an account vouches for the address
        }

        if ($status === self::STATUS_PENDING) {
            $this->email_verified_at = null;
        }
    }
}
