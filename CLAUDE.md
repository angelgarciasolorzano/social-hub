# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. It is a short map plus the invariants that break things; the detail of each module lives in [`docs/architecture/`](docs/architecture/).

> ⚠️ **Mantenimiento de la doc**: cuando cambies la estructura de módulos del backend, agregues un nuevo dominio, modifiques la morph map, cambies versiones de paquetes relevantes o actualices convenciones de Laravel/PHP/Inertia/React, actualizá este documento en el mismo PR/commit. El detalle de cada módulo va en su documento de `docs/architecture/` (actualizalo en el mismo PR); aquí solo queda el mapa, los comandos, las convenciones y las invariantes. La doc es la fuente de verdad para entender el proyecto y no debe quedar desincronizada con el código.

## Project overview

**social-hub** is a Laravel 13 + React 19 SPA (Inertia v3) social-media application, with a Filament 5 admin panel and Spatie Permission. PHP 8.5, Pest, Tailwind v4, shadcn/ui, Laravel Reverb.

- **Modules are moving to `app/Modules/<Domain>/` (SOC-38).** The migration is incremental: migrated modules live under `app/Modules/`, the others still live at `app/<Domain>/` (the *old architecture*) until their own task moves them. New domains always go under `app/Modules/`.
- The old architecture also has **feature submodules** at `app/<Domain>/Modules/<Feature>/` (SOC-14, e.g. `app/Auth/Modules/TrustedDevice/`). Don't confuse them with the top-level `app/Modules/`: a submodule owns its controllers, requests, services, listeners and tests, but providers, models, routes, migrations and factories stay shared at the parent.
- **Global code is not a module (SOC-42):** `app/Providers/`, `app/Http/`, `app/Events/`, `app/Console/Commands/` (auto-discovered), `app/Support/` (shared classes of no domain), global listeners, policies and middleware. Any other new base folder needs approval.

### Module map

| Module | Path | What it owns | Details |
| --- | --- | --- | --- |
| Auth | `app/Auth/` (old) | Login, register, email verification, password reset (Fortify), the 2FA login challenge and trusted devices | [auth.md](docs/architecture/modules/auth.md) |
| User | `app/User/` (old) | The `User` model, account settings, account status (suspension), 2FA settings, account events | [user.md](docs/architecture/modules/user.md) |
| Home | `app/Home/` (old) | Landing and dashboard pages | — |
| Friendship | `app/Friendship/` (old) | Friend requests and relationships | — |
| Post | `app/Modules/Post/` | Posts with an image (MediaLibrary), engagement counters | [content.md](docs/architecture/modules/content.md) |
| Comment | `app/Modules/Comment/` | Polymorphic, single-level comment threads with mentions; edit and delete with tombstones | [content.md](docs/architecture/modules/content.md) |
| Like | `app/Modules/Like/` | Polymorphic likes on posts and comments (JSON toggle endpoint) | [content.md](docs/architecture/modules/content.md) |
| Announcement | `app/Modules/Announcement/` | System announcements from the panel, approval workflow, toast/dialog display | [announcement.md](docs/architecture/modules/announcement.md) |
| Notification | `app/Modules/Notification/` | Real-time React notification center (Reverb, private channel, history page) | [notification.md](docs/architecture/modules/notification.md) |
| ActivityLog | `app/Modules/ActivityLog/` | Activity log of user actions and the administrative audit | [activity-log.md](docs/architecture/modules/activity-log.md) |
| Admin | `app/Modules/Admin/` | System module: Filament panel, roles and permissions, backups, Admin commands | [admin.md](docs/architecture/modules/admin.md) |

Cross-cutting documents: [languages (panel and application)](docs/architecture/i18n.md) and [global infrastructure, morph map, media, seeders, test infrastructure, frontend layout and tooling](docs/architecture/infrastructure.md).

## Where the rules live

