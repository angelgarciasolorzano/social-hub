# Backend Architecture

Reference for creating and maintaining backend modules in this Laravel project.

> ⚠️ **Keeping this document current**: when you add, change or remove a convention (or make any other backend architecture change), update this document in the same PR/commit. The docs are the source of truth for the project's conventions and must not drift from the code.

> 📚 **Module documents** (with the detail of each task): [Auth](modules/auth.md), [User](modules/user.md), [Post, Comment and Like](modules/content.md), [Announcement](modules/announcement.md), [Notification](modules/notification.md), [ActivityLog](modules/activity-log.md), [Admin](modules/admin.md), [languages](i18n.md) and [global infrastructure, seeders and tooling](infrastructure.md). This document explains how to build a backend module.

## 0. Where modules live: `app/Modules/` (SOC-38)

Domain modules live in `app/Modules/<Module>/` (namespace `App\Modules\<Module>\…`). Everything **outside** `app/Modules/` is global to the application:

| Inside `app/Modules/`                                                                 | Outside `app/Modules/` (global)                                                                                   |
| ------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| The domain's controllers, models, requests, resources, routes and tests                | `app/Providers/`, `app/Http/` (middleware, base controller), `app/Events/`, `app/Console/Commands/`, `app/Support/` |
| The module's own providers (`<Module>ServiceProvider`, `<Module>RouteServiceProvider`) | Listeners, policies and services shared by several modules                                                         |

**Migration status:** the migration is incremental. **Post**, **Comment** and **Like** moved to `app/Modules/Post/`, `app/Modules/Comment/` and `app/Modules/Like/`, and **ActivityLog**, **Announcement** and **Notification** were born there (SOC-54, SOC-61 and SOC-68); **Admin** lives in `app/Modules/Admin/` as a system module, not a standard CRUD domain. The other domains (`Auth`, `Friendship`, `Home`, `User`) stay in `app/<Domain>/` (the _old architecture_) until their own task moves them; for example, `Auth` will move to `app/Modules/Auth/`. The submodules described in section 3 (`app/<Domain>/Modules/<Feature>/`) also belong to the old architecture: do not confuse them with the top-level `app/Modules/` folder.

**Global code (SOC-42):** not everything in the root of `app/` is a domain waiting to be migrated. Code that serves several domains or the framework and has no models, routes or business logic of its own goes outside `app/Modules/`: Artisan commands in `app/Console/Commands/` (auto-discovered, no provider needed), global providers and middleware, and shared classes with no domain in `app/Support/`. `MediaLibrary` is the example: it is an integration with `spatie/laravel-medialibrary` that Post and User use, so its path generator lives in `app/Support/MediaLibrary/`, its command in `app/Console/Commands/MediaLibraryCleanFoldersCommand.php` and its tests in `tests/Feature/MediaLibrary/`. `app/Support/` is a base folder explicitly approved in SOC-42; any other new base folder needs approval, as `AGENTS.md` says. Destructive commands use `Prohibitable` and are prohibited in production from `AppServiceProvider::boot()`. Tests for global code live in `tests/Feature/<Area>/`; tests that touch the filesystem redirect `storage_path()` / `public_path()` with `useStoragePath()` / `usePublicPath()` to a temporary directory.

**Frontend and Wayfinder:** the controller's namespace decides the path of the generated actions. A migrated module is imported from `@/shared/wayfinder/actions/App/Modules/<Module>/Controllers/<Controller>`; an old-architecture one from `@/shared/wayfinder/actions/App/<Domain>/…`. The folder is gitignored: after moving a module, regenerate with `php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder` and update the imports.

## 1. Naming convention

Every file and class uses **PascalCase** and starts with the module name.

