# Feature Update History

The **latest two days** are kept here in full, newest first. Everything older lives verbatim in [docs/history/](history/) and is summarised in one line per entry under [Earlier work](#earlier-work-one-line-digest). The README changelog has one row per day.

**Maintaining this file:** add new entries at the top. When this file passes ~400 lines, move the entries that are more than two days old to `docs/history/<YYYY-MM>.md` (verbatim, keep the `## NAME - Month D, YYYY` title) and add a one-line digest for each below.

---

## STOCK_PAGE_UX_REFRESH - October 2, 2026

### Summary
Owner: the recent work was invisible to users; propose and build changes people see at once. Package 1 + 4 + 5 of the proposal: new stock page header, modern price table, skeleton loading and number cues.

### Found while looking at the real page (Chrome)
The header printed **"62 VNĐ"** for a 62,100 ₫ share, the chart axis ran 10–140 and the table printed `63.7` with a `VND` badge on every row: the feed is quoted in thousands of VND and the page never converted it. Also: a ~380 px banner that was half empty with five buttons stacked at different widths, and a table of bare numbers.

### Done
- **`StockQuoteSummary`** (pure) converts once: stocks/ETFs in whole VND, indices in points; the page hands `{scale, decimals, unit}` to the script so header, chart axis, legend and table agree (62.100 everywhere; VN-Index stays 1.737,71).
- **New header**: symbol + exchange (HOSE/UPCoM) + company + industry, one row of actions, big coloured price, change chip (`-600 (-0,96%)`), day and 52-week range bars with the close marked, tiles for volume (× the 20-session average), open, and 1/3/6-month and 1-year returns. Phone layout checked.
- **New history table**: close, change in dong and %, open/high/low, volume with a thin bar (amber when ≥ 1.5× average), weekday under the date, no currency column, sticky header, paging window `1 … 4 5 6 … 102`; open/high/low drop out on a phone. Chart legend now spells Mở/Cao/Thấp/Đóng (it showed `C` for the high next to `Đ`).
- **Skeletons**: shimmering placeholders for the chart, the table and the finance block (which dims the old table when you switch tab instead of a spinner); honours reduced motion.
- **Number cues** (`shared/pricefx.js`): the price counts up from the previous close and flashes once red/green.
- Removed the old banner CSS. `VnFormat` gained `signed`, `volume`, `exchange`.

### Tests
Groups `stockPageUx` (PHP 34, Node 17 new). Full suite and Node suite green; Pint clean on touched PHP. Verified in the owner's Chrome (FPT and VNINDEX) and a 390 px phone emulation without horizontal overflow.

### Not done
Dark mode, home-page redesign and the phone bottom navigation (items 3, 2, 6 of the proposal) are still open.

---

## PUBLIC_ENDPOINT_ABUSE_PROTECTION - October 2, 2026

### Summary
Last open item of the security audit (A1): anonymous visitors could make the server start Python processes of up to a minute, limited only by a 30-requests-a-minute per-IP throttle, and so keep dozens running and burn the shared vnstock quota.

### Found (by reading every web-reachable `PythonRunner` call)
- `POST /company/{symbol}/load`: any 2–10 character ticker spawned a run (a made-up symbol took ~60 s to come back "not found"). A loop over random symbols = ~30 concurrent subprocesses.
- `GET /exchange-rate/search?date=`: only checked inside the Python wrapper, so **the raw text was used as a cache key** (unbounded keys, and an array parameter crashed it), every valid date missing from the database spawned a run, and **a failed or refused fetch cached `[]` for 30 minutes**.
- `?force=1` and `/gold/refresh`: manual refresh buttons open to everyone (the gold one shares a global cooldown, so a guest could also burn it).
- Already fine: stock pages (`StockPriceFreshness::isKnown`, failure memory), fund detail (stored codes only, cached for hours), market overview / ETF / fund lists (single flight).

### Fixed
- **`PythonWebGuard`** (new, used by `PythonRunner::run()` for web requests only): global ceiling of 3 concurrent processes and a per-visitor budget (guest IP 4/min and 20/h, signed-in user 10/min and 60/h), counted only for processes that really start; a refused call returns a `refused` result that callers already treat as "source unreachable". All numbers in `.env` (`PYTHON_WEB_*`, `config/python_limits.php`, floored at 1). Queue workers, scheduler and artisan are not limited.
- Company profile: only listed symbols (or ones with a stored profile) reach Python — a made-up ticker is now a 404 in ~0.3 s instead of ~60 s; forced refresh needs a login (401 JSON with `login_url`).
- Exchange rates: a searched date must be a real `Y-m-d`, not in the future, at most 3 years back, otherwise the page shows "Ngày không hợp lệ" before any cache or Python; empty or refused answers for the latest rates are no longer cached (a refused date is not cached either; a genuine "no rates that day" still is).
- `/gold/refresh`: login required, and a guest no longer touches the shared cooldown.

### Verified
Live: 5 made-up symbols → 404 in 0.3–0.7 s each, guest `force=1` and gold refresh → 401, bad dates → warning. Tests (group `webPythonGuard`, 40) count real process starts with a stand-in executable. Full suite 871 passed.

### Not done
Fund detail and the stock first-load still use their own protections plus the new guard; the paid AI endpoints were handled earlier (login + per-account limits). No CAPTCHA.

---

## IMPORTS_CLEANUP - October 1, 2026

### Summary
Owner: classes were written out in full inside the code (for example `\App\Models\User::STATUS_ACTIVE` in a Blade view) — a very serious cleanliness defect that must be forbidden for every AI agent.

### Done
- Scanned the whole project with the PHP tokenizer: **165 inline fully-qualified names in 57 PHP files** (45 `\Mockery`, 30 `\RuntimeException`, 21 `\Throwable`, 13 `\Exception`, models, jobs, commands, interfaces…) and **50 in 21 Blade views**, plus two doc comments. All replaced by imports (`use` in PHP, `@use` in Blade); no name clash existed, so no alias was needed. PHP was rewritten with Pint restricted to the import rules only (the rest of those files was left as it was).
- Rule written into `AGENTS.md` (new "Imports" section and a row in the forbidden-patterns table), `docs/GUIDELINES.md` and the assistant's memory.
- Enforcement: `pint.json` (Laravel preset + `fully_qualified_strict_types` with `import_symbols`, global classes imported) and `tests/Unit/CodeStyle/NoInlineFullyQualifiedNamesTest.php` (group `codeStyle`, 4 tests) which fails on any inline name in PHP, doc comments and Blade.
- Verified: full suite green, `view:cache` compiles every view, public pages return 200.

### Found while checking the views, then fixed
`/profile/edit` returned a 500 for an account with no `user_profiles` row: the view read `$profile->username` and `$profile->mobile` on null. The existing tests only asserted which view the controller returned, so nothing rendered it. The form now falls back to the account name (and an empty mobile), opening the page does not create a row, and saving creates it. New `ProfilePagesRenderTest` (group `profile`, 5 tests) renders both pages for real: no profile, stored profile, old input, a hostile name, and the create-on-save path.

---

## AUTHORIZATION_HARDENING - October 1, 2026

### Summary
Follow-up of the login/register audit: scan of authorization (frontend ownership, admin RBAC, privilege escalation) and the agreed fixes. Decisions by the owner: (1) only `admin` may change users/roles/permissions; (2) account status 0 inactive / 1 active / 2 pending / 4 blocked; (3) AI chat and prediction only for signed-in users with per-account limits read from `.env`.

### Scan (attacks run as tests: user B vs user A, every admin route × role, delegation abuse)
Safe already: ownership of portfolios, items, transactions and watchlist (reads 404, writes change nothing); route × role gates (56 admin routes); CSRF and method checks on admin writes; sensitive files not served.
Found and fixed:
1. **Privilege escalation through delegated permissions** — a `manage-users` holder could make themselves or anyone admin; a `manage-roles` holder could grant their own role any permission. Now every write in users/roles/permissions needs the `admin` role (`AdminOnlyForChanges`), reads stay permission-based.
2. **Admin lock-out** — an admin could remove their own admin role, rename the `admin` role (everyone loses `/admin`, because access is checked by role name) or rename/strip the core `manage-roles`/`manage-permissions`. `AdminGuard` forbids it; the last effective admin is protected.
3. **"Suspend" did nothing** — an un-verified administrator kept access, and open sessions of blocked people kept working. Replaced by real statuses (below) and `EnsureUserIsActive`, which ends such sessions on the next request.
4. **No audit trail** for user/role/permission/portfolio/queue changes — now logged with actor, IP, target and before/after (never secrets).
5. **Dashboard leaked** user e-mails, portfolio owners and the activity feed to roles without `manage-users` / `manage-features` / `view-timeline`.
6. **Admin forms bypassed the policies** (weak passwords, `<script>` names, free-form stock symbols) — now `AuthRules` everywhere; symbols `A-Z0-9{2,10}`.
7. `/portfolio/99999999999999999999` and similar oversized ids gave a 500 — route patterns make them a 404; `roles`/`permissions` `show` routes (no method behind them) removed; manual sync no longer returns the exception text.
8. **Anyone could call the paid AI endpoints** and spawn work — see below.

### Account status
Migration `2026_10_01_000001_add_status_to_users_table` (default 2, back-filled: confirmed e-mail → 1, else 2). Only active + confirmed e-mail can sign in; reasons are shown after the right password only; admin login always answers like a wrong password; a verification link / password reset turns pending → active and never unblocks. Admin user form has the four statuses; `User::applyStatus()` keeps the e-mail confirmation consistent. Because pending accounts cannot sign in, the "resend verification" endpoint is now public (per-IP and per-address limits, same answer for any address, mails only pending accounts) with a form on the login page.

### AI access and limits
`/ai-chat` and `/ai-predict` require login (401 JSON with a readable message and `login_url` for AJAX). Limits are per account, from `.env` (floored at 1): `AI_PREDICT_INTERVAL_MINUTES=15` (one prediction per interval), `AI_CHAT_WINDOW_MINUTES=5` and `AI_CHAT_MAX_QUESTIONS=5`. Over the limit: 429 JSON with a Vietnamese message and `retry_after`; nothing is sent to Groq. Documented in `.env.example` and `config/ai_limits.php`.

### Seeder for manual testing
`DemoStaffSeeder` (local only): `webadmin@`, `support@` (can open /admin, not admins), `pending@`, `inactive@`, `blocked@sunstock.test`, shared demo password.

### Tests
New groups `authzSecurity` (67 + AdminGuard rules), `accountStatus` (46 incl. guard tests), `aiLimits` (18); `demoUsers` +6. Legacy tests adapted (role/permission writes as admin, AI endpoints signed in, watchlist/verification flows). Full suite 805 passed (was 665). Pint run on every touched PHP file (the repo had never been formatted, so some old files changed style).

### Status propagation re-check (same day)
Owner asked to verify the new status is applied everywhere from the frontend to the admin. Walked every place that authenticates, e-mails, schedules or lists users and fixed the gaps: one `User::scopeMayEnter()` now defines "may enter" for both logins and `AdminGuard`; blocked/inactive accounts no longer get reset mails and an old reset token is refused; price-alert e-mails and the scheduled price refresh skip owners who may not sign in (the alert flag is left for later); the dashboard counts by status; the admin user list can be filtered by status. Tests found two real bugs in the new filter ("abc" silently meant status 0, and `when(0, …)` dropped the inactive filter) — both fixed. Remember-me, public pages, AJAX and registration-after-block are covered by tests. +17 tests (822 in total).

### Not done / for the owner
`.env` still has `APP_DEBUG=true`/`APP_ENV=local` (stack traces are returned to the browser): set `false`/`production` when deploying. Other public endpoints that call Python (`/company/{symbol}/load`, `/gold/refresh`, `/stock/*`) are still anonymous, per-IP limited only. No CAPTCHA/2FA.

---
## AUTH_SECURITY_AUDIT - October 1, 2026

### Summary
Owner asked for a full security review of login/register (XSS, CSRF, SQL injection, brute force…), a check of existing tests, a double-check in the browser, simulated attacks, and fixes. The comment feature mentioned afterwards does not exist in the codebase (no model, route or view), so there is nothing to audit there yet.

### Method
Read every auth controller/route/middleware/config, then attacked the running stack with `curl` (isolated cases, limiter reset between them), then re-ran the same attacks after the fixes, then confirmed in the browser pane (reflected payload shown as text, no script executed). Throw-away accounts used for the attacks were deleted afterwards.

### Already safe (verified live and now covered by tests)
CSRF is enforced on every credential POST (419); SQL-injection payloads in e-mail/password/username/token never authenticate and never break the query (Eloquent bindings, array/JSON inputs rejected); mass assignment on register ignored (`role`, `email_verified_at`, `id`…); output is HTML-escaped everywhere user text is shown; session cookie is `Secure`, `HttpOnly`, `SameSite=Lax`; session id regenerated on login; open-redirect parameters ignored; spoofed `X-Forwarded-For` does not reset limits; 1 MB passwords cost 0.3 s (no hashing DoS).

### Vulnerabilities found and fixed
1. **Password-reset poisoning (critical).** URLs in e-mails were built from the request `Host` header: `POST /forgot-password` with `Host: evil.test` made the victim receive a real reset token in a link to evil.test (same for the verification link). Now every URL is pinned to `APP_URL` (`pinRootUrl`), plus `trustHosts` for non-local environments.
2. **Account enumeration on `/reset-password`**: an unknown address answered "Không tìm thấy tài khoản", a known one "Link không hợp lệ". Same message now. The admin login also told a non-admin with a correct password that the account "has no admin rights"; now identical to a wrong password.
3. **Brute force protection was weak**: a single `throttle:5,1` shared by GET pages and POSTs per IP (two page views locked a person out) and nothing per account (a botnet could try unlimited passwords). Replaced by named limiters (per IP, per account+IP, per account per hour) on the POSTs only; forgot-password is also capped per recipient (inbox flooding).
4. **Stored XSS payloads were accepted** as username/mobile (`<script>` was saved). Output escaping stopped it from firing, but the data reached logs/e-mails/exports. Username and mobile are now whitelisted (`AuthRules`), in register and profile; toast helper uses `textContent`.
5. **Weak passwords / unbounded length**: min 8 only. Now 8–128 with a letter and a digit (register, reset, profile, admin account).
6. **Sessions survived a password change.** `authenticateSessions()` added: changing or resetting the password signs every other device out; the profile/admin password forms re-stamp the current session.
7. **No security headers.** Added `SecurityHeaders` middleware (frame-ancestors/clickjacking, nosniff, Referrer-Policy — `no-referrer` on reset URLs, Permissions-Policy, HSTS, form-action/base-uri/object-src CSP, `no-store` on auth pages); nginx `server_tokens off`; `expose_php = Off`. A full script-src CSP needs a nonce rollout (the site uses CDN and inline page-data scripts) and is not done.
8. **Registration was not atomic** (user could exist without profile/role) and failures were swallowed silently: now one transaction and `report($e)`.
9. **Verification dead end (functional)**: login refuses unverified accounts but the verify link required being logged in, so a new user could never verify. The link now works from a signed URL (+ hash of the current e-mail) without a session, and completing a password reset also verifies the mailbox. Login no longer creates a session (or remember cookie) for an unverified account.
10. Failed logins are now logged with IP.

### Not changed / for the owner
- Registration still says "Email đã được sử dụng" (needed for usability; mitigated by the register limiter).
- No CAPTCHA, no 2FA, no compromised-password check (needs an outbound call).
- `APP_DEBUG=true` / `APP_ENV=local` in `.env`: must be `false` / `production` when deployed (stack traces leak otherwise). `.env` was not touched.
- Guests who lost the verification e-mail cannot request another one (resend needs a session); an admin can still verify them.

### Tests
Group `authSecurity`: 120 tests (CSRF, SQLi, brute force variants, enumeration, mass assignment, XSS, Host poisoning, token/verification abuse, admin probing, headers, session invalidation, rule boundaries). Two fixtures in `AccountControllerTest` used a password without a digit and were updated; the old test that asserted the leaking message was replaced. Full suite: 665 passed.

---
## COMPANY_PROFILE_QUEUE_FAILURES - October 1, 2026

### Summary
Admin > Giám sát Queue showed ~44 failed `SyncCompanyProfileJob` after a sync, all with "Không lấy được dữ liệu công ty (nguồn dữ liệu chậm hoặc lỗi kết nối)".

### Root cause
Not the queue and not load: even a single `sync:company-profiles --symbol=FPT` failed after 61s. Timing each vnstock call separately: KBS answered in 0.2–1.4s, but **VCI (`iq.vietcap.com.vn`) timed out** (30s read timeout, retried 3 times by vnstock = ~95s). `get_company_profile.py` already isolated failing sections, but it waited for every thread, so it outlived `PythonRunner`'s 60s ceiling, was killed, printed nothing, and the job threw — discarding the good KBS data.

### Fix
- `py/get_company_profile.py`: `DEADLINE_SECONDS = 40`; sections that have not answered are reported in `errors` and everything else is returned; `os._exit(0)` so a stuck retry thread cannot hold the process. With VCI still down FPT now returns in 40s with overview, ownership, officers, subsidiaries from KBS.
- `CompanyProfileService::sync()`: a partial result (`errors.vci_events`) no longer overwrites the stored corporate events.
- Restarted the queue workers (they keep old PHP in memory) and retried the failed jobs.

### Tests
Group `companyProfile`: +3 (events survive a partial refresh, a clean refresh replaces them without reading the old row, the script's deadline is below the PHP timeout and it exits hard). 44 pass. Also fixed a flaky `goldPrice` test that failed when run within 30 minutes after Vietnamese midnight (it used the real clock; now frozen at midday). Full suite: 545 passed.

### Not fixed (outside our control)
VCI being slow/down: valuation snapshot, events and the long shareholder list are missing from profiles fetched while it is down, and they fill in on a later refresh.

---

## Earlier work — one-line digest

Full text: [2026-09](history/2026-09.md) · [2026-06](history/2026-06.md) · [2026-05 and earlier](history/2026-05-and-earlier.md).

### September 2026 — [archive](history/2026-09.md)

**Sep 30**
- **PORTFOLIO_AUDIT_AND_INSIGHTS** — Owner: users lose interest in the portfolio feature. Asked to (1) audit its UI/UX and current features, (2) seed 4–5 more accounts, (3) build wave 1 of the suggested improvements (benchmark comparison, risk, sector split).
- **ETF_PAGES** — Owner asked for an ETF page after the vnstock capability survey (idea #8). Explored what the feed really has for ETFs before designing anything.
- **CLAUDE_CONFIG_AND_DOCS_REREAD** — Owner asked to re-read AGENTS.md and every file in `docs/`, and to create a `.claude` folder if useful.

**Sep 20**
- **HISTORY_AND_CHANGELOG_CONDENSED** — User: one day had too many README changelog rows and `docs/HISTORY.md` had grown to 1,429 lines / 160 KB.
- **README_SCREENSHOTS_AND_WEBSITE_TOUR** — User: the README Screenshots section was outdated; re-shoot the site's highlights, add a detailed page-by-page `.md`, and make sure nothing sensitive (usernames, passwords, security-related or attackable surfaces) is pictured.
- **VNAI_AGENTS_MD_INJECTION** — User asked why `AGENTS.md` kept showing as modified and was never pushed. Cause: the `vnai` package (dependency of vnstock) appends an 86-line `vnai-bootstrap` "skill router" prompt to `AGENTS.md` every time a Python script run…
- **AI_PREDICT_AND_CHAT_FIX** — User reported two problems: (1) the home page "AI Dự đoán thị trường tuần này" answered "Dịch vụ AI tạm thời không khả dụng…"; (2) the AI chat popup opened but no button worked, typing failed and any message ended in the same s…
- **WEEKLY_DB_BACKUP** — After Docker Desktop lost all containers/images (recovered, volumes intact) the user asked for an automatic weekly database backup stored OUTSIDE Docker: a `database_backup` folder beside the source tree (path derived from wher…
- **ADMIN_REDESIGN_2026** — Task 3 of three. User: the admin backend looks like 2010s UI/UX; find a free, current template and redesign the whole backend to keep users engaged.
- **STOCK_PAGE_FIRST_LOAD_SPEED** — Task 2 of three. User: the first click on any symbol (search or link) takes ~10 s, later ones are fast because of the cache — bring it to ~3–4 s.
- **MARKET_OVERVIEW_WATCHLIST_LEDGER** — Task 1 of three: make users come back every day (market overview + watchlist) and make the portfolio worth using (a buy/sell ledger with realised P&L).
- **GOLD_PRICE_PAGE** — User asked for a gold-price feature (vnstock has it) and to put it in one menu with the exchange rate, as two submenus.
- **PORTFOLIO_REWORK** — User reported the portfolio (frontend + admin) had ugly buttons, buttons that "sometimes work", and little value to users (low usage in production). Audit found real bugs, not just looks.

**Sep 19**
- **ADMIN_SIDEBAR_AND_ACCOUNT_UI_FIXES** — 1. **Sidebar sub-menus** (Hệ thống: Quản lý Users / Vai trò / Quyền hạn / Giám sát Queue; also Tính năng) rendered side by side and wrapped: the sub-menu container was `<div class="nav collapse">` and Tabler's `.nav` is a horiz…
- **SEARCH_AUTOCOMPLETE_REDESIGN** — User found the symbol-search dropdown ugly: the highlighted row was a heavy colour and the hovered row looked identical to the keyboard-selected one, so two rows appeared "selected" at once.
- **COMPANY_PROFILE_FUND_CATALOG_LIGHTWEIGHT_CHARTS** — User asked for two features suggested from free vnstock APIs — a company profile page (#1) and an open-ended fund catalog (#3) — then asked to replace the "ugly" ApexCharts with TradingView Lightweight Charts, downloaded locall…
- **QUEUE_DELETE_ALL_FAILED_JOBS** — Admin > Giám sát Queue's "Job thất bại" card only had "Retry tất cả"; user asked for a matching "Xoá tất cả" button.

**Sep 17**
- **ADMIN_ACCOUNT_AND_NEWS_CATEGORIES** — survey of admin gaps; self-service password page `/admin/account` (no permission gate) and news-category CRUD that refuses to delete a category still used by RSS sources.

**Sep 16**
- **QUEUE_MONITOR_REALTIME_ACTIVITY** — `queue_job_logs`: which job runs now, elapsed time, recent jobs, processed-today counter.
- **PYTHON_EXEC_TIMEOUT_FIX** — every Python call goes through `App\Support\PythonRunner` (Unix `timeout`), because Laravel's pcntl-based job timeout cannot interrupt a blocked `exec()` (a "300 s" job had run 9+ minutes).
- **QUEUE_MONITOR_CUSTOM_PAGE** — Horizon removed as too hard to read for this app; a small in-app monitor (Controller/Service/Repository) replaces it; Redis queue kept.
- **SYNC_SPEED_OPTIMIZATION** — queue workers 3 → 6 for the I/O-bound sync jobs.

**Sep 15**
- **QUEUE_MONITORING_HORIZON** — Horizon added to watch a growing Redis backlog; fixed `retry_after` < `timeout` misconfiguration (later replaced, see above).
- **DOCS_CONSISTENCY_AUDIT** — every `.md` checked against the code; wrong command names, missing setup steps and stale RBAC text fixed.
- **SCALABLE_PERMISSIONS_SYSTEM** — DB-driven `permissions`/`permission_role` replace hard-coded Gates; Admin UI for roles and permissions.
- **FIX_TEST_SUITE_LAST_FAILURE** — the long-standing `ExampleTest` failure fixed (partition migration made MySQL-only); suite green.
- **PROFILE_EXTENDED_FIELDS** — birthday, gender, address, bio and avatar upload on the profile.
- **PROFILE_AND_PORTFOLIO_AUDIT** — crash for admin-created users without a profile row fixed; portfolio totals hardened against drift.
- **ADD_FORGOT_PASSWORD_FEATURE** — reset by e-mail on Laravel's `Password` broker with a generic response (no user enumeration).

**Sep 14**
- **AUTH_AUTHZ_AUDIT_AND_TESTS** — guests hitting `/admin/*` now go to the admin login; RBAC/Gate tests.
- **FIX_SCREENER_INLINE_CSS_AND_DATE_ICON_ROUND2** and **FIX_SCREENER_UIUX_AND_EXCHANGE_RATE_DATEPICKER** — screener redesign with its own CSS and proper Vietnamese; exchange-rate date picker.
- **FIX_EXCHANGE_RATE_PAGE_BROKEN** — Python output was concatenated before JSON decoding; now the last JSON line is used (the pattern every caller follows).
- **MANDATORY_TESTING_RULE_AND_FIRST_TEST_SUITE** — `#[Group('feature')]` rule, `docs/TESTING.md`, in-memory sqlite tests.
- **PORTFOLIO_REAL_PRICES_ALERTS_AND_SCREENER** — real prices instead of `rand()`, target/stop-loss e-mail alerts, stock screener.

### June 2026 — [archive](history/2026-06.md)

**Jun 27**
- **BACKEND_ADMIN_UPGRADE** — admin panel on real data, activity log, sync management.
- **SYNC_NEWS_SCHEDULER_MISSING** — `/news` showed only 4-week-old articles because `sync:news` was never run by the scheduler.
- **COMPANY_FINANCIALS_AND_BACKEND_USER_LAYER** — `company_financials` table/model and the backend user service layer.

### May 2026 and earlier — [archive](history/2026-05-and-earlier.md)

**May 30**
- **NEWS_RSS_SYSTEM** and **NEWS_CATEGORIES_FRONTEND** — RSS → `news` table (dedup by URL hash), categories FK, `/news` page.
- **STOCK_ADMIN_SERVICE_LAYER** — admin StockController moved onto the Controller→Service→Repository pattern.
- **AI_GROQ_MIGRATION_AND_SECURITY_HARDENING** — OpenRouter (404/429) replaced by Groq; timeout, XSS escaping, prompt-injection hardening.
- **DOCKER_MIGRATION** — Nginx, PHP-FPM, MySQL, Redis, queue and scheduler containers with HTTPS.
- **IDEMPOTENCY_SYNC_SKIP_EXISTING_DATA** — sync/backfill commands skip data already present (`--force` overrides).

**May 29**
- **COMPANY_FINANCIALS_DB_CACHE_AND_ARCHITECTURE** — financials cached in the DB instead of a live vnstock call per request.
- **TECHNICAL_INDICATORS_AND_COMPANY_FINANCIALS** — MA/Bollinger/RSI/MACD on the stock chart; financial statements.
- **HISTORICAL_BACKFILL_PARALLEL_QUEUE** — history back to 2018 with 6 parallel workers.
- **DATABASE_SCALABILITY_PARTITIONING** — `stock_prices` partitioned by year.
- **FIX_VNSTOCK_DEPRECATED_API_AND_SCHEDULER** — vnstock 4.x API change and Laravel 12 scheduler fixed after data froze at 2026-05-15.

**May 28**
- **AUTO_SYNC_BACKGROUND_SCHEDULER** — slow pages (hot industries, exchange rates) now filled by the scheduler instead of on request.
- **DOCUMENTATION_AUDIT_AND_EXPANSION** — STRUCTURE/GUIDELINES brought in line with the code.

**May 16** — **SEARCH_AND_DATA_INTEGRITY** — search auto-select bug, missing ETF data.

**May 1–2 and January 2025** — **CLEANUP_OLD_FILES**, **RBAC_SYSTEM_AND_ADMIN_DASHBOARD**, **RBAC_SYSTEM_MULTIPLE_ROLES_AND_SEPARATE_AUTH**, **SYSTEM_ERROR_FIXES_AND_EMAIL_VERIFICATION**, **AUTHENTICATION_SYSTEM**, **PORTFOLIO_MANAGEMENT_FEATURE** — legacy cleanup, roles and separate admin auth, e-mail verification, profile, first portfolio feature.
