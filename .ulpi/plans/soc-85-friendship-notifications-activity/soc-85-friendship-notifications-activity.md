# Plan: SOC-85 — Friend request notifications and friendship activity

**Linear:** SOC-85 (épica SOC-82) · **Modo:** EXPANSION · **Revisión por defecto:** claude

## Overview

Hoy una solicitud de amistad solo se ve al recargar o navegar, y nada de lo que pasa con una amistad queda en el activity log. Este plan agrega:

* **Notificaciones en tiempo real:** "te envió una solicitud" al receptor y "aceptó tu solicitud" a quien la envió, en la campana, en el historial y como toast, en el idioma de quien la recibe.
* **Limpieza:** cuando una solicitud deja de estar pendiente (aceptada, rechazada o cancelada), su notificación sin leer se borra de la base y de la campana.
* **Refresco de la página abierta:** al llegar una notificación de amistad se recarga el panel de solicitudes con su contador y, según la página, el feed, la página de Amigos o el perfil.
* **Activity log:** un log `friendships` con los cinco eventos de amistad, su retención, su seeder y sus traducciones del panel.
* **Mejoras de experiencia (EXPANSION):** un icono por tipo de notificación y el enlace correcto para cada tipo en la campana y en el historial.

## Scope Challenge

**Qué existe hoy:**

- `FriendshipService` (SOC-83) despacha `FriendRequestSent` y `FriendRequestAccepted` con la fila, y `FriendshipEnded` con `friendshipId`, `actor`, `otherUser` y `reason` (`declined`, `canceled` o `removed`), todos **dentro** de su transacción.
- `NotificationType` tiene `comment_on_post`, `comment_reply` y `likes_grouped`; su espejo vive en `resources/js/modules/notification/types/notification.ts`.
- `CommentedOnPostNotification` usa los canales `database` y `broadcast` en cola; `User` implementa `HasLocalePreference`, así que el resumen sale en el idioma del destinatario.
- `LikeNotificationGroupService` ya borra un grupo sin leer y emite un broadcast `{deleted: true}` con `DB::afterCommit`; `NotificationRealtimeListener` solo atiende ese borrado para `likes_grouped`.
- `NotificationResource` expone `target: {postId, commentId}` y descarta cualquier otra clave; `NotificationListItem` y `useNotificationDropdown` solo saben enlazar a una publicación.
- La cola tiene `after_commit` en `false` y `.env.testing` usa `QUEUE_CONNECTION=sync`.
- El activity log tiene seis logs (`ActivityLogName`), un enum de eventos por dominio registrado en `ActivityLogEvent::ENUMS`, retención por log en `app/Modules/ActivityLog/config/activity-log.php` y una prueba que exige que cada evento lo registre algún listener, comando o servicio.
- `FriendRequestsPanel` (SOC-86) tiene su propia tabla de props a recargar por página.

**Qué se reutiliza:**

- El patrón de `CommentedOnPostNotification` y `CommentNotificationSubscriber` para las dos notificaciones nuevas.
- El borrado con broadcast `{deleted: true}` después del commit de `LikeNotificationGroupService`.
- `LikeActivitySubscriber` y `ActivityLogger::record()` para el suscriptor del activity log.
- La tabla de recargas por página de `FriendRequestsPanel`, que pasa a un helper compartido.
- `ActivityLogEventCatalog`: su `default` ya da un nombre en inglés a cualquier evento nuevo.

**Qué es nuevo:**

- Dos tipos de notificación (`friend_request_received` y `friend_request_accepted`), sus dos clases y su suscriptor.
- El log `friendships` con `ActivityLogFriendshipEvent` y `FriendshipActivitySubscriber`.
- Un helper de URL por tipo de notificación, un icono por tipo y el refresco de la página al llegar una notificación de amistad.

**Decisiones confirmadas:**

- Modo EXPANSION, revisión `claude` después de cada tarea.
- La notificación "te envió una solicitud" **sin leer** se borra cuando la solicitud deja de estar pendiente (aceptada, rechazada o cancelada), con un broadcast de borrado. Las ya leídas se quedan: su enlace lleva a la pestaña de recibidas, que nunca da 404.
- Al llegar una notificación de amistad se recarga siempre `friendRequests` (panel y contador), y además las props de la página abierta: el feed solo cuando la amistad quedó aceptada, la página de Amigos y el perfil siempre.

