# Plan: SOC-68: Centro de notificaciones en tiempo real para React

## Overview

**Issue:** [SOC-68](https://linear.app/social-hub-ang/issue/SOC-68/feature-add-a-real-time-notification-center-to-the-react-app)  
**Mode:** EXPANSION · **Default review:** codex  
**Expansion:** SOC-68 completo más notificaciones de likes agrupados, confirmado por el usuario.

Construir un centro de notificaciones de React para comentarios en publicaciones ajenas, respuestas a comentarios y likes agrupados. Laravel persistirá el estado en la tabla `notifications` existente y transmitirá los cambios por Reverb a un canal privado propio de cada usuario. La campana, el historial, el conteo de no leídas y las acciones de lectura se integrarán con los módulos existentes. El plan añade una ruta de publicación porque el proyecto no tiene actualmente una página a la que pueda llevar un aviso.

## Scope Challenge

- Se reutilizan Laravel Reverb, Laravel Echo, `@laravel/echo-react`, Sonner, el trait `Notifiable`, la tabla `notifications`, `CommentCreated` y `LikeToggled`.
- La configuración existente no está completa para desarrollo: `.env.example` selecciona `BROADCAST_CONNECTION=log` y el comando `composer run dev` registra `serve`, `queue:listen`, Pail y Vite, pero todavía no inicia Reverb.
- El canal actual usa `App.Models.User.{id}`. El modelo real está en `App\User\Models\User`; se definirá un nombre estable, explícito y autorizado por usuario.
- La tabla `notifications` ya persiste avisos de Filament. El plan reutiliza esa tabla y mantiene intactos el panel y sus notificaciones.
- El icono de `HomeNotificationHeader` es todavía un marcador; Sonner ya está montado por `FlashToaster`.
- La decisión confirmada para respuestas es: comentario raíz → dueño de la publicación; respuesta a comentario raíz → autor del comentario; respuesta a respuesta → `replied_to_user_id`. Se ignoran acciones propias y se evita notificar dos veces al mismo usuario.
- La ampliación de likes usa un grupo no leído por destinatario y contenido (publicación o comentario). El grupo conserva actores únicos; un unlike retira al actor de un grupo no leído y, si queda vacío, lo elimina. Un grupo ya leído queda como historial y un like posterior crea otro grupo no leído.
- SOC-68 actualmente enumera los likes agrupados como fuera de alcance. Antes de ejecutar el plan, hay que sincronizar esa descripción con la ampliación que el usuario confirmó.

## Prerequisites

- SOC-41 está completada y `CommentCreated` ya incluye el comentario objetivo; `Comment` conserva `replied_to_user_id` para respuestas a respuestas.
- `LikeToggled` se dispara al agregar y retirar likes de publicaciones y comentarios, y ya distingue el estado resultante.
- La tabla `notifications` y `User::notifications()` ya existen; no se añade una tabla paralela ni una dependencia.
- El entorno local usa los valores Reverb existentes de `.env.example`; `composer run dev` debe quedar como punto de arranque de servidor, cola, Vite y Reverb.
- Antes de implementación, actualizar el texto de SOC-68 para incluir likes agrupados y conservar como fuera de alcance los avisos de seguridad, anuncios del sistema, presencia, email y push.

## Non-Goals

- Avisos de seguridad, preferencias para silenciar tipos, anuncios del sistema en tiempo real y cambios a las notificaciones de Filament.
- Presencia, usuarios en línea, eventos de escritura, contadores sociales en vivo fuera del centro de notificaciones, correo y push del navegador.
- Migrar o reemplazar la tabla `notifications`, añadir dependencias o cambiar el funcionamiento del panel Admin.
- Guardar el contenido completo de comentarios o datos sensibles dentro del payload de una notificación.
- Cambiar la política de likes o permitir respuestas con más de un nivel.

## Contracts

- Canal: `users.{id}.notifications`, privado y autorizado solo si la sesión pertenece a ese usuario y su cuenta no está suspendida. `User::receivesBroadcastNotificationsOn()` fija el nombre; `routes/channels.php` valida propietario y estado.
- Idioma: `User` implementa `HasLocalePreference` y devuelve `preferred_locale->value`. Los resúmenes guardados se generan en el idioma del destinatario; los textos de React se leen con `useTranslation()` desde `notification/center` en inglés y español.
- Persistencia y entrega: notificaciones Laravel usan `database` y `broadcast`, son encoladas y conservan un payload normalizado con tipo, resumen localizado, snapshot mínimo de actores, ids de destino, `readAt` y fecha. La base de datos es la fuente de verdad; Echo solo mantiene la pantalla al día.
- Comentarios: comentario raíz en publicación ajena avisa al dueño de la publicación; respuesta a raíz avisa al autor de raíz; respuesta a respuesta avisa a `replied_to_user_id`. El autor nunca se notifica a sí mismo.
- Likes: una fila de notificación no leída por destinatario y destino. La clave lógica es `(notifiable, likeable_type, likeable_id)`; la escritura se serializa para que likes simultáneos no dupliquen el grupo. Actualizaciones de grupo conservan el id de notificación para que React lo reemplace.
- Payload público: `NotificationResource` expone solo `id`, `type`, `summary`, `actors`, `target`, `readAt` y `createdAt`. Los snapshots permiten mostrar un aviso aunque el comentario o la publicación se borren; serializar una notificación no carga esos modelos.
- Endpoints: `GET /notifications` entrega historial con cursor, `GET /notifications/recent` entrega las cinco más recientes, `POST /notifications/{notificationId}/read` marca una propia y `POST /notifications/read-all` marca todas las propias. Las escrituras llevan throttle; el acceso ajeno responde 404.
- Inertia: `unreadNotificationsCount` es una prop compartida diferida. Home registra `notification/center`; la página de notificaciones registra el mismo grupo. El historial usa `Inertia::scroll()` y `InfiniteScroll` con orden estable por `created_at` e `id`.
- Deep link: `GET /posts/{post}` renderiza una página de publicación con su hilo de comentarios. Los avisos apuntan a esa página; si el destino se eliminó, el resumen guardado sigue visible y el destino puede responder 404.
- Reverb: `composer run dev` debe iniciar Reverb además de los procesos que Laravel ya registra; `queue:listen` existente procesa las notificaciones. La configuración Echo actual se reutiliza si supera la comprobación de autenticación de sesión y `npm run build:ssr`.

## Existing Code Leverage

- `app/Modules/Comment/Events/CommentCreated.php` y `app/Modules/Like/Events/LikeToggled.php` son los puntos de entrada de los suscriptores del módulo Notification.
- `app/Modules/ActivityLog/Providers/ActivityLogServiceProvider.php`, `ContentActivitySubscriber.php` y `LikeActivitySubscriber.php` muestran cómo registrar suscriptores de eventos de dominio.
- `app/User/Models/User.php`, `routes/channels.php` y `config/broadcasting.php` son los puntos reales de locale, autorización y broadcasting.
- `database/migrations/2026_10_30_193919_create_notifications_table.php` y el trait `Notifiable` proveen persistencia e historial.
- `app/Modules/Comment/Props/CommentThreadProps.php`, `PostResource`, `CommentList` y `HomeController` sirven de patrón para cursor, recursos, hilos y props Inertia.
- `resources/js/modules/home/components/header/HomeNotificationHeader.tsx` es el marcador existente; `HomeLayout` y `app.tsx` son los puntos de integración visual y Echo.
- `resources/js/shared/components/FlashToaster.tsx` ya monta Sonner; el listener de tiempo real debe usar el mismo toaster.
- `resources/js/shared/wayfinder/` se genera desde `vite.config.ts`; las rutas nuevas se importan desde el output generado y nunca se editan a mano.
- `package.json` ya contiene `@laravel/echo-react`, `laravel-echo`, `pusher-js`, Inertia v3, Zustand y Sonner; no hace falta instalar paquetes.

## Tasks

### TASK-001: Activar Reverb en el arranque local

Cambiar el default de `.env.example` a Reverb y registrar `reverb:start` en los procesos de desarrollo mediante `Illuminate\Foundation\DevCommands`. Laravel ya agrega `serve`, `queue:listen`, Pail y Vite al comando `dev`; no registrar una segunda cola. Añadir una prueba pequeña del roster para que `composer run dev` siga levantando Reverb y el worker.

**Type:** infra
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P0
**Review:** codex
**Depends on:** —
**Write scope:**
- `.env.example`
- `app/Providers/AppServiceProvider.php`
- `tests/Feature/Console/DevProcessConfigurationTest.php`
**Validate:**
```bash
rtk php artisan test --compact tests/Feature/Console/DevProcessConfigurationTest.php
```
**Acceptance Criteria:**
- [ ] `.env.example` selecciona `BROADCAST_CONNECTION=reverb` y mantiene las credenciales locales de Reverb.
- [ ] La prueba confirma que el roster de `php artisan dev` contiene Reverb, Vite y el `queue:listen` ya registrado por Laravel.
- [ ] Al arrancar con los valores del ejemplo, las notificaciones no quedan en el broadcaster `log`.

### TASK-002: Definir el canal privado y locale del usuario

Implementar `HasLocalePreference` en `User`, devolver el valor de `preferred_locale` y reemplazar el canal de plantilla por `users.{id}.notifications`. Autorizar únicamente al usuario activo dueño del canal y cubrir la ruta real de autorización de broadcasting.

**Type:** feature
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P0
**Review:** codex
**Depends on:** —
**Write scope:**
- `app/User/Models/User.php`
- `routes/channels.php`
- `app/User/Tests/Notifications/UserNotificationChannelTest.php`
**Validate:**
```bash
rtk php artisan test --compact app/User/Tests/Notifications/UserNotificationChannelTest.php
```
**Acceptance Criteria:**
- [ ] Una sesión activa puede autorizar su canal `users.{id}.notifications`; un usuario distinto y un invitado reciben 403.
- [ ] Una cuenta suspendida recibe 403 y no conserva acceso al canal.
- [ ] `preferredLocale()` devuelve `en` o `es` según el enum persistido del usuario.

### TASK-003: Crear la base del módulo Notification

Crear `NotificationServiceProvider`, `NotificationRouteServiceProvider` y `routes/routes.php` siguiendo la estructura de Comment y Like. El provider del módulo registra el route provider; el archivo de rutas existe desde esta tarea y recibe las rutas funcionales en la tarea de endpoints.

**Type:** infra
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P0
**Review:** codex
**Depends on:** —
**Write scope:**
- `app/Modules/Notification/Providers/NotificationServiceProvider.php`
- `app/Modules/Notification/Providers/NotificationRouteServiceProvider.php`
- `app/Modules/Notification/routes/routes.php`
**Validate:**
```bash
php -l app/Modules/Notification/Providers/NotificationServiceProvider.php
php -l app/Modules/Notification/Providers/NotificationRouteServiceProvider.php
php -l app/Modules/Notification/routes/routes.php
```
**Acceptance Criteria:**
- [ ] Los dos providers siguen el patrón registrado por los módulos Post, Comment y Like y el route provider usa el middleware `web`.
- [ ] El route provider carga el archivo real `app/Modules/Notification/routes/routes.php`.
- [ ] Los archivos nuevos pasan `php -l` sin depender de rutas duplicadas o clases ficticias.

### TASK-004: Registrar el módulo y su suite Pest

Registrar `NotificationServiceProvider` en `bootstrap/providers.php` y sumar la suite `Notification` a PHPUnit y Pest. Esto deja asignado el propietario del wiring del módulo y hace que los tests co-localizados se ejecuten con el TestCase y `RefreshDatabase` del proyecto.

**Type:** infra
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P0
**Review:** codex
**Depends on:** TASK-003
**Write scope:**
- `bootstrap/providers.php`
- `phpunit.xml`
- `tests/Pest.php`
**Validate:**
```bash
rtk php artisan route:list --except-vendor
```
**Acceptance Criteria:**
- [ ] Laravel carga el módulo Notification y sus rutas sin errores.
- [ ] PHPUnit y Pest reconocen `app/Modules/Notification/Tests/` como una suite del módulo.
- [ ] La aplicación continúa registrando las rutas existentes de Admin, Comment y Like.

### TASK-005: Añadir los catálogos de notificaciones

Crear el grupo `notification/center` en inglés y español para resúmenes de comentarios, respuestas y likes agrupados, además del centro, estados vacíos, acciones, contador, accesibilidad y fallback de destino eliminado. Usar placeholders y plurales donde corresponda.

**Type:** feature
**Effort:** S
**Agent:** general-purpose
**Priority:** P1
**Review:** codex
**Depends on:** —
**Write scope:**
- `lang/en/notification/center.php`
- `lang/es/notification/center.php`
**Validate:**
```bash
php -l lang/en/notification/center.php
php -l lang/es/notification/center.php
```
**Acceptance Criteria:**
- [ ] Los catálogos `en` y `es` contienen las mismas claves y placeholders.
- [ ] Los resúmenes permiten generar el idioma del destinatario y los textos de interfaz permiten `t()`/`tChoice()`.
- [ ] El copy contempla una notificación sin destino disponible y un centro sin notificaciones.

### TASK-006: Crear notificaciones Laravel para comentarios

Crear las clases `CommentedOnPostNotification` y `RepliedToCommentNotification` con canales `database` y `broadcast`, `ShouldQueue`, tipos de broadcast estables y payload con resumen localizado, snapshot del actor y los ids del post/comentario. El payload no almacena el texto íntegro del comentario ni datos privados.

**Type:** feature
**Effort:** M
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-002, TASK-004, TASK-005
**Write scope:**
- `app/Modules/Notification/Notifications/CommentedOnPostNotification.php`
- `app/Modules/Notification/Notifications/RepliedToCommentNotification.php`
- `app/Modules/Notification/Tests/Notifications/CommentNotificationsTest.php`
**Validate:**
```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Notifications/CommentNotificationsTest.php
```
**Acceptance Criteria:**
- [ ] `Notification::fake()` confirma ambos canales para cada clase y el `broadcastType` identifica el tipo de aviso.
- [ ] Los payloads guardan id y nombre mínimo del actor, resumen y destino; no incluyen contenido completo, email ni otros datos del usuario.
- [ ] Los resúmenes se producen en el locale preferido del destinatario y siguen serializándose aunque el comentario se elimine.

### TASK-007: Enviar avisos para comentarios y respuestas

Crear `CommentNotificationSubscriber` y registrarlo desde `NotificationServiceProvider`. Resolver el destinatario según el tipo de acción: dueño de la publicación para comentario raíz, autor del comentario raíz para una respuesta normal y `replied_to_user_id` para respuesta a respuesta.

**Type:** feature
**Effort:** M
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-006
**Write scope:**
- `app/Modules/Notification/Listeners/CommentNotificationSubscriber.php`
- `app/Modules/Notification/Providers/NotificationServiceProvider.php`
- `app/Modules/Notification/Tests/Listeners/CommentNotificationSubscriberTest.php`
**Validate:**
```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Listeners/CommentNotificationSubscriberTest.php
```
**Acceptance Criteria:**
- [ ] Un comentario raíz en publicación ajena avisa al dueño de la publicación; una respuesta normal avisa al autor del comentario respondido.
- [ ] Una respuesta a respuesta avisa al usuario guardado en `replied_to_user_id`, nunca toma ese destinatario de datos del cliente.
- [ ] Las acciones propias y los casos sin destinatario distinto no producen notificaciones duplicadas.

### TASK-008: Persistir grupos de likes no leídos

Crear `LikeNotification` y `LikeNotificationGroupService`. El servicio mantiene un agregado Laravel en `notifications` por destinatario y destino mientras sigue sin leer, actualiza actores y resumen en una transacción y publica la versión actualizada después del commit con el mismo id de grupo.

**Type:** feature
**Effort:** L
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-002, TASK-004, TASK-005
**Write scope:**
- `app/Modules/Notification/Notifications/LikeNotification.php`
- `app/Modules/Notification/Services/LikeNotificationGroupService.php`
- `app/Modules/Notification/Tests/Services/LikeNotificationGroupServiceTest.php`
**Validate:**
```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Services/LikeNotificationGroupServiceTest.php
```
**Acceptance Criteria:**
- [ ] Likes de usuarios distintos a la misma publicación o comentario actualizan un único grupo no leído con actores únicos y contador correcto.
- [ ] Un unlike retira al actor del grupo no leído y elimina el grupo cuando queda vacío; un grupo ya leído conserva su historial.
- [ ] Un like posterior a un grupo leído crea un grupo no leído nuevo y los likes simultáneos no generan dos grupos para el mismo destino.

### TASK-009: Conectar likes con el agregado de notificaciones

Crear `LikeNotificationSubscriber` para consumir `LikeToggled` y registrarlo en `NotificationServiceProvider`. El destinatario es el dueño del post o comentario likeado; el subscriber pasa tanto altas como bajas al servicio de grupos.

**Type:** feature
**Effort:** M
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-007, TASK-008
**Write scope:**
- `app/Modules/Notification/Listeners/LikeNotificationSubscriber.php`
- `app/Modules/Notification/Providers/NotificationServiceProvider.php`
- `app/Modules/Notification/Tests/Listeners/LikeNotificationSubscriberTest.php`
**Validate:**
```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Listeners/LikeNotificationSubscriberTest.php
```
**Acceptance Criteria:**
- [ ] Un like a una publicación o comentario ajeno actualiza el grupo del dueño correcto.
- [ ] Un like propio no genera un aviso y un unlike actualiza solo el grupo del destino correspondiente.
- [ ] Una excepción al guardar el agregado no deja un evento parcial ni cambia el like de otro usuario.

### TASK-010: Serializar notificaciones con un contrato seguro

Crear `NotificationResource` que convierta `DatabaseNotification` al contrato de la interfaz mediante una lista permitida de campos. La serialización no consulta post, comentario ni usuario; los snapshots guardados permiten representar destinos eliminados sin N+1 ni excepciones.

**Type:** feature
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-006, TASK-008
**Write scope:**
- `app/Modules/Notification/Resources/NotificationResource.php`
- `app/Modules/Notification/Tests/Resources/NotificationResourceTest.php`
**Validate:**
```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Resources/NotificationResourceTest.php
```
**Acceptance Criteria:**
- [ ] El recurso devuelve `id`, tipo, resumen, actores mínimos, destino, `readAt` y `createdAt`.
- [ ] No serializa emails, contenido completo ni atributos de Eloquent fuera del contrato.
- [ ] Una notificación con destino eliminado se serializa desde su snapshot sin consultar relaciones inexistentes.

### TASK-011: Calcular el conteo diferido de no leídas

Crear `NotificationProps` como propietario del conteo no leído y exponerlo con `Inertia::defer()`. Como las agrupaciones de likes ocupan una fila, el conteo es el número de filas de notificación no leídas del usuario, no el número de actores del grupo.

**Type:** feature
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-010
**Write scope:**
- `app/Modules/Notification/Props/NotificationProps.php`
- `app/Modules/Notification/Tests/Props/NotificationPropsTest.php`
**Validate:**
```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Props/NotificationPropsTest.php
```
**Acceptance Criteria:**
- [ ] El conteo diferido se obtiene solo de `unreadNotifications()` del usuario autenticado y no incluye otros usuarios.
- [ ] Un usuario sin avisos recibe cero; marcar un aviso leído baja el conteo de manera consistente.
- [ ] La prop queda marcada como diferida y no añade una consulta de conteo al render inicial.

### TASK-012: Compartir el conteo con Home y React

Añadir las props de `NotificationProps` a `HandleInertiaRequests` y registrar el grupo `notification/center` desde `HomeController`, donde se monta la campana. Añadir una prueba de integración para invitado, usuario sin avisos y usuario con avisos.

**Type:** feature
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-005, TASK-011
**Write scope:**
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Home/Controllers/HomeController.php`
- `app/Modules/Notification/Tests/Props/SharedUnreadNotificationsTest.php`
**Validate:**
```bash
rtk php artisan test --compact app/Modules/Notification/Tests/Props/SharedUnreadNotificationsTest.php
```
**Acceptance Criteria:**
- [ ] Las páginas Inertia reciben `unreadNotificationsCount` diferido y el catálogo de `notification/center` requerido por Home.
- [ ] El conteo es propio del usuario actual; un invitado no dispara una consulta de usuario ni recibe datos ajenos.
- [ ] Las props existentes de auth, anuncios, locale y búsquedas siguen presentes.

### TASK-013: Crear el historial, recientes y acciones de lectura

Implementar `NotificationController` y sus rutas autenticadas: historial Inertia cursor-paginado, recientes JSON para la campana, marcar una notificación y marcar todas. Ordenar con `created_at` e `id`, limitar lecturas y escrituras al vínculo del usuario autenticado y aplicar throttle a las escrituras.

**Type:** feature
**Effort:** M
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-004, TASK-010, TASK-011
**Write scope:**
- `app/Modules/Notification/Controllers/NotificationController.php`
- `app/Modules/Notification/routes/routes.php`
- `app/Modules/Notification/Tests/Controllers/NotificationControllerTest.php`
**Validate:**
```bash
rtk php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder --no-interaction
rtk php artisan test --compact app/Modules/Notification/Tests/Controllers/NotificationControllerTest.php
```
**Acceptance Criteria:**
- [ ] `GET /notifications` devuelve el historial con cursor estable y `GET /notifications/recent` devuelve como máximo las cinco más recientes serializadas por `NotificationResource`.
- [ ] Marcar una y marcar todas actualiza solo notificaciones propias; un id ajeno o inexistente responde 404.
- [ ] Un invitado no puede leer ni modificar la bandeja y los endpoints de escritura tienen throttle.

### TASK-014: Dar a las notificaciones una ruta de destino para publicaciones

Añadir `GET /posts/{post}` a `PostController` y al route file existente. La acción autenticada carga el `PostResource` y `CommentThreadProps` para renderizar una publicación con su hilo; esta ruta es el destino estable de avisos de comentarios, respuestas y likes.

**Type:** feature
**Effort:** M
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** codex
**Depends on:** —
**Write scope:**
- `app/Modules/Post/Controllers/PostController.php`
- `app/Modules/Post/routes/routes.php`
- `app/Modules/Post/Tests/Crud/PostShowTest.php`
**Validate:**
```bash
rtk php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder --no-interaction
rtk php artisan test --compact app/Modules/Post/Tests/Crud/PostShowTest.php
```
**Acceptance Criteria:**
- [ ] Un usuario autenticado abre una publicación existente en el componente `post/Show` con su recurso y comentarios disponibles.
- [ ] Una publicación inexistente o eliminada responde 404 y un invitado sigue el flujo de autenticación existente.
- [ ] La ruta queda nombrada como `posts.show` y Wayfinder genera un helper con la URI correcta.

### TASK-015: Tipar el contrato compartido de notificaciones

Definir en TypeScript la forma serializada de avisos de comentarios y grupos de likes, junto con el tipo de `unreadNotificationsCount` en `SharedData`. El contrato mantiene los mismos nombres y nulabilidad del `NotificationResource`.

**Type:** feature
**Effort:** S
**Agent:** react-vite-tailwind-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-010, TASK-012, TASK-013
**Write scope:**
- `resources/js/shared/types/index.d.ts`
- `resources/js/modules/notification/types/notification.ts`
**Validate:**
```bash
rtk npm run types
rtk npx eslint resources/js/shared/types/index.d.ts resources/js/modules/notification/types/notification.ts
rtk npx prettier --check resources/js/shared/types/index.d.ts resources/js/modules/notification/types/notification.ts
```
**Acceptance Criteria:**
- [ ] El tipo cubre `comment_on_post`, `comment_reply` y `likes_grouped`, incluyendo snapshots de actores y destino post/comment.
- [ ] `SharedData` tipa el conteo compartido y la carga diferida sin `any`.
- [ ] El chequeo de TypeScript resuelve los imports sin introducir tipos duplicados del mismo payload.

### TASK-016: Mostrar una publicación desde su deep link

Crear `resources/js/modules/post/Show.tsx` como página Inertia que usa `HomeLayout` y el `PostCard` existente para presentar la publicación y abrir su hilo de comentarios. No duplica la tarjeta ni inventa una segunda UI de likes.

**Type:** feature
**Effort:** S
**Agent:** react-vite-tailwind-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-014
**Write scope:**
- `resources/js/modules/post/Show.tsx`
**Validate:**
```bash
rtk npm run types
rtk npx eslint resources/js/modules/post/Show.tsx
rtk npx prettier --check resources/js/modules/post/Show.tsx
rtk npm run build:ssr
```
**Acceptance Criteria:**
- [ ] El componente `post/Show` muestra el Post recibido por Inertia y permite acceder al diálogo de comentarios.
- [ ] El deep link funciona con una publicación existente; una ruta 404 no deja una tarjeta fantasma.
- [ ] La página compila con React 19, Inertia v3 y el layout existente.

### TASK-017: Construir la página de historial

Crear la página Inertia `notification/Notifications`, su item reutilizable y el barrel del módulo. La lista usa `InfiniteScroll` con la prop `notifications` de `Inertia::scroll()`, muestra estados vacíos/carga y permite marcar avisos individuales o todos como leídos.

**Type:** feature
**Effort:** M
**Agent:** react-vite-tailwind-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-005, TASK-013, TASK-015, TASK-016
**Write scope:**
- `resources/js/modules/notification/Notifications.tsx`
- `resources/js/modules/notification/components/NotificationListItem.tsx`
- `resources/js/modules/notification/index.ts`
**Validate:**
```bash
rtk npm run types
rtk npx eslint resources/js/modules/notification/Notifications.tsx resources/js/modules/notification/components/NotificationListItem.tsx resources/js/modules/notification/index.ts
rtk npx prettier --check resources/js/modules/notification/Notifications.tsx resources/js/modules/notification/components/NotificationListItem.tsx resources/js/modules/notification/index.ts
```
**Acceptance Criteria:**
- [ ] El historial consume cursores sin duplicar filas y conserva el orden estable del servidor.
- [ ] Marcar una o todas actualiza estado visual y conteo; errores de servidor conservan la lista actual.
- [ ] Copy, plurales, acciones accesibles y fechas usan `useTranslation()` y `useDateTime()`.

### TASK-018: Conectar Echo con el estado React del centro

Crear el store de notificaciones y un listener con `useEchoNotification`, y montarlo una sola vez en `withApp`. El listener procesa el canal del usuario actual, deduplica por id, reemplaza el payload del grupo de likes cuando mantiene el mismo id y usa Sonner para avisar.

**Type:** feature
**Effort:** M
**Agent:** react-vite-tailwind-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-001, TASK-002, TASK-007, TASK-009, TASK-015
**Write scope:**
- `resources/js/app/app.tsx`
- `resources/js/modules/notification/stores/notificationStore.ts`
- `resources/js/modules/notification/components/NotificationRealtimeListener.tsx`
**Validate:**
```bash
rtk npm run types
rtk npx eslint resources/js/app/app.tsx resources/js/modules/notification/stores/notificationStore.ts resources/js/modules/notification/components/NotificationRealtimeListener.tsx
rtk npx prettier --check resources/js/app/app.tsx resources/js/modules/notification/stores/notificationStore.ts resources/js/modules/notification/components/NotificationRealtimeListener.tsx
rtk npm run build:ssr
```
**Acceptance Criteria:**
- [ ] Una notificación nueva actualiza lista/conteo y muestra un toast sin recargar; una actualización de grupo reemplaza el elemento y no incrementa de nuevo el conteo.
- [ ] El listener solo se monta con un usuario autenticado y se desuscribe al desmontarse.
- [ ] `npm run build:ssr` termina sin errores de browser globals y la conexión usa la configuración Reverb existente.

### TASK-019: Completar la campana y su desplegable

Reemplazar el marcador de `HomeNotificationHeader` con una campana accesible, badge, desplegable de recientes y enlace al historial. El desplegable carga el endpoint reciente al abrirse, se mantiene sincronizado con el store Echo y abre `posts.show` al seleccionar un aviso.

**Type:** feature
**Effort:** M
**Agent:** react-vite-tailwind-engineer
**Priority:** P1
**Review:** codex
**Depends on:** TASK-013, TASK-015, TASK-016, TASK-017, TASK-018
**Write scope:**
- `resources/js/modules/home/components/header/HomeNotificationHeader.tsx`
- `resources/js/modules/notification/components/NotificationDropdown.tsx`
- `resources/js/modules/notification/hooks/useNotificationDropdown.ts`
**Validate:**
```bash
rtk npm run types
rtk npx eslint resources/js/modules/home/components/header/HomeNotificationHeader.tsx resources/js/modules/notification/components/NotificationDropdown.tsx resources/js/modules/notification/hooks/useNotificationDropdown.ts
rtk npx prettier --check resources/js/modules/home/components/header/HomeNotificationHeader.tsx resources/js/modules/notification/components/NotificationDropdown.tsx resources/js/modules/notification/hooks/useNotificationDropdown.ts
```
**Acceptance Criteria:**
- [ ] El badge representa grupos no leídos; el desplegable muestra hasta cinco avisos y el enlace abre el historial completo.
- [ ] Al seleccionar un aviso se marca como leído y navega a la publicación; un destino eliminado conserva el resumen y no rompe la campana.
- [ ] El botón, badge, empty state, acciones y toasts tienen copy traducido y nombres accesibles en inglés y español.

### TASK-020: Documentar la arquitectura y el recorrido de una notificación

Actualizar CLAUDE.md y la documentación de arquitectura del backend/frontend con el módulo Notification, el contrato de destinatarios, la agrupación de likes, el payload, los endpoints y el flujo evento → persistencia → cola → Reverb → canal privado → Echo/React. Explicar las decisiones y los límites, sin documentar mecanismos que no queden implementados.

**Type:** docs
**Effort:** S
**Agent:** general-purpose
**Priority:** P2
**Review:** codex
**Depends on:** TASK-001, TASK-002, TASK-004, TASK-007, TASK-009, TASK-013, TASK-014, TASK-016, TASK-017, TASK-018, TASK-019
**Write scope:**
- `CLAUDE.md`
- `docs/architecture/backend.md`
- `docs/architecture/frontend.md`
**Validate:**
```bash
git diff --check -- CLAUDE.md docs/architecture/backend.md docs/architecture/frontend.md
```
**Acceptance Criteria:**
- [ ] CLAUDE.md explica canal, driver, autenticación, cola, arranque local y módulo de notificaciones con los motivos de diseño.
- [ ] Los documentos de arquitectura describen los límites backend/frontend y la semántica acordada de comentarios, respuestas y likes agrupados.
- [ ] Las notificaciones de Filament siguen descritas como existentes e independientes del centro React.

### TASK-021: Actualizar los comandos locales de desarrollo

Documentar en `docs/development/backend-commands.md` que `composer run dev` arranca Reverb junto con los procesos Laravel ya registrados, y explicar cómo validar y reiniciar Reverb cuando cambie su configuración.

**Type:** docs
**Effort:** S
**Agent:** general-purpose
**Priority:** P2
**Review:** codex
**Depends on:** TASK-001
**Write scope:**
- `docs/development/backend-commands.md`
**Validate:**
```bash
git diff --check -- docs/development/backend-commands.md
```
**Acceptance Criteria:**
- [ ] La guía enumera `serve`, `queue:listen`, Vite y Reverb con el comando que los registra.
- [ ] La guía diferencia las variables cliente `VITE_REVERB_*` de credenciales de servidor y explica el broadcaster activo local.
- [ ] Los comandos documentados coinciden con `composer run dev` y `php artisan reverb:restart`.

## Failure Modes

- Si `BROADCAST_CONNECTION` permanece en `log` o `reverb:start` no se registra, la UI puede mostrar persistencia pero no recibirá eventos reales.
- Si el canal usa el namespace automático o no valida propietario/estado, un usuario puede perder sus avisos o suscribirse al canal de otro.
- Si una notificación se emite antes de que su escritura termine, el cliente puede recibir una fila que no se puede cargar; las actualizaciones agrupadas se publican después del commit.
- Si el servicio de likes no serializa actualizaciones concurrentes o React cuenta cada update del mismo id como nuevo, habrá grupos duplicados y conteos inflados.
- Si el controlador carga relaciones del actor o destino por fila, el historial puede introducir N+1 y fallar cuando se borre un destino.
- Si los endpoints no consultan a través de `$user->notifications()`, un usuario puede marcar o leer notificaciones ajenas.
- Si el listener se monta más de una vez, un evento puede producir toasts duplicados y estado desincronizado.
- Si se omite un catálogo o un placeholder entre `lang/en` y `lang/es`, la traducción del backend y la interfaz dejan de coincidir.
- No hay runner de pruebas React instalado; la validación de UI depende de TypeScript, ESLint, Prettier, build SSR y verificación manual con dos usuarios.

## Ship Cut

- El corte de entrega exige canal privado autorizado, cola/Reverb local, persistencia de los tres tipos, agrupación de likes, historial cursor-paginado, contador, lectura individual/total, deep link a publicación y actualización realtime de campana/toast.
- No presentar como terminado un backend que solo guarda notificaciones o una UI que solo muestra toasts; ambas rutas deben probarse de punta a punta.
- Mantener fuera del corte avisos de seguridad, anuncios en vivo, likes agrupados por actor en otras pantallas, preferencias, Filament, email y push.

## Test Coverage Map

- **Dev processes y broadcaster:** `tests/Feature/Console/DevProcessConfigurationTest.php` — Reverb y queue:listen quedan registrados para composer run dev.
- **Canal privado y locale de User:** `app/User/Tests/Notifications/UserNotificationChannelTest.php` — Propietario, invitado, cuenta suspendida y HasLocalePreference.
- **Serialización de notificaciones:** `app/Modules/Notification/Tests/Notifications/CommentNotificationsTest.php y app/Modules/Notification/Tests/Resources/NotificationResourceTest.php` — Canales, payload mínimo/localizado, campos permitidos y destino eliminado.
- **Suscriptores de dominio:** `app/Modules/Notification/Tests/Listeners/CommentNotificationSubscriberTest.php y app/Modules/Notification/Tests/Listeners/LikeNotificationSubscriberTest.php` — Destinatario exacto, exclusión de la acción propia y cambio de tipo de evento.
- **Agrupación de likes:** `app/Modules/Notification/Tests/Services/LikeNotificationGroupServiceTest.php` — Agregado por destino, unlike, grupo vacío, grupo leído y escritura concurrente.
- **Prop compartida y API:** `app/Modules/Notification/Tests/Props/NotificationPropsTest.php, app/Modules/Notification/Tests/Props/SharedUnreadNotificationsTest.php y app/Modules/Notification/Tests/Controllers/NotificationControllerTest.php` — Conteo propio, cursor, recientes, acciones de lectura y acceso ajeno.
- **Destino de publicación:** `app/Modules/Post/Tests/Crud/PostShowTest.php` — Ruta posts.show, autenticación, recurso y 404 del destino.
- **Frontend:** `Sin suite React configurada; `npm run types`, ESLint, Prettier, `npm run build:ssr` y navegador con dos sesiones.` — Render SSR, recepción realtime, agrupación, toast, conteo, historial, marcaje y textos es/en.

## Execution Summary

- **Mode:** EXPANSION
- **Default review:** codex
- **Task count:** 21
- **Critical path:** TASK-003 → TASK-004 → TASK-006 → TASK-010 → TASK-011 → TASK-012 → TASK-015 → TASK-017 → TASK-019 → TASK-020
- **Critical path summary:** Crear la base del módulo Notification → Registrar el módulo y su suite Pest → Crear notificaciones Laravel para comentarios → Serializar notificaciones con un contrato seguro → Calcular el conteo diferido de no leídas → Compartir el conteo con Home y React → Tipar el contrato compartido de notificaciones → Construir la página de historial → Completar la campana y su desplegable → Documentar la arquitectura y el recorrido de una notificación

## Task Dependencies

- **Layer 1:** TASK-001, TASK-002, TASK-003, TASK-005, TASK-014
- **Layer 2:** TASK-004, TASK-016, TASK-021
- **Layer 3:** TASK-006, TASK-008
- **Layer 4:** TASK-007, TASK-010
- **Layer 5:** TASK-009, TASK-011
- **Layer 6:** TASK-012, TASK-013
- **Layer 7:** TASK-015
- **Layer 8:** TASK-017, TASK-018
- **Layer 9:** TASK-019
- **Layer 10:** TASK-020

