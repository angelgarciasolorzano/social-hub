# Plan: SOC-71: Aislar notificaciones React de Filament

## Overview

Aislar de forma consistente el centro React de las notificaciones Filament y de tipos desconocidos. Una enum de Notification será la fuente de verdad para los valores PHP persistidos; la frontera TypeScript usará una lista runtime equivalente para rechazar eventos Echo no admitidos antes de modificar Zustand. Historial, recientes, contador diferido y acciones de lectura se limitarán a comment_on_post, comment_reply y likes_grouped. Se conservan la tabla compartida, el payload JSON, la paginación, la autorización por propietario y el panel Filament.

**Modo:** EXPANSION acotada. **Revisión por defecto:** Codex después de cada tarea atómica.

## Scope Challenge

- El defecto está presente en cuatro superficies backend: historial y recientes en NotificationController, ambas acciones de lectura y el conteo diferido en NotificationProps.
- SOC-71 ya define la lista cerrada de tres tipos y la coexistencia por usuario con notificaciones Filament/desconocidas. El filtro debe aplicarse en la consulta antes de ordenar, limitar o paginar.
- El frontend también recibe eventos por Echo directamente en NotificationRealtimeListener. El listener y el store no reciben datos a través de las consultas HTTP, así que el filtro de servidor solo no cierra toda la frontera React.
- phpunit.xml y tests/Pest.php ya incluyen el módulo Notification desde su carpeta raíz. TASK-001 acota ambos registros a app/Modules/Notification/Tests, como hacen los demás módulos, y evita descubrir por accidente archivos ajenos a tests.
- La expansión se mantiene acotada: no se cambia la tabla, las rutas ni el shape JSON, no se reemplaza Filament y no se añade una infraestructura compartida PHP/TypeScript.

## Prerequisites

- SOC-71 permanece en Backlog y su criterio funcional es filtrar comment_on_post, comment_reply y likes_grouped en las superficies del centro React.
- Laravel ya persiste notificaciones en la tabla notifications mediante User::notifications(); Filament y el centro React comparten esa persistencia.
- El módulo Notification y sus pruebas ya existen y están incluidas indirectamente por sus carpetas raíz en phpunit.xml y tests/Pest.php. TASK-001 estrecha esos registros a la carpeta Tests.
- No se requieren migraciones, rutas nuevas ni dependencias.

## Non-Goals

- Modificar la creación, polling, payload o interfaz de notificaciones de Filament.
- Cambiar la tabla notifications, sus columnas, las rutas, el orden, el tamaño de página o el contrato de NotificationResource.
- Añadir tipos de notificación, preferencias, notificaciones de seguridad, anuncios en vivo o un generador de tipos compartido entre PHP y TypeScript.
- Añadir un runner o una dependencia de pruebas frontend; el repositorio no tiene una suite React configurada.

## Contracts

- La enum App\Modules\Notification\Enums\NotificationType es la fuente PHP de los tres valores persistidos; sus emisores, el servicio de likes y las consultas usan esos casos.
- Las lecturas y escrituras HTTP pasan por la relación del usuario autenticado y añaden whereIn(type, tipos soportados). Una notificación propia de tipo desconocido responde 404 al marcarla y conserva read_at.
- El historial filtra antes de cursorPaginate; recientes filtra antes de limit. Se mantiene el orden descendente por created_at e id.
- Mark-all actualiza únicamente filas no leídas del usuario y de los tipos admitidos. El conteo diferido usa la misma lista de tipos.
- La frontera React acepta los mismos tres valores. NotificationRealtimeListener descarta un payload desconocido antes de llamar al store o mostrar un toast; los borrados de likes_grouped siguen funcionando.
- El contrato JSON {id, type, summary, actors, target, readAt, createdAt}, la separación por usuario y el comportamiento de los tres tipos conocidos permanecen estables.
- La tabla notifications continúa compartida. No se altera el flujo de autorización ni de persistencia que usa Filament.

## Existing Code Leverage

- `app/Modules/Notification/Controllers/NotificationController.php` ya contiene historial, recientes, lectura individual y lectura total.
- `app/Modules/Notification/Props/NotificationProps.php` comparte unreadNotificationsCount como prop diferida.
- `app/Modules/Notification/Notifications/CommentedOnPostNotification.php,` RepliedToCommentNotification.php y LikeNotification.php ya definen los tipos usados por los emisores.
- `app/Modules/Notification/Services/LikeNotificationGroupService.php` usa el tipo de likes para encontrar y actualizar el grupo existente.
- `Los` tests de controlador, props, emisores y agrupación ya cubren paginación, propiedad, payloads y conteos; se ampliarán con escenarios mixtos.
- `resources/js/modules/notification/types/notification.ts` define el tipo frontend; NotificationRealtimeListener.tsx recibe Echo y notificationStore.ts aplica las mutaciones locales.
- `docs/architecture/backend.md,` docs/architecture/frontend.md y CLAUDE.md ya describen el límite entre Admin y React, pero documentan la falta actual de filtro.

