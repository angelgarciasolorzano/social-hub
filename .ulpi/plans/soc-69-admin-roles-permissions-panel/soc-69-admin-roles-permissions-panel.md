# Plan: SOC-69: Gestionar roles y permisos de administración desde el panel de Filament

## Overview

Llevar al panel de Filament la gestión que hoy solo existe por consola (`admin:user-role`, `admin:role-permissions`): un servicio compartido con las reglas de los comandos (bloqueos, último `admin.access`, auditoría), un Resource de roles con detalle, edición de permisos, usuarios, historial, widgets y una matriz roles × permisos, y en Users una acción 'Gestionar roles', una pestaña de roles, los permisos efectivos y una columna y un filtro de roles. El catálogo sigue cerrado a los enums `AdminRole` y `AdminPermission`.

**Linear:** [SOC-69](https://linear.app/social-hub-ang/issue/SOC-69/feature-manage-admin-roles-and-permissions-from-the-filament-panel) · **Modo:** EXPANSION · **Revisión predeterminada:** Claude

## Scope Challenge

EXPANSION con revisión de Claude. Decisiones del usuario (2026-11-03): permisos `admin.roles.viewAny`, `admin.roles.view`, `admin.roles.update` y `admin.users.roles`, con dos roles nuevos `admin-roles-reader` y `admin-roles-manager`; confirmación de contraseña en las dos acciones de escritura (editar permisos de un rol y gestionar roles de un usuario); extras: eventos simulados de permisos de rol en `ActivityLogSeeder`, columna y filtro de roles en Users, matriz de permisos, widgets en Roles, historial del rol y permisos efectivos del usuario. Hallazgos: la lógica vive hoy dentro de `AdminUserRoleCommand`/`AdminRolePermissionsCommand` y del trait `InteractsWithAdminCatalog::lockCatalogRole()`, que escribe errores en consola, así que el servicio devuelve fallos tipados que cada comando traduce a sus mensajes actuales (las pruebas de comandos verifican el texto). Spatie usa su propio `Role` (morph `role` ya registrado), así que su política se registra con `Gate::policy()`. Los valores de los permisos tienen puntos (`admin.users.view`), por lo que las claves de traducción usan el nombre del caso en snake_case, no el valor.

## Prerequisites

- Catálogo `AdminRole`/`AdminPermission` y `AdminAuthorizationSeeder`, que solo asigna permisos a un rol la primera vez que lo crea (SOC-44).
- `ActivityLogger` con `ActivityLogAdminEvent::RoleGranted|RoleRevoked|RolePermissionGranted|RolePermissionRevoked` (SOC-54).
- Autorización por políticas de modelo en Filament (SOC-57) y textos del panel con `TranslatedText::of()` en `lang/{en,es}/admin/` (SOC-65/66).
- Confirmación de contraseña con límite de intentos ya resuelta en `BackupManagementService` (SOC-55), usada como referencia.
- Morph `role` → `Spatie\Permission\Models\Role` (`AppServiceProvider::ROLE_MORPH_NAME`).

## Non-Goals

- Crear, renombrar o eliminar roles o permisos fuera de los enums (catálogo cerrado).
- Teams de Spatie y roles del guard `web`.
- Cambiar `admin:bootstrap-admin`: el primer administrador sigue recibiendo `admin` y los cinco roles lectores; los roles nuevos se otorgan después con `admin:user-role`.
- Que un administrador gestione sus propios roles desde el panel (por consola sigue siendo posible).
- Refactorizar la confirmación de contraseña de Backups para usar el concern nuevo.
- Notificar al usuario cuando cambian sus roles.

## Contracts

- `AdminAuthorizationService` es el único escritor de roles y permisos del catálogo fuera de seeders: los tres caminos (dos comandos y dos acciones de Filament) pasan por él.
- El servicio no autoriza (lo hacen `RolePolicy` y `UserPolicy::manageRoles`); sí valida el catálogo, bloquea filas, protege el último `admin.access`, audita solo cambios reales y limpia la caché de Spatie (`PermissionRegistrar::forgetCachedPermissions()`) tras cada transacción confirmada.
- Los fallos del servicio son `AdminAuthorizationException` con un caso de `AdminAuthorizationFailure`; los comandos los traducen a sus mensajes y códigos de salida actuales, byte por byte; Filament los muestra como notificación de fallo traducida.
- Las operaciones `sync*` del panel corren en una sola transacción: si el resultado deja al actor sin `admin.access` o sin el permiso que autorizó la acción, se revierte todo (cambios y actividad).
- Las acciones del panel piden la contraseña actual con límite de 5 intentos por minuto por administrador; una contraseña errónea no escribe nada.
- Las claves de traducción de roles y permisos son `admin/roles.catalog.roles.<case_snake>` y `admin/roles.catalog.permissions.<case_snake>` (`label`, `description`); las áreas, `admin/roles.catalog.areas.<area>`.
- Un `Role` de la base de datos que no esté en `AdminRole` o no use el guard `admin` se lista como no editable: `RolePolicy::update()` lo niega.

## Existing Code Leverage

- `AdminUserRoleCommand::userRetainsAdminAccessAfterRevocation()` y `AdminRolePermissionsCommand::aUserRetainsAdminAccessAfterRevocation()`: las comprobaciones del último `admin.access`, que se mueven al servicio sin cambiar su lógica.
- `InteractsWithAdminCatalog::lockCatalogRole()`: el orden de bloqueos (rol `admin`, permiso `admin.access`, rol pedido).
- `AccountStatusService` + `ViewUser::changeAccountStatus()`: patrón servicio sin autorización + acción de Filament con `->authorize()` y notificación de fallo.
- `BackupManagementService` (RateLimiter + `Hash::check`) y `BackupsTable` (campo `current_password`): base del concern de confirmación de contraseña.
- `ActivityLogsTable::configure($table, deferLoading: false)` y `ActivityLogsRelationManager`: base del historial del rol.
- `BuildsDailyActivityCharts` y `RegistrationsChart`: base del widget de estadísticas con sparkline y del gráfico de barras.
- `BackupManagement` + su vista Blade en `UI/Views/filament/backups/`: base de la página de matriz.
- `AdminFilamentTestContext`, `FilamentTableRecords` y `createAdminCommandActor()` para las pruebas.

## Tasks

### TASK-001: Ampliar el catálogo: permisos y roles de gestión de roles, con etiquetas y áreas

Agregar a `AdminPermission` los casos `AdminRolesViewAny = 'admin.roles.viewAny'`, `AdminRolesView = 'admin.roles.view'`, `AdminRolesUpdate = 'admin.roles.update'` y `AdminUsersRoles = 'admin.users.roles'`, y a `AdminRole` `AdminRolesReader = 'admin-roles-reader'` y `AdminRolesManager = 'admin-roles-manager'`. Agregar a ambos enums `translationKey()` (el nombre del caso en snake_case: `admin_users_view`), `label()` y `description()` que leen `admin/roles.catalog.{roles|permissions}.<translationKey>.{label|description}` con `TranslatedText::of()`, y a `AdminPermission` `area()` (el segundo segmento del valor: `access`, `dashboard`, `users`, `posts`, `activity`, `backups`, `announcements`, `roles`). Las traducciones las agrega TASK-004; esta tarea prueba solo valores, claves y áreas.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Los cuatro permisos y dos roles nuevos existen con los valores exactos indicados, y `AdminPermission::tryFrom('admin.roles.delete')` es `null`.
2. `AdminPermission::AdminUsersView->translationKey()` es `admin_users_view` y `AdminPermission::AdminAccess->area()` es `access`; todas las claves son únicas (sin colisiones entre casos).
3. `AdminRolesManager` y `AdminRolesReader` no colisionan con ningún valor existente de `AdminRole`.

**writeScope:**

- `app/Modules/Admin/Tests/Enums/AdminRoleManagementAuthorizationEnumsTest.php` (nuevo)
- `app/Modules/Admin/Enums/AdminPermission.php`
- `app/Modules/Admin/Enums/AdminRole.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Enums`

**Review:** claude

### TASK-002: Sembrar los bundles de los roles nuevos en el catálogo

En `AdminAuthorizationSeeder`: `AdminRolesReader` → `AdminRolesViewAny`, `AdminRolesView`; `AdminRolesManager` → `AdminRolesViewAny`, `AdminRolesView`, `AdminRolesUpdate`, `AdminUsersRoles`, `AdminUsersViewAny`, `AdminUsersView` (necesita ver el usuario para usar su acción en ViewUser, como `AdminUsersModerator`). Mantener la regla actual: los permisos se asignan solo cuando el rol se crea.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Tras sembrar, `admin-roles-manager` tiene exactamente los seis permisos y `admin-roles-reader` exactamente los dos.
2. Volver a correr el seeder sobre un rol ya existente con permisos modificados no los restablece (idempotente, sin pisar cambios hechos desde el panel).

**writeScope:**

- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**Review:** claude

### TASK-003: Asignar el rol de gestor de roles a Angel en local

En `AdminUserSeeder::run()`, Angel recibe además `AdminRolesManager` (como `AdminUsersModerator`). `readerRoles()` no cambia, así que los administradores aleatorios no reciben roles de gestión de roles y `admin:bootstrap-admin` sigue igual.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-002

**Acceptance Criteria:**

1. En `local`/`testing`, Angel tiene `admin-roles-manager` y puede `admin.users.roles`.
2. Ningún administrador aleatorio recibe `admin-roles-manager` ni `admin-roles-reader`.

**writeScope:**

- `app/Modules/Admin/Database/Seeders/AdminUserSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminUserSeederTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Database/AdminUserSeederTest.php app/Modules/Admin/Tests/Database/AdminUserSeederRandomAdminsTest.php`

**Review:** claude

### TASK-004: Catálogo de traducciones de Roles (en/es)

Crear `lang/{en,es}/admin/roles.php` con el árbol completo que consumen las tareas de UI: `resource` (model_label, plural_model_label, navigation_label, navigation_group 'Access control' / 'Control de acceso'); `catalog.roles.<case_snake>.{label,description}` para los 13 roles; `catalog.permissions.<case_snake>.{label,description}` para los 24 permisos; `catalog.areas.<area>`; `table` (name, description, permissions_count, users_count, guard, not_editable); `infolist` (sections y campos); `actions.edit_permissions.*` y `actions.manage_roles.*` (label, modal_heading, modal_description, submit, success, unchanged, current_password, password_incorrect, too_many_attempts); `failures.<failure_snake>` para cada caso de `AdminAuthorizationFailure` (TASK-006); `relations.users.*`, `relations.history.*`; `widgets.overview.*`, `widgets.users_per_role.*`; `matrix.*`; `users.*` (columna y filtro de roles, sección de permisos efectivos, pestaña de roles); `quick_links.roles.*`. Mismas claves y placeholders en ambos idiomas.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Cada caso de `AdminRole` y `AdminPermission` tiene `label` y `description` no vacíos en `en` y `es` (`Lang::has` con el locale, sin fallback).
2. Ambos archivos tienen exactamente las mismas claves y placeholders (lo verifica el `AdminTranslationKeysTest` existente al recorrer los catálogos de `lang/*/admin`).

**writeScope:**

- `lang/en/admin/roles.php` (nuevo)
- `lang/es/admin/roles.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**Review:** claude

### TASK-005: Probar que el catálogo de roles y permisos tiene nombre en ambos idiomas

Extender `AdminTranslationKeysTest` con un caso que recorre `AdminRole::cases()`, `AdminPermission::cases()` y sus áreas y exige `label`/`description` (y nombre de área) en `en` y `es`, como hace ya con los eventos de actividad y los enums de anuncios.

**Type:** test · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**

1. Falla si se agrega un caso a `AdminPermission` sin traducción en `lang/es/admin/roles.php`.
2. Pasa con el catálogo de TASK-004 en ambos locales.

**writeScope:**

- `app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**Review:** claude

### TASK-006: Servicio de autorización: operaciones unitarias con fallos tipados

Crear `App\Modules\Admin\Services\AdminAuthorizationService` (final readonly; recibe `ActivityLogger` y `PermissionRegistrar`) con `grantRole(User $user, AdminRole, User $actor): bool`, `revokeRole(...)`, `grantPermission(AdminRole, AdminPermission, User $actor): bool` y `revokePermission(...)`; cada una devuelve si hubo cambio. Mover desde los comandos, sin cambiar su lógica: el bloqueo del catálogo (rol `admin`, permiso `admin.access`, rol pedido), el bloqueo del usuario, las comprobaciones del último `admin.access` y la auditoría (`RoleGranted`/`RoleRevoked` con sujeto el usuario y `role`; `RolePermissionGranted`/`RolePermissionRevoked` con sujeto el `Role` y `role` + `permission`), solo si hubo cambio. Limpiar la caché de Spatie con `DB::afterCommit`. Los fallos lanzan `App\Modules\Admin\Exceptions\AdminAuthorizationException` con un caso de `App\Modules\Admin\Enums\AdminAuthorizationFailure`: `CatalogNotInitialized`, `RoleNotInitialized`, `PermissionNotInitialized`, `LastAdminAccess`, `ActorWouldLoseAccess` (este lo usa TASK-008). La carpeta `Exceptions/` es nueva en el módulo. El servicio no valida al actor ni autoriza.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. `grantRole` dos veces devuelve `true` y luego `false`, y deja una sola actividad `admin.role_granted` con el actor como causante.
2. Revocar el único rol con `admin.access` del único administrador lanza `LastAdminAccess` y no cambia roles ni actividad; `revokePermission(Admin, AdminAccess)` en ese caso lanza lo mismo.
3. Tras `grantPermission`, `$user->fresh()->can(...)` es verdadero en la misma prueba (caché limpiada); sin sembrar el catálogo, lanza `CatalogNotInitialized`.

**writeScope:**

- `app/Modules/Admin/Services/AdminAuthorizationService.php` (nuevo)
- `app/Modules/Admin/Exceptions/AdminAuthorizationException.php` (nuevo)
- `app/Modules/Admin/Enums/AdminAuthorizationFailure.php` (nuevo)

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Services/AdminAuthorizationService.php app/Modules/Admin/Exceptions app/Modules/Admin/Enums/AdminAuthorizationFailure.php`

**Review:** claude

### TASK-007: Pruebas del servicio de autorización (operaciones unitarias)

Crear `Tests/Services/AdminAuthorizationServiceTest.php` cubriendo los tres criterios de TASK-006 y, además, que una revocación sin cambio no registra actividad y que el sujeto de `role_permission_*` es el `Role` (morph `role`). La carpeta `Tests/Services/` queda dentro de la suite `Admin` ya registrada.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-006

**Acceptance Criteria:**

1. Cubre: cambio real vs. repetido, último `admin.access` por rol y por permiso, caché limpiada y catálogo sin sembrar.
2. Ninguna prueba depende de mensajes de consola.

**writeScope:**

- `app/Modules/Admin/Tests/Services/AdminAuthorizationServiceTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Services/AdminAuthorizationServiceTest.php`

**Review:** claude

### TASK-008: Servicio: sincronización del panel con protección contra auto-bloqueo

Agregar a `AdminAuthorizationService` `syncUserRoles(User $user, list<AdminRole> $roles, User $actor): AdminAuthorizationChanges` y `syncRolePermissions(AdminRole, list<AdminPermission>, User $actor): AdminAuthorizationChanges`. Cada una calcula la diferencia contra el estado bloqueado y aplica las operaciones unitarias dentro de UNA transacción; al final, si el actor ya no tiene `admin.access` o el permiso que autorizó la acción (`AdminUsersRoles` / `AdminRolesUpdate`), lanza `ActorWouldLoseAccess` y todo se revierte. `AdminAuthorizationChanges` (Data en `Data/`) lleva `granted` y `revoked` (listas de valores) y `hasChanges()`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006

**Acceptance Criteria:**

1. Sincronizar `[A, B]` sobre un usuario con `[B, C]` concede A, revoca C, deja dos actividades y devuelve `granted=[A]`, `revoked=[C]`; sincronizar el mismo conjunto devuelve `hasChanges() === false` sin actividad.
2. Un gestor que quita `admin.roles.update` de `admin-roles-manager` (su propio rol) recibe `ActorWouldLoseAccess` y el rol conserva todos sus permisos y no hay actividad nueva.
3. Si una de varias operaciones lanza `LastAdminAccess`, ninguna de las anteriores de la misma sincronización persiste.

**writeScope:**

- `app/Modules/Admin/Data/AdminAuthorizationChanges.php` (nuevo)
- `app/Modules/Admin/Tests/Services/AdminAuthorizationServiceSyncTest.php` (nuevo)
- `app/Modules/Admin/Services/AdminAuthorizationService.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Services/AdminAuthorizationServiceSyncTest.php`

**Review:** claude

### TASK-009: Comandos admin:user-role y admin:role-permissions delegan en el servicio

Reescribir `handle()` de ambos comandos para validar argumentos y `--actor` como hoy y delegar la escritura en `AdminAuthorizationService` dentro de su transacción, traduciendo cada `AdminAuthorizationFailure` al mensaje y código de salida actuales (`FAILURE`). Quitar `lockCatalogRole()` y los helpers privados de último `admin.access` que pasaron al servicio; `InteractsWithAdminCatalog` conserva `isValidAction`, `actorEmail`, `resolveActor` y las constantes. `AdminBootstrapCommand` no cambia.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-006

**Acceptance Criteria:**

1. Las 32 pruebas de `AdminUserRoleCommandTest` y `AdminRolePermissionsCommandTest` pasan sin modificar ni un archivo de prueba (mensajes y códigos idénticos).
2. `AdminBootstrapCommandTest` sigue pasando y ningún comando llama a `ActivityLogger` directamente salvo el bootstrap.

**writeScope:**

- `app/Modules/Admin/Console/Commands/AdminUserRoleCommand.php`
- `app/Modules/Admin/Console/Commands/AdminRolePermissionsCommand.php`
- `app/Modules/Admin/Console/Concerns/InteractsWithAdminCatalog.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Commands`

**Review:** claude

### TASK-010: RolePolicy para el Role de Spatie

Crear `App\Modules\Admin\Policies\RolePolicy`: `viewAny` (`admin.roles.viewAny`), `view` (`admin.roles.view`), `update(User, Role)` (`admin.roles.update`, guard `admin` y nombre en `AdminRole`); niega `create`, `delete`, `deleteAny`, `restore`, `restoreAny`, `forceDelete`, `forceDeleteAny`, `replicate` y `reorder`. Registrarla con `Gate::policy(Role::class, RolePolicy::class)` en `AdminServiceProvider::boot()` (el modelo es del paquete, no admite `#[UsePolicy]`).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Un gestor puede `update` `admin-users-reader`, pero no un `Role` llamado `legacy-role` ni uno del guard `web`.
2. Un lector de roles puede `viewAny` y `view` pero no `update`; nadie puede `create`, `delete` ni `forceDelete`.

**writeScope:**

- `app/Modules/Admin/Policies/RolePolicy.php` (nuevo)
- `app/Modules/Admin/Tests/Policies/RolePolicyTest.php` (nuevo)
- `app/Modules/Admin/Providers/AdminServiceProvider.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Policies/RolePolicyTest.php`

**Review:** claude

### TASK-011: UserPolicy::manageRoles

Agregar `manageRoles(User $authenticatedUser, User $user): bool`: permiso `admin.users.roles` y `isNot($user)`. Las demás escrituras siguen negadas.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Un gestor puede gestionar los roles de otro usuario pero no los suyos.
2. Un moderador sin `admin.users.roles` no puede.

**writeScope:**

- `app/User/Policies/UserPolicy.php`
- `app/Modules/Admin/Tests/Policies/UserPolicyTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Policies/UserPolicyTest.php`

**Review:** claude

### TASK-012: Concern de Filament para confirmar la contraseña actual

Crear `Filament/Concerns/ConfirmsCurrentPassword.php` con `currentPasswordField(): TextInput` (password, `autocomplete=current-password`, requerido, etiqueta `admin/roles.actions.*.current_password` vía parámetro) y `ensureCurrentPassword(User $user, string $password, string $throttleKeyPrefix): void`, que aplica `RateLimiter` (5 por minuto por usuario) y `Hash::check`, y lanza `ValidationException` en `current_password` con los textos de TASK-004. Mismo comportamiento que `BackupManagementService`, sin tocar Backups.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**

1. Una contraseña correcta no lanza y limpia el contador; una incorrecta lanza con la clave `current_password`.
2. El sexto intento erróneo en un minuto lanza el error de demasiados intentos aunque la contraseña sea correcta.

**writeScope:**

- `app/Modules/Admin/Filament/Concerns/ConfirmsCurrentPassword.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Concerns/ConfirmsCurrentPasswordTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Concerns/ConfirmsCurrentPasswordTest.php`

**Review:** claude

### TASK-013: Resource de roles: listado

Crear `RoleResource` (modelo Spatie `Role`, URL `/admin/roles`, grupo de navegación y etiquetas traducidas, sin overrides `can*()`: Filament pregunta a `RolePolicy`), con solo la página `index` por ahora, `ListRoles` y `Tables/RolesTable`: consulta `Role::query()->where('guard_name', 'admin')->withCount(['permissions', 'users'])`, columnas nombre (etiqueta del catálogo o el nombre crudo), descripción, nº de permisos, nº de usuarios y un badge 'No editable' para roles fuera del catálogo; búsqueda por nombre; orden por nombre.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004, TASK-010

**Acceptance Criteria:**

1. `php artisan route:list --name=filament.admin.resources.roles` muestra solo `index`.
2. PHPStan pasa sobre los tres archivos sin overrides `can*()`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RoleResource.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/Pages/ListRoles.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/Tables/RolesTable.php` (nuevo)

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Roles`

**Review:** claude

### TASK-014: Resource de roles: detalle

Crear `Pages/ViewRole` y `Schemas/RoleInfolist` (nombre, descripción, guard, nº de usuarios y los permisos agrupados por área como badges con su etiqueta y la descripción en el tooltip) y registrar la página `view` y el infolist en `RoleResource`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-013

**Acceptance Criteria:**

1. La página `view` existe en `route:list` y el registro de la tabla enlaza a ella.
2. PHPStan pasa sobre `Roles/`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/ViewRole.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/Schemas/RoleInfolist.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/RoleResource.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Roles`

**Review:** claude

### TASK-015: Pruebas del listado y el detalle de roles

Crear `ListRolesTest` y `ViewRoleTest` en `Tests/Filament/Resources/Roles/Pages/`.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-014, TASK-005

**Acceptance Criteria:**

1. Un lector ve los 13 roles del catálogo con sus conteos correctos y la consulta del listado es constante (no crece con el nº de roles o usuarios); un administrador sin `admin.roles.viewAny` recibe 403.
2. El detalle muestra los permisos agrupados por área en inglés y en español; un rol fuera del catálogo se lista con 'No editable'.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages`

**Review:** claude

### TASK-016: Acción 'Editar permisos' en el detalle del rol

Agregar a `ViewRole` la acción de cabecera `editPermissions`: `CheckboxList` de `AdminPermission` agrupada por área (`label()` + `description()`), con el estado actual por defecto, más `currentPasswordField()`; `->authorize('update')`; confirma la contraseña, llama a `syncRolePermissions()` y notifica éxito con el nº de cambios, 'sin cambios' cuando no hay diferencia, o el fallo traducido de `AdminAuthorizationFailure`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-012, TASK-014

**Acceptance Criteria:**

1. Un gestor concede y quita permisos de `admin-posts-reader`; el rol queda con el conjunto enviado y hay una actividad por cambio con él como causante.
2. Con contraseña errónea no cambia nada; un lector no ve la acción y una llamada directa no escribe; un rol fuera del catálogo no muestra la acción.
3. Quitar `admin.access` del rol `admin` siendo el único administrador muestra la notificación de último acceso y no cambia nada.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleEditPermissionsActionTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/Pages/ViewRole.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleEditPermissionsActionTest.php`

**Review:** claude

### TASK-017: Pestaña de usuarios del rol

Crear `RelationManagers/RoleUsersRelationManager` (solo lectura, relación `users` del rol: nombre, email, estado de cuenta con `DescribesAccountStatus`, enlace al usuario si `UserResource::canView`) y registrarlo en `RoleResource::getRelations()`. Visible solo si el administrador puede `view` el rol y `viewAny` usuarios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-014

**Acceptance Criteria:**

1. Lista solo los usuarios con ese rol, sin acciones de crear, asociar ni quitar.
2. Oculta para un lector de roles sin `admin.users.viewAny`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManager.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/RoleResource.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php`

**Review:** claude

### TASK-018: Pestaña de historial del rol

Crear `RelationManagers/RoleActivityLogsRelationManager` reutilizando `ActivityLogsTable::configure($table, deferLoading: false)` sobre `ActivityLog::query()` con sujeto morph `role` e id del rol (eventos `role_permission_*`) y registrarlo en `RoleResource::getRelations()`. Visible solo con `viewAny` de `ActivityLog`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-017

**Acceptance Criteria:**

1. Muestra los `role_permission_granted/revoked` de ese rol, no los de otro rol.
2. Oculta para un administrador sin `admin.activity.viewAny`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RelationManagers/RoleActivityLogsRelationManager.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleActivityLogsRelationManagerTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/RoleResource.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleActivityLogsRelationManagerTest.php`

**Review:** claude

### TASK-019: Widgets del listado de roles

Crear `Widgets/RolesOverview` (StatsOverview: usuarios con `admin.access`, roles del catálogo sin usuarios, cambios de autorización —`role_*` y `role_permission_*`— en 30 días con sparkline diaria vía `BuildsDailyActivityCharts`) y `Widgets/UsersPerRoleChart` (ChartWidget de barras: usuarios por rol del catálogo, etiquetas traducidas), y mostrarlos con `getHeaderWidgets()` en `ListRoles`. Ambos `canView()` con `Gate` `viewAny` de `Role`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-013

**Acceptance Criteria:**

1. Los valores coinciden con los datos sembrados en la prueba (TASK-020).
2. Ningún widget se muestra a un administrador sin `admin.roles.viewAny`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Widgets/RolesOverview.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/Widgets/UsersPerRoleChart.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/Pages/ListRoles.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Roles`

**Review:** claude

### TASK-020: Pruebas de los widgets de roles

Crear `RolesOverviewTest` y `UsersPerRoleChartTest` en `Tests/Filament/Resources/Roles/Widgets/`.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-019, TASK-015

**Acceptance Criteria:**

1. Con 3 administradores, 1 rol vacío y 4 cambios en 30 días (uno a los 31), las estadísticas muestran 3, 1 y 4.
2. El gráfico tiene una barra por rol del catálogo con su etiqueta en español; un administrador sin permiso no ve ninguno.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/RolesOverviewTest.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/UsersPerRoleChartTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets`

**Review:** claude

### TASK-021: Matriz de permisos

Crear la página de recurso `Pages/RolePermissionMatrix` (`Filament\Resources\Pages\Page`, ruta `/matrix`, `canAccess` con `viewAny` de `Role`) y su vista `UI/Views/filament/roles/permission-matrix.blade.php`: filas = permisos agrupados por área, columnas = roles del catálogo, ✓ en cada cruce, encabezados con etiqueta y descripción en `title`; una sola consulta (`Role::with('permissions')`). Registrarla en `RoleResource::getPages()` y agregar en `ListRoles` una acción de cabecera que enlaza a ella.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-018, TASK-019

**Acceptance Criteria:**

1. La ruta `filament.admin.resources.roles.matrix` existe y la vista no contiene texto literal (todo `__()`).
2. PHPStan pasa sobre `Roles/`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/RolePermissionMatrix.php` (nuevo)
- `app/Modules/Admin/UI/Views/filament/roles/permission-matrix.blade.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Roles/RoleResource.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Roles`

**Review:** claude

### TASK-022: Pruebas de la matriz de permisos

Crear `RolePermissionMatrixTest`.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-021

**Acceptance Criteria:**

1. Marca exactamente los cruces del catálogo sembrado (p. ej. `admin-users-moderator` × `admin.users.suspend`) y no `admin-posts-reader` × `admin.users.suspend`.
2. Un administrador sin `admin.roles.viewAny` recibe 403; la página hace un número constante de consultas.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php`

**Review:** claude

### TASK-023: Acción 'Gestionar roles' en el detalle del usuario

Agregar a `ViewUser` la acción de cabecera `manageRoles`: `CheckboxList` de `AdminRole` (`label()` + `description()`) con los roles actuales por defecto, más `currentPasswordField()`; `->authorize('manageRoles')`; confirma la contraseña y llama a `syncUserRoles()`, con las mismas notificaciones que TASK-016. No toca las acciones de suspensión.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-011, TASK-012

**Acceptance Criteria:**

1. Un gestor asigna `admin-posts-reader` y quita `admin-users-reader` a otro usuario; hay una actividad por cambio.
2. La acción no aparece en su propia cuenta ni para un moderador; una llamada directa no escribe; con contraseña errónea no cambia nada.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserManageRolesActionTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Users/Pages/ViewUser.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserManageRolesActionTest.php app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserAccountStatusActionsTest.php`

**Review:** claude

### TASK-024: Pestaña de roles y permisos efectivos del usuario

Crear `Users/RelationManagers/RolesRelationManager` (solo lectura: rol, descripción y sus permisos como badges; visible con `view` del usuario y `viewAny` de `Role`) y registrarlo en `UserResource::getRelations()`. En `UserInfolist`, agregar una sección 'Permisos efectivos' (permisos que el usuario obtiene de sus roles, agrupados por área, calculados con `getAllPermissions()`), visible con `viewAny` de `Role`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-013

**Acceptance Criteria:**

1. La pestaña lista solo los roles del usuario, sin acciones de escritura, y se oculta sin `admin.roles.viewAny`.
2. La sección muestra la unión sin duplicados de los permisos de dos roles que comparten `admin.users.view`; un usuario sin roles muestra un estado vacío traducido.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/RolesRelationManager.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Users/UserResource.php`
- `app/Modules/Admin/Filament/Resources/Users/Schemas/UserInfolist.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Users`

**Review:** claude

### TASK-025: Pruebas de la pestaña de roles y los permisos efectivos

Crear `RolesRelationManagerTest` y actualizar `ViewUserTest` (su lista de campos permitidos del infolist incluye la sección nueva).

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-024, TASK-015

**Acceptance Criteria:**

1. Cubre los dos criterios de TASK-024 en inglés y español.
2. `ViewUserTest` sigue fallando si el infolist expone un campo fuera de la lista (2FA, contraseña).

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/RolesRelationManagerTest.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/RolesRelationManagerTest.php app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserTest.php`

**Review:** claude

### TASK-026: Columna y filtro de roles en el listado de usuarios

En `UsersTable`: precargar `roles` junto con `media`, columna 'Roles' (badges con `AdminRole::label()` o el nombre crudo, `toggleable`) y `SelectFilter` múltiple por rol del catálogo (`whereHas('roles', name in ...)` con guard `admin`), visibles solo con `viewAny` de `Role`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-013

**Acceptance Criteria:**

1. Filtrar por `admin-users-moderator` devuelve solo usuarios con ese rol; filtrar por dos roles devuelve la unión.
2. El nº de consultas del listado no crece con el nº de usuarios; sin `admin.roles.viewAny` no aparecen ni la columna ni el filtro.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersRolesTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersRolesTest.php app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php`

**Review:** claude

### TASK-027: Enlazar roles en el activity log y en los accesos rápidos

En `DescribesActivityLogParties::recordUrl()`, un `Role` enlaza a `RoleResource` cuando el administrador puede verlo. En `AdminQuickLinksWidget`, agregar el enlace 'Roles' cuando `Gate` permite `viewAny` de `Role` (incluido en `canView()`).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-014

**Acceptance Criteria:**

1. El sujeto de un `role_permission_granted` enlaza al detalle del rol para un lector de roles y no enlaza para quien no tiene permiso.
2. El widget muestra 'Roles' a un lector de roles sin otros permisos y no se lo muestra a un lector de posts.

**writeScope:**

- `app/Modules/Admin/Filament/Concerns/DescribesActivityLogParties.php`
- `app/Modules/Admin/Filament/Widgets/AdminQuickLinksWidget.php`
- `app/Modules/Admin/Tests/Filament/Widgets/AdminQuickLinksWidgetTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Widgets/AdminQuickLinksWidgetTest.php app/Modules/Admin/Tests/Filament/Resources/ActivityLogs`

**Review:** claude

### TASK-028: Eventos simulados de permisos de rol en ActivityLogSeeder

En `seedAdminActivity()`, agregar entre 3 y 8 cambios de permisos de rol hechos por Angel sobre roles del catálogo: pares revocar → volver a conceder (en ese orden y con fechas crecientes) para que el estado final coincida con el catálogo sembrado; sujeto el `Role`, propiedades `role` y `permission`, sin IP (consola).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Cada `role_permission_revoked` sembrado tiene un `role_permission_granted` posterior del mismo rol y permiso, y ambos apuntan a un `Role` existente.
2. Correr el seeder dos veces no duplica (regla actual de `activity_log` con filas).

**writeScope:**

- `app/Modules/ActivityLog/Database/Seeders/ActivityLogSeeder.php`
- `app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php`

**Review:** claude

### TASK-029: Cubrir Roles en la prueba de texto fijo del panel

Agregar `app/Modules/Admin/Filament/Resources/Roles` y `app/Modules/Admin/UI/Views/filament/roles/permission-matrix.blade.php` a `ADMIN_TRANSLATED_PATHS` en `AdminHardcodedTextTest` y al caso que verifica qué se escanea.

**Type:** test · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-016, TASK-018, TASK-020, TASK-022

**Acceptance Criteria:**

1. Pasa con todo `Roles/` y la vista de la matriz escaneados.
2. Falla si se agrega un `->label('Texto')` literal en un archivo de `Roles/`.

**writeScope:**

- `app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-030: Documentación

Actualizar CLAUDE.md (tabla de roles con `admin-roles-reader` y `admin-roles-manager`, lista de permisos y roles válidos de los comandos, sección de Admin: Resource de roles, matriz, widgets, acción en ViewUser, servicio compartido, `RolePolicy`, confirmación de contraseña; la frase 'Role and permission management remains command-line based' se reemplaza; volver a sembrar el catálogo en bases existentes), AGENTS.md (párrafo de bootstrap y gestión) y `docs/architecture/backend.md` (mismo contenido en español).

**Type:** docs · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-009, TASK-016, TASK-023

**Acceptance Criteria:**

1. Ningún documento dice que la gestión es solo por consola.
2. Los tres documentos nombran los mismos roles, permisos y el servicio, y explican cómo otorgar `admin-roles-manager` en producción con `admin:user-role grant`.

**writeScope:**

- `CLAUDE.md`
- `AGENTS.md`
- `docs/architecture/backend.md`

**validateCommand:** `rtk grep -n "remains command-line based" CLAUDE.md AGENTS.md docs/architecture/backend.md; test $? -eq 1`

**Review:** claude

### TASK-031: Gates finales de calidad

Sin escrituras salvo las que pidan los formateadores: Pint (`--dirty --format agent`), `composer rector-clear-cache` → `composer rector-dry` → `composer rector`, `composer phpstan` y `composer test` (en paralelo; no al mismo tiempo que la suite de SOC-68).

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002, TASK-003, TASK-005, TASK-007, TASK-009, TASK-016, TASK-017, TASK-018, TASK-020, TASK-022, TASK-023, TASK-025, TASK-026, TASK-027, TASK-028, TASK-029, TASK-030

**Acceptance Criteria:**

1. Pint, Rector (sin cambios pendientes tras aplicar), PHPStan max y la suite completa pasan.
2. Si Rector renombra variables o reescribe código, se revisa y se vuelve a correr la suite.

**validateCommand:** `rtk composer phpstan && rtk composer test`

**Review:** claude

## Failure Modes

- Dos administradores editan el mismo rol a la vez: los bloqueos `lockForUpdate` del servicio serializan; el segundo calcula su diferencia sobre el estado ya confirmado.
- Quitar `admin.access` al último usuario que lo tiene, desde un rol o desde un usuario: `AdminAuthorizationFailure::LastAdminAccess`, sin cambios ni actividad.
- Un gestor quita a su propio rol `admin.roles.update` o `admin.access`: `AdminAuthorizationFailure::ActorWouldLoseAccess`, rollback completo.
- Contraseña errónea o demasiados intentos: error de validación en `current_password`, nada se escribe.
- Base existente sin los roles nuevos: hay que volver a correr `AdminAuthorizationSeeder` (crea los roles con sus permisos la primera vez); se documenta en TASK-028.
- Caché de permisos obsoleta tras un cambio: el servicio la limpia después del commit; TASK-008 lo prueba con un `can()` inmediato.
- Rol fuera del catálogo en la base de datos: aparece en el listado y la matriz, sin acciones de edición.

## Ship Cut

Mínimo entregable: TASK-001 a TASK-016 (catálogo, traducciones, servicio, comandos refactorizados, políticas, Resource de roles con edición de permisos y acción 'Gestionar roles' en ViewUser). Los extras (TASK-017 a TASK-026) son independientes entre sí y pueden quedar para después; TASK-027 y TASK-028 cierran cualquier corte.

## Test Coverage Map

| Área | Tareas |
| --- | --- |
| Catálogo y seeders | TASK-001, TASK-002, TASK-003 |
| Traducciones | TASK-005, TASK-029 |
| Servicio: último admin.access, auditoría, caché | TASK-007 |
| Servicio: sincronización y auto-bloqueo | TASK-008 |
| Compatibilidad de comandos | TASK-009 |
| Políticas | TASK-010, TASK-011 |
| Confirmación de contraseña | TASK-012, TASK-016, TASK-023 |
| Resource de roles | TASK-015, TASK-016, TASK-017, TASK-018, TASK-020, TASK-022 |
| Users | TASK-023, TASK-025, TASK-026 |
| Activity log y accesos rápidos | TASK-027, TASK-028 |

## Execution Summary

- Tareas: 31
- Ruta crítica: TASK-001 → TASK-004 → TASK-013 → TASK-014 → TASK-017 → TASK-018 → TASK-021 → TASK-022 → TASK-029 → TASK-031
- Capas paralelas:

  1. TASK-001
  2. TASK-002, TASK-004, TASK-006, TASK-010, TASK-011, TASK-028
  3. TASK-003, TASK-005, TASK-007, TASK-008, TASK-009, TASK-012, TASK-013
  4. TASK-014, TASK-019, TASK-023, TASK-024, TASK-026
  5. TASK-015, TASK-016, TASK-017, TASK-027
  6. TASK-018, TASK-020, TASK-025, TASK-030
  7. TASK-021
  8. TASK-022
  9. TASK-029
  10. TASK-031

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-002 |
| TASK-004 | TASK-001 |
| TASK-005 | TASK-004 |
| TASK-006 | TASK-001 |
| TASK-007 | TASK-006 |
| TASK-008 | TASK-006 |
| TASK-009 | TASK-006 |
| TASK-010 | TASK-001 |
| TASK-011 | TASK-001 |
| TASK-012 | TASK-004 |
| TASK-013 | TASK-004, TASK-010 |
| TASK-014 | TASK-013 |
| TASK-015 | TASK-014, TASK-005 |
| TASK-016 | TASK-008, TASK-012, TASK-014 |
| TASK-017 | TASK-014 |
| TASK-018 | TASK-017 |
| TASK-019 | TASK-013 |
| TASK-020 | TASK-019, TASK-015 |
| TASK-021 | TASK-018, TASK-019 |
| TASK-022 | TASK-021 |
| TASK-023 | TASK-008, TASK-011, TASK-012 |
| TASK-024 | TASK-013 |
| TASK-025 | TASK-024, TASK-015 |
| TASK-026 | TASK-013 |
| TASK-027 | TASK-014 |
| TASK-028 | TASK-001 |
| TASK-029 | TASK-016, TASK-018, TASK-020, TASK-022 |
| TASK-030 | TASK-009, TASK-016, TASK-023 |
| TASK-031 | TASK-002, TASK-003, TASK-005, TASK-007, TASK-009, TASK-016, TASK-017, TASK-018, TASK-020, TASK-022, TASK-023, TASK-025, TASK-026, TASK-027, TASK-028, TASK-029, TASK-030 |
