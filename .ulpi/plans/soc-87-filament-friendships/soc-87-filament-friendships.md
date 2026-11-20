# Plan: SOC-87 — Friendships in the Filament user detail and dashboard

**Linear:** SOC-87 (épica SOC-82) · **Modo:** EXPANSION · **Revisión por defecto:** claude

## Overview

Hoy el panel de Filament no muestra nada de las amistades. Este plan agrega:

* **Pestaña "Amigos" en el detalle del usuario**, de solo lectura y con carga lazy: la otra persona (con enlace a su detalle si el administrador puede verla), el estado, quién envió la solicitud y la fecha, con filtros por estado y por dirección y búsqueda por nombre.
* **Un permiso propio**, `admin.users.friendships`, en el área de usuarios, con sus textos en el catálogo de roles y asignado a los roles de lectura y moderación de usuarios.
* **`FriendshipPolicy` para el panel:** `viewAny` y `view` por permiso, y todas las escrituras masivas negadas explícitamente.
* **Un widget en el dashboard** con las amistades aceptadas, las solicitudes pendientes y una gráfica diaria de 30 días, con un número constante de consultas. Para la gráfica, `BuildsDailyActivityCharts` recibe el número de días y la columna de fecha.
* **El número de amigos y de solicitudes pendientes** en el infolist del usuario.

## Scope Challenge