## Tasks

### TASK-001: Acotar el registro de pruebas del módulo Notification

Cambiar el directorio del testsuite Notification en phpunit.xml y la ruta de Pest desde app/Modules/Notification hasta app/Modules/Notification/Tests. El registro actual ya incluye las pruebas de forma indirecta desde la raíz del módulo; alinear ambos archivos con los demás módulos hace explícito el alcance de descubrimiento.

**Type:** chore  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** Ninguna

**Acceptance Criteria:**

1. phpunit.xml y Pest registran explícitamente app/Modules/Notification/Tests, no la raíz del módulo.
2. Las pruebas del módulo conservan el TestCase y RefreshDatabase de Pest.
3. El testsuite Notification ejecuta las pruebas existentes desde su directorio dedicado.

**Write scope:**

- `phpunit.xml`
- `tests/Pest.php`

**Validate command:**

```bash
rtk php artisan test --compact --testsuite=Notification
```

### TASK-002: Definir la enum canónica de tipos soportados

Crear NotificationType como enum backed con los tres tipos permitidos y un método values(): list<string> derivado de sus casos. Añadir una prueba en el módulo que fije los valores de contrato y compruebe que un tipo desconocido no pertenece a la allowlist. No requiere registro adicional porque las clases del módulo usan autoload PSR-4.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** TASK-001

**Acceptance Criteria:**

1. La enum contiene exactamente comment_on_post, comment_reply y likes_grouped, con los valores persistidos actuales.
2. values() devuelve la lista de valores de los casos sin otra lista literal de tipos soportados.
3. NotificationType::tryFrom() rechaza un tipo Filament/desconocido y ese valor no aparece en values().

**Write scope:**

- `app/Modules/Notification/Enums/NotificationType.php`
- `app/Modules/Notification/Tests/Enums/NotificationTypeTest.php`

**Validate command:**

```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Enums/NotificationTypeTest.php
```

### TASK-003: Usar la enum en notificaciones de comentarios

Cambiar CommentedOnPostNotification y RepliedToCommentNotification para devolver los valores de NotificationType en databaseType() y broadcastType(). Alinear CommentNotificationsTest con el caso de enum que corresponde a cada emisor; NotificationTypeTest ya fija los literales públicos.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002

**Acceptance Criteria:**

1. Las notificaciones de comentario y respuesta usan la enum para los tipos de base de datos y broadcast.
2. CommentNotificationsTest comprueba que cada emisor usa su caso de enum; NotificationTypeTest fija los literales públicos y hace fallar un cambio accidental de valor.
3. Resumen, destinatario, canales y payload de ambas notificaciones siguen comportándose igual.

**Write scope:**

- `app/Modules/Notification/Notifications/CommentedOnPostNotification.php`
- `app/Modules/Notification/Notifications/RepliedToCommentNotification.php`
- `app/Modules/Notification/Tests/Notifications/CommentNotificationsTest.php`

**Validate command:**

```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Notifications/CommentNotificationsTest.php
```

### TASK-004: Alinear la agrupación de likes con la enum

Cambiar LikeNotificationGroupService para buscar el grupo mediante NotificationType y actualizar LikeNotificationGroupServiceTest para usar la misma definición. Mantener el id estable del grupo y la semántica actual de agrupar, quitar actores y borrar grupos vacíos.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002

**Acceptance Criteria:**

1. La consulta del grupo unread de likes usa NotificationType::LikesGrouped->value y no una cadena duplicada.
2. Likes agrupados existentes se siguen actualizando por id sin crear una segunda fila.
3. Los escenarios actuales de unlike, grupo leído y eliminación del último actor siguen pasando.

**Write scope:**

- `app/Modules/Notification/Services/LikeNotificationGroupService.php`
- `app/Modules/Notification/Tests/Services/LikeNotificationGroupServiceTest.php`

**Validate command:**

```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Services/LikeNotificationGroupServiceTest.php
```

### TASK-005: Usar la enum al emitir likes agrupados

Cambiar LikeNotification para devolver NotificationType::LikesGrouped en databaseType() y broadcastType(), retirar su constante TYPE duplicada y actualizar LikeNotificationSubscriberTest. Esta tarea depende de que el servicio y su prueba ya no referencien la constante anterior.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-004

**Acceptance Criteria:**

1. El emisor usa la enum para el tipo de base de datos y broadcast, y no mantiene una segunda constante literal.
2. El test comprueba que el valor persistido conserva exactamente likes_grouped.
3. La recepción y agrupación siguen usando el mismo tipo y no pierden las actualizaciones o borrados del grupo.