| Kind                   | Pattern                        | Example                                 |
| ---------------------- | ------------------------------ | --------------------------------------- |
| Controller             | `{Module}Controller`           | `PostController`                        |
| Model                  | `{Module}`                     | `Post`                                  |
| Factory                | `{Module}Factory`              | `PostFactory`                           |
| Request                | `{Module}{Action}Request`      | `PostStoreRequest`, `PostUpdateRequest` |
| Resource               | `{Module}Resource`             | `PostResource`                          |
| Data                   | `{Module}{Concept}Data`        | `TrustedDeviceFiltersData`              |
| Seeder                 | `{Module}Seeder`               | `PostSeeder`                            |
| Enum                   | `{Module}{Concept}`            | `CommentType`, `FriendshipStatus`       |
| Service Provider       | `{Module}ServiceProvider`      | `PostServiceProvider`                   |
| Route Service Provider | `{Module}RouteServiceProvider` | `PostRouteServiceProvider`              |
| Policy                 | `{Module}Policy`               | `PostPolicy`                            |
| Service                | `{Module}Service`              | `PostService`                           |
| Console Command        | `{Module}{Purpose}Command`     | `MediaLibraryCleanFoldersCommand`       |

**Exception:** `routes/routes.php` has no prefix (Laravel looks for it by that name).

## 2. Structure of a module

```
app/Modules/<Module>/
├── Controllers/
│   └── {Module}Controller.php
├── Models/
│   └── {Module}.php
├── Requests/
│   ├── {Module}StoreRequest.php
│   └── {Module}UpdateRequest.php
├── Resources/
│   └── {Module}Resource.php
├── Data/
│   └── {Module}{Concept}Data.php      ← input, filters and non-model structures
├── Database/
│   ├── Factories/
│   │   └── {Module}Factory.php
│   ├── Migrations/
│   └── Seeders/
│       └── {Module}Seeder.php        ← only when there is seed data
├── Tests/                             ← only when the module has its own tests
├── Providers/
│   ├── {Module}ServiceProvider.php
│   └── {Module}RouteServiceProvider.php
└── routes/
    └── routes.php
```

**Not every module needs everything.** Create only the folders you will use. The mandatory core:

- `Models/`
- `Providers/` (both providers)
- `routes/`

A module with its own migrations registers them with `loadMigrationsFrom()` in its service provider. Migrations moved from the global `database/migrations/` keep their original file name, so a database that already ran them does not run them again; published third-party package migrations stay in `database/migrations/`.

## 3. Submodules (old architecture)

> This section describes the `app/<Domain>/Modules/<Feature>/` layout of the old architecture (Auth and User). When those domains are migrated they will live in `app/Modules/<Domain>/` (e.g. `app/Modules/Auth/`).

When a module has **several independent areas** that deserve their own space, it is split. Examples in the project:

- `app/Auth/Modules/` → `Email/`, `Login/`, `Password/`, `Register/`, `TrustedDevice/`
- `app/User/` → `Profile/`, `Preferences/`, `Modules/AccountSettings/`, `Modules/AccountStatus/`, `Modules/TwoFactor/`

How Auth and User split their features (TrustedDevice, the 2FA settings, AccountSettings, AccountStatus, the password change owned by Auth) is described in [Auth](modules/auth.md) and [User](modules/user.md). Laravel's initial migrations stay in the root `database/migrations/` folder.

### When to split

| ✅ Yes                                                     | ❌ No                             |
| ---------------------------------------------------------- | --------------------------------- |
| There are independent flows (login vs. registration)       | It is a small, cohesive module    |
| Each area has its own Requests with different validation   | The files are tightly coupled     |
| The areas will evolve separately                           | You only have 2-3 files           |

### Structure of a submodule

```
app/<Module>/<Submodule>/
├── Controllers/
│   └── <Submodule>Controller.php
├── Services/                         ← shared business logic of the feature
│   └── <Submodule>Service.php
└── Requests/                          ← only when there is specific validation
    └── <Submodule>Request.php
```

**Important:** submodules have NO providers of their own. Routes are centralized in `app/<Module>/routes/` with one file per submodule:

```
app/Auth/routes/
├── routes.php        ← loads the includes
├── email.php
├── login.php
├── password.php
└── register.php
```

