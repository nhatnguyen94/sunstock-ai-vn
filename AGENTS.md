# AGENTS.md - Master Project Guide

> [!IMPORTANT]
> **MANDATORY**: Every AI agent MUST read this file AND all linked documents in the `docs/` folder before starting ANY task. This ensures consistency with the project's unique architecture and strict development rules.

## 🚨 BEFORE YOU START - MANDATORY PRE-CHECKLIST

> **You CANNOT proceed with any task until you complete this checklist:**

- [ ] **I have read AGENTS.md** (this file, lines 1-150+)
- [ ] **I have read [docs/GUIDELINES.md](docs/GUIDELINES.md)** - AI Development Rules & Checklists
- [ ] **I have read [docs/STRUCTURE.md](docs/STRUCTURE.md)** - Complete directory map and namespaces
- [ ] **I have read [docs/PYTHON_INTEGRATION.md](docs/PYTHON_INTEGRATION.md)** - If task involves Python
- [ ] **I have read [docs/RBAC.md](docs/RBAC.md)** - If task involves permissions/roles
- [ ] **I understand the Forbidden Patterns section below** - Common AI mistakes

**If you have NOT read these files, STOP immediately and read them first. Do not proceed with any coding.**

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

---

## 📚 Detailed Documentation
To keep this guide manageable, it has been split into the following sections:

1. **[Project Structure & Architecture](docs/STRUCTURE.md)** ⭐ READ FIRST
   - Detailed directory map, namespaces, all components (controllers, services, models, views, commands, jobs, middleware, python scripts).

2. **[AI Development & Behavioral Guidelines](docs/GUIDELINES.md)** ⭐ READ SECOND
   - Mandatory rules for coding, checklists, RBAC patterns, known gotchas, surgical change policies.

3. **[Python Integration Guide](docs/PYTHON_INTEGRATION.md)** ⭐ MANDATORY IF TOUCHING PYTHON
   - How to call Python scripts from Services, error handling, adding new scripts, stdout/stderr rules.

4. **[RBAC & Permissions](docs/RBAC.md)** ⭐ MANDATORY IF TOUCHING PERMISSIONS
   - Role constants, Gate definitions, middleware, permission matrix, authorization patterns.

5. **[DI Bindings & Gates](docs/BINDINGS.md)**
   - All Interface→Implementation bindings and Gate definitions in AppServiceProvider.

6. **[Routes Map](docs/ROUTES_MAP.md)**
   - Complete route → controller → action mapping table for fast lookup.

7. **[Quick Start Guide](docs/QUICKSTART.md)**
   - Setup, environment, migration, seed, artisan commands for new developers and AI agents.

8. **[Feature Update History](docs/HISTORY.md)**
   - Log of all major features implemented and fixes made. UPDATE THIS AS FINAL STEP.

9. **[Frontend Views & Asset Structure](docs/FRONTEND_VIEWS.md)**
   - CSS/JS file locations, Blade → asset mapping, data-init pattern, CDN dependencies, build commands.

---

## 🗂️ DATABASE MIGRATION RULES

**If your task involves database changes, you MUST follow these rules:**

1. **Create migration file**: `php artisan make:migration create_table_name_table`
2. **Location**: `database/migrations/` (Laravel default)
3. **Naming**: `YYYY_MM_DD_HHMMSS_create_table_name_table.php`
4. **Write schema in `up()` method only** — never in `down()`
5. **Always provide `down()` rollback logic** — must undo `up()` completely
6. **Add foreign keys AFTER referenced tables exist** — use `->constrained()` shorthand
7. **Soft deletes**: Add `$table->softDeletes()` if model needs this
8. **Timestamps**: Always add `$table->timestamps()` unless explicitly excluded
9. **Run locally first**: `php artisan migrate:fresh --seed` to test
10. **Test rollback**: `php artisan migrate:rollback` to verify `down()` works
11. **Never modify existing migrations** — create new ones for changes
12. **Update `database/factories/` if adding new fields to existing tables**

**Example**:
```php
Schema::create('stock_prices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('stock_id')->constrained('stocks')->onDelete('cascade');
    $table->decimal('price', 10, 2);
    $table->timestamps();
});
```

---

## 📋 PRE-TASK CHECKLIST

Before you write ANY code, complete this checklist:

- [ ] **Task is clear**: I understand exactly what needs to be built
- [ ] **Scope is defined**: I know which Frontend/Backend layer this belongs to
- [ ] **Models exist**: I've checked `app/Models/` for existing related models
- [ ] **Routes checked**: I've looked at `docs/ROUTES_MAP.md` to see related routes
- [ ] **Bindings checked**: I've reviewed `docs/BINDINGS.md` for existing DI patterns
- [ ] **RBAC checked**: If this task involves permissions, I've read `docs/RBAC.md`
- [ ] **No namespace conflicts**: I've verified the namespace won't clash with existing code
- [ ] **Python rules checked**: If using Python, I've read `docs/PYTHON_INTEGRATION.md`
- [ ] **Documentation path**: I know which section of `docs/` needs updating

**If ANY of these is unchecked, STOP and investigate first.**

---