**¿Ejecución directa?** No: toca dos módulos de backend, el frontend de notificaciones y el seeder.

## Prerequisites

- SOC-83, SOC-84 y SOC-86 están mergeadas: eventos de dominio, `FriendRequestsPanel` y su recarga por página.
- Reverb corre en local (`composer run dev`) para la revisión a mano del tiempo real.

## Non-Goals

- Notificar cuando alguien rechaza o cancela una solicitud, o elimina una amistad: solo se registra en el activity log.
- Agrupar varias solicitudes en una notificación, como `likes_grouped`.
- Preferencias para silenciar tipos de notificación y correo electrónico.
- Mostrar amistades en Filament (SOC-87).
- Una prueba de navegador del tiempo real: las pruebas de navegador no levantan Reverb; el broadcast se prueba en PHP y el refresco se revisa a mano.

## Contracts

### Tipos de notificación

`NotificationType::FriendRequestReceived = 'friend_request_received'` y `NotificationType::FriendRequestAccepted = 'friend_request_accepted'`, en el mismo orden en el enum de PHP y en la tupla de TypeScript.

### Datos de la notificación

`{summary, actors: [{id, name}], target: {postId: null, commentId: null, friendshipId}}`. `actors[0]` es quien hizo la acción. `friendshipId` se guarda para encontrar la notificación al borrarla; `NotificationResource` no lo expone y el contrato público de `target` no cambia.

### Entrega

Las dos clases van en cola con `afterCommit()`, porque el evento se despacha dentro de la transacción del servicio. `shouldSend()` vuelve a leer la amistad: la recibida solo se envía si sigue pendiente y la aceptada solo si sigue aceptada; ninguna se envía a una cuenta suspendida ni a quien hizo la acción.

### Borrado de la solicitud

Con `FriendRequestAccepted` o con `FriendshipEnded` por `declined` o `canceled`, el suscriptor borra la notificación `friend_request_received` **sin leer** del receptor cuyo `data->target->friendshipId` coincide, dentro de la transacción del servicio, y emite un broadcast `{deleted: true}` con su id después del commit. Una notificación leída no se borra.

### Enlaces por tipo

`comment_on_post`, `comment_reply` y `likes_grouped`: la publicación como hoy. `friend_request_received`: `friendships.index?tab=received`. `friend_request_accepted`: `profile.show(actors[0].id)`.

### Activity log de amistades

`ActivityLogName::Friendships = 'friendships'`, retención `SOCIALHUB_ACTIVITY_LOG_FRIENDSHIPS_RETENTION_DAYS` (365 por defecto). `ActivityLogFriendshipEvent`: `friendship.request_sent`, `friendship.request_accepted`, `friendship.request_declined`, `friendship.request_canceled` y `friendship.removed`. El causante es quien actúa, sin `subject`, y las propiedades son solo `{friendship_id, target_user_id}`.

### Recarga de props por amistad

`reloadFriendshipDependentProps(component, friendshipState)` en `resources/js/modules/friendship/utils/` hace un solo `router.reload` con `friendRequests` y las props de la página según la tabla que hoy vive en `FriendRequestsPanel`.

## Existing Code Leverage

- `app/Modules/Notification/Notifications/CommentedOnPostNotification.php`: canales, tipo y `toArray`.
- `app/Modules/Notification/Listeners/CommentNotificationSubscriber.php`: suscriptor y reglas de destinatario.
- `app/Modules/Notification/Services/LikeNotificationGroupService.php`: borrado y broadcast después del commit.
- `app/Modules/ActivityLog/Listeners/LikeActivitySubscriber.php` y `app/Modules/ActivityLog/Services/ActivityLogger.php`.
- `app/Modules/ActivityLog/Database/Seeders/ActivityLogSeeder.php`: `queue()` para derivar filas de datos sembrados.
- `resources/js/modules/friendship/components/FriendRequestsPanel.tsx`: la tabla de recargas por página.

## Tasks

### TASK-001: Add the two friendship notification types in PHP and TypeScript

