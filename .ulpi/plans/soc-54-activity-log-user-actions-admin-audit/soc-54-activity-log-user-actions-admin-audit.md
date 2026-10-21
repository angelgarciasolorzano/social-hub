# Plan: SOC-54: Registro de actividad de usuarios y auditoría administrativa (parte 1)

## Overview

Registrar de forma persistente las acciones de los usuarios y la auditoría administrativa con spatie/laravel-activitylog v5, a través de un módulo nuevo `app/Modules/ActivityLog/` con un servicio `ActivityLogger`, enums de logs y eventos, subscribers de eventos de dominio y una purga programada con retención por log. Una sola tabla `activity_log` separa los dominios con `log_name`.

**Linear:** [SOC-54](https://linear.app/social-hub-ang/issue/SOC-54/feature-add-an-activity-log-for-user-actions-and-administrative-audit) · **Modo:** EXPANSION · **Revisión predeterminada:** Claude

## Scope Challenge

EXPANSION confirmada. Además del alcance de Linear (publicaciones, comentarios creados, contraseña, 2FA, login/logout, comandos Admin e invitaciones de administrador) se registran: edición y eliminación de comentarios, likes, un espejo resumido de las acciones de dispositivos de confianza y cambios/eliminación de la cuenta. El usuario propuso varias tablas por dominio; se acordó una sola tabla con `log_name` (auth, content, likes, trusted_devices, account, admin), índices compuestos y retención por log, porque el volumen se controla con la retención y la línea de tiempo de la parte 2 necesita consultar todo junto. Los comandos Admin reciben `--actor=<email>` obligatorio. IP y user agent se guardan completos. Revisión predeterminada: Claude.

## Prerequisites

- SOC-53 ya está en main: `AdminInvitationService` (issue/accept), la acción de invitar en `ListUsers` y `AcceptAdminInvitation`.
- Los comandos `admin:user-role`, `admin:role-permissions` y `admin:bootstrap-admin` existen en `app/Modules/Admin/Console/Commands/` y comparten `InteractsWithAdminCatalog`.
- El login usa `Auth::login()` (dispara `Login`), el logout `Auth::guard('web')->logout()` (dispara `Logout`), y el restablecimiento ya despacha `PasswordReset`; Fortify despacha `TwoFactorAuthenticationConfirmed`, `TwoFactorAuthenticationDisabled` y `RecoveryCodesGenerated`.
- El login fallido valida con `Auth::getProvider()` directamente, así que hoy no despacha `Failed` (lo agrega TASK-007).
- La dependencia spatie/laravel-activitylog está aprobada explícitamente en SOC-54.

## Non-Goals

- Registro de vistas visitadas, línea de tiempo diaria y recurso Filament de solo lectura, interacciones de React y correos de seguridad (parte 2).
- Reemplazar `TrustedDeviceEvent` o cambiar `TrustedDeviceService`; el modelo solo gana el despacho de `TrustedDeviceEventRecorded` en `record()` (TASK-016) y el log general refleja sus filas.
- Varias tablas de actividad por dominio; si un log (likes) crece demasiado, se separará más adelante con datos reales.
- Enviar el registro a una cola: se escribe en la misma transacción para no perder atomicidad; se reevaluará si se mide latencia.
- Registrar acciones del sistema (seeders, borrado automático del padre vacío de un comentario, purgas).
- Suspensión y reactivación de cuentas (SOC-35), que solo añadirá sus casos a `ActivityLogAccountEvent` (o un enum nuevo registrado en `ActivityLogEvent::ENUMS`) y llamará a `ActivityLogger`.
- Migrar datos históricos: no hay registros previos que importar.

## Contracts

- Solo `App\Modules\ActivityLog` usa `Spatie\Activitylog`; controladores, comandos y servicios de otros módulos llaman a `ActivityLogger` o despachan eventos de dominio.
- `ActivityLogger::record(ActivityLogEvent, ?Model $subject, ?Model $causer, array $properties)` siempre fija el causer (o anónimo), deriva `log_name` del evento y rechaza claves prohibidas.
- Las acciones de usuario se registran con subscribers que escuchan eventos de dominio; la auditoría Admin (comandos e invitaciones) llama a `ActivityLogger` dentro de su transacción para que el registro sea atómico con el cambio.
- `ActivityLogServiceProvider` es la única superficie de registro (config, subscribers, listener Eloquent de `TrustedDeviceEvent`, comando y schedule); las tareas que lo editan están encadenadas.
- Morph map: se añaden `admin_invitation`, `trusted_device_event` y `role`; las claves existentes (`user`, `post`, `like`, `comment`) no cambian.
- Privacidad: nunca contenido de publicaciones o comentarios, contraseñas, códigos OTP o de recuperación, secretos, tokens ni correos; solo ids, nombres de atributos cambiados, el guard, IP y user agent.
- Los comandos Admin cambian de firma: `--actor=<email>` es obligatorio; el actor debe existir, estar verificado y tener `admin.access`, salvo en `admin:bootstrap-admin`.
- Retención por log en `module.activity_log.retention_days` (variables `SOCIALHUB_ACTIVITY_LOG_<LOG>_RETENTION_DAYS`); `activity-log:purge` corre a diario a las 03:30.

## Existing Code Leverage

- `app/Auth/Models/TrustedDeviceEvent.php` y `TrustedDeviceEvent::record()` — patrón append-only existente y origen del espejo.
- `app/Auth/Providers/AuthServiceProvider.php` y `app/Auth/config/trusted-devices.php` — patrón de `mergeConfigFrom`, registro de comando solo en consola y `Schedule::command(...)->dailyAt()`.
- `app/User/Providers/UserEventServiceProvider.php` y `app/Auth/Providers/AuthEventServiceProvider.php` — cómo se registran listeners de Fortify y de `Login`.
- `app/Modules/Admin/Console/Concerns/InteractsWithAdminCatalog.php` — lugar compartido para resolver el actor de los comandos.
- `app/Modules/Admin/Services/AdminInvitationService.php` — transacciones de emisión y aceptación.
- `app/Providers/AppServiceProvider.php` — `Relation::enforceMorphMap`.
- `tests/Pest.php` (`allowOtpReuse()`, helpers de usuarios) y `phpunit.xml` — registro de carpetas de pruebas de módulo.

## External References

- Spatie Activitylog v5, instalación: https://spatie.be/docs/laravel-activitylog/v5/installation-and-setup
- Spatie Activitylog v5, registro de actividad: https://spatie.be/docs/laravel-activitylog/v5/basic-usage/logging-activity
- Spatie Activitylog v5, limpieza (`activitylog:clean <log> --days --force`): https://spatie.be/docs/laravel-activitylog/v5/basic-usage/cleaning-up-the-log

## Tasks

### TASK-001: Instalar spatie/laravel-activitylog y publicar su configuración

Ejecutar `composer require spatie/laravel-activitylog:^5` (dependencia ya aprobada en SOC-54) y publicar `config/activitylog.php` con el tag `activitylog-config`. Ajustar solo `include_soft_deleted_subjects => true` (para que un comentario convertido en tombstone siga resolviendo como sujeto) y dejar `default_auth_driver` en null y `clean_after_days` sin uso (la retención real la define el módulo, TASK-003/TASK-024). Antes de cerrar la tarea, leer en la documentación de v5 la página de buffering y confirmar que no está activo por defecto; si lo estuviera, desactivarlo en la configuración y documentarlo.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
- `composer show spatie/laravel-activitylog` reporta una versión 5.x y `composer.lock` queda actualizado.
- `php artisan config:show activitylog.include_soft_deleted_subjects` devuelve true y `activitylog.enabled` sigue en true.
- Ningún archivo de `app/` llama todavía a `activity()` ni a clases de `Spatie\Activitylog` (grep vacío).

**writeScope:**
- `config/activitylog.php` (nuevo)
- `composer.json`
- `composer.lock`

**validateCommand:** `rtk composer show spatie/laravel-activitylog && rtk php artisan config:show activitylog.include_soft_deleted_subjects`

**Review:** Claude

### TASK-002: Publicar la migración de activity_log con índices de consulta

Publicar la migración del paquete (`--tag=activitylog-migrations`) en el directorio global `database/migrations/` (regla backend: las migraciones publicadas de paquetes son globales). Mantener las columnas del paquete (`log_name`, `description`, `subject` y `causer` con `nullableMorphs`, `event`, `attribute_changes`, `properties`, timestamps) con IDs enteros, y agregar índices compuestos `(log_name, created_at)` para la purga por log y `(causer_type, causer_id, created_at)` para consultar por usuario y fecha.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**
- `php artisan migrate:fresh --env=testing` crea la tabla `activity_log` con `subject_id` y `causer_id` enteros.
- `php artisan db:table activity_log --env=testing` muestra los índices `(log_name, created_at)` y `(causer_type, causer_id, created_at)`.
- `php artisan migrate:rollback --step=1 --env=testing` elimina la tabla sin error (el down es simétrico).

**writeScope:**
- `database/migrations/2026_10_21_000000_create_activity_log_table.php` (nuevo)

**validateCommand:** `rtk php artisan migrate:fresh --env=testing --force && rtk php artisan db:table activity_log --env=testing`

**Review:** Claude

### TASK-003: Crear el catálogo de eventos por dominio y la configuración de retención

Crear el módulo `app/Modules/ActivityLog/` (nueva arquitectura). `Enums/ActivityLogName` (backed string): `auth`, `content`, `likes`, `trusted_devices`, `account`, `admin`, con `retentionDays(): int` leído de `module.activity_log.retention_days.<log>`. Los eventos se dividen en un enum por dominio en `Enums/Events/` (`ActivityLogAuthEvent`, `ActivityLogContentEvent`, `ActivityLogLikeEvent`, `ActivityLogTrustedDeviceEvent`, `ActivityLogAccountEvent`, `ActivityLogAdminEvent`; valores `dominio.accion`), porque PHP no permite que un enum herede o reúna casos de otros. `Contracts/ActivityLogEvent` (interfaz que extiende `BackedEnum`) es el orquestador: declara `eventName(): string` y `logName(): ActivityLogName`, y su constante `ENUMS` lista los enums registrados; un dominio nuevo (por ejemplo SOC-35) crea su enum y lo agrega ahí. `config/activity-log.php` define la retención por log con variables `SOCIALHUB_ACTIVITY_LOG_<LOG>_RETENTION_DAYS` (por defecto: auth 180, content 365, likes 30, trusted_devices 180, account 365, admin 730). El merge de la configuración lo hace TASK-004. Durante la ejecución se añadió `app/Modules/ActivityLog/config` a `configDirectories` de `phpstan.neon` (como `app/Auth/config`) para que Larastan acepte `env()` en el config del módulo. Esta tarea excede el límite de 3 archivos por la división acordada con el usuario.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
- Cada enum listado en `ActivityLogEvent::ENUMS` implementa el contrato, devuelve un único `ActivityLogName` y ningún log queda sin eventos.
- Los valores de evento son únicos entre todos los enums del catálogo (31 hoy).
- La configuración define una retención entera positiva para cada caso de `ActivityLogName`, y PHPStan nivel max pasa sin `mixed` ni supresiones.

**writeScope:**
- `app/Modules/ActivityLog/Enums/ActivityLogName.php` (nuevo)
- `app/Modules/ActivityLog/Contracts/ActivityLogEvent.php` (nuevo)
- `app/Modules/ActivityLog/Enums/Events/ActivityLogAuthEvent.php` (nuevo)
- `app/Modules/ActivityLog/Enums/Events/ActivityLogContentEvent.php` (nuevo)
- `app/Modules/ActivityLog/Enums/Events/ActivityLogLikeEvent.php` (nuevo)
- `app/Modules/ActivityLog/Enums/Events/ActivityLogTrustedDeviceEvent.php` (nuevo)
- `app/Modules/ActivityLog/Enums/Events/ActivityLogAccountEvent.php` (nuevo)
- `app/Modules/ActivityLog/Enums/Events/ActivityLogAdminEvent.php` (nuevo)
- `app/Modules/ActivityLog/config/activity-log.php` (nuevo)
- `phpstan.neon`

**validateCommand:** `rtk vendor/bin/phpstan analyse app/Modules/ActivityLog`

**Review:** Claude

### TASK-004: Crear el servicio ActivityLogger y registrar el proveedor del módulo

`Services/ActivityLogger::record(ActivityLogEvent $event, ?Model $subject = null, ?Model $causer = null, array $properties = []): void` es el único punto que usa `activity()`: recibe el contrato `Contracts\ActivityLogEvent` (cualquier enum del catálogo), fija `useLog($event->logName()->value)`, `event($event->eventName())`, `performedOn`, y `causedBy($causer)` o `causedByAnonymous()` cuando es null (para que el paquete no adivine un usuario de otro guard). Si hay una petición HTTP agrega `ip` y `user_agent` completos a las propiedades (decisión confirmada; la purga los elimina con el registro). Rechaza con `InvalidArgumentException` claves prohibidas en `properties` (`password`, `password_confirmation`, `current_password`, `token`, `token_hash`, `code`, `otp_code`, `recovery_code(s)`, `two_factor_secret`, `content`, `email`). `ActivityLogServiceProvider` hace `mergeConfigFrom` a `module.activity_log` y se registra en `bootstrap/providers.php`; es la superficie compartida donde las tareas posteriores suscriben sus listeners.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001, TASK-003

**Acceptance Criteria:**
- `php artisan config:show module.activity_log` muestra la retención de los seis logs.
- Llamar a `record()` con una clave prohibida lanza `InvalidArgumentException` y no inserta filas.
- Sin causer explícito el registro queda con `causer_type`/`causer_id` null aunque haya un usuario autenticado en el guard web.

**writeScope:**
- `app/Modules/ActivityLog/Services/ActivityLogger.php` (nuevo)
- `app/Modules/ActivityLog/Providers/ActivityLogServiceProvider.php` (nuevo)
- `bootstrap/providers.php`

**validateCommand:** `rtk php artisan config:show module.activity_log && rtk vendor/bin/phpstan analyse app/Modules/ActivityLog`

**Review:** Claude

### TASK-005: Probar ActivityLogger y registrar la carpeta de pruebas del módulo

Registrar `app/Modules/ActivityLog/Tests` en `phpunit.xml` y en el bloque `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in(...)` de `tests/Pest.php`. Probar el servicio y el catálogo (todo enum de `ActivityLogEvent::ENUMS` implementa el contrato y los valores son únicos): log y evento correctos, causer y subject que resuelven por el morph map (`user`, `post`), IP/UA presentes en HTTP y ausentes en consola, rechazo de claves prohibidas. El catálogo se prueba en su propio archivo (`Tests/Contracts/ActivityLogEventTest.php`, con una regla `arch()` que exige que todo enum de `Enums/Events` implemente el contrato), por eso la tarea toca 4 archivos.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002, TASK-004

**Acceptance Criteria:**
- Un registro con subject Post y causer User guarda `subject_type = post` y `causer_type = user`, y `$activity->subject`/`$activity->causer` devuelven los modelos.
- Cada clave prohibida (dataset) lanza excepción y deja `activity_log` vacío.
- Ejecutado sin petición HTTP, el registro no contiene `ip` ni `user_agent`.

**writeScope:**
- `app/Modules/ActivityLog/Tests/Services/ActivityLoggerTest.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Contracts/ActivityLogEventTest.php` (nuevo)
- `phpunit.xml`
- `tests/Pest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests`

**Review:** Claude

### TASK-006: Ampliar el morph map para los nuevos sujetos auditados

Agregar al `Relation::enforceMorphMap` de `AppServiceProvider` las claves `admin_invitation` (`AdminInvitation::MORPH_NAME`), `trusted_device_event` (`TrustedDeviceEvent::MORPH_NAME`) y `role` (`Spatie\Permission\Models\Role`). Añadir solo la constante `MORPH_NAME` a los dos modelos propios, sin cambiar su comportamiento. Spatie Permission guarda en `model_has_roles` el morph del usuario, no del rol, así que mapear `Role` no altera datos existentes.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
- `Relation::getMorphedModel('role')`, `('admin_invitation')` y `('trusted_device_event')` devuelven sus clases.
- Las suites existentes de TrustedDevice, Admin (modelos, comandos, invitaciones) y Like siguen verdes sin cambios en sus pruebas.

**writeScope:**
- `app/Providers/AppServiceProvider.php`
- `app/Modules/Admin/Models/AdminInvitation.php`
- `app/Auth/Models/TrustedDeviceEvent.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests app/Modules/Admin/Tests app/Modules/Like/Tests`

**Review:** Claude

### TASK-007: Emitir eventos de dominio de contraseña actualizada y login fallido

Crear `App\Auth\Modules\Password\Events\PasswordUpdated` (lleva el `User`) y despacharlo en `PasswordController::update()` después de que la transacción confirma. En `LoginRequest::validateCredentials()` despachar el evento estándar `Illuminate\Auth\Events\Failed('web', $user, ['email' => ...])` cuando las credenciales no validan, sin incluir la contraseña. `PasswordReset` ya se despacha en `PasswordNewController` y el login exitoso ya dispara `Login`, así que no se tocan.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**
- Actualizar la contraseña despacha `PasswordUpdated` una sola vez y una validación fallida no lo despacha (lo comprueba de extremo a extremo `AuthActivitySubscriberTest`, TASK-008).
- Un login fallido despacha `Failed` cuyo arreglo de credenciales no contiene la clave `password`.
- Las suites existentes de Password y Login siguen verdes.

**writeScope:**
- `app/Auth/Modules/Password/Events/PasswordUpdated.php` (nuevo)
- `app/Auth/Modules/Password/Controllers/PasswordController.php`
- `app/Auth/Modules/Login/Requests/LoginRequest.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/Password/Tests app/Auth/Modules/Login/Tests`

**Review:** Claude

### TASK-008: Registrar autenticación, contraseña y 2FA con AuthActivitySubscriber

`Listeners/AuthActivitySubscriber` (subscriber de Laravel registrado con `Event::subscribe` en `ActivityLogServiceProvider`) traduce a `ActivityLogger`: `Login` (propiedad `guard`, cubre `web` y `admin`), `Logout` (se ignora si no hay usuario), `Failed` (sujeto = usuario si se encontró, causer anónimo, nunca el correo ni la contraseña), `PasswordReset`, `PasswordUpdated`, y los eventos de Fortify `TwoFactorAuthenticationConfirmed`, `TwoFactorAuthenticationDisabled` y `RecoveryCodesGenerated`. Sujeto y causer son el usuario. Probar en esta tarea login, logout, fallo y contraseña; el 2FA lo prueba TASK-009.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004, TASK-005, TASK-007

**Acceptance Criteria:**
- Un login exitoso por `/login` crea un registro `auth` con causer y subject del usuario y `guard = web`.
- Un login fallido con correo existente crea un registro sin causer y sin `email` ni `password` en propiedades; con correo inexistente el sujeto es null.
- Cambiar y restablecer la contraseña generan un registro cada uno; los listeners existentes (`TrackLastLogin`, revocación de dispositivos) siguen funcionando.

**writeScope:**
- `app/Modules/ActivityLog/Listeners/AuthActivitySubscriber.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Listeners/AuthActivitySubscriberTest.php` (nuevo)
- `app/Modules/ActivityLog/Providers/ActivityLogServiceProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Listeners/AuthActivitySubscriberTest.php`

**Review:** Claude

### TASK-009: Probar el registro de las acciones de 2FA

Pruebas de extremo a extremo por HTTP: confirmar el 2FA, desactivarlo (`TwoFactorController::destroy`) y regenerar los códigos de recuperación generan su registro `auth`. Verificar que las propiedades no contienen el secreto, el código OTP usado ni ningún código de recuperación (usar `allowOtpReuse()` cuando haga falta).

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008

**Acceptance Criteria:**
- Confirmar, desactivar y regenerar crean exactamente un registro cada uno con el evento correcto.
- Una desactivación con código inválido no crea registro.
- Ningún registro contiene el secreto TOTP, el OTP enviado ni los códigos de recuperación del usuario.

**writeScope:**
- `app/Modules/ActivityLog/Tests/Listeners/TwoFactorActivityTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Listeners/TwoFactorActivityTest.php`

**Review:** Claude

### TASK-010: Emitir PostCreated al crear una publicación

Crear `App\Modules\Post\Events\PostCreated` (lleva el `Post`) y despacharlo en `PostController` justo después de crear la publicación (la publicación existe aunque luego falle la imagen).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**
- Crear una publicación despacha `PostCreated` con el post creado (comprobado de extremo a extremo en `ContentActivitySubscriberTest`, TASK-013).
- Una petición rechazada por validación no despacha el evento; las pruebas existentes de Post siguen verdes.

**writeScope:**
- `app/Modules/Post/Events/PostCreated.php` (nuevo)
- `app/Modules/Post/Controllers/PostController.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Post/Tests`

**Review:** Claude

### TASK-011: Crear los eventos de dominio de comentarios

Crear en `app/Modules/Comment/Events/`: `CommentCreated` (comentario creado, usuario que actúa y `?int $repliedToCommentId`, el id que el cliente envió cuando es una respuesta), `CommentUpdated` (comentario y usuario) y `CommentDeleted` (comentario en memoria, usuario y `bool $permanently`: true si se borró definitivamente, false si quedó como tombstone). Los tres llevan el `User` que actúa para que el subscriber use un causer explícito sin consultar la relación. Solo clases de evento; el despacho es de TASK-012.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**
- Las tres clases son readonly, tipadas y pasan PHPStan nivel max.
- `CommentDeleted` distingue tombstone de borrado definitivo sin depender de que la fila siga existiendo.

**writeScope:**
- `app/Modules/Comment/Events/CommentCreated.php` (nuevo)
- `app/Modules/Comment/Events/CommentUpdated.php` (nuevo)
- `app/Modules/Comment/Events/CommentDeleted.php` (nuevo)

**validateCommand:** `rtk vendor/bin/phpstan analyse app/Modules/Comment/Events`

**Review:** Claude

### TASK-012: Despachar los eventos de comentarios desde CommentController

`store()` despacha `CommentCreated` con el comentario creado (y el id respondido si el destino era un comentario); `update()` despacha `CommentUpdated` solo cuando el contenido cambió; `destroy()` despacha `CommentDeleted` después de la transacción, con `permanently` según la rama tomada. El borrado del padre vacío no genera evento propio (lo provoca el sistema, no el usuario).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-011

**Acceptance Criteria:**
- Comentar, responder y responder a una respuesta despachan `CommentCreated` una vez cada uno (comprobado en `ContentActivitySubscriberTest`, TASK-013).
- Editar sin cambios no despacha `CommentUpdated`; borrar un comentario con respuestas despacha `CommentDeleted` con `permanently = false` y uno sin respuestas con `true`.
- Las pruebas existentes de Comment (Crud, Security, Props) siguen verdes.

**writeScope:**
- `app/Modules/Comment/Controllers/CommentController.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Comment/Tests`

**Review:** Claude

### TASK-013: Registrar publicaciones y comentarios con ContentActivitySubscriber

`Listeners/ContentActivitySubscriber` registra en el log `content`: publicación creada (sujeto post), comentario creado o respuesta (sujeto comentario; propiedades solo con ids: `post_id` o `thread_comment_id`, `replied_to_comment_id`), comentario editado y eliminado (propiedad `permanently`). Nunca guarda `content`. Suscribirlo en `ActivityLogServiceProvider`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-010, TASK-012

**Acceptance Criteria:**
- Crear una publicación y comentar por HTTP crean registros `content` con causer el autor y subject que resuelve por el morph map.
- Eliminar un comentario con respuestas deja un registro cuyo `subject` sigue resolviendo (tombstone, `include_soft_deleted_subjects`); un borrado definitivo conserva `subject_id` aunque el modelo ya no exista.
- Ningún registro contiene el texto del comentario ni de la publicación.

**writeScope:**
- `app/Modules/ActivityLog/Listeners/ContentActivitySubscriber.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Listeners/ContentActivitySubscriberTest.php` (nuevo)
- `app/Modules/ActivityLog/Providers/ActivityLogServiceProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Listeners/ContentActivitySubscriberTest.php`

**Review:** Claude

### TASK-014: Emitir LikeToggled solo cuando cambia el estado del like

Crear `App\Modules\Like\Events\LikeToggled` (usuario, likeable y `bool $liked`) y despacharlo en `LikeController::toggleLike()` al crear o borrar el like. Si la creación choca con el índice único (`UniqueConstraintViolationException`, doble clic) no se despacha nada porque el estado no cambió.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** Ninguna

**Acceptance Criteria:**
- Dar like despacha `liked = true` y quitarlo `liked = false` (comprobado en `LikeActivitySubscriberTest`, TASK-015).
- La rama de violación de índice único no despacha el evento; las pruebas existentes de Like siguen verdes.

**writeScope:**
- `app/Modules/Like/Events/LikeToggled.php` (nuevo)
- `app/Modules/Like/Controllers/LikeController.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Like/Tests`

**Review:** Claude

### TASK-015: Registrar likes con LikeActivitySubscriber

`Listeners/LikeActivitySubscriber` registra en el log `likes` (retención corta, 30 días por defecto) el like dado o quitado, con sujeto la publicación o el comentario y causer el usuario. Suscribirlo en `ActivityLogServiceProvider`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-013, TASK-014

**Acceptance Criteria:**
- Dar y quitar like a una publicación y a un comentario por HTTP crean registros `likes` con `subject_type` `post` o `comment`.
- Un toggle de un invitado (401) no crea registro.

**writeScope:**
- `app/Modules/ActivityLog/Listeners/LikeActivitySubscriber.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Listeners/LikeActivitySubscriberTest.php` (nuevo)
- `app/Modules/ActivityLog/Providers/ActivityLogServiceProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Listeners/LikeActivitySubscriberTest.php`

**Review:** Claude

### TASK-016: Reflejar en el log general las acciones de dispositivos de confianza

Decisión tomada al ejecutar: escuchar el evento Eloquent `created` también reflejaba las filas de TrustedDeviceSeeder y de las factories, así que `TrustedDeviceEvent::record()` despacha un evento de dominio nuevo, `App\Auth\Modules\TrustedDevice\Events\TrustedDeviceEventRecorded` (fila y usuario), sin cambiar lo que guarda ni lo que devuelve. `Listeners/TrustedDeviceActivityMirror` escucha ese evento (`Event::listen` en `ActivityLogServiceProvider`) y traduce cada `TrustedDeviceAction` a su caso de `ActivityLogTrustedDeviceEvent` en el log `trusted_devices`, con sujeto la fila de `TrustedDeviceEvent` (morph `trusted_device_event`) y causer su usuario; no copia la instantánea del dispositivo. Al despacharse dentro de la transacción del servicio, un rollback descarta ambas filas. La tarea toca 5 archivos por este cambio.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-006, TASK-015

**Acceptance Criteria:**
- Cada `TrustedDeviceAction` (dataset) produce un registro con el evento correspondiente y un subject que resuelve a la fila de `TrustedDeviceEvent`.
- Las filas creadas por factories o seeders no generan registro espejo, y un rollback de la transacción no deja ni el `TrustedDeviceEvent` ni el espejo.
- La suite de TrustedDevice sigue verde sin cambios en sus pruebas.

**writeScope:**
- `app/Auth/Modules/TrustedDevice/Events/TrustedDeviceEventRecorded.php` (nuevo)
- `app/Modules/ActivityLog/Listeners/TrustedDeviceActivityMirror.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Listeners/TrustedDeviceActivityMirrorTest.php` (nuevo)
- `app/Auth/Models/TrustedDeviceEvent.php`
- `app/Modules/ActivityLog/Providers/ActivityLogServiceProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Listeners/TrustedDeviceActivityMirrorTest.php app/Auth/Modules/TrustedDevice/Tests`

**Review:** Claude

### TASK-017: Crear los eventos de dominio de la cuenta

Crear en `app/User/Events/`: `AccountUpdated` (usuario y `list<string> $changedAttributes`, solo nombres de atributos), `AccountImageUpdated` (usuario y `UserImageType`) y `AccountDeleted` (usuario en memoria antes de borrarse). Solo clases de evento; el despacho es de TASK-018.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** Ninguna

**Acceptance Criteria:**
- Las tres clases son readonly, tipadas y pasan PHPStan nivel max.
- `AccountUpdated` solo acepta nombres de atributos, no valores.

**writeScope:**
- `app/User/Events/AccountUpdated.php` (nuevo)
- `app/User/Events/AccountImageUpdated.php` (nuevo)
- `app/User/Events/AccountDeleted.php` (nuevo)

**validateCommand:** `rtk vendor/bin/phpstan analyse app/User/Events`

**Review:** Claude

### TASK-018: Despachar los eventos de cuenta desde AccountSettings y UserController

`AccountSettingsController::update()` y `UserController::update()` despachan `AccountUpdated` con las claves de `getChanges()` sin `updated_at`, solo si hubo cambios. `UserController::updateImage()` despacha `AccountImageUpdated` tras subir la imagen. `UserController::destroy()` despacha `AccountDeleted` después de `delete()`, solo si el borrado tuvo éxito (la instancia en memoria conserva su id). Las dos actualizaciones usan `AccountUpdated::fromSavedChanges($user)` cuando `$user->wasChanged()`; ese constructor se agregó al evento de TASK-017 para no repetir el cálculo en los dos controladores.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-017

**Acceptance Criteria:**
- Guardar datos distintos despacha `AccountUpdated` con los nombres cambiados y guardar los mismos datos no despacha nada (comprobado en `AccountActivitySubscriberTest`, TASK-019, que también cubre `UserController`, hoy sin pruebas propias).
- Una subida de imagen fallida (`FileIsTooBig`) no despacha `AccountImageUpdated`; las pruebas existentes de AccountSettings siguen verdes.

**writeScope:**
- `app/User/Modules/AccountSettings/Controllers/AccountSettingsController.php`
- `app/User/Controllers/UserController.php`
- `app/User/Events/AccountUpdated.php`

**validateCommand:** `rtk php artisan test --compact app/User/Modules/AccountSettings/Tests`

**Review:** Claude

### TASK-019: Registrar los cambios de cuenta con AccountActivitySubscriber

`Listeners/AccountActivitySubscriber` registra en el log `account` la actualización (propiedad `changed_attributes` con nombres), el cambio de imagen (`image_type`) y la eliminación de la cuenta. Suscribirlo en `ActivityLogServiceProvider`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-016, TASK-018

**Acceptance Criteria:**
- Actualizar teléfono y biografía crea un registro con `changed_attributes = [phone, biography]` y sin sus valores; guardar sin cambios no crea registro.
- Cambiar la imagen de perfil (`UserController::updateImage`) crea un registro con `image_type`; una subida fallida no.
- Eliminar la cuenta deja un registro cuyo `causer_id` conserva el id del usuario borrado (el causer ya no resuelve) y que la purga eliminará por retención.

**writeScope:**
- `app/Modules/ActivityLog/Listeners/AccountActivitySubscriber.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Listeners/AccountActivitySubscriberTest.php` (nuevo)
- `app/Modules/ActivityLog/Providers/ActivityLogServiceProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Listeners/AccountActivitySubscriberTest.php`

**Review:** Claude

### TASK-020: Agregar --actor y auditoría a admin:user-role

En `InteractsWithAdminCatalog` agregar `resolveActor(?string $email, bool $requiresAdminAccess = true): ?User`, que informa el error y devuelve null si falta la opción, el usuario no existe, no está verificado o, cuando se exige, no tiene `admin.access`. `admin:user-role` exige `--actor=<email>` y, dentro de su transacción, llama a `ActivityLogger` (log `admin`) solo cuando el estado cambió: rol concedido o revocado, causer el actor, subject el usuario afectado, propiedad `role`. Actualizar sus pruebas.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004, TASK-005

**Acceptance Criteria:**
- `admin:user-role grant <email> <rol> --actor=<admin>` asigna el rol y crea un registro con causer el actor y subject el usuario.
- Sin `--actor`, con un actor inexistente o sin `admin.access` el comando devuelve INVALID/FAILURE sin cambiar roles ni registrar.
- Conceder un rol que ya tenía o la revocación bloqueada por el último `admin.access` no crean registro.

**writeScope:**
- `app/Modules/Admin/Console/Concerns/InteractsWithAdminCatalog.php`
- `app/Modules/Admin/Console/Commands/AdminUserRoleCommand.php`
- `app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php`

**Review:** Claude

### TASK-021: Agregar --actor y auditoría a admin:role-permissions

`admin:role-permissions` exige `--actor` (con `admin.access`) y registra el permiso concedido o revocado cuando cambia, con causer el actor, subject el `Role` (morph `role`) y propiedad `permission`. Actualizar sus pruebas.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006, TASK-020

**Acceptance Criteria:**
- Conceder un permiso a un rol crea un registro `admin` cuyo subject resuelve al `Role`.
- Una revocación rechazada por proteger el último `admin.access` o un permiso fuera del catálogo no crean registro.

**writeScope:**
- `app/Modules/Admin/Console/Commands/AdminRolePermissionsCommand.php`
- `app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`

**Review:** Claude

### TASK-022: Agregar --actor y auditoría a admin:bootstrap-admin

`admin:bootstrap-admin` exige `--actor`, resuelto con `requiresAdminAccess: false` (todavía no existe ningún administrador): debe existir y estar verificado y puede ser el mismo usuario promovido. Registra el arranque con causer el actor, subject el usuario promovido y propiedad `roles` con los cuatro roles asignados. Actualizar sus pruebas.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-020

**Acceptance Criteria:**
- Un arranque válido crea un registro `admin` con los roles asignados.
- Un segundo arranque rechazado o un actor no verificado no cambian roles ni crean registro.

**writeScope:**
- `app/Modules/Admin/Console/Commands/AdminBootstrapCommand.php`
- `app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`

**Review:** Claude

### TASK-023: Auditar la emisión y aceptación de invitaciones de administrador

En `AdminInvitationService`, dentro de cada transacción: `issue()` registra la invitación emitida (causer el invitador, subject la invitación con morph `admin_invitation`, propiedad `target_user_id` o null; nunca el correo ni el token); `accept()` registra la aceptación (causer el usuario resultante, subject la invitación, propiedades `account_created` y `roles_assigned`). Un rechazo no registra nada.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004, TASK-005, TASK-006

**Acceptance Criteria:**
- Emitir una invitación desde Filament crea un registro con causer el invitador y subject la invitación.
- Aceptar con cuenta nueva registra `account_created = true`; con cuenta existente, false; un token vencido, reutilizado o alterado no crea registro.
- Ningún registro contiene el token en claro, su hash, el correo invitado ni la contraseña elegida.

**writeScope:**
- `app/Modules/Admin/Services/AdminInvitationService.php`
- `app/Modules/Admin/Tests/Invitations/AdminInvitationServiceTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Invitations`

**Review:** Claude

### TASK-024: Crear y programar la purga por log con retención configurable

`Console/Commands/ActivityLogPurgeCommand` (`activity-log:purge`) recorre `ActivityLogName::cases()` y ejecuta `activitylog:clean <log> --days=<retentionDays> --force` por cada log, informando cuántas filas borró. `ActivityLogServiceProvider` registra el comando (solo en consola, como `AuthServiceProvider`) y lo programa a diario a las 03:30 (después de `trusted-devices:purge`).

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-002, TASK-019

**Acceptance Criteria:**
- Con retenciones distintas, la purga borra los registros vencidos de cada log y conserva los recientes y los de otros logs dentro de su plazo.
- Cambiar `module.activity_log.retention_days.likes` en la prueba cambia el corte solo de ese log.
- El scheduler contiene `activity-log:purge` a las 03:30.

**writeScope:**
- `app/Modules/ActivityLog/Console/Commands/ActivityLogPurgeCommand.php` (nuevo)
- `app/Modules/ActivityLog/Tests/Console/ActivityLogPurgeCommandTest.php` (nuevo)
- `app/Modules/ActivityLog/Providers/ActivityLogServiceProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Console/ActivityLogPurgeCommandTest.php`

**Review:** Claude

### TASK-025: Pruebas transversales de privacidad y de frontera del paquete

Una prueba de seguridad ejecuta los flujos registrados con valores centinela conocidos (texto de comentario y publicación, contraseña, OTP, código de recuperación, token de invitación) y comprueba que ninguna fila de `activity_log` (descripción ni propiedades) los contiene. Una prueba de arquitectura de Pest verifica que solo `App\Modules\ActivityLog` usa `Spatie\Activitylog` y que ningún controlador lo hace.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-009, TASK-013, TASK-015, TASK-016, TASK-019, TASK-021, TASK-022, TASK-023

**Acceptance Criteria:**
- Ningún centinela aparece en `activity_log` tras ejecutar todos los flujos.
- La regla de arquitectura falla si un controlador importa `Spatie\Activitylog`.
- Cada acción del alcance aparece al menos una vez con causer, subject, evento y fecha (o causer null documentado para el login fallido).

**writeScope:**
- `app/Modules/ActivityLog/Tests/Security/ActivityLogPrivacyTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Security/ActivityLogPrivacyTest.php`

**Review:** Claude

### TASK-026: Documentar el módulo ActivityLog y la nueva firma de los comandos Admin

Actualizar CLAUDE.md (dominio ActivityLog: decisión de usar el paquete con una tabla y `log_name`, contrato `ActivityLogEvent` con enums por dominio, servicio, subscribers, morph map ampliado, política de privacidad, IP/UA, retención y purga, `--actor`), `docs/architecture/backend.md` y AGENTS.md donde mencionan `admin:user-role`, `admin:role-permissions` y `admin:bootstrap-admin`.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-024, TASK-025

**Acceptance Criteria:**
- Los ejemplos de los tres comandos Admin en los tres documentos incluyen `--actor`.
- CLAUDE.md lista los seis logs con su retención por defecto y las claves prohibidas.

**writeScope:**
- `CLAUDE.md`
- `docs/architecture/backend.md`
- `AGENTS.md`

**validateCommand:** `rtk grep -n "actor\|ActivityLog" CLAUDE.md docs/architecture/backend.md AGENTS.md`

**Review:** Claude

### TASK-027: Agregar las variables de retención a .env.example

Añadir `SOCIALHUB_ACTIVITY_LOG_<LOG>_RETENTION_DAYS` para los seis logs con sus valores por defecto, junto a las variables de TrustedDevice.

**Type:** chore · **Effort:** S · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-003

**Acceptance Criteria:**
- `.env.example` define las seis variables con los mismos valores por defecto que `config/activity-log.php`.

**writeScope:**
- `.env.example`

**validateCommand:** `rtk grep -n SOCIALHUB_ACTIVITY_LOG .env.example`

**Review:** Claude

### TASK-028: Pasar los gates de calidad y la suite completa

Ejecutar el gate del proyecto: `composer rector-clear-cache` → `composer rector-dry` → `composer rector`, `vendor/bin/pint --dirty --format agent`, `composer phpstan` y `php artisan test --compact`. Solo corrige archivos ya tocados por tareas anteriores.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-026, TASK-027

**Acceptance Criteria:**
- Rector no propone cambios después de aplicarse, Pint queda limpio y PHPStan nivel max pasa con cobertura de tipos al 100%.
- La suite completa pasa; cualquier fallo se reporta con su salida en lugar de silenciarse.

**writeScope:**
- Ninguno (solo verificación; corrige archivos de tareas previas)

**validateCommand:** `rtk composer rector-dry && rtk composer phpstan && rtk php artisan test --compact`

**Review:** Claude

## Failure Modes

- Una clave sensible llega a `properties`: `ActivityLogger` lanza excepción y la prueba de privacidad lo detecta.
- El paquete asigna como causer a un usuario de otro guard: se evita fijando siempre el causer o `causedByAnonymous()`.
- Falla el registro dentro de una transacción de Admin o TrustedDevice: se revierte también el cambio, y no queda una auditoría parcial.
- Un comentario borrado definitivamente deja `subject_id` sin modelo: es esperado y documentado; el tombstone sigue resolviendo.
- Un script o despliegue llama a un comando Admin sin `--actor`: el comando falla sin cambiar nada; la documentación se actualiza en TASK-026.
- El volumen de likes crece: la retención corta de `likes` y el índice `(log_name, created_at)` mantienen la purga barata.

## Ship Cut

Infraestructura (TASK-001 a TASK-006) más auth (TASK-007 a TASK-009) y la auditoría Admin (TASK-020 a TASK-023) ya cumplen lo esencial de SOC-54 y desbloquean SOC-35. Contenido, likes, dispositivos y cuenta pueden cerrarse después, pero la tarea no se da por terminada sin la purga (TASK-024), la prueba de privacidad (TASK-025) y la documentación.

## Test Coverage Map

- Servicio y morph map: `ActivityLoggerTest`.
- Auth y contraseña: `AuthActivitySubscriberTest`; 2FA: `TwoFactorActivityTest`.
- Contenido: `ContentActivitySubscriberTest`; likes: `LikeActivitySubscriberTest`; dispositivos: `TrustedDeviceActivityMirrorTest`; cuenta: `AccountActivitySubscriberTest`.
- Admin: `AdminUserRoleCommandTest`, `AdminRolePermissionsCommandTest`, `AdminBootstrapCommandTest`, `AdminInvitationServiceTest`.
- Purga: `ActivityLogPurgeCommandTest`; privacidad y frontera del paquete: `ActivityLogPrivacyTest`.
- Regresión: suites existentes de Password, Login, Post, Comment, Like, TrustedDevice, AccountSettings y Admin.

## Execution Summary

- Tareas: 28.
- Capas paralelizables: L0: TASK-001, TASK-003, TASK-006, TASK-007, TASK-010, TASK-011, TASK-014, TASK-017; L1: TASK-002, TASK-004, TASK-012, TASK-018, TASK-027; L2: TASK-005; L3: TASK-008, TASK-020, TASK-023; L4: TASK-009, TASK-013, TASK-021, TASK-022; L5: TASK-015; L6: TASK-016; L7: TASK-019; L8: TASK-024, TASK-025; L9: TASK-026; L10: TASK-028.
- Ruta crítica (11 tareas): TASK-001 → TASK-002 → TASK-005 → TASK-008 → TASK-013 → TASK-015 → TASK-016 → TASK-019 → TASK-024 → TASK-026 → TASK-028.
- Próxima ejecución: implementar solo la siguiente tarea atómica y detenerse para revisión.

## Task Dependencies

- **TASK-001** ← sin dependencias
- **TASK-002** ← TASK-001
- **TASK-003** ← sin dependencias
- **TASK-004** ← TASK-001, TASK-003
- **TASK-005** ← TASK-002, TASK-004
- **TASK-006** ← sin dependencias
- **TASK-007** ← sin dependencias
- **TASK-008** ← TASK-004, TASK-005, TASK-007
- **TASK-009** ← TASK-008
- **TASK-010** ← sin dependencias
- **TASK-011** ← sin dependencias
- **TASK-012** ← TASK-011
- **TASK-013** ← TASK-008, TASK-010, TASK-012
- **TASK-014** ← sin dependencias
- **TASK-015** ← TASK-013, TASK-014
- **TASK-016** ← TASK-006, TASK-015
- **TASK-017** ← sin dependencias
- **TASK-018** ← TASK-017
- **TASK-019** ← TASK-016, TASK-018
- **TASK-020** ← TASK-004, TASK-005
- **TASK-021** ← TASK-006, TASK-020
- **TASK-022** ← TASK-020
- **TASK-023** ← TASK-004, TASK-005, TASK-006
- **TASK-024** ← TASK-002, TASK-019
- **TASK-025** ← TASK-009, TASK-013, TASK-015, TASK-016, TASK-019, TASK-021, TASK-022, TASK-023
- **TASK-026** ← TASK-024, TASK-025
- **TASK-027** ← TASK-003
- **TASK-028** ← TASK-026, TASK-027
