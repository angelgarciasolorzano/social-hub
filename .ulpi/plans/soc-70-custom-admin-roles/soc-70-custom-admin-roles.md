# Plan: SOC-70: Crear y gestionar roles personalizados de administración desde el panel

## Overview

Pasar de un catálogo cerrado de roles (SOC-69) a roles como datos: un modelo propio que extiende el `Role` de Spatie, con roles de sistema protegidos (los de `AdminRole`, con `admin` totalmente bloqueado) y roles personalizados que el panel crea, edita, duplica y elimina, asignándoles permisos del catálogo `AdminPermission`, que sigue siendo código. Se suman dependencias entre permisos, un resumen de cambios antes de guardar, un widget de tipos de rol, gestión de usuarios desde el detalle del rol y roles de ejemplo sembrados.

**Linear:** [SOC-70](https://linear.app/social-hub-ang/issue/SOC-70/feature-create-and-manage-custom-admin-roles-from-the-filament-panel) · **Modo:** EXPANSION · **Revisión predeterminada:** Claude

## Scope Challenge

EXPANSION con revisión de Claude. Decisiones del usuario (2026-11-03/04): `admin` bloqueado y los demás roles de sistema con permisos editables pero sin borrar ni renombrar; borrar un rol con usuarios se bloquea; nombre visible y descripción en un solo idioma (columnas `label` y `description`); identificador `custom-` + slug con sufijo numérico si existe; permisos nuevos `admin.roles.create` (crear y duplicar) y `admin.roles.delete`, con `admin.roles.update` para editar nombre y descripción; extras: dependencias de permisos, roles de ejemplo sembrados, resumen antes de guardar, widget de tipos de rol y lo que mejore el módulo (se agrega: agregar y quitar usuarios desde el detalle del rol). Hallazgos: la tabla `roles` es una migración publicada en el proyecto (`database/migrations/2026_10_10_150651_create_permission_tables.php`) y Spatie admite un modelo propio en `config/permission.php`; unos 25 archivos de producción usan `AdminRole::tryFrom()` o `AdminRole::cases()` para nombrar o listar roles, y `AdminAuthorizationService` recibe `AdminRole` en todas sus operaciones, así que el cambio central es pasar del enum al modelo `Role`.

## Prerequisites

- SOC-69 mergeado: `AdminAuthorizationService`, `RolePolicy`, `RoleResource`, `ConfirmsCurrentPassword` y sus pruebas.
- `AdminServiceProvider` ya carga `app/Modules/Admin/Database/Migrations/` con `loadMigrationsFrom`.
- El proyecto nunca se ha desplegado: se puede cambiar el esquema de `roles` sin migración de datos.
- Morph `role` registrado en `AppServiceProvider::ROLE_MORPH_NAME`.

## Non-Goals

- Crear, renombrar o eliminar permisos desde el panel (siguen siendo código).
- Reasignar usuarios al eliminar un rol.
- Traducir nombres y descripciones de roles personalizados (un solo idioma, como el contenido de los anuncios).
- Permisos asignados directamente a usuarios, super-admin con `Gate::before`, Teams de Spatie y roles del guard `web`.
- Cambiar `admin:bootstrap-admin`.

## Contracts

- `App\Modules\Admin\Models\Role` extiende `Spatie\Permission\Models\Role`, está configurado en `config/permission.php` (`models.role`) y en el morph map (`role`), y lleva `#[UsePolicy(RolePolicy::class)]`; nadie en `app/` importa ya el `Role` de Spatie.
- `roles.is_system` es la única fuente de verdad del tipo; `AdminRole` solo lista los roles de sistema y el seeder los marca. `Role::isLocked()` es verdadero solo para `admin`.
- `Role::displayName()` / `displayDescription()`: un rol de sistema lee `lang/` a través de `AdminRole`; uno personalizado devuelve `label` / `description` tal como se guardaron; sin `label`, cae al `name`. Toda la UI usa estos métodos, nunca `AdminRole::tryFrom()`.
- El `name` de un rol personalizado es `custom-` + slug del `label`, con sufijo `-2`, `-3`… si ya existe, y no cambia nunca.
- `AdminPermission::requires()` define las dependencias; un conjunto de permisos es válido solo si contiene los requisitos de cada uno. El servicio rechaza conjuntos inválidos (`permission_dependency_missing`) en todos los caminos; el panel los mantiene válidos en vivo.
- `AdminAuthorizationService` sigue siendo el único escritor: recibe `Role` en lugar de `AdminRole`, bloquea primero el rol `admin` (orden de SOC-69), rechaza tocar los permisos de `admin` (`role_locked`), borrar o renombrar un rol de sistema (`system_role_protected`), borrar un rol con usuarios (`role_has_users`) y un `label` repetido (`role_label_taken`), y audita `admin.role_created|updated|duplicated|deleted` solo con ids, nombres técnicos y nombres de permisos, nunca `label` ni `description`.
- Los comandos aceptan cualquier rol existente del guard `admin` y aplican las mismas reglas que el panel; sus mensajes para los fallos nuevos se definen en `InteractsWithAdminCatalog`.

## Existing Code Leverage

- `AdminAuthorizationService` (SOC-69): bloqueos, último `admin.access`, auto-bloqueo, auditoría y caché; se extiende, no se reescribe.
- `ViewRole` 'Editar permisos' y `ViewUser` 'Gestionar roles': base del concern compartido de casillas por área.
- `ConfirmsCurrentPassword` para crear, duplicar, eliminar y agregar o quitar usuarios.
- `AnnouncementService::duplicate()` y su evento `admin.announcement_duplicated`: patrón de duplicar con `source_*_id`.
- `AdminInvitationFactory` como modelo de factory del módulo; `AccountStatusChange` como modelo con `@property` y constantes de longitud.
- `ActivityLogEventCatalog` ya traduce eventos desde `admin/activity-log.events.admin.*`.

## Tasks

### TASK-001: Modelo Role propio, migración de columnas y factory

Crear la migración `2026_11_04_000000_add_presentation_columns_to_roles_table.php` en `app/Modules/Admin/Database/Migrations/` (ya cargada por `AdminServiceProvider`): `is_system` boolean default false e indexado, `label` string nullable (`Role::LABEL_MAX_LENGTH`, 80) y `description` string nullable (`DESCRIPTION_MAX_LENGTH`, 255), con `down()`. Crear `App\Modules\Admin\Models\Role extends Spatie\Permission\Models\Role` con `@property` de las columnas, cast `is_system` boolean, constantes de longitud y `CUSTOM_NAME_PREFIX = 'custom-'`, `#[UsePolicy(RolePolicy::class)]`, `#[UseFactory(RoleFactory::class)]`, scopes `#[Scope] admin()` (guard `admin`), `system()` y `custom()`, y los métodos `isSystem()`, `isLocked()` (`name === AdminRole::Admin->value`), `adminRole(): ?AdminRole`, `displayName()` y `displayDescription()` según el contrato. Crear `RoleFactory` (guard `admin`, rol personalizado con `label` y `name` `custom-…`; estados `system(AdminRole)` y `withoutLabel()`). El cableado en config y morph map es de TASK-002.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. La migración sube y baja sin errores, y `roles` gana las tres columnas con los valores por defecto indicados.
2. `Role::factory()->system(AdminRole::AdminPostsReader)->create()->displayName()` devuelve la etiqueta traducida del catálogo; uno personalizado devuelve su `label`; uno sin `label` devuelve su `name`.
3. `isLocked()` solo es verdadero para el rol `admin` del guard `admin`.

**writeScope:**

- `app/Modules/Admin/Database/Migrations/2026_11_04_000000_add_presentation_columns_to_roles_table.php` (nuevo)
- `app/Modules/Admin/Models/Role.php` (nuevo)
- `app/Modules/Admin/Database/Factories/RoleFactory.php` (nuevo)

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Models/Role.php app/Modules/Admin/Database/Factories/RoleFactory.php app/Modules/Admin/Database/Migrations`

**Review:** claude

### TASK-002: Cablear el modelo Role en Spatie, el morph map y la política

En `config/permission.php`, `models.role` pasa a `App\Modules\Admin\Models\Role`. En `AppServiceProvider::registerMorphMap()`, `role` apunta al modelo nuevo. `Gate::policy(Role::class, RolePolicy::class)` de `AdminServiceProvider` se mantiene: el código que todavía usa el `Role` de Spatie (UI, widgets, pruebas) pierde la política sin él y Filament lo permitiría todo; se quita en TASK-030. Corrección posterior: como `role` del morph map solo admite el modelo nuevo, una instancia del `Role` de Spatie que llegue al registro de actividad falla con 'No morph map defined'; por eso todos los imports de `Spatie\Permission\Models\Role` en `app/` (servicio, seeders, comandos, recursos de Filament y pruebas) pasan al modelo nuevo, salvo `AdminServiceProvider` y `RolePolicy`, cuyos tipos aceptan ambas clases.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. `app(PermissionRegistrar::class)->getRoleClass()` es el modelo nuevo y `Relation::getMorphedModel('role')` también.
2. `Gate::getPolicyFor()` devuelve `RolePolicy` tanto para el modelo nuevo como para el de Spatie, y la suite Admin sigue pasando.

**writeScope:**

- `config/permission.php`
- `app/Providers/AppServiceProvider.php`

**validateCommand:** `rtk php -d memory_limit=2G artisan test --parallel --compact --testsuite=Admin --passthru-php="'-d' 'memory_limit=1G'"`

**Review:** claude

### TASK-003: Pruebas del modelo Role y su cableado

Crear `Tests/Models/RoleTest.php`: columnas y casts, scopes `admin`/`system`/`custom`, `isLocked()`, `displayName()`/`displayDescription()` en ambos idiomas para un rol de sistema, fallback al `name`, que `assignRole()` y `hasRole()` de Spatie funcionan con el modelo nuevo, y que el morph `role` resuelve al modelo nuevo.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002

**Acceptance Criteria:**

1. Cubre los tres criterios de TASK-001 y los dos de TASK-002.
2. Un rol del guard `web` no aparece en el scope `admin()`.

**writeScope:**

- `app/Modules/Admin/Tests/Models/RoleTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Models/RoleTest.php`

**Review:** claude

### TASK-004: Permisos nuevos y dependencias entre permisos

Agregar a `AdminPermission` `AdminRolesCreate = 'admin.roles.create'` y `AdminRolesDelete = 'admin.roles.delete'`. Agregar `requires(): list<AdminPermission>` (`*.view` requiere `*.viewAny`; las escrituras y acciones de un área requieren su `view` y `viewAny`, salvo `admin.users.create`, que solo requiere `admin.users.viewAny`; `admin.users.roles` requiere ver usuarios; `admin.access` y `admin.dashboard.view` no requieren nada), `static withRequirements(list): list` (cierre transitivo en orden de catálogo), `static missingRequirements(list): list` y `dependents(): list` (inverso, para desmarcar en vivo). Actualizar la lista fija del catálogo en `AdminAnnouncementAuthorizationEnumsTest` y probar las dependencias en `AdminRoleManagementAuthorizationEnumsTest`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Los bundles sembrados de todos los roles de sistema cumplen sus dependencias (`missingRequirements()` vacío para cada uno).
2. `withRequirements([AdminUsersSuspend])` devuelve `viewAny`, `view` y `suspend` de users, y `missingRequirements([AdminUsersView])` devuelve `[AdminUsersViewAny]`.
3. Ningún permiso se requiere a sí mismo y no hay ciclos (la prueba recorre todos los casos).

**writeScope:**

- `app/Modules/Admin/Enums/AdminPermission.php`
- `app/Modules/Admin/Tests/Enums/AdminRoleManagementAuthorizationEnumsTest.php`
- `app/Modules/Admin/Tests/Enums/AdminAnnouncementAuthorizationEnumsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Enums`

**Review:** claude

### TASK-005: Fallos y eventos de auditoría nuevos

Agregar a `AdminAuthorizationFailure` `RoleLocked` (`role_locked`), `SystemRoleProtected` (`system_role_protected`), `RoleHasUsers` (`role_has_users`), `RoleLabelTaken` (`role_label_taken`) y `PermissionDependencyMissing` (`permission_dependency_missing`). Agregar a `ActivityLogAdminEvent` `RoleCreated` (`admin.role_created`), `RoleUpdated` (`admin.role_updated`), `RoleDuplicated` (`admin.role_duplicated`) y `RoleDeleted` (`admin.role_deleted`). Sus textos los agregan TASK-006 y TASK-007; los graba el servicio en TASK-011.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Los valores son exactamente los indicados y `ActivityLogAdminEvent::RoleCreated->logName()` es el log `admin`.
2. `ActivityLogEventTest` sigue pasando (el enum ya está registrado en `ActivityLogEvent::ENUMS`).

**writeScope:**

- `app/Modules/Admin/Enums/AdminAuthorizationFailure.php`
- `app/Modules/ActivityLog/Enums/Events/ActivityLogAdminEvent.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/ActivityLog/Tests/Contracts`

**Review:** claude

### TASK-006: Textos del módulo de roles (en/es)

Ampliar `lang/{en,es}/admin/roles.php`: nombre y descripción de `admin_roles_create` y `admin_roles_delete`; `types.system` / `types.custom` y su filtro; formulario (`form.label`, `form.description`, `form.permissions`, ayudas y la nota de dependencias 'Al marcar un permiso se marcan los que necesita'); acciones `create`, `edit_details`, `duplicate`, `delete` (label, modal, submit, success, y para eliminar el texto con el conteo de usuarios); `locked` (por qué `admin` no se edita); resumen de cambios (`summary.grant`, `summary.revoke`, `summary.none`, con `:list` y plurales); fallos nuevos de TASK-005; widget de tipos (`widgets.types.*`); acciones de usuarios en el detalle del rol (`relations.users.add`, `remove`, con su modal). Mismas claves y placeholders en ambos idiomas.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-004, TASK-005

**Acceptance Criteria:**

1. `AdminTranslationKeysTest` pasa: mismas claves, sin textos vacíos y mismos placeholders.
2. Cada caso de `AdminAuthorizationFailure` tiene `failures.<value>` en ambos idiomas (lo verifica la prueba de mensajes de `AdminAuthorizationServiceTest`).

**writeScope:**

- `lang/en/admin/roles.php`
- `lang/es/admin/roles.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**Review:** claude

### TASK-007: Nombres de los eventos nuevos en el Activity log (en/es)

Agregar a `lang/{en,es}/admin/activity-log.php` `events.admin.role_created|role_updated|role_duplicated|role_deleted`, para que `ActivityLogEventCatalog` y `AdminTranslationKeysTest` los encuentren.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-005

**Acceptance Criteria:**

1. `AdminTranslationKeysTest` ('names every cataloged activity event') pasa en ambos idiomas.
2. Ningún evento existente cambia de texto.

**writeScope:**

- `lang/en/admin/activity-log.php`
- `lang/es/admin/activity-log.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**Review:** claude

### TASK-008: Seeder del catálogo: marcar roles de sistema y nuevos permisos

En `AdminAuthorizationSeeder`: crear los permisos nuevos (ya lo hace al recorrer `AdminPermission::cases()`), marcar cada rol de `AdminRole` con `is_system = true` también si ya existía (idempotente), y dar `admin.roles.create` y `admin.roles.delete` al bundle de `admin-roles-manager` solo al crearlo (regla actual). Actualizar `AdminAuthorizationSeederTest`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002, TASK-004

**Acceptance Criteria:**

1. Tras sembrar, los 13 roles del catálogo tienen `is_system = true` y `admin-roles-manager` tiene sus 8 permisos.
2. Volver a sembrar sobre una base donde un rol del catálogo tenía `is_system = false` lo corrige sin tocar sus permisos.

**writeScope:**

- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**Review:** claude

### TASK-009: Servicio: operar sobre Role, bloquear admin y exigir dependencias

Refactorizar `AdminAuthorizationService` para recibir `Role` en vez de `AdminRole`: `lockCatalog(Role)`, `grantRole(User, Role, User)`, `revokeRole`, `grantPermission(Role, AdminPermission, User)`, `revokePermission`, `syncUserRoles(User, list<Role>, User)` (los roles del guard `admin`, sistema o personalizados) y `syncRolePermissions(Role, list<AdminPermission>, User)`. Mantener el orden de bloqueo (rol `admin` primero) y bloquear el rol afectado por id. Rechazar con `RoleLocked` cualquier cambio de permisos de `admin`, y con `PermissionDependencyMissing` un conjunto resultante incompleto (en `grantPermission` sin requisitos, en `revokePermission` de un requisito con dependientes, y en `syncRolePermissions`). Actualizar `AdminAuthorizationServiceTest` y `AdminAuthorizationServiceSyncTest` al nuevo API. Los comandos y la UI se adaptan en TASK-010, TASK-016 y TASK-021.

**Type:** refactor · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-003, TASK-005, TASK-008

**Acceptance Criteria:**

1. Todas las pruebas de SOC-69 del servicio pasan reescritas con `Role` (mismo comportamiento: auditoría, último `admin.access`, auto-bloqueo, orden de bloqueo).
2. Cambiar permisos de `admin` lanza `RoleLocked`; quitar `admin.users.viewAny` de un rol que tiene `admin.users.view` lanza `PermissionDependencyMissing`; ninguno escribe ni audita.
3. `syncUserRoles` asigna un rol personalizado igual que uno de sistema.

**writeScope:**

- `app/Modules/Admin/Services/AdminAuthorizationService.php`
- `app/Modules/Admin/Tests/Services/AdminAuthorizationServiceTest.php`
- `app/Modules/Admin/Tests/Services/AdminAuthorizationServiceSyncTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Services/AdminAuthorizationServiceTest.php app/Modules/Admin/Tests/Services/AdminAuthorizationServiceSyncTest.php`

**Review:** claude

### TASK-010: Comandos: cualquier rol del guard admin y fallos nuevos

`AdminUserRoleCommand` y `AdminRolePermissionsCommand` buscan el rol por nombre en `Role::query()->admin()` (no en el enum), responden `Unknown Admin role` si no existe, y delegan en el servicio refactorizado. `InteractsWithAdminCatalog::catalogFailureMessage()` agrega mensajes de consola para `RoleLocked` y `PermissionDependencyMissing`.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-009

**Acceptance Criteria:**

1. `admin:user-role grant <email> custom-soporte` asigna un rol personalizado existente.
2. `admin:role-permissions revoke admin admin.access` responde el mensaje de rol bloqueado con `FAILURE` y no cambia nada; un nombre inexistente responde `INVALID`.

**writeScope:**

- `app/Modules/Admin/Console/Commands/AdminUserRoleCommand.php`
- `app/Modules/Admin/Console/Commands/AdminRolePermissionsCommand.php`
- `app/Modules/Admin/Console/Concerns/InteractsWithAdminCatalog.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Console`

**Review:** claude

### TASK-011: Pruebas de los comandos con roles personalizados

Actualizar `AdminUserRoleCommandTest` y `AdminRolePermissionsCommandTest`: los casos que revocaban `admin.access` del rol `admin` pasan a esperar el mensaje de rol bloqueado (la protección del último `admin.access` se prueba con un rol personalizado que lo concede); agregar casos con un rol personalizado y con un conjunto que rompe dependencias.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-010

**Acceptance Criteria:**

1. Cubre los dos criterios de TASK-010.
2. Los demás mensajes y códigos de salida de SOC-69 no cambian.

**writeScope:**

- `app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php`
- `app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Commands`

**Review:** claude

### TASK-012: Servicio: crear, editar, duplicar y eliminar roles

Agregar `createRole(string $label, ?string $description, list<AdminPermission>, User $actor): Role`, `updateRoleDetails(Role, string $label, ?string $description, User $actor): bool`, `duplicateRole(Role $source, string $label, User $actor): Role` y `deleteRole(Role, User $actor): void`. Cada una bloquea el rol `admin` primero; recorta y valida longitudes (`InvalidArgumentException`); genera el `name` `custom-` + slug con sufijo; rechaza un `label` repetido entre roles del guard `admin` sin distinguir mayúsculas (`RoleLabelTaken`), renombrar o borrar un rol de sistema (`SystemRoleProtected`) y borrar un rol con usuarios (`RoleHasUsers`, contando dentro de la transacción); exige dependencias al crear; protege el último `admin.access` al borrar; y audita `RoleCreated` (`role`, `permissions`), `RoleUpdated` (`role`, `changed_attributes`), `RoleDuplicated` (`role`, `source_role_id`) y `RoleDeleted` (`role`) sin `label` ni `description`. Crear `AdminAuthorizationServiceRoleLifecycleTest`.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-009

**Acceptance Criteria:**

1. Crear 'Soporte nivel 1' dos veces da `custom-soporte-nivel-1` y luego `RoleLabelTaken`; un `label` distinto con el mismo slug da `custom-…-2`.
2. Borrar un rol de sistema, o uno personalizado con usuarios, lanza el fallo correspondiente y no escribe; borrar uno sin usuarios lo elimina con sus permisos y deja `admin.role_deleted`.
3. Ninguna actividad contiene `label` ni `description` (`ActivityLogPrivacyTest` pasa).

**writeScope:**

- `app/Modules/Admin/Tests/Services/AdminAuthorizationServiceRoleLifecycleTest.php` (nuevo)
- `app/Modules/Admin/Services/AdminAuthorizationService.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Services app/Modules/ActivityLog/Tests/Security/ActivityLogPrivacyTest.php`

**Review:** claude

### TASK-013: RolePolicy por tipo de rol

Reescribir `RolePolicy`: `viewAny`/`view` como hoy; `create` y `replicate(Role)` con `admin.roles.create`; `update(Role)` (permisos) con `admin.roles.update` y no `isLocked()`; `updateDetails(Role)` con `admin.roles.update` y rol personalizado; `delete(Role)` con `admin.roles.delete` y rol personalizado (los usuarios los comprueba el servicio y la UI); niega `deleteAny`, `restore`, `restoreAny`, `forceDelete`, `forceDeleteAny` y `reorder`. Actualizar `RolePolicyTest`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002, TASK-004

**Acceptance Criteria:**

1. Un gestor puede editar permisos de `admin-posts-reader` y de un personalizado, pero no de `admin`; solo puede renombrar y borrar personalizados.
2. Un lector de roles no puede crear, duplicar, editar ni borrar; nadie puede `forceDelete` ni `restore`.

**writeScope:**

- `app/Modules/Admin/Policies/RolePolicy.php`
- `app/Modules/Admin/Tests/Policies/RolePolicyTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Policies/RolePolicyTest.php`

**Review:** claude

### TASK-014: Concern compartido de casillas de permisos con dependencias y resumen

Crear `Filament/Concerns/EditsRolePermissions.php`, extraído de 'Editar permisos' de `ViewRole`: `permissionCheckboxLists()` (una `CheckboxList` por área, `live()`, con descripción), aplicación en vivo de dependencias (al marcar se agregan `requires()`, al desmarcar se quitan `dependents()`, entre áreas), `permissionsByArea(Role|null)`, `selectedPermissions(array)` y `permissionChangeSummary(array $before, array $after)` para un `Text` en vivo ('Se concederán…', 'Se quitarán…' o 'Sin cambios'). Crear `EditsRolePermissionsTest` con una clase anónima que use el trait.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004, TASK-006

**Acceptance Criteria:**

1. Marcar 'Suspender cuentas' en el estado agrega 'Listar usuarios' y 'Ver un usuario'; desmarcar 'Listar usuarios' quita sus dependientes.
2. El resumen lista concedidos y quitados con sus etiquetas traducidas, y dice 'Sin cambios' cuando son iguales.
3. `selectedPermissions()` ignora valores fuera del catálogo.

**writeScope:**

- `app/Modules/Admin/Filament/Concerns/EditsRolePermissions.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Concerns/EditsRolePermissionsTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Concerns/EditsRolePermissionsTest.php`

**Review:** claude

### TASK-015: Listado y detalle de roles con tipos y nombres del modelo

En `RoleResource` (modelo nuevo, `getEloquentQuery()` con el scope `admin()`, `getRecordTitle()` con `displayName()`), `RolesTable` (nombre y descripción con `displayName()`/`displayDescription()`, badge 'Sistema' / 'Personalizado', filtro por tipo, sin la lógica de 'No editable') y `RoleInfolist` (tipo, nombre visible e identificador, y la nota de bloqueo para `admin`).

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003, TASK-006

**Acceptance Criteria:**

1. Un rol personalizado aparece con su `label`, el badge 'Personalizado' y su descripción; filtrar por 'Sistema' deja solo los del catálogo.
2. El detalle de `admin` muestra que está bloqueado; PHPStan pasa sobre `Roles/` sin referencias a `AdminRole::tryFrom()` en estos archivos.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RoleResource.php`
- `app/Modules/Admin/Filament/Resources/Roles/Tables/RolesTable.php`
- `app/Modules/Admin/Filament/Resources/Roles/Schemas/RoleInfolist.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Roles/RoleResource.php app/Modules/Admin/Filament/Resources/Roles/Tables app/Modules/Admin/Filament/Resources/Roles/Schemas`

**Review:** claude

### TASK-016: Editar permisos con el concern, resumen y bloqueo de admin

En `ViewRole`, 'Editar permisos' usa `EditsRolePermissions` (dependencias en vivo y resumen antes de guardar), llama a `syncRolePermissions(Role, …)`, y no se muestra en `admin` (política `update`). Actualizar `ViewRoleEditPermissionsActionTest`: agregar el resumen, las dependencias y un rol personalizado; el caso de último `admin.access` pasa a un rol personalizado que lo concede.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-009, TASK-013, TASK-014, TASK-015

**Acceptance Criteria:**

1. Editar permisos de un rol personalizado y de uno de sistema funciona y audita; en `admin` la acción no aparece y una llamada directa no escribe.
2. El modal muestra 'Se concederán: Ver respaldos' antes de guardar, y enviar un conjunto sin requisitos por llamada directa muestra el fallo traducido.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/ViewRole.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleEditPermissionsActionTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleEditPermissionsActionTest.php`

**Review:** claude

### TASK-017: Acciones de ciclo de vida en el detalle del rol

En `ViewRole`, agregar 'Editar detalles' (label y descripción, `->authorize('updateDetails')`), 'Duplicar' (nuevo `label`, contraseña, `->authorize('replicate')`, abre el rol nuevo) y 'Eliminar' (confirmación con el número de usuarios, contraseña, `->authorize('delete')`, deshabilitado con tooltip si tiene usuarios; redirige al listado). Todas con el servicio y notificaciones traducidas. Crear `ViewRoleLifecycleActionsTest`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-012, TASK-016

**Acceptance Criteria:**

1. Renombrar un personalizado cambia su `label` y no su `name`; en un rol de sistema la acción no aparece.
2. Duplicar `admin-posts-reader` crea un personalizado con sus permisos y abre su detalle; eliminar un personalizado sin usuarios lo borra y redirige; con usuarios, la acción está deshabilitada y una llamada directa muestra `role_has_users`.
3. Con contraseña incorrecta ninguna acción escribe.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleLifecycleActionsTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/Pages/ViewRole.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleLifecycleActionsTest.php`

**Review:** claude

### TASK-018: Crear rol desde el listado

En `ListRoles`, la acción de cabecera 'Crear rol' (`->authorize('create')`): `label`, descripción, casillas de `EditsRolePermissions` con resumen y contraseña; llama a `createRole()` y abre el detalle del rol nuevo. Crear `ListRolesCreateActionTest`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-012, TASK-013, TASK-014, TASK-015

**Acceptance Criteria:**

1. Un gestor crea 'Soporte' con 'Ver un usuario' (y su requisito marcado en vivo); el rol aparece en el listado con 'Personalizado' y hay una actividad `admin.role_created`.
2. Un `label` repetido muestra el error en el campo; un lector de roles no ve la acción y una llamada directa no escribe.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesCreateActionTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/Pages/ListRoles.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesCreateActionTest.php`

**Review:** claude

### TASK-019: Matriz y widgets con todos los roles del guard admin

`RolePermissionMatrix` (columnas: roles de sistema en orden de catálogo y luego personalizados por nombre, con `displayName()`), `UsersPerRoleChart` (una barra por rol del guard `admin`) y `RolesOverview` ('Roles sin usuarios' cuenta todos los roles del guard `admin`).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-015

**Acceptance Criteria:**

1. Un rol personalizado aparece como columna de la matriz y como barra del gráfico, con su `label`.
2. Las consultas siguen siendo constantes (una para la matriz, una para el gráfico).

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/RolePermissionMatrix.php`
- `app/Modules/Admin/Filament/Resources/Roles/Widgets/UsersPerRoleChart.php`
- `app/Modules/Admin/Filament/Resources/Roles/Widgets/RolesOverview.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Roles/Pages/RolePermissionMatrix.php app/Modules/Admin/Filament/Resources/Roles/Widgets`

**Review:** claude

### TASK-020: Widget de tipos de rol

Crear `Roles/Widgets/RoleTypesOverview` (roles de sistema, roles personalizados y personalizados sin usuarios, en una consulta agregada; `canView()` con `viewAny` de `Role`) y mostrarlo en `ListRoles` después de `RolesOverview`. Crear `RoleTypesOverviewTest`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-018, TASK-019

**Acceptance Criteria:**

1. Con 2 personalizados (uno sin usuarios) muestra 13, 2 y 1.
2. Se oculta a quien no puede listar roles.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Widgets/RoleTypesOverview.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/RoleTypesOverviewTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/Pages/ListRoles.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/RoleTypesOverviewTest.php`

**Review:** claude

### TASK-021: Users con roles personalizados y resumen antes de guardar

`ViewUser` 'Gestionar roles' ofrece todos los roles del guard `admin` (sistema primero, luego personalizados) con `displayName()`/`displayDescription()`, muestra el resumen de cambios en vivo y llama a `syncUserRoles(User, list<Role>, …)`; `UsersTable` (columna y filtro con todos los roles y `displayName()`) y `Users/RelationManagers/RolesRelationManager` (`displayName()`/`displayDescription()`).

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-009, TASK-015

**Acceptance Criteria:**

1. Un rol personalizado se puede asignar desde 'Gestionar roles' y aparece en la columna, el filtro y la pestaña Roles.
2. El modal muestra 'Se asignarán…' / 'Se quitarán…' antes de guardar; PHPStan pasa sin `AdminRole::tryFrom()` en estos archivos.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/Pages/ViewUser.php`
- `app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php`
- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/RolesRelationManager.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Users`

**Review:** claude

### TASK-022: Pruebas de Users con roles personalizados

Actualizar `ViewUserManageRolesActionTest` (rol personalizado y resumen), `ListUsersRolesTest` (filtro por un personalizado) y la prueba de la pestaña Roles del usuario.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-021

**Acceptance Criteria:**

1. Cubre los dos criterios de TASK-021.
2. Las pruebas de SOC-69 de esos archivos siguen pasando.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserManageRolesActionTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersRolesTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/RolesRelationManagerTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users`

**Review:** claude

### TASK-023: Agregar y quitar usuarios desde el detalle del rol

En `RoleUsersRelationManager`: acción de cabecera 'Agregar usuarios' (`Select` múltiple y buscable de usuarios que aún no tienen el rol, contraseña, `->authorize` con `admin.users.roles` vía `UserPolicy::manageRoles` para cada usuario) y acción de fila 'Quitar del rol'; ambas con `grantRole`/`revokeRole`. Nunca sobre la cuenta propia. Actualizar `RoleUsersRelationManagerTest`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-009

**Acceptance Criteria:**

1. Un gestor agrega dos usuarios a un rol personalizado y quita uno; hay una actividad por cambio.
2. La cuenta propia no aparece en el selector ni tiene 'Quitar'; sin `admin.users.roles` las acciones no aparecen y una llamada directa no escribe.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php`

**Review:** claude

### TASK-024: Pruebas del listado, detalle, matriz y widgets

Actualizar `ListRolesTest` (tipo y filtro, rol personalizado, sin 'No editable'), `ViewRoleTest` (detalle de personalizado y de `admin` bloqueado) y `RolePermissionMatrixTest` (columna de un personalizado).

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-017, TASK-019

**Acceptance Criteria:**

1. Cubre los criterios de TASK-015 y TASK-019.
2. El número de consultas del listado y la matriz sigue constante con roles personalizados.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php`

**Review:** claude

### TASK-025: Pruebas de los widgets de roles existentes

Actualizar `RolesOverviewTest` y `UsersPerRoleChartTest` para incluir roles personalizados.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-019

**Acceptance Criteria:**

1. Un personalizado sin usuarios cuenta en 'Roles sin usuarios' y tiene su barra.
2. Las pruebas de SOC-69 siguen pasando.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/RolesOverviewTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/UsersPerRoleChartTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/RolesOverviewTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/UsersPerRoleChartTest.php`

**Review:** claude

### TASK-026: Roles personalizados de ejemplo y su actividad

Crear `AdminCustomRoleSeeder` (solo `local`/`testing`, idempotente): 'Soporte' (ver usuarios y actividad), 'Editor de contenido' (publicaciones y anuncios sin borrar) y 'Auditor' (actividad, panel principal y respaldos en lectura), con dependencias completas, asignados a algunos administradores aleatorios (nunca Angel). Registrarlo en `DatabaseSeeder` después de `AdminUserSeeder`. En `ActivityLogSeeder`, agregar `admin.role_created` de Angel por cada rol personalizado (con `permissions`) antes de sus `role_granted`, y excluir el rol `admin` de los cambios simulados de permisos.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-008, TASK-012

**Acceptance Criteria:**

1. Tras `migrate:fresh --seed` existen los tres roles personalizados con usuarios y sus permisos cumplen dependencias; correr el seeder dos veces no duplica.
2. Cada rol personalizado tiene un `admin.role_created` anterior a sus asignaciones, y ningún cambio simulado de permisos apunta al rol `admin`.

**writeScope:**

- `app/Modules/Admin/Database/Seeders/AdminCustomRoleSeeder.php` (nuevo)
- `database/seeders/DatabaseSeeder.php`
- `app/Modules/ActivityLog/Database/Seeders/ActivityLogSeeder.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Database app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php`

**Review:** claude

### TASK-027: Pruebas de los seeders de roles

Crear `AdminCustomRoleSeederTest` y extender `ActivityLogSeederTest` con los criterios de TASK-026; actualizar `DescribesActivityLogParties` para importar el modelo nuevo (el enlace a roles sigue igual).

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-026

**Acceptance Criteria:**

1. Cubre los dos criterios de TASK-026.
2. El sujeto de un `role_created` enlaza al detalle del rol para un lector de roles.

**writeScope:**

- `app/Modules/Admin/Tests/Database/AdminCustomRoleSeederTest.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php`
- `app/Modules/Admin/Filament/Concerns/DescribesActivityLogParties.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Database/AdminCustomRoleSeederTest.php app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php app/Modules/Admin/Tests/Filament/Resources/ActivityLogs`

**Review:** claude

### TASK-028: Guardas de traducción y texto fijo

`AdminHardcodedTextTest` escanea también `EditsRolePermissions.php`; `AdminTranslationKeysTest` exige `types.*` y un texto por cada caso de `AdminAuthorizationFailure`.

**Type:** test · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-014, TASK-017, TASK-018, TASK-020, TASK-023

**Acceptance Criteria:**

1. Pasa con todo `Roles/` y el concern escaneados.
2. Falla si se agrega un fallo sin texto en `lang/es/admin/roles.php`.

**writeScope:**

- `app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php`
- `app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-029: Documentación

Actualizar CLAUDE.md (modelo `Role` propio y config, morph map, tabla de roles con los permisos nuevos, roles de sistema vs personalizados, `admin` bloqueado, dependencias, servicio y fallos nuevos, política, acciones del panel, comandos, seeders y la sección de SOC-69 que dice 'catálogo cerrado'), AGENTS.md y `docs/architecture/backend.md`.

**Type:** docs · **Effort:** M · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-011, TASK-017, TASK-018, TASK-022, TASK-026

**Acceptance Criteria:**

1. Ningún documento dice que el catálogo de roles es cerrado ni que no se crean roles desde el panel.
2. Los tres documentos nombran los mismos permisos, fallos y eventos nuevos.

**writeScope:**

- `CLAUDE.md`
- `AGENTS.md`
- `docs/architecture/backend.md`

**validateCommand:** `rtk grep -n "catalog stays closed" CLAUDE.md AGENTS.md docs/architecture/backend.md; test $? -eq 1`

**Review:** claude

### TASK-030: Gates finales de calidad

Quitar `Gate::policy(Role::class, RolePolicy::class)` y sus imports de `AdminServiceProvider` (la política resuelve por `#[UsePolicy]` del modelo nuevo), después de comprobar con `grep` que ningún archivo de `app/` salvo `RolePolicy` usa ya `Spatie\Permission\Models\Role` (pasar también los tipos de `RolePolicy` al modelo nuevo). Pint (`--dirty --format agent`), `composer rector-clear-cache` → `rector-dry` → `rector` sobre todo el proyecto, `composer phpstan`, `composer test` y `composer doctor`. Prueba manual en el navegador del flujo crear → asignar → editar → duplicar → eliminar.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-020, TASK-022, TASK-023, TASK-024, TASK-025, TASK-027, TASK-028, TASK-029

**Acceptance Criteria:**

1. Sin el registro manual, las pruebas de denegación de Roles (`ListRolesTest`, `ViewRoleTest`) siguen respondiendo 403.
2. Pint, Rector (sin pendientes), PHPStan max, la suite completa y `composer doctor` pasan.
3. El flujo completo funciona en el navegador sin errores de consola.

**writeScope:**

- `app/Modules/Admin/Providers/AdminServiceProvider.php`

**validateCommand:** `rtk composer phpstan && rtk composer test && rtk composer doctor`

**Review:** claude

## Failure Modes

- Dos administradores crean roles con el mismo `label` a la vez: el índice único `(name, guard_name)` de Spatie y la generación con sufijo dentro de la transacción con bloqueo del rol `admin` lo serializan; el segundo recibe otro sufijo o `role_label_taken`.
- Eliminar un rol mientras otro administrador le asigna un usuario: ambas operaciones bloquean el rol `admin` primero; la eliminación vuelve a contar usuarios dentro de la transacción.
- Un rol personalizado concede `admin.access` y es el último con ese permiso: revocarlo o eliminarlo cae en `last_admin_access`.
- Un conjunto de permisos sin sus requisitos llega por una llamada directa o por consola: `permission_dependency_missing`, sin cambios.
- Una base existente tiene los roles del catálogo sin `is_system`: volver a correr `AdminAuthorizationSeeder` los marca (idempotente).
- Un rol creado fuera del panel (sin `label`, `is_system` falso) se trata como personalizado y muestra su `name`.

## Ship Cut

Mínimo entregable: TASK-001 a TASK-019 (modelo, migración, permisos y dependencias, servicio refactorizado y de ciclo de vida, comandos, política, concern, Resource con crear/editar/duplicar/eliminar y la UI que lista roles). Los extras (widget de tipos, gestión de usuarios desde el rol, roles sembrados) son TASK-020 a TASK-026; TASK-027 a TASK-029 cierran cualquier corte. Entre TASK-008 y TASK-010 el proyecto no pasa PHPStan completo (los comandos y la UI aún reciben `AdminRole`), así que no conviene detenerse en medio.

## Test Coverage Map

| Área | Tareas |
| --- | --- |
| Modelo, migración y cableado | TASK-003 |
| Permisos y dependencias | TASK-004, TASK-014 |
| Servicio: refactor y reglas nuevas | TASK-009, TASK-012 |
| Comandos | TASK-011 |
| Política | TASK-013 |
| Resource de roles | TASK-016, TASK-017, TASK-018, TASK-020, TASK-023, TASK-024, TASK-025 |
| Users | TASK-022 |
| Seeders y actividad | TASK-008, TASK-027 |
| Traducciones | TASK-006, TASK-007, TASK-028 |

## Execution Summary

- Tareas: 30
- Ruta crítica: TASK-001 → TASK-002 → TASK-003 → TASK-009 → TASK-012 → TASK-017 → TASK-024 → TASK-030
- Capas paralelas:

  1. TASK-001, TASK-004, TASK-005
  2. TASK-002, TASK-006, TASK-007
  3. TASK-003, TASK-008, TASK-013, TASK-014
  4. TASK-009, TASK-015
  5. TASK-010, TASK-012, TASK-016, TASK-019, TASK-021, TASK-023
  6. TASK-011, TASK-017, TASK-018, TASK-022, TASK-025, TASK-026
  7. TASK-020, TASK-024, TASK-027, TASK-029
  8. TASK-028
  9. TASK-030

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-002 |
| TASK-004 | — |
| TASK-005 | — |
| TASK-006 | TASK-004, TASK-005 |
| TASK-007 | TASK-005 |
| TASK-008 | TASK-002, TASK-004 |
| TASK-009 | TASK-003, TASK-005, TASK-008 |
| TASK-010 | TASK-009 |
| TASK-011 | TASK-010 |
| TASK-012 | TASK-009 |
| TASK-013 | TASK-002, TASK-004 |
| TASK-014 | TASK-004, TASK-006 |
| TASK-015 | TASK-003, TASK-006 |
| TASK-016 | TASK-009, TASK-013, TASK-014, TASK-015 |
| TASK-017 | TASK-012, TASK-016 |
| TASK-018 | TASK-012, TASK-013, TASK-014, TASK-015 |
| TASK-019 | TASK-015 |
| TASK-020 | TASK-018, TASK-019 |
| TASK-021 | TASK-009, TASK-015 |
| TASK-022 | TASK-021 |
| TASK-023 | TASK-009 |
| TASK-024 | TASK-017, TASK-019 |
| TASK-025 | TASK-019 |
| TASK-026 | TASK-008, TASK-012 |
| TASK-027 | TASK-026 |
| TASK-028 | TASK-014, TASK-017, TASK-018, TASK-020, TASK-023 |
| TASK-029 | TASK-011, TASK-017, TASK-018, TASK-022, TASK-026 |
| TASK-030 | TASK-020, TASK-022, TASK-023, TASK-024, TASK-025, TASK-027, TASK-028, TASK-029 |
