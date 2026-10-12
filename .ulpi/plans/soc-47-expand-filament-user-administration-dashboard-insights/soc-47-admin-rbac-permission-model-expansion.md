# Plan: SOC-47 Expansion: Reestructurar roles y permisos del panel Admin

## Overview

Agregar como alcance adicional de SOC-47 un modelo claro de RBAC para Filament: un rol `admin` que concede entrada al panel y roles de lectura por sección que agrupan permisos específicos. Las aplicaciones autorizan por permisos; los usuarios obtienen la suma de permisos de sus roles. El plan original no se modifica.

**Linear:** [SOC-47](https://linear.app/social-hub-ang/issue/SOC-47/feature-expand-filament-user-administration-dashboard-insights) · expansión del alcance existente.

**Modo:** EXPANSION · **Revisión predeterminada:** Codex

## Scope Challenge

EXPANSION confirmada por el usuario. Este plan agrega a SOC-47 la normalización del catálogo Spatie y la autorización por secciones. Se guarda como un par aparte en la carpeta existente; el par original de SOC-47 permanece intacto.

## Prerequisites

- SOC-47 ya tiene recursos de solo lectura para Users y Posts, relation managers para posts, comments y trusted devices, y widgets del dashboard.
- El código y los documentos actuales usan `admin` como guard Spatie, pero las instrucciones vigentes de esta tarea requieren `web` para Spatie. El guard de autenticación de Filament permanece `admin`; son conceptos separados.
- El catálogo Admin ya está centralizado en `AdminRole` y `AdminPermission`, con seeders, comandos Artisan y pruebas existentes en `app/Modules/Admin/`.
- Los seeders pueden haber asignado `admin` aleatoriamente en bases locales ya pobladas. La actualización deja de crear asignaciones aleatorias, pero no revoca asignaciones existentes que podrían haberse administrado manualmente.

## Non-Goals

- Editar o reemplazar el plan original de SOC-47.
- Cambiar el guard de sesión de Filament (`admin`), el inicio de sesión público o el requisito de correo verificado.
- Añadir una interfaz Filament para administrar roles o permisos.
- Asignar permisos directamente a usuarios; los permisos se asignan a roles y los roles a usuarios.
- Añadir acciones de creación, edición, eliminación, suspensión o revocación de dispositivos.

## Contracts

- Spatie usa el guard `web` configurado por el modelo User. Filament conserva `authGuard('admin')`; `canAccessPanel()` exige panel `admin`, correo verificado y permiso `admin.access`.
- El rol `admin` agrupa únicamente `admin.access`: permite entrar al panel, pero por sí solo no concede acceso a secciones ni significa superadministrador.
- `admin-users-reader` agrupa `admin.users.viewAny` y `admin.users.view`; `admin-posts-reader` agrupa `admin.posts.viewAny` y `admin.posts.view`; `admin-dashboard-reader` agrupa `admin.dashboard.view`.
- Los permisos del catálogo son `admin.access`, `admin.dashboard.view`, `admin.users.viewAny`, `admin.users.view`, `admin.posts.viewAny` y `admin.posts.view`. `AdminPermission` y `AdminRole` son la única fuente de identificadores.
- User Policies y componentes Filament comprueban permisos con `can()`/Gate, no nombres de roles. Los permisos obtenidos por roles se combinan; no se conceden permisos directos al usuario.
- El recurso Posts y su vista de comentarios/replies requieren permisos `admin.posts.*`. La relación Posts dentro del detalle User requiere autorización de User y permiso de listado Posts; trusted devices y actividad de User continúan bajo permisos Users.
- Los widgets de estadísticas del dashboard solo muestran datos con `admin.dashboard.view`.
- La migración del catálogo transforma solo identificadores Admin conocidos: preserva asignaciones y pivots existentes al mover `guard_name` de `admin` a `web`, renombrar `users-reader` y renombrar permisos `users.*`. No elimina asignaciones de usuarios.
- El seeder conserva su comportamiento idempotente y las revocaciones manuales de permisos en roles existentes. `AdminUserSeeder` asigna roles de forma determinista a Angel en local/testing y deja de conceder roles a usuarios aleatorios.
- Los comandos existentes siguen validando entradas contra los enums y protegiendo que siempre quede al menos un usuario con `admin.access`.

## Existing Code Leverage

- `app/User/Models/User.php` — HasRoles, guard_name actual y comprobación de acceso al panel.
- `app/Modules/Admin/Providers/AdminPanelProvider.php` — Guard de sesión Filament `admin` y descubrimiento del panel.
- `app/Modules/Admin/Enums/AdminRole.php` — Catálogo actual `admin` y `users-reader`.
- `app/Modules/Admin/Enums/AdminPermission.php` — Catálogo actual `admin.access`, `users.viewAny` y `users.view`.
- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php` — Creación idempotente de roles/permisos y asignaciones pivot.
- `app/Modules/Admin/Database/Seeders/AdminUserSeeder.php` — Asignaciones actuales de Angel y promoción aleatoria local/testing.
- `app/User/Policies/UserPolicy.php` — Gate de listado y detalle User.
- `app/Modules/Admin/Filament/Resources/Posts/PostResource.php` — Autorización actual de listado y detalle Posts a través de UserPolicy.
- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/PostsRelationManager.php` — Relación de Posts visible desde el detalle de User.
- `app/Modules/Admin/Filament/Resources/Posts/RelationManagers/CommentsRelationManager.php` — Relación de comments y replies bajo Post.
- `app/Modules/Admin/Filament/Widgets/RegistrationSummaryWidget.php` — Widget de estadísticas de cuentas del dashboard.
- `app/Modules/Admin/Filament/Widgets/RegistrationsChart.php` — Chart de registros del dashboard.
- `app/Modules/Admin/Console/Commands/AdminBootstrapCommand.php` — Promoción del primer usuario verificado a los roles Admin.
- `app/Modules/Admin/Console/Commands/AdminUserRoleCommand.php` — Asignación/revocación de roles del catálogo por email.
- `app/Modules/Admin/Console/Commands/AdminRolePermissionsCommand.php` — Asignación/revocación de permisos del catálogo a roles.
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php` — Contrato actual del catálogo sembrado e idempotencia.
- `app/Modules/Admin/Tests/Database/AdminUserSeederTest.php` — Contrato actual de asignación aleatoria para admins locales.
- `app/Modules/Admin/Tests/Policies/UserPolicyTest.php` — Cobertura de lectura y rechazo de mutaciones User.
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ListPostsTest.php` — Acceso al índice del recurso Posts.
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ViewPostTest.php` — Acceso directo al detalle de Post.
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php` — Acceso a Posts desde el detalle de User.
- `app/Modules/Admin/Tests/Filament/Resources/Posts/RelationManagers/CommentsRelationManagerTest.php` — Acceso a comments y replies.
- `app/Modules/Admin/Tests/Filament/RegistrationDashboardTest.php` — Cobertura de los widgets de resumen y registros.
- `database/seeders/DatabaseSeeder.php` — Orden de seeders User, autorización Admin y usuarios de ejemplo.

## External References

- Spatie Laravel Permission v8, Roles vs Permissions: https://spatie.be/docs/laravel-permission/v8/best-practices/roles-vs-permissions
- Spatie Laravel Permission v8, Multiple Guards: https://spatie.be/docs/laravel-permission/v8/basic-usage/multiple-guards
- Spatie Laravel Permission v8, Direct Permissions: https://spatie.be/docs/laravel-permission/v8/basic-usage/direct-permissions

## Tasks

### TASK-013: Align the Spatie guard with web and preserve legacy assignments

Configure User and the Admin authorization seeder to use Spatie guard `web` while leaving Filament's session guard `admin` intact. Normalize existing canonical Admin roles and permissions from `admin` to `web` in place so existing user-role and role-permission pivots remain connected.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**
- A legacy Admin catalog seeded under guard `admin` is normalized to `web` without changing the users assigned to its roles or the permissions attached to those roles.
- Running the authorization seeder twice leaves one catalog entry per identifier and preserves existing grants.
- User resolves Spatie roles under `web`, while Filament continues authenticating with `admin` and panel admission still requires a verified email plus `admin.access`.

**writeScope:**
- `app/User/Models/User.php`
- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**Review:** Codex

### TASK-014: Update User role assertions to the web permission guard

Adjust the User role model test to assert role and permission resolution using the Spatie `web` guard, distinct from Filament's authentication guard.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-013

**Acceptance Criteria:**
- A User receives the seeded reader role and resolves its inherited permission with the `web` guard.
- The test fails if the Admin catalog is accidentally recreated under the Filament `admin` guard.

**writeScope:**
- `app/Modules/Admin/Tests/Models/UserRoleTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Models/UserRoleTest.php`

**Review:** Codex

### TASK-015: Namespace Admin permissions and add dashboard and Posts capabilities

Rename the current `users.*` permission values to `admin.users.*`, add `admin.posts.viewAny`, `admin.posts.view`, and `admin.dashboard.view`, and migrate the legacy permission rows in place. Preserve permission-to-role pivots and keep `admin.access` as panel admission only.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-014

**Acceptance Criteria:**
- The catalog contains the namespaced Users permissions, the Posts list/detail permissions, the dashboard permission, and `admin.access`, with no legacy `users.viewAny` or `users.view` entries.
- Legacy role grants remain attached to the renamed permission records after seeding, including a deliberately revoked permission.
- A repeated seeder run creates no duplicate permissions and does not silently restore a manually revoked role permission.

**writeScope:**
- `app/Modules/Admin/Enums/AdminPermission.php`
- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**Review:** Codex

### TASK-016: Name and seed the Admin permission bundle roles

Rename `users-reader` to `admin-users-reader` in place and add `admin-posts-reader` and `admin-dashboard-reader`. Assign each role only its matching section permissions; keep `admin` mapped only to `admin.access`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-015

**Acceptance Criteria:**
- Existing users assigned to `users-reader` retain the assignment under `admin-users-reader` after the seeder runs.
- `admin-users-reader`, `admin-posts-reader`, and `admin-dashboard-reader` each contain only their intended permission bundle; the `admin` role contains only `admin.access` by default.
- Seeding is idempotent, and removing a permission manually from an existing role remains effective after reseeding.

**writeScope:**
- `app/Modules/Admin/Enums/AdminRole.php`
- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**Review:** Codex

### TASK-017: Authorize User policies with namespaced capabilities

Update UserPolicy and its tests to use `admin.users.viewAny` and `admin.users.view` through Laravel Gate. Preserve the read-only policy contract.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-016

**Acceptance Criteria:**
- Users list access requires `admin.users.viewAny`; viewing a User record requires `admin.users.view`.
- Having only Posts or dashboard permissions does not authorize User listing or detail.
- Create, update, delete, restore, and force-delete abilities remain denied.

**writeScope:**
- `app/User/Policies/UserPolicy.php`
- `app/Modules/Admin/Tests/Policies/UserPolicyTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Policies/UserPolicyTest.php`

**Review:** Codex

### TASK-018: Authorize the Posts resource with Posts capabilities

Stop routing PostResource authorization through UserPolicy. Require `admin.posts.viewAny` for the index and `admin.posts.view` for a record, including direct detail URLs.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-016

**Acceptance Criteria:**
- A user with the Posts reader role can list and view Posts without receiving User permissions.
- A user with only `admin-users-reader` is denied the Posts index and direct detail route.
- All Post mutation abilities remain unavailable.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Posts/PostResource.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ListPostsTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ViewPostTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ListPostsTest.php && rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ViewPostTest.php`

**Review:** Codex

### TASK-019: Require Posts permission in the User Posts relation

Keep the User detail protected by its User permission and require `admin.posts.viewAny` before exposing the Posts relation manager from that page.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-017, TASK-018

**Acceptance Criteria:**
- A user with User read access but without Posts read access cannot see or query the Posts relation from a User detail.
- A user with both required permissions can search, filter, sort, and open only the selected User's Posts.
- Direct relation requests do not bypass either the User record or Posts permission check.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/PostsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php`

**Review:** Codex

### TASK-020: Require Posts permission for comments and replies

Authorize the Comments relation manager from the Post capability rather than the owning User's permission. Comments and replies remain part of read-only Post details.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-018

**Acceptance Criteria:**
- A user with `admin.posts.view` can view comments and open replies for an authorized Post.
- A user with only User permissions cannot reach the comments table or its ViewAction through a direct Livewire request.
- Comment/reply create, edit, delete, and bulk mutation actions remain absent.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Posts/RelationManagers/CommentsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/RelationManagers/CommentsRelationManagerTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/RelationManagers/CommentsRelationManagerTest.php`

**Review:** Codex

### TASK-021: Protect dashboard statistics with a dashboard role

Gate the registration summary and chart widgets with `admin.dashboard.view`; the `admin` panel-entry role alone must not reveal registration statistics.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-016

**Acceptance Criteria:**
- Both registration widgets are visible to a user with `admin-dashboard-reader`.
- A verified panel user with only the `admin` role can enter the panel but does not receive registration counts or chart data.
- Existing chart ranges and summary values remain unchanged for authorized users.

**writeScope:**
- `app/Modules/Admin/Filament/Widgets/RegistrationSummaryWidget.php`
- `app/Modules/Admin/Filament/Widgets/RegistrationsChart.php`
- `app/Modules/Admin/Tests/Filament/RegistrationDashboardTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/RegistrationDashboardTest.php`

**Review:** Codex

### TASK-022: Update the user-role command for the new catalog

Use the `web` Spatie guard and the renamed role enum values in admin:user-role. Preserve the safeguard that prevents revoking the final role granting `admin.access`.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-016

**Acceptance Criteria:**
- The command can grant and revoke `admin-users-reader`, `admin-posts-reader`, and `admin-dashboard-reader` by email.
- Assigning a reader role alone does not grant panel entry; assigning `admin` grants `admin.access`.
- Unknown roles and revocation of the last panel-access role are rejected without changing assignments.

**writeScope:**
- `app/Modules/Admin/Console/Commands/AdminUserRoleCommand.php`
- `app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php`

**Review:** Codex

### TASK-023: Update role-permission administration for namespaced permissions

Use the `web` Spatie guard and new enum values in admin:role-permissions. Keep the last-admin-access safeguard and allow operators to update the role bundles from the catalog.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-016

**Acceptance Criteria:**
- The command grants and revokes namespaced permissions only for cataloged roles and permissions.
- Unknown role or permission identifiers are rejected without mutating pivots.
- The last `admin.access` grant cannot be revoked.

**writeScope:**
- `app/Modules/Admin/Console/Commands/AdminRolePermissionsCommand.php`
- `app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`

**Review:** Codex

### TASK-024: Bootstrap a verified administrator with the reader bundles

Update admin:bootstrap-admin to assign the base `admin` role and all current dashboard, Users, and Posts reader roles to the first verified administrator.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-016

**Acceptance Criteria:**
- The first verified user receives `admin` plus all cataloged reader roles and can access the panel and all current read-only sections.
- An unverified account is rejected without role changes.
- The command refuses a second bootstrap once an account already has `admin.access`.

**writeScope:**
- `app/Modules/Admin/Console/Commands/AdminBootstrapCommand.php`
- `app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`

**Review:** Codex

### TASK-025: Make local Admin role seeding deterministic

Assign Angel the base role and all current reader bundles in local/testing, then remove the random ten-percent Admin promotion. Do not revoke pre-existing explicit role assignments automatically.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-016

**Acceptance Criteria:**
- A fresh local/testing seed gives Angel `admin`, `admin-dashboard-reader`, `admin-users-reader`, and `admin-posts-reader`.
- Other generated users receive no Admin roles through random selection.
- Rerunning the seeder remains deterministic and does not revoke roles explicitly assigned to existing users.

**writeScope:**
- `app/Modules/Admin/Database/Seeders/AdminUserSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminUserSeederTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminUserSeederTest.php`

**Review:** Codex

### TASK-026: Synchronize the Admin RBAC documentation

Update the existing project instructions and backend architecture reference to distinguish Filament's `admin` session guard from Spatie's `web` guard and document the new role-permission bundles and command arguments. Do not edit either SOC-47 plan pair.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P2

**Depends on:** TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025

**Acceptance Criteria:**
- AGENTS.md, CLAUDE.md, and docs/architecture/backend.md describe the same guards, roles, permissions, and panel-entry behavior.
- Documentation states that `admin.access` is panel entry only and reader roles grant section capabilities through permissions.
- The original `.ulpi/plans/soc-47-expand-filament-user-administration-dashboard-insights/` Markdown and JSON files remain unchanged.

**writeScope:**
- `AGENTS.md`
- `CLAUDE.md`
- `docs/architecture/backend.md`

**validateCommand:** `rtk git diff --check -- AGENTS.md CLAUDE.md docs/architecture/backend.md`

**Review:** Codex

### TASK-027: Run integrated Admin authorization and backend gates

After the expansion slices are integrated, run the Admin tests, full Pest suite, formatting, static analysis, Rector preview and apply, then Laravel Doctor as the final local gate.

**Type:** test · **Effort:** L · **Agent:** general-purpose · **Priority:** P1

**Depends on:** TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-026

**Acceptance Criteria:**
- All Admin module tests and the full Pest suite pass, including panel access, independent Users/Posts/dashboard permissions, command safeguards, and preserved legacy assignments.
- Pint, PHPStan, Rector dry-run, Rector apply, and Composer Doctor pass in the required order; Composer Doctor runs last.

**writeScope:**
- (sin archivos de código)

**validateCommand:** `rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk composer rector-dry && rtk composer rector && rtk php artisan test --compact app/Modules/Admin/Tests && rtk php artisan test --compact && rtk composer doctor`

**Review:** Codex

## Failure Modes

- Cambiar el guard o renombrar roles/permisos creando filas nuevas puede dejar pivots de usuarios huérfanos; la transición debe modificar las filas canónicas en sitio y probar una asignación heredada.
- El recurso Posts puede revelar datos desde el relation manager de Users o desde comments/replies aunque su índice esté protegido; cada ruta de acceso debe exigir los permisos correspondientes.
- `admin.access` puede confundirse con acceso global; el contrato lo limita a la entrada al panel y protege dashboard y recursos con permisos específicos.
- Una base local ya poblada puede conservar roles `admin` asignados aleatoriamente por la versión anterior. El seeder nuevo no crea nuevas asignaciones aleatorias ni revoca automáticamente asignaciones persistidas indistinguibles de las manuales.

## Ship Cut

La expansión se considera integrada después de TASK-027. El corte conserva sin cambios el plan original de SOC-47; cualquier rol legado persistido que deba revocarse se revisa explícitamente con el comando Admin, sin borrado masivo durante el seeding.

## Test Coverage Map

- **TASK-013:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`
- **TASK-014:** `rtk php artisan test --compact app/Modules/Admin/Tests/Models/UserRoleTest.php`
- **TASK-015:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`
- **TASK-016:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`
- **TASK-017:** `rtk php artisan test --compact app/Modules/Admin/Tests/Policies/UserPolicyTest.php`
- **TASK-018:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ListPostsTest.php && rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ViewPostTest.php`
- **TASK-019:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php`
- **TASK-020:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/RelationManagers/CommentsRelationManagerTest.php`
- **TASK-021:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/RegistrationDashboardTest.php`
- **TASK-022:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php`
- **TASK-023:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`
- **TASK-024:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`
- **TASK-025:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminUserSeederTest.php`
- **TASK-026:** `rtk git diff --check -- AGENTS.md CLAUDE.md docs/architecture/backend.md`
- **TASK-027:** `rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk composer rector-dry && rtk composer rector && rtk php artisan test --compact app/Modules/Admin/Tests && rtk php artisan test --compact && rtk composer doctor`

## Execution Summary

- **Tareas:** 15
- **Fases lógicas:** 8
- **Caminos críticos:** TASK-013 → TASK-014 → TASK-015 → TASK-016 → TASK-017 → TASK-019 → TASK-026 → TASK-027; TASK-013 → TASK-014 → TASK-015 → TASK-016 → TASK-018 → TASK-019 → TASK-026 → TASK-027; TASK-013 → TASK-014 → TASK-015 → TASK-016 → TASK-021 → TASK-026 → TASK-027
- **Revisión predeterminada:** Codex
- **Salida:** La expansión se considera integrada después de TASK-027. El corte conserva sin cambios el plan original de SOC-47; cualquier rol legado persistido que deba revocarse se revisa explícitamente con el comando Admin, sin borrado masivo durante el seeding.

## Task Dependencies

- **TASK-013** ← ninguna
- **TASK-014** ← TASK-013
- **TASK-015** ← TASK-014
- **TASK-016** ← TASK-015
- **TASK-017** ← TASK-016
- **TASK-018** ← TASK-016
- **TASK-019** ← TASK-017, TASK-018
- **TASK-020** ← TASK-018
- **TASK-021** ← TASK-016
- **TASK-022** ← TASK-016
- **TASK-023** ← TASK-016
- **TASK-024** ← TASK-016
- **TASK-025** ← TASK-016
- **TASK-026** ← TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025
- **TASK-027** ← TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-026

