<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'display_name',
    ];

    /**
     * Quan hệ many-to-many với User
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * Quan hệ many-to-many với Permission — đây là phần cho phép các role
     * chồng chéo quyền hạn với nhau (nhiều role có thể cùng share một
     * permission) mà không cần sửa code, chỉ cần cập nhật qua backend.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    /**
     * Kiểm tra role có permission cụ thể không
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }

    /**
     * Role constants
     */
    const ADMIN = 'admin';
    const WEBADMIN = 'webadmin';
    const ADMIN_SUPPORT = 'adminsupport';
    const USER = 'user';

    /**
     * Kiểm tra xem role có quyền truy cập backend không
     */
    public function canAccessBackend(): bool
    {
        return in_array($this->name, [
            self::ADMIN,
            self::WEBADMIN,
            self::ADMIN_SUPPORT
        ]);
    }

    /**
     * Lấy tất cả backend roles
     */
    public static function getBackendRoles(): array
    {
        return [
            self::ADMIN,
            self::WEBADMIN,
            self::ADMIN_SUPPORT
        ];
    }
}