- **[`AGENTS.md`](AGENTS.md)** — package-specific guidance (Inertia v3, Fortify, Wayfinder, MediaLibrary, FluentValidation, Pint, Pest, Rector) and the Laravel Boost conventions. Read it before non-trivial changes.
- **[`.ai/rules/index.md`](.ai/rules/index.md)** — maps file globs to rule files (`app.md`, `backend.md`, `frontend.md`, `linear.md`). Read every matching rule before editing; `linear.md` requires a plan (`plan-to-task-list-with-dag`) before implementing.
- **[`.claude/skills/social-hub-conventions/SKILL.md`](.claude/skills/social-hub-conventions/SKILL.md)** — activates on any non-trivial code change and on Linear task closure; enforces structure, naming, QA gates and the Definition of Done.
- **[`docs/architecture/backend.md`](docs/architecture/backend.md)** and **[`frontend.md`](docs/architecture/frontend.md)** — how to build a module (in Spanish). **[`docs/development/`](docs/development/)** — every command and gate in detail. **[`README.md`](README.md)** — first-time setup and environment variables.
- `.github/copilot-instructions.md`: **prefix shell commands with `rtk`** (e.g. `rtk git status`).
- **Skill placement (symlink-only):** `.claude/skills/<skill-name>/SKILL.md` is the canonical source of every project-local skill. Other IDE folders (`.windsurf/skills/`, `.agents/skills/`, `.codex/skills/`, `.ai/skills/`…) reference it with a symlink (`ln -s ../../.claude/skills/<skill-name> <ide>/skills/<skill-name>`), never a copy; replace a physical duplicate on sight. Check with `ls -la <ide>/skills/<skill-name>` (an IDE that shows the target as a file is a display artifact).

## Common commands

All commands run from the repo root; details in [`docs/development/backend-commands.md`](docs/development/backend-commands.md) and [`frontend-commands.md`](docs/development/frontend-commands.md).

- `composer run dev` — `serve`, `queue:listen`, `pail`, `vite` and `reverb:start` together (preferred). `npm run dev` runs only Vite.
- `npm run build` / `npm run build:ssr` — production / SSR bundle (both must compile before a PR).
- `composer test` — the whole Pest suite **in parallel** (one MySQL database per worker, `--recreate-databases` after changing migrations).
- `php artisan test --compact <path>` or `--filter=testName` — narrow runs (after every edit).
- `composer ide-helper` — regenerates `_ide_helper_models.php` (PHPStan scans it).
- `php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder` — regenerate the gitignored Wayfinder helpers after route or controller changes.
- `php artisan route:list --name=…` — inspect routes.

## Quality gates

Backend, after touching PHP (in this order):

1. `vendor/bin/pint --dirty --format agent`
2. `composer phpstan` — `level: max`, 100% type coverage, `type_perfect`; `mixed` is disallowed.
3. `composer rector-clear-cache` → `composer rector-dry` → `composer rector` — mandatory for AI agents on every PHP change, no approval needed. Always over the whole project (no paths): rules such as `RenameVariableToMatchMethodCallReturnTypeRector` need types from other files, and the local cache can hide what CI (`backend-rector`) flags (SOC-41).
4. Affected tests, then the full suite before closing the task. `composer doctor` is the last local backend gate.

Frontend: `npm run format:check`, `npm run lint:check`, `npm run types` (fix with `npm run format` / `npm run lint`; type errors by hand).

## Conventions

- **Module wiring:** each domain registers `<Domain>ServiceProvider` (thin, registers the route provider and events) in `bootstrap/providers.php`, and `<Domain>RouteServiceProvider` loads `routes/routes.php` under the `web` group (Auth splits routes per concern in `app/Auth/routes/`). A module with migrations calls `loadMigrationsFrom`. Admin is the exception: `AdminPanelProvider` and Filament own its routes.
- **PHP:** `declare(strict_types=1);` everywhere; typed class constants (`private const string NAME = '...';`); FormRequests use `HasFluentRules` + `FluentRule::*`; `spatie/laravel-data` for typed non-model structures, `JsonResource` for model serialization; policies attached with `#[UsePolicy]` and applied with `#[Authorize('<ability>', '<routeParameter>')]`; several actions on one resource share one controller, single actions stay invokable.
- **Naming:** no single-letter variables in PHP or TypeScript, closures included (only a numeric `for` counter). See `.ai/rules/app.md`.
- **Frontend:** `resources/js/modules/<domain>/` mirrors the PHP domains; shared code in `resources/js/shared/` (shadcn primitives in `shared/components/shadcn/ui/`); `@/` is `resources/js/`; routes come from `@/shared/wayfinder/` (generated, never edited, never hardcoded URLs). Import order is enforced by Prettier.
- **Tests:** module tests live in the owning module's `Tests/` (`app/Modules/<Domain>/Tests/`, or `app/<Domain>/…/Tests/` in the old architecture), grouped by responsibility (`Crud/`, `Models/`, `Requests/`, `Resources/`, `Security/`, `Seeders/`…) and registered in `phpunit.xml` + `tests/Pest.php`; `tests/` is for cross-module and infrastructure tests. `make:test` writes under `tests/`, so move the file.

## Invariants and traps

Each item is explained in the linked module document.

