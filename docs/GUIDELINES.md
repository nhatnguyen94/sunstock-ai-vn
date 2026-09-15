# AI Development & Behavioral Guidelines

## Development Rules

1. **Frontend**: For regular users to view stocks, exchange rates, portfolio, AI chat
2. **Backend**: For admin system management (users, stocks, news, portfolios, timeline)
3. **Models**: Shared between Frontend/Backend, no separation
4. **Dependency Injection**: Use Interface and Repository pattern
5. **Service Binding**: Define bindings in `AppServiceProvider.php`
6. **RBAC**: Use `Role` model constants + `Gate` definitions — never hardcode role strings in controllers

## Important Notes
- Frontend and Backend are completely separated in namespace and directories
- Models are shared between Frontend/Backend
- Use Repository pattern and Dependency Injection
- **Backend routes** use the `admin` middleware alias (maps to `App\Http\Middleware\AdminAccess`)
- All users must verify email before accessing portfolio and profile (`verified` middleware)
- Python scripts in `py/` are called via `proc_open` / `shell_exec` inside Service classes and return JSON

## When Adding New Features

### Frontend (Users)
1. Create Controller in `app/Frontend/Controllers/` extending `App\Frontend\Controllers\Controller`
2. Create Service in `app/Frontend/Services/` (if needed)
3. Create Interface + Repository in `app/Frontend/Interfaces/` and `app/Frontend/Repositories/`
4. Register binding in `AppServiceProvider::register()`
5. Add routes to `web.php` with correct middleware (`auth`, `verified` as needed)

### Backend (Admin)
1. Create Controller in `app/Backend/Controllers/` extending `App\Backend\Controllers\Controller`
2. Create Interface + Service + Repository in `app/Backend/Interfaces/`, `app/Backend/Services/`, `app/Backend/Repositories/` (follow existing UserService/StockService/NewsService pattern)
3. Register binding in `AppServiceProvider::register()`
4. Add admin routes under `Route::prefix('admin')->middleware(['auth:web', 'admin'])` group, gated with `Route::middleware('can:your-permission-name')`
5. Create views in `resources/views/backend/`
6. Use `Gate::authorize()` inside controller actions for granular permission checks; create the permission (Admin > Quyền hạn) and attach it to the relevant role(s) (Admin > Vai trò) — see `docs/RBAC.md`

## ⚡ Quick Checklist for AI
When adding ANY new feature (or changing an existing one):
- [ ] Use correct `App\Frontend\*` or `App\Backend\*` namespace
- [ ] Controller extends proper base `Controller` class
- [ ] Create Interface for Repository/Service if DB access needed
- [ ] Register binding in `AppServiceProvider`
- [ ] Apply correct middleware on routes (`auth`, `verified`, `admin`)
- [ ] Add Gate checks in Backend controllers (`Gate::authorize('manage-users')`)
- [ ] Run `composer dump-autoload`
- [ ] Test with `php artisan route:list`
- [ ] **Write/update tests tagged `#[Group('featureName')]` (camelCase — mandatory), then run just that group — `php artisan test --group=featureName` — MANDATORY, all tests in the group must pass**
- [ ] Update documentation immediately

## ⚠️ Known Gotchas & Patterns

