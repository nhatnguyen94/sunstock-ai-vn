# AGENTS.md - Master Project Guide

> [!IMPORTANT]
> **MANDATORY**: Every AI agent MUST read this file IN FULL and ALL linked `docs/` files listed below before starting ANY task. Skipping any file is not permitted regardless of perceived task scope.

## 🚨 BEFORE YOU START — MANDATORY READ ORDER

> **You CANNOT write a single line of code until ALL steps below are complete.**
> **This is not optional. This is not conditional. Read everything.**

### Step 1 — Always Read (NO exceptions, every task)

| # | File | Why always required |
|---|---|---|
| 1 | **AGENTS.md** (this file, full) | Architecture rules + forbidden patterns |
| 2 | **[docs/STRUCTURE.md](docs/STRUCTURE.md)** | Directory map, namespaces, all components |
| 3 | **[docs/GUIDELINES.md](docs/GUIDELINES.md)** | Coding rules, surgical change policy, gotchas |
| 4 | **[docs/BINDINGS.md](docs/BINDINGS.md)** | All DI bindings — must check before creating any Service/Repo |
| 5 | **[docs/ROUTES_MAP.md](docs/ROUTES_MAP.md)** | All routes — must check before adding/modifying routes |

### Step 2 — Read Based on Task Type

| Task involves | Read these additional files |
|---|---|
| Python scripts / vnstock | **[docs/PYTHON_INTEGRATION.md](docs/PYTHON_INTEGRATION.md)** |
| Permissions / roles / Gates | **[docs/RBAC.md](docs/RBAC.md)** |
| Frontend JS/CSS/Blade assets | **[docs/FRONTEND_VIEWS.md](docs/FRONTEND_VIEWS.md)** |
| Docker / infrastructure / env | **[docs/DOCKER.md](docs/DOCKER.md)** |
| Database migrations | **[docs/HISTORY.md](docs/HISTORY.md)** (check existing migrations) |
| Writing/running tests (every task — see mandatory rule below) | **[docs/TESTING.md](docs/TESTING.md)** — folder structure, `#[Group(...)]` convention, why the DB can't be used in tests |
| Anything unclear | **[docs/QUICKSTART.md](docs/QUICKSTART.md)** |

### Step 3 — Confirmation Block (REQUIRED before writing any code)

After reading, you MUST output this block verbatim with your own answers filled in:

```
✅ DOCS READ CONFIRMATION
- AGENTS.md: read
- docs/STRUCTURE.md: read
- docs/GUIDELINES.md: read  
- docs/BINDINGS.md: read
- docs/ROUTES_MAP.md: read
- Additional docs read: [list any from Step 2, or "none"]

📌 TASK UNDERSTANDING
- Task: [one sentence summary]
- Namespace: App\[Frontend|Backend]\[layer]
- Affected files: [list]
- RBAC needed: [yes/no]
- Python involved: [yes/no]
- Migration needed: [yes/no]
```

**If you cannot fill in this block confidently → you have NOT read enough. Go back and read.**

---

## 📋 Quick Reference
- **Project Type**: Laravel 12 Stock Application
- **Architecture**: Strict Frontend/Backend Separation with ZERO crossover
- **Frontend Namespace**: `App\Frontend\{Controllers|Services|Repositories|Interfaces}`
- **Backend Namespace**: `App\Backend\{Controllers|Services|Repositories|Interfaces}`
- **Shared Models**: `App\Models` (and ONLY this folder)
- **Middleware**: `AdminAccess` (alias: `admin`) in `bootstrap/app.php`
- **RBAC**: Role constants in `App\Models\Role`, Gates in `AppServiceProvider::boot()`
- **CRITICAL RULE**: NEVER use default Laravel folders like `app/Http/Controllers`, `app/Services`, `app/Http/Requests`, etc. — ALL code must live in `App\Frontend\*` or `App\Backend\*`
- **Runtime**: Docker Compose (6 containers) — `https://sunstock-local.dev`. See `docs/DOCKER.md`
- **AI Provider**: Groq API (`GROQ_API_KEY` in `.env`) — model `llama-3.3-70b-versatile` primary
- **Python**: `/opt/venv/bin/python3` inside PHP container (env var `PYTHON_PATH`)

---

## ❌ FORBIDDEN PATTERNS - AI Will Make These Mistakes

> **If you do ANY of these, your code will be REJECTED immediately:**

