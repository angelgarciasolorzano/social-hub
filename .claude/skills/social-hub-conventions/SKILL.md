---
name: social-hub-conventions
description: "Activate when creating, editing, or reviewing files under app/, resources/js/, database/, tests/, or when running backend/frontend QA commands, and when closing or moving a Linear task to Done. Enforces the project conventions from CLAUDE.md and AGENTS.md: modular domain structure, naming, strict typing, Pint + PHPStan + Rector + PHPUnit gates, Prettier + ESLint + tsc gates, and the Definition of Done (update CLAUDE.md/AGENTS.md/docs, open PR with template, leave summary comment on the Linear issue). Use this skill for any non-trivial code change in social-hub, not just when the user asks for it."
license: MIT
metadata:
  author: social-hub
---

# Social Hub Conventions

This skill is the **actionable index** for the conventions that already live in `CLAUDE.md`, `AGENTS.md`, and `docs/`. Those files are the source of truth; this skill only reminds you of what to do and where to look. Do not duplicate content here.

When you read this skill, the project conventions are already in effect. Treat them as hard rules, not suggestions.

## 1. Structure & naming

### Backend

- Modular layout under `app/<Domain>/` (e.g. `app/Auth/`, `app/Post/`, `app/Comment/`). Each domain owns its controllers, models, requests, resources, routes, providers, factories, seeders, enums, listeners.
- Each domain registers two providers in `bootstrap/providers.php`:
  - `<Domain>ServiceProvider` — registers the route + event providers.
  - `<Domain>RouteServiceProvider` — extends `Illuminate\Foundation\Support\Providers\RouteServiceProvider`, loads routes from `app/<Domain>/routes/routes.php` under the `web` middleware group.
  - Auth splits routes per concern (`app/Auth/routes/login.php`, `register.php`, etc.) aggregated by `AuthRouteServiceProvider`.
- File naming inside a domain:
  - Controllers → `XxxController.php`
  - Form Requests → `XxxRequest.php` (with `HasFluentRules` + `FluentRule::*`)
  - Models in singular under `Models/` (e.g. `app/Post/Models/Post.php`)
  - Enums under `Enums/`
  - Listeners under any of: `<Domain>/Modules/<Feature>/Listeners/`, `<Domain>/<Feature>/Listeners/`, or `<Domain>/Listeners/`. Confirm via `grep -r "extends Listener" app/` when uncertain.
  - Migrations and factories under `Database/Migrations/` and `Database/Factories/`
- PSR-4 namespace must match the path. Every PHP file starts with `declare(strict_types=1);`.
- Typed class constants: `private const string NAME = '...';` (see `FortifyServiceProvider`).
- Create files with the appropriate `php artisan make:*` command (`--no-interaction`). Don't hand-author migrations, models, requests, controllers, or tests when a generator exists.
- See [`CLAUDE.md`](../../CLAUDE.md) "Modular service-provider pattern" and "Models and relations" sections for the canonical references.

### Frontend

- `resources/js/modules/<domain>/{views,components,types,hooks}` mirrors the PHP domains. Example: `app/Post/` ↔ `resources/js/modules/post/`.
- Shared code under `resources/js/shared/`:
  - `components/shadcn/ui/` — shadcn primitives (style `new-york`, base `neutral`, see `components.json`)
  - `components/`, `hooks/`, `lib/`, `types/`, `assets/`
  - `wayfinder/` — **auto-generated** by the Vite plugin (`@laravel/vite-plugin-wayfinder`). Never edit by hand.
- Naming:
  - Components & pages `.tsx` → `PascalCase.tsx`
  - Types, hooks, utils `.ts` → `camelCase.ts`
  - shadcn primitives under `shared/components/shadcn/ui/` → `kebab-case.tsx`
- Aliases: `@/` → `resources/js/` (configured in `tsconfig.json`).
- Routes: import from `@/actions/` (controllers) and `@/routes/` (named routes). Never hardcode URLs.
- Import order is enforced by `.prettierrc` via `@trivago/prettier-plugin-sort-imports` — `react`, `@inertiajs/react`, `react-icons` first, then `@/modules`, then `@/shared/...`, then relative. Don't reorder manually.

