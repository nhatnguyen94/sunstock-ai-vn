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

### Authentication security (rules learned the hard way)
- **Never build a URL from the Host header.** `AppServiceProvider::pinRootUrl()` pins every generated URL to `APP_URL`; otherwise `POST /forgot-password` with a forged `Host` mails a victim a genuine reset token inside a link to the attacker. `trustHosts` alone is not enough: Laravel disables it while `APP_ENV=local`.
- **One answer for everything that could reveal an account**: wrong password, unknown e-mail, bad reset token, unknown e-mail on reset, and a non-admin on the admin login all return the same message.
- **Throttle POSTs, not pages**, with named limiters (`auth-login`, …) keyed per IP, per account+IP and per account. A bare `throttle:5,1` shared by GET and POST locked people out after two page views and did nothing against a botnet.
- In `Auth::attempt([... , fn ($query) => ...])` the closure receives the **query builder**, not the user. Use it for `whereNotNull('email_verified_at')`; do role checks after the attempt.
- `Auth::logoutOtherDevices($password)` (Laravel 12) does not change the password: set the new one first, then call it with that same plain password. It re-stamps this session; `authenticateSessions()` signs every other device out.
- Validate every free-text account field with `AuthRules` (whitelist). Output is escaped by Blade, but a stored `<script>` still reaches e-mails, exports and any future `innerHTML`.
- Tests that must exercise CSRF set `$this->app['env'] = 'local'` (the middleware is skipped under `testing`).

### Console-only registrations are invisible to a web request
`->withSchedule()` (bootstrap/app.php) is wrapped in `Artisan::starting()`, so `Schedule::events()` is empty in an HTTP request. Code that reads the schedule from the web (Admin > Sức khỏe hệ thống) must call `Artisan::all()` first. Likewise `CommandStarting` / `CommandFinished` are not dispatched by `Artisan::call()` inside a test.

### Responses: everything goes through `App\Support\TransformerResponse`
**One class owns the HTTP status codes, the stock messages and the response shapes** (JSON, view, redirect with a flash, abort). No number such as 404 / 429 and no stock sentence belongs in a controller, middleware, service or route; the `NoScatteredResponsesTest` (groups `transformerResponse` + `codeStyle`) fails when one creeps back.