Agregar `FriendRequestReceived` y `FriendRequestAccepted` a `NotificationType` y a la tupla de `resources/js/modules/notification/types/notification.ts`, en el mismo orden, y actualizar `NotificationTypeTest`.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/Notification/Enums/NotificationType.php`
- `resources/js/modules/notification/types/notification.ts`
- `app/Modules/Notification/Tests/Enums/NotificationTypeTest.php`

**Acceptance Criteria:**

1. `NotificationType::values()` devuelve los cinco tipos en el orden del contrato.
2. `isNotificationType('friend_request_received')` es `true` y un tipo de Filament sigue siendo rechazado.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Notification/Tests/Enums/NotificationTypeTest.php && npm run types`

### TASK-002: Add the friendship notification texts in English and Spanish

En `lang/{en,es}/notification/center.php`:
* `summaries.friend_request_received` (":actor te envió una solicitud de amistad") y `summaries.friend_request_accepted` (":actor aceptó tu solicitud de amistad");
* `actions.open_friend_requests` y `actions.open_profile`;
* `types.<tipo>` con un nombre corto de cada uno de los cinco tipos, para el `aria-label` del icono.

`lang/*/notification/` no tiene prueba de paridad: se agrega `app/Modules/Notification/Tests/Translations/NotificationTranslationKeysTest.php`, igual que `FriendshipTranslationKeysTest`.

**Type:** feature  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/Notification/Tests/Translations/NotificationTranslationKeysTest.php`
- `lang/en/notification/center.php`
- `lang/es/notification/center.php`

**Acceptance Criteria:**

1. Los dos idiomas tienen las mismas claves y los mismos placeholders.
2. La prueba nueva falla si se borra una de las claves nuevas en `es`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Notification/Tests/Translations/NotificationTranslationKeysTest.php tests/Feature/Translation/FrontendTranslationKeysTest.php`

### TASK-003: Create the received and accepted friend request notifications

`app/Modules/Notification/Notifications/FriendRequestReceivedNotification.php` y `FriendRequestAcceptedNotification.php`, con el patrón de `CommentedOnPostNotification`:
* canales `database` y `broadcast`, en cola con `afterCommit()`;
* `toArray`: resumen en el idioma del destinatario, `actors` con quien actúa y `target` con `postId` y `commentId` en `null` más `friendshipId`;
* `shouldSend()` vuelve a leer la amistad: la recibida solo si sigue pendiente, la aceptada solo si sigue aceptada, y nunca a una cuenta suspendida.

La recibida ofrece además un constructor de borrado (`FriendRequestReceivedNotification::deletion(string $id)`) que solo usa `broadcast` y envía `{deleted: true}`.

**Prueba:** `app/Modules/Notification/Tests/Notifications/FriendshipNotificationsTest.php`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-001, TASK-002  
**Review:** claude

**writeScope:**

- `app/Modules/Notification/Notifications/FriendRequestReceivedNotification.php`
- `app/Modules/Notification/Notifications/FriendRequestAcceptedNotification.php`
- `app/Modules/Notification/Tests/Notifications/FriendshipNotificationsTest.php`

**Acceptance Criteria:**