### Security

- **Trusted devices skip the OTP only** with a valid, non-revoked, non-expired cookie of an account with **confirmed** 2FA (`two_factor_confirmed_at` set). Confirming or disabling 2FA revokes every device. `TwoFactorCodeVerifier` is the only code verifier; recovery codes are consumed inside the caller's `DB::transaction` with `lockForUpdate`. The four routes that check a 2FA code share the `throttle:two-factor-code` budget. ([auth](docs/architecture/modules/auth.md))
- **A suspended account cannot sign in by any path.** `users.status` is the only source of truth, is not mass assignable, and changes only through `AccountStatusService`. `EnsureAccountIsActive` must stay ahead of authentication in the middleware priority (`bootstrap/app.php`), or Filament answers 403 before the session ends. ([user](docs/architecture/modules/user.md))
- **Write throttles take a prefix** (`throttle:6,1,comments.store`): unnamed throttles are keyed by user only, so routes would share one budget.
- **`#[Authorize]` runs as middleware after route-model binding**, so a stranger gets 403 before FormRequest validation.
- **Never trust the client for derived users:** the user mentioned by a reply (`replied_to_user_id`) and notification recipients are resolved on the server.
- **Announcement bodies are rendered only through `Announcement::safeBodyHtml()`**, never the raw column. ([announcement](docs/architecture/modules/announcement.md))
- **`ActivityLogger::record()` is the only caller of `activity()`** (an `arch()` rule keeps `Spatie\Activitylog` inside the module) and rejects forbidden property keys: never content, passwords, codes, secrets, tokens or emails. ([activity log](docs/architecture/modules/activity-log.md))
- `app:clean-media-library-folders` and destructive commands are prohibited in production (`Prohibitable`, wired in `AppServiceProvider::boot()`).

### Filament, Livewire and authorization

- **Authorization lives in the model policy:** resources do not override `can*()`. Filament allows any ability the policy does not define, so a read-only policy must deny every write ability explicitly. Relation managers and widgets ask the same policy through `Gate`. ([admin](docs/architecture/modules/admin.md))
- **Two meanings of `admin`:** the `admin` session guard authenticates the panel; Spatie's `guard_name` `admin` scopes roles and permissions. Inside the panel read the user with `Filament::auth()`, never `auth()` (the `web` guard).
- **Use `App\Modules\Admin\Models\Role`**, never Spatie's `Role`. `AdminAuthorizationService` is the only writer of roles and permissions outside seeders; it never leaves nobody with `admin.access`, never lets the acting administrator lose access, and a role can never hold a permission without its requirements (`AdminPermission::requires()`). The `admin` role is locked.
- **Every Admin command requires `--actor=<email>`**, recorded as the causer in the activity log.
- **Livewire updates need the language middleware as persistent middleware** (`Livewire::addPersistentMiddleware()`), or every filter, sort or modal answers in `app.locale`.

### Translations