**Qué existe hoy:**

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/` tiene cinco pestañas de solo lectura; `TrustedDevicesRelationManager` y `AccountStatusChangesRelationManager` usan `loadingSkeleton()`, `canViewForRecord()` con `Gate::forUser(Filament::auth()->user())` y acciones vacías.
- `ActivityLogsRelationManager` declara `$relationship = 'causedActivityLogs'` pero arma su tabla con `->query()` sobre un scope más amplio (`involvingUser`): es el precedente para listar las dos direcciones de una amistad.
- `Friendship` tiene los scopes `involving`, `pending` y `accepted`; `User` tiene `sentFriendRequests()`, `receivedFriendRequests()`, `friendsCount()` y `pendingReceivedFriendRequestsCount()`.
- `FriendshipPolicy` es la policy del sitio: `create`, `accept`, `decline`, `cancel` y `delete` sirven a las rutas JSON; `view` ("participa en la amistad") solo lo usa su prueba; `update`, `restore` y `forceDelete` ya devuelven `false`.
- `AdminPermission` tiene un `requires()` exhaustivo y dos pruebas que fijan la lista completa de valores. Otra prueba exige que cada permiso dependa solo de permisos de **su misma área**.
- `AdminAuthorizationSeeder` crea los permisos con `firstOrCreate`, pero solo da permisos a un rol cuando lo crea: los roles existentes no cambian al volver a sembrar.
- `BuildsDailyActivityCharts` arma siempre 7 días sobre `created_at`; lo usan `UserActivityOverview`, `PostsOverview`, `PostActivityOverview` y `RolesOverview`.
- El panel descubre los widgets de `app/Modules/Admin/Filament/Widgets`; `AdminHardcodedTextTest` ya escanea esa carpeta y `app/Modules/Admin/Filament/Resources/Users`.

**Qué se reutiliza:**

- La forma de `TrustedDevicesRelationManager` para la pestaña nueva.
- `RegistrationSummaryWidget` y `UserActivityOverview` para el widget de totales con gráfica.
- `PostPolicy` y `UserPolicy` como modelo de habilidades del panel por permiso.
- `AdminTranslationKeysTest` y `AdminHardcodedTextTest`, que cubren los archivos nuevos sin cambios.

**Qué es nuevo:**

- El permiso `admin.users.friendships` y sus textos.
- `FriendshipsRelationManager`, `FriendshipsOverviewWidget` y las entradas del infolist.
- Los parámetros de días y de columna de `BuildsDailyActivityCharts`.

**Decisiones confirmadas:**

- Modo EXPANSION, revisión `claude` después de cada tarea.
- Permiso propio. Vive en el área `users` (`admin.users.friendships`) y requiere `admin.users.viewAny` y `admin.users.view`: la pestaña está dentro del detalle del usuario, y la prueba de áreas impide depender de otra área.
- `BuildsDailyActivityCharts` recibe el número de días (7 por defecto) y la columna de fecha (`created_at` por defecto); los cuatro widgets que lo usan no cambian.
- EXPANSION agrega el filtro por dirección (enviada o recibida), la búsqueda por nombre de la otra persona y las solicitudes pendientes en el infolist.

**¿Ejecución directa?** No: toca la autorización del panel, la policy de un módulo, un trait compartido y cuatro archivos de traducciones.

## Prerequisites

- SOC-83 está mergeada: modelo, scopes, policy y `FriendshipSeeder`.
- `FriendshipSeeder` deja amistades en local para revisar la pestaña y el widget a mano.

## Non-Goals

- Crear, aceptar o eliminar amistades desde el panel.
- Un recurso de Friendship en la navegación del panel.
- Una migración de datos que dé el permiso nuevo a los roles que ya existen: se asigna desde la matriz de permisos, igual que los permisos anteriores.
- Un rol de sistema nuevo para el permiso.
- Pruebas de navegador del panel: el proyecto no las tiene; se prueba con Livewire.

## Contracts

### Permiso

`AdminPermission::AdminUsersFriendships = 'admin.users.friendships'`, después de `AdminUsersRoles`. `requires()` → `[AdminUsersViewAny, AdminUsersView]`; no es sensible; `translationKey()` → `admin_users_friendships`. Textos en `admin/roles.catalog.permissions.admin_users_friendships.{label, description}`.

### Habilidades del panel en FriendshipPolicy

`viewAny(User $user)` → `$user->can('admin.users.friendships')`. `view(User $user, Friendship $friendship)` → participa en la amistad **o** tiene el permiso. `deleteAny`, `restoreAny` y `forceDeleteAny` → `false`. Las habilidades del sitio (`create`, `accept`, `decline`, `cancel`, `delete`) no cambian: el panel nunca las llama, porque no hay recurso ni acciones.

### Pestaña Amigos

`FriendshipsRelationManager` (`$relationship = 'sentFriendRequests'` solo para que Filament resuelva el modelo relacionado; la tabla usa `->query(Friendship::query()->involving($owner))`). Visible solo en `ViewUser` cuando el administrador pasa `view` sobre el usuario **y** `viewAny` sobre `Friendship`. Columnas: la otra persona (enlace a `UserResource::getUrl('view')` solo si puede verla), estado (`accepted`, `pending_sent`, `pending_received`, vistos desde el dueño), fecha (`accepted_at` o `created_at`). Sin acciones de ningún tipo.

### Widget

`FriendshipsOverviewWidget` (`StatsOverviewWidget`, sin polling): amistades aceptadas (gráfica de 30 días por `accepted_at`), solicitudes pendientes (30 días por `created_at`) y amistades nuevas en 30 días. Tres consultas: una con los totales por estado y dos de gráfica. Visible con `Gate::allows('viewAny', Friendship::class)`.

### Gráfica diaria

`getDailyActivityChartData(Builder $query, CarbonImmutable $startDate, CarbonImmutable $endDate, int $days = 7, string $dateColumn = 'created_at'): array<float>` devuelve `$days` valores, uno por día desde `$startDate`.

## Existing Code Leverage

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/TrustedDevicesRelationManager.php`: visibilidad, skeleton y acciones vacías.
- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/ActivityLogsRelationManager.php`: `->query()` más amplio que la relación.
- `app/Modules/Admin/Filament/Widgets/RegistrationSummaryWidget.php`: totales con gráfica y `canView()`.
- `app/Modules/Admin/Filament/Concerns/BuildsDailyActivityCharts.php`: la gráfica diaria que se generaliza.
- `app/Modules/Post/Policies/PostPolicy.php`: habilidades del panel por permiso y escrituras negadas.
- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`: permisos por rol de sistema.

## Tasks

### TASK-001: Add the admin.users.friendships permission

Agregar `AdminPermission::AdminUsersFriendships = 'admin.users.friendships'` después de `AdminUsersRoles`, con `requires()` → `[AdminUsersViewAny, AdminUsersView]`, y sumarlo a las dos pruebas que fijan la lista completa de permisos.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/Admin/Enums/AdminPermission.php`
- `app/Modules/Admin/Tests/Enums/AdminRoleManagementAuthorizationEnumsTest.php`
- `app/Modules/Admin/Tests/Enums/AdminAnnouncementAuthorizationEnumsTest.php`

**Acceptance Criteria:**

1. `AdminPermission::from('admin.users.friendships')->area()` es `users` y `requires()` devuelve `[AdminUsersViewAny, AdminUsersView]`.
2. La prueba de áreas sigue pasando y el permiso no aparece en `AdminPermission::sensitive()`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Enums`

### TASK-002: Name the new permission in the roles catalog

En `lang/{en,es}/admin/roles.php`, agregar `catalog.permissions.admin_users_friendships` con `label` y `description`.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-001  
**Review:** claude

**writeScope:**

- `lang/en/admin/roles.php`
- `lang/es/admin/roles.php`

**Acceptance Criteria:**

1. `AdminTranslationKeysTest` pasa: el permiso tiene nombre y descripción en los dos idiomas.
2. La prueba falla si se borra la descripción en `es`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

### TASK-003: Grant the permission to the users reader and moderator roles

En `AdminAuthorizationSeeder`, sumar el permiso a `AdminUsersReader` y `AdminUsersModerator`, y actualizar `AdminAuthorizationSeederTest`.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-001  
**Review:** claude

**writeScope:**

- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**Acceptance Criteria:**

1. Una base nueva da el permiso a esos dos roles y a ningún otro.
2. Volver a sembrar crea el permiso, pero no se lo da a un rol que ya existía.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

### TASK-004: Add the panel abilities to FriendshipPolicy

`viewAny` por permiso, `view` = participa **o** tiene el permiso, y `deleteAny`, `restoreAny` y `forceDeleteAny` en `false`. Las habilidades del sitio no cambian.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** TASK-001  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Policies/FriendshipPolicy.php`
- `app/Modules/Friendship/Tests/Policies/FriendshipPolicyTest.php`

**Acceptance Criteria:**

1. Un administrador con el permiso pasa `viewAny` y `view` sobre una amistad ajena; uno sin el permiso, no.
2. Ese administrador sigue sin poder `accept`, `decline`, `cancel` ni `delete` una amistad ajena, y las habilidades masivas devuelven `false`.
3. Las pruebas del sitio siguen pasando sin cambios.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Policies/FriendshipPolicyTest.php`

### TASK-005: Let BuildsDailyActivityCharts take the days and the date column

Agregar `int $days = 7` y `string $dateColumn = 'created_at'` a `getDailyActivityChartData()`. Prueba nueva del trait.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Concerns/BuildsDailyActivityChartsTest.php`
- `app/Modules/Admin/Filament/Concerns/BuildsDailyActivityCharts.php`

**Acceptance Criteria:**

1. Con 30 días devuelve 30 valores en orden y cuenta por la columna pedida.
2. Un día sin registros vale 0 y un registro fuera del rango no cuenta.
3. Las pruebas de los cuatro widgets que ya lo usan pasan sin cambios.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Concerns/BuildsDailyActivityChartsTest.php app/Modules/Admin/Tests/Filament/Resources/Users/Pages/UsersAccountOverviewTest.php app/Modules/Admin/Tests/Filament/Resources/Posts/Widgets app/Modules/Admin/Tests/Filament/Resources/Roles/Widgets/RolesOverviewTest.php`

### TASK-006: Add the Friends tab and infolist texts

En `lang/{en,es}/admin/users.php`: `relations.friendships.{title, other_user, status, since, sent_by, search, statuses.{accepted, pending_sent, pending_received}, filters.{status, direction, sent, received}}` e `infolist.account_activity.{friends_count, pending_friend_requests}`.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `lang/en/admin/users.php`
- `lang/es/admin/users.php`

**Acceptance Criteria:**

1. Los dos idiomas tienen las mismas claves y los mismos placeholders.
2. La prueba falla si falta una clave nueva en `es`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

### TASK-007: Add the friendships widget texts

En `lang/{en,es}/admin/dashboard.php`: `friendships_overview.{heading, accepted.{label, description}, pending.{label, description}, new.{label, description}}`.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `lang/en/admin/dashboard.php`
- `lang/es/admin/dashboard.php`

**Acceptance Criteria:**

1. Los dos idiomas tienen las mismas claves.
2. La prueba falla si falta una clave nueva en `es`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

### TASK-008: Show the Friends tab in the user detail

`app/Modules/Admin/Filament/Resources/Users/RelationManagers/FriendshipsRelationManager.php` según el contrato, registrado en `UserResource::getRelations()`, con columnas, filtro por estado, carga lazy con skeleton, orden por fecha y sin acciones. Precarga `requester` y `receiver` con `id` y `name`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-004, TASK-006  
**Review:** claude

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/FriendshipsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/FriendshipsRelationManagerTest.php`
- `app/Modules/Admin/Filament/Resources/Users/UserResource.php`

**Acceptance Criteria:**

1. Con el permiso, la pestaña lista las amistades aceptadas y las solicitudes de las dos direcciones, con el estado visto desde el dueño; sin el permiso no aparece, y montarla directamente responde 403.
2. La tabla no tiene acciones de cabecera, de fila ni masivas, y el enlace a la otra persona solo aparece si el administrador puede verla.
3. Listar 3 o 15 amistades hace el mismo número de consultas.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/FriendshipsRelationManagerTest.php`

### TASK-009: Filter the Friends tab by direction and search by name

Filtro por dirección (enviada o recibida, desde el dueño) y búsqueda por el nombre de la otra persona, con `LikePattern` para escapar comodines.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-008  
**Review:** claude

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/FriendshipsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/FriendshipsRelationManagerTest.php`

**Acceptance Criteria:**

1. El filtro "enviadas" deja solo las solicitudes del dueño, y "recibidas" solo las que recibió.
2. La búsqueda encuentra a la otra persona por el inicio de su nombre, nunca al dueño, y un `%` se busca literal.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/FriendshipsRelationManagerTest.php`

### TASK-010: Show the friend counts in the user infolist

En `UserInfolist`, dos entradas en "actividad de la cuenta": amigos (`friendsCount()`) y solicitudes pendientes recibidas, visibles solo con el permiso.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-004, TASK-006  
**Review:** claude

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/Schemas/UserInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserTest.php`

**Acceptance Criteria:**

1. Con el permiso, el detalle muestra los números correctos, contando las amistades de las dos direcciones.
2. Sin el permiso, las dos entradas no aparecen y el resto del infolist no cambia.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserTest.php`

### TASK-011: Add the friendships overview widget to the dashboard

`app/Modules/Admin/Filament/Widgets/FriendshipsOverviewWidget.php` según el contrato; el panel lo descubre solo. Prueba con Livewire.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-004, TASK-005, TASK-007  
**Review:** claude

**writeScope:**

- `app/Modules/Admin/Filament/Widgets/FriendshipsOverviewWidget.php`
- `app/Modules/Admin/Tests/Filament/Widgets/FriendshipsOverviewWidgetTest.php`

**Acceptance Criteria:**

1. Muestra los totales correctos de aceptadas y pendientes y una gráfica de 30 valores; con cero amistades muestra ceros.
2. Un administrador sin el permiso no ve el widget en el dashboard.
3. Con 3 o 30 amistades hace el mismo número de consultas.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Widgets/FriendshipsOverviewWidgetTest.php`

### TASK-012: Document the friendships in the panel

`docs/architecture/modules/admin.md`: el permiso, la pestaña, el widget y el trait. `docs/architecture/modules/friendship.md`: las habilidades del panel de la policy. `CLAUDE.md`: el mapa de módulos si cambia lo que dice de Admin o Friendship.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Depends on:** TASK-003, TASK-009, TASK-010, TASK-011  
**Review:** claude

**writeScope:**

- `docs/architecture/modules/admin.md`
- `docs/architecture/modules/friendship.md`
- `CLAUDE.md`

**Acceptance Criteria:**

1. Cada clase, permiso y archivo que se nombra existe (`grep`).
2. La doc explica que el permiso no se da a los roles que ya existían y cómo asignarlo.

**validateCommand:** `grep -rn "admin.users.friendships" docs/architecture/modules/admin.md`

### TASK-013: Run the quality gates and check the panel by hand

1. Pint, PHPStan y Rector (`rector-clear-cache` → `rector-dry` → `rector`);
2. `composer test` y `composer doctor`;
3. a mano en el panel, con un administrador con el permiso y otro sin él: la pestaña, sus filtros y búsqueda, el infolist y el widget, en los dos idiomas.

**Type:** chore  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002, TASK-012  
**Review:** claude

**writeScope:**

- (sin archivos: verificación)

**Acceptance Criteria:**

1. Pint, PHPStan y Rector no reportan cambios ni errores, y la suite completa pasa.
2. En la revisión a mano, un administrador sin el permiso no ve la pestaña, las entradas ni el widget, y la matriz de roles muestra el permiso nuevo.

**validateCommand:** `composer phpstan && composer rector-dry && composer test && composer doctor`

### TASK-014: Add the texts of the richer Friends table

En `lang/{en,es}/admin/users.php`, bajo `relations.friendships`: el resumen de la tabla (amigos, solicitudes recibidas y enviadas), la foto y su texto alternativo, la cuenta y el último acceso de la otra persona, la fecha de la solicitud y la de aceptación, el estado vacío y los filtros por estado de la cuenta y por rango de fechas.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-013  
**Review:** claude

**writeScope:**

- `lang/en/admin/users.php`
- `lang/es/admin/users.php`

**Acceptance Criteria:**

1. Los dos idiomas tienen las mismas claves y los mismos placeholders.
2. La prueba de paridad falla si falta una clave nueva en `es`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations`

### TASK-015: Make the Friends table richer

