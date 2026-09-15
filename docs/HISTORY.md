# Feature Update History

---

## SCALABLE_PERMISSIONS_SYSTEM - September 15, 2026

### Summary:
Replaced the hardcoded `Gate::define()` closures (one per ability, each listing role constants by name) with a DB-driven permission system, plus a backend admin UI to manage it — addressing "phần author phân quyền tao cảm giác nó không có khả năng scale rộng ra" (feature/action authorization couldn't scale to many new, overlapping roles without code changes). `spatie/laravel-permission` is present in `composer.json`/`vendor` but was confirmed completely unconfigured and unused; rather than adopt its full surface area (bigger refactor, would have required rewriting ~18 already-passing auth tests and every `Role::ADMIN`/`hasRole()` call site), the existing `Role`/`role_user` system was extended in place with a `permissions`/`permission_role` layer — the lower-risk option, confirmed with the user via `AskUserQuestion`.

### Design — two layers of authorization (see `docs/RBAC.md`):
1. **Backend access (coarse, unchanged)** — `AdminAccess` middleware / `User::canAccessBackend()` still hardcode the 3 backend-role names. Deliberately left untouched to avoid touching `AdminAccessTest`/`RoleTest`/`UserTest`'s existing passing coverage.
2. **Feature/action permissions (fine-grained, new)** — `permissions` table + `permission_role` pivot (many-to-many, so roles freely overlap). `AppServiceProvider::defineGates()` now registers a single `Gate::before()` hook: `return $user->hasPermission($ability);`. Every `can:xxx` / `@can` / `Gate::authorize()` call in the app resolves through this — a brand-new ability works the instant a permission with that name is attached to a role, no `Gate::define()`/deploy needed.

### Added:
- **`app/Models/Permission.php`** — new model (`name`, `display_name`, `group`), `roles()` BelongsToMany.
- **`app/Models/Role.php`** — `permissions()` BelongsToMany, `hasPermission(string $name): bool`.
- **`app/Models/User.php`** — `hasPermission(string $name): bool`, checks across all of the user's roles.
- **Migrations**: `create_permissions_table`, `create_permission_role_table` (both with `unique` pivot constraints).
- **`database/seeders/PermissionSeeder.php`** — recreates the exact 4 previously-hardcoded abilities (`manage-users`, `manage-features`, `view-timeline`, `access-backend`) plus 2 new ones for the admin UI itself (`manage-roles`, `manage-permissions`), attached to the same 4 roles the old code granted them to — 100% behavior-preserving. Wired into `DatabaseSeeder` after `RoleSeeder`.
- **Backend admin UI** (`App\Backend\Controllers\RoleController` / `PermissionController`, matching the existing Interface+Repository+Service pattern used by `UserController`): **Admin > Hệ thống > Vai trò** (gate `manage-roles`) — CRUD roles, checkbox-grid permission assignment grouped by `permissions.group`; **Admin > Hệ thống > Quyền hạn** (gate `manage-permissions`) — CRUD permissions. Destroy guards: system roles (`admin`/`webadmin`/`adminsupport`/`user`) and roles still assigned to a user can't be deleted from the UI; the 2 core permissions (`manage-roles`, `manage-permissions`) can't be deleted either — both would otherwise let an admin lock themselves out with no recovery path except direct DB access.
- **Sidebar** (`layouts/admin.blade.php`) — "Vai trò"/"Quyền hạn" links added under the existing "Hệ thống" section, each gated by `@can`.
- **Tests** (group `permissions`, 38 new): `Role::hasPermission()`/`User::hasPermission()` unit tests (setRelation-based, no DB); `GatesTest.php` fully rewritten around the new `Gate::before()` mechanism — including a test proving an arbitrary permission name with **no** code-defined gate anywhere resolves correctly (the actual scalability claim, not just a behavior-preservation check); `RoleControllerTest`/`PermissionControllerTest` (RefreshDatabase — genuinely needs real pivot-sync + unique/exists validation + the real `can:` middleware chain) covering CRUD and every destroy guard.

### Verified:
- `php artisan test --group=permissions`: 38/38 passing. Full suite: **113/113 passing**.
- Migrated + seeded the real dev DB (`docker exec stock-app-php-1 php artisan migrate` + `db:seed --class=PermissionSeeder`) — confirmed via tinker that `sunadmin`'s existing session gained `manage-roles`/`manage-permissions` with zero other behavior change, and that an unknown/never-defined ability correctly denies (no exception).
- End-to-end in the real browser against the live `sunadmin@example.com` admin account: `/admin/roles` and `/admin/permissions` render with correct live counts; created a demo permission and a demo role with it attached through the actual create forms, confirmed it appeared correctly and the role's `hasPermission()` returned true; verified `/admin/roles/1/edit` (the real `admin` role) pre-checks exactly its 6 seeded permissions. Demo data cleaned up afterward (the in-browser delete-via-JS attempt turned out not to hit the server at all — confirmed by checking nginx access logs, that's a limitation of driving the DOM directly through the automated browser tool rather than a real click, not an app bug — so cleanup was done via tinker instead; the automated `RoleControllerTest`/`PermissionControllerTest` destroy tests already cover the real code path with real HTTP requests).

### Docs:
`docs/RBAC.md` rewritten around the two-layer model; `docs/BINDINGS.md`, `docs/STRUCTURE.md`, `docs/ROUTES_MAP.md`, `docs/TESTING.md` updated for the new controllers/services/repositories/routes/test group.

---

## FIX_TEST_SUITE_LAST_FAILURE - September 15, 2026

### Summary:
The one test that had been failing throughout this session's work (`Tests\Feature\ExampleTest`, documented repeatedly as a "pre-existing, unrelated" known issue) is now fixed — full suite is 93/93 green. Root cause was exactly what `docs/TESTING.md` already suspected: the `stock_prices` partition migration's raw MySQL-only SQL halted every migration after it whenever something tried to actually migrate the sqlite test DB, and `RefreshDatabase` was commented out in the test besides.

### Fixed:
- **`database/migrations/2026_05_29_000001_partition_stock_prices_by_year.php`** — both `up()` and `down()` now return immediately when `DB::connection()->getDriverName() !== 'mysql'`. Partitioning is a MySQL-only optimization (irrelevant at test data volumes); production behavior on MySQL is completely unchanged. This unblocks `RefreshDatabase`/`artisan migrate` against sqlite for the whole project going forward, not just this one test.
- **`tests/Feature/ExampleTest.php`** — un-commented `use RefreshDatabase;`. Running it revealed a second issue: against an otherwise-empty freshly-migrated DB, `StockController::home()` fell through to *real* Python/vnstock subprocess calls (hot industries and exchange rates each have a "DB empty → fetch live" first-run fallback) — the test passed, but slowly (~12s) and with a live network dependency, which is exactly the kind of flakiness this project's tests otherwise avoid. Fixed by seeding one `HotIndustry` row and one `ExchangeRate` row (for today's date) before the request, so neither fallback triggers. Runs in ~0.6s now.

### Docs:
- **`docs/TESTING.md`** — rewrote the "why tests never touch a real database" section (renamed to reflect that `RefreshDatabase` is now available, with guidance on when to actually reach for it — the mocking-first default for everything else stands, mainly for speed and to keep Services going through the Repository pattern rather than touching Eloquent directly). Removed the now-obsolete "Known issues" section and the "ExampleTest will fail regardless" caveat.

### Verified:
`php artisan test`: **93/93 passing**, full suite in 4.77s (down from ~7s pre-fix, since the previously-failing test no longer aborts early either).

---

## PROFILE_EXTENDED_FIELDS - September 15, 2026

### Summary:
Built the extension flagged in the previous Profile audit: `UserProfile` already had `birthday`, `gender`, `avatar`, `address`, `bio` columns and fillable fields, but `profile/edit.blade.php` only ever exposed `username`/`mobile`. All five are now editable, including a real avatar upload (not just a URL field).

### Added:
- **`app/Frontend/Controllers/ProfileController.php`** — `update()` now validates and saves `birthday` (`nullable|date|before_or_equal:today`), `gender` (`nullable|in:male,female,other`), `address`, `bio` (max 1000 chars), and `avatar` (`nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048`). New `storeAvatar()` method (public, for testability) resolves what to save: stores + returns the new file path when one's uploaded (deleting the old file first), clears it when a `remove_avatar` checkbox is checked, or leaves the existing value untouched otherwise.
- **`php artisan storage:link`** run — `public/storage` now symlinks to `storage/app/public`, needed to serve uploaded avatars. `storage/app/public/.gitignore` already had the standard Laravel pattern (ignore uploaded content, keep the folder), so no `.gitignore` changes needed.
- **`resources/views/profile/edit.blade.php`** — avatar preview (uploaded image, or an initials-circle fallback matching the navbar's `.user-avatar` treatment — deliberately *not* a third-party avatar service like Gravatar/ui-avatars.com, to avoid sending the user's name to an external host on every page view) with a client-side instant preview on file select, a "remove current avatar" checkbox, plus new birthday/gender/address/bio fields. Form gained `enctype="multipart/form-data"`.
- **`resources/views/profile/show.blade.php`** — displays the avatar (or initials fallback) and all five new fields, each falling back to "Chưa cập nhật" when empty, same pattern as the existing username/mobile fields.
- **Tests** (group `profile`, 5 new): `storeAvatar()` covered fully with `Storage::fake('public')` (upload, replace-deletes-old, remove, keep-existing-when-untouched, null-profile-and-nothing-uploaded) — pure filesystem faking, no database.

