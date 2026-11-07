# Plan: SOC-76: Filtros por permiso, agrupación, columnas nuevas y comparación en la tabla de roles

## Overview

Hacer que la tabla de roles responda preguntas de auditoría: qué roles tienen un permiso, cubren un área, conceden permisos sensibles o no tienen usuarios, y cuándo y quién los cambió por última vez. Se agrupa por tipo (sistema / personalizado), se agregan las columnas de áreas cubiertas, último cambio y creado por, y la matriz de permisos pasa a servir para comparar de 2 a 4 roles con «Solo diferencias». Como extras del modo EXPANSION: comparar los roles seleccionados en la tabla con una acción masiva, y marcar los permisos sensibles en la matriz.

**Linear:** [SOC-76](https://linear.app/social-hub-ang/issue/SOC-76/feature-add-permission-filters-grouping-new-columns-and-role) (sub-issue de [SOC-72](https://linear.app/social-hub-ang/issue/SOC-72/feature-improve-the-roles-module-of-the-admin-panel-pages-filters-and)) · **Modo:** EXPANSION · **Revisión predeterminada:** Claude

## Scope Challenge

EXPANSION con revisión de Claude. Decisiones del usuario (2026-11-07): «último cambio» cuenta solo la actividad cuyo sujeto es el rol (creación, edición de datos, duplicado y cambios de permisos), no las asignaciones a usuarios; «Comparar roles» se hace en la matriz de permisos, con la selección en la URL para compartirla. Extras elegidos al planificar: acción masiva «Comparar seleccionados» en la tabla y la marca de permisos sensibles en las filas de la matriz. Hallazgos: `RolesTable` solo filtra por tipo y carga `withCount(['permissions', 'users'])`; `RolePermissionMatrix` es una página con vista Blade propia (`admin::filament.roles.permission-matrix`) que muestra todos los roles; `AdminPermission::isSensitive()` (SOC-74) y `Role::activityLogQuery()` (SOC-73) existen; la tabla no tiene acciones masivas ni grupos.

## Prerequisites

- SOC-73 mergeado: los cambios de un rol se registran con el rol como sujeto (`admin.role_created|updated|duplicated|deleted`, `admin.role_permission_granted|revoked`).
- SOC-74 mergeado: `AdminPermission::isSensitive()` y `sensitive()`.
- `RolePermissionMatrix` y su vista Blade (SOC-69/70) con `getMatrixRoles()` y `getMatrixAreas()`.

## Non-Goals

- Acciones masivas que cambien roles: `deleteAny` sigue denegado y la única acción masiva es comparar.
- Editar permisos desde la comparación.
- Contar las asignaciones de usuarios como «último cambio».
- Guardar filtros favoritos o exportar la comparación.

## Contracts

- `Role::lastChangeDateSubquery(): Builder<ActivityLog>` es una subconsulta correlacionada con `roles.id` que da la fecha de la última actividad cuyo sujeto es el rol; `Role::latestChangesFor(array<int> $roleIds): array<int, ActivityLog>` da esa última actividad con su autor, y `Role::creationsFor(array<int> $roleIds): array<int, ActivityLog>` el `admin.role_created` de cada rol con su autor; ambas en una consulta más la de autores, indexadas por id de rol.
- Filtros de `RolesTable`: «Incluye el permiso» (múltiple, agrupado por área; el rol debe tener todos los elegidos), «Cubre el área» (al menos un permiso del área), «Con permisos sensibles» (ternario), «Con usuarios» (ternario) y «Último cambio entre» (desde/hasta sobre `lastChangeDateSubquery()`; un rol sin actividad registrada queda fuera).
- `RolesTable` agrupa por tipo con títulos traducidos (Sistema / Personalizado), agrega «Áreas» (badges con los nombres de área de sus permisos, con `permissions` precargados), «Último cambio» (fecha ordenable con el autor debajo, o `not_recorded`) y «Creado por» (solo roles personalizados; `not_recorded` si no hay `admin.role_created`). Fechas y autores se cargan una vez por página: el número de consultas no depende de la cantidad de roles.
- La matriz acepta `?roles[]=<nombre>&only_differences=1`: con 2 a 4 roles del guard `admin` muestra solo esos (en orden de visualización); con «Solo diferencias» oculta las filas en que todos coinciden y las áreas que quedan vacías. Una selección de menos de 2, más de 4 o con nombres desconocidos se ignora y muestra la matriz completa con un aviso; la página sigue autorizada con `viewAny` de roles.
- `ListRoles` agrega «Comparar roles» (enlace a la matriz) y `RolesTable` la acción masiva «Comparar seleccionados» (2 a 4 filas; con otra cantidad notifica y no navega).
- Textos nuevos en `lang/{en,es}/admin/roles.php` (claves exactas en TASK-002).

## Existing Code Leverage

- `RolesTable`: filtro de tipo, búsqueda y `withCount`.
- `Role::latestGrantsFor()` / `latestGrantDateSubquery()` (SOC-75): patrón de consultas de actividad en lote y subconsulta correlacionada.
- `RoleUsersTable` + `RoleUsersRelationManager` (SOC-75): filtro de fechas sobre una subconsulta con `mergeWheres` y carga en lote desde `getTableRecords()`.
- `RolePermissionMatrix` y su vista: tabla de permisos por área contra roles.
- `DescribesActivityLogParties::causerLabel()` para el autor (incluido «Usuario eliminado #id»).

## Tasks

### TASK-001: Consultas de cambios y creación de un rol

Agregar a `Role` `lastChangeDateSubquery()`, `latestChangesFor(array $roleIds)` y `creationsFor(array $roleIds)` según el contrato, construyendo el builder de Eloquent en una variable como en SOC-75. Pruebas en un archivo nuevo.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `latestChangesFor()` da el último cambio de cada rol (creación, edición, permisos) con su autor, ignora asignaciones y retiros de usuarios y omite los roles sin actividad; carga varios roles en dos consultas.
2. `creationsFor()` da el `admin.role_created` de cada rol personalizado y nada para un rol de sistema sembrado sin actividad.
3. `lastChangeDateSubquery()` agregada a una consulta de roles da la fecha del último cambio o `null`.

**writeScope:**

- `app/Modules/Admin/Models/Role.php`
- `app/Modules/Admin/Tests/Models/RoleChangeActivityTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Models`

**Review:** claude

### TASK-002: Textos de filtros, columnas y comparación (en/es)

Agregar en `lang/{en,es}/admin/roles.php`: `table.{areas,last_changed_at,created_by,not_recorded}`; `table.filters.{includes_permission,covers_area,sensitive,with_users,last_changed_between,last_changed_from,last_changed_until}` y sus etiquetas de opciones ternarias (`has_sensitive`, `no_sensitive`, `has_users`, `no_users`); `table.groups.type`; `compare.{action,bulk_action,roles,roles_helper,only_differences,invalid_selection,wrong_count}` (`:min`, `:max`); `matrix.sensitive`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `AdminTranslationKeysTest` pasa: mismas claves, sin textos vacíos y mismos placeholders en `en` y `es`.
2. Las claves existentes de `table`, `matrix` y `types` no cambian.

**writeScope:**

- `lang/en/admin/roles.php`
- `lang/es/admin/roles.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**Review:** claude

### TASK-003: Filtros de la tabla de roles

Agregar a `RolesTable` los filtros del contrato: «Incluye el permiso» (`SelectFilter` múltiple con opciones agrupadas por área, un `whereHas` por permiso elegido), «Cubre el área», «Con permisos sensibles» y «Con usuarios» (`TernaryFilter`), y «Último cambio entre» (desde/hasta comparando `lastChangeDateSubquery()` con `mergeWheres`, sin SQL crudo). El filtro de tipo se conserva.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001, TASK-002

**Acceptance Criteria:**

1. «Incluye el permiso» con dos permisos deja solo los roles que tienen ambos; «Cubre el área» incluye un rol con un solo permiso del área.
2. «Con permisos sensibles» y «Con usuarios» funcionan en sus dos valores; «Último cambio entre» deja fuera un rol sin actividad registrada.
3. Los filtros se combinan entre sí, con el filtro de tipo y con la búsqueda actual.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Tables/RolesTable.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Tables/RolesTableFiltersTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Tables/RolesTableFiltersTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-004: Columnas nuevas y agrupación por tipo

En `RolesTable`, agregar el grupo por tipo y las columnas «Áreas», «Último cambio» (`last_changed_at` con `lastChangeDateSubquery()`, ordenable, autor en la descripción) y «Creado por», todas ocultables, con `permissions` precargados. `RolesTable::configure()` recibe dos `Closure` que devuelven los cambios y las creaciones de los roles de la página; `ListRoles` los carga una vez por petición con `latestChangesFor()` y `creationsFor()` a partir de `getTableRecords()`.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. Un rol personalizado creado y editado muestra sus áreas, la fecha y el autor del último cambio y quién lo creó; un rol de sistema sin actividad muestra `not_recorded` en ambas columnas.
2. La tabla se agrupa en Sistema y Personalizado, y ordenar por «Último cambio» funciona en ambos sentidos.
3. El número de consultas es el mismo con pocos y con muchos roles (prueba con 5 y 20 roles personalizados con actividad).

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Tables/RolesTable.php`
- `app/Modules/Admin/Filament/Resources/Roles/Pages/ListRoles.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Tables/RolesTableColumnsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Tables app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-005: Comparar roles en la matriz de permisos

En `RolePermissionMatrix`, agregar las propiedades `#[Url]` `roles` (nombres) y `onlyDifferences`, un selector múltiple de roles y un interruptor «Solo diferencias» en la parte superior, y filtrar `getMatrixRoles()`/`getMatrixAreas()` según el contrato. La vista Blade muestra el aviso cuando la selección no es válida. Ampliar `RolePermissionMatrixTest`.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-002

**Acceptance Criteria:**

1. Con 2 a 4 roles elegidos la matriz muestra solo esos en orden de visualización; con «Solo diferencias» oculta las filas en que coinciden y las áreas vacías.
2. Con menos de 2, más de 4 o nombres desconocidos en la URL muestra todos los roles y el aviso, sin errores.
3. Las pruebas existentes de la matriz (celdas, español, consultas constantes, 403) siguen pasando.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/RolePermissionMatrix.php`
- `app/Modules/Admin/UI/Views/filament/roles/permission-matrix.blade.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-006: Accesos para comparar desde la lista

En `ListRoles`, agregar el enlace «Comparar roles» a la matriz. En `RolesTable`, agregar la acción masiva «Comparar seleccionados»: con 2 a 4 roles redirige a la matriz con `roles[]` y `only_differences=1`; con otra cantidad notifica `compare.wrong_count` y no navega. Pruebas en `ListRolesTest`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-004, TASK-005

**Acceptance Criteria:**

1. Seleccionar tres roles y comparar redirige a la matriz con esos tres y «Solo diferencias».
2. Comparar con uno o con cinco roles seleccionados notifica la cantidad válida y no redirige.
3. Un lector de roles ve ambos accesos (es de solo lectura) y la tabla no ofrece ninguna otra acción masiva.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/ListRoles.php`
- `app/Modules/Admin/Filament/Resources/Roles/Tables/RolesTable.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Tables`

**Review:** claude

### TASK-007: Marca de permisos sensibles en la matriz

En la vista de la matriz, marcar las filas de permisos sensibles con el texto `matrix.sensitive` junto al nombre; `getMatrixAreas()` incluye `sensitive` por permiso.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-005

**Acceptance Criteria:**

1. Las ocho filas sensibles muestran la marca y las demás no.
2. La marca se mantiene con una comparación y con «Solo diferencias».

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/RolePermissionMatrix.php`
- `app/Modules/Admin/UI/Views/filament/roles/permission-matrix.blade.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/RolePermissionMatrixTest.php`

**Review:** claude

### TASK-008: Documentación de la tabla y la comparación

Actualizar `docs/architecture/modules/admin.md`: consultas de cambios de `Role`, filtros, grupos y columnas de `RolesTable`, la comparación en la matriz (parámetros de URL, límites, «Solo diferencias»), los accesos desde la lista y la marca de sensibles; ajustar las descripciones de `ListRoles` y `RolePermissionMatrix` de SOC-69/70.

**Type:** docs · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-006, TASK-007

**Acceptance Criteria:**

1. La documentación describe la tabla y la matriz como quedaron y no contradice las secciones anteriores.
2. Los nombres de clases y métodos citados existen en el código (verificado con `grep`).

**writeScope:**

- `docs/architecture/modules/admin.md`

**validateCommand:** `rtk grep -n "latestChangesFor\|onlyDifferences" docs/architecture/modules/admin.md`

**Review:** claude

### TASK-009: Gates finales y prueba en el navegador

Pint (`--dirty --format agent`), `composer rector-clear-cache` → `rector-dry` → `rector`, `composer phpstan`, `composer test` y `composer doctor`. Prueba manual: cada filtro, agrupación, columnas, comparar desde la lista y desde la matriz, «Solo diferencias» y la marca de sensibles.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-008

**Acceptance Criteria:**

1. Pint, Rector (sin pendientes), PHPStan max, la suite completa y `composer doctor` pasan.
2. La tabla y la matriz funcionan en el navegador sin errores de consola ni de Laravel.

**writeScope:**

- (ninguno: solo comandos)

**validateCommand:** `rtk composer phpstan && rtk composer test && rtk composer doctor`

**Review:** claude

## Failure Modes

- Un rol de sistema sembrado no tiene actividad: «Último cambio» y «Creado por» muestran `not_recorded`, y el filtro de fechas lo deja fuera.
- El autor de un cambio fue eliminado: «Usuario eliminado #id».
- La URL de la matriz trae un rol de otro guard, un nombre inexistente o más de 4: se ignora la selección y se muestra la matriz completa con el aviso.
- Un rol elegido para comparar se elimina mientras está abierta la matriz: la próxima carga lo descarta por nombre desconocido.
- Una acción masiva con todas las filas de todas las páginas seleccionadas supera 4 roles: se notifica y no se navega.

## Ship Cut

Mínimo entregable: TASK-001 a TASK-005 y TASK-009 (consultas, textos, filtros, columnas con agrupación y comparación en la matriz). TASK-006 (accesos desde la lista con la acción masiva) y TASK-007 (marca de sensibles en la matriz) son los extras del modo EXPANSION; TASK-008 documenta lo entregado.

## Test Coverage Map

| Área | Tareas |
| --- | --- |
| Consultas de cambios | TASK-001 |
| Traducciones | TASK-002, TASK-003, TASK-004, TASK-005 |
| Filtros | TASK-003 |
| Columnas y agrupación | TASK-004 |
| Comparación en la matriz | TASK-005, TASK-007 |
| Accesos desde la lista | TASK-006 |

## Execution Summary

- Tareas: 9
- Ruta crítica: TASK-001 → TASK-003 → TASK-004 → TASK-006 → TASK-008 → TASK-009
- Capas paralelas:

  1. TASK-001, TASK-002
  2. TASK-003, TASK-005
  3. TASK-004, TASK-007
  4. TASK-006
  5. TASK-008
  6. TASK-009

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | TASK-001, TASK-002 |
| TASK-004 | TASK-003 |
| TASK-005 | TASK-002 |
| TASK-006 | TASK-004, TASK-005 |
| TASK-007 | TASK-005 |
| TASK-008 | TASK-006, TASK-007 |
| TASK-009 | TASK-008 |