### The `Modules/<Feature>/` convention (introduced in SOC-14)

When a feature **inside a module grows enough to deserve its own namespace**, it is extracted under `app/<Module>/Modules/<Feature>/`. Canonical example: `app/Auth/Modules/TrustedDevice/`.

**What the submodule DOES have** (it mirrors the feature side of the parent module):

```
app/<Module>/Modules/<Feature>/
├── Concerns/        ← local traits / helpers (e.g. HasPasswordConfirmation.php)
├── Controllers/     ← the feature's controllers
├── Listeners/       ← Fortify / domain event listeners scoped to the feature
├── Data/            ← typed filters and structures that are not models
├── Requests/        ← the feature's FormRequests
├── Resources/       ← Eloquent API Resources of the feature's models
└── Services/        ← shared business logic of the feature
```

**What stays in the parent module** (never duplicated inside the submodule):

- `Providers/` — the `<Module>ServiceProvider` and `<Module>RouteServiceProvider` already cover the feature.
- `Models/` — the Eloquent model lives in the parent so its relations (`User::trustedDevices()`) stay with the rest of the domain.
- `routes/` — registered through the central `AuthRouteServiceProvider` with one file per concern (e.g. `routes/trustedDevice.php`).
- `Database/Migrations/`, `Database/Factories/` and `Database/Seeders/` — migrations and factories are registered through `<Module>ServiceProvider::loadMigrationsFrom`; seeders are called from `DatabaseSeeder`.
- `config/` — the module's configuration lives in the parent.

**Rule of thumb for extracting a submodule**: the feature has its own event listeners **or** 3+ controllers/requests of its own. Below that, keep it in the matching concern of the parent module. SOC-32 sets an explicit exception for Auth: Email, Login and Register live in `app/Auth/Modules/{Email,Login,Register}/` with their tests co-located; Login and Register are below the general threshold but follow the structure agreed for that task.

## 4. What each file is responsible for

| Kind                       | What it does                                                                     |
| -------------------------- | -------------------------------------------------------------------------------- |
| **Controller**             | Orchestrates HTTP: receives the Request → calls the model/service → returns a Response |
| **Model**                  | Domain entity + Eloquent relations                                                |
| **Request**                | Validates user input (FormRequest with FluentRule)                                |
| **Resource**               | Turns a model into the JSON the client sees                                       |
| **Data**                   | Represents and normalizes input, filters or non-model structures                  |
| **Factory**                | Builds fake instances for tests/seeders                                           |
| **Seeder**                 | Fills the database with initial data                                              |
| **Policy**                 | Authorization rules (who can do what)                                             |
| **Service**                | Complex business logic that does not fit in a controller                          |
| **Console Command**        | Artisan operations: batch, maintenance (global: `app/Console/Commands/`)          |
| **Service Provider**       | Registers bindings in Laravel's container                                         |
| **Route Service Provider** | Loads the module's routes under the `web` middleware                              |

> 💡 If an action does not fit in a controller (many cases, complex rules, several transactions) → create a **Service**. If an authorization rule is reusable → create a **Policy**.

### Data and Resources

- Use `spatie/laravel-data` for typed filters, input and internal structures that are not Eloquent models.
- Use `JsonResource` to serialize Eloquent models for Inertia or APIs.
- Do not duplicate in a `Data` class a model transformation that belongs to a `Resource`.

### Test organization

New tests live next to the module that owns the behavior:

- A module on the new architecture: `app/Modules/<Domain>/Tests/`, plus the system module's `app/Modules/Admin/Tests/`.
- A domain module on the old architecture: `app/<Domain>/Tests/`.
- A submodule feature (old architecture): `app/<Domain>/Modules/<Feature>/Tests/`.
- Keep `tests/` for cross-module/infrastructure tests and existing tests not yet migrated; do not add new module-specific tests there.

Inside each `Tests/`, group by responsibility:

