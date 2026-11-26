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

### Admin: Filament and Spatie Permission

- The current installed packages are `filament/filament` v5.10.1, `filament/spatie-laravel-media-library-plugin` v5.10.1 (approved in SOC-61 for the announcement image fields), `bezhansalleh/filament-language-switch` v5.0.0 (approved in SOC-65 for the panel's language switch) and `spatie/laravel-permission` v8.3.0; all are direct Composer dependencies.
- Admin panel code belongs under `app/Modules/Admin/`. `AdminPanelProvider` is registered in `bootstrap/providers.php` and discovers Filament classes from `app/Modules/Admin/Filament/`; Filament owns the panel routes, so do not add duplicate module routes for them. Follow Filament's per-resource hierarchy (`Filament/Resources/<PluralModel>/` with `Pages/`, `Schemas/`, `Tables/`), keep shared behavior in `Concerns/` traits, and keep `Console/Commands/` and `Filament/Widgets/` for classes that are registered or discovered (`.ai/rules/backend.md`).
- The `admin` session guard authenticates panel requests through the `users` provider; Spatie's `guard_name` `admin` scopes roles and permissions. They are distinct concepts, and `web` remains the default guard. Panel entry requires a verified, non-suspended account, `admin.access` and app-based MFA; `admin.access` alone grants no section.
- `AdminRole` (system roles) and `AdminPermission` are the canonical catalog; administrators can also create custom roles (SOC-70). Use `App\Modules\Admin\Models\Role`, never Spatie's class. Authorization lives in model policies (resources do not override `can*()`, and a read-only policy must deny every write ability). `AdminAuthorizationService` is the only writer of roles and permissions outside seeders.
- Every write of an announcement goes through `AnnouncementService`, only `Announcement::visibleTo()` decides what the application shows (approved announcements only, SOC-64), and the body is rendered only through `Announcement::safeBodyHtml()`.
- Production bootstrap: `php artisan db:seed --class='App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder'`, then `php artisan admin:bootstrap-admin <verified-email> --actor=<verified-email>`. Later changes go through the panel or `admin:user-role` / `admin:role-permissions`; every Admin command requires `--actor=<email>`. Existing administrators never receive new roles automatically: grant them with `admin:user-role grant`.
- Published third-party package migrations stay in the global `database/migrations/`; Admin's application code, seeders and tests stay in the module.
- Details: [`docs/architecture/modules/admin.md`](docs/architecture/modules/admin.md) (roles table, policies, commands, backups, roles in the panel, custom roles) and [`docs/architecture/modules/announcement.md`](docs/architecture/modules/announcement.md).

### Languages

- The admin panel (SOC-65, SOC-66) and the React settings pages (SOC-67) speak Spanish and English. `lang/{en,es}/` at the project root is the only source of translations for PHP and React (never `resources/lang/`, no i18n package, no catalog under `resources/js`).
- Follow the translation rules in `.ai/rules/backend.md` (panel texts and backend texts of translated pages through `TranslatedText::of()`) and `.ai/rules/frontend.md` (`useTranslation()`, `useDateTime()`, keys in `data/*.ts`). Add every key to `en` and `es` together; the parity and hardcoded-text tests fail otherwise.
- Details: [`docs/architecture/i18n.md`](docs/architecture/i18n.md).

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.
