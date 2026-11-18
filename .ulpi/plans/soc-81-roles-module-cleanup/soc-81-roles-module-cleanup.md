# Plan: SOC-81: Limpieza del módulo de Roles: sin duplicados y con las convenciones de Filament

## Overview

Última tarea de la épica SOC-72. SOC-73 a SOC-77 añadieron cada una sus helpers, consultas y pruebas; esta tarea deja cada pieza común con un único dueño en producción y en pruebas, revisa las convenciones de Filament 5 y quita traducciones sin uso, sin cambiar ningún comportamiento visible.

**Linear:** [SOC-81](https://linear.app/social-hub-ang/issue/SOC-81/refactor-clean-up-the-roles-module-remove-duplication-and-align-with) (sub-issue de [SOC-72](https://linear.app/social-hub-ang/issue/SOC-72/feature-improve-the-roles-module-of-the-admin-panel-pages-filters-and)) · **Modo:** HOLD · **Revisión predeterminada:** ninguna

## Scope Challenge

HOLD sin revisión automática (el usuario revisa cada tarea antes del «Sí»). Decisiones del usuario (2026-11-17): alcance exacto de la issue para Roles, incluidos los helpers de pruebas de `Models/`, `Policies/` y `Filament/Concerns/` que repiten los de Roles; el filtro del guard pasa a un scope local de `Role`. Hallazgos del inventario: el scope local `Role::admin()` ya existe pero 10 consultas siguen escribiendo `where('guard_name', …)` a mano; la restricción «roles de admin que otorgan X» está escrita en 3 sitios (`PermissionHolders` ×2 y `PermissionHoldersTable`); 3 lectores de conteos agregados (`RoleUsersTable::summaryCount`, `PermissionHolders::summaryCount`, `RoleTypesOverview::countIn`); 6 `canView`/`canAccess`/`canViewForRecord` repiten `Filament::auth()` + `Gate::forUser()` en lugar de `RoleResource::canViewAny()`/`canView()` (que llaman a la misma policy con el usuario del panel); 4 copias de la notificación de `AdminAuthorizationException`. En pruebas: 6 contadores de consultas, 8 registradores de actividad, 3 `actingAs*` idénticos, 3 atajos de claves visibles, 3 creadores de titulares que repiten `createUserHoldingRoles()` y 2 creadores de roles con permisos. Se quedan como están: `RolesTable::causerOf()` (envoltorio nulo usado dos veces, no duplica a `DescribesActivityLogParties`), los helpers que describen un escenario propio de su archivo (`changedCustomRole`, `listedRoles`, `filteredRoleNames`, `holderKeysByName`) y las acciones de un solo uso, que siguen dentro de su página o relation manager como hace Filament; la estructura de carpetas del recurso ya sigue Filament 5.

## Prerequisites

- SOC-77 mergeada en `main` (confirmado por el usuario).
- Scope local `Role::admin()` (`#[Scope]`) y `RolePolicy`/`UserPolicy` registradas por `#[UsePolicy]`, así `RoleResource::canViewAny()` y `UserResource::canViewAny()` consultan esas policies con `Filament::auth()`.
- Helpers globales de `tests/Pest.php`: `adminCatalogRole()`, `createUserHoldingRoles()`, `permissionHoldersPageFor()`; soporte en `app/Modules/Admin/Tests/Support/` (namespace `App\Modules\Admin\Tests\Support`, autoload PSR-4).

## Non-Goals

- Cambios de comportamiento visibles: textos, permisos, consultas por página y resultados quedan iguales.
- Otros recursos del panel (Users, ActivityLogs, Announcements…), aunque `ViewUser` repita la notificación de fallo o tengan contadores de consultas propios.
- Mover acciones de un solo uso a `Actions/` sin motivo: solo se moverán si la auditoría de TASK-019 encuentra que Filament 5 lo recomienda para ese caso.
- Cambiar `ActivityLogFactory` (vive en el módulo ActivityLog); los helpers de actividad de roles van en `Tests/Support/`.

## Contracts

- `Role::query()->admin()` es el filtro del guard `admin` en consultas que parten de `Role`; dentro de `whereHas('roles')` (relación de Spatie tipada como `Model`) se mantiene `getQuery()->where('roles.guard_name', …)`. `Role::grantingPermission(Builder, AdminPermission)` es el único dueño de «roles de admin con ese permiso de admin».
- Autorización: los widgets y páginas de roles usan `RoleResource::canViewAny()`; las páginas que además listan usuarios, `UserResource::canViewAny()`; los relation managers, `RoleResource::canView($ownerRecord)` más el `canViewAny()` del recurso relacionado. Ninguna comprobación nueva de autorización vive fuera de las policies.
- `ReadsAggregateCounts`: dado un builder con un `selectRaw` agregado y la lista de columnas, devuelve `array<string, int>`; columna ausente o no numérica = 0.
- `NotifiesAdminAuthorizationFailure::notifyAdminAuthorizationFailure()` envía la misma notificación `danger` con `failure->message()` que hoy.
- `Tests/Support/QueryLog::count()`, `Tests/Support/RoleActivity::{grant,change,record}()`, `AdminFilamentTestContext::{actingAsRolesManager,actingAsRolesReader}()`, `FilamentTableRecords::shownKeys()` y `RoleFactory::withPermissions()` son los únicos dueños de lo que reemplazan.
- Las pruebas cambian cómo se preparan, nunca lo que afirman (criterio 1 de la issue).
- Comentarios: todo archivo de Roles que el plan toca, y los que solo tocan las tareas de comentarios (TASK-020 a TASK-024), termina con docblocks de 3 líneas de prosa como máximo (`.ai/rules/backend.md`) y sin comentarios que repitan el nombre o los tipos.
- Convenciones de Filament: TASK-019 audita el recurso contra la documentación de Filament 5 y agrega al plan una tarea por cada mejora real.

## Existing Code Leverage

- `Role::admin()`, `Role::system()`, `Role::custom()` (scopes locales con `#[Scope]`).
- `Filament\Resources\Resource::canViewAny()`/`canView()` → `get_authorization_response()` con el usuario del panel.
- `DescribesActivityLogParties`, `DescribesPanelMfa`, `DescribesAccountStatus` como modelo de concern compartido en `Filament/Concerns/`.
- `AdminFilamentTestContext` y `FilamentTableRecords` en `Tests/Support/`; `createUserHoldingRoles()` y `adminCatalogRole()` en `tests/Pest.php`.
- `ActivityLogFactory::forEvent()->performedOn()->causedBy()`.

## Tasks

### TASK-001: Páginas de roles: scope `admin()` y autorización del recurso

Cambiar `Role::query()->where('guard_name', Role::GUARD_NAME)` por el scope local que ya existe, `Role::query()->admin()`, en `ListRoles` (conteos de la cabecera), `RolePermissionMatrix` (`roleQuery`) y `CreateRole` (`adminRoleQuery`). `RolePermissionMatrix::canAccess()` pasa a `RoleResource::canViewAny()`, que consulta la misma `RolePolicy::viewAny` con el usuario del panel, en lugar de repetir `Filament::auth()` + `Gate::forUser()`. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Ninguno de los tres archivos filtra `guard_name` a mano; todos usan `->admin()`.
2. Un administrador sin `admin.roles.viewAny` sigue recibiendo 403 en la matriz, y sin usuario autenticado `canAccess()` sigue siendo `false` (las pruebas de autorización existentes pasan sin cambios).
3. Un rol del guard `web` con el mismo nombre sigue sin aparecer en la cabecera, la matriz ni las opciones «Empezar desde».

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/ListRoles.php`
- `app/Modules/Admin/Filament/Resources/Roles/Pages/RolePermissionMatrix.php`
- `app/Modules/Admin/Filament/Resources/Roles/Pages/CreateRole.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/CreateRoleTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-002: Widgets de roles: scope `admin()` y autorización del recurso

En `RolesOverview`, `RoleTypesOverview` y `UsersPerRoleChart`, usar `Role::query()->admin()` y reemplazar los tres `canView()` (que repiten `auth('admin')->user()` + `Gate::forUser()->allows('viewAny', Role::class)`) por `RoleResource::canViewAny()`. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Los tres widgets autorizan con `RoleResource::canViewAny()` y ninguno importa `Gate`.
2. Un administrador sin `admin.roles.viewAny` sigue sin ver ningún widget (las pruebas de visibilidad existentes pasan sin cambios).
3. Los conteos siguen ignorando roles del guard `web`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Widgets/RolesOverview.php`
- `app/Modules/Admin/Filament/Resources/Roles/Widgets/RoleTypesOverview.php`
- `app/Modules/Admin/Filament/Resources/Roles/Widgets/UsersPerRoleChart.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets < /dev/null && composer phpstan`

**Review:** none

### TASK-003: Concern único para leer conteos agregados

Crear `app/Modules/Admin/Filament/Concerns/ReadsAggregateCounts.php` con un método estático que ejecute un `selectRaw` agregado (`->toBase()->first()`) y devuelva cada columna pedida como `int` (0 cuando falta o no es numérica). Lo usan `RoleUsersTable::holdersSummary()` (quita su `summaryCount()`) y `RoleTypesOverview::roleTypeCounts()` (quita su `countIn()`). En `RoleUsersTable`, `filterByOtherRoles()` y el resto de filtros de guard pasan a `->admin()`. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002

**Acceptance Criteria:**

1. No queda ninguna función `summaryCount` ni `countIn` en `RoleUsersTable` ni en `RoleTypesOverview`; la lectura del conteo vive solo en el concern.
2. Un rol sin titulares sigue devolviendo `null` en el resumen de la pestaña de usuarios, y una fila sin la columna cuenta 0.
3. El número de consultas de la pestaña de usuarios no cambia (prueba de consultas constantes en verde).

**writeScope:**

- `app/Modules/Admin/Filament/Concerns/ReadsAggregateCounts.php`
- `app/Modules/Admin/Filament/Resources/Roles/Tables/RoleUsersTable.php`
- `app/Modules/Admin/Filament/Resources/Roles/Widgets/RoleTypesOverview.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersSummaryTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/RoleTypesOverviewTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-004: «Roles de admin que otorgan un permiso» con un solo dueño

Mover a `Role` un método estático público genérico `grantingPermission(Builder<TModel> $builder, AdminPermission): Builder<TModel>` que aplica la restricción a cualquier builder de roles (guard `admin` con `roles.guard_name` + `whereHas('permissions')` del mismo guard y nombre) para usarla en `whereHas('roles', …)`. No puede ser un scope local: Spatie tipa la relación `roles` como `Model`, así que PHPStan no ve los scopes dentro de esos closures (comprobado en TASK-003). Es el dueño único de lo que hoy repiten `PermissionHolders::grantingAdminRole()`, `grantingRoleQuery()`, `grantsPermission()` y la restricción de la carga ansiosa en `PermissionHoldersTable::holdersQuery()`. `PermissionHolders::getHolderSummary()` usa el concern de TASK-003 en lugar de su `summaryCount()`, y `canAccess()` pasa a `RoleResource::canViewAny() && UserResource::canViewAny()`. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. La restricción guard + permiso se escribe una sola vez (en `Role`); `PermissionHolders` y `PermissionHoldersTable` la reutilizan.
2. Un rol del guard `web` con un permiso del mismo nombre sigue sin contar como titular, y un usuario con el permiso por dos roles sigue apareciendo una vez.
3. Sin `viewAny` de roles o de usuarios la página sigue respondiendo 403; el número de consultas de la página no cambia.

**writeScope:**

- `app/Modules/Admin/Models/Role.php`
- `app/Modules/Admin/Filament/Resources/Roles/Pages/PermissionHolders.php`
- `app/Modules/Admin/Filament/Resources/Roles/Tables/PermissionHoldersTable.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/PermissionHoldersTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/PermissionHoldersUsersTest.php app/Modules/Admin/Tests/Models/RoleTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-005: Relation managers y recurso: autorización y guard sin repetir

`RoleUsersRelationManager::canViewForRecord()` y `RoleActivityLogsRelationManager::canViewForRecord()` dejan de repetir `Filament::auth()` + `Gate::forUser()`: usan `RoleResource::canView($ownerRecord)` junto con `UserResource::canViewAny()` o `ActivityLogResource::canViewAny()`. `RoleResource::getEloquentQuery()` usa el scope `admin()` en lugar de `getQuery()->where('guard_name', …)`. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Ninguno de los tres archivos importa `Gate` para estas comprobaciones; la autorización sigue en `RolePolicy`, `UserPolicy` y la policy de `ActivityLog`.
2. Un lector de roles sin `admin.users.viewAny` sigue sin ver la pestaña de usuarios, y uno sin lector de actividad sigue sin ver el historial.
3. Un rol del guard `web` sigue respondiendo 404 en `/admin/roles/{id}`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManager.php`
- `app/Modules/Admin/Filament/Resources/Roles/RelationManagers/RoleActivityLogsRelationManager.php`
- `app/Modules/Admin/Filament/Resources/Roles/RoleResource.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-006: Notificación única de los fallos de autorización

Crear `app/Modules/Admin/Filament/Concerns/NotifiesAdminAuthorizationFailure.php` con `notifyAdminAuthorizationFailure(AdminAuthorizationException)` (la notificación `danger` con `failure->message()`). Lo usan `ViewRole` y `RoleUsersRelationManager`, que pierden su `notifyFailure()` privado. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-005

**Acceptance Criteria:**

1. No queda ningún `notifyFailure()` privado en `ViewRole` ni en `RoleUsersRelationManager`.
2. Duplicar o eliminar un rol bloqueado, y quitar a alguien de un rol cuando el servicio lo rechaza, siguen mostrando la misma notificación de error y no cambian nada.

**writeScope:**

- `app/Modules/Admin/Filament/Concerns/NotifiesAdminAuthorizationFailure.php`
- `app/Modules/Admin/Filament/Resources/Roles/Pages/ViewRole.php`
- `app/Modules/Admin/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManager.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleLifecycleActionsTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersBulkRemoveTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-007: Crear y editar rol usan la notificación compartida

`CreateRole::handleRecordCreation()` y `EditRole::handleRecordUpdate()` usan el concern de TASK-006 para la rama que notifica (la rama que convierte el fallo en error del campo `label` se queda igual). Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-001, TASK-006

**Acceptance Criteria:**

1. Ninguna de las dos páginas construye a mano la notificación de `AdminAuthorizationException`.
2. Una etiqueta repetida sigue mostrándose como error del campo, no como notificación; cualquier otro rechazo del servicio sigue notificando y no guarda.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/CreateRole.php`
- `app/Modules/Admin/Filament/Resources/Roles/Pages/EditRole.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/CreateRoleTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/EditRoleTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/SensitivePermissionAcknowledgementTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-008: Helper de pruebas para contar consultas

Crear `app/Modules/Admin/Tests/Support/QueryLog.php` con `QueryLog::count(Closure $render, ?Closure $keepQuery = null): int` (vacía y activa el log, ejecuta, filtra opcionalmente por texto de la consulta, desactiva el log). Reemplaza `countRolesListQueries()` en `ListRolesTest` y `countPermissionMatrixQueries()` en `RolePermissionMatrixTest`. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Las dos funciones globales desaparecen y las pruebas afirman lo mismo con `QueryLog::count()`.
2. El log de consultas queda desactivado aunque el render lance una excepción (`try/finally`).

**writeScope:**

- `app/Modules/Admin/Tests/Support/QueryLog.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-009: Helper de pruebas para registrar actividad de roles

Crear `app/Modules/Admin/Tests/Support/RoleActivity.php` con `grant(Role, User $holder, ?User $administrator = null, ?string $at = null)`, `change(Role, ActivityLogAdminEvent, ?User $causer = null, ?string $at = null, array $properties = [])` y `record(ActivityLogAdminEvent, ?Model $subject, array $properties = [])`, sobre `ActivityLogFactory`. Reemplaza `recordGrantOf()` en `RoleGrantActivityTest` y `recordRoleChange()` / `recordRoleGrantToUser()` en `RoleChangeActivityTest`. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Las tres funciones globales desaparecen; las propiedades guardadas (`role`, fecha, causante) son las mismas que antes.
2. Sin administrador, `grant()` crea un causante nuevo (como hacían `recordRoleHolderGrant` y `recordRoleGrantToUser`), y sin fecha usa la fecha actual.

**writeScope:**

- `app/Modules/Admin/Tests/Support/RoleActivity.php`
- `app/Modules/Admin/Tests/Models/RoleGrantActivityTest.php`
- `app/Modules/Admin/Tests/Models/RoleChangeActivityTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Models/RoleGrantActivityTest.php app/Modules/Admin/Tests/Models/RoleChangeActivityTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-010: Contexto de pruebas: actuar como gestor o lector de roles y claves visibles

Añadir a `AdminFilamentTestContext` `actingAsRolesManager()` (Admin + AdminRolesManager) y `actingAsRolesReader(AdminRole ...$extraRoles)` (Admin + AdminRolesReader + extras), y a `FilamentTableRecords` `shownKeys(Testable, string $modelClass)`, el atajo que repiten `visibleRoleHistoryKeys`, `filteredRoleHolderKeys` y `shownHolderKeys`. Primer consumidor: `CreateRoleTest` (quita `actingAsRoleCreator()`). Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `actingAsRoleCreator()` desaparece y `CreateRoleTest` pasa igual.
2. `shownKeys()` devuelve las claves en el orden de la tabla y una lista vacía cuando la tabla no muestra registros.

**writeScope:**

- `app/Modules/Admin/Tests/Support/AdminFilamentTestContext.php`
- `app/Modules/Admin/Tests/Support/FilamentTableRecords.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/CreateRoleTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/CreateRoleTest.php app/Modules/Admin/Tests/Filament/Resources/ActivityLogs < /dev/null && composer phpstan`

**Review:** none

### TASK-011: Pruebas de editar y ciclo de vida sin actingAs propios

`EditRoleTest` (quita `actingAsRoleEditor()`) y `ViewRoleLifecycleActionsTest` (quita `actingAsLifecycleRolesManager()`) usan `AdminFilamentTestContext::actingAsRolesManager()`. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-010

**Acceptance Criteria:**

1. Las dos funciones globales desaparecen y ninguna afirmación cambia.
2. Las pruebas de rechazo (sin permiso, rol bloqueado) siguen en verde.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/EditRoleTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleLifecycleActionsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/EditRoleTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleLifecycleActionsTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-012: Pruebas del historial con los helpers compartidos

`RoleActivityLogsRelationManagerTest` usa `RoleActivity`, `QueryLog`, `shownKeys()` y `actingAsRolesReader(AdminRole::AdminActivityReader)` (quita `recordRoleGrant`, `recordRolePermissionChange`, `visibleRoleHistoryKeys`, `countRoleHistoryQueries`, `actingAsRoleHistoryReader`). `DescribesRoleActivityTest` (`roleActivity`) y `RoleActivityLogQueryTest` (`recordRoleActivity`) usan `RoleActivity::record()`; `DescribesRoleActivityTest` carga `subject` después. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-009, TASK-010

**Acceptance Criteria:**

1. Ninguno de los tres archivos declara funciones globales de actividad, claves o consultas.
2. La prueba de consultas constantes del historial conserva su filtro de tablas de permisos y actividad.
3. Las descripciones de un sujeto eliminado siguen probándose (la actividad sin sujeto sigue siendo posible).

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleActivityLogsRelationManagerTest.php`
- `app/Modules/Admin/Tests/Filament/Concerns/DescribesRoleActivityTest.php`
- `app/Modules/Admin/Tests/Models/RoleActivityLogQueryTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleActivityLogsRelationManagerTest.php app/Modules/Admin/Tests/Filament/Concerns/DescribesRoleActivityTest.php app/Modules/Admin/Tests/Models/RoleActivityLogQueryTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-013: Pruebas de la pestaña de usuarios con los helpers compartidos

`RoleUsersRelationManagerTest` (`grantRoleWithActivity`, `countRoleUsersQueries`), `RoleUsersFiltersTest` (`roleHolder`, `recordRoleHolderGrant`, `filteredRoleHolderKeys`) y `RoleUsersBulkRemoveTest` (`bulkRoleHolder`) usan `createUserHoldingRoles()` de `tests/Pest.php`, `RoleActivity::grant()`, `QueryLog` y `shownKeys()`. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-009, TASK-010

**Acceptance Criteria:**

1. Las seis funciones globales desaparecen y ninguna afirmación cambia.
2. La prueba de consultas constantes de la pestaña conserva su filtro (permisos y `activity_log`).
3. Los filtros por fecha de asignación siguen probando los extremos del rango (fechas incluidas).

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersFiltersTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersBulkRemoveTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersFiltersTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersBulkRemoveTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-014: Estado de factory para roles con permisos

Añadir a `RoleFactory` el estado `withPermissions(AdminPermission ...$adminPermissions)` (vía `afterCreating`). Lo usan `RolesTableFiltersTest` (quita `customRoleWith`; `recordFilteredRoleChange` pasa a `RoleActivity::change()`) y `PermissionHoldersUsersTest` (quita `createBackupsDownloadRole`; `countPermissionHoldersQueries` y `shownHolderKeys` pasan a `QueryLog` y `shownKeys()`). Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-009, TASK-010

**Acceptance Criteria:**

1. `RoleFactory::new()->withPermissions(...)->createOne()` deja el rol con exactamente esos permisos del guard `admin`; sin argumentos no le da ninguno.
2. Las cuatro funciones globales sustituidas desaparecen; las que expresan algo propio del archivo (`filteredRoleNames`, `holderKeysByName`) se quedan.

**writeScope:**

- `app/Modules/Admin/Database/Factories/RoleFactory.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Tables/RolesTableFiltersTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/PermissionHoldersUsersTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Tables/RolesTableFiltersTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/PermissionHoldersUsersTest.php app/Modules/Admin/Tests/Models/RoleTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-015: Pruebas de columnas y policy con los helpers compartidos

`RolesTableColumnsTest` usa `RoleActivity::change()` y `QueryLog` (quita `recordListedRoleActivity` y `countCustomRolesListQueries`; `changedCustomRole` se queda como escenario propio). `RolePolicyTest` usa `adminCatalogRole()` y `createUserHoldingRoles()` de `tests/Pest.php` (quita `catalogRole` y `createUserWithAdminRole`). Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-008, TASK-009

**Acceptance Criteria:**

1. Las cuatro funciones globales desaparecen y ninguna afirmación cambia.
2. La policy sigue probando el rechazo para un rol bloqueado y para un usuario sin permisos.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Tables/RolesTableColumnsTest.php`
- `app/Modules/Admin/Tests/Policies/RolePolicyTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Tables/RolesTableColumnsTest.php app/Modules/Admin/Tests/Policies/RolePolicyTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-016: Traducciones de roles sin claves muertas

Confirmar con búsqueda (incluidas las claves armadas por concatenación) y quitar de `lang/en/admin/roles.php` y `lang/es/admin/roles.php` las claves sin uso. Candidatas del inventario: `types.filter`, `locked.badge`, `table.not_editable_tooltip`. Revisar también textos idénticos bajo dos claves del mismo grupo y unificarlos cuando signifiquen lo mismo. Además, en cada archivo del writeScope: docblocks de clases y métodos con 3 líneas de prosa como máximo (`.ai/rules/backend.md`), sin repetir lo que ya dicen el nombre y los tipos; los `@param`/`@return` con tipos se conservan.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007

**Acceptance Criteria:**

1. Cada clave quitada no tiene ninguna referencia literal ni dinámica en `app/` ni `resources/`.
2. Los dos idiomas siguen con las mismas claves (paridad) y las pruebas de textos fijos pasan.

**writeScope:**

- `lang/en/admin/roles.php`
- `lang/es/admin/roles.php`
- `app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations tests/Feature/Translation < /dev/null && composer phpstan`

**Review:** none

### TASK-017: Documentar la estructura final del módulo de roles

Actualizar `docs/architecture/modules/admin.md`: el scope `Role::admin()` y `Role::grantingPermission()` como dueños de los filtros, los concerns nuevos (`ReadsAggregateCounts`, `NotifiesAdminAuthorizationFailure`), la autorización por `RoleResource::canViewAny()`/`canView()`, los helpers de `Tests/Support/` (`QueryLog`, `RoleActivity`, `AdminFilamentTestContext`, `FilamentTableRecords::shownKeys`) y el estado `RoleFactory::withPermissions()`. Anotar la decisión de dejar las acciones de un solo uso dentro de su página o relation manager (las carpetas ya siguen Filament 5: `Pages/`, `RelationManagers/`, `Schemas/`, `Tables/`, `Widgets/`).

**Type:** docs · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-026, TASK-027, TASK-028

**Acceptance Criteria:**

1. Cada helper o concern citado existe con ese nombre en el código.
2. No quedan referencias en la doc a funciones quitadas (`summaryCount`, `grantingAdminRole`, `notifyFailure`…).

**writeScope:**

- `docs/architecture/modules/admin.md`

**validateCommand:** `! grep -nE 'summaryCount|grantingAdminRole|notifyFailure|countIn\(' docs/architecture/modules/admin.md`

**Review:** none

### TASK-018: Gates completos, inventario final y prueba en el navegador

Pint, Rector (limpiar caché, dry, aplicar; todo el proyecto), PHPStan, `composer test` y `composer doctor`. Volver a buscar funciones globales en las pruebas del módulo de Roles para confirmar que no queda ninguna duplicada. Recorrer en el navegador listado, matriz, detalle (pestañas de usuarios e historial), crear/editar y «Quién puede hacer esto». El inventario incluye contar los docblocks de Roles con más de 3 líneas de prosa (debe dar 0).

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-017

**Acceptance Criteria:**

1. Todos los gates en verde y la suite completa pasa sin cambiar ninguna afirmación respecto a `main`.
2. El inventario final no encuentra funciones de prueba con el mismo cuerpo en los archivos de Roles ni docblocks con más de 3 líneas de prosa.
3. Las páginas de roles se ven y se comportan igual que antes en el navegador.

**writeScope:**

- Ninguno (solo verificación)

**validateCommand:** `composer test && composer doctor`

**Review:** none

### TASK-019: Auditoría de convenciones de Filament 5 en el recurso de roles

Revisar con `search-docs` (Filament 5) todo el recurso: páginas (`ListRecords`, `ViewRecord`, `CreateRecord`/wizard, `EditRecord` y las dos páginas propias), acciones de cabecera y de tabla (`->authorize()` frente a `->visible()`, `->requiresConfirmation()`, modales), `Schemas/RoleInfolist`, `Tables/`, relation managers (`$relatedResource`, `canViewForRecord`), widgets (`$isLazy`, `$sort`, `columnSpan`), iconos con el enum `Heroicon` y las vistas Blade con componentes `x-filament::`. No escribe código: cada mejora encontrada se agrega a este plan como una tarea nueva (TASK-025 en adelante), con su writeScope de 3 archivos como máximo y dependencias para no chocar con TASK-001 a TASK-007. Resultado (2026-11-18): tres mejoras (TASK-025 a TASK-027) y decisiones sin tarea: los esquemas con nombre de las páginas propias (`compareForm`, `permissionForm`) siguen el mecanismo de Filament 5; el `ViewAction` del historial que enlaza al registro de actividad es correcto; «Abrir matriz» no necesita autorización propia (la matriz pide el mismo `viewAny` que el listado); `RoleResource::getEloquentQuery()` parte de `parent::getEloquentQuery()` como pide Filament; las acciones de un solo uso se quedan en su página. Fuera de alcance (cambio visible): la búsqueda global del recurso busca por `name` (identificador guardado) y no por la etiqueta mostrada.

**Type:** research · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Cada hallazgo cita la página de la documentación de Filament 5 y el archivo y la línea donde se aparta de ella.
2. Si un patrón del proyecto se aparta de Filament a propósito (por ejemplo, la autorización solo en la policy), queda anotado como decisión y no como tarea.
3. El JSON y el markdown del plan siguen describiendo el mismo grafo, sin ciclos.

**writeScope:**

- `.ulpi/plans/soc-81-roles-module-cleanup/soc-81-roles-module-cleanup.json`
- `.ulpi/plans/soc-81-roles-module-cleanup/soc-81-roles-module-cleanup.md`

**validateCommand:** `python3 -c "import json;json.load(open('.ulpi/plans/soc-81-roles-module-cleanup/soc-81-roles-module-cleanup.json'))"`

**Review:** none

### TASK-020: Comentarios de las tablas y el infolist de roles

Recortar los comentarios de estos archivos, que ninguna otra tarea toca: docblocks de clases, métodos y helpers con 3 líneas de prosa como máximo, quitando los que solo repiten el nombre o los tipos y conservando las anotaciones de tipo (`@param`, `@return`, `@var`, formas de array) que PHPStan necesita.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Ningún docblock de estos archivos supera 3 líneas de prosa (lo comprueba el inventario de TASK-018).
2. No se pierde ninguna anotación de tipo: PHPStan (nivel max, 100 % de cobertura de tipos) sigue en verde.
3. El código no cambia: solo comentarios, y las pruebas afectadas pasan sin tocarse.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Tables/RolesTable.php`
- `app/Modules/Admin/Filament/Resources/Roles/Tables/RoleActivityLogsTable.php`
- `app/Modules/Admin/Filament/Resources/Roles/Schemas/RoleInfolist.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Tables app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleActivityLogsRelationManagerTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-021: Comentarios de los concerns de roles y de la vista «Quién puede hacer esto»

Recortar los comentarios de estos archivos, que ninguna otra tarea toca: docblocks de clases, métodos y helpers con 3 líneas de prosa como máximo, quitando los que solo repiten el nombre o los tipos y conservando las anotaciones de tipo (`@param`, `@return`, `@var`, formas de array) que PHPStan necesita. En la vista Blade, los comentarios `{{-- --}}` se quedan solo donde explican algo que el marcado no dice.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Ningún docblock de estos archivos supera 3 líneas de prosa (lo comprueba el inventario de TASK-018).
2. No se pierde ninguna anotación de tipo: PHPStan (nivel max, 100 % de cobertura de tipos) sigue en verde.
3. El código no cambia: solo comentarios, y las pruebas afectadas pasan sin tocarse.

**writeScope:**

- `app/Modules/Admin/Filament/Concerns/EditsRolePermissions.php`
- `app/Modules/Admin/Filament/Concerns/DescribesRoleActivity.php`
- `app/Modules/Admin/UI/Views/filament/roles/permission-holders.blade.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Concerns app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/PermissionHoldersTest.php app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-022: Comentarios de las pruebas de detalle, permisos sensibles y resumen

Recortar los comentarios de estos archivos, que ninguna otra tarea toca: docblocks de clases, métodos y helpers con 3 líneas de prosa como máximo, quitando los que solo repiten el nombre o los tipos y conservando las anotaciones de tipo (`@param`, `@return`, `@var`, formas de array) que PHPStan necesita.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Ningún docblock de estos archivos supera 3 líneas de prosa (lo comprueba el inventario de TASK-018).
2. No se pierde ninguna anotación de tipo: PHPStan (nivel max, 100 % de cobertura de tipos) sigue en verde.
3. El código no cambia: solo comentarios, y las pruebas afectadas pasan sin tocarse.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/SensitivePermissionAcknowledgementTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersSummaryTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/SensitivePermissionAcknowledgementTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersSummaryTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-023: Comentarios de las pruebas de la página, el modelo y el editor de permisos

Recortar los comentarios de estos archivos, que ninguna otra tarea toca: docblocks de clases, métodos y helpers con 3 líneas de prosa como máximo, quitando los que solo repiten el nombre o los tipos y conservando las anotaciones de tipo (`@param`, `@return`, `@var`, formas de array) que PHPStan necesita.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-004

**Acceptance Criteria:**

1. Ningún docblock de estos archivos supera 3 líneas de prosa (lo comprueba el inventario de TASK-018).
2. No se pierde ninguna anotación de tipo: PHPStan (nivel max, 100 % de cobertura de tipos) sigue en verde.
3. El código no cambia: solo comentarios, y las pruebas afectadas pasan sin tocarse.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/PermissionHoldersTest.php`
- `app/Modules/Admin/Tests/Models/RoleTest.php`
- `app/Modules/Admin/Tests/Filament/Concerns/EditsRolePermissionsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/PermissionHoldersTest.php app/Modules/Admin/Tests/Models/RoleTest.php app/Modules/Admin/Tests/Filament/Concerns/EditsRolePermissionsTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-024: Comentarios de las pruebas de los widgets de roles

Recortar los comentarios de estos archivos, que ninguna otra tarea toca: docblocks de clases, métodos y helpers con 3 líneas de prosa como máximo, quitando los que solo repiten el nombre o los tipos y conservando las anotaciones de tipo (`@param`, `@return`, `@var`, formas de array) que PHPStan necesita.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Ningún docblock de estos archivos supera 3 líneas de prosa (lo comprueba el inventario de TASK-018).
2. No se pierde ninguna anotación de tipo: PHPStan (nivel max, 100 % de cobertura de tipos) sigue en verde.
3. El código no cambia: solo comentarios, y las pruebas afectadas pasan sin tocarse.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/RolesOverviewTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/RoleTypesOverviewTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/UsersPerRoleChartTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets < /dev/null && composer phpstan`

**Review:** none

### TASK-025: Editar desde el detalle con `EditAction`

Hallazgo de la auditoría de TASK-019. `ViewRole::getHeaderActions()` reimplementa a mano la acción de editar (`Action::make('edit')->authorize('update')->url(...)`). Filament 5 la trae hecha: `EditAction::make()` en una página `ViewRecord` enlaza a la página de edición y consulta `RolePolicy::update` (docs: Actions › Overview › Authorization using a policy; Resources › Viewing records). Se conservan el nombre `edit`, la etiqueta `admin/roles.actions.edit.label` y el icono.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-007

**Acceptance Criteria:**

1. La acción `edit` es un `EditAction` y enlaza a `/admin/roles/{record}/edit`.
2. Sigue oculta para el rol bloqueado `admin` y para quien no tiene `admin.roles.update` (pruebas existentes de `ViewRoleLifecycleActionsTest` sin cambios).

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/ViewRole.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleLifecycleActionsTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-026: Crear desde el listado con `CreateAction`

Hallazgo de la auditoría de TASK-019. `ListRoles::getHeaderActions()` reimplementa la acción de crear (`Action::make('createRole')->authorize('create')->url(...)`), que es lo que hace `CreateAction::make()` en una página `ListRecords` con página de creación. Se conservan la etiqueta y el icono. El nombre de la acción pasa de `createRole` a `create`, así que `ListRolesTest` cambia ese nombre en sus afirmaciones: necesita la aprobación del usuario antes de empezar.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-008

**Acceptance Criteria:**

1. La acción de crear es un `CreateAction` que enlaza a `/admin/roles/create`.
2. Sigue oculta para quien no tiene `admin.roles.create`; la prueba solo cambia el nombre `createRole` por `create`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/ListRoles.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-027: Acciones de la pestaña de usuarios autorizadas por la policy

Hallazgo de la auditoría de TASK-019. En `RoleUsersRelationManager`, «Agregar usuarios» y «Quitar el rol» (masiva) deciden su visibilidad con `administrator()->can(AdminPermission::AdminUsersRoles->value)`: consultan el permiso de Spatie directamente, fuera de la policy, contra la invariante del proyecto («la autorización vive en la policy»). Añadir a `UserPolicy` una habilidad sin usuario destino (p. ej. `manageAnyRoles(User $authenticatedUser): bool`, que reutiliza `manageRoles()`) y usar `->authorize('manageAnyRoles')` en las dos acciones, como manda Filament para acciones propias. `UserPolicy` vive en `app/User/` (arquitectura antigua): es el dueño legítimo de esa regla.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006

**Acceptance Criteria:**

1. Ninguna acción de la pestaña consulta un permiso de Spatie directamente; todas pasan por `UserPolicy`.
2. Quien no tiene `admin.users.roles` sigue sin ver «Agregar usuarios» ni la acción masiva; quien lo tiene sigue sin poder seleccionarse a sí mismo.
3. La nueva habilidad tiene su prueba en `UserPolicyTest` (permitida y denegada).

**writeScope:**

- `app/User/Policies/UserPolicy.php`
- `app/Modules/Admin/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManager.php`
- `app/Modules/Admin/Tests/Policies/UserPolicyTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers app/Modules/Admin/Tests/Policies/UserPolicyTest.php < /dev/null && composer phpstan`

**Review:** none

### TASK-028: Un editor de prueba en `Tests/Support` para `EditsRolePermissions`

Hallazgo de TASK-023. `EditsRolePermissionsTest` tiene 8 funciones globales (`editorPermissionCheckboxLists()`, `editorPermissionsByArea()`, `editorSelectedPermissions()`, `editorAppliedDependencies()`, `editorChangeSummary()`, `editorSelectionOverview()`, `editorSensitivePermissionsText()` y la parte de `editorPermissionDescriptions()` que lee el trait) que crean cada una su propia clase anónima con `use EditsRolePermissions` solo para exponer un método `protected static`, con cada tipo de retorno escrito dos veces. Una clase anónima devuelta por una función perdería su tipo para PHPStan, y una clase con nombre no puede vivir en el archivo de pruebas (rompe el mapa PSR-4), así que va en `Tests/Support/RolePermissionsEditor`, que usa el trait y hace públicos sus métodos con alias (`as public`), sin envoltorios.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** Ninguna

**Acceptance Criteria:**

1. No queda ninguna clase anónima con `use EditsRolePermissions` en las pruebas; desaparecen las funciones envoltorio repetidas.
2. Ninguna afirmación cambia y todas las pruebas del archivo siguen en verde, incluidas las de dependencias entre áreas y permisos fuera del catálogo.

**writeScope:**

- `app/Modules/Admin/Tests/Support/RolePermissionsEditor.php`
- `app/Modules/Admin/Tests/Filament/Concerns/EditsRolePermissionsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Concerns/EditsRolePermissionsTest.php < /dev/null && composer phpstan`

**Review:** none

## Failure Modes

- `RoleResource::canViewAny()` con `shouldSkipAuthorization()` activo permitiría todo: el recurso no lo activa; las pruebas de 403 existentes lo detectarían.
- El scope `admin()` dentro de un `whereHas('roles')` sin calificar la columna: `guard_name` sería ambiguo con `model_has_roles`; calificar `roles.guard_name` donde haga falta (hoy `PermissionHolders` ya lo hace).
- Un helper compartido que cambie la fecha o el causante por defecto: los filtros por fecha y las columnas de causante fallarían; los criterios de TASK-009 fijan los valores por defecto.
- Quitar una clave de traducción usada por concatenación (`'admin/roles.'.$key`): TASK-016 exige buscar también referencias dinámicas.
- PHPStan con scopes mágicos sobre `Builder<Model>` en `whereHas`: si el tipo no los reconoce, el scope se llama sobre el builder tipado `Builder<Role>` o se mantiene `getQuery()` con un comentario.

## Ship Cut

Mínimo entregable: TASK-001 a TASK-007 (producción sin duplicados) más TASK-018. Las tareas de pruebas (TASK-008 a TASK-015) son independientes por archivo y pueden cortarse en cualquier punto sin romper nada; TASK-016 y TASK-017 cierran la issue.

## Test Coverage Map

- **Criterio 1: sin cambios de comportamiento:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-018
- **Criterio 2: helpers de prueba sin duplicar:** TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-018
- **Criterio 3: gates y documentación:** TASK-016, TASK-017, TASK-018
- **Comentarios dentro del límite del proyecto:** TASK-001, TASK-004, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-018
- **Convenciones de Filament 5:** TASK-019, TASK-005

## Execution Summary

**Tareas:** 28

**Capas:**

1. TASK-001, TASK-002, TASK-005, TASK-008, TASK-009, TASK-010, TASK-019, TASK-020, TASK-021, TASK-022, TASK-024, TASK-028
2. TASK-003, TASK-006, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-026
3. TASK-004, TASK-007, TASK-027
4. TASK-016, TASK-023, TASK-025
5. TASK-017
6. TASK-018

**Ruta crítica:** TASK-002 → TASK-003 → TASK-004 → TASK-016 → TASK-017 → TASK-018

## Dependency Map

```
TASK-001 ← —
TASK-002 ← —
TASK-003 ← TASK-002
TASK-004 ← TASK-003
TASK-005 ← —
TASK-006 ← TASK-005
TASK-007 ← TASK-001, TASK-006
TASK-008 ← —
TASK-009 ← —
TASK-010 ← —
TASK-011 ← TASK-010
TASK-012 ← TASK-008, TASK-009, TASK-010
TASK-013 ← TASK-008, TASK-009, TASK-010
TASK-014 ← TASK-008, TASK-009, TASK-010
TASK-015 ← TASK-008, TASK-009
TASK-016 ← TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007
TASK-017 ← TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-026, TASK-027, TASK-028
TASK-018 ← TASK-017
TASK-019 ← —
TASK-020 ← —
TASK-021 ← —
TASK-022 ← —
TASK-023 ← TASK-004
TASK-024 ← —
TASK-025 ← TASK-007
TASK-026 ← TASK-008
TASK-027 ← TASK-006
TASK-028 ← —
```
