# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> ⚠️ **Mantenimiento de la doc**: cuando cambies la estructura de módulos del backend, agregues un nuevo dominio, modifiques la morph map, cambies versiones de paquetes relevantes o actualices convenciones de Laravel/PHP/Inertia/React, actualizá este documento en el mismo PR/commit. La doc es la fuente de verdad para entender el proyecto y no debe quedar desincronizada con el código.

## Project Overview

**social-hub** is a Laravel 13 + React 19 SPA (Inertia v3) social-media application. The domain is split into self-contained modules under `app/<Domain>/`, each with its own controllers, models, providers, requests, resources, routes, and (when applicable) factories/enums/seeders.

Domains in this codebase:

- **Auth** (`app/Auth/`) — login, register, email verification, password reset (via Fortify), 2FA + trusted devices.
  - **Per-concern layout**: `app/Auth/{Email,Login,Password,Register}/` each owns its controllers + requests and is wired via the central `AuthRouteServiceProvider`.
  - **Shared infrastructure stays at the parent**: `app/Auth/Models/` (domain models), `app/Auth/Database/{Migrations,Factories}/` (registered automatically by `AuthServiceProvider::loadMigrationsFrom`), `app/Auth/Providers/`, `app/Auth/routes/` (one file per concern, e.g. `trustedDevice.php`), and `app/Auth/config/`.
  - **Submodule convention (`app/Auth/Modules/<Feature>/`)** — established in SOC-14. Use when a feature grows beyond a single concern and earns its own namespace (e.g. `Modules/TrustedDevice/`). The submodule mirrors the parent's *feature-side* layout (`Controllers/`, `Requests/`, `Listeners/`, `Resources/`, `Concerns/`, `Enums/`) but **does not duplicate providers, models, routes, migrations, or factories** — those stay shared at the parent so the feature integrates with the domain's wiring without owning its own bootstrap.
  - `AuthRouteServiceProvider` loads `routes/` (one file per concern); `AuthEventServiceProvider` registers Fortify event listeners for the module.
  - **Trusted device audit log (SOC-20)** — `app/Auth/Database/Migrations/*_create_trusted_device_events_table.php` is the canonical example of the **domain audit log** pattern: append-only `trusted_device_events` table records discrete actions (`Created`, `Renewed`, `Renamed`, `Revoked`, `RevokedAll`) with FKs `nullOnDelete` to preserve forensic trail. Records are inserted via `TrustedDeviceEvent::record()` (static helper, the ONLY entry point) called from 5 hook sites (controller `store/update/renew/destroy/destroyAll` + the `TwoFactorAuthenticationDisabled` listener).
- **Home** (`app/Home/`) — landing/dashboard pages.
- **User** (`app/User/`) — profile, settings; the authenticatable model lives here.
- **Post** (`app/Post/`) — posts with images via `spatie/laravel-medialibrary`.
- **Comment** (`app/Comment/`) — polymorphic, self-referential (comments on posts and on other comments).
- **Like** (`app/Like/`) — polymorphic likes on posts and comments.
- **Friendship** (`app/Friendship/`) — friend requests/relationships.
- **MediaLibrary** (`app/MediaLibrary/`) — media customisations and `CleanMediaLibraryFolders` Artisan command.

The polymorphic map (`Relation::enforceMorphMap`) is registered in `App\Providers\AppServiceProvider::boot()` with keys: `user`, `post`, `like`, `comment`.

## Authoritative rules source

Project-wide coding rules, package-specific guidance (Inertia v3, Fortify, Wayfinder, MediaLibrary, FluentValidation, etc.), and Laravel Boost conventions live in **`AGENTS.md`**. Read it before making non-trivial changes — it contains instructions that override generic defaults (e.g. `inertia-react-development` skill activation, `FluentRule::` over string rules, Pint must run with `--dirty --format agent`, Wayfinder imports from `@/actions`/`@/routes`, no Pest — PHPUnit only).

Copilot rules in `.github/copilot-instructions.md` are also active; the one directive is: **prefix shell commands with `rtk`** (e.g. `rtk git status`) to save tokens.

The project also ships its own skill at `.claude/skills/social-hub-conventions/SKILL.md` — it activates automatically on any non-trivial code change and on Linear task closure, and enforces the modular domain structure, naming, the backend/frontend QA gates, and the Definition of Done documented in this file. Treat the skill's triggers as active by default.

## Common commands

All commands run from the repo root.

### Development