### RBAC / Permissions
- Use `Gate::authorize('permission-name')` (throws 403), `Gate::allows('permission-name')`, or `$this->authorize('permission-name')` in controllers; `@can('permission-name')` in Blade
- **There is no fixed/hardcoded list of gates anymore.** A single `Gate::before()` hook in `AppServiceProvider` resolves every ability against the DB (`permissions`/`permission_role` tables) via `User::hasPermission()` — any permission name works the moment it's created and attached to a role from **Admin > Vai trò / Quyền hạn**, no `Gate::define()`/code change needed. Full model + the 6 seeded permissions → **[docs/RBAC.md](RBAC.md)**.
- Backend login access itself (`AdminAccess` middleware / `User::canAccessBackend()`) is a separate, still-hardcoded coarse check (role **name** must be `admin`/`webadmin`/`adminsupport`) — see "Two-layer authorization" in RBAC.md before assuming a new custom role can log into `/admin`.
- Role constants live in `App\Models\Role`: `Role::ADMIN`, `Role::WEBADMIN`, `Role::ADMIN_SUPPORT`, `Role::USER` — these 4 are the system roles (can't be deleted from the UI); any additional role is created via **Admin > Vai trò**, not a new constant.
- Never check roles with raw strings like `$user->hasRole('admin')` — use `Role::ADMIN` constant. Never check a new feature permission with a raw string typo either — the name you `Gate::authorize()` in code must exactly match the `permissions.name` row created in the admin UI.

### Email Verification
- `User` model implements `MustVerifyEmail` — new users must verify before accessing protected routes
- Routes requiring verification use `middleware(['auth', 'verified'])`
- Admin can manually verify/unverify users via `EmailVerificationController@adminVerify` / `adminUnverify`

### Python Integration
- All Python calls must handle: empty output, JSON decode errors, non-zero exit codes
- Python scripts write only JSON to stdout (no debug prints)
- See `docs/PYTHON_INTEGRATION.md` for the full calling pattern

### Caching
- Use `Cache::remember('key', $ttl, fn)` for expensive queries
- Featured stocks: 600s, exchange rates: 1800s, hot industries: 3600s
- Always bust cache after admin data updates

### Scheduling (Laravel 12 — CRITICAL)
- **ONLY** define scheduled commands in `bootstrap/app.php` `->withSchedule()`.
- `app/Console/Kernel.php` exists but its `schedule()` method is **NOT executed** in Laravel 12's slim bootstrap — it is completely ignored by the scheduler.
- `Kernel.php` is only used to register `$commands[]` (manual command discovery). Schedule definitions there have zero effect.
- Verify active schedule with: `php artisan schedule:list`

## 🤖 AI Development Guidelines

**MANDATORY**: Documentation is NOT optional. Every AI agent MUST update the following files as the FINAL STEP of every task:

### 1. Update `docs/HISTORY.md`
- Add a new "Feature Update" block at the TOP of the file.
- Use the standard template: **[FEATURE_NAME] - [DATE]**.
- List all Added, Fixed, Modified components and Technical implementation details.

### 2. Update `docs/STRUCTURE.md`
- If you added new Controllers, Services, Repositories, or Models.
- If you changed the route structure or folder organization.
- Keep the "Directory Structure" map accurate and up-to-date.

### 3. Update `docs/GUIDELINES.md`
- If you found a specific "Gotcha" or bug that others should avoid.
- If you established a new coding pattern (e.g., a specific way to handle AJAX).

### 📝 Verification Requirements
1. **Register service bindings** in `AppServiceProvider.php`.
2. **Test routes** with `php artisan route:list`.
3. **Run** `composer dump-autoload` after any namespace/file changes.
4. **Write/update tests** for the feature or fix, tagged `#[Group('featureName')]` — **camelCase, mandatory** (one group name per feature, shared by all its test methods/classes). Then **run only that group**: `php artisan test --group=featureName` — MANDATORY for every task, new feature or existing one. All tests in the group must pass before the task is done. Don't run the full suite unless asked.
5. **Commit message format — MANDATORY**: `[branch-name] <short summary> (<optional extra detail>)`, e.g. `[master] Add stock screener`. This repo currently only has `master`, so the prefix is always `[master]` unless a feature branch exists.

## 🧠 AI Behavioral Guidelines

**CRITICAL**: Follow these guidelines to reduce common LLM coding mistakes. These bias toward caution over speed.

### 1. Think Before Coding
Don't assume. Don't hide confusion. Surface tradeoffs.

**Before implementing:**
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

### 2. Simplicity First
Minimum code that solves the problem. Nothing speculative.

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.
- Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

### 3. Surgical Changes
Touch only what you must. Clean up only your own mess.

**When editing existing code:**
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

**When your changes create orphans:**
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

**The test:** Every changed line should trace directly to the user's request.

### 4. Goal-Driven Execution
Define success criteria. Loop until verified.

**Transform tasks into verifiable goals:**
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

**For multi-step tasks, state a brief plan:**
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]  
3. [Step] → verify: [check]
```