1. Los datos guardados tienen el tipo, el resumen en el idioma del destinatario (en y es), `actors` y `target.friendshipId`; `NotificationResource` no expone `friendshipId`.
2. `shouldSend()` es `false` si la solicitud se canceló antes de entregarse, si la amistad ya no está aceptada o si el destinatario está suspendido.
3. El broadcast de borrado solo usa `broadcast` y su payload es `{deleted: true}`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Notification/Tests/Notifications/FriendshipNotificationsTest.php`

### TASK-004: Notify friend requests and remove the stale ones

`app/Modules/Notification/Listeners/FriendshipNotificationSubscriber.php`, registrado en `NotificationServiceProvider::boot()`:
* `FriendRequestSent`: notifica al receptor;
* `FriendRequestAccepted`: notifica a quien envió la solicitud y borra la notificación recibida sin leer del receptor;
* `FriendshipEnded` por `declined` o `canceled`: borra la notificación recibida sin leer del receptor. Por `removed` no hace nada.

El borrado busca por `data->target->friendshipId`, ocurre dentro de la transacción del servicio y emite el broadcast con `DB::afterCommit`.

**Prueba:** `app/Modules/Notification/Tests/Listeners/FriendshipNotificationSubscriberTest.php`, a través de `FriendshipService`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-003  
**Review:** claude

**writeScope:**

- `app/Modules/Notification/Listeners/FriendshipNotificationSubscriber.php`
- `app/Modules/Notification/Tests/Listeners/FriendshipNotificationSubscriberTest.php`
- `app/Modules/Notification/Providers/NotificationServiceProvider.php`

**Acceptance Criteria:**

1. Enviar una solicitud deja una notificación al receptor y ninguna a quien la envió; aceptarla deja una a quien la envió y borra la del receptor.
2. Cancelar o rechazar borra la notificación sin leer y emite su broadcast de borrado; una notificación ya leída se conserva y eliminar una amistad no borra nada.
3. Una acción rechazada por el servicio (repetida, en cooldown o a una cuenta suspendida) no crea ni borra notificaciones.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Notification/Tests/Listeners/FriendshipNotificationSubscriberTest.php`

### TASK-005: Add the friendships activity log and its event enum

`ActivityLogName::Friendships = 'friendships'`, su retención en `app/Modules/ActivityLog/config/activity-log.php` (`SOCIALHUB_ACTIVITY_LOG_FRIENDSHIPS_RETENTION_DAYS`, 365) y `app/Modules/ActivityLog/Enums/Events/ActivityLogFriendshipEvent.php` con los cinco eventos del contrato.

El enum todavía no se registra en `ActivityLogEvent::ENUMS`: lo hace TASK-006.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/ActivityLog/Enums/Events/ActivityLogFriendshipEvent.php`
- `app/Modules/ActivityLog/Enums/ActivityLogName.php`
- `app/Modules/ActivityLog/config/activity-log.php`

**Acceptance Criteria:**

1. `ActivityLogName::Friendships->retentionDays()` devuelve 365 sin variable de entorno.
2. Cada caso de `ActivityLogFriendshipEvent` devuelve el log `friendships` y un nombre `friendship.<acción>`.
3. `activity-log:purge` sigue pasando sus pruebas con el log nuevo.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/ActivityLog/Tests/Console/ActivityLogPurgeCommandTest.php && composer phpstan`

### TASK-006: Register the friendship events in the activity log catalog

Agregar `ActivityLogFriendshipEvent::class` a `ActivityLogEvent::ENUMS` y la variable `SOCIALHUB_ACTIVITY_LOG_FRIENDSHIPS_RETENTION_DAYS=365` a `.env.example`.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-005  
**Review:** claude

**writeScope:**

- `app/Modules/ActivityLog/Contracts/ActivityLogEvent.php`
- `.env.example`

**Acceptance Criteria:**

1. `ActivityLogEventTest` pasa: cada log tiene eventos y cada enum de `Enums/Events/` está registrado.
2. `ActivityLogEventCatalog::eventOptions()` incluye el grupo de amistades.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/ActivityLog/Tests/Contracts/ActivityLogEventTest.php app/Modules/ActivityLog/Tests/Support/ActivityLogEventCatalogTest.php`

### TASK-007: Translate the friendships log and its events for the panel

En `lang/{en,es}/admin/activity-log.php`: `logs.friendships` y `events.friendship.{request_sent, request_accepted, request_declined, request_canceled, removed}`.

**Type:** feature  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P2  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `lang/en/admin/activity-log.php`
- `lang/es/admin/activity-log.php`

**Acceptance Criteria:**

1. Los dos idiomas tienen las mismas claves.
2. La prueba de claves del panel falla si falta una de las claves nuevas en `es`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

### TASK-008: Record every friendship action in the activity log

`app/Modules/ActivityLog/Listeners/FriendshipActivitySubscriber.php`, registrado en `ActivityLogServiceProvider::boot()`:
* `FriendRequestSent`: `request_sent`, causante quien envía;
* `FriendRequestAccepted`: `request_accepted`, causante quien acepta;
* `FriendshipEnded`: `request_declined`, `request_canceled` o `removed` según `reason`, causante `actor`.

Propiedades `{friendship_id, target_user_id}` (la otra persona), sin `subject`.

**Prueba:** `app/Modules/ActivityLog/Tests/Listeners/FriendshipActivitySubscriberTest.php`, a través de `FriendshipService`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-006  
**Review:** claude

**writeScope:**

- `app/Modules/ActivityLog/Listeners/FriendshipActivitySubscriber.php`
- `app/Modules/ActivityLog/Tests/Listeners/FriendshipActivitySubscriberTest.php`
- `app/Modules/ActivityLog/Providers/ActivityLogServiceProvider.php`

**Acceptance Criteria:**

1. Cada una de las cinco acciones deja un registro con el causante, el evento y `target_user_id` correctos.
2. Una acción rechazada por el servicio no registra nada.
3. `ActivityLogPrivacyTest` pasa: cada evento del catálogo lo registra un listener y ningún registro guarda nombres ni emails.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/ActivityLog/Tests/Listeners/FriendshipActivitySubscriberTest.php app/Modules/ActivityLog/Tests/Security/ActivityLogPrivacyTest.php`