**Write scope:**

- `app/Modules/Notification/Notifications/LikeNotification.php`
- `app/Modules/Notification/Tests/Listeners/LikeNotificationSubscriberTest.php`

**Validate command:**

```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Listeners/LikeNotificationSubscriberTest.php
```

### TASK-006: Filtrar historial, recientes y acciones de lectura

Aplicar NotificationType::values() en las consultas de index(), recent(), markAsRead() y markAllAsRead(). Añadir escenarios mixtos a NotificationControllerTest con notificaciones soportadas, Filament/desconocidas y notificaciones de otro usuario; filtrar antes de ordenar, limitar o paginar.

**Type:** bug  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002

**Acceptance Criteria:**

1. Historial y recientes devuelven solo los tres tipos soportados para el usuario autenticado; el orden, límite y cursores actuales se conservan.
2. Marcar una notificación propia de tipo desconocido responde 404 y deja read_at intacto; una notificación ajena continúa respondiendo 404.
3. Mark-all cambia read_at solo en notificaciones unread admitidas del usuario y deja intactas las de Filament/desconocidas y las ya leídas.

**Write scope:**

- `app/Modules/Notification/Controllers/NotificationController.php`
- `app/Modules/Notification/Tests/Controllers/NotificationControllerTest.php`

**Validate command:**

```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Controllers/NotificationControllerTest.php
```

### TASK-007: Limitar el contador diferido a tipos soportados

Añadir NotificationType::values() a la consulta de unreadNotificationsCount en NotificationProps. Actualizar SharedUnreadNotificationsTest y NotificationPropsTest para crear filas soportadas y no soportadas del mismo usuario, sin perder las comprobaciones existentes para guests, usuarios distintos y carga diferida.

**Type:** bug  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002

**Acceptance Criteria:**

1. El conteo diferido cuenta únicamente filas no leídas propias de los tipos admitidos.
2. Filas Filament/desconocidas del mismo usuario no inflan el contador; las filas de otro usuario tampoco se cuentan.
3. El comportamiento de guest y la carga diferida de Inertia se conservan.

**Write scope:**

- `app/Modules/Notification/Props/NotificationProps.php`
- `app/Modules/Notification/Tests/Props/SharedUnreadNotificationsTest.php`
- `app/Modules/Notification/Tests/Props/NotificationPropsTest.php`

**Validate command:**

```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Props/SharedUnreadNotificationsTest.php app/Modules/Notification/Tests/Props/NotificationPropsTest.php
```

### TASK-008: Cerrar la frontera de tipos en Echo y React

Definir NotificationType desde una tupla TypeScript con los tres valores admitidos y exponer un type guard runtime. En NotificationRealtimeListener, descartar antes de cualquier mutación del store o toast los payloads desconocidos, incluidos los de borrado; conservar el borrado especial de likes_grouped y los eventos soportados.

**Type:** bug  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-002

**Acceptance Criteria:**

1. NotificationType queda cerrado a comment_on_post, comment_reply y likes_grouped, y el guard rechaza strings desconocidos en runtime.
2. Un payload normal desconocido no entra a Zustand ni genera toast; un borrado desconocido no altera el store.
3. Los broadcasts soportados continúan actualizando el estado; el evento deleted de likes_grouped sigue quitando el grupo.

**Write scope:**

- `resources/js/modules/notification/types/notification.ts`
- `resources/js/modules/notification/components/NotificationRealtimeListener.tsx`

**Validate command:**

```bash
rtk npx eslint resources/js/modules/notification/types/notification.ts resources/js/modules/notification/components/NotificationRealtimeListener.tsx
rtk npx prettier --check resources/js/modules/notification/types/notification.ts resources/js/modules/notification/components/NotificationRealtimeListener.tsx
```

### TASK-009: Sincronizar la documentación de arquitectura

Actualizar CLAUDE.md y las guías de arquitectura backend/frontend para reflejar la fuente de tipos, el filtrado de consultas y acciones, el contador acotado, el descarte de eventos Echo desconocidos y la frontera intacta con Filament.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P2  
**Depends on:** TASK-003, TASK-005, TASK-006, TASK-007, TASK-008

**Acceptance Criteria:**

1. CLAUDE.md y backend.md describen la lista canónica y que historial, recientes, contador y acciones solo operan sobre tipos soportados.
2. frontend.md documenta que Echo descarta eventos desconocidos y conserva los tres tipos soportados.
3. Las tres guías mantienen explícito que la tabla es compartida y que el emisor/UI/polling de Filament no cambia.

**Write scope:**

- `CLAUDE.md`
- `docs/architecture/backend.md`
- `docs/architecture/frontend.md`

