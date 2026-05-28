# AGENTS.md - Master Project Guide

> [!IMPORTANT]
> **MANDATORY**: Every AI agent MUST read this file AND all linked documents in the `docs/` folder before starting any task. This ensures consistency with the project's unique architecture and strict development rules.

## 📋 Quick Reference
- **Project Type**: Laravel 12 Stock Application
- **Architecture**: Strict Frontend/Backend Separation
- **Frontend Namespace**: `App\Frontend\{Controllers|Services|Repositories|Interfaces}`
- **Backend Namespace**: `App\Backend\{Controllers|Services|Repositories|Interfaces}`
- **Shared Models**: `App\Models`
- **Middleware**: `AdminAccess` (alias: `admin`) in `bootstrap/app.php`
- **RBAC**: Role constants in `App\Models\Role`, Gates in `AppServiceProvider::boot()`
- **Critical Rule**: NEVER use default Laravel folders like `app/Http/Controllers`, `app/Services`, etc.

## 📚 Detailed Documentation
To keep this guide manageable, it has been split into the following sections:

1. **[Project Structure & Architecture](docs/STRUCTURE.md)**
   - Detailed directory map, namespaces, and all components (controllers, services, models, views, commands, jobs, middleware, python scripts).

2. **[AI Development & Behavioral Guidelines](docs/GUIDELINES.md)**
   - Mandatory rules for coding, checklists, RBAC patterns, known gotchas, and surgical change policies.

3. **[Feature Update History](docs/HISTORY.md)**
   - Log of all major features implemented and fixes made.

4. **[Quick Start Guide](docs/QUICKSTART.md)**
   - Setup, environment, migration, seed, artisan commands for new developers and AI agents.

5. **[Routes Map](docs/ROUTES_MAP.md)**
   - Complete route → controller → action mapping table for fast lookup.

6. **[DI Bindings & Gates](docs/BINDINGS.md)**
   - All Interface→Implementation bindings and Gate definitions in AppServiceProvider.

7. **[Python Integration Guide](docs/PYTHON_INTEGRATION.md)**
   - How to call Python scripts from Laravel Services, error handling, adding new scripts.

8. **[RBAC & Permissions](docs/RBAC.md)**
   - Role constants, Gate definitions, middleware, permission matrix.

9. **[Frontend Views & Asset Structure](docs/FRONTEND_VIEWS.md)**
   - CSS/JS file locations, Blade → asset mapping, data-init pattern, CDN dependencies, build commands.

---

## 🤖 VNStock AI Agent References

This project also integrates external VNStock AI agent documentation.

### Reference Location
- `docs/vnstock-agent/`
- `docs/vnstock-agent/AGENTS.md`

### VNStock Modules Available
- `vnstock`
- `vnstock_chart`
- `vnstock_ezchart`
- `vnstock_news`
- `vnstock_pipeline`
- `vnstock_ta`
- `vnstock-data`

### Usage Rules
- VNStock guides are considered **supplemental references only**
- Project-specific architecture rules in this repository ALWAYS take priority
- AI agents MUST NOT override existing Laravel structure based on VNStock examples
- Keep Python-related stock processing isolated under the `py/` directory when possible

### Recommended Flow
```text
Controller
→ Service
→ Python Executor
→ vnstock