### Verified:
- Blade rendering checked via `view(...)->render()` in tinker for both templates (edit initially threw in tinker only because `$errors` isn't shared there the way the real `ShareErrorsFromSession` middleware does it in an actual request — confirmed a non-issue by sharing an empty `ViewErrorBag` manually, then rendered clean).
- End-to-end in a real browser against the live `sunadmin@example.com` account: logged in, filled in mobile/birthday/gender/address/bio, submitted, confirmed every value round-tripped correctly onto the show page. Test data reset back to the account's original (all-null) state afterward via tinker — no lasting changes to real data.
- `php artisan test --group=profile`: 8/8 passing. Full suite: 92/93 (same pre-existing unrelated failure).

---

## PROFILE_AND_PORTFOLIO_AUDIT - September 15, 2026

### Summary:
Audited Profile and Portfolio (requested after "cảm giác nó hơi hơi lủng" about Profile specifically). Found and fixed one real crash bug in Profile, and hardened a data-drift risk in Portfolio's total tracking. Also surfaced (not implemented) a few scale/extension opportunities — see "Flagged, not built" below.

### Fixed — real bug:
- **Accounts created via the admin panel had no `UserProfile` row**, and `ProfileController::update()` assumed one always exists: `'unique:user_profiles,username,'.$profile->id` on a null `$profile` degrades silently (PHP 8 just warns on `null->id`), but `$this->profileRepo->update($profile, [...])` — a **method call** on `null` — throws an uncaught `TypeError` (`Error`, not `Exception`, so the surrounding `catch (\Exception $e)` never catches it) → 500 for that user the moment they try to save their profile. Confirmed 0 users currently affected in the dev DB (everyone so far came through registration or `AdminUserSeeder`, both of which do create a profile) — but the very first admin-created account to edit their profile would have hit this.
  - **`app/Backend/Repositories/UserRepository.php`** — `create()` now also creates a blank `UserProfile` row, matching what registration and the seeder already do.
  - **`app/Frontend/Controllers/ProfileController.php`** — `update()` now uses `Rule::unique('user_profiles','username')->ignore($profile?->id)` (handles a null id correctly, unlike the old string-concatenated rule) and `UserProfileRepositoryInterface::updateOrCreateForUser()` instead of assuming the row exists.
  - New `UserProfileRepository::updateOrCreateForUser()` / interface method.

### Hardened — data-drift risk in Portfolio totals:
- **`app/Frontend/Repositories/PortfolioRepository.php`** — `createItem`/`updateItem`/`deleteItem` used to keep `total_invested`/`current_value` in sync by incrementally adjusting them (`$portfolio->total_invested += ...` / `-= ...`). This class of bookkeeping silently drifts from reality if anything ever touches a `PortfolioItem` outside these exact three methods (a future feature, a manual DB fix, a bug elsewhere) — there was no way to tell the cached totals were wrong, and no way to fix them short of manual SQL.
  - **`app/Models/Portfolio.php`** — new `calculateTotalInvested()` (mirrors the existing `calculateCurrentValue()`) and `recalculateTotals()`, which recompute both from the actual items every time — always correct, can't drift.
  - The three repository methods now call `Portfolio::find($item->portfolio_id)?->recalculateTotals()` — **a freshly-fetched Portfolio**, not `$item->portfolio`. This distinction matters: while implementing this, testing caught a real bug in the fix itself — reusing `$item->portfolio` (a cached `belongsTo` relation) meant its own cached `items` collection could predate the change that had just been made, so recalculating from it silently produced the *old* totals. Verified with a real DB round-trip (create → update quantity → delete, checking values at each step) that the fresh-fetch version is correct even when the caller had already touched `$item->portfolio` beforehand.

### Added (tests):
- `tests/Unit/Models/PortfolioTest.php` (group `portfolioTotals`) — value calculations, the empty-portfolio div-by-zero guard, profit/loss accessors. Pure unit test (`setRelation()`, no DB) — this is exactly what the `Portfolio::recalculateTotals()` docblock's caching warning is about; the repository-level fresh-fetch fix itself could only be verified against the real DB (see above), not the automated suite.
- `tests/Feature/Frontend/Controllers/ProfileControllerTest.php` (group `profile`) — `show()`/`edit()` return the correct view without crashing when `findByUserId()` returns null. `update()`'s specific fix couldn't be covered by the automated suite: `Rule::unique()` queries the real `users`/`user_profiles` tables directly, which don't exist in the sqlite test DB (see docs/TESTING.md) — verified manually instead (create a user the same way the admin panel does → confirm a profile now exists → confirm `updateOrCreateForUser()` succeeds where the old code would have thrown).

### Flagged, not built (surfaced for the user to decide on):
- **`UserProfile` has `birthday`, `gender`, `avatar`, `address`, `bio` columns and fillable fields that nothing in the UI ever sets** — `profile/edit.blade.php` only exposes `username`/`mobile`. The schema already supports a richer profile; the form just doesn't ask for it.
- **`PortfolioService::getPortfoliosPaginated()` / `PortfolioRepository::paginate()` exist but are never called** — `PortfolioController::index()` uses the unpaginated `getUserPortfolios()`, loading every one of a user's portfolios (with all their items) on every visit to `/portfolio`. Not a problem at today's scale (few portfolios per user), but the paginated path is sitting there unused if/when that stops being true.
- **`PortfolioController::storeStock()` accepts any `stock_symbol` string with no check that it corresponds to a real tracked `Stock`** — a typo'd or made-up symbol is accepted silently and will just never receive price updates (stays frozen at `buy_price` forever, since `StockRepositoryInterface::getLatestPrices()` simply won't find it). No crash, just a silent data-quality gap.

### Verified:
`php artisan test --group=portfolioTotals --group=profile`: 10/10 passing. Full suite: 87/88 (same pre-existing unrelated failure).

---

## ADD_FORGOT_PASSWORD_FEATURE - September 15, 2026

### Summary:
Implemented the "forgot password" flow flagged as a gap in the previous auth/authz audit. Built on Laravel's existing (but previously unused) `Password` broker — the `password_reset_tokens` table already existed from the default migration, just nothing used it.

### Added:
- **`app/Frontend/Controllers/PasswordResetController.php`** — `showForgotForm`, `sendResetLink`, `showResetForm`, `reset`. Security-conscious by design: `sendResetLink` returns the exact same generic message ("if this email exists, we sent a link") regardless of whether the broker actually found a matching account — an earlier naive implementation would leak which emails are registered (user enumeration). `translateStatus()` maps broker status constants to Vietnamese messages and is `public` specifically so it's unit-testable independent of the DB-touching `Password::reset()` call.
- **Routes** (throttled `5,1`, same group as login/register): `GET|POST /forgot-password` (`password.request` / `password.email`), `GET /reset-password/{token}` (`password.reset`), `POST /reset-password` (`password.update`).
- **Views**: `resources/views/auth/forgot-password.blade.php`, `resources/views/auth/reset-password.blade.php` — same visual language as `auth/login.blade.php` (`.auth-wrap`/`.auth-left`/`.auth-right` split panel), styles in the new `resources/frontend/css/auth/password-reset.css`.
- **`auth/login.blade.php`** — added a "Quên mật khẩu?" link next to the password label, and a success-message banner (for the redirect back from a completed reset).
- **Tests** (group `auth`, 11 new): `tests/Unit/Frontend/Controllers/PasswordResetControllerTest.php` (pure — `translateStatus()` for all 4 broker statuses), `tests/Feature/Frontend/Controllers/PasswordResetControllerTest.php` (validation-failure paths via real HTTP; the two "show form" cases call the controller directly instead — see the new navbar-DB note in `docs/TESTING.md`).

### Verified:
- `php artisan test --group=auth`: 47/47 passing. Full suite: 77/78 (same pre-existing unrelated failure).
- End-to-end against the real dev DB (not covered by the automated tests, per the DB limitation): created a throwaway user, requested a reset link (confirmed a `password_reset_tokens` row was created), then called `Password::reset()` with the controller's exact callback and a real token — status came back `PASSWORD_RESET`, the new password verified, the old one no longer did. Throwaway user deleted afterward; no real accounts touched.
- Browser-tested the actual `/forgot-password` submit against the real `sunadmin@example.com` account: generic success message shown, token row created — without ever telling the browser whether that email existed.

---

## AUTH_AUTHZ_AUDIT_AND_TESTS - September 14, 2026

### Summary:
Full review of the login/register/admin-login flow and the RBAC layer (Gates, `AdminAccess` middleware, `User`/`Role` role checks) requested after a suspicion something was "a bit off." Found and fixed one real bug (guest admin-route redirect went to the wrong login page), one code-quality violation of this repo's own coding rule, and refactored `User::hasRole()`/`hasAnyRole()` for testability. Added 36 tests (group `auth`).

### Fixed — real bug caught by writing the middleware test:
- **`bootstrap/app.php`** — an unauthenticated guest hitting any `/admin/*` route was redirected to `/login` (the regular frontend user login) instead of `/admin/login`. Root cause: `Route::middleware(['auth:web', 'admin'])` runs Laravel's built-in `auth:web` **before** the custom `admin` (`AdminAccess`) alias; `auth:web`'s default guest-redirect always targets `route('login')`, so `AdminAccess::handle()`'s own "not authenticated → redirect to admin.login" branch was dead code, unreachable via the real route. Fixed with `$middleware->redirectGuestsTo(fn ($request) => $request->is('admin*') ? route('admin.login') : route('login'))`. Verified via real HTTP: `/admin` → `/admin/login`, `/portfolio` still → `/login` (no regression on the frontend path). Not a security hole (guests were still correctly blocked either way) — a UX/correctness bug.

### Fixed — code-quality (this repo's own rule):
- **`app/Frontend/Controllers/EmailVerificationController.php`** — `adminVerify()`/`adminUnverify()` checked `hasRole('admin')` (hardcoded string) instead of `hasRole(Role::ADMIN)`, violating the FORBIDDEN PATTERNS rule in `AGENTS.md`. Not exploitable (the route is already gated by `can:manage-users` at the route level), but inconsistent and fragile if the role name ever changed.

### Refactored for testability:
- **`app/Models/User.php`** — `hasRole()`/`hasAnyRole()` used `$this->roles()->where(...)->exists()` (method call — always issues a fresh query, ignores any already-loaded relation). Changed to use the `roles` relation **property** (`$this->roles->contains(...)` / `->pluck('name')->intersect(...)`), which transparently lazy-loads via query if not yet loaded, or reuses the cached collection if it is (e.g. after `$user->load('roles')`, or across repeated calls within one request — a minor perf win too). This is what makes the role checks unit-testable by constructing a `User` with `setRelation('roles', collect([...]))`, no database needed. Behavior verified unchanged against a real DB-backed admin account via tinker before/after.

### Added (tests, group `auth`, 36 passing):
- `tests/Unit/Models/UserTest.php` — `hasRole`, `hasAnyRole`, `canAccessBackend` (incl. zero-roles edge case), `getRoleNames`.
- `tests/Unit/Models/RoleTest.php` — `canAccessBackend` per role name (incl. an unrecognized role name defaulting to `false`), `getBackendRoles()`.
- `tests/Feature/Http/Middleware/AdminAccessTest.php` — guest → `admin.login` (this is the test that caught the bug above), non-backend role → `home`, admin/webadmin/adminsupport → pass-through, plain `user` role → blocked.
- `tests/Feature/Providers/GatesTest.php` — all 4 Gates from `AppServiceProvider::defineGates()` against every role combination via `Gate::forUser()`.