### TASK-009: Derive the friendship activity from the seeded friendships

`ActivityLogSeeder::seedFriendshipActivity()`: por cada amistad sembrada, `request_sent` del solicitante en `created_at` y, si está aceptada, `request_accepted` del receptor en `accepted_at`, con `{friendship_id, target_user_id}`. `FriendshipSeeder` ya corre antes en `DatabaseSeeder`.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-006  
**Review:** claude

**writeScope:**

- `app/Modules/ActivityLog/Database/Seeders/ActivityLogSeeder.php`
- `app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php`

**Acceptance Criteria:**

1. Hay un `request_sent` por amistad y un `request_accepted` por cada amistad aceptada, con sus fechas.
2. Sin amistades sembradas no se agrega ningún registro de `friendships`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php`

### TASK-010: Link each notification type to its page in the history

`resources/js/modules/notification/utils/notificationUrl.ts` devuelve la URL y la etiqueta de la acción de cada tipo, según el contrato de enlaces. `NotificationListItem` la usa en lugar de armar la URL de la publicación.

**Type:** feature  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-001, TASK-002  
**Review:** claude

**writeScope:**

- `resources/js/modules/notification/utils/notificationUrl.ts`
- `resources/js/modules/notification/components/NotificationListItem.tsx`

**Acceptance Criteria:**

1. Una notificación de comentario sigue enlazando a la misma URL de antes.
2. `friend_request_received` enlaza a `friendships.index?tab=received` y `friend_request_accepted` al perfil de `actors[0]`; sin actor no se muestra enlace.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-011: Open friendship notifications from the bell

`useNotificationDropdown` reemplaza `getNotificationPostUrl` por `getNotificationUrl`, que usa el helper de TASK-010, y `NotificationDropdown` lo llama con el nombre nuevo.

**Type:** feature  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-010  
**Review:** claude

**writeScope:**

- `resources/js/modules/notification/hooks/useNotificationDropdown.ts`
- `resources/js/modules/notification/components/NotificationDropdown.tsx`

**Acceptance Criteria:**

1. Abrir una notificación de amistad desde la campana la marca como leída y navega a su página.
2. Las notificaciones de comentarios y likes siguen abriendo la misma publicación.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-012: Share the friendship-dependent reloads

`resources/js/modules/friendship/utils/reloadFriendshipDependentProps.ts` con la tabla de recargas por página y un solo `router.reload`. `FriendRequestsPanel` lo usa en lugar de su propia tabla.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/utils/reloadFriendshipDependentProps.ts`
- `resources/js/modules/friendship/components/FriendRequestsPanel.tsx`

**Acceptance Criteria:**

