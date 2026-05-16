# AI Development & Behavioral Guidelines

## Development Rules

1. **Frontend**: For regular users to view stocks, exchange rates, AI chat
2. **Backend**: For admin system management
3. **Models**: Shared between Frontend/Backend, no separation
4. **Dependency Injection**: Use Interface and Repository pattern
5. **Service Binding**: Define bindings in `AppServiceProvider.php`

## Important Notes
- Frontend and Backend are completely separated in namespace and directories
- Models are shared between Frontend/Backend
- Use Repository pattern and Dependency Injection
- Backend requires authentication and permission middleware

## When Adding New Features

### Frontend (Users)
1. Create Controller in `app/Frontend/Controllers/`
2. Create Service in `app/Frontend/Services/` (if needed)
3. Create Repository and Interface (if needed)
4. Register binding in `AppServiceProvider`
5. Add routes to `web.php` with Frontend namespace

### Backend (Admin)
1. Create Controller in `app/Backend/Controllers/`
2. Create Service in `app/Backend/Services/` (if needed)
3. Create Repository and Interface (if needed)
4. Add admin routes with auth and permission middleware
5. Create views in `resources/views/backend/`

## ⚡ Quick Checklist for AI
When adding ANY new feature:
- [ ] Use correct `App\Frontend\*` or `App\Backend\*` namespace
- [ ] Create Interface for Repository/Service
- [ ] Register binding in `AppServiceProvider`
- [ ] Run `composer dump-autoload`
- [ ] Test with `php artisan route:list`
- [ ] Update documentation immediately

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