- It **extends `JsonResponse`** (so it is a `Response`, and every `: JsonResponse` signature keeps working): `return TransformerResponse::success('…', $data)`.
- **JSON envelope**: `{ success, code, message, data, ...extra }`. `extra` is merged on top-level *after* the envelope, so a page script that already reads `answer`, `result`, `error`, `login_url`, `synced_at`… keeps working; on a clash the legacy key wins (a fund's own `code` is not overwritten by the HTTP code).
- **JSON**: `success()`, `created()`, `accepted()`, `noContent()`, `failed($message, $code)`, `badRequest()`, `unauthorized()`, `forbidden()`, `notFound()`, `unprocessable($message, $errors)`, `tooManyRequests($message, $retryAfter)` (adds `Retry-After`), `serverError()`, `badGateway()` (a source we depend on failed), `serviceUnavailable()`, `json($payload, $code)` (a body exactly as given: an autocomplete list, a chart map), `make(...)` (the envelope with a cookie).
- **Views / redirects / aborts**: `view($name, $data, $status)`, `backSuccess/backError/backWarning/backInfo()`, `backWithInput($level, $message, except: [...])`, `redirectSuccess/Error/Warning/Info($route, $message, $params)`, `redirectIntended()`, `redirectTo()`, `abortWith($code, $message?)`, `abortIf()`, `abortUnless()`, and `negotiated($request, $ok, $message)` for an action reached both by `fetch()` and by a form (JSON for the first, redirect + flash for the second).
- **Constants**: `HTTP_*` (re-declared from the framework so the file is the visible list), `*_MESSAGE` (Vietnamese stock sentences: `NOT_FOUND_MESSAGE`, `UNAUTHORIZED_MESSAGE`, `NO_PERMISSION_MESSAGE`, `PORTFOLIO_NOT_FOUND_MESSAGE`…), and builders `createdMessage('Vai trò')` / `updatedMessage()` / `deletedMessage()`. A service that reports a failure to its controller returns `'status' => TransformerResponse::HTTP_UNPROCESSABLE_ENTITY`, never `422`.
- **When something is missing, add it to the class** (a code, a sentence, a helper) rather than writing the number or the text at the call site. Tests may still assert numbers (`assertStatus(404)`): they pin the contract.

### Pint: run it on the files you wrote, never on a directory
A repo-wide `./vendor/bin/pint app/Backend app/Support ...` reformatted 56 files that had nothing to do with the task (and had to be reverted). Name the files: `./vendor/bin/pint path/a.php path/b.php`; if a run touches other files, `git checkout` them.

### Anything an admin can switch must be read through `SiteSettings`
The home blocks, the site notice and the AI kill switch / quota live in `site_settings` behind `App\Support\SiteSettings` (1-minute cache, defaults when a row or the table is missing). A hidden home block must also skip its computation (`HomeDashboardService::build(..., $blocks)`), otherwise it still queues refresh jobs for something nobody sees.

### Imports (mandatory — see AGENTS.md "Imports")
- `use` the class at the top, use the short name in the body. No `\App\Models\User::STATUS_ACTIVE`, no `new \RuntimeException`, no `@var \Foo\Bar`, in any file type — **including Blade (`@use(...)`) and tests**.
- Same short name twice → alias one with `as`. Function calls/constants like `\count()` / `\PHP_EOL` are exempt.
- `pint.json` makes Pint add the imports (`fully_qualified_strict_types` + `import_symbols`); `tests/Unit/CodeStyle/NoInlineFullyQualifiedNamesTest.php` (group `codeStyle`) fails on any violation. Run `php artisan test --group=codeStyle`.

### Showing prices
The feed is in thousands of VND (62.1 = 62,100 ₫) except for index codes (points). Never format a `stock_prices` value as money directly: convert with `PriceUnit` / `StockQuoteSummary`, and show the unit. Loading states use the shared skeleton classes (`css/shared/skeleton.css`), not a spinner or the word "loading"; numbers that change use `shared/pricefx.js`. See `docs/FRONTEND_VIEWS.md`, "Stock page".

### Anything a visitor can trigger that costs money or a process
Validate the input against what exists before it reaches Python/cache/queue (known symbol, real date in range, stored fund code); never cache a failed or refused run; keep manual refresh buttons for signed-in users; and count real starts in a test (see `docs/PYTHON_INTEGRATION.md`, "Python started by a visitor's web request"). A bare `throttle:N,M` per IP is not protection for a 60-second subprocess.

### Authorization and account status (rules learned the hard way)
- **Delegating `manage-users` / `manage-roles` / `manage-permissions` is read-only by design**: writes need the `admin` role (`admin.only`). New admin write routes for these areas must sit inside those groups; do not add "special" routes outside them.
- Add the `AdminGuard` check to any new code that changes a user's roles/status or a role/permission, and write an `ActivityLogger::log('admin_action', ...)` entry with ids, before/after and **never** a password or token.
- Account state lives in `users.status` (0/1/2/4, see docs/RBAC.md). Never write `email_verified_at` or `status` from request input; use `User::applyStatus()`. A new place that lets people in must check `canSignIn()` (or rely on `EnsureUserIsActive`).
- AI endpoints (`/ai-chat`, `/ai-predict`) are per-account limited; any new endpoint that costs money or spawns a process should get a named limiter keyed by user id, not a bare `throttle:N,M` by IP.
- Tests that act as several users in one test must call `forgetGuards()` + `flushSession()` between actors, otherwise `AuthenticateSession` sees the previous actor's password stamp and signs the next one out.

### Email Verification
- `User` model implements `MustVerifyEmail` — new users must verify before accessing protected routes
- Routes requiring verification use `middleware(['auth', 'verified'])`
- Admin can manually verify/unverify users via `EmailVerificationController@adminVerify` / `adminUnverify`

### Python Integration
- All Python calls must handle: empty output, JSON decode errors, non-zero exit codes
- Python scripts write only JSON to stdout (no debug prints)
- **Never guess a vnstock/MSN symbol code.** A guessed code answers with *some* instrument (`BZ` 15.73, `GC` 5.3, `SI` 0.07 instead of Brent, gold, silver) and the page would show it as fact. Take codes from vnstock's own maps and check the value against a second source — see `docs/VNSTOCK.md` rule 8.
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
- Keep the file short: when it passes ~400 lines, move entries older than two days verbatim to `docs/history/<YYYY-MM>.md` and leave a one-line digest under "Earlier work" (procedure at the top of `docs/HISTORY.md`).
- Also keep the README changelog to **one row per day** (append to the day's row instead of adding another).

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
