# Plan: SOC-75: Detalles de asignación, MFA, filtros y retiro masivo en la pestaña de usuarios del rol

## Overview

Convertir la pestaña «Usuarios» del detalle de un rol en una herramienta de revisión de accesos: desde cuándo y por quién tiene cada usuario el rol, si tiene MFA del panel (sin él no puede entrar), su último acceso y sus otros roles; filtros para encontrar accesos que conviene retirar; y quitar el rol a varios usuarios a la vez. Como extras del modo EXPANSION, ordenar por fecha de asignación y un resumen arriba de la tabla con cuántos titulares no tienen MFA, no entran hace 90 días o están suspendidos.

**Linear:** [SOC-75](https://linear.app/social-hub-ang/issue/SOC-75/feature-enrich-the-role-users-tab-with-assignment-details-mfa-status) (sub-issue de [SOC-72](https://linear.app/social-hub-ang/issue/SOC-72/feature-improve-the-roles-module-of-the-admin-panel-pages-filters-and)) · **Modo:** EXPANSION · **Revisión predeterminada:** Claude

## Scope Challenge

EXPANSION con revisión de Claude. Decisiones del usuario (2026-11-06): «asignado entre fechas» filtra por la última asignación vigente, la misma que muestra la columna; quitar el rol a varios es todo o nada en una transacción, como «Agregar usuarios». Hallazgos: `RoleUsersRelationManager` define la tabla (nombre, correo, estado) y las acciones agregar/quitar en un solo archivo; `admin.role_granted` guarda el usuario como sujeto y el rol en `properties->role`, y SOC-73 dejó `Role::activityLogQuery()` con la regla del último `admin.role_deleted` del mismo nombre; `users.app_authentication_secret` (MFA de app de Filament, oculto) y `users.last_login_at` existen; `DescribesAccountStatus::accountStatusOptions()` y el filtro de roles de `UsersTable` son patrones reutilizables; ninguna tabla del panel usa aún acciones masivas.

## Prerequisites

- SOC-73 mergeado: `Role::activityLogQuery()` y la regla del último `admin.role_deleted` con el mismo nombre técnico.
- `AdminAuthorizationService::revokeRole(User, Role, User)` con sus protecciones (último `admin.access`, auto-bloqueo) y `ConfirmsCurrentPassword` (SOC-69).
- `UserPolicy::manageRoles` (requiere `admin.users.roles` y otra cuenta) y `ViewRole::ROLE_USERS_CHANGED_EVENT` (SOC-70).
- `users.app_authentication_secret` (oculto, MFA de app del panel) y `users.last_login_at`.

## Non-Goals

- Exportar la lista.
- Roles temporales o con vencimiento.
- Cambiar cómo se registran las asignaciones en el registro de actividad.
- Acciones masivas en otras tablas del panel.
- Mostrar el MFA de la aplicación (2FA de Fortify): la columna se refiere solo al MFA de app que exige el panel.

## Contracts

- `Role::grantActivityQuery(): Builder<ActivityLog>` devuelve los `admin.role_granted` de este rol (por `properties->role`) posteriores al último `admin.role_deleted` del mismo nombre; `activityLogQuery()` reutiliza esa misma regla sin cambiar su resultado.
- `Role::latestGrantsFor(array<int> $userIds): array<int, ActivityLog>` devuelve, en una consulta con `causer` cargado, la última asignación de cada usuario pedido, indexada por id de usuario; un usuario sin asignación registrada no aparece.
- `Role::latestGrantDateSubquery(): Builder<ActivityLog>` es una subconsulta correlacionada con `users.id` que devuelve la fecha de la última asignación; la tabla la agrega como `role_assigned_at` para mostrarla, ordenar y filtrar.
- «MFA del panel» es `app_authentication_secret !== null`; el secreto ni los códigos de recuperación salen nunca en la respuesta (solo un ícono o un booleano).
- `Roles/Tables/RoleUsersTable::configure(Table, Role, Closure $grantsByUser)` define columnas, filtros, orden y resumen; `RoleUsersRelationManager` conserva las acciones (agregar, quitar, quitar a varios) y la carga en lote de autores.
- La acción masiva «Quitar el rol» exige `admin.users.roles`, solo permite seleccionar usuarios que el administrador puede gestionar (`UserPolicy::manageRoles`, nunca su propia cuenta), pide la contraseña actual y llama a `revokeRole` por usuario dentro de una sola `DB::transaction`: ante un `AdminAuthorizationException` no cambia nada y notifica el motivo; al terminar emite `ViewRole::ROLE_USERS_CHANGED_EVENT`.
- Textos nuevos en `lang/{en,es}/admin/roles.php` bajo `relations.users` (claves exactas en TASK-002).

## Existing Code Leverage

- `Role::activityLogQuery()` (SOC-73) y su regla de nombres reutilizados.
- `ConfirmsCurrentPassword` y la acción «Agregar usuarios» (contraseña, transacción, notificación de fallo).
- `DescribesAccountStatus::accountStatusOptions()` y el filtro de estado de `UsersTable`.
- `UsersTable`: columna `last_login_at` con `admin/common.never` y el filtro múltiple de roles.
- `RoleActivityLogsRelationManager`: patrón de carga en lote por página a partir de `getTableRecords()`.
- `DescribesActivityLogParties::causerLabel()` para el autor de la asignación (incluido «Usuario eliminado #id»).

## Tasks

### TASK-001: Consultas de asignación del rol

En `App\Modules\Admin\Models\Role`, extraer la regla de asignaciones a `grantActivityQuery()` y agregar `latestGrantsFor(array $userIds)` y `latestGrantDateSubquery()` según el contrato, sin cambiar el resultado de `activityLogQuery()`. Pruebas en un archivo nuevo.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `latestGrantsFor()` devuelve la asignación más reciente de cada usuario (con su autor cargado) en una consulta, ignora las de otros roles y las anteriores a la eliminación de un rol con el mismo nombre, y omite a quien no tiene asignación registrada.
2. `latestGrantDateSubquery()` agregada a una consulta de usuarios da la fecha de la última asignación, o `null` sin asignación registrada.
3. `RoleActivityLogQueryTest` (SOC-73) sigue pasando sin cambios.

**writeScope:**

- `app/Modules/Admin/Models/Role.php`
- `app/Modules/Admin/Tests/Models/RoleGrantActivityTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Models/RoleGrantActivityTest.php app/Modules/Admin/Tests/Models/RoleActivityLogQueryTest.php`

**Review:** claude

### TASK-002: Textos de la pestaña de usuarios (en/es)

Agregar en `lang/{en,es}/admin/roles.php`, dentro de `relations.users`: `columns.{assigned_at,assigned_by,panel_mfa,last_login_at,other_roles}`; `not_recorded`; `mfa.{configured,missing}`; `filters.{status,panel_mfa,also_has_role,assigned_between,inactive_for}` y `filters.inactive_option` (`:days`, una sola clave para 30, 90 y 180); `bulk_remove.{label,modal_heading,modal_description,submit,success}` (`success` en plural con `:count`); `summary` (una frase con `:without_mfa`, `:inactive` y `:suspended`).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `AdminTranslationKeysTest` pasa: mismas claves, sin textos vacíos y mismos placeholders en `en` y `es`.
2. Las claves existentes de `relations.users` no cambian.

**writeScope:**

- `lang/en/admin/roles.php`
- `lang/es/admin/roles.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**Review:** claude

### TASK-003: Tabla propia de usuarios del rol con columnas nuevas

Crear `Roles/Tables/RoleUsersTable::configure()` moviendo las columnas actuales (nombre, correo, estado) y agregando: «Asignado el» (`role_assigned_at` de `latestGrantDateSubquery()`, ordenable, «Sin registro» si es nulo); «Asignado por» (de `$grantsByUser`, con `causerLabel()`/`causerUrl()`, «Sin registro» si no hay asignación); «MFA del panel» (ícono booleano a partir de `app_authentication_secret !== null`); «Último acceso» (`last_login_at`, ordenable, `admin/common.never`); «Otros roles» (badges con `displayName()` de los roles del guard `admin` distintos de este, con `roles` precargados). Las tres últimas son ocultables. `RoleUsersRelationManager` usa la tabla, conserva sus acciones y carga los autores una vez por página con `latestGrantsFor()` a partir de `getTableRecords()`.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001, TASK-002

**Acceptance Criteria:**

1. Asignar el rol desde el panel muestra la fecha y el autor en la fila; un usuario con el rol sin asignación registrada muestra «Sin registro»; ordenar por «Asignado el» funciona en ambos sentidos.
2. La columna de MFA muestra configurado o no sin que el HTML contenga el secreto ni los códigos de recuperación; «Otros roles» no incluye el rol actual ni roles de otro guard.
3. El número de consultas es el mismo con 10 y con 50 filas, y agregar, quitar y sus pruebas existentes siguen pasando.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php`
- `app/Modules/Admin/Filament/Resources/Roles/Tables/RoleUsersTable.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-004: Filtros de la pestaña de usuarios

En `RoleUsersTable`, agregar filtros: estado de la cuenta (`accountStatusOptions()`); MFA del panel (`TernaryFilter` sobre `app_authentication_secret`); «Tiene además el rol» (múltiple, roles del guard `admin` distintos de este, `whereHas`); «Asignado entre» (desde/hasta sobre la última asignación, con `latestGrantDateSubquery()` en un `where`); «Sin acceso en» (30, 90 o 180 días: `last_login_at` nulo o anterior).

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. Cada filtro deja solo las filas que corresponden, y combinados se aplican todos (prueba con al menos tres juntos).
2. «Sin acceso en 90 días» incluye a quien nunca entró; «Asignado entre» usa la última asignación: un usuario asignado en el rango y reasignado después queda fuera, y uno sin asignación registrada no aparece.
3. «Tiene además el rol» no ofrece el rol actual ni roles de otro guard.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Tables/RoleUsersTable.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersFiltersTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersFiltersTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-005: Quitar el rol a varios usuarios a la vez

En `RoleUsersRelationManager`, agregar la acción masiva «Quitar el rol» según el contrato: visible con `admin.users.roles`, filas seleccionables solo si `mayManageRolesOf()`, campo de contraseña con su propio prefijo de límite, una transacción con `revokeRole()` por usuario, notificación con la cantidad o con el motivo del fallo, deselección al terminar y `ViewRole::ROLE_USERS_CHANGED_EVENT`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. Con la contraseña correcta quita el rol a los seleccionados y registra un `admin.role_revoked` por usuario; con una incorrecta o sin intentos no quita a nadie.
2. Si un retiro falla (por ejemplo, dejaría el sistema sin `admin.access`) no quita a nadie y muestra el motivo; la cuenta propia del administrador no se puede seleccionar ni se quita aunque llegue en una llamada directa.
3. Sin `admin.users.roles` la acción no se ve y una llamada directa no escribe nada.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersBulkRemoveTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersBulkRemoveTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersRelationManagerTest.php`

**Review:** claude

### TASK-006: Resumen de titulares arriba de la tabla

En `RoleUsersTable`, agregar una descripción a la tabla con la frase `relations.users.summary`: cuántos titulares del rol no tienen MFA del panel, cuántos no entran hace 90 días o nunca, y cuántos están suspendidos, calculado con una sola consulta agregada sobre los titulares (sin aplicar los filtros de la tabla). Sin titulares no se muestra.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-004

**Acceptance Criteria:**

1. La frase muestra las tres cantidades correctas para un rol con titulares mezclados, en `en` y `es`.
2. Un rol sin titulares no muestra el resumen, y calcularlo agrega una sola consulta.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Tables/RoleUsersTable.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersSummaryTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleUsersSummaryTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-007: Documentación de la pestaña de usuarios

Actualizar `docs/architecture/modules/admin.md`: las consultas de asignación de `Role`, `RoleUsersTable` (columnas, filtros, orden, resumen), la acción masiva (todo o nada, contraseña, selección) y los textos y pruebas nuevos; ajustar la descripción de `RoleUsersRelationManager` de SOC-70.

**Type:** docs · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-005, TASK-006

**Acceptance Criteria:**

1. La documentación describe la pestaña como quedó y no contradice la de SOC-70.
2. Los nombres de clases y métodos citados existen en el código (verificado con `grep`).

**writeScope:**

- `docs/architecture/modules/admin.md`

**validateCommand:** `rtk grep -n "latestGrantsFor\|RoleUsersTable" docs/architecture/modules/admin.md`

**Review:** claude

### TASK-008: Gates finales y prueba en el navegador

Pint (`--dirty --format agent`), `composer rector-clear-cache` → `rector-dry` → `rector` sobre todo el proyecto, `composer phpstan`, `composer test` y `composer doctor`. Prueba manual en `/admin/roles/{id}` con datos sembrados: columnas, orden, cada filtro, resumen y quitar el rol a varios (y restaurarlo).

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-005, TASK-006, TASK-007

**Acceptance Criteria:**

1. Pint, Rector (sin pendientes), PHPStan max, la suite completa y `composer doctor` pasan.
2. La pestaña funciona en el navegador sin errores de consola ni de Laravel.

**writeScope:**

- (ninguno: solo comandos)

**validateCommand:** `rtk composer phpstan && rtk composer test && rtk composer doctor`

**Review:** claude

## Failure Modes

- Un usuario tiene el rol pero ninguna asignación registrada (datos sembrados sin actividad, o asignado antes del registro): columnas «Sin registro», queda fuera de «Asignado entre» y se puede ordenar (los nulos al final o al principio según el sentido).
- El autor de la asignación fue eliminado: «Usuario eliminado #id».
- Otro administrador quita el rol a un usuario seleccionado antes de enviar la acción masiva: `revokeRole` devuelve que no cambió nada y la cantidad notificada lo refleja.
- La acción masiva incluye al último titular de `admin.access`: `last_admin_access`, no cambia nada y se muestra el motivo.
- Un rol personalizado reutiliza el nombre de uno eliminado: la regla del último `admin.role_deleted` evita mostrar asignaciones del rol anterior.

## Ship Cut

Mínimo entregable: TASK-001 a TASK-005 y TASK-008 (consultas, textos, columnas, filtros y retiro masivo). TASK-006 (resumen) es el extra del modo EXPANSION, junto con el orden por fecha de asignación de TASK-003; se puede dejar para después sin romper nada. TASK-007 documenta lo entregado.

## Test Coverage Map

| Área | Tareas |
| --- | --- |
| Consultas de asignación | TASK-001 |
| Traducciones | TASK-002, TASK-003, TASK-004, TASK-006 |
| Columnas y carga en lote | TASK-003 |
| Filtros | TASK-004 |
| Retiro masivo | TASK-005 |
| Resumen | TASK-006 |

## Execution Summary

- Tareas: 8
- Ruta crítica: TASK-001 → TASK-003 → TASK-004 → TASK-006 → TASK-007 → TASK-008
- Capas paralelas:

  1. TASK-001, TASK-002
  2. TASK-003
  3. TASK-004, TASK-005
  4. TASK-006
  5. TASK-007
  6. TASK-008

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | TASK-001, TASK-002 |
| TASK-004 | TASK-003 |
| TASK-005 | TASK-003 |
| TASK-006 | TASK-004 |
| TASK-007 | TASK-005, TASK-006 |
| TASK-008 | TASK-005, TASK-006, TASK-007 |
