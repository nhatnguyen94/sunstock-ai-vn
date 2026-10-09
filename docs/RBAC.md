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

That's the entire authorization source for feature/action gates. The 7 permissions seeded by `PermissionSeeder` reproduce exactly what used to be hardcoded per-role in `Gate::define()`, plus `manage-queue` for the queue monitor dashboard:

| Permission | Group | Purpose |
|---|---|---|
| `manage-users` | Hệ thống | Create/edit/delete users, assign roles, manual email verification |
| `manage-roles` | Hệ thống | CRUD roles + assign permissions to a role (Admin > Vai trò) |
| `manage-permissions` | Hệ thống | CRUD permissions (Admin > Quyền hạn) |
| `manage-queue` | Hệ thống | View the queue monitor dashboard at `/admin/queue` (`QueueMonitorController`) — resolved through `Gate::before()` like any other permission, gated via the standard `can:manage-queue` route middleware |
| `access-backend` | Hệ thống | Registered for symmetry/testing; the actual backend-entry gate is `canAccessBackend()`, not this ability (see above) |
| `view-timeline` | Tính năng | View activity timeline |
| `manage-features` | Tính năng | Manage stocks, news, portfolios, sync status in admin |

## Default role → permission matrix (seeded by `PermissionSeeder`)

| Permission | admin | webadmin | adminsupport | user |
|---|:---:|:---:|:---:|:---:|
| `manage-users` | ✅ | ❌ | ❌ | ❌ |
| `manage-roles` | ✅ | ❌ | ❌ | ❌ |
| `manage-permissions` | ✅ | ❌ | ❌ | ❌ |
| `manage-queue` | ✅ | ❌ | ❌ | ❌ |
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

## Who may CHANGE users, roles and permissions: the `admin` role only

`can:manage-users|manage-roles|manage-permissions` still decides who may **see** those screens (so the permission can be
delegated for read-only access), but every route in those three groups also carries the `admin.only` middleware
(`App\Http\Middleware\AdminOnlyForChanges`): anything that is not a plain read — store/update/delete, verify/unverify,
and even the `create`/`edit` forms — answers 403 unless the signed-in user has the `admin` role. Without it, a delegated
`manage-users` holder could promote themselves to admin and a `manage-roles` holder could grant their own role anything.
The users list hides the edit/delete/create controls for non-admins.

`App\Support\AdminGuard` then protects the admin role from a careless or malicious administrator (each rule returns a
Vietnamese message that the controller flashes as an error):

| Rule | Why |
|---|---|
| An admin cannot remove their own admin role, block/deactivate/un-verify themselves or delete themselves | self lock-out |
| The last *effective* admin (admin role + active + confirmed e-mail) cannot be demoted, blocked, un-verified or deleted | nobody could manage the system any more |
| The four system roles (`admin`, `webadmin`, `adminsupport`, `user`) cannot be renamed (display name and permissions can still be edited) | `canAccessBackend()`, registration and the seeders refer to them by name |
| The core permissions (`manage-roles`, `manage-permissions`) cannot be renamed, and the `admin` role cannot lose them | the screens that manage permissions would become unreachable |

## Account status (`users.status`)

| Value | Constant | Meaning | Can sign in? |
|---|---|---|---|
| `0` | `User::STATUS_INACTIVE` | switched off by an admin | no |
| `1` | `User::STATUS_ACTIVE` | normal | yes (also needs a confirmed e-mail) |
| `2` | `User::STATUS_PENDING` | registered, waiting for the e-mail link (the default for new rows) | no |
| `4` | `User::STATUS_BLOCKED` | blocked by an admin | no |

(3 is intentionally unused.) Rules, all enforced in code and covered by the `accountStatus` tests:
- Login (`/login` and `/admin/login`) only lets in `status = 1` with a confirmed e-mail. The frontend form tells the *reason*
  (verify e-mail / inactive / blocked) **only after the right password was given**; the admin form always answers like a wrong password.
- `EnsureUserIsActive` (in the `web` group) ends the session of an account that stops being active while signed in
  (redirect to the right login page, or 401 JSON for AJAX), so blocking someone takes effect on their next request.
- A verification link or a password reset turns **pending → active**; it never touches `blocked` / `inactive`.
- In the admin, `User::applyStatus()` keeps the e-mail confirmation consistent: choosing *Active* confirms the address
  (an admin vouches for it), *Pending* clears the confirmation, *Blocked/Inactive* keep what is already confirmed.
  "Hủy xác thực" on an active user sends them back to *Pending*.
- `status` is not mass-assignable: it is only set through `applyStatus()` / `forceFill()`, never from request input.
- Existing accounts were back-filled by the migration: confirmed e-mail → 1, otherwise 2.

### Where the status is enforced (checked end to end by the `accountStatus` tests)

