# RBAC & Permissions

## Overview

Two-layer authorization:

1. **Backend access (coarse gate)** — unchanged from before. `AdminAccess` middleware / `User::canAccessBackend()` / `Role::canAccessBackend()` still hardcode the 3 backend-capable role **names** (`admin`, `webadmin`, `adminsupport`). This just answers "can this account log into `/admin` at all" and rarely changes.
2. **Feature/action permissions (fine-grained, DB-driven)** — a `permissions` table + `permission_role` pivot. `AppServiceProvider::defineGates()` registers a single `Gate::before()` hook that resolves **every** ability (`can:xxx`, `@can('xxx')`, `Gate::allows('xxx')`, `Gate::authorize('xxx')`) by checking whether any of the user's roles has a permission row with that exact name. There is no per-ability `Gate::define()` anymore — a brand-new ability works the moment a permission with that name is attached to a role, no code change or deploy required.

This is what makes the system scale to many roles with overlapping permissions: roles and permissions are a plain many-to-many, so any number of roles can share any subset of permissions, and admins manage both entirely from the backend UI (**Admin > Hệ thống > Vai trò / Quyền hạn**).

## Role Constants (`App\Models\Role`)

| Constant | Value | Description |
|---|---|---|
| `Role::ADMIN` | `'admin'` | Full system access |
| `Role::WEBADMIN` | `'webadmin'` | Content management, view-only admin access |
| `Role::ADMIN_SUPPORT` | `'adminsupport'` | Feature management (stocks, news, portfolios) |
| `Role::USER` | `'user'` | Regular user (frontend only) |

These 4 are the **system roles** — `RoleService::isSystemRole()` blocks deleting them from the UI because other code (`AdminAuthController`, `EmailVerificationController`, `RoleSeeder`, `AdminAccess`/`canAccessBackend()`) references them by name directly. Any *new* role created via the UI has no such restriction and can be freely renamed/deleted.

## Database Tables

- `roles`: `id`, `name`, `display_name`
- `permissions`: `id`, `name` (the ability string used in `can:`/`@can`), `display_name`, `group` (UI grouping only)
- `role_user`: `role_id`, `user_id` (pivot — which roles a user has)
- `permission_role`: `role_id`, `permission_id` (pivot — which permissions a role has; this is what lets roles overlap freely)

## Model Helpers

```php
// App\Models\User
$user->hasRole(Role::ADMIN);                             // bool — role-name check (backend-access layer)
$user->hasAnyRole([Role::ADMIN, Role::ADMIN_SUPPORT]);    // bool
$user->canAccessBackend();                                // bool — unchanged, hardcoded 3 roles
$user->hasPermission('manage-users');                     // bool — DB-driven, checked across all of the user's roles

// App\Models\Role
$role->hasPermission('manage-users');                     // bool
$role->permissions;                                       // BelongsToMany collection
```

## Gate (`AppServiceProvider::defineGates()`)

```php
Gate::before(function ($user, string $ability) {
    return $user->hasPermission($ability);
});
```

That's the entire authorization source for feature/action gates. The 6 permissions seeded by `PermissionSeeder` reproduce exactly what used to be hardcoded per-role in `Gate::define()`:

| Permission | Group | Purpose |
|---|---|---|
| `manage-users` | Hệ thống | Create/edit/delete users, assign roles, manual email verification |
| `manage-roles` | Hệ thống | CRUD roles + assign permissions to a role (Admin > Vai trò) |
| `manage-permissions` | Hệ thống | CRUD permissions (Admin > Quyền hạn) |
| `access-backend` | Hệ thống | Registered for symmetry/testing; the actual backend-entry gate is `canAccessBackend()`, not this ability (see above) |
| `view-timeline` | Tính năng | View activity timeline |
| `manage-features` | Tính năng | Manage stocks, news, portfolios, sync status in admin |

## Default role → permission matrix (seeded by `PermissionSeeder`)

| Permission | admin | webadmin | adminsupport | user |
|---|:---:|:---:|:---:|:---:|
| `manage-users` | ✅ | ❌ | ❌ | ❌ |
| `manage-roles` | ✅ | ❌ | ❌ | ❌ |
| `manage-permissions` | ✅ | ❌ | ❌ | ❌ |
| `access-backend` | ✅ | ✅ | ✅ | ❌ |
| `view-timeline` | ✅ | ✅ | ✅ | ❌ |
| `manage-features` | ✅ | ❌ | ✅ | ❌ |

This is only the **starting point** — from here on, roles/permissions are meant to be managed from the backend UI, not by editing this table or reseeding.

## Middleware: `AdminAccess` (alias: `admin`)

Unchanged. File: `app/Http/Middleware/AdminAccess.php`, registered in `bootstrap/app.php`.

1. Not authenticated → redirect to `admin.login`
2. Authenticated but `!$user->canAccessBackend()` → redirect to `home`
3. Otherwise → allow through

Applied on all admin routes: `middleware(['auth:web', 'admin'])`. Feature-level gates (`can:manage-users`, `can:manage-roles`, ...) are layered on top of this inside `routes/web.php`.

## Backend admin UI

- **Admin > Hệ thống > Vai trò** (`admin.roles.*`, gate `manage-roles`) — `App\Backend\Controllers\RoleController`. Create/edit a role's name + display name + which permissions it has (checkbox grid grouped by `permissions.group`). Delete is blocked for system roles and for any role still assigned to a user.
- **Admin > Hệ thống > Quyền hạn** (`admin.permissions.*`, gate `manage-permissions`) — `App\Backend\Controllers\PermissionController`. Create/edit a permission's name (the literal string used in `can:<name>`) + display name + group. Delete is blocked for the 2 core permissions (`manage-roles`, `manage-permissions`) so an admin can never lock themselves out of this screen.
- **Admin > Hệ thống > Quản lý Users** (existing, unchanged) assigns *roles* to a user.

## Adding a brand-new permission-gated feature (no code changes to AppServiceProvider needed)

1. In **Admin > Quyền hạn**, create a permission, e.g. `manage-alerts`.
2. In **Admin > Vai trò**, tick it on whichever role(s) should have it (any number, freely overlapping with other roles).
3. In code, gate the new route/controller/Blade the normal Laravel way — `Route::middleware('can:manage-alerts')`, `Gate::authorize('manage-alerts')`, or `@can('manage-alerts')`. It resolves automatically through the `Gate::before()` hook.

## Adding a new role

Just use **Admin > Vai trò > Tạo Vai trò mới** — no migration, seeder, or code change needed unless the role also needs backend login access, in which case its **name** must currently be one of `admin`/`webadmin`/`adminsupport` (see "Two-layer authorization" above) — extending `canAccessBackend()` to also be permission-driven is a natural follow-up if/when that coarse layer itself needs to scale.

## Note on `spatie/laravel-permission`

`composer.json` lists `spatie/laravel-permission` and it is physically installed in `vendor/`, but it is **not used anywhere** — no published config, no spatie migrations, zero references in `app/`. This custom `permissions`/`permission_role` implementation supersedes any need for it; the dependency can be removed from `composer.json` in a future cleanup if desired.