### Checked and found correct (no changes needed):
CSRF present on all 3 auth forms (`auth/login`, `auth/register`, `backend/auth/login`); registration only mass-assigns validated fields (no privilege-escalation vector — role is always hardcoded to `Role::USER` server-side); email-verification gate on frontend login (`AuthController::login` logs an unverified user back out); session regenerated on both login flows (session-fixation protection); backend route middleware layering (`auth:web` → `admin` → `can:manage-*`) has no bypass path; login/register/admin-login all throttled `5,1`.

### Known gaps surfaced, not fixed (flagged for the user to decide on):
- **No password-reset ("forgot password") flow exists at all** — no routes, no controller, no `password_reset_tokens` usage. A user who forgets their password has no self-service recovery path. Out of scope for this audit (would be a new feature, not a fix), but worth prioritizing.
- **`app/Backend/Controllers/AdminAuthController.php::activeSessions()`** is dead code — not registered in `routes/web.php`. If it's ever wired up without the `auth:web` middleware, `Auth::user()->hasRole(...)` will fatal-error on a null `Auth::user()` for guests. Left as-is since currently unreachable; flagging so it isn't wired up carelessly later.

### Verified:
`php artisan test --group=auth` → 36/36 passing. Full suite → 66/67 (only the pre-existing unrelated `Tests\Feature\ExampleTest` failure, see `docs/TESTING.md`). Real HTTP smoke test: `/login`, `/register`, `/admin/login` all return 200; `/admin` and `/portfolio` guest redirects verified via `curl -w "%{redirect_url}"`.

---

## FIX_SCREENER_INLINE_CSS_AND_DATE_ICON_ROUND2 - September 14, 2026

User reported both fixes from the previous entry were incomplete. Both were real gaps, not misunderstandings:

### Issue 1 — Screener still had inline CSS (the hero header):
The previous redesign only rewrote the filter/stats/table sections; the hero header (badge, title, subtitle, "Trang chủ" button) was untouched and still used `style="..."` attributes copied from `stock/compare.blade.php`. Worse: it used `class="compare-header"`, which is **only defined in `stock/compare.css`** — a stylesheet this page never loads — so the header was silently rendering completely unstyled (no gradient, no proper spacing) the whole time. Same problem for `class="back-button"` and `class="main-content"`, both only defined in other pages' CSS files.

- **Added** proper scoped classes to `resources/frontend/css/stock/screener.css`: `.screener-header`, `.screener-header-row`, `.screener-badge`, `.screener-title`, `.screener-subtitle`, `.screener-header-actions`, `.back-button`, `.screener-main`, `.screener-actions`, `.screener-stat-icon`, and extended `.screener-stat-label` to cover what was previously an inline `display:block;margin-top`.
- **`resources/views/stock/screener.blade.php`** now has zero `style="..."` attributes in its own content (verified by rendering the view and checking; the layout's navbar/footer still use inline styles site-wide — that's an existing, unrelated pattern used on every page, not specific to this one).

### Issue 2 — Exchange-rate date icon: previous fix was incomplete.
The last fix only addressed `.has-value`. Found four more places doing the exact same thing (using the `background` **shorthand**, which resets `background-image` to `none`, wiping the calendar icon):
- CSS `.search-input[type="date"]:focus { background: white; }` — fires on the *first click* (focusing the input), before any date is even picked. This was the actual primary trigger for "click once and it disappears."
- JS: `dateInput.style.background = '#f8fafc'` in `updateDateDisplay()`'s clear branch, and the `focus`/`blur` listeners, and the validation-error branch (4 spots) — **inline styles set via JS beat any CSS rule** (highest specificity), so even after the CSS fix, clearing the date via the ✕ button re-broke the icon through these.

- **Fixed**: CSS `:focus` rule changed to `background-color`. All five JS `.style.background = ...` assignments targeting the date input changed to `.style.backgroundColor = ...` (the two unrelated ones on `.rate-value` copy-feedback elements were left alone — no icon dependency there).

### Verified:
- `grep 'style="'` on the screener Blade source: zero matches.
- Rendered the screener view via `StockController::screener()`: content section between the header and footnote contains no inline styles; `compare-header` class reference is gone.
- Built bundle (`index-*.js` for exchange_rate) confirmed to contain 5 `backgroundColor` assignments and only the 2 legitimate unrelated `background` ones (copy-feedback).
- `php artisan test`: 30/31 passing, same pre-existing unrelated failure as before — no regression.
- Visual confirmation still blocked by this session's browser-automation sandbox (it refuses all `/build/assets/*` requests, unrelated to the app) — ask the user to verify in a real browser.

---

## FIX_SCREENER_UIUX_AND_EXCHANGE_RATE_DATEPICKER - September 14, 2026

### Issue 1 — Stock Screener UI/UX redesign:
The screener page (`/stock/screener`) was shipped with unaccented Vietnamese text throughout, an inline `<style>` block instead of a dedicated CSS file (inconsistent with `docs/FRONTEND_VIEWS.md` convention), a raw internal artisan command name (`sync:company-financials`) leaking into a user-facing footnote, and flat/low-contrast styling that didn't match the rest of the site (home, exchange-rate).

- **Added** `resources/frontend/css/stock/screener.css` — full redesign matching `exchange_rate/index.css`'s design language: white cards with a top gradient bar, `.screener-stat-card` result-count strip, `.screener-symbol-badge` gradient pills (same pattern as `.currency-code`), color-coded `.screener-pill` (green/red/neutral) for ROE, dividend yield, and debt/equity based on value thresholds, sort-arrow indicators on sortable table headers.
- **Rewrote** `resources/views/stock/screener.blade.php` — proper Vietnamese diacritics throughout, removed the inline `<style>` block in favor of `@vite('resources/frontend/css/stock/screener.css')`, footnote reworded to drop the internal command name, fixed a scale bug in the "Nợ/VCSH tối đa" filter placeholder (was `1.5` implying a ratio scale like `1.0 = 100%`; the underlying data is already percentage-scale — e.g. `112.83` — so the filter never meaningfully matched anything at that placeholder value; now `150`).
- **Updated** `docs/FRONTEND_VIEWS.md` Blade→asset mapping table with the new row.

### Issue 2 — Exchange-rate date picker icon disappearing after first pick:
`resources/frontend/css/exchange_rate/index.css`'s `.search-input[type="date"].has-value` rule set `background: #f0fdf4` using the `background` **shorthand**, which resets `background-image` to `none` for every property not explicitly given — wiping out the custom calendar-icon `background-image` set on the base `.search-input[type="date"]` rule the moment a date was picked (`has-value` gets added by JS on `change`). The icon didn't just look different, it visually vanished, even though the underlying (invisible, opacity:0) native `::-webkit-calendar-picker-indicator` hit-area was still there — so users had no visual cue for where to click to change the date again.

- **Fixed**: changed to `background-color: #f0fdf4` (not the shorthand), preserving the inherited `background-image` so the calendar icon stays visible after a date is selected.

### Verification notes:
Both fixes are pure CSS/Blade with no new PHP logic, so no new test group was added (per `docs/TESTING.md`, tests cover Service/Notification/Command logic, not static markup/styling). Rendered output verified via `Route::screener()`'s Blade output (diacritics intact, old command-name text gone, new CSS classes present) and via browser page-text (filter + sort + stat count all correct). The actual visual rendering (colors, icon disappearing) could **not** be visually confirmed in this session's browser-automation tool, which blocks all `/build/assets/*` requests in its sandbox — ask the user to verify visually in a real browser.

---

## FIX_EXCHANGE_RATE_PAGE_BROKEN - September 14, 2026

### Summary:
`/exchange-rate` showed no data at all — no default latest-day rates, date search always empty, "Làm mới"/refresh looked broken. Root cause: `ExchangeRateService::fetchRatesFromPython()` concatenated every line of the Python script's stdout (`implode('', $output)`) before `json_decode()`. vnstock prints a promo banner and version-update notices to stdout before the actual JSON line, so the concatenated string was never valid JSON — parsing silently failed on every single call, always returning `[]`. The Python script itself and the underlying data were fine the whole time; this was a pure PHP-side parsing bug.

### Fixed:
- **`app/Frontend/Services/ExchangeRateService.php`** — `fetchRatesFromPython()` now scans `$output` backward for the last line starting with `{`/`[` (same pattern already used in `StockService`/`CompanyFinancialService`, documented in `docs/PYTHON_INTEGRATION.md`). Extracted into a new `parsePythonOutput(array $output, $daysOrDate): array` method specifically so it's unit-testable without invoking a real Python process.
- **`resources/frontend/js/exchange_rate/index.js`** — the "Key Rates Bar Chart" (ApexCharts) was a dangling comment with no implementation (`#keyRatesChart` div stayed empty). Implemented it using the `$chartRates` data the Blade view already computed, passed to JS via `window._chartRatesData`.
- **`resources/views/exchange_rate/index.blade.php`** — added the `window._chartRatesData = @json($chartRates ?? [])` inline data bridge for the above.

### Added (tests):
- `tests/Unit/Frontend/Services/ExchangeRateServiceTest.php` (group `exchangeRate`) — regression tests for the banner-noise parsing bug, malformed/empty output, invalid-input short-circuit.
- `tests/Feature/Frontend/Services/ExchangeRateServiceTest.php` (group `exchangeRate`) — DB-first cache behavior and the Python-fallback-then-persist flow, using a Mockery partial mock of `ExchangeRateService` to stub `fetchRatesFromPython()` (so no real Python process runs, consistent with the Unit test file).

### Verified:
- `php artisan test --group=exchangeRate` → 10/10 passing; full suite 30/31 (only the pre-existing unrelated `Tests\Feature\ExampleTest` failure, see `docs/TESTING.md`).
- Manually via `sync:exchange-rates`-equivalent service calls: `getLatestRates(3)` and `getRatesByDate()` both now return real, fresh Vietcombank data end-to-end; confirmed in the rendered page (3-day table + working date search).
- The chart's client-side render could not be visually confirmed in this session's browser-automation tool — it blocks all `/build/assets/*` requests in its sandbox (`net::ERR_BLOCKED_BY_CLIENT` on every asset, environment-specific, unrelated to the app) — but the exact render logic was verified by running it manually in the page's console, and the built bundle was confirmed to contain the code.