| Place | Behaviour |
|---|---|
| `POST /login`, `POST /admin/login` | `User::scopeMayEnter()` (status 1 + confirmed e-mail) is part of the attempt: no session and no remember cookie for anyone else |
| Every web request (`EnsureUserIsActive`) | ends the session of an account that stopped being allowed in — also a session resumed from a **remember-me cookie**, public pages, and AJAX (401 JSON) |
| Registration | an address already used by a blocked/inactive/pending account is a duplicate: a block cannot be dodged by registering again |
| Verification link / admin "xác thực" / password reset | pending → active; never unblocks or reactivates |
| `POST /forgot-password`, `POST /reset-password` | no mail to blocked/inactive accounts (identical answer for every address); a token issued before the block is refused |
| Resend verification | only for pending accounts |
| Portfolio price alerts (e-mail) | not sent to an owner who may not sign in, and the alert flag is left untouched so it fires after reactivation; the scheduled price refresh skips such owners' portfolios |
| `AdminGuard` | "last admin" counts only effective admins (admin role + `mayEnter`) |
| Admin dashboard | "chờ xác thực" = status 2; a separate "bị chặn / ngưng" counter = status 4 + 0 |
| Admin users list | filter by status (only the exact numbers 0/1/2/4; `0` is a real filter, anything else is ignored) combined with the search without escaping it |

## Admin audit trail

Every change made in the admin is written to `activity_logs` (shown in Admin > Timeline) with the actor, IP and a
`properties` JSON (target id, before/after roles and status, whether a password changed — never the password itself):
user create/update/delete, verify/unverify, role and permission create/update/delete, portfolio toggle/delete, queue
retry/delete, manual sync, password change. Refused actions are not logged as done.

## The dashboard follows the same permissions

`/admin` is open to every backend account, but its "latest users" (needs `manage-users`), "active portfolios"
(`manage-features`) and "recent activity" (`view-timeline`) blocks are only loaded and rendered for roles holding that
permission; the numbers-only cards are shown to everyone.

## Backend admin UI

- **Admin > Hệ thống > Vai trò** (`admin.roles.*`, gate `manage-roles`) — `App\Backend\Controllers\RoleController`. Create/edit a role's name + display name + which permissions it has (checkbox grid grouped by `permissions.group`). Delete is blocked for system roles and for any role still assigned to a user.
- **Admin > Hệ thống > Quyền hạn** (`admin.permissions.*`, gate `manage-permissions`) — `App\Backend\Controllers\PermissionController`. Create/edit a permission's name (the literal string used in `can:<name>`) + display name + group. Delete is blocked for the 2 core permissions (`manage-roles`, `manage-permissions`) so an admin can never lock themselves out of this screen.
- **Admin > Hệ thống > Quản lý Users** (existing, unchanged) assigns *roles* to a user.
- **Admin > Hệ thống > Giám sát Queue** (`admin.queue.*`, gate `manage-queue`) — `App\Backend\Controllers\QueueMonitorController`. Custom-built page (not a third-party package — see "Queue monitoring" in `docs/DOCKER.md` for why): per-queue pending/reserved/delayed counts, **real-time currently-processing job list + recently-finished job list + jobs-processed-today counter** (all auto-refreshing via polling `/admin/queue/stats`, powered by `App\Support\QueueJobLogger` — see `docs/STRUCTURE.md`), and a paginated failed-jobs list with retry/delete/retry-all/delete-all actions.

### Admin-role-only actions inside delegated pages
Reading the **Bảo mật** page follows `manage-users`, but blocking / unblocking an address goes through `admin.only`; **bulk user actions** too. The **CSV export** of users and **taking / downloading a database backup** check the `admin` role explicitly (a CSV is every e-mail address, a dump every password hash), even for a role that was given `manage-users` / `manage-features`.

### Pages under `manage-features`
Stocks, news, portfolios, **Sync Status**, **Quản lý AI** (`/admin/ai`) and **Giao diện & Cache** (`/admin/site`) all use `manage-features`: no separate permission was added for the AI and site controls (changing what visitors see is the same trust level as managing content). The AI page shows names and e-mails only to holders of `manage-users`; blocking an account from the AI is not a role/status change, so `AdminGuard` does not apply, but it is written to the audit trail.

## Adding a brand-new permission-gated feature (no code changes to AppServiceProvider needed)

1. In **Admin > Quyền hạn**, create a permission, e.g. `manage-alerts`.
2. In **Admin > Vai trò**, tick it on whichever role(s) should have it (any number, freely overlapping with other roles).
3. In code, gate the new route/controller/Blade the normal Laravel way — `Route::middleware('can:manage-alerts')`, `Gate::authorize('manage-alerts')`, or `@can('manage-alerts')`. It resolves automatically through the `Gate::before()` hook.

## Adding a new role

Just use **Admin > Vai trò > Tạo Vai trò mới** — no migration, seeder, or code change needed unless the role also needs backend login access, in which case its **name** must currently be one of `admin`/`webadmin`/`adminsupport` (see "Two-layer authorization" above) — extending `canAccessBackend()` to also be permission-driven is a natural follow-up if/when that coarse layer itself needs to scale.

## Note on `spatie/laravel-permission`

`composer.json` lists `spatie/laravel-permission` and it is physically installed in `vendor/`, but it is **not used anywhere** — no published config, no spatie migrations, zero references in `app/`. This custom `permissions`/`permission_role` implementation supersedes any need for it; the dependency can be removed from `composer.json` in a future cleanup if desired.
