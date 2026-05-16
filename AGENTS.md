# AGENTS.md - Master Project Guide

> [!IMPORTANT]
> **MANDATORY**: Every AI agent MUST read this file AND all linked documents in the `docs/` folder before starting any task. This ensures consistency with the project's unique architecture and strict development rules.

## 📋 Quick Reference
- **Project Type**: Laravel Stock Application 
- **Architecture**: Strict Frontend/Backend Separation
- **Frontend Namespace**: `App\Frontend\{Controllers|Services|Repositories|Interfaces}`
- **Backend Namespace**: `App\Backend\{Controllers|Services|Repositories|Interfaces}`
- **Shared Models**: `App\Models`
- **Critical Rule**: NEVER use default Laravel folders like `app/Http/Controllers`, `app/Services`, etc.

## 📚 Detailed Documentation
To keep this guide manageable, it has been split into the following sections:

1. **[Project Structure & Architecture](file:///c:/xampp/htdocs/stock-app/docs/STRUCTURE.md)**
   - Detailed directory map, namespaces, and core components.
2. **[AI Development & Behavioral Guidelines](file:///c:/xampp/htdocs/stock-app/docs/GUIDELINES.md)**
   - Mandatory rules for coding, checklists, and surgical change policies.
3. **[Feature Update History](file:///c:/xampp/htdocs/stock-app/docs/HISTORY.md)**
   - Log of all major features implemented and fixes made.

## ⚡ Quick Start & Final Checklist
- [ ] **Read** `docs/STRUCTURE.md` and `docs/GUIDELINES.md` before starting.
- [ ] **Use** `App\Frontend\*` or `App\Backend\*` namespaces ONLY.
- [ ] **Register** new services/repositories in `AppServiceProvider.php`.
- [ ] **MANDATORY FINAL STEP**: Before finishing, you MUST:
    - [x] Update `docs/HISTORY.md` with a detailed log of your changes.
    - [x] Update `docs/STRUCTURE.md` if you added/removed files or changed architecture.
    - [x] Update `docs/GUIDELINES.md` if you established new patterns or rules.

---
**This file is the entry point for all AI interactions. Do not bypass the rules defined in the linked documents.**