---

## MANDATORY_TESTING_RULE_AND_FIRST_TEST_SUITE - September 14, 2026

### Summary:
Added a mandatory testing rule to `AGENTS.md`/`GUIDELINES.md` (write tests tagged `#[Group('feature-name')]`, run only that group — never required to run the full suite), wrote the first tests for the previous session's 3 features, and refactored `PortfolioService` to be fully mockable in the process.

### Added:
- **`docs/TESTING.md`** — full testing guide: folder structure mirrors `app/`, the `#[Group(...)]` convention, why tests here can never touch the database (see below), current group list, how to add a new feature's tests.
- **Tests** (19 total, all passing):
  - `tests/Unit/Frontend/Services/PortfolioServiceTest.php` (group `portfolioPrices`) — pure PHPUnit, no Laravel boot.
  - `tests/Feature/Frontend/Services/PortfolioServiceTest.php` (group `portfolioAlerts`) — needs the container for `Notification::fake()`.
  - `tests/Feature/Frontend/Services/CompanyFinancialServiceTest.php` (group `stockScreener`) — needs the container for the `Cache` facade; also a regression test locking in the ROEA-vs-"4 quý gần nhất" fix from the previous session.
  - `tests/Feature/Notifications/PortfolioAlertNotificationTest.php` (group `portfolioAlerts`) — needs the container for the `url()` helper.
  - `tests/Feature/Console/Commands/SyncPortfolioPricesTest.php` (group `portfolioAlerts`) — uses `$this->artisan()` against the real kernel with `PortfolioService` swapped for a mock.

### Fixed / Refactored:
- **`app/Frontend/Services/PortfolioService.php`** — had two direct Eloquent queries (`Stock::whereIn(...)` in `fetchCurrentPrices()`, `$item->saveQuietly()` in the alert-flag logic), which is both a `docs/GUIDELINES.md` Repository-pattern violation and untestable without a real database. Moved both into repositories:
  - **`app/Frontend/Interfaces/StockRepositoryInterface.php`** / **`StockRepository.php`** — new `getLatestPrices(symbols): array`.
  - **`app/Frontend/Interfaces/PortfolioRepositoryInterface.php`** / **`PortfolioRepository.php`** — new `setAlertFlag(item, column, value): void`.
  - `PortfolioService` now injects `StockRepositoryInterface` alongside `PortfolioRepositoryInterface` and contains zero direct Eloquent calls — fully unit-testable with Mockery.

### Docs updated:
- **`AGENTS.md`** — "AFTER EVERY TASK" step 1 and a new "FORBIDDEN PATTERNS" row now mandate tests tagged `#[Group(...)]`, run in isolation (not the full suite). Points to `docs/TESTING.md`.
- **`docs/GUIDELINES.md`** — mirrored the same rule in "Quick Checklist for AI" and "Verification Requirements".

### Known issue (documented, not fixed here):
- `database/migrations/2026_05_29_000001_partition_stock_prices_by_year.php` is raw MySQL-only SQL (`ALTER TABLE ... DROP FOREIGN KEY`, `PARTITION BY RANGE`) and cannot run against the SQLite `:memory:` DB `phpunit.xml` configures — so `RefreshDatabase`/`$this->artisan('migrate')` is unusable in tests today. Every test above mocks its `*RepositoryInterface` dependencies instead of touching a real DB. See `docs/TESTING.md` for the full explanation and the workaround pattern to follow for future tests.

---

## PORTFOLIO_REAL_PRICES_ALERTS_AND_SCREENER - September 14, 2026

### Summary:
Fixed portfolio prices (was mocked with `rand()`), added target/stop-loss email alerts, and added a stock screener filtered by cached financial ratios.

### Added:
- **`app/Notifications/PortfolioAlertNotification.php`** — `ShouldQueue` mail notification sent once when a `PortfolioItem` crosses its `target_price` or `stop_loss_price`. Dispatched on the `high` queue so it's never stuck behind heavy Python-backed sync jobs.
- **`app/Console/Commands/SyncPortfolioPrices.php`** — `sync:portfolio-prices`: refreshes `current_price` for every active portfolio's items from the latest cached `StockPrice` and checks alerts. Scheduled daily at 16:00 (30 min after `sync:stock-prices`) and run once on every `docker compose up` via `docker/php/scheduler-entrypoint.sh`.
- **Migration** `2026_09_14_153154_add_alert_tracking_to_portfolio_items_table.php` — adds `target_alerted_at`, `stop_loss_alerted_at` (nullable timestamps) to `portfolio_items`, so each threshold only notifies once until price moves back past it.
- **Route** `GET /stock/screener` (`stock.screener`) → `StockController@screener` — filters cached ratio data (P/E, P/B, ROE, ROA, dividend yield, debt/equity) with sortable columns. View: `resources/views/stock/screener.blade.php`. Linked from the navbar "Cổ phiếu" dropdown and footer.
- **`CompanyFinancialService::screenStocks()`** — parses the latest-year column out of each cached `company_financials` ratio JSON blob (`SCREENER_METRICS` label map), cached under `screener_ratio_metrics` (1h) since the source table is small.
- **`PortfolioRepositoryInterface::getAllActivePortfolios()`** / **`CompanyFinancialRepositoryInterface::getAllRatiosByPeriod()`** — new repository methods backing the above.

### Fixed:
- **`app/Frontend/Services/PortfolioService.php`** — `fetchCurrentPrices()` previously returned `rand(10000, 50000) / 100` (mock data). Now reads the real latest close from `Stock::latestPrice` (existing `latestOfMany` relation), so portfolio P&L and target/stop-loss checks reflect actual synced prices.

### Infra:
- **`docker/php/supervisord.conf`** — redis queue workers now run `queue:work redis --queue=high,default` (was `--queue` unset ⇒ `default` only), so a stuck/slow default-queue job (e.g. a `ProcessStockPriceSync` chunk hitting a VCI timeout) can never delay a time-sensitive notification.

### Known issue observed while testing (not caused by this change):
- `trading.vietcap.com.vn` (VCI, vnstock's price source) was intermittently timing out, causing `ProcessStockPriceSync` jobs to hang near their 600s timeout and tying up all 3 queue workers. Pre-existing — same root cause as the ~594 old rows already in `failed_jobs`. Not fixed here; flagged for a future look if it recurs.

---

## BACKEND_ADMIN_UPGRADE - June 27, 2026

### Summary:
Complete upgrade of the admin backend panel with real data, activity logging, and sync management.

### New Components:
- **`app/Models/ActivityLog.php`** — Eloquent model for `activity_logs` table. No `updated_at`. Static `iconConfig()` method maps event_type to icon/color.
- **`app/Support/ActivityLogger.php`** — Static helper `ActivityLogger::log(eventType, description, properties, user)`. Catches all Throwable, never crashes caller.
- **`app/Backend/Interfaces/ActivityLogRepositoryInterface.php`** — Interface: `paginate(filters, perPage)`, `countByType()`.
- **`app/Backend/Repositories/ActivityLogRepository.php`** — Implementation of above. Filters: type, date, search.
- **`app/Backend/Controllers/SyncStatusController.php`** — Shows data sync status cards + AJAX trigger buttons for 5 commands (whitelist enforced).
- **`resources/views/backend/sync-status/index.blade.php`** — Sync Status admin page.
- **`resources/views/backend/sync-status/_icon.blade.php`** — SVG icon partial.
- **`resources/views/backend/timeline/_icon.blade.php`** — SVG icon partial for timeline.
- **`database/migrations/2026_06_27_000001_create_activity_logs_table.php`** — Creates `activity_logs` table.

### Rewritten Components:
- **`app/Backend/Controllers/TimelineController.php`** — Removed fake hardcoded data. Now uses `ActivityLogRepository` for real data with filters.
- **`app/Backend/Controllers/DashboardController.php`** — Now includes news count/sync time, exchange rates, stock prices, hot industries, financials, activity_today, and recent_activity.
- **`resources/views/backend/timeline/index.blade.php`** — Real data, filter form, pagination.
- **`resources/views/backend/dashboard/index.blade.php`** — 4 rows: core stats, data sync stats, recent users/portfolios, recent activity + quick actions.

### ActivityLogger Hooks Added:
- `AuthController::register()` → `user_register`
- `PortfolioController::store()` → `portfolio_created`
- `NewsController::updateRss()` → `news_sync`
- `StockController::updatePrices()` → `stock_price_sync`
- `AdminAuthController::login()` → `admin_login`

### Routes Added:
- `GET /admin/sync-status` → `admin.sync-status`
- `POST /admin/sync-status/trigger/{key}` → `admin.sync-status.trigger`

---

## Fix: SYNC_NEWS_SCHEDULER_MISSING - June 27, 2026

### Problem:
- `/news` page chỉ hiển thị tin tức từ 4 tuần trước, không có bài mới.
- `php artisan sync:news` chạy thủ công thì OK (lưu 260 bài mới), nhưng scheduler không tự chạy.

### Root Cause:
- Laravel 12 chỉ sử dụng schedule được khai báo trong `bootstrap/app.php` `->withSchedule()`.
- `app/Console/Kernel.php` tồn tại nhưng bị bỏ qua hoàn toàn — không được bind làm console kernel.
- `sync:news` **chỉ được khai báo trong `Kernel.php`**, không có trong `bootstrap/app.php` → không bao giờ chạy trong 4 tuần.
- Verify bằng `php artisan schedule:list` → `sync:news` hoàn toàn vắng mặt.

### Fix:
- **`bootstrap/app.php`** — thêm `$schedule->command('sync:news')->everyThirtyMinutes()->withoutOverlapping()->runInBackground()` vào `->withSchedule()`.
- Xóa cache `homepage_news` và `frontend_news_categories` để user thấy tin tức mới ngay.

### Lesson Learned:
- **Laravel 12**: LUÔN khai báo schedule trong `bootstrap/app.php` `->withSchedule()`, KHÔNG dùng `Kernel.php::schedule()`.
- `app/Console/Kernel.php` vẫn được giữ lại để đăng ký `$commands[]` (auto-discover commands), nhưng `schedule()` method của nó bị bỏ qua.
- Khi thêm scheduled command mới → chỉ cần thêm vào `bootstrap/app.php`.

---

## Feature Update: COMPANY_FINANCIALS_AND_BACKEND_USER_LAYER - June 27, 2026

### Added:
- **Migration** `2026_05_29_161453_create_company_financials_table.php` — `company_financials(symbol, type, period, raw_data JSON, synced_at)`
- **Model** `app/Models/CompanyFinancial.php` — fillable(symbol, type, period, raw_data, synced_at); casts raw_data→array, synced_at→datetime; `STALE_DAYS=30` constant
- **`app/Frontend/Interfaces/CompanyFinancialRepositoryInterface.php`** — `find(symbol, type, period)`, `upsert(symbol, type, period, rawData)`
- **`app/Frontend/Repositories/CompanyFinancialRepository.php`** — implements interface; uses `updateOrCreate` for upsert
- **`app/Frontend/Services/CompanyFinancialService.php`** — DB-cache-first logic: returns cached record if non-empty, else fetches from Python and persists; `syncSymbol()` for force-fetch by Artisan command
- **`py/get_company_finance.py`** — fetches income/balance/cashflow/ratio statements via vnstock for a symbol+type+period
- **Route** `GET /stock/finance` (`stock.finance`) → `StockController@finance` — JSON API for financial data; validates symbol/type/period inputs
- **`app/Jobs/SyncCompanyFinancialJob.php`** — queued job for syncing all types/periods for one symbol
- **`app/Console/Commands/SyncCompanyFinancials.php`** — `php artisan sync:company-financials` with `--symbol`, `--type`, `--period`, `--stale`, `--limit`, `--dispatch` options
- **`app/Backend/Interfaces/UserRepositoryInterface.php`** — `paginate`, `findWithRelations`, `create`, `update`, `delete`
- **`app/Backend\Interfaces\UserServiceInterface.php`** — `listUsers`, `getRoles`, `createUser`, `updateUser`, `deleteUser`, `findWithRelations`
- **`app/Backend/Repositories/UserRepository.php`** — full CRUD with eager-load roles/profile, `syncRoles()` on create/update
- **`app/Backend/Services/UserService.php`** — implements `UserServiceInterface`; delegates DB ops to `UserRepository`

### Modified:
- **`app/Frontend/Controllers/StockController.php`** — injected `CompanyFinancialService`; added `finance()` action
- **`app/Backend/Controllers/UserController.php`** — refactored to inject `UserServiceInterface` (was using direct Eloquent); all CRUD via service layer
- **`app/Providers/AppServiceProvider.php`** — added bindings: `BackendUserRepositoryInterface→BackendUserRepository`, `BackendUserServiceInterface→BackendUserService`, `CompanyFinancialRepositoryInterface→CompanyFinancialRepository`

### Technical:
- Financial data is cached in DB per `(symbol, type, period)` tuple; STALE_DAYS=30 before allowing background refresh
- Python script outputs `{ "data": [...], "periods": [...] }` JSON to stdout
- `finance()` API validates: symbol must match `/^[A-Z0-9]{1,20}$/`, type in [income,balance,cashflow,ratio], period in [quarter,year]
- Backend User CRUD now follows full Controller→Service→Repository pattern consistent with Stock and News admin modules

---

## Feature Update: NEWS_CATEGORIES_FRONTEND - May 30, 2026

### Added:
- **Migration** `2026_05_30_130000_create_news_categories_table.php` — `news_categories(id, name, slug)` seeded with 4 initial categories
- **Migration** `2026_05_30_130001_refactor_news_category_to_category_id.php` — replaces `news.category` varchar with `news.category_id` FK; migrates all 260 existing rows
- **Model** `app/Models/NewsCategory.php` — fillable(name, slug), hasMany(News)
- **Updated Model** `app/Models/News.php` — `category_id` in fillable, `belongsTo(NewsCategory)`
- **`app/Frontend/Interfaces/NewsRepositoryInterface.php`** — `getLatest`, `paginate`, `getCategories`
- **`app/Frontend/Repositories/NewsRepository.php`** — reads from `news` table with category relationship
- **`app/Frontend/Controllers/NewsController.php`** — `index(?$categorySlug)`: paginated news + category sidebar
- **Routes** `/news` (news.index) and `/news/category/{slug}` (news.category)
- **`resources/views/news/index.blade.php`** — hero header, search bar, card grid, sidebar category nav, pagination
- **`resources/frontend/css/news/index.css`** — full styling (hero, grid, cards, sidebar, badges)

### Modified:
- **`app/Frontend/Interfaces/NewsServiceInterface.php`** — `getLatestNews()→Collection`, `getPaginatedNews()`, `getCategories()`
- **`app/Frontend/Services/NewsService.php`** — rewrites from RSS live-fetch to DB-backed via `NewsRepositoryInterface`
- **`app/Frontend/Controllers/StockController.php`** — `getLatestNews(6)` (was 4), returns `Collection` not array
- **`resources/views/index.blade.php`** — news section uses Eloquent model attrs (`$item->url`, `$item->image_url`, `$item->published_at`); "Xem tất cả" links to `/news`
- **`resources/views/layouts/app.blade.php`** — added "Tin tức" dropdown nav with category sub-items (queried inline)
- **Backend `NewsService`** — SOURCES now use hardcoded `category_id` (1–4), no string lookup
- **Backend `NewsRepository`** — `getCategories()` added; `paginate()` adds `category_id` filter + `with('category')` eager load
- **Backend `news/index.blade.php`** — category filter dropdown; `$item->category?->name` instead of string
- **`AppServiceProvider`** — binds `FrontendNewsRepositoryInterface → FrontendNewsRepository`

### Technical:
- 260 news rows all have `category_id` populated after migration
- DB schema: `news_categories(id,name,slug)` ← `news.category_id` FK
- Nav dropdown queries `NewsCategory::orderBy('name')` inline in layout (1 query, cached by OPcache)
- Frontend `NewsService` reads from DB (no live RSS), homepage news cached 15 min via Redis

---

## Feature Update: NEWS_RSS_SYSTEM - May 30, 2026

### Added:
- **Migration** `2026_05_30_120000_create_news_table.php` — `news` table with dedup via `url_hash` (MD5), index on `source` + `published_at`
- **Model** `app/Models/News.php` — fillable + datetime casts
- **`app/Backend/Interfaces/NewsRepositoryInterface.php`** — `paginate`, `insertNew`, `getSources`, `getLatestSyncTime`
- **`app/Backend/Interfaces/NewsServiceInterface.php`** — `listNews`, `syncFromAllSources`, `getSources`
- **`app/Backend/Repositories/NewsRepository.php`** — bulk insert with dedup, filter paginate (search/source/date range)
- **`app/Backend/Services/NewsService.php`** — crawls 5 RSS feeds (VnExpress ×2, CafeF ×2, Dân Trí ×1), parses SimpleXML, extracts images from enclosure/media:content/description
- **`app/Console/Commands/SyncNews.php`** — `php artisan sync:news`
- **Scheduler** `Kernel.php` — `sync:news` every 30 minutes
- **DI Bindings** `AppServiceProvider` — `BackendNewsRepositoryInterface → BackendNewsRepository`, `BackendNewsServiceInterface → BackendNewsService`

### Modified:
- **`app/Backend/Controllers/NewsController.php`** — replaced fake hardcoded data with proper DI injection of `NewsServiceInterface`; `index()` paginate + filter; `updateRss()` calls service and reports count
- **`resources/views/backend/news/index.blade.php`** — full rewrite: filter form (search/source/date), thumbnail + description table, source badges, pagination, spinner on sync button

### Technical:
- RSS sources: VnExpress Kinh doanh, VnExpress Chứng khoán, CafeF Thị trường, CafeF Doanh nghiệp, Dân Trí Kinh doanh
- Dedup by `url_hash = MD5(url)` — safe to re-run sync anytime
- 260 articles saved on first sync (160 + 100 after URL fix)

---

## Feature Update: STOCK_ADMIN_SERVICE_LAYER - May 30, 2026

### Problem Solved:
- `StockController` was calling `StockRepositoryInterface` directly — violating the Controller→Service→Repository pattern required by AGENTS.md.
- Business logic (symbol normalization, data transformation) was leaking into the controller.

### Solution:
- Added full Service layer for admin stock management:
  - `app/Backend/Interfaces/StockServiceInterface.php` — contract with 6 methods
  - `app/Backend/Services/StockService.php` — implements business logic (normalization, orchestration, job dispatch stub); injects `StockRepositoryInterface`
- Updated `StockController` to inject `StockServiceInterface` instead of repository directly.
- Moved `strtoupper(trim($symbol))` normalization from controller to `StockService`.
- Added guard in `updateStock()` to `unset()` exchange/industry/market_cap fields — prevents admin form from ever overwriting auto-synced data.
- Registered `BackendStockServiceInterface → BackendStockService` binding in `AppServiceProvider`.

### Modified:
- **`app/Backend/Interfaces/StockServiceInterface.php`** — NEW
- **`app/Backend/Services/StockService.php`** — NEW
- **`app/Backend/Controllers/StockController.php`** — inject service; move transforms out
- **`app/Providers/AppServiceProvider.php`** — add service binding

---



### Problem Solved:
- `/admin/stocks` threw `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'exchange' in 'field list'` — migration never ran.
- `stocks` table had redundant `exchange`, `industry`, `market_cap` columns that duplicated data already in `stock_symbols` (master reference table).
- `StockController` used no Repository pattern — direct Eloquent calls in controller violated architecture.
- `StockController.php` had a duplicate class definition causing PHP fatal error.

### Solution:
- Ran pending migration `2026_05_02_032539_add_exchange_column_to_stocks_table`.
- Created migration to add `exchange` and `industry` to `stock_symbols` table.
- Created migration to remove `exchange`, `industry`, `market_cap` from `stocks` table.
- Updated `Stock` model: removed redundant fillable fields, added `symbolInfo()` hasOne relationship to `StockSymbol`.
- Updated `StockSymbol` model: added `exchange` and `industry` to `$fillable`.
- Updated `py/get_stock_list.py` to fetch exchange and industry from vnstock `symbols_by_exchange()` / `symbols_by_industries()`.
- Updated `StockService::syncStockSymbolsAndDetails()` to persist exchange/industry to `stock_symbols`.
- Implemented full Repository pattern for Backend stocks:
  - `app/Backend/Interfaces/StockRepositoryInterface.php`
  - `app/Backend/Repositories/StockRepository.php` (eager-loads `symbolInfo`, caches exchanges 5 min)
  - Registered binding in `AppServiceProvider` (aliased to avoid collision with Frontend interface)
- Rewrote `app/Backend/Controllers/StockController.php`: constructor DI, `Gate::authorize()`, English docblocks, return type hints, all DB ops via repository.
- Created missing admin views: `create.blade.php`, `edit.blade.php`, `show.blade.php` for stocks.
- Updated `index.blade.php` to show exchange badge via `$stock->symbolInfo->exchange`.

### Modified:
- **`app/Backend/Controllers/StockController.php`** — full rewrite with Repository DI
- **`app/Backend/Interfaces/StockRepositoryInterface.php`** — NEW
- **`app/Backend/Repositories/StockRepository.php`** — NEW
- **`app/Providers/AppServiceProvider.php`** — added Backend stock binding
- **`app/Models/Stock.php`** — removed redundant fillable, added `symbolInfo()` relationship
- **`app/Models/StockSymbol.php`** — added exchange/industry to fillable
- **`py/get_stock_list.py`** — fetches exchange and industry from vnstock
- **`app/Frontend/Services/StockService.php`** — writes exchange/industry to stock_symbols
- **`resources/views/backend/stocks/index.blade.php`** — uses symbolInfo relationship
- **`resources/views/backend/stocks/create.blade.php`** — NEW
- **`resources/views/backend/stocks/edit.blade.php`** — NEW
- **`resources/views/backend/stocks/show.blade.php`** — NEW
- **`database/migrations/2026_05_30_000001_add_exchange_industry_to_stock_symbols_table.php`** — NEW
- **`database/migrations/2026_05_30_000002_remove_redundant_columns_from_stocks_table.php`** — NEW

---

## Feature Update: AI_GROQ_MIGRATION_AND_SECURITY_HARDENING - May 30, 2026

### Problem Solved:
- OpenRouter free tier extremely unstable: most model IDs returned HTTP 404 (models removed), popular models 429 rate-limited constantly.
- `AiService` had no timeout → requests hung indefinitely.
- XSS vulnerability: `data.answer` and user `msg` rendered via raw `innerHTML` without escaping.
- Prompt injection possible: no input sanitization, system prompt too weak.
- API response body logged in full → potential info leak.
- AI market prediction called live API on every click, no caching.
- Chat allowed double-send (no busy lock).

### Solution:
- Migrated AI provider from OpenRouter → **Groq** (free: 14,400 req/day, ~0.4s response time)
- Added XSS escaping for all AI output and user input in frontend
- Hardened system prompt against jailbreak/role-play/injection
- Added server-side input sanitization (control chars, null bytes)
- Cached market prediction result 2 hours per week in Redis
- Added busy lock + AbortController timeout in frontend chat

### Modified:
- **`app/Frontend/Services/AiService.php`**
  - Provider: OpenRouter → **Groq** (`https://api.groq.com/openai/v1/chat/completions`)
  - Models: `llama-3.3-70b-versatile` → `llama3-70b-8192` → `gemma2-9b-it` (fallback chain)
  - Added `->timeout(30)` to all HTTP calls
  - `predictMarket()`: result cached in Redis 2 hours (`ai_market_predict_YYYYWW`)
  - System prompt hardened: scope-limited to finance, anti-jailbreak, no HTML/script output
  - Log truncated to 300 chars (was full body)
  - 0.3s delay between fallback retries
  - Config key: `config('services.groq.key')`

- **`app/Frontend/Controllers/StockController.php`** — `aiChat()`: strip control chars (`\x00`–`\x1F`) from user input before sending to AI

- **`config/services.php`** — added `'groq' => ['key' => env('GROQ_API_KEY')]`

- **`.env`** — added `GROQ_API_KEY=...`

- **`resources/frontend/js/layouts/app.js`** — added `escapeHtml()` helper; applied to `msg` and `data.answer`; busy lock `_aiChatBusy`; `AbortController` 35s timeout; check `res.ok` before parse

- **`resources/frontend/js/index.js`** — added `_escapeHtml()` helper; applied to `data.result`; `AbortController` 40s timeout; check `res.ok`; button re-enabled on error

### Performance:
- Before (OpenRouter): 4–8s response, frequent 404/429 failures
- After (Groq): **~0.4s chat, ~1.9s predict**, stable HTTP 200

### Verified:
- Chat: `FPT là cổ phiếu gì?` → response in 0.41s ✓
- Predict: full market analysis in 1.94s ✓

---

## Feature Update: DOCKER_MIGRATION - May 30, 2026

### Problem Solved:
- XAMPP requires manual start/stop of services; no reproducible environment.
- Python venv management difficult on Windows.
- No HTTPS support locally.
- Queue workers required manual `.bat` script; scheduler not persistent.

### Solution:
Full migration to Docker Compose stack with 6 containers.

### New Files:
- **`docker-compose.yml`** — 6 containers: nginx, php (PHP-FPM 8.2 + Python venv), mysql:8.0, redis:7, queue (supervisor 6 workers), scheduler
- **`docker/php/Dockerfile`** — PHP 8.2-FPM + `python3-venv` + `pip install vnstock pandas` in `/opt/venv`
- **`docker/nginx/default.conf`** — HTTPS server block, HTTP→HTTPS redirect, fastcgi proxy to `php:9000`
- **`docker/nginx/ssl/sunstock-local.dev.pem`** + key — mkcert cert trusted by Windows/browsers, expires 2028
- **`docker/php/supervisord.conf`** — 6 `queue:work redis` workers in queue container
- **`docker/php/php.ini`** — upload_max_filesize, max_execution_time overrides
- **`.dockerignore`** — excludes node_modules, vendor, storage logs, docker/nginx/ssl
- **`.env`** — updated: `DB_HOST=mysql`, `REDIS_HOST=redis`, `APP_URL=https://sunstock-local.dev`, `PYTHON_PATH=/opt/venv/bin/python3`
- **`.env.xampp`** — backup of original XAMPP config
- **`docs/DOCKER.md`** — comprehensive guide: setup, daily commands, exec into containers, MySQL Workbench, troubleshooting, production notes

### Stack Details:
| Container | Image | Host Port |
|---|---|---|
| nginx | nginx:1.27-alpine | 80, 443 |
| php | stock-app-php (custom) | — |
| mysql | mysql:8.0 | 3307 |
| redis | redis:7-alpine | — |
| queue | stock-app-php | — |
| scheduler | stock-app-php | — |

### Access:
- **App**: `https://sunstock-local.dev` (add `127.0.0.1 sunstock-local.dev` to `hosts` file)
- **MySQL Workbench**: host `127.0.0.1`, port `3307`, user `root`, password `<DB_PASSWORD từ .env>`

### Common Commands:
```bash
docker compose up -d                           # Start all containers
docker compose down                            # Stop (data volumes persist)
docker exec -it stock-app-php-1 bash          # Shell into PHP container
docker exec stock-app-php-1 php artisan ...   # Run artisan commands
```

---

## Feature Update: IDEMPOTENCY_SYNC_SKIP_EXISTING_DATA - May 30, 2026

### Problem Solved:
- `backfill:stock-prices --dispatch` dispatched ~312 jobs every time `.bat` was run, even if all stocks already had historical data from 2018+.
- `sync:stock-prices` dispatched 1,558 jobs every daily run, even for stocks that already had today's price synced (e.g., ran twice in a day).
- `sync:company-financials --dispatch --stale` created a job per symbol even when all 8 type/period combinations were fresh — jobs would immediately skip all work.

### Solution:
Add pre-dispatch idempotency checks in all 3 commands so jobs are only created when there is actual work to do.

### Modified:
- **`app/Console/Commands/SyncStockPrices.php`**
  - Added `--force` option
  - Before chunking symbols, queries `StockPrice` for `date = today` → gets set of already-synced `stock_id`s
  - Filters out those stock IDs before dispatch
  - Reports: `Skipped {N} symbols already synced today (YYYY-MM-DD). Use --force to override.`
  - Result: **979 of 1,558 stocks skipped** on re-run; only 58 jobs dispatched instead of 156

- **`app/Console/Commands/SyncCompanyFinancials.php`** (dispatch mode)
  - In `--dispatch` mode with `--stale`: before dispatching job per symbol, checks all type/period combinations via `$this->repo->find()` + `isStale()`
  - If ALL 8 combinations (4 types × 2 periods) are fresh → skip symbol, no job created
  - If ANY combination is stale/missing → dispatch job (job still respects `$stale` flag internally)
  - Reports: `Dispatched {N} jobs | Skipped (all fresh): {M}`

- **`app/Console/Commands/BackfillStockPrices.php`**
  - Added `--force` option
  - Uses **2-year threshold**: if a stock has any `StockPrice` record with `date <= now() - 2 years`, it has already been backfilled (daily-sync-only stocks only have 1-2 years of data)
  - Filters out already-backfilled stocks before dispatch
  - Reports: `Skipped {N} stocks already have historical data (data before YYYY-MM-DD). Use --force to rebackfill.`
  - Result: **900 of 1,558 stocks skipped** on re-run

### Verified:
- `php artisan sync:stock-prices` → `Skipped 979 symbols already synced today (2026-05-29)` ✓
- `php artisan sync:company-financials --symbol=VCB --type=income --period=quarter --dispatch --stale` → `Dispatched 0 jobs | Skipped (all fresh): 1` ✓
- `php artisan backfill:stock-prices --dispatch` → `Skipped 900 stocks... Dispatched 132 jobs` ✓
- `--force` flag bypasses all checks and dispatches everything ✓

---

## Feature Update: COMPANY_FINANCIALS_DB_CACHE_AND_ARCHITECTURE - May 29, 2026

### Problem Solved:
- `GET /stock/finance` fetched live from vnstock on every request → slow (2–5s), fails when API is down.
- Financial data (income/balance/cashflow/ratio) updates quarterly/annually — no need for live fetch.
- Original `finance()` method had direct DB query (`CompanyFinancial::where(...)`) inside Controller — violation of Controller→Service→Repository architecture.

### Solution:
Cache financial data in a dedicated DB table. First request fetches live and persists; all subsequent requests are instant DB reads. Monthly scheduler refreshes stale records.

### New Files:
- **`database/migrations/2026_05_29_161453_create_company_financials_table.php`**
  - Table `company_financials`: `symbol`, `type` (enum income/balance/cashflow/ratio), `period` (enum quarter/year), `raw_data` (JSON), `synced_at`
  - Unique index on `(symbol, type, period)`
- **`app/Models/CompanyFinancial.php`** — `$timestamps = false`, `raw_data` cast to array, `isStale()` method (>30 days)
- **`app/Frontend/Interfaces/CompanyFinancialRepositoryInterface.php`** — `find()` + `upsert()` contract
- **`app/Frontend/Repositories/CompanyFinancialRepository.php`** — implements interface, all DB access here
- **`app/Frontend/Services/CompanyFinancialService.php`**
  - `getFinancialData()`: DB hit → fallback Python → persist (used by controller)
  - `syncSymbol()`: always fetch Python → persist (used by sync command)
  - Fixed `2>/dev/null` → cross-platform (`2>NUL` on Windows, `2>/dev/null` on Unix)
- **`app/Console/Commands/SyncCompanyFinancials.php`**
  - Signature: `sync:company-financials {--symbol=} {--type=} {--period=} {--stale} {--limit=50}`
  - Uses `CompanyFinancialService::syncSymbol()` + `CompanyFinancialRepositoryInterface::find()` — no direct DB access
  - `--stale`: skip records synced within last 30 days

### Modified:
- **`app/Frontend/Controllers/StockController.php`** — `finance()` method now delegates entirely to `$this->financialService->getFinancialData()`. Removed all direct DB access and Python exec from controller.
- **`app/Providers/AppServiceProvider.php`** — added `CompanyFinancialRepositoryInterface → CompanyFinancialRepository` binding
- **`bootstrap/app.php`** — added `sync:company-financials --stale --limit=50` schedule monthly on 5th at 02:00
- **`app/Console/Kernel.php`** — command auto-discovered via `$this->load()`, removed from explicit `$commands` array
- **`start-workers.bat`** — added step `[3/8]` running `sync:company-financials --stale --limit=50` on startup

### Architecture (Controller → Service → Repository):
```
StockController::finance()          ← validates input only
  → CompanyFinancialService::getFinancialData()
    → CompanyFinancialRepositoryInterface::find()   ← DB read
    → [miss] fetchFromPython()                      ← Python exec (once only)
    → CompanyFinancialRepositoryInterface::upsert() ← DB write

SyncCompanyFinancials::handle()     ← Artisan command
  → CompanyFinancialService::syncSymbol()
    → fetchFromPython()                             ← forced fetch
    → CompanyFinancialRepositoryInterface::upsert() ← DB write
```

### Verified:
- `php artisan migrate` → `company_financials` table created ✓
- `php artisan sync:company-financials --symbol=FPT --type=income --period=quarter` → `Synced: 1` ✓
- `php artisan tinker` → `CompanyFinancial::count()` = 1 ✓
- `GET /stock/finance?symbol=FPT&type=income&period=quarter` → instant DB read on 2nd request ✓
- `php artisan sync:company-financials --help` → command registers correctly via auto-discovery ✓

---

## Feature Update: TECHNICAL_INDICATORS_AND_COMPANY_FINANCIALS - May 29, 2026

### Features Added:
Two major new capabilities added to the Stock Detail page (`/stock?symbol=XXX`).

#### 1. Technical Indicators on Price Chart
Overlay technical analysis indicators directly onto the existing ApexCharts candlestick/line chart.

- **Indicator toolbar** — Row of checkboxes above chart controls: MA20, MA50, MA200, Bollinger Bands, RSI(14), MACD
- **Moving Averages** — Simple Moving Average (SMA) overlaid as line series on main chart (MA20=blue, MA50=orange, MA200=red)
- **Bollinger Bands** — 20-period SMA ± 2σ. Renders upper/mid/lower lines on main chart
- **RSI(14)** — Wilder smoothing. Renders as a separate sub-chart below main chart (0–100 scale, 70/30 reference lines)
- **MACD(12,26,9)** — EMA-based. Renders as a separate sub-chart: MACD line (blue), Signal line (orange), Histogram (bar, green/red)
- **Period filter integration** — All indicators update when user filters by 1M/3M/6M/1Y/All
- **Pre-computation** — All indicator values computed once on full dataset when chart loads; filtering just slices the arrays (no recalc)

#### 2. Company Financial Data
On-demand financial statement viewer for any Vietnamese listed company.

- **"Tải dữ liệu tài chính" button** — Appears at bottom of stock page, loads on first click
- **4 statement types** — Kết quả kinh doanh (income), Bảng cân đối kế toán (balance sheet), Lưu chuyển tiền tệ (cash flow), Chỉ số tài chính (ratios)
- **Quarter / Year toggle** — Fetch quarterly or annual data
- **Latest 8 periods** — Displayed in reverse-chronological order (newest first)
- **Number formatting** — Large values auto-abbreviated: tỷ (billions), tr (millions), K (thousands)
- **4-hour cache** — Reduces API calls; cache keyed by `finance_{symbol}_{type}_{period}`
- **Negative value highlighting** — Red color for negative financial figures

### New Files:
- **`py/get_company_finance.py`** — Python script calling `vnstock.Finance(source='kbs', symbol, show_log=False)` for income/balance/cashflow/ratio. Uses `io.TextIOWrapper` to force UTF-8 stdout (fixes Windows charmap encoding error for Vietnamese text).
- **`app/Frontend/Controllers/StockController.php`** → added `finance()` method — validates inputs, calls Python via `exec()`, scans output from last line for JSON, caches result 4 hours.
- **`routes/web.php`** → added `GET /stock/finance` route
- **`resources/frontend/js/stock/stock.js`** → added indicator math (calcSMA, calcEMAValues, calcBB, calcRSI, calcMACD), ApexCharts integration, IIFE finance AJAX section
- **`resources/frontend/css/stock/stock.css`** → added styles for `.indicator-toolbar`, `.indicator-check`, sub-chart wrappers, `.finance-section`, tab buttons, `.btn-load-finance`

### Bug Fixed (same session):
- **Windows `charmap` encoding error** in `get_company_finance.py`: `os.environ['PYTHONIOENCODING']` set inside Python has no effect on already-open stdout stream. Fixed by using `io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')` to reconfigure stdout at runtime.

### Verified:
- `python get_company_finance.py ACB income quarter` → valid JSON with 26 rows ✓
- `/stock/finance?symbol=FPT&type=income&period=quarter` → returns `{data:[...], periods:[...]}` ✓
- Indicator checkboxes render above chart ✓
- MA20/50/200 overlay on main chart ✓
- RSI + MACD sub-charts appear/disappear on checkbox toggle ✓
- Finance table loads and tab-switching works ✓
- Vite build: `stock-Cc0M-p8Y.js` (15.5 kB) ✓

---

## Feature Update: HISTORICAL_BACKFILL_PARALLEL_QUEUE - May 29, 2026

### Problem Solved:
- Stock price history showed only 2025–2026 data (e.g., FPT chart). Root cause: `py/get_stock.py` hardcoded `timedelta(days=365)` as start date.
- Single-worker sync took ~26 minutes for all 1,555 stocks (sequential, 1s Python delay per call).

### Added:
- **`py/get_stock.py`** — Added optional `argv[2]` (start_date) and `argv[3]` (end_date) parameters. Reduced sleep from 1.0s → 0.3s.
- **`app/Jobs/BackfillStockPriceChunk.php`** — Queue job: fetches multi-symbol OHLCV batch via Python for a given date range, upserts into `stock_prices`. `timeout=600`, `tries=2`, `backoff=120`.
- **`app/Console/Commands/BackfillStockPrices.php`** — Artisan command `backfill:stock-prices {--start=2015-01-01} {--symbols=} {--chunk-size=5} {--dispatch}`. With `--dispatch` flag: pushes `BackfillStockPriceChunk` jobs to queue (returns immediately). Without: runs inline sequentially.
- **`start-workers.bat`** — Updated to 6 parallel workers (`--timeout=600 --tries=2`), auto-dispatches backfill on startup, runs scheduler. Flow: sync:exchange-rates → sync:hot-industries → 6 workers → sync:stock-prices (chunk=10) → backfill:stock-prices (--start=2019-01-01 --dispatch) → scheduler.
- **`app/Console/Kernel.php`** — Registered `BackfillStockPrices` in `$commands`.

### Result:
- Full historical backfill from 2019 runs in parallel across 6 workers (~3–4 min vs 26 min before).
- `start-workers.bat` is the single command to spin up entire data pipeline.

---

## Feature Update: DATABASE_SCALABILITY_PARTITIONING - May 29, 2026
### Problem Solved:
- `stock_prices` table at 417k rows (1,555 stocks × ~268 rows avg), projected 2.4M rows in 5 years.
- Growing table causes slow bulk INSERT during sync, large backups, difficult archiving.
- No aggregate data for multi-year chart queries (future feature).

### Added:
- **Migration**: `2026_05_29_000001_partition_stock_prices_by_year.php`
  - Drops FK to `stocks.id` (MySQL disallows FK on partitioned tables — enforced at app level)
  - Drops `created_at`/`updated_at` (market OHLCV data is immutable — date IS the timestamp)
  - Changes PRIMARY KEY to composite `(id, date)` (MySQL RANGE partitioning requirement)
  - Applies `PARTITION BY RANGE (YEAR(date))` — partitions p2018 through p2027 + p_future
- **Migration**: `2026_05_29_000002_create_stock_price_summaries_table.php`
  - New table `stock_price_summaries`: monthly OHLCV per stock (open=first day, high=MAX, low=MIN, close=last day, volume=SUM)
  - Unique `(stock_id, period_start)` + index on `period_start`
- **Model**: `app/Models/StockPriceSummary.php` — Eloquent model, `$timestamps = false`
- **Model**: `app/Models/StockPrice.php` — Added `$timestamps = false` (columns dropped)
- **Command**: `app/Console/Commands/GeneratePriceSummaries.php` — `php artisan generate:price-summaries`
  - Loads daily prices per stock (index scan), groups by month in PHP, bulk upserts via `StockPriceSummary::upsert()`
  - Supports `--stock_id=X` for targeted rebuild
- **Schedule**: `bootstrap/app.php` — Added `generate:price-summaries` daily at 17:00 (after 15:30 sync)
- **Kernel**: `app/Console/Kernel.php` — Registered `GeneratePriceSummaries` in `$commands`

### Verified:
- `php artisan migrate` → both migrations ran successfully ✓
- `php artisan generate:price-summaries` → 1,555 stocks with monthly summaries populated ✓
- `php artisan schedule:list` → 5 scheduled tasks including new 17:00 summary job ✓
- `SHOW PARTITIONS` → 11 partitions (p2018–p2027 + p_future) created ✓

### Architecture Notes (for future maintainers):
- Add new partition each January: `ALTER TABLE stock_prices REORGANIZE PARTITION p_future INTO (PARTITION p2028 VALUES LESS THAN (2029), PARTITION p_future VALUES LESS THAN MAXVALUE);`
- To archive old data: `ALTER TABLE stock_prices DROP PARTITION p2018;` (instant, no DELETE needed)
- `stock_price_summaries` → use for chart views > 1 year range (~4,800 rows/year vs ~390k daily)

---

## Feature Update: FIX_VNSTOCK_DEPRECATED_API_AND_SCHEDULER - May 29, 2026
### Problem Solved:
- Stock price data stuck at 2026-05-15 — no data updated since then.
- `ProcessStockPriceSync` jobs were failing with `MaxAttemptsExceededException` (6 failed jobs).
- Root cause 1: `py/get_stock.py` used `Vnstock().stock()` which was **deprecated and removed** by vnstock on 31/08/2025.
- Root cause 2: Laravel 12 does NOT use `Kernel.php` schedule method — scheduling was silently ignored since project started.

### Fixed:
- **Python**: `py/get_stock.py` — replaced deprecated `Vnstock().stock(symbol, source='VCI')` with `from vnstock.api.quote import Quote; Quote(symbol, source='VCI').history(start, end)`. Removed unused `import os`. Fixed `show=False` parameter (not supported by `Quote.history()`).
- **Python**: `py/get_stock_list.py` — replaced `Vnstock().listing.*` with `Listing().*` (same pattern as `get_hot_industries.py`). Added `show=False` for cleaner output. Removed unused `import os`.
- **Scheduler**: `bootstrap/app.php` — added `->withSchedule()` with all 4 tasks (Laravel 12 requires this; `Kernel.php` schedule() method is ignored by the new Application bootstrap).

### Technical Details:
- vnstock version: 4.0.3 (Python 3.13). New API: `from vnstock.api.quote import Quote`
- `Quote.history(start, end)` — does NOT accept `show=False` parameter (unlike `Listing` methods)
- Laravel 12 bootstrap (`Application::configure()`) ignores `Kernel::schedule()` — must use `->withSchedule(fn(Schedule $s))` in `bootstrap/app.php`
- After fix: `php artisan schedule:list` shows all 4 tasks correctly

### Verified:
- `get_stock.py VCB` returns JSON with data up to 2026-05-29 ✓
- `php artisan schedule:list` shows 4 scheduled tasks ✓
- `ProcessStockPriceSync` job for [VCB,FPT,ACB,HPG,VIC] completed with no failures ✓
- `StockPrice::max('date')` → `2026-05-29` ✓
- Full sync of 78 job batches dispatched and queue:work started ✓

---

## Feature Update: AUTO_SYNC_BACKGROUND_SCHEDULER - May 28, 2026
### Problem Solved:
- Homepage was slow because `fetchHotIndustriesFromPython()` was called synchronously on cache miss (every 3600s), blocking page load for 10-20s.
- Exchange rates had the same issue on the first request of each day.
- No automatic data refresh — user had to manually run `php artisan stock:sync`.

### Added:
- **Migration**: `database/migrations/2026_05_28_070000_create_hot_industries_table.php` — stores hot industry records in DB.
- **Model**: `app/Models/HotIndustry.php` — Eloquent model for `hot_industries` table.
- **Command**: `app/Console/Commands/SyncHotIndustries.php` — `php artisan sync:hot-industries [--limit=100]` — fetches from Python, truncates and re-populates the table, busts cache.
- **Command**: `app/Console/Commands/SyncExchangeRates.php` — `php artisan sync:exchange-rates [--days=1]` — fetches rates from Python, saves to DB, busts cache.

### Modified:
- **Kernel**: `app/Console/Kernel.php` — Added 4 scheduled tasks:
  - `sync:exchange-rates` → daily at 07:30 (before users access)
  - `sync:hot-industries` → daily at 07:45
  - `sync:stock-data` → weekly Monday at 07:00 (symbols rarely change)
  - `sync:stock-prices` → daily at 15:30 (after VN market close)
- **Controller**: `app/Frontend/Controllers/StockController.php` — `home()` now reads hot industries from DB (instant) via new private `getHotIndustries()` method. Falls back to Python on first run and persists result.
- **Python**: `py/get_hot_industries.py` — Fixed for vnstock 4.x API (`Listing()` instead of deprecated `Vnstock().listing`). Joins `all_symbols()` to get `organ_name`. Updated industry name `'Công nghệ Thông tin'` → `'Công nghệ và thông tin'`.

### How to Activate Scheduler (XAMPP/Windows):
```
# Option A: Persistent process (keep terminal open)
php artisan schedule:work

# Option B: Windows Task Scheduler (recommended for production)
# Program: C:\xampp\php\php.exe
# Arguments: C:\xampp\htdocs\stock-app\artisan schedule:run
# Trigger: Every 1 minute, starting at system startup
```

### Verified:
- `php artisan sync:hot-industries` → synced 100 records ✓
- `php artisan sync:exchange-rates` → synced 20 records across 1 date ✓
- DB table `hot_industries` contains 100 rows ✓
- Homepage hot industries section now loads from DB (no Python call on page load) ✓

---

## Feature Update: DOCUMENTATION_AUDIT_AND_EXPANSION - May 28, 2026
### Updated:
- **docs/STRUCTURE.md**: Full audit against actual codebase — added missing controllers (`EmailVerificationController`, all Backend controllers), `Role` model, Middleware, Console Commands, Jobs, full Python scripts list, Backend views, key architectural patterns.
- **docs/GUIDELINES.md**: Added RBAC/Gates usage patterns, email verification notes, Python integration gotcha, caching patterns, updated controller inheritance rules, updated backend middleware to use `admin` alias.
- **docs/AGENTS.md**: Linked new documentation files in the master guide.
### Added:
- **docs/QUICKSTART.md**: Setup, environment, migration, seed, artisan commands, common dev workflow.
- **docs/ROUTES_MAP.md**: Complete route → controller → action mapping table (Frontend + Backend).
- **docs/BINDINGS.md**: All DI bindings (Interface → Implementation) and Gate definitions.
- **docs/PYTHON_INTEGRATION.md**: How to call Python scripts, error handling, adding new scripts.
- **docs/RBAC.md**: Role constants, Gate definitions, middleware, permission matrix.

---

## Feature Update: SEARCH_AND_DATA_INTEGRITY - May 16, 2026
### Fixed:
- **Search UI/UX**: Resolved issue where search bar would auto-select first result incorrectly, improving user control.
- **Data Integrity (ETFs)**: Added support for missing ETF data, specifically "FUEVFVND", which was previously excluded from stock lists.
- **Backend Pipeline**: Updated `get_stock_list.py` and sync logic to handle ETFs and ensure all valid symbols are indexed.
- **Search Logic**: Enhanced backend search to return accurate results for tickers and company names.

### Modified:
- **Python**: `py/get_stock_list.py` - Expanded data fetching to include ETFs.
- **Service**: `app/Frontend/Services/StockService.php` - Improved data synchronization and verification logic.
- **Database**: Updated `stocks` and `stock_symbols` tables to include newly discovered assets.

### Verified:
- **Search**: Tested search functionality with various symbols (VCB, FUEVFVND, etc.) and verified correct behavior.
- **Sync**: Ran `php artisan stock:sync` to confirm all 400+ symbols are correctly populated.

---
### Fixed:
- **Search UI/UX**: Resolved issue where search bar would auto-select first result incorrectly, improving user control.
- **Data Integrity (ETFs)**: Added support for missing ETF data, specifically "FUEVFVND", which was previously excluded from stock lists.
- **Backend Pipeline**: Updated `get_stock_list.py` and sync logic to handle ETFs and ensure all valid symbols are indexed.
- **Search Logic**: Enhanced backend search to return accurate results for tickers and company names.

### Modified:
- **Python**: `py/get_stock_list.py` - Expanded data fetching to include ETFs.
- **Service**: `app/Frontend/Services/StockService.php` - Improved data synchronization and verification logic.
- **Database**: Updated `stocks` and `stock_symbols` tables to include newly discovered assets.

### Verified:
- **Search**: Tested search functionality with various symbols (VCB, FUEVFVND, etc.) and verified correct behavior.
- **Sync**: Ran `php artisan stock:sync` to confirm all 400+ symbols are correctly populated.

---

## Feature Update: PORTFOLIO_MANAGEMENT_FEATURE - January 2025
### Added:
- **Models**: `app/Models/Portfolio.php`, `app/Models/PortfolioItem.php`
- **Database**: `portfolios` and `portfolio_items` tables.
- **Controller**: `app/Frontend/Controllers/PortfolioController.php`
- **Service**: `app/Frontend/Services/PortfolioService.php`
- **Repository**: `app/Frontend/Repositories/PortfolioRepository.php`
- **Interface**: `app/Frontend/Interfaces/PortfolioRepositoryInterface.php`
- **Views**: Complete set of CRUD views for portfolios and stocks.

### Features:
- Portfolio CRUD, Stock Management, Real-time P&L, Analytics, Price Alerts.

---

## Feature Update: AUTHENTICATION_SYSTEM - January 2025
### Added:
- **Controller**: `app/Frontend/Controllers/ProfileController.php`
- **Views**: Profile show/edit.
- **Routes**: Profile management routes.

### Modified:
- **AuthController**: Enhanced validation and security.
- **Layout**: Updated navbar for profile access.

---

## Feature Update: SYSTEM_ERROR_FIXES_AND_EMAIL_VERIFICATION - January 3, 2025
### Fixed:
- SQL errors in stocks table, missing backend views.
- Implemented full email verification and admin manual verification.

---

## Feature Update: RBAC_SYSTEM_MULTIPLE_ROLES_AND_SEPARATE_AUTH - January 3, 2025
### Added:
- Multiple roles support, separate admin login system, enhanced user management UI.

---

## Feature Update: RBAC_SYSTEM_AND_ADMIN_DASHBOARD - May 2, 2026
### Added:
- Role system, Admin Dashboard, Permission Gates, Middleware protection.

---

## Feature Update: CLEANUP_OLD_FILES - May 1, 2026
### Removed:
- Legacy files in old namespaces.
- Optimized autoloader and fixed controller inheritance.