## 🚀 PYTHON INTEGRATION - MANDATORY FOR PYTHON TASKS

**⚠️ STOP: If your task involves Python scripts, you MUST read [docs/PYTHON_INTEGRATION.md](docs/PYTHON_INTEGRATION.md) FIRST.**

### Quick Rules (NOT a substitute for reading the full guide):
1. **All Python calls** must go through Service classes (never direct `shell_exec()` in controller)
2. **Python must return JSON only** — no debug prints, no intermediate output
3. **Service MUST handle errors**: empty output, non-zero exit codes, JSON decode failures
4. **Use `proc_open()` for better error handling** than `shell_exec()`
5. **Python scripts live in `py/` directory** and are versioned in Git
6. **Environment variable**: `PYTHONIOENCODING=utf-8` when calling Python
7. **Available modules**: `vnstock`, `vnstock_chart`, `pandas`, etc. (installed system-wide)
8. **If adding new Python script**: Create in `py/`, update `docs/PYTHON_INTEGRATION.md`, add Service class

---

## 🧪 QUALITY ASSURANCE CHECKLIST - Before Submitting Work

> **Your code is NOT done until ALL these items pass:**

### Code Quality
- [ ] All new classes use correct namespace (`App\Frontend\*` or `App\Backend\*`)
- [ ] All controllers extend proper base `Controller` class
- [ ] All database access goes through Repository + Interface
- [ ] All DI bindings registered in `AppServiceProvider::register()`
- [ ] No hardcoded values — use configuration or constants
- [ ] No SQL directly in controller — must go through Repository

### RBAC & Security
- [ ] Backend actions have `Gate::authorize()` check
- [ ] Frontend actions check `$this->authorize()` if needed
- [ ] Roles use `Role::CONSTANT` not hardcoded strings
- [ ] Email verification checked with `verified` middleware where needed

### Routing & Middleware
- [ ] All routes in correct group (`admin`, `auth`, `verified`)
- [ ] Backend routes have `admin` middleware
- [ ] Frontend routes have `auth` middleware if needed
- [ ] Middleware applied at route level, not controller

### Database & Migrations
- [ ] Migration created (if schema change)
- [ ] Migration tested: `php artisan migrate:fresh --seed`
- [ ] Rollback tested: `php artisan migrate:rollback`
- [ ] No uncommitted database changes

### Python (if applicable)
- [ ] Python script returns valid JSON only
- [ ] Service handles all error cases
- [ ] Service uses `proc_open()` with error handling
- [ ] Tested with actual vnstock API

### Testing
- [ ] Routes tested: `php artisan route:list` shows new routes
- [ ] No PHP errors: `php artisan tinker` loads all classes
- [ ] Database integrity: No orphaned records after migration
- [ ] If models changed: Verify relationships work

### Documentation (FINAL STEP - MUST DO LAST)
- [ ] `docs/HISTORY.md` updated with new feature
- [ ] `docs/STRUCTURE.md` updated if adding new components
- [ ] `docs/ROUTES_MAP.md` updated if adding new routes
- [ ] `docs/BINDINGS.md` updated if adding new DI bindings
- [ ] `docs/PYTHON_INTEGRATION.md` updated if adding new Python scripts
- [ ] Comments added to complex logic
- [ ] README.md updated if setup changes

### Final Steps
- [ ] `composer dump-autoload` executed
- [ ] No uncommitted changes in `composer.lock`
- [ ] Code follows existing patterns in codebase
- [ ] No deprecated Laravel methods used (Laravel 12 required)

---

## 🤖 VNStock AI Agent References

This project also integrates external VNStock AI agent documentation.

### Reference Location
- `docs/vnstock-agent/`
- `docs/vnstock-agent/AGENTS.md`

### VNStock Modules Available
- `vnstock` — Core stock data library
- `vnstock_chart` — Chart generation
- `vnstock_ezchart` — Easy chart wrapper
- `vnstock_news` — News scraping
- `vnstock_pipeline` — Data pipeline
- `vnstock_ta` — Technical analysis
- `vnstock-data` — Historical data

### Usage Rules
- VNStock guides are **supplemental references only**
- **Project-specific architecture rules ALWAYS take priority** over VNStock examples
- AI agents MUST NOT override existing Laravel structure based on VNStock docs
- Keep Python-related stock processing isolated under `py/` directory
- All vnstock calls go through Services (see `docs/PYTHON_INTEGRATION.md`)

### Recommended Flow
```text
Frontend/Backend Controller
  ↓
Service Layer (handles errors)
  ↓
Python Executor (proc_open with stdio handling)
  ↓
vnstock module
  ↓
Return JSON to Service
  ↓
Format response in Controller
```

---

## 📞 Questions & Support

- **Structure Questions?** → Read `docs/STRUCTURE.md`
- **RBAC Issues?** → Read `docs/RBAC.md`
- **Python Not Working?** → Read `docs/PYTHON_INTEGRATION.md`
- **Don't know where to put a file?** → Check `docs/STRUCTURE.md` directory map
- **Unsure about DI bindings?** → Review `docs/BINDINGS.md`

---

**Last Updated**: May 29, 2026  
**Revision**: 2.0 (Strict AI Enforcement)