- `Crud/` to create, update and delete resources.
- `Factories/` and `Seeders/` for factories and seeders (the Admin module keeps `Database/` for its own).
- `Models/` for relations, the morph map and the model's media collections.
- `Lifecycle/` for renewals, reactivations and other lifecycle changes.
- `Listeners/` for behavior triggered by listeners.
- `Queries/` for indexes, listings and filters.
- `Requests/` for FormRequest validation rules and messages.
- `Resources/` for Resource serialization.
- `Security/` for security and robustness regressions: rate limiting, input normalization, nesting limits and query counts.

Create a new folder (for example `Services/`) only when there is code of that kind to test. When you add a new modular test folder, register it in `phpunit.xml` and configure its base case and shared traits in `tests/Pest.php`. The test infrastructure (parallel runs, shared helpers) is described in [infrastructure.md](infrastructure.md#test-infrastructure).

## 5. How to create a new module

Follow this checklist in order:

1. **Create the folder structure:**

   ```bash
   mkdir -p app/Modules/{Module}/{Models,Providers,routes,Controllers,Requests,Resources,Database/{Migrations,Factories,Seeders}}
   ```

2. **Create the model** in `app/Modules/{Module}/Models/{Module}.php`.

3. **Create the factory** in `app/Modules/{Module}/Database/Factories/{Module}Factory.php`. Its default state covers the model's persisted fields and its migration with realistic values or an explicit `null`; let Eloquent manage the primary key and the timestamps.

4. **Create both providers:**
   - `app/Modules/{Module}/Providers/{Module}ServiceProvider.php` — its `boot()` registers the `RouteServiceProvider`.
   - `app/Modules/{Module}/Providers/{Module}RouteServiceProvider.php` — extends `Illuminate\Foundation\Support\Providers\RouteServiceProvider` and loads `app/Modules/{Module}/routes/routes.php` with the `web` middleware.

5. **Register `{Module}ServiceProvider`** in `bootstrap/providers.php`.

6. **Create the controller** in `app/Modules/{Module}/Controllers/{Module}Controller.php`.

7. **Create the Requests** for the actions (Store, Update or other specific ones).

8. **Create the Resource** (paginated listings are serialized with `->through()` over the Resource; no Collection class is created).

9. **Define the routes** in `app/Modules/{Module}/routes/routes.php`.

10. **Create the tests next to the code they cover:** `app/Modules/{Module}/Tests/` for module tests (old architecture: `app/{Module}/Tests/` or `app/{Module}/Modules/{Feature}/Tests/` for a submodule). Register the folder in `phpunit.xml` and `tests/Pest.php` so Pest discovers it with the right configuration.

## 6. Additional conventions

- Every PHP file starts with `declare(strict_types=1);`.
- PSR-4 namespaces: `App\Modules\{Module}\{Type}` (old architecture: `App\{Module}\{Type}`).
- When a model or its schema changes, review and update its factory so it covers the persisted fields with realistic data or an explicit `null`; omit only the fields Eloquent manages (primary key and timestamps).
- Eloquent relations declare their generics in PHPDoc: `@return HasMany<Post, $this>`.
- Models with `HasFactory` declare the generic: `/** @use HasFactory<{Module}Factory> */`.
- Model constants (`MORPH_NAME`, `MORPH_COLUMN`) carry a descriptive docblock.
- A polymorphic model registers its key in `App\Providers\AppServiceProvider::registerMorphMap()` (`Relation::enforceMorphMap`).
- In a domain's own polymorphic table, the columns and the relation share Laravel's `{adjective}able` suffix: `morphs('commentable')` creates `commentable_type` and `commentable_id`, and the model exposes `commentable()` (like `likeable` in Like). `model_type` / `model_id` is the convention of generic package tables such as Spatie's (`media`, permissions), not of domain tables.
- A policy under `Modules/` is attached with `#[UsePolicy]` on the model (Laravel's name-based discovery does not find it) and applied with `#[Authorize('<ability>', '<routeParameter>')]` on the controller method: it runs after route-model binding and before the FormRequest, so a stranger gets 403 even with invalid input.
- The route placeholder must match the controller's typed parameter when using implicit enum or model binding (e.g. `{post}` ↔ `Post $post`); otherwise Laravel resolves the class from the container and the route answers 500. Rector renames parameters to match their type, so fix the route, not the parameter.
- Routes that write data have a `throttle`. When a module has several throttled routes, use the third argument as a prefix (`throttle:6,1,comments.store`): Laravel signs unnamed throttles by user only, so without a prefix every route would share one budget.
- Normalize free-text input in the FormRequest's `prepareForValidation()` (for example, strip control characters while keeping tabs and line breaks, and trim) so the rules run on the value that is actually stored.
- Cursor-paginated listings are serialized with `cursorPaginate()->through(fn (Model $model): array => new {Module}Resource($model)->resolve($request))`; they are not wrapped in a hand-made Collection that rebuilds `data`, `meta` and `links`. The paginator is returned as is. Wrap it in `Inertia::scroll(...)` only when the page uses Inertia's `<InfiniteScroll>` component (like `posts` in Home and Profile): `Inertia::scroll` adds merge instructions (`mergeProps`) and scroll metadata (`scrollProps`) that nobody consumes without that component.
- Data for a dialog or list that is only needed when it opens is sent as `Inertia::optional` props from a `ProvidesInertiaProperties` class in `Props/` (see `app/Modules/Comment/Props/CommentThreadProps.php`), which the host page's controller includes in `Inertia::render`. They have no route of their own: the client does a partial reload with `only`. The read's rate limit is applied with `RateLimiter` inside the class, since there is no route middleware.
- A controller with a single action is invokable (`__invoke`), e.g. the Auth/TrustedDevice controllers; when a module has several actions on the same resource they are grouped in one controller with one method per action, e.g. `CommentController` (`store`, `update` and `destroy`) and `LikeController` (`toggleComment` and `togglePost`, which share a private method).
- Deleting a record with dependants is decided explicitly inside a `DB::transaction` (the comment tombstones of SOC-52 are the example in [content.md](modules/content.md)). Adding `SoftDeletes` changes the default queries of every module and of the Admin panel, so run the full suite and update tests that used `delete()` as a hard delete.
- A `JsonResource` with several blocks splits them into private methods with the same style (`author()`, `repliedTo()`, `repliesInfo()`, `likesInfo()`, `isMine()`), and flags that depend on the authenticated user (`isMine`, `likedByMe`) are computed by comparing already loaded attributes, without a Gate or extra queries.
- Listings that serialize relation counters preload them with `withCount()` (or `withExists()`, or a scope such as `Post::withEngagementFor()`) in the query; the model's accessor reuses `{relation}_count` when present and queries only when it was not preloaded, so a page has a constant cost. Back it with a query-count test.
- A maximum length shared with the frontend lives in a model constant (e.g. `Comment::CONTENT_MAX_LENGTH`) mirrored in TypeScript; change both together.

## Canonical example: the `Post` module

```
app/Modules/Post/
├── Controllers/PostController.php
├── Database/
│   ├── Factories/PostFactory.php
│   └── Seeders/PostSeeder.php
├── Events/PostCreated.php
├── Models/Post.php
├── Policies/PostPolicy.php
├── Providers/
│   ├── PostServiceProvider.php
│   └── PostRouteServiceProvider.php
├── Requests/PostRequest.php
├── Resources/PostResource.php
├── Tests/
│   ├── Crud/
│   ├── Factories/
│   ├── Models/
│   ├── Policies/
│   ├── Queries/
│   ├── Requests/
│   ├── Resources/
│   └── Seeders/
└── routes/routes.php
```

Use this module as the reference when creating new ones. `app/Modules/Comment/` is the second example: it adds `Concerns/`, `Enums/`, `Policies/`, `Props/` and `Tests/Security/`. `app/Modules/Like/` is the third and shows a module with `Database/Migrations/` moved from the global folder, a single controller with one action per likeable kind (comments and posts) and `routes/routes.php`.