**Validate command:**

```bash
rtk git diff --check -- CLAUDE.md docs/architecture/backend.md docs/architecture/frontend.md
```

## Failure Modes

- Si algún endpoint o el contador no usa la enum, Filament vuelve a aparecer en React o el conteo se infla.
- Si el filtro se aplica después de cursorPaginate o limit, la UI puede recibir páginas incompletas o saltarse filas soportadas.
- Si markAsRead filtra por propietario pero no por tipo, una solicitud puede modificar una notificación propia de Filament/desconocida; la respuesta esperada para ese id es 404 y no mutación.
- Si markAllAsRead omite la allowlist, marcar todo desde React cambia notificaciones internas del panel.
- Si Echo actualiza Zustand antes de validar el tipo, un tipo futuro no admitido puede inflar el conteo local; si el guard bloquea también likes_grouped deleted, los grupos vacíos reaparecen.
- Si se modifica la relación de notificaciones del usuario o el flujo Admin en vez de limitar consultas React, se rompe el límite de propiedad entre módulos.

## Ship Cut

- El corte seguro requiere el registro explícito del directorio Tests, la enum usada por emisores/agrupación/consultas, cobertura de coexistencia para usuario dueño, el guard Echo y documentación sincronizada.
- Mantener intactos paginación estable, límite de cinco recientes, payload JSON, respuestas 404 para recursos ajenos/no admitidos y comportamiento de lectura de tipos conocidos.
- Después de las tareas PHP, ejecutar los gates del repo: Pint dirty, PHPStan, Rector clear-cache → dry → apply, y la suite Pest completa. Ejecutar los gates frontend: format:check, lint:check, types, build y build:ssr. composer doctor es el gate local backend final; no ejecutar comprobaciones posteriores.
- Como no hay suite React, verificar manualmente una sesión con eventos soportados y un payload Echo desconocido, incluyendo que solo likes_grouped deleted produce la eliminación local.
- La regla del repo requiere completar una tarea atómica, resumir cambios y checks y detenerse para revisión Codex/usuario antes de empezar la siguiente.

## Test Coverage Map

| Área | Cobertura | Invariante protegida |
|---|---|---|
| Alcance de registro de suite | phpunit.xml, tests/Pest.php y testsuite Notification | PHPUnit y Pest descubren desde Tests y mantienen el TestCase compartido. |
| Lista canónica | app/Modules/Notification/Tests/Enums/NotificationTypeTest.php | Valores admitidos exactos y rechazo de tipos desconocidos. |
| Tipos de emisores | app/Modules/Notification/Tests/Notifications/CommentNotificationsTest.php y app/Modules/Notification/Tests/Listeners/LikeNotificationSubscriberTest.php | Tipos de base de datos/broadcast siguen estables tras centralizarlos. |
| Agrupación de likes | app/Modules/Notification/Tests/Services/LikeNotificationGroupServiceTest.php | Búsqueda y actualización del grupo por el tipo canonical, conservando el id y semántica de unlike. |
| Historial, recientes y acciones | app/Modules/Notification/Tests/Controllers/NotificationControllerTest.php | Coexistencia misma cuenta, paginación, propietario, 404 sin mutación y mark-all limitado. |
| Contador diferido | app/Modules/Notification/Tests/Props/SharedUnreadNotificationsTest.php y app/Modules/Notification/Tests/Props/NotificationPropsTest.php | Conteo filtrado, guest, usuario distinto y carga diferida. |
| Entrada Echo | TypeScript, ESLint y Prettier por archivo, más verificación manual de payload admitido/desconocido con dos sesiones. | Guard runtime previo a Zustand/toast y semántica de eliminación de likes. |

## Execution Summary

- Tareas: 9.
- Capas DAG: TASK-001 → TASK-002 → TASK-003 + TASK-004 + TASK-006 + TASK-007 + TASK-008 → TASK-005 → TASK-009.
- Ruta crítica (5 tareas): TASK-001 → TASK-002 → TASK-004 → TASK-005 → TASK-009.
- Resumen: Acotar el registro de pruebas → definir la enum canónica → alinear servicio de likes → cambiar el emisor de likes → sincronizar documentación.
- Las capas muestran dependencias reales; el proyecto exige ejecutar una tarea atómica y detenerse para revisión antes de avanzar.

## Task Dependencies

- **TASK-001** ← sin dependencias
- **TASK-002** ← TASK-001
- **TASK-003** ← TASK-002
- **TASK-004** ← TASK-002
- **TASK-005** ← TASK-004
- **TASK-006** ← TASK-002
- **TASK-007** ← TASK-002
- **TASK-008** ← TASK-002
- **TASK-009** ← TASK-003, TASK-005, TASK-006, TASK-007, TASK-008