## 2. Backend QA gates (run in this order)

Always prefix shell commands with `rtk` per [`.github/copilot-instructions.md`](../../.github/copilot-instructions.md) (the hook lives in `.github/hooks/rtk-rewrite.json`).

```bash
# 1. Format changed files
vendor/bin/pint --dirty --format agent

# 2. Static analysis (level max + type_coverage 100)
composer phpstan

# 3. Refactor preview (skip if empty)
composer rector-dry

# 4. Run affected tests
php artisan test --compact --filter=testName
# Full suite before closing the PR:
php artisan test --compact
```

Details and rationale for each command: [`docs/development/backend-commands.md`](../../docs/development/backend-commands.md).

Hard rules:

- Tests are PHPUnit only — never Pest. Create with `php artisan make:test --phpunit {Name}` (add `--unit` for unit tests).
- Validation uses `HasFluentRules` + `FluentRule::*` chains. Never string rules, never `Rule::*` when a `FluentRule::` equivalent exists. The `fluent-validation` / `fluent-validation-optimize` skills have the method reference.
- After every meaningful backend change, regenerate Wayfinder (it re-reads routes during `npm run dev` / `npm run build`) and review the diff under `resources/js/shared/wayfinder/`.

## 3. Frontend QA gates (run in this order)

```bash
# 1. Format check (auto-fix with `npm run format`)
npm run format:check

# 2. Lint check (auto-fix with `npm run lint`)
npm run lint:check

# 3. Type check
npm run types
```

Details: [`docs/development/frontend-commands.md`](../../docs/development/frontend-commands.md).

Hard rules:

- TypeScript errors don't auto-fix — fix them by hand before declaring the change done.
- Both `npm run build` and `npm run build:ssr` must compile before opening the PR.
- Inertia v3 + React 19 + Tailwind v4: prefer server-side data via `Inertia::render()` and typed prop shape definitions in PHPDoc.
- shadcn primitives come from `npx shadcn@latest add <name>`, never copy-pasted from docs.

## 4. Definition of Done — closing a Linear task

Before moving an issue to `Done`, every one of these must be true:

1. **Gates green**: `vendor/bin/pint --dirty --format agent`, `composer phpstan`, `composer rector-dry`, `php artisan test --compact`, `npm run format:check`, `npm run lint:check`, `npm run types`, `npm run build`, `npm run build:ssr`. CI (SOC-7 backend, SOC-11 frontend) must be green too.
2. **Doc sync** — update the docs that the change actually touched:
   - [`CLAUDE.md`](../../CLAUDE.md) when the change touches: domain structure, the morph map, package versions, the Wayfinder layout, or the project's tooling/conventions.
   - [`AGENTS.md`](../../AGENTS.md) when the change touches: package-specific rules (Inertia, Fortify, Wayfinder, MediaLibrary, FluentValidation, Pint, PHPUnit) or Laravel/React conventions.
   - [`docs/architecture/backend.md`](../../docs/architecture/backend.md) / [`docs/architecture/frontend.md`](../../docs/architecture/frontend.md) for architectural changes.
   - [`docs/development/backend-commands.md`](../../docs/development/backend-commands.md) / [`docs/development/frontend-commands.md`](../../docs/development/frontend-commands.md) when commands, scripts, or gates change.
3. **PR template**: open the PR using [`.github/PULL_REQUEST_TEMPLATE/`](../../.github/PULL_REQUEST_TEMPLATE/). Do not skip the linked-issue line, the change summary, or the test plan.
4. **Linear summary**: post a closing comment on the issue mirroring the SOC-13 style — context, decisions (with a small table when applicable), implementation summary, criteria cumplidos (checkbox list), link to the PR. The comment is what reviewers read first; treat it as the deliverable's front page.

If any item above is missing, the change is **not done**. Don't move the issue to `Done` — leave it in `In Progress` and finish the missing step.

## Out of scope for this skill

- Migrating `.agents/skills/` or editing `skills-lock.json`.
- Adding new hooks in `.github/hooks/` (a separate issue if needed).
- Rewriting `CLAUDE.md` or `AGENTS.md` beyond the minimal registration line that links to this skill.