| ❌ WRONG | ✅ RIGHT | Why |
|---------|----------|-----|
| `app/Http/Controllers/StockController.php` | `app/Frontend/Controllers/StockController.php` | Must use strict namespaces |
| `app/Services/StockService.php` | `app/Frontend/Services/StockService.php` | Services MUST be in Frontend or Backend |
| `app/Http/Requests/StockRequest.php` | Create FormRequest in `app/Frontend/Requests/` | Custom folders for requests |
| Create file in `app/` directly | Always use `App\Frontend\*` or `App\Backend\*` | Never use default Laravel structure |
| `if ($user->role === 'admin')` | `if ($user->hasRole(Role::ADMIN))` | Use Role constants, never hardcode strings |
| `Route::get('/admin/...')` without `admin` middleware | `Route::middleware(['admin'])->group(...)` | ALL backend routes need `admin` middleware |
| Insert data without Gate check | Always use `Gate::authorize()` first | RBAC must be enforced before action |
| Direct `shell_exec()` Python call | Use Service class with error handling | All Python calls go through Services |
| Update `docs/HISTORY.md` before coding | Update HISTORY.md as FINAL STEP | Documentation updated AFTER complete |
| Forget `composer dump-autoload` | Run after ALL new files created | Autoloading won't work without this |
| Use `dd()` in Services | Return only JSON or structured data | Python stdout must be clean JSON |
| Ship a new or changed feature without tests | Write/update tests tagged `#[Group('featureName')]`, then run `php artisan test --group=featureName` | Untested changes are not considered done — see "AFTER EVERY TASK" |
| Test method/class with no `#[Group(...)]` tag | Every test MUST have a feature group so it can be run in isolation | Lets you verify just your change without running the full (slow) suite |
| Group name in kebab-case, e.g. `#[Group('portfolio-alerts')]` | **camelCase only**, e.g. `#[Group('portfolioAlerts')]` | Mandatory naming convention — kebab-case groups will be rejected |
| Commit message without a `[branch-name]` prefix | `[master] <summary> (<detail>)` — e.g. `[master] Add stock screener` | Mandatory commit format — see "AFTER EVERY TASK" |

---

## 📚 Detailed Documentation

> **READ ORDER**: Start with Step 1 (always), then Step 2 (task-specific) per the checklist above.
> Each file below is required reading — not optional browsing.

### 🔴 Always Required (Step 1)

1. **[docs/STRUCTURE.md](docs/STRUCTURE.md)** — Directory map, namespaces, all components
2. **[docs/GUIDELINES.md](docs/GUIDELINES.md)** — Coding rules, surgical change policy, gotchas
3. **[docs/BINDINGS.md](docs/BINDINGS.md)** — Every Interface→Implementation binding registered in AppServiceProvider
4. **[docs/ROUTES_MAP.md](docs/ROUTES_MAP.md)** — Every route → controller → action mapping

### 🟡 Task-Conditional (Step 2)

5. **[docs/PYTHON_INTEGRATION.md](docs/PYTHON_INTEGRATION.md)** — `proc_open()` pattern, error handling, stdout rules, adding scripts
6. **[docs/RBAC.md](docs/RBAC.md)** — Role constants, Gate definitions, permission matrix, middleware
7. **[docs/FRONTEND_VIEWS.md](docs/FRONTEND_VIEWS.md)** — CSS/JS file locations, Blade → asset mapping, data-init pattern, CDN deps
8. **[docs/DOCKER.md](docs/DOCKER.md)** — 6-container stack, daily commands, troubleshooting, production notes
9. **[docs/QUICKSTART.md](docs/QUICKSTART.md)** — Setup, env config, migration, seed, artisan commands
10. **[docs/TESTING.md](docs/TESTING.md)** — Test folder structure (mirrors `app/`), the `#[Group(...)]` convention, why tests can't touch the DB here

### 🟢 Reference Only (update as FINAL step)

11. **[docs/HISTORY.md](docs/HISTORY.md)** — Feature log. **Read to understand what exists. UPDATE LAST.**

---

## ✅ AFTER EVERY TASK

> [!IMPORTANT]
> **MANDATORY — NO EXCEPTIONS**: Step 1 below applies to every feature you touch, whether it's brand new or already existed before your change. A task is NOT complete until it passes.

1. **Write or update unit/feature tests** covering the feature/fix you just built or changed. Every test (new or existing) MUST be tagged with a PHPUnit **Group** named after the feature it belongs to, using the `#[Group('featureName')]` attribute — **group names are camelCase, mandatory, no exceptions** (e.g. `#[Group('portfolioAlerts')]`, NOT `portfolio-alerts`). Use one consistent group name per feature across all its test methods/classes so they can be run together. Full details (folder structure, why tests can't touch the DB here, current group list) → **[docs/TESTING.md](docs/TESTING.md)**.
   Then **run only that feature's group** — not the full suite: `docker exec stock-app-php-1 php artisan test --group=featureName` (or `php artisan test --group=featureName` outside Docker). Every test in the group must pass — fix failures before moving on, don't skip or comment them out.
2. `composer dump-autoload`
3. Update **[docs/HISTORY.md](docs/HISTORY.md)** — LAST step, never first
4. Update **[docs/STRUCTURE.md](docs/STRUCTURE.md)** — if new files/components added
5. Update **[docs/ROUTES_MAP.md](docs/ROUTES_MAP.md)** — if new routes added
6. Update **[docs/BINDINGS.md](docs/BINDINGS.md)** — if new DI bindings added
7. **Commit message format — MANDATORY**: `[branch-name] <short summary> (<optional extra detail>)`. `branch-name` is the actual branch you're committing to — this repo currently only has `master`, so it's always `[master]` unless a feature branch exists. Example: `[master] Update feature (fix portfolio price sync)`.

> Full QA checklist (code quality, RBAC, migrations, testing) → **[docs/GUIDELINES.md](docs/GUIDELINES.md)**

---

## 🤖 VNStock Agent

External VNStock AI docs live in `docs/vnstock-agent/AGENTS.md`.
Rules there are **supplemental only** — project architecture always takes priority.

---

**Last Updated**: September 14, 2026  
**Revision**: 3.3 (mandatory tests per feature tagged `#[Group(...)]` camelCase, run in isolation — see docs/TESTING.md; mandatory `[branch-name]` commit message format)