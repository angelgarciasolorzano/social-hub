# Plan: SOC-56: Vista de solo lectura del registro de actividad en Filament con permiso propio

## Overview

Agregar al panel Admin un recurso de solo lectura (`ActivityLogs`) para consultar el registro de actividad de SOC-54, protegido por los permisos nuevos `admin.activity.viewAny` y `admin.activity.view` y el rol `admin-activity-reader`, más una pestaña de actividad en el detalle de usuario.

**Linear:** [SOC-56](https://linear.app/social-hub-ang/issue/SOC-56/feature-add-a-read-only-filament-activity-log-viewer-with-its-own) · **Modo:** HOLD · **Revisión predeterminada:** Claude

## Scope Challenge

HOLD confirmado con revisión de Claude. Decisiones del usuario: la autorización vive en una `ActivityLogPolicy` asociada al modelo con `#[UsePolicy]`, como `UserPolicy` (primero se eligió el patrón de Posts/Comments, con overrides en el recurso, y luego se pidió agregar la policy); `admin:bootstrap-admin` asigna también el rol nuevo; se incluye la pestaña de actividad en el detalle de usuario. Ajustes técnicos propuestos: un modelo `ActivityLog` propio del módulo ActivityLog, que además es donde se asocia la policy, porque la regla `arch()` de SOC-54 impide usar el modelo de Spatie desde Admin, y un catálogo central de etiquetas (`ActivityLogEventCatalog`) en lugar de agregar `label()` a los seis enums de eventos.

## Prerequisites

- SOC-54 está en main: tabla `activity_log`, `ActivityLogger`, catálogo `ActivityLogEvent::ENUMS`, morph map con `user`, `post`, `comment`, `like`, `admin_invitation`, `trusted_device_event` y `role`, y la regla `arch()` que limita `Spatie\Activitylog` al módulo ActivityLog.
- `AdminPanelProvider` descubre recursos en `app/Modules/Admin/Filament/Resources/`; `UserResource` se autoriza con `UserPolicy` (asociada con `#[UsePolicy]`) y sin overrides, el patrón que sigue el recurso ActivityLogs.
- `AdminAuthorizationSeeder` crea todos los permisos de `AdminPermission::cases()` y solo asigna permisos a roles recién creados.
- Las pruebas de Filament usan `AdminFilamentTestContext` y `FilamentTableRecords` (`app/Modules/Admin/Tests/Support/`).

## Non-Goals

- Registrar las vistas visitadas, las interacciones de React y los correos de seguridad (parte 2 de SOC-54).
- Línea de tiempo diaria o gráficos de actividad.
- Crear, editar, borrar, exportar o purgar actividad desde el panel.
- Registrar en el log quién consultó la actividad.
- Traducir el panel: las etiquetas siguen en inglés como el resto de Filament.

## Contracts

- Permisos `admin.activity.viewAny` (listado y pestaña de usuario) y `admin.activity.view` (detalle), rol `admin-activity-reader`; `admin.access` por sí solo no da acceso.
- El recurso usa `App\Modules\ActivityLog\Models\ActivityLog`; `activitylog.activity_model` apunta a ese modelo, así que la escritura, la purga y la lectura usan la misma clase y la regla `arch()` sigue en verde.
- Solo lectura: `ActivityLogPolicy` (`app/Modules/ActivityLog/Policies/`, registrada con `#[UsePolicy]` en el modelo) permite `viewAny`/`view` según los permisos y niega `create`, `update`, `delete`, `deleteAny`, `restore` y `forceDelete`; el recurso solo registra las páginas `index` y `view` y la pestaña del usuario consulta la misma policy.
- La vista nunca muestra contenido de publicaciones o comentarios: el sujeto se describe por tipo e id; las propiedades mostradas son las que ya se guardan.
- Los enlaces a usuarios, publicaciones y comentarios solo aparecen si el administrador puede ver ese recurso y el registro existe.
- El listado precarga causante y sujeto para que el número de consultas no dependa de la cantidad de filas.
- `admin:bootstrap-admin` asigna `admin` más cuatro roles lectores; el evento `admin.bootstrapped` registra esa lista.

## Existing Code Leverage

- `app/Modules/Admin/Filament/Resources/Posts/` — estructura de recurso, tabla, infolist y páginas de solo lectura a imitar.
- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/TrustedDevicesRelationManager.php` — relation manager de solo lectura con `canViewForRecord()` y `->query()` propio.
- `app/Modules/Admin/Enums/AdminPermission.php`, `AdminRole.php`, `Database/Seeders/AdminAuthorizationSeeder.php` y `AdminUserSeeder.php` — catálogo RBAC.
- `app/Modules/Admin/Console/Commands/AdminBootstrapCommand.php` — lista de roles del bootstrap.
- `app/Modules/ActivityLog/Contracts/ActivityLogEvent.php` y `Enums/` — catálogo de eventos y logs para las etiquetas.
- `app/Modules/Admin/Tests/Support/AdminFilamentTestContext.php` y `FilamentTableRecords.php` — utilidades de prueba de Filament.

## External References

- Filament 5, recursos: https://filamentphp.com/docs/5.x/resources/overview
- Filament 5, relation managers y su consulta: https://filamentphp.com/docs/5.x/resources/managing-relationships
- Filament 5, restauración de modelos en Livewire (autorizar `$ownerRecord`): https://filamentphp.com/docs/5.x/advanced/security

## Tasks

### TASK-001: Agregar los permisos y el rol de lectura de actividad al catálogo RBAC

Agregar a `AdminPermission` los casos `AdminActivityViewAny = 'admin.activity.viewAny'` y `AdminActivityView = 'admin.activity.view'`, y a `AdminRole` el caso `AdminActivityReader = 'admin-activity-reader'`. En `AdminAuthorizationSeeder`, mapear el rol nuevo a ambos permisos en `$permissionsByRole` (el seeder ya crea todo permiso de `AdminPermission::cases()` y solo asigna permisos a roles recién creados, así que una base existente recibe el rol nuevo con sus permisos sin tocar los demás). Actualizar `AdminAuthorizationSeederTest` con la expectativa del rol nuevo. Toca 4 archivos porque el enum, el seeder y su prueba no se pueden validar por separado: la prueba recorre `AdminRole::cases()` y fallaría con un rol que el seeder no crea.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
- Tras el seeder, `admin-activity-reader` existe bajo el guard `admin` con exactamente `admin.activity.viewAny` y `admin.activity.view`.
- Ejecutar el seeder dos veces no duplica nada y conserva las revocaciones hechas a mano en los roles existentes.
- `admin.access` sigue sin otorgar `admin.activity.*`.

**writeScope:**
- `app/Modules/Admin/Enums/AdminPermission.php`
- `app/Modules/Admin/Enums/AdminRole.php`
- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**Review:** Claude

### TASK-002: Incluir el rol nuevo en AdminUserSeeder

`AdminUserSeeder::readerRoles()` incorpora `AdminActivityReader`: Angel recibe todos los roles lectores (también el nuevo) y cada administrador aleatorio recibe `admin` más un subconjunto no vacío de 1 a 4 roles lectores. Actualizar sus dos archivos de prueba (`readerRoleNames()`, el texto 'between one and three' y las expectativas de Angel).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001

**Acceptance Criteria:**
- Angel tiene `admin` y los cuatro roles lectores, y con ellos `admin.activity.viewAny`.
- Los administradores aleatorios tienen entre 1 y 4 roles lectores, todos del catálogo; correr el seeder dos veces no agrega administradores.

**writeScope:**
- `app/Modules/Admin/Database/Seeders/AdminUserSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminUserSeederTest.php`
- `app/Modules/Admin/Tests/Database/AdminUserSeederRandomAdminsTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminUserSeederTest.php app/Modules/Admin/Tests/Database/AdminUserSeederRandomAdminsTest.php`

**Review:** Claude

### TASK-003: Asignar el rol nuevo en admin:bootstrap-admin

Agregar `AdminRole::AdminActivityReader` a `$requiredRoleNames` de `AdminBootstrapCommand` (decisión confirmada: el primer administrador ve todas las secciones de lectura). El evento `admin.bootstrapped` registra la lista ampliada. Actualizar sus pruebas, incluida la que exige que todos los roles existan.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001

**Acceptance Criteria:**
- Un bootstrap válido asigna `admin` y los cuatro roles lectores y el usuario puede `admin.activity.viewAny` y `admin.activity.view`.
- Si falta `admin-activity-reader` en el guard `admin`, el comando falla con 'Admin roles are not initialized.' sin asignar nada.

**writeScope:**
- `app/Modules/Admin/Console/Commands/AdminBootstrapCommand.php`
- `app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`

**Review:** Claude

### TASK-004: Crear el modelo ActivityLog propio del módulo ActivityLog

La regla `arch()` de SOC-54 prohíbe usar `Spatie\Activitylog` fuera de `App\Modules\ActivityLog`, así que el recurso de Admin necesita un modelo del módulo. Crear `App\Modules\ActivityLog\Models\ActivityLog extends Spatie\Activitylog\Models\ActivityLog` con un scope `#[Scope] involvingUser(Builder, User)` (causante o sujeto es el usuario) y apuntar `activitylog.activity_model` a él en `config/activitylog.php`, para que `ActivityLogger` y la purga escriban y lean con el mismo modelo. La policy se le asocia en TASK-016. Durante la ejecución, Rector exigió `HasFactory` en el modelo y PHPStan el tipo de su factory, así que se agregó `Database/Factories/ActivityLogFactory` (estado por defecto: evento anónimo sin sujeto) con `#[UseFactory]` y su prueba; sus estados (`forEvent`, `causedBy`, `performedOn`) se agregan en TASK-010, donde se usan con varios tipos, porque PHPStan exige que los parámetros sean tan estrechos como sus llamadas.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
- `resolve(ActivityLogger::class)->record(...)` crea una instancia de `App\Modules\ActivityLog\Models\ActivityLog`.
- `ActivityLog::query()->involvingUser($user)` devuelve los registros donde el usuario es causante o sujeto y excluye los de otros usuarios.
- Las 110 pruebas existentes del módulo ActivityLog siguen pasando.

**writeScope:**
- `app/Modules/ActivityLog/Models/ActivityLog.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Models/ActivityLogTest.php` (nuevo)
- `app/Modules/ActivityLog/Database/Factories/ActivityLogFactory.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Factories/ActivityLogFactoryTest.php` (nuevo)
- `config/activitylog.php`

**validateCommand:** `rtk php artisan test --compact -d memory_limit=2G app/Modules/ActivityLog/Tests`

**Review:** Claude

### TASK-005: Crear el catálogo de etiquetas de eventos y logs

Crear `App\Modules\ActivityLog\Support\ActivityLogEventCatalog` (decisión: un catálogo central en lugar de agregar `label()` a los seis enums): `find(string $eventName): ?ActivityLogEvent` recorre `ActivityLogEvent::ENUMS`; `label(string $eventName): string` devuelve el nombre legible del caso (`Str::headline`, por ejemplo 'Password Updated') o el valor crudo si el evento ya no existe; `logLabel(string $logName): string`; y `eventOptions(): array<string, string>` y `logOptions(): array<string, string>` para los filtros de Filament.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
- `label('auth.password_updated')` devuelve 'Password Updated' y `label('legacy.unknown')` devuelve 'legacy.unknown' sin errores.
- `eventOptions()` contiene una entrada por cada caso de los enums registrados (31 hoy) y `logOptions()` una por log.

**writeScope:**
- `app/Modules/ActivityLog/Support/ActivityLogEventCatalog.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Support/ActivityLogEventCatalogTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Support/ActivityLogEventCatalogTest.php`

**Review:** Claude

### TASK-016: Crear ActivityLogPolicy y asociarla al modelo ActivityLog

Crear `App\Modules\ActivityLog\Policies\ActivityLogPolicy` siguiendo `UserPolicy`: `viewAny()` exige `admin.activity.viewAny`, `view()` exige `admin.activity.view`, y `create`, `update`, `delete`, `deleteAny`, `restore` y `forceDelete` devuelven siempre `false`. Asociarla con `#[UsePolicy(ActivityLogPolicy::class)]` en el modelo `ActivityLog` del módulo (TASK-004). La policy es la única fuente de autorización: el recurso (TASK-009) y la pestaña del usuario (TASK-012) la consultan en lugar de repetir la regla. Pedida por el usuario después de una primera versión sin policy.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001, TASK-004

**Acceptance Criteria:**
- `Gate::getPolicyFor(new ActivityLog)` devuelve `ActivityLogPolicy`.
- Con solo `admin.activity.viewAny` se permite `viewAny` pero no `view`; con `admin.access` solo, ninguna de las dos.
- Ningún usuario, ni siquiera con todos los permisos, puede `create`, `update`, `delete`, `deleteAny`, `restore` ni `forceDelete`.

**writeScope:**
- `app/Modules/ActivityLog/Policies/ActivityLogPolicy.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Policies/ActivityLogPolicyTest.php` (nuevo)
- `app/Modules/ActivityLog/Models/ActivityLog.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Policies/ActivityLogPolicyTest.php`

**Review:** Claude

### TASK-006: Crear el concern que describe causante y sujeto

Crear `Filament/Concerns/DescribesActivityLogParties.php` (convención `Concerns/` de Admin), compartido por la tabla, el detalle y la pestaña del usuario: `causerLabel(ActivityLog)` (nombre del usuario, 'Anonymous' sin causante, 'Deleted user #id' si ya no existe), `causerUrl(ActivityLog)` (detalle en `UserResource` solo si `UserResource::canView()`), `subjectLabel(ActivityLog)` (tipo legible e id, por ejemplo 'Post #12', nunca contenido) y `subjectUrl(ActivityLog)` (solo para `user`, `post` y `comment` cuando su recurso permite verlo y el registro existe; nunca para un comentario convertido en tombstone). Un sujeto que ya no existe se muestra como '<Tipo> #id (deleted)'. Se ejecutó junto con TASK-007: PHPStan no analiza un trait sin uso y lo marca como error, así que no se podía validar por separado.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**
- Para un causante borrado devuelve 'Deleted user #<id>' y ninguna URL, sin lanzar errores.
- Las URL solo se devuelven si el administrador autenticado tiene el permiso de lectura del recurso destino.
- Ningún label incluye el texto de una publicación o comentario.

**writeScope:**
- `app/Modules/Admin/Filament/Concerns/DescribesActivityLogParties.php` (nuevo)

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Concerns`

**Review:** Claude

### TASK-007: Definir la tabla de actividad

`Tables/ActivityLogsTable::configure()` (patrón de `PostsTable`): `modifyQueryUsing` con `with(['causer', 'subject'])`; columnas fecha (`created_at`, ordenable), log (etiqueta), evento (etiqueta del catálogo), causante y sujeto (con URL del concern); filtros `SelectFilter` de log y evento (opciones del catálogo, eventos agrupados por log), causante (un `Filter` con un `Select` buscable por nombre de usuario, porque `causer` es una relación polimórfica) y rango de fechas (como `PostsTable`); orden por `id` descendente, paginación 10/25/50, sin `recordActions` de edición ni `toolbarActions`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-005, TASK-006

**Acceptance Criteria:**
- Las columnas muestran etiquetas legibles y nunca `properties` crudas.
- Los filtros reducen el listado por log, evento, causante y fechas (comprobado en TASK-010).

**writeScope:**
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Tables/ActivityLogsTable.php` (nuevo)

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/ActivityLogs`

**Review:** Claude

### TASK-008: Definir el detalle de una actividad

`Schemas/ActivityLogInfolist::configure()` (patrón de `PostInfolist`): sección 'Activity' con fecha, log, evento, causante y sujeto (con enlaces del concern); sección 'Details' con las propiedades guardadas como pares clave-valor (listas unidas por comas, booleanos 'Yes'/'No'), excluyendo `ip` y `user_agent`; sección 'Request' con IP y user agent, o 'Not recorded' cuando la acción vino de consola.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-005, TASK-006

**Acceptance Criteria:**
- El detalle muestra las propiedades guardadas y la IP/user agent cuando existen.
- Una actividad de consola muestra 'Not recorded' en la sección de petición.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Schemas/ActivityLogInfolist.php` (nuevo)

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/ActivityLogs`

**Review:** Claude

### TASK-009: Crear el recurso ActivityLogs y sus páginas

`ActivityLogResource` (modelo `App\Modules\ActivityLog\Models\ActivityLog`) con el patrón de `UserResource`: sin overrides `can*()`, porque Filament consulta la policy del modelo (`ActivityLogPolicy`, TASK-016) para listar, ver, crear, editar y borrar; solo registra las páginas `index` y `view`; ícono de navegación, etiqueta 'Activity log', `table()` e `infolist()` delegados a TASK-007/008. Páginas `Pages/ListActivityLogs` (sin acciones de cabecera) y `Pages/ViewActivityLog` (sin acciones). El panel lo descubre solo (`discoverResources`).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-007, TASK-008, TASK-016

**Acceptance Criteria:**
- `php artisan route:list --name=filament.admin.resources.activity-logs` muestra solo las rutas `index` y `view`.
- Sin `admin.activity.viewAny` el recurso no aparece en la navegación (comprobado en TASK-010).

**writeScope:**
- `app/Modules/Admin/Filament/Resources/ActivityLogs/ActivityLogResource.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Pages/ListActivityLogs.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Pages/ViewActivityLog.php` (nuevo)

**validateCommand:** `rtk php artisan route:list --name=activity-logs`

**Review:** Claude

### TASK-010: Probar el listado de actividad

Agregar a `ActivityLogFactory` los estados `forEvent(ActivityLogEvent)`, `causedBy(Model)` y `performedOn(Model)` para crear actividad de distintos tipos. `ListActivityLogsTest` con `AdminFilamentTestContext`: acceso (sin permiso 403 y fuera de la navegación; con `admin-activity-reader` lista), columnas y etiquetas, filtros por log, evento, causante y fechas, causante anónimo y causante/sujeto eliminados, y una prueba de conteo de consultas (la cantidad no crece al pasar de pocas a muchas filas con distintos tipos de sujeto).

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-002, TASK-009

**Acceptance Criteria:**
- Un administrador sin `admin.activity.viewAny` recibe 403 en el listado y no ve 'Activity log' en la navegación.
- El número de consultas del listado es el mismo con 3 y con 30 actividades.
- Una actividad cuyo usuario y comentario ya fueron borrados se lista como 'Deleted user #id' y 'Comment #id (deleted)'.

**writeScope:**
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php` (nuevo)
- `app/Modules/ActivityLog/Database/Factories/ActivityLogFactory.php`

**validateCommand:** `rtk php artisan test --compact -d memory_limit=2G app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php`

**Review:** Claude

### TASK-011: Probar el detalle de actividad y su privacidad

`ViewActivityLogLogTest`: con `admin.activity.view` se ve el detalle con propiedades, IP y user agent; sin él, 403 al abrir la URL directa; el recurso no tiene páginas de crear/editar y la policy niega esas acciones; y el detalle de una actividad cuyo sujeto es una publicación o comentario con texto centinela no muestra ese texto.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-009

**Acceptance Criteria:**
- Sin `admin.activity.view` abrir `/admin/activity-logs/{id}` responde 403.
- El HTML del detalle no contiene el texto de la publicación ni del comentario que son sujeto.

**writeScope:**
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ViewActivityLogLogTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact -d memory_limit=2G app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ViewActivityLogLogTest.php`

**Review:** Claude

### TASK-012: Agregar la pestaña de actividad al detalle de usuario

Agregar a `User` la relación `causedActivityLogs(): MorphMany` hacia el modelo `ActivityLog` del módulo (morph `causer`), que Filament necesita como relación base. Crear `Users/RelationManagers/ActivityLogsRelationManager` con `$relationship = 'causedActivityLogs'` y una consulta propia (`->query()`, como hace `TrustedDevicesRelationManager`) basada en `ActivityLog::query()->involvingUser($owner)` para incluir también las actividades donde el usuario es sujeto. `canViewForRecord()` exige `ViewUser`, que el administrador pueda ver al usuario y `Gate::allows('viewAny', ActivityLog::class)` (la policy de TASK-016). Reutiliza `DescribesActivityLogParties` y el catálogo; sin acciones. Registrarlo en `UserResource::getRelations()`. Si al implementar Filament no respeta la consulta propia, limitarse a la relación de causante y anotarlo en el plan.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-005, TASK-006, TASK-016

**Acceptance Criteria:**
- En el detalle de un usuario, un lector de actividad ve la pestaña con las actividades donde el usuario es causante o sujeto, y no las de otros usuarios.
- Sin `admin.activity.viewAny` la pestaña no se muestra aunque el administrador pueda ver el usuario.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/ActivityLogsRelationManager.php` (nuevo)
- `app/User/Models/User.php`
- `app/Modules/Admin/Filament/Resources/Users/UserResource.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Users`

**Review:** Claude

### TASK-013: Probar la pestaña de actividad del usuario

`ActivityLogsRelationManagerTest` (patrón de `TrustedDevicesRelationManagerTest`): visibilidad según permiso, contenido limitado al usuario (causante o sujeto) y orden descendente.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-012

**Acceptance Criteria:**
- La pestaña lista la actividad causada por el usuario y la que lo tiene como sujeto (por ejemplo, un rol que le concedieron).
- Un administrador sin `admin.activity.viewAny` no puede montar el relation manager.

**writeScope:**
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/ActivityLogsRelationManagerTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact -d memory_limit=2G app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/ActivityLogsRelationManagerTest.php`

**Review:** Claude

### TASK-014: Documentar el permiso, el rol y el recurso

Actualizar CLAUDE.md (tabla del catálogo RBAC con `admin-activity-reader` y también `admin-users-provisioner`, que faltaba; el bootstrap asigna cuatro roles lectores; el recurso ActivityLogs y la pestaña del usuario; el modelo `ActivityLog` propio, `activitylog.activity_model` y `ActivityLogPolicy`; el catálogo de etiquetas), AGENTS.md (roles y comandos) y `docs/architecture/backend.md` (sección Admin y la de ActivityLog). Corregir las menciones a 'tres roles lectores'.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-003, TASK-011, TASK-013

**Acceptance Criteria:**
- Los tres documentos listan `admin-activity-reader` con sus permisos y mencionan los cuatro roles lectores del bootstrap.
- Ninguno sigue diciendo 'three reader roles' o 'tres roles lectores' donde ya no aplica.

**writeScope:**
- `CLAUDE.md`
- `AGENTS.md`
- `docs/architecture/backend.md`

**validateCommand:** `rtk grep -n "admin-activity-reader" CLAUDE.md AGENTS.md docs/architecture/backend.md`

**Review:** Claude

### TASK-015: Pasar los gates de calidad y la suite completa

`composer rector-clear-cache` → `composer rector-dry` → `composer rector`, `vendor/bin/pint --dirty --format agent`, `composer phpstan` y `composer test`. Solo corrige archivos de tareas previas.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-014

**Acceptance Criteria:**
- Rector no propone cambios, Pint queda limpio y PHPStan nivel max pasa.
- `composer test` pasa completo; cualquier fallo se reporta con su salida.

**writeScope:**
- Ninguno (solo verificación; corrige archivos de tareas previas)

**validateCommand:** `rtk composer rector-dry && rtk composer phpstan && rtk composer test`

**Review:** Claude

## Failure Modes

- Un administrador sin permiso abre la URL directa: Filament consulta `ActivityLogPolicy` y responde 403.
- El causante o el sujeto ya no existen: el concern devuelve 'Deleted …' con el id y ninguna URL, sin romper el listado.
- Un evento que ya no está en el catálogo (registro antiguo): el catálogo devuelve el valor crudo.
- Muchos tipos de sujeto en una página: la carga `with(['causer', 'subject'])` hace una consulta por tipo, no por fila.
- Filament no respeta la consulta propia del relation manager: la pestaña se limita a la actividad causada por el usuario y se anota en el plan.
- Bases existentes: correr `AdminAuthorizationSeeder` crea el rol y los permisos nuevos sin alterar los roles ya creados.

## Ship Cut

TASK-001 a TASK-011 y TASK-016 entregan lo que pide la tarea: permiso, rol, policy, recurso de solo lectura y sus pruebas. La pestaña del usuario (TASK-012/013) es independiente y puede cerrarse después; la tarea no se da por terminada sin la documentación y los gates.

## Test Coverage Map

- Catálogo RBAC y seeders: `AdminAuthorizationSeederTest`, `AdminUserSeederTest`, `AdminUserSeederRandomAdminsTest`.
- Bootstrap: `AdminBootstrapCommandTest`.
- Modelo, policy y catálogo de etiquetas: `ActivityLogTest`, `ActivityLogPolicyTest`, `ActivityLogEventCatalogTest`, más la suite existente de ActivityLog.
- Recurso: `ListActivityLogsTest` (acceso, columnas, filtros, borrados, conteo de consultas) y `ViewActivityLogLogTest` (detalle, 403, privacidad).
- Pestaña de usuario: `ActivityLogsRelationManagerTest`.

## Execution Summary

- Tareas: 16.
- Capas paralelizables: L0: TASK-001, TASK-004, TASK-005; L1: TASK-002, TASK-003, TASK-016, TASK-006; L2: TASK-007, TASK-008, TASK-012; L3: TASK-009, TASK-013; L4: TASK-010, TASK-011; L5: TASK-014; L6: TASK-015.
- Ruta crítica (7 tareas): TASK-004 → TASK-006 → TASK-007 → TASK-009 → TASK-011 → TASK-014 → TASK-015.
- Próxima ejecución: implementar solo la siguiente tarea atómica y detenerse para revisión.

## Task Dependencies

- **TASK-001** ← sin dependencias
- **TASK-002** ← TASK-001
- **TASK-003** ← TASK-001
- **TASK-004** ← sin dependencias
- **TASK-005** ← sin dependencias
- **TASK-016** ← TASK-001, TASK-004
- **TASK-006** ← TASK-004
- **TASK-007** ← TASK-005, TASK-006
- **TASK-008** ← TASK-005, TASK-006
- **TASK-009** ← TASK-007, TASK-008, TASK-016
- **TASK-010** ← TASK-002, TASK-009
- **TASK-011** ← TASK-009
- **TASK-012** ← TASK-005, TASK-006, TASK-016
- **TASK-013** ← TASK-012
- **TASK-014** ← TASK-003, TASK-011, TASK-013
- **TASK-015** ← TASK-014
