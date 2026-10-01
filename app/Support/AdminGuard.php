<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Rules that keep the administration area from being locked or hijacked from the inside. Each method returns a
 * Vietnamese error message when the change must be refused, or null when it is fine, so controllers just show it.
 *
 * Who may change users/roles/permissions at all is decided by the AdminOnlyForChanges middleware (admin role only);
 * these rules protect the admin role itself from a careless or malicious administrator.
 */
final class AdminGuard
{
    /** The roles the code refers to by name: renaming or deleting one would lock people out or break registration. */
    public const SYSTEM_ROLES = [Role::ADMIN, Role::WEBADMIN, Role::ADMIN_SUPPORT, Role::USER];

    /** Permissions the role/permission screens themselves depend on: the admin role must always hold them. */
    public const CORE_PERMISSIONS = ['manage-roles', 'manage-permissions'];

    /** An administrator who can actually sign in right now. */
    public static function isEffectiveAdmin(User $user): bool
    {
        return $user->hasRole(Role::ADMIN) && $user->canSignIn();
    }

    public static function otherEffectiveAdminExists(User $except): bool
    {
        return User::query()
            ->whereKeyNot($except->getKey())
            ->where('status', User::STATUS_ACTIVE)
            ->whereNotNull('email_verified_at')
            ->whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN))
            ->exists();
    }

    /**
     * @param  list<string>  $newRoleNames  the roles the account will have after the change
     */
    public static function userChangeProblem(User $actor, User $target, array $newRoleNames, int $newStatus): ?string
    {
        $staysAdmin = in_array(Role::ADMIN, $newRoleNames, true);
        $staysActive = $newStatus === User::STATUS_ACTIVE;

        if ($actor->is($target)) {
            if ($target->hasRole(Role::ADMIN) && ! $staysAdmin) {
                return 'Bạn không thể tự gỡ vai trò admin của chính mình.';
            }
            if (! $staysActive) {
                return 'Bạn không thể tự khoá hoặc ngưng hoạt động tài khoản của chính mình.';
            }
        }

        if (self::isEffectiveAdmin($target) && ! ($staysAdmin && $staysActive) && ! self::otherEffectiveAdminExists($target)) {
            return 'Đây là admin cuối cùng đang hoạt động, không thể gỡ quyền hoặc khoá tài khoản này.';
        }

        return null;
    }

    public static function deleteProblem(User $actor, User $target): ?string
    {
        if ($actor->is($target)) {
            return 'Bạn không thể xóa chính mình!';
        }

        if (self::isEffectiveAdmin($target) && ! self::otherEffectiveAdminExists($target)) {
            return 'Đây là admin cuối cùng đang hoạt động, không thể xóa.';
        }

        return null;
    }

    /**
     * @param  list<int|string>  $permissionIds  the permission ids the role will have after the change
     */
    public static function roleProblem(Role $role, string $newName, array $permissionIds): ?string
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true) && $newName !== $role->name) {
            return 'Không thể đổi tên vai trò hệ thống ('.$role->display_name.'): code và đăng nhập quản trị phụ thuộc vào tên này.';
        }

        if ($role->name === Role::ADMIN) {
            $missing = Permission::whereIn('name', self::CORE_PERMISSIONS)
                ->whereNotIn('id', array_map('intval', $permissionIds))
                ->pluck('name');

            if ($missing->isNotEmpty()) {
                return 'Vai trò admin phải luôn có quyền: '.$missing->implode(', ').'.';
            }
        }

        return null;
    }

    public static function permissionProblem(Permission $permission, string $newName): ?string
    {
        if (in_array($permission->name, self::CORE_PERMISSIONS, true) && $newName !== $permission->name) {
            return 'Không thể đổi tên quyền lõi ('.$permission->display_name.') — hệ thống dùng nó để tự bảo vệ.';
        }

        return null;
    }
}
