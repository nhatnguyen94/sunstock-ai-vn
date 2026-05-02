<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
     * 
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Kiểm tra user có ít nhất một trong các roles không
     * 
     * @param array $roles
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Kiểm tra user có quyền truy cập Backend không
     * Chỉ Admin, Webadmin, AdminSupport mới được truy cập
     * 
     * @return bool
     */
    public function canAccessBackend(): bool
    {
        return $this->hasAnyRole([
            Role::ADMIN,
            Role::WEBADMIN,
            Role::ADMIN_SUPPORT
        ]);
    }

    /**
     * Gán role cho user (multiple roles supported)
     * 
     * @param string|array $roleNames
     * @return void
     */
    public function assignRole($roleNames): void
    {
        if (is_string($roleNames)) {
            $roleNames = [$roleNames];
        }
        
        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->toArray();
        if (!empty($roleIds)) {
            $this->roles()->syncWithoutDetaching($roleIds);
        }
    }

    /**
     * Gỡ bỏ role khỏi user
     * 
     * @param string|array $roleNames
     * @return void
     */
    public function removeRole($roleNames): void
    {
        if (is_string($roleNames)) {
            $roleNames = [$roleNames];
        }
        
        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->toArray();
        if (!empty($roleIds)) {
            $this->roles()->detach($roleIds);
        }
    }

    /**
     * Sync roles cho user (replace all current roles)
     * 
     * @param array $roleNames
     * @return void
     */
    public function syncRoles(array $roleNames): void
    {
        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->toArray();
        $this->roles()->sync($roleIds);
    }

    /**
     * Lấy tất cả role names của user
     * 
     * @return array
     */
    public function getRoleNames(): array
    {
        return $this->roles->pluck('name')->toArray();
    }
}
