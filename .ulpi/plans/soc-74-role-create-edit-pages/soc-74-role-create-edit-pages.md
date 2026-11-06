# Plan: SOC-74: Crear y editar roles de administración en páginas propias en lugar de modales

## Overview

Sacar la creación y la edición de roles de los modales: una página de creación con un asistente de tres pasos (datos, permisos, revisión) y un resumen al costado, y una página de edición que reúne datos y permisos de un rol personalizado (solo permisos en uno de sistema). Se marca qué permisos son sensibles para que no se concedan sin darse cuenta. Como extras del modo EXPANSION, el panel avisa antes de salir con cambios sin guardar y conceder un permiso sensible exige confirmarlo con una casilla.

**Linear:** [SOC-74](https://linear.app/social-hub-ang/issue/SOC-74/feature-create-and-edit-admin-roles-on-dedicated-pages-instead-of) (sub-issue de [SOC-72](https://linear.app/social-hub-ang/issue/SOC-72/feature-improve-the-roles-module-of-the-admin-panel-pages-filters-and)) · **Modo:** EXPANSION · **Revisión predeterminada:** Claude

## Scope Challenge

EXPANSION con revisión de Claude. Decisiones del usuario (2026-11-07): «Duplicar» sigue siendo el modal rápido y queda auditado como `admin.role_duplicated`; la página de creación ofrece «Partir de un rol existente», que solo copia sus permisos al formulario y se audita como `admin.role_created`. Permisos sensibles: `admin.users.roles`, `admin.users.create`, `admin.users.suspend`, `admin.users.reactivate`, `admin.roles.update`, `admin.roles.create`, `admin.roles.delete` y `admin.backups.download`. Las pruebas de los modales que desaparecen (`ListRolesCreateActionTest`, `ViewRoleEditPermissionsActionTest` y los casos de «Editar datos» de `ViewRoleLifecycleActionsTest`) se reemplazan por pruebas equivalentes de las páginas. Hallazgos: `RoleResource` solo registra `index`, `matrix` y `view`; `ListRoles::createRole` y `ViewRole::editPermissions`/`editDetails` concentran la lógica (contraseña, nombre repetido, notificaciones); `EditsRolePermissions` ya da las casillas por área con dependencias en vivo y el resumen de cambios; `AdminAuthorizationService` ya tiene `createRole`, `updateRoleDetails` y `syncRolePermissions`; el panel no tiene activadas las alertas de cambios sin guardar.

## Prerequisites

- SOC-70 mergeado: `AdminAuthorizationService::createRole`, `updateRoleDetails`, `syncRolePermissions`, `RolePolicy` (`create`, `update`, `updateDetails`) y `EditsRolePermissions`.
- `ConfirmsCurrentPassword` (SOC-69) con límite de intentos por prefijo.
- `AdminPermission::requires()`/`withRequirements()`/`missingRequirements()` y `area()`.

## Non-Goals

- Cambiar «Duplicar» (sigue como modal) o «Eliminar».
- Cambiar las reglas de dependencias entre permisos o agregar permisos nuevos.
- Comparar con otro rol mientras se eligen permisos (podría ir en SOC-76 con la comparación de roles).
- Traducir el nombre y la descripción de un rol personalizado.
- Editar roles fuera del guard `admin`.

## Contracts

- `AdminPermission::isSensitive(): bool` es verdadero exactamente para `admin.users.roles`, `admin.users.create`, `admin.users.suspend`, `admin.users.reactivate`, `admin.roles.update`, `admin.roles.create`, `admin.roles.delete` y `admin.backups.download`; `AdminPermission::sensitive(): list<self>` los devuelve en orden de catálogo.
- `RoleResource::getPages()` agrega `create` (`/create`, `Pages/CreateRole`) y `edit` (`/{record}/edit`, `Pages/EditRole`). Filament autoriza `create` con `RolePolicy::create` y `edit` con `RolePolicy::update`; `admin` (bloqueado) no tiene página de edición porque `update` lo niega.
- `CreateRole` es un asistente de tres pasos: Datos (nombre, descripción, «Partir de un rol existente» que reemplaza las casillas por los permisos de ese rol con sus requisitos), Permisos (casillas de `EditsRolePermissions`) y Revisión (resumen y contraseña). Guarda con `AdminAuthorizationService::createRole()`; un nombre repetido (`role_label_taken`) vuelve al campo de nombre como error de validación; otros fallos se notifican sin crear nada; al crear abre el detalle.
- `EditRole` muestra, para un rol personalizado, nombre y descripción editables y los permisos; para uno de sistema, el nombre y la descripción traducidos en solo lectura y los permisos. Al guardar pide la contraseña y aplica, en una sola `DB::transaction`, `updateRoleDetails()` (solo si `updateDetails` lo permite) y `syncRolePermissions()`: si una parte falla no cambia nada.
- El resumen lateral (`EditsRolePermissions`) muestra en vivo cuántos permisos, qué áreas cubre, qué se agrega y qué se quita respecto del rol guardado (o de ninguno al crear) y la lista de permisos sensibles elegidos; cada casilla de un permiso sensible lleva la marca «Sensible».
- `ListRoles` y `ViewRole` dejan de tener los modales «Nuevo rol», «Editar permisos» y «Editar datos»: «Nuevo rol» y «Editar» son enlaces a las páginas. «Duplicar» y «Eliminar» no cambian.
- Extras: `AdminPanelProvider` activa `unsavedChangesAlerts()` (afecta a todas las páginas de crear y editar del panel); conceder un permiso sensible que el rol no tenía exige marcar la casilla «Entiendo que estos permisos…» antes de guardar, en ambas páginas.
- Textos nuevos en `lang/{en,es}/admin/roles.php` (claves exactas en TASK-002).

## Existing Code Leverage

- `ListRoles::createRole()` y `ViewRole::editPermissions()`/`editDetails()`: contraseña, nombre repetido y notificaciones que se mueven a las páginas.
- `EditsRolePermissions`: casillas por área, dependencias en vivo, `permissionsByArea()`, `selectedPermissions()` y `permissionChangeSummary()`.
- `ConfirmsCurrentPassword` (campo y límite de intentos).
- `AnnouncementResource` (`create`/`edit` con `AnnouncementService`): patrón de páginas de crear y editar que escriben por un servicio.
- `Role::inDisplayOrder()` y `displayName()` para el selector «Partir de un rol existente».

## Tasks

### TASK-001: Marca de permisos sensibles en el catálogo

Agregar a `AdminPermission` los métodos `isSensitive()` y `sensitive()` según el contrato. Pruebas en un archivo nuevo de `Tests/Enums/`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `isSensitive()` es verdadero exactamente para los ocho permisos del contrato y falso para el resto, incluido `admin.access`.
2. `sensitive()` devuelve esos ocho en orden de catálogo, sin repetidos.

**writeScope:**

- `app/Modules/Admin/Enums/AdminPermission.php`
- `app/Modules/Admin/Tests/Enums/AdminPermissionSensitivityTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Enums`

**Review:** claude

### TASK-002: Textos de las páginas de crear y editar (en/es)

Agregar en `lang/{en,es}/admin/roles.php`: `pages.create.{title,breadcrumb}` y `pages.edit.{title,breadcrumb}` (`:role`); `wizard.{details,permissions,review}.{label,description}`; `form.start_from` y `form.start_from_helper`; `form.read_only_details` (aviso para roles de sistema); `form.sensitive` (la marca); `summary.{count,areas,sensitive_heading,no_sensitive}` (con `:count` y `:areas`); `form.acknowledge_sensitive` y su mensaje de error; `actions.edit.label`; `actions.edit.success` (`:role`) y `actions.edit.unchanged`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `AdminTranslationKeysTest` pasa: mismas claves, sin textos vacíos y mismos placeholders en `en` y `es`.
2. Las claves existentes que siguen en uso (`actions.create.*`, `form.*`, `summary.*`) no cambian de significado.

**writeScope:**

- `lang/en/admin/roles.php`
- `lang/es/admin/roles.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**Review:** claude

### TASK-003: Marca «Sensible» y resumen ampliado en EditsRolePermissions

En `EditsRolePermissions`, marcar cada permiso sensible en su casilla (en la descripción, con el texto `form.sensitive`) y agregar `permissionSummarySchema(): list<Component>` con el resumen en vivo: cantidad de permisos, áreas cubiertas, lo que se agrega y quita (el `permissionChangeSummary()` actual) y los permisos sensibles elegidos (o `summary.no_sensitive`). Sin cambiar las dependencias en vivo ni `selectedPermissions()`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001, TASK-002

**Acceptance Criteria:**

1. Las casillas de los permisos sensibles muestran la marca y las demás no.
2. El resumen da la cantidad, las áreas y los sensibles correctos para una selección mixta y para una vacía.
3. Las pruebas existentes de `EditsRolePermissionsTest` (dependencias en vivo, resumen de cambios) siguen pasando.

**writeScope:**

- `app/Modules/Admin/Filament/Concerns/EditsRolePermissions.php`
- `app/Modules/Admin/Tests/Filament/Concerns/EditsRolePermissionsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Concerns/EditsRolePermissionsTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-004: Página de creación con asistente

Crear `Pages/CreateRole` (extiende `CreateRecord`, con `HasWizard`) según el contrato: pasos Datos (nombre, descripción, select «Partir de un rol existente» con los roles del guard `admin` en orden de visualización, que al cambiar llena las casillas con sus permisos), Permisos (las casillas y el resumen en dos columnas) y Revisión (el resumen y la contraseña). `handleRecordCreation()` confirma la contraseña y llama a `createRole()`; `role_label_taken` vuelve al campo nombre; otros fallos notifican y detienen (`halt()`). Registrar `create` en `RoleResource::getPages()`. Pruebas nuevas que cubren los casos de `ListRolesCreateActionTest`.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. Crear desde la página deja el rol `custom-…` con los permisos elegidos y sus requisitos, registra `admin.role_created` y abre el detalle; «Partir de un rol existente» copia los permisos de ese rol y se pueden ajustar antes de guardar.
2. Un nombre vacío o repetido (sin importar mayúsculas) se marca en el campo nombre y no crea nada; una contraseña incorrecta tampoco.
3. Un lector de roles recibe 403 en `/admin/roles/create`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RoleResource.php`
- `app/Modules/Admin/Filament/Resources/Roles/Pages/CreateRole.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/CreateRoleTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/CreateRoleTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-005: Página de edición de un rol

Crear `Pages/EditRole` (extiende `EditRecord`) según el contrato: para un rol personalizado, nombre y descripción editables (si `updateDetails` lo permite) y las casillas; para uno de sistema, nombre y descripción traducidos en solo lectura con el aviso `form.read_only_details`; el resumen al costado comparado con el rol guardado; la contraseña. `handleRecordUpdate()` aplica `updateRoleDetails()` y `syncRolePermissions()` en una transacción, notifica cambios o «sin cambios» y vuelve al detalle. Registrar `edit` en `RoleResource::getPages()`. Pruebas nuevas que cubren los casos de `ViewRoleEditPermissionsActionTest` y de «Editar datos».

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**

1. Editar un rol personalizado cambia nombre, descripción y permisos y registra `admin.role_updated` y los `admin.role_permission_*`; si una parte falla (por ejemplo, el administrador perdería el acceso con el que edita o el nombre está tomado) no cambia nada.
2. En un rol de sistema solo se pueden cambiar los permisos (un nombre enviado a mano se ignora); `admin` y un lector de roles reciben 403 en la página de edición.
3. Sin cambios, guardar notifica «sin cambios» y no registra actividad; una contraseña incorrecta no cambia nada.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/RoleResource.php`
- `app/Modules/Admin/Filament/Resources/Roles/Pages/EditRole.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/EditRoleTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/EditRoleTest.php app/Modules/Admin/Tests/Translations`

**Review:** claude

### TASK-006: «Nuevo rol» lleva a la página de creación

En `ListRoles`, reemplazar la acción modal `createRole` por un enlace a `RoleResource::getUrl('create')` visible solo con `RolePolicy::create`, y quitar `createRole()` y lo que solo usaba. Eliminar `ListRolesCreateActionTest` (sus casos viven en `CreateRoleTest`) y ajustar `ListRolesTest` para comprobar el enlace.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**

1. Un administrador de roles ve «Nuevo rol» con el enlace a la página de creación; un lector de roles no lo ve.
2. `ListRoles` ya no tiene la acción modal ni el método `createRole`, y el resto de `ListRolesTest` sigue pasando.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/ListRoles.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesCreateActionTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ListRolesTest.php`

**Review:** claude

### TASK-007: «Editar» en el detalle lleva a la página de edición

En `ViewRole`, reemplazar las acciones modales «Editar permisos» y «Editar datos» por un enlace «Editar» a `RoleResource::getUrl('edit')`, visible con `RolePolicy::update` (oculto en `admin`), y quitar `editPermissions()`, `editDetails()` y lo que solo usaban. Eliminar `ViewRoleEditPermissionsActionTest` (sus casos viven en `EditRoleTest`) y quitar de `ViewRoleLifecycleActionsTest` los casos de «Editar datos», conservando los de duplicar y eliminar.

**Type:** refactor · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-005

**Acceptance Criteria:**

1. Un administrador de roles ve «Editar» en un rol personalizado y en uno de sistema, pero no en `admin`; un lector de roles no lo ve.
2. «Duplicar» y «Eliminar» y sus pruebas siguen funcionando igual, y la pestaña de usuarios sigue refrescando el detalle.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/ViewRole.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleEditPermissionsActionTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleLifecycleActionsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleLifecycleActionsTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/ViewRoleTest.php`

**Review:** claude

### TASK-008: Confirmar la concesión de permisos sensibles

En `CreateRole` (paso Revisión) y `EditRole`, mostrar una casilla obligatoria `form.acknowledge_sensitive` solo cuando la selección concede un permiso sensible que el rol no tenía (al crear, cualquier sensible elegido); sin marcarla no se guarda. Pruebas en un archivo nuevo.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-005, TASK-006, TASK-007

**Acceptance Criteria:**

1. Crear o editar concediendo un permiso sensible nuevo sin marcar la casilla no guarda nada y marca el error; marcándola guarda.
2. Si no se concede ningún permiso sensible nuevo (por ejemplo, quitar uno o conservar los que ya tenía) la casilla no aparece y no se exige.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Roles/Pages/CreateRole.php`
- `app/Modules/Admin/Filament/Resources/Roles/Pages/EditRole.php`
- `app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/SensitivePermissionAcknowledgementTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/SensitivePermissionAcknowledgementTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/CreateRoleTest.php app/Modules/Admin/Tests/Filament/Resources/Roles/Pages/EditRoleTest.php`

**Review:** claude

### TASK-009: Avisar antes de salir con cambios sin guardar

Activar `unsavedChangesAlerts()` en `AdminPanelProvider` y cubrirlo con una prueba de configuración del panel. Afecta a todas las páginas de crear y editar del panel (también Anuncios) y a los modales abiertos.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** Ninguna

**Acceptance Criteria:**

1. El panel `admin` tiene las alertas de cambios sin guardar activadas.
2. Las pruebas de Anuncios (crear y editar) siguen pasando sin cambios.

**writeScope:**

- `app/Modules/Admin/Providers/AdminPanelProvider.php`
- `app/Modules/Admin/Tests/Filament/AdminPanelConfigurationTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/AdminPanelConfigurationTest.php app/Modules/Admin/Tests/Filament/Resources/Announcements`

**Review:** claude

### TASK-010: Documentación de las páginas de roles

Actualizar `docs/architecture/modules/admin.md`: páginas `create` y `edit`, el asistente, el resumen lateral, los permisos sensibles y su confirmación, las alertas de cambios sin guardar y que «Duplicar» sigue como modal; ajustar las descripciones de `ListRoles` y `ViewRole` de SOC-69 y SOC-70 que hablan de los modales.

**Type:** docs · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-008, TASK-009

**Acceptance Criteria:**

1. Ningún texto de `docs/` describe ya «Nuevo rol», «Editar permisos» o «Editar datos» como modales.
2. Los nombres de clases y métodos citados existen en el código (verificado con `grep`).

**writeScope:**

- `docs/architecture/modules/admin.md`

**validateCommand:** `rtk grep -n "CreateRole\|EditRole\|isSensitive" docs/architecture/modules/admin.md`

**Review:** claude

### TASK-011: Gates finales y prueba en el navegador

Pint (`--dirty --format agent`), `composer rector-clear-cache` → `rector-dry` → `rector` sobre todo el proyecto, `composer phpstan`, `composer test` y `composer doctor`. Prueba manual: crear un rol desde cero y partiendo de otro, editar uno personalizado y uno de sistema, conceder un permiso sensible, y salir de una página con cambios sin guardar.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-010

**Acceptance Criteria:**

1. Pint, Rector (sin pendientes), PHPStan max, la suite completa y `composer doctor` pasan.
2. Las páginas funcionan en el navegador sin errores de consola ni de Laravel.

**writeScope:**

- (ninguno: solo comandos)

**validateCommand:** `rtk composer phpstan && rtk composer test && rtk composer doctor`

**Review:** claude

## Failure Modes

- Otro administrador crea un rol con el mismo nombre mientras se completa el asistente: `createRole()` responde `role_label_taken` y el error vuelve al campo nombre.
- El administrador quita de un rol el permiso con el que lo está editando: `syncRolePermissions()` responde `actor_would_lose_access` y la transacción deshace también el cambio de nombre.
- Otro administrador cambia el rol mientras está abierta la página de edición: se guarda la selección enviada (la última escritura gana) y el resumen de la notificación lo refleja; no se agrega bloqueo optimista en esta tarea.
- Se envía a mano un nombre para un rol de sistema: `updateRoleDetails()` no se llama porque `updateDetails` lo niega, y el servicio igual lo rechazaría con `system_role_protected`.
- Se envía a mano un permiso fuera del catálogo: `selectedPermissions()` lo descarta.

## Ship Cut

Mínimo entregable: TASK-001 a TASK-007 y TASK-011 (permisos sensibles, textos, resumen, ambas páginas y los enlaces que reemplazan a los modales). TASK-008 (confirmar permisos sensibles) y TASK-009 (alertas de cambios sin guardar) son los extras del modo EXPANSION; TASK-010 documenta lo entregado. Entre TASK-004 y TASK-007 conviven los modales viejos y las páginas nuevas, así que conviene no detenerse en medio.

## Test Coverage Map

| Área | Tareas |
| --- | --- |
| Permisos sensibles | TASK-001, TASK-003 |
| Traducciones | TASK-002, TASK-003, TASK-004, TASK-005 |
| Página de creación | TASK-004 |
| Página de edición | TASK-005 |
| Enlaces desde lista y detalle | TASK-006, TASK-007 |
| Confirmación de sensibles | TASK-008 |
| Configuración del panel | TASK-009 |

## Execution Summary

- Tareas: 11
- Ruta crítica: TASK-001 → TASK-003 → TASK-004 → TASK-005 → TASK-007 → TASK-008 → TASK-010 → TASK-011
- Capas paralelas:

  1. TASK-001, TASK-002, TASK-009
  2. TASK-003
  3. TASK-004
  4. TASK-005, TASK-006
  5. TASK-007
  6. TASK-008
  7. TASK-010
  8. TASK-011

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | TASK-001, TASK-002 |
| TASK-004 | TASK-003 |
| TASK-005 | TASK-004 |
| TASK-006 | TASK-004 |
| TASK-007 | TASK-005 |
| TASK-008 | TASK-005, TASK-006, TASK-007 |
| TASK-009 | — |
| TASK-010 | TASK-008, TASK-009 |
| TASK-011 | TASK-010 |