- `lang/{en,es}/` at the project root is the **only** source of translations for PHP and React (no `resources/lang/`, no i18n package). Add every key to both languages together; parity and hardcoded-text tests fail otherwise. ([languages](docs/architecture/i18n.md))
- PHP texts go through `TranslatedText::of('<group>.<key>')`; Filament text properties cannot call a function, so override the getter (`getTitle()`, `getNavigationLabel()`…); a badge color or icon never depends on its translated label. React texts use `useTranslation()` and dates `useDateTime()`.
- `app.locale` stays `en` (the panel's language switch needs it); the request language comes from `SetApplicationLocale` (user preference, then `Accept-Language`, then Spanish). Never force a locale in `__()` and never cache text already translated into one language.

### Data

- **The morph map is enforced** (`AppServiceProvider::registerMorphMap()`): `user`, `post`, `like`, `comment`, `admin_invitation`, `trusted_device_event`, `account_status_change`, `announcement`, `role`. A new polymorphic model needs a key there.
- **Moved migrations keep their original file name**, so a database that already ran them does not run them again. Published package migrations stay in `database/migrations/`.
- **`likes` has no foreign key:** deleting a post or comment deletes its likes explicitly. `Comment` uses `SoftDeletes` (tombstones), which changes the default queries of every module and of the panel.
- **Listings keep a constant number of queries:** counters and "liked by me" are preloaded (`withCount`, `withExists`, `Post::withEngagementFor()`) and reused by accessors; viewer flags never run a query per row.
- **Values mirrored in TypeScript change together:** `Comment::CONTENT_MAX_LENGTH` ↔ `modules/comment/constants/commentContent.ts`, and backend enums ↔ their TS mirrors (`AccountStatus`, `AnnouncementType`, `AnnouncementDisplay`, `NotificationType`, `PreferredLocale`).
- The route placeholder must match the controller's typed parameter for implicit binding (`{userImageType}` ↔ `UserImageType $userImageType`); Rector renames parameters to their type, so fix the route.
- Media paths come from `MediaLibraryCustomPathGenerator`; changing it orphans stored files. ([infrastructure](docs/architecture/infrastructure.md))

### Frontend

- Dialogs never mutate the URL for their own filters (`router.reload` with `preserveUrl`); empty states read `committedFilters`, not live `filters`. See `.ai/rules/frontend.md`.
- React StrictMode double-invokes mount effects in dev: flip "skip first render" refs only in the user-facing setter, never in the effect.
- `onHttpException` / `onNetworkError` return `false` where a 404/429 must not open Inertia's error modal.
- To check light mode, emulate `prefers-color-scheme`: the app follows the system theme.

### Tests

- A serial run of the whole suite needs `php -d memory_limit=2G vendor/bin/pest`; paratest workers do not inherit `-d` (hence `--passthru-php`).
- A test file without a `namespace` must not import a global class (`use LogicException;`): the warning becomes an `ErrorException` in paratest workers and aborts the run.
- Symfony sends `Accept-Language: en-us` on every test request, so a guest gets English. Compare messages with `translatedTextIn()`, never a copied text.
- Tests that send a wrong OTP use `invalidOtpFor($user)`; tests that reuse a code call `allowOtpReuse()`. Tests that run `UserSeeder` or `PostSeeder` call `Storage::fake('public')` first. Seeder tests never rely on `mt_srand`.
- In tests, change model data through Eloquent, never `DB::table()->update()` (`.ai/rules/backend.md`).

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

### Data and Resources

- Use `spatie/laravel-data` classes under `app/**/Data/` for typed request filters, input objects, and internal structures that do not represent Eloquent models.
- Use Laravel `JsonResource` classes under `app/**/Resources/` to serialize Eloquent models for Inertia or API output.
- Do not duplicate a model transformation in both a Data class and a Resource.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- Place new module-specific tests under the owning module's `Tests/` directory (`app/Modules/{Domain}/Tests/` for migrated modules; `app/{Domain}/Tests/` or `app/{Domain}/Modules/{Feature}/Tests/` in the old architecture); reserve root `tests/` for cross-module, infrastructure, and existing tests.
- Register new module test directories in `phpunit.xml` and configure their Pest base case/shared traits in `tests/Pest.php` as needed.
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== sandermuller/laravel-fluent-validation/core rules ===

## FluentRule Validation

- This project uses `sandermuller/laravel-fluent-validation` for type-safe validation rules. Use `FluentRule::` instead of string rules or `Rule::` where possible.
- FormRequests MUST use `HasFluentRules` trait. Livewire components MUST use `HasFluentValidation` trait.
- Keep `FluentRule` definitions in the FormRequest `rules()` method. Build Laravel Data input DTOs from `$request->validated()` for typed access and mapping; do not define or return `FluentRule` rules from a Data class.
- Do NOT use `->rule('string_rule')` when a native FluentRule method exists. Check the skill references before using escape hatches.
- Available types: `FluentRule::string()`, `integer()`, `numeric()`, `email()`, `date()`, `dateTime()`, `boolean()`, `array()`, `file()`, `image()`, `password()`, `field()`.
- Convenience shortcuts: `FluentRule::url()`, `uuid()`, `ulid()`, `ip()` — shorthand for `FluentRule::string()->url()`, etc.
- `email()` and `password()` use app defaults (`Email::default()`, `Password::default()`). Pass `defaults: false` to opt out.
- All conditional modifiers (`requiredIf`, `excludeIf`, `prohibitedIf`, etc.) accept both `(string $field, ...$values)` AND `(Closure|bool)` — do NOT wrap in `Rule::requiredIf()`.
- For converting validation rules, activate the `fluent-validation-optimize` skill which has a complete method reference.
- For Livewire-specific guidance, activate the `fluent-validation-livewire` skill.

=== spatie/laravel-medialibrary/core rules ===

## Media Library

- `spatie/laravel-medialibrary` associates files with Eloquent models, with support for collections, conversions, and responsive images.
- Always activate the `medialibrary-development` skill when working with media uploads, conversions, collections, responsive images, or any code that uses the `HasMedia` interface or `InteractsWithMedia` trait.

</laravel-boost-guidelines>