1. Aceptar desde el panel en el inicio sigue mostrando las publicaciones del nuevo amigo (la prueba de navegador de SOC-86 sigue pasando).
2. Rechazar en el inicio no recarga `posts`; en una página fuera de la tabla solo se recarga `friendRequests`.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && composer test:browser`

### TASK-013: Refresh the open page when a friendship notification arrives

En `NotificationRealtimeListener`:
* un broadcast de borrado de `friend_request_received` quita la notificación del store, como `likes_grouped`;
* al recibir o borrar una notificación de amistad llama a `reloadFriendshipDependentProps` con el componente de la página y el estado (`friends` para la aceptada).

**Type:** feature  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-001, TASK-012  
**Review:** claude

**writeScope:**

- `resources/js/modules/notification/components/NotificationRealtimeListener.tsx`

**Acceptance Criteria:**

1. Con Reverb, recibir una solicitud actualiza el panel y el contador del menú sin recargar; en Amigos también la pestaña y los contadores.
2. Una notificación de comentario no provoca ninguna recarga de props.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-014: Show an icon for each notification type

`resources/js/modules/notification/components/NotificationTypeIcon.tsx` (comentario, respuesta, like, solicitud recibida, solicitud aceptada), con `aria-label` desde `notification/center.types.*`. Se muestra en `NotificationListItem` y en las filas de `NotificationDropdown`.

**Type:** feature  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P3  
**Depends on:** TASK-010, TASK-011  
**Review:** claude

**writeScope:**

- `resources/js/modules/notification/components/NotificationTypeIcon.tsx`
- `resources/js/modules/notification/components/NotificationListItem.tsx`
- `resources/js/modules/notification/components/NotificationDropdown.tsx`

**Acceptance Criteria:**

1. Cada uno de los cinco tipos muestra un icono distinto en el historial y en la campana.
2. El icono tiene nombre accesible en los dos idiomas y la fila no cambia de alto.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-015: Add a browser test for the friend request notification

`app/Modules/Notification/Tests/Browser/FriendshipNotificationsBrowserTest.php`:
* después de recibir una solicitud, el historial muestra su resumen y su enlace lleva a la pestaña de recibidas;
* después de que la persona cancela la solicitud, la notificación ya no aparece;
* `assertNoJavaScriptErrors()`.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-004, TASK-010, TASK-014  
**Review:** claude

**writeScope:**

- `app/Modules/Notification/Tests/Browser/FriendshipNotificationsBrowserTest.php`

**Acceptance Criteria:**

1. La prueba pasa con `composer test:browser`.
2. Falla si el suscriptor deja de borrar la notificación al cancelar.

**validateCommand:** `composer test:browser`

### TASK-016: Document the friendship notifications and the shared reloads

`docs/architecture/modules/notification.md`: los tipos nuevos, `afterCommit`, `shouldSend`, el borrado de la solicitud, los enlaces y el icono por tipo.

`docs/architecture/modules/friendship.md`: el helper de recargas compartido por el panel y el listener.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Depends on:** TASK-004, TASK-011, TASK-013  
**Review:** claude

**writeScope:**

- `docs/architecture/modules/notification.md`
- `docs/architecture/modules/friendship.md`

**Acceptance Criteria:**

1. Cada clase, archivo y tipo que se nombra existe (`grep`).
2. `notification.md` ya no dice que el borrado en tiempo real solo existe para `likes_grouped`.

**validateCommand:** `grep -n 'friend_request_received' docs/architecture/modules/notification.md`

### TASK-017: Document the friendships activity log

`docs/architecture/modules/activity-log.md`: el log `friendships`, sus eventos, sus propiedades y su seeder. `docs/development/environment.md`: la variable de retención nueva. `CLAUDE.md`: el mapa de módulos de Notification y ActivityLog.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Depends on:** TASK-008, TASK-009  
**Review:** claude

**writeScope:**

- `docs/architecture/modules/activity-log.md`
- `docs/development/environment.md`
- `CLAUDE.md`

**Acceptance Criteria:**

1. La retención documentada coincide con `config/activity-log.php` y `.env.example`.
2. Cada clase y evento que se nombra existe (`grep`).

**validateCommand:** `grep -n 'SOCIALHUB_ACTIVITY_LOG_FRIENDSHIPS_RETENTION_DAYS' docs/development/environment.md docs/architecture/modules/activity-log.md`

### TASK-018: Run the full gates

1. Pint, PHPStan y Rector (`rector-clear-cache` → `rector-dry` → `rector`);
2. `composer test:recreate`, `composer test` y `composer test:browser`;
3. `composer doctor`;
4. `npm run format:check`, `lint:check`, `types`, `build` y `build:ssr`;
5. a mano con `composer run dev` y dos sesiones: enviar, aceptar, rechazar y cancelar, y comprobar la campana, el toast, el panel, la página de Amigos y el activity log del panel.

**Type:** chore  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P3  
**Depends on:** TASK-007, TASK-015, TASK-016, TASK-017  
**Review:** claude

**writeScope:**

- (sin archivos: verificación)

**Acceptance Criteria:**

1. Pint, PHPStan y Rector no reportan cambios ni errores, y las tres suites pasan.
2. En la revisión a mano, una solicitud cancelada desaparece de la campana del receptor sin recargar y la consola no tiene errores.

**validateCommand:** `composer phpstan && composer rector-dry && composer test && composer test:browser && npm run build && npm run build:ssr`

## Failure Modes

- La notificación se encola antes del commit y llega aunque la transacción se revierta: `afterCommit()` en las dos clases (TASK-003).
- La solicitud se cancela entre el commit y la entrega de la cola: `shouldSend()` vuelve a leer la amistad (TASK-003).
- Borrar una notificación ya leída descontaría de más el contador del cliente: solo se borran las sin leer (TASK-004).
- Un evento del catálogo sin listener rompe `ActivityLogPrivacyTest`: TASK-008 registra los cinco.
- `ActivityLogEventTest` exige que cada log tenga eventos registrados: queda rojo entre TASK-005 y TASK-006, por eso TASK-005 no lo usa como validación.
- Recargar `posts` del feed al rechazar perdería el scroll: el helper solo lo hace con `friends` (TASK-012).

## Ship Cut

El mínimo entregable es TASK-001 a TASK-004, TASK-010, TASK-011 y TASK-013: las notificaciones con su borrado, sus enlaces y el refresco. El activity log (TASK-005 a TASK-009, TASK-017) se recorta como bloque. TASK-012 es necesaria para TASK-013. TASK-014 y TASK-015 son recortables; los gates no.

## Test Coverage Map

| Comportamiento | Tarea |
| --- | --- |
| Tipos de notificación en PHP y TypeScript | TASK-001 |
| Paridad de lang/*/notification (prueba nueva) | TASK-002 |
| Datos, idioma, shouldSend y borrado de las notificaciones | TASK-003 |
| Notificar y borrar desde los eventos de amistad | TASK-004 |
| Retención del log friendships | TASK-005 |
| Catálogo del activity log | TASK-006 |
| Traducciones del panel | TASK-007 |
| Registro de las cinco acciones y privacidad | TASK-008 |
| Seeder del activity log | TASK-009 |
| Panel de solicitudes con el helper compartido | TASK-012 |
| Historial de notificaciones en el navegador | TASK-015 |

## Execution Summary

- **Tareas:** 18
- **Capa 1:** TASK-001, TASK-002, TASK-005, TASK-007, TASK-012
- **Capa 2:** TASK-003, TASK-006, TASK-010, TASK-013
- **Capa 3:** TASK-004, TASK-008, TASK-009, TASK-011
- **Capa 4:** TASK-014, TASK-016, TASK-017
- **Capa 5:** TASK-015
- **Capa 6:** TASK-018
- **Ruta crítica:** TASK-001 → TASK-010 → TASK-011 → TASK-014 → TASK-015 → TASK-018

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | TASK-001, TASK-002 |
| TASK-004 | TASK-003 |
| TASK-005 | — |
| TASK-006 | TASK-005 |
| TASK-007 | — |
| TASK-008 | TASK-006 |
| TASK-009 | TASK-006 |
| TASK-010 | TASK-001, TASK-002 |
| TASK-011 | TASK-010 |
| TASK-012 | — |
| TASK-013 | TASK-001, TASK-012 |
| TASK-014 | TASK-010, TASK-011 |
| TASK-015 | TASK-004, TASK-010, TASK-014 |
| TASK-016 | TASK-004, TASK-011, TASK-013 |
| TASK-017 | TASK-008, TASK-009 |
| TASK-018 | TASK-007, TASK-015, TASK-016, TASK-017 |
