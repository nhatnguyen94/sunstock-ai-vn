# RBAC & Permissions

## Overview

This project uses a custom RBAC system based on a `roles` table with a many-to-many `role_user` pivot. Gates are defined in `AppServiceProvider::boot()`.

## Role Constants (`App\Models\Role`)

| Constant | Value | Description |
|---|---|---|
| `Role::ADMIN` | `'admin'` | Full system access |
| `Role::WEBADMIN` | `'webadmin'` | Content management, view-only admin access |
| `Role::ADMIN_SUPPORT` | `'adminsupport'` | Feature management (stocks, news, portfolios) |
| `Role::USER` | `'user'` | Regular user (frontend only) |

## Database Tables

- `roles`: `id`, `name`, `display_name`, `created_at`, `updated_at`
- `role_user`: `role_id`, `user_id` (pivot)

## User Model Helpers (`App\Models\User`)

```php
$user->hasRole(Role::ADMIN);                             // bool
$user->hasAnyRole([Role::ADMIN, Role::ADMIN_SUPPORT]);   // bool
$user->canAccessBackend();                               // bool (Admin|Webadmin|AdminSupport)
$user->roles;                                            // BelongsToMany collection
```

## Gate Definitions (`AppServiceProvider::boot()`)

| Gate | Allowed Roles | Purpose |
|---|---|---|
| `manage-users` | `admin` | Create/edit/delete users, assign roles, manual email verification |
| `manage-features` | `admin`, `adminsupport` | Manage stocks, news, portfolios in admin |
| `view-timeline` | `admin`, `webadmin`, `adminsupport` | View activity timeline |
| `access-backend` | `admin`, `webadmin`, `adminsupport` | General backend access (used by `AdminAccess` middleware) |

## Middleware: `AdminAccess` (alias: `admin`)

File: `app/Http/Middleware/AdminAccess.php`  
Registered in: `bootstrap/app.php`

**Logic:**
1. If not authenticated → redirect to `admin.login`
2. If authenticated but `!$user->canAccessBackend()` → redirect to `home` with error
3. Otherwise → allow through

Applied on all admin routes: `middleware(['auth:web', 'admin'])`

## Permission Matrix

| Action | admin | webadmin | adminsupport | user |
|---|:---:|:---:|:---:|:---:|
| Access backend panel | ✅ | ✅ | ✅ | ❌ |
| View dashboard | ✅ | ✅ | ✅ | ❌ |
| View timeline | ✅ | ✅ | ✅ | ❌ |
| Manage users (CRUD) | ✅ | ❌ | ❌ | ❌ |
| Verify/unverify emails | ✅ | ❌ | ❌ | ❌ |
| Manage stocks (admin) | ✅ | ❌ | ✅ | ❌ |
| Manage news | ✅ | ❌ | ✅ | ❌ |
| Manage portfolios (admin) | ✅ | ❌ | ✅ | ❌ |
| Frontend features | ✅ | ✅ | ✅ | ✅ |

## Seeding Roles

```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=AdminUserSeeder
```

`RoleSeeder` creates all 4 roles.  
`AdminUserSeeder` creates the first admin user with `Role::ADMIN`.

## Adding a New Gate

1. Add to `AppServiceProvider::defineGates()`:
```php
Gate::define('my-new-gate', function ($user) {
    return $user->hasAnyRole([Role::ADMIN, Role::WEBADMIN]);
});
```
2. Use in a controller:
```php
Gate::authorize('my-new-gate'); // throws 403 if denied
```
3. Update the permission matrix in this file.

## Adding a New Role

1. Add a constant to `App\Models\Role`:
```php
const MY_ROLE = 'myrole';
```
2. Create a migration or add to `RoleSeeder`
3. Update `canAccessBackend()` if this role needs backend access
4. Define appropriate Gates
5. Update the permission matrix above