En `FriendshipsRelationManager`: un resumen en la descripción de la tabla (una consulta agregada), la foto de perfil de la otra persona, su correo bajo el nombre, el estado de su cuenta, su último acceso, la fecha relativa bajo "Desde", las fechas de solicitud y aceptación como columnas ocultables, un estado vacío, y filtros por estado de la cuenta de la otra persona y por rango de fechas. La foto y los datos se precargan, así que la tabla sigue con un número constante de consultas.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-014  
**Review:** claude

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/FriendshipsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/FriendshipsRelationManagerTest.php`

**Acceptance Criteria:**

1. El resumen cuenta amigos y solicitudes recibidas y enviadas del dueño, y no aparece sin filas.
2. El filtro por cuenta deja solo las filas cuya otra persona tiene ese estado (nunca el dueño), y el de fechas usa la fecha de aceptación o, si está pendiente, la de envío.
3. Listar 3 o 15 amistades sigue haciendo el mismo número de consultas.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/FriendshipsRelationManagerTest.php`

### TASK-016: Document the richer Friends table

Actualizar la sección SOC-87 de `docs/architecture/modules/admin.md` con las columnas, el resumen y los filtros nuevos.

**Type:** docs  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P3  
**Depends on:** TASK-015  
**Review:** claude

**writeScope:**

- `docs/architecture/modules/admin.md`

**Acceptance Criteria:**

1. La sección describe cada columna, el resumen y cada filtro tal como quedaron en el código.
2. Menciona que la tabla sigue con consultas constantes.

**validateCommand:** `grep -n "SOC-87" docs/architecture/modules/admin.md`

## Failure Modes

- `AdminTranslationKeysTest` exige nombre para cada permiso: queda en rojo entre TASK-001 y TASK-002, por eso TASK-001 se valida solo con las pruebas de enums.
- Volver a sembrar no da el permiso a los roles existentes: es el comportamiento del seeder; la doc (TASK-012) dice cómo asignarlo desde la matriz.
- Filament permite una habilidad que la policy no define: TASK-004 niega las masivas, y la pestaña no tiene acciones, así que nunca llama a las habilidades del sitio.
- `view` de la policy deja de significar solo "participa": hoy ninguna ruta del sitio lo usa; TASK-004 lo cubre en las dos direcciones.
- Cambiar el trait podría alterar los widgets que lo usan: los valores por defecto mantienen 7 días y `created_at`, y TASK-005 corre sus pruebas.
- Los totales del widget recorren la tabla `friendships`, que no tiene índice por `status` solo: es aceptable con el volumen actual; si crece, se agrega el índice en otra tarea.

## Ship Cut

El mínimo entregable es TASK-001 a TASK-004, TASK-006 y TASK-008: el permiso, la policy y la pestaña. El widget (TASK-005, TASK-007 y TASK-011) se recorta como bloque. TASK-009 y TASK-010 son recortables; la doc y los gates no.

## Test Coverage Map

| Comportamiento | Tarea |
| --- | --- |
| Permiso, área y requisitos | TASK-001 |
| Nombre del permiso en el catálogo | TASK-002 |
| Permiso en los roles sembrados | TASK-003 |
| Habilidades del panel en la policy | TASK-004 |
| Gráfica diaria con días y columna | TASK-005 |
| Paridad de textos de usuarios y dashboard | TASK-006, TASK-007 |
| Pestaña Amigos: visibilidad, columnas, sin acciones, consultas | TASK-008 |
| Filtro por dirección y búsqueda | TASK-009 |
| Números de amigos en el infolist | TASK-010 |
| Widget del dashboard | TASK-011 |

## Execution Summary

- **Tareas:** 16
- **Capa 1:** TASK-001, TASK-005, TASK-006, TASK-007
- **Capa 2:** TASK-002, TASK-003, TASK-004
- **Capa 3:** TASK-008, TASK-010, TASK-011
- **Capa 4:** TASK-009
- **Capa 5:** TASK-012
- **Capa 6:** TASK-013
- **Capa 7:** TASK-014
- **Capa 8:** TASK-015
- **Capa 9:** TASK-016
- **Ruta crítica:** TASK-001 → TASK-004 → TASK-008 → TASK-009 → TASK-012 → TASK-013

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-001 |
| TASK-004 | TASK-001 |
| TASK-005 | — |
| TASK-006 | — |
| TASK-007 | — |
| TASK-008 | TASK-004, TASK-006 |
| TASK-009 | TASK-008 |
| TASK-010 | TASK-004, TASK-006 |
| TASK-011 | TASK-004, TASK-005, TASK-007 |
| TASK-012 | TASK-003, TASK-009, TASK-010, TASK-011 |
| TASK-013 | TASK-002, TASK-012 |
| TASK-014 | TASK-013 |
| TASK-015 | TASK-014 |
| TASK-016 | TASK-015 |