- `composer run dev` — starts `serve`, `queue:listen`, `pail`, and `vite` concurrently with coloured output (preferred over running them individually).
- `npm run dev` — Vite dev server only (use if PHP services are already running).
- `npm run build` / `npm run build:ssr` — production / SSR bundle.

### Frontend tooling (resources/)

- `npm run lint` / `npm run lint:check` — ESLint with autofix / check-only.
- `npm run format` / `npm run format:check` — Prettier on `resources/`.
- `npm run types` — `tsc --noEmit`.

### Backend tooling

- `composer pint` / `composer pint-dirty` — Laravel Pint formatter. After touching any PHP, run `vendor/bin/pint --dirty --format agent` (per AGENTS.md).
- `composer phpstan` — PHPStan at `level: max` with 100% type-coverage on return/param/property/constant/declare.
- `composer rector` / `composer rector:dry` — Rector refactoring (configured for PHP 8.5 + Laravel set).
- `composer ide-helper` — regenerates `_ide_helper_models.php` (PHPStan scans it).

### Tests

- `composer test` — clears config then runs PHPUnit.
- `php artisan test --compact` — full suite, compact output.
- `php artisan test --compact tests/Feature/FooTest.php` — single file.
- `php artisan test --compact --filter=testName` — single test by name (use after every edit).
- `php artisan make:test --phpunit {Name}` — create a feature test (most tests are feature tests; pass `--unit` for unit).

### Database / scaffolding

- `php artisan make:model --help` — see options; create matching factory/seeder when adding a model.
- `php artisan route:list` — inspect routes (filter by `--name`, `--method`, etc.).

## High-level architecture

### Modular service-provider pattern

Each domain registers itself through **two providers** wired up in `bootstrap/providers.php`:

1. `<Domain>ServiceProvider` (`app/<Domain>/Providers/`) — a thin provider whose `boot()` registers the domain's `<Domain>RouteServiceProvider`.
2. `<Domain>RouteServiceProvider` (`app/<Domain>/Providers/`) — extends `Illuminate\Foundation\Support\Providers\RouteServiceProvider`, loads routes from `app/<Domain>/routes/routes.php` under the `web` middleware group.

When adding a new domain: create both providers, register the outer one in `bootstrap/providers.php`, add a `routes/routes.php` (or for Auth, split per concern under `app/Auth/routes/`).

### Frontend layout (`resources/js/`)

- `app/app.tsx` — Inertia + React entrypoint.
- `modules/<domain>/...` — feature pages/components mirrored to PHP domains (`auth`, `home`, `post`, `comments`, `profile`, `setting`, `account`, `static`).
  - `modules/setting/` has sub-modules mirroring PHP: `modules/trustedDevices/`, `modules/twoFactor/`, plus `shared/` (cross-module code). The `setting` module also has its own `shared/` layer (`resources/js/modules/setting/shared/{components,layouts,utils}/`) for code reused by ≥2 sub-modules — when a util is used by only one sub-module it stays private; when both consume it, promote to `setting/shared/utils/`.
  - **TrustedDevice as parallel module** (SOC-20): the dedicated `/setting/trusted-devices` view lives in `modules/setting/modules/trustedDevices/` (not under `modules/twoFactor/`). It mirrors the layout: `components/dialog/` (7 dialogs + `index.ts` barrel), `components/ui/` (DeviceSummaryCard, DeviceMetadataItem, ActivityTimeline), `data/`, `types/`, `utils/`. The 7 dialogs were relocated from `modules/twoFactor/components/dialog/trustedDevice/` to enforce single ownership of domain-specific UI.
- `shared/` — reusable code:
  - `components/shadcn/ui/` — shadcn/ui primitives (style: `new-york`, base: `neutral`, see `components.json`). Includes `chart` (Recharts wrapper) for any chart blocks — use `ChartContainer` + `ChartConfig` with `theme: { light, dark }` for theme-aware segments.
  - `components/`, `hooks/`, `lib/`, `types/`, `assets/`.
  - `wayfinder/` — auto-generated typed Laravel routes/controllers (do not edit; regenerated by Vite plugin in `vite.config.ts`).
- `@/` alias resolves to `resources/js/` (configured in `tsconfig.json`).
- Real-time via `laravel-echo` + `@laravel/echo-react` against Laravel Reverb; broadcast channel defined in `routes/channels.php`.

### Models and relations (Eloquent)

