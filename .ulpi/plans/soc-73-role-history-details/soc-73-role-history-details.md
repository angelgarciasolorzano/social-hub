# Plan: SOC-73: Detalle legible, filtros y agrupación por día en el historial del rol

## Overview

Convertir la pestaña «Historial» del detalle de un rol en una auditoría legible: incluir las asignaciones y retiros del rol a usuarios (hoy invisibles porque se guardan con el usuario como sujeto) y la promoción inicial `admin.bootstrapped`, describir cada cambio en texto traducido, filtrar por tipo de cambio, autor y fecha, y agrupar por día. Además, la columna «Sobre» de todas las tablas de actividad muestra el nombre del rol en vez de `Rol #id`, y el detalle de una actividad muestra el mismo resumen.

**Linear:** [SOC-73](https://linear.app/social-hub-ang/issue/SOC-73/feature-show-readable-details-filters-and-day-grouping-in-the-role) (sub-issue de [SOC-72](https://linear.app/social-hub-ang/issue/SOC-72/feature-improve-the-roles-module-of-the-admin-panel-pages-filters-and)) · **Modo:** EXPANSION · **Revisión predeterminada:** Claude

## Scope Challenge

EXPANSION con revisión de Claude. Decisiones del usuario (2026-11-06): el helper de «última asignación» (fecha y autor) se mueve a SOC-75, donde se usa; SOC-73 solo crea la consulta de actividad del rol. El nombre del rol en «Sobre» se aplica a todas las tablas de actividad (`DescribesActivityLogParties`). Extras del modo EXPANSION: incluir `admin.bootstrapped` en el historial y mostrar el resumen legible también en el detalle de una actividad (`ActivityLogInfolist`). Hallazgos: `RoleActivityLogsRelationManager` reutiliza `ActivityLogsTable` sobre `morphMany(ActivityLog, 'subject')`, así que solo ve cambios de permisos y del ciclo de vida; `admin.role_granted`/`role_revoked` guardan el usuario como sujeto y el rol solo en `properties->role` (nombre técnico); `admin.bootstrapped` guarda la lista en `properties->roles`; `properties` es una columna JSON; los filtros de autor y fecha de `ActivityLogsTable` son privados o están en línea.

## Prerequisites

- SOC-70 mergeado: modelo `App\Modules\Admin\Models\Role` con `displayName()`, eventos `admin.role_created|updated|duplicated|deleted` y sus propiedades (`role`, `permissions`, `changed_attributes`, `source_role_id`).
- `AdminAuthorizationService` registra `admin.role_granted`/`role_revoked` con el usuario como sujeto y `['role' => <nombre técnico>]`, y `admin.role_permission_granted|revoked` con el rol como sujeto y `['role', 'permission']` (`AdminAuthorizationService.php:72`, `:107`, `:485`).
- `AdminBootstrapCommand` registra `admin.bootstrapped` con `['roles' => list<string>]`.
- `ActivityLogsTable` y `DescribesActivityLogParties` (SOC-56) cargan `causer` y `subject` en lote.

## Non-Goals

- Cambiar cómo se guarda la actividad (por ejemplo, agregar `role_id` a las asignaciones) o migrar datos de actividad.
- El helper de la última asignación de un rol a un usuario (fecha y autor): pasa a SOC-75.
- Reconstruir qué permisos tenía un rol en una fecha pasada.
- Una vista de línea de tiempo distinta de la tabla.
- Cambiar la tabla general del registro de actividad más allá de la columna «Sobre» y de exponer sus filtros de autor y fecha.

## Contracts

- `Role::activityLogQuery(): Builder<ActivityLog>` devuelve la actividad del rol: (a) toda actividad cuyo sujeto es el rol (`subject_type = 'role'`, `subject_id = id`); (b) `admin.role_granted` y `admin.role_revoked` con `properties->role` igual al `name` del rol; (c) `admin.bootstrapped` cuyo `properties->roles` contiene el `name`. Para (b) y (c) solo cuentan los registros con `id` mayor que el último `admin.role_deleted` con ese mismo `properties->role`, así un rol personalizado nuevo que reutiliza el nombre de uno eliminado no hereda su historial. No se filtra por `created_at` del rol, porque los seeders fechan la actividad en el pasado.
- La consulta vive en el módulo Admin (`Role`), no en `ActivityLog`: Admin ya depende de ActivityLog y no al revés.
- `RoleActivityChangeType` (`app/Modules/Admin/Enums/`) con casos `permissions` (`role_permission_granted|revoked`), `users` (`role_granted|revoked`, `bootstrapped`) y `lifecycle` (`role_created|updated|duplicated|deleted`); `events(): list<ActivityLogAdminEvent>`, `label(): string` y `static forEvent(string $event): ?self`.
- `DescribesRoleActivity::roleActivityDetail(ActivityLog $activityLog, array<int, string> $roleNamesById): string` devuelve un texto traducido por evento, sin texto libre: nombre del permiso (`AdminPermission::label()`), nombre del usuario sujeto (o `Usuario eliminado #id`), cantidad de permisos al crear, atributos editados traducidos, rol de origen al duplicar (de `$roleNamesById`, o `Rol #id (eliminado)`). Un evento desconocido, sin propiedades o con un permiso fuera del catálogo devuelve `admin/roles.history.detail.unknown` sin lanzar.
- `DescribesActivityLogParties::subjectLabel()` muestra `Role::displayName()` cuando el sujeto es un `Role` existente; un rol eliminado sigue mostrando `<Tipo> #id (eliminado)`. Aplica a todas las tablas y detalles de actividad.
- `ActivityLogsTable::causerFilter()` y `ActivityLogsTable::dateRangeFilter()` son estáticos públicos y la tabla general los usa sin cambiar su comportamiento.
- Textos nuevos en `lang/{en,es}/admin/roles.php` bajo `history.*` (claves exactas en TASK-003).

## Existing Code Leverage

- `ActivityLogsTable` (columnas de fecha, evento y autor, filtros de autor y fecha, acción Ver).
- `DescribesActivityLogParties` (autor y sujeto por tipo e id, con enlace solo si el administrador puede abrirlo).
- `ActivityLogEventCatalog::label()` para el nombre del evento.
- `AdminPermission::label()` y `Role::displayName()` para los nombres traducidos.
- `Users/RelationManagers/ActivityLogsRelationManager`: patrón de pestaña que reemplaza la consulta con `->query()`.
- `ActivityLogSeeder` ya siembra eventos de rol con las propiedades reales, útil para la prueba en el navegador.

## Tasks

### TASK-001: Consulta de actividad del rol

Agregar a `App\Modules\Admin\Models\Role` el método `activityLogQuery(): Builder<ActivityLog>` según el contrato: sujeto el rol, o `admin.role_granted`/`role_revoked` con `properties->role` igual al `name`, o `admin.bootstrapped` con `properties->roles` que contiene el `name` (`whereJsonContains`), solo después del último `admin.role_deleted` con ese nombre (subconsulta por `max(id)`). Los eventos se nombran con `ActivityLogAdminEvent`. No hace falta filtrar por guard: los eventos de rol solo existen para el guard `admin`. Pruebas en un archivo nuevo.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Incluye los cambios de permisos y del ciclo de vida del rol, sus asignaciones y retiros a usuarios y la promoción `admin.bootstrapped` que lo incluye; excluye la actividad de otros roles y otros eventos del mismo usuario.
2. Un rol personalizado creado con el nombre técnico de uno eliminado no incluye las asignaciones anteriores a esa eliminación, pero sí las suyas.
3. Una actividad sin propiedades o con `properties->roles` que no es una lista no rompe la consulta.

**writeScope:**

- `app/Modules/Admin/Models/Role.php`
- `app/Modules/Admin/Tests/Models/RoleActivityLogQueryTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Models/RoleActivityLogQueryTest.php app/Modules/Admin/Tests/Models/RoleTest.php`

**Review:** claude

### TASK-002: «Sobre» muestra el nombre del rol en todas las tablas de actividad

En `DescribesActivityLogParties::subjectLabel()`, cuando el sujeto cargado es un `Role`, devolver `displayName()` en lugar de `<Tipo> #id`. El rol eliminado (sujeto sin cargar) mantiene el texto actual. El enlace no cambia. Cubrirlo en la prueba de la lista general del registro de actividad.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**

1. En la lista del registro de actividad, un cambio de permisos de un rol de sistema muestra su nombre traducido y el de un rol personalizado su `label`, con enlace al rol.
2. Un rol eliminado sigue mostrando `Rol #id (eliminado)` sin enlace.
3. Los sujetos de otros tipos (usuario, publicación, comentario) no cambian.

**writeScope:**

- `app/Modules/Admin/Filament/Concerns/DescribesActivityLogParties.php`
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php`

**Review:** claude

### TASK-003: Textos del historial (en/es)

Agregar en `lang/{en,es}/admin/roles.php`, dentro de `relations.history`: `columns.{change_type,detail}`; `change_types.{permissions,users,lifecycle}`; `filters.change_type`; `group.day`; `detail.permission_granted` y `permission_revoked` (`:permission`), `role_granted` y `role_revoked` (`:user`), `bootstrapped` (`:user`), `created` (plural `:count` permisos), `updated` (`:attributes`), `attributes.{label,description}`, `duplicated` (`:role`), `deleted` y `unknown`. Mismas claves y placeholders en ambos idiomas.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `AdminTranslationKeysTest` pasa: mismas claves, sin textos vacíos y mismos placeholders en `en` y `es`.
2. Las claves existentes de `relations.history` (`title`) no cambian.

**writeScope:**

- `lang/en/admin/roles.php`
- `lang/es/admin/roles.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**Review:** claude

### TASK-004: Tipos de cambio y descripción legible de la actividad de un rol

Crear `App\Modules\Admin\Enums\RoleActivityChangeType` y el trait `App\Modules\Admin\Filament\Concerns\DescribesRoleActivity` según el contrato. El detalle lee solo `event`, `properties` (`permission`, `permissions`, `changed_attributes`, `source_role_id`) y el sujeto ya cargado; nunca consulta la base: el nombre del rol de origen llega en `$roleNamesById`. Pruebas unitarias con actividades de la factory (`ActivityLogFactory::forEvent()`, `performedOn()`).

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. Cada evento de rol (`role_created`, `role_updated`, `role_duplicated`, `role_deleted`, `role_permission_granted`, `role_permission_revoked`, `role_granted`, `role_revoked`, `bootstrapped`) produce su texto en `en` y `es`, y `forEvent()` lo clasifica en un solo tipo de cambio; los demás eventos devuelven `null`.
2. Un permiso fuera del catálogo, propiedades ausentes o un evento ajeno devuelven el texto `unknown` sin excepción; un usuario sujeto eliminado se describe como `Usuario eliminado #id`; un rol de origen ausente de `$roleNamesById`, como `Rol #id (eliminado)`.
3. Describir una actividad no ejecuta consultas.

**writeScope:**

- `app/Modules/Admin/Enums/RoleActivityChangeType.php`
- `app/Modules/Admin/Filament/Concerns/DescribesRoleActivity.php`
- `app/Modules/Admin/Tests/Filament/Concerns/DescribesRoleActivityTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Concerns/DescribesRoleActivityTest.php`

**Review:** claude

### TASK-005: Exponer los filtros de autor y fecha de la tabla de actividad

Mover el filtro de autor (select buscable de usuarios con `filterByCauser`) y el de rango de fechas de `ActivityLogsTable::configure()` a los métodos estáticos públicos `causerFilter(): Filter` y `dateRangeFilter(): Filter`, y usarlos en la propia tabla. Sin cambios de comportamiento.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `ListActivityLogsTest` y la pestaña Actividad de usuarios pasan sin cambios en sus pruebas.
2. Los filtros conservan sus nombres (`causer`, `created_at`), así que una URL con filtros guardados sigue funcionando.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/ActivityLogs/Tables/ActivityLogsTable.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php app/Modules/Admin/Tests/Filament/Resources/Users`

**Review:** claude

### TASK-006: Tabla propia del historial del rol

Crear `Roles/Tables/RoleActivityLogsTable::configure(Table, Role)`: columnas Fecha, Tipo de cambio (badge con `RoleActivityChangeType::label()`), Evento, Detalle (`roleActivityDetail()`), Realizado por y Sobre; filtros Tipo de cambio (`SelectFilter` múltiple por los eventos del tipo), `ActivityLogsTable::causerFilter()` y `dateRangeFilter()`; agrupación por día (`Group::make('created_at')->date()->collapsible()`, activa por defecto); acción Ver al detalle de la actividad; `with(['causer', 'subject'])`. Los nombres de los roles de origen de los duplicados se cargan una vez por página a partir de los registros visibles (`$livewire->getTableRecords()`) y se memorizan durante la petición. `RoleActivityLogsRelationManager` usa `->query(fn () => $role->activityLogQuery())` como la pestaña Actividad de usuarios y esta tabla; mantiene `canViewForRecord()`.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001, TASK-002, TASK-004, TASK-005

**Acceptance Criteria:**

1. La pestaña muestra las asignaciones y retiros del rol a usuarios y la promoción inicial, con el usuario en el detalle, además de los cambios de permisos y del ciclo de vida, y agrupa las filas por día.
2. Los filtros por tipo de cambio, autor y fecha funcionan solos y combinados; un tipo sin actividad muestra el estado vacío.
3. El número de consultas al renderizar la tabla es el mismo con 10 y con 50 filas (incluidos duplicados y asignaciones), y un administrador sin `admin.activity.viewAny` sigue sin ver la pestaña.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RelationManagers/RoleActivityLogsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleActivityLogsRelationManagerTest.php`
- `app/Modules/Admin/Filament/Resources/Roles/Tables/RoleActivityLogsTable.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/RelationManagers/RoleActivityLogsRelationManagerTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-007: Resumen legible en el detalle de una actividad de rol

En `ActivityLogInfolist`, sección Actividad, agregar la entrada «Detalle» (`admin/roles.relations.history.columns.detail`) visible solo cuando `RoleActivityChangeType::forEvent()` reconoce el evento, con `roleActivityDetail()`; el nombre del rol de origen de un duplicado se resuelve con una sola consulta. La sección Detalles con las propiedades crudas no cambia.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-004

**Acceptance Criteria:**

1. El detalle de un `admin.role_permission_granted` muestra «Agregó el permiso …» y el de un duplicado nombra el rol de origen.
2. El detalle de una actividad que no es de roles (por ejemplo `content.post_created`) no muestra la entrada.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/ActivityLogs/Schemas/ActivityLogInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ViewActivityLogTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ViewActivityLogTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-008: Documentación del historial del rol

Actualizar `docs/architecture/modules/admin.md`: `Role::activityLogQuery()` y su regla de nombres reutilizados, `RoleActivityLogsTable` (columnas, filtros, agrupación, carga en lote), `RoleActivityChangeType`, `DescribesRoleActivity`, los filtros públicos de `ActivityLogsTable`, el nombre del rol en «Sobre» y la entrada «Detalle» del infolist. Reemplazar la frase que dice que la pestaña construye `morphMany(ActivityLog::class, 'subject')`.

**Type:** docs · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-006, TASK-007

**Acceptance Criteria:**

1. Ningún texto de `docs/` describe ya la pestaña Historial como «solo la actividad cuyo sujeto es el rol».
2. Los nombres de clases y métodos citados existen en el código (verificado con `grep`).

**writeScope:**

- `docs/architecture/modules/admin.md`

**validateCommand:** `rtk grep -n "activityLogQuery\|RoleActivityLogsTable\|DescribesRoleActivity" docs/architecture/modules/admin.md`

**Review:** claude

### TASK-009: Gates finales y prueba en el navegador

Pint (`--dirty --format agent`), `composer rector-clear-cache` → `rector-dry` → `rector` sobre todo el proyecto, `composer phpstan`, `composer test` y `composer doctor`. Prueba manual en `/admin/roles/{id}` con datos sembrados: asignar y quitar un usuario, cambiar permisos, filtrar y plegar grupos; revisar el detalle de una actividad de rol y la columna «Sobre» del registro de actividad.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-006, TASK-007, TASK-008

**Acceptance Criteria:**

1. Pint, Rector (sin pendientes), PHPStan max, la suite completa y `composer doctor` pasan.
2. El historial funciona en el navegador sin errores de consola ni de Laravel, en español y en inglés.

**writeScope:**

- (ninguno: solo comandos)

**validateCommand:** `rtk composer phpstan && rtk composer test && rtk composer doctor`

**Review:** claude

## Failure Modes

- Un rol personalizado se elimina y otro se crea con el mismo nombre técnico: la regla del último `admin.role_deleted` evita mezclar historiales; los cambios de permisos no se ven afectados porque usan el id del rol como sujeto.
- Un usuario asignado se elimina: la fila sigue, con `Usuario eliminado #id` en el detalle y en «Sobre».
- El rol de origen de un duplicado se eliminó: el detalle dice `Rol #id (eliminado)`.
- Un permiso deja de existir en `AdminPermission`: el detalle usa el texto `unknown` en vez de lanzar.
- Actividad sembrada sin `admin.role_created` para roles de sistema: el historial muestra igual sus asignaciones y cambios de permisos.
- La agrupación por día exige ordenar primero por fecha: el orden por `id` descendente se mantiene dentro de cada día.

## Ship Cut

Mínimo entregable: TASK-001 a TASK-006 y TASK-009 (consulta, nombre en «Sobre», textos, descripción, filtros y la tabla nueva). TASK-007 (resumen en el detalle de una actividad) es el extra del modo EXPANSION y se puede dejar para después sin romper nada; TASK-008 documenta lo que se haya entregado.

## Test Coverage Map

| Área | Tareas |
| --- | --- |
| Consulta de actividad del rol | TASK-001 |
| Columna «Sobre» global | TASK-002 |
| Traducciones | TASK-003, TASK-006, TASK-007 |
| Descripción y tipos de cambio | TASK-004 |
| Filtros compartidos | TASK-005 |
| Pestaña Historial | TASK-006 |
| Detalle de actividad | TASK-007 |

## Execution Summary

- Tareas: 9
- Ruta crítica: TASK-003 → TASK-004 → TASK-006 → TASK-008 → TASK-009
- Capas paralelas:

  1. TASK-001, TASK-002, TASK-003, TASK-005
  2. TASK-004
  3. TASK-006, TASK-007
  4. TASK-008
  5. TASK-009

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | — |
| TASK-004 | TASK-003 |
| TASK-005 | — |
| TASK-006 | TASK-001, TASK-002, TASK-004, TASK-005 |
| TASK-007 | TASK-004 |
| TASK-008 | TASK-006, TASK-007 |
| TASK-009 | TASK-006, TASK-007, TASK-008 |