- `App\User\Models\User` — extends `Authenticatable`, implements `HasMedia`, uses `TwoFactorAuthenticatable`, has `posts()`, `comments()`, `likes()`, `trustedDeviceEvents()` (HasMany to `TrustedDeviceEvent`).
- `App\Auth\Models\TrustedDevice` — has `events()` (HasMany to `TrustedDeviceEvent`); column `expires_at` drives `isActive` cast; device is hard-deleted on revoke (the event log preserves history).
- `App\Auth\Models\TrustedDeviceEvent` — append-only audit log row. Casts `action` to `TrustedDeviceAction` enum. Relations: `device()` (nullable BelongsTo, nullOnDelete), `user()` (nullable BelongsTo, nullOnDelete). Always created via `TrustedDeviceEvent::record(...)` helper.
- `App\Auth\Enums\TrustedDeviceAction` — cases `Created`, `Renewed`, `Renamed`, `Revoked`, `RevokedAll` (no `Expired` in v1 — see SOC-20 follow-ups).
- `App\Post\Models\Post` — implements `HasMedia` (collection `posts_images`, `singleFile()`), morph `post`. Has `user()`, `comments()` (MorphMany), `likes()` (MorphMany).
- `App\Comment\Models\Comment` — self-referential polymorphic via `commentable` column; casts `commentable_type` to `CommentType` enum. Has `user()`, `comments()` (replies, eager-loads `user`), `likes()`, `hasReplies()`, `repliesCount()`.
- `App\Like\Models\Like` — polymorphic via `likeable`; `user()`, `likeable()`.

### Validation

`FormRequest` classes MUST use the `HasFluentRules` trait and build rules with `FluentRule::*` (see `sandermuller/laravel-fluent-validation` rules in AGENTS.md). Use the `fluent-validation-optimize` skill when converting string-based rules.

### Strict typing & static analysis

- Every PHP file starts with `declare(strict_types=1);`.
- PHPStan (`phpstan.neon`) runs at `level: max` with `type_coverage: 100` across all dimensions and `type_perfect` rules — type hints/return types are mandatory, `mixed` is disallowed.
- Rector is configured for PHP 8.5; new typed class constants use `private const string NAME = '...';` syntax (see `FortifyServiceProvider` for the pattern).

### Naming — no single-letter variables

- Variables, parameters, and closure-captured values must be a **full descriptive word** (e.g. `$candidate`, `$device`, `$browserFilter`). Single-letter names like `$v`, `$i`, `$e`, `$x` are forbidden because they force the reader to decode intent from context.
- The only acceptable exception is a numeric `for` loop counter (`for ($i = 0; $i < count($arr); $i++)` is idiomatic PHP). Even there, prefer `foreach` over indexed `for` whenever possible.
- TypeScript follows the same rule (see `AGENTS.md`).

### Media uploads

Files are handled via `spatie/laravel-medialibrary` with a custom path generator (`app/MediaLibrary/CustomPathGenerator.php`). Controllers catch `FileDoesNotExist|FileIsTooBig` and surface a flash notification via `back()->with('notification', [...])` or `Inertia::flash([...])->back()`. The notification shape is `{type: 'success'|'error', message: string, action?: {label, url}}`.

## Tooling configuration quick reference

- `phpstan.neon` — paths `app`, `bootstrap`, `database`, `routes`; excludes `bootstrap/cache` and `app/Http/Middleware/HandleInertiaRequests.php`; scans `_ide_helper_models.php`.
- `phpunit.xml` — sqlite `:memory:`, `BCRYPT_ROUNDS=4`, array drivers for cache/queue/session/mail.
- `eslint.config.ts` — flat config with `typescript-eslint`, `eslint-plugin-react`, `eslint-plugin-perfectionist`, prettier integration.
- `.prettierrc` — import order puts `react`, `@inertiajs/react`, `react-icons` first, then `@/modules`, then shared (`@/shared/components`, `@/shared/hooks`, etc.), then relative.
- `vite.config.ts` — `laravel-vite-plugin` + `@inertiajs/vite` + `@vitejs/plugin-react` + `@rolldown/plugin-babel` (`reactCompilerPreset`) + `@tailwindcss/vite` + `@laravel/vite-plugin-wayfinder` (outputs to `resources/js/shared/wayfinder`).

===

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

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
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
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

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

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

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

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== sandermuller/laravel-fluent-validation/core rules ===

## FluentRule Validation

- This project uses `sandermuller/laravel-fluent-validation` for type-safe validation rules. Use `FluentRule::` instead of string rules or `Rule::` where possible.
- FormRequests MUST use `HasFluentRules` trait. Livewire components MUST use `HasFluentValidation` trait.
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
