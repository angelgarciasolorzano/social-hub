# Plan: SOC-83 — Rebuild the Friendship domain foundation in app/Modules

**Linear:** SOC-83 (épica SOC-82) · **Modo:** EXPANSION · **Revisión por defecto:** claude

## Overview

Mover el módulo de amistades de `app/Friendship/` (arquitectura vieja) a `app/Modules/Friendship/` y reconstruirlo:

* **Modelo de datos:** una fila por par de usuarios, garantizada por la base de datos.
* **Escritura:** un servicio como único escritor, con bloqueos.
* **Autorización y entrada:** una policy y endpoints JSON con throttle propio.
* **Búsqueda:** sin fuga de datos.
* **Extras de EXPANSION:** espera tras un rechazo, límite de solicitudes pendientes, amigos en común, contadores precargados en `User` y un enum de resultados con su código HTTP.

Se corrigen el bug de `sendRequest()`, que bloquea a un receptor para todos, y la fuga del email en `search()`. `HomeSearchHeader` se adapta lo mínimo para usar los endpoints nuevos; la UI completa es SOC-84.

## Scope Challenge

**Qué existe hoy:**

- Tabla `friendships` (`requester_id`, `receiver_id`, `status`, timestamps), creada por `database/migrations/2025_09_26_144358_create_friendships_table.php`.
- `app/Friendship/` con un controlador (`sendRequest` con el bug, `search` que filtra modelos `User` completos por flash), el modelo, el enum `FriendshipStatus` (casos en MAYÚSCULAS) y los providers registrados en `bootstrap/providers.php`.
- `database/factories/FriendshipFactory.php`, con `definition()` vacío.
- `HomeSearchHeader.tsx`, que usa las acciones de Wayfinder `search` y `sendRequest` y lee la prop flash `search_results`, tipada en `shared/types/index.d.ts`.

**Qué se reutiliza:**

- El patrón del módulo Like: providers, `loadMigrationsFrom`, una migración movida con su nombre original, el índice único con `UniqueConstraintViolationException` tratada como 'ya existe', y `throttle:N,1,<nombre de ruta>`.
- El patrón de `AccountStatusService` y `TrustedDeviceService`: un servicio con `DB::transaction` y `lockForUpdate`, que no autoriza.
- `TrustedDeviceOperationResult` como modelo del enum de resultados que se convierte en respuesta.
- `#[UsePolicy]` en el modelo y `#[Authorize('<ability>', '<parámetro>')]` en el controlador (`TrustedDeviceController`).
- La configuración de módulo con `mergeConfigFrom` en `module.<nombre>` (ActivityLog), listada en `configDirectories` de `phpstan.neon`.
- `SkewedRandom` y `WeightedRandom` (`app/Support/Seeding/`) para el seeder.
- `translationFilesIn()`, `translationFileTexts()` y `translationPlaceholders()` de `tests/Pest.php` para la prueba de paridad de traducciones.
- `createUser()` y `createSuspendedUser()` de `tests/Pest.php`.
- `TranslatedText::of()` para los mensajes.

**Qué es nuevo:**

- Columnas generadas `user_low_id` y `user_high_id` (`LEAST`/`GREATEST`) con índice único: una fila por par sin importar quién envió.
- La tabla `friend_request_declines`, que guarda los rechazos para la espera.
- Los enums `FriendshipState` (estado visto por quien consulta) y `FriendshipOperationResult`.
- `FriendshipService`, `FriendshipSearchService`, `FriendshipPolicy` y los eventos de dominio.
- Endpoints JSON de acciones y de búsqueda.
- Relaciones y contadores de amistad en `User`.
- El archivo de configuración del módulo, el seeder, las pruebas y la documentación.

**Decisiones confirmadas:**

- Una fila por par, confirmado por el usuario: se conservan `requester_id` y `receiver_id` y se agregan las columnas generadas almacenadas `user_low_id`/`user_high_id` con índice único. La base de datos impide A→B y B→A sin que el código tenga que ordenar el par.
- Solicitud cruzada, confirmado por el usuario: si B ya le envió una solicitud a A y A intenta enviarle otra, se **rechaza con un aviso** (`IncomingRequestExists`, 409) que le dice a A que la acepte desde sus solicitudes recibidas. No se acepta sola.
- Rechazar, cancelar y eliminar amigo borran la fila de `friendships`. Desaparece el estado `declined`.
- **Espera tras un rechazo (EXPANSION):** solo un rechazo crea o actualiza una fila de `friend_request_declines`. Mientras no pasen `module.friendship.decline_cooldown_days` días (por defecto 7), quien envió no puede volver a enviarle a quien rechazó, pero quien rechazó sí puede enviarle a él. Cancelar o eliminar amigo no genera espera. El mensaje es neutro, para no revelar que hubo un rechazo.
- **Límite de pendientes (EXPANSION):** como máximo `module.friendship.max_pending_sent` solicitudes enviadas sin responder (por defecto 50), contado con la fila del emisor bloqueada.
- **Amigos en común y estado de amistad (EXPANSION):** la búsqueda los precarga en la misma consulta.
- **Contadores en `User` (EXPANSION):** `friendsCount()` y `pendingReceivedFriendRequestsCount()` reutilizan los valores precargados por el scope `withFriendshipCounts()`.
- **Relevante agregado por mí:** la columna `accepted_at` (fecha desde la que son amigos), el enum `FriendshipOperationResult` (resultado, código HTTP y clave de traducción en un solo lugar) y el estado `FriendshipState` en la búsqueda, que necesita el botón de SOC-84.
- Transporte: los endpoints son JSON (como Like) para que SOC-84 los consuma con `useHttp` y actualización optimista. La búsqueda es un GET JSON con `cursorPaginate`, no una prop flash ni una prop de una página concreta: el diálogo de búsqueda vive en el header y no debe depender de la página anfitriona.
- Los eventos son `FriendRequestSent`, `FriendRequestAccepted` y `FriendshipEnded`. Este último lleva como motivo `FriendshipOperationResult::Declined|Canceled|Removed`, así SOC-85 distingue los tres casos con un solo evento. Se despachan dentro de la transacción del servicio.

**¿Ejecución directa?** No: son unos 25 archivos entre backend, migraciones, pruebas y frontend, con decisiones de concurrencia. Se planifica.

## Prerequisites

- MySQL en desarrollo y en pruebas (`.env.testing` usa `DB_CONNECTION=mysql`): las columnas generadas almacenadas con `LEAST`/`GREATEST` dependen de ello.
- SOC-68 está mergeada: el módulo Notification existe, pero en esta tarea no se toca.
- La tabla `friendships` nunca estuvo en producción: la migración de reconstrucción puede vaciar las filas locales existentes.

## Non-Goals

- La UI nueva en React: página de amigos, botón de amistad en el perfil y contador en la navegación (SOC-84).
- Notificaciones, el activity log y la clave `friendship` en el morph map (SOC-85).
- El feed de amigos (SOC-86).
- El panel de Filament (SOC-87).
- Bloquear usuarios, sugerencias de personas y privacidad por publicación.
- Traducir `HomeSearchHeader`: el módulo `home` sigue en español hasta su propia tarea de idiomas.

## Contracts

### Esquema de friendships

`friendships`: `id`, `requester_id`, `receiver_id` (ambas FK a `users`, `cascadeOnDelete`), `status` (`pending|accepted`), `accepted_at` (nullable), `user_low_id` y `user_high_id` (`storedAs` `LEAST`/`GREATEST`), único `friendships_user_low_id_user_high_id_unique`, e índices `(receiver_id, status)` y `(requester_id, status)`. Una fila con `requester_id = receiver_id` nunca se escribe: la policy y el servicio lo impiden.

### Esquema de friend_request_declines

`id`, `requester_id` (quien envió), `receiver_id` (quien rechazó), ambas FK con `cascadeOnDelete`, `declined_at` (immutable datetime), único `(requester_id, receiver_id)`. Un rechazo nuevo del mismo par actualiza `declined_at`.

### Rutas (auth + verified)

* `GET friendships/search` (`friendships.search`, `throttle:60,1,friendships.search`).
* `POST friendships/{user}` (`friendships.store`, `throttle:20,1,friendships.store`).
* `POST friendships/{friendship}/accept` (`friendships.accept`).
* `DELETE friendships/{friendship}/decline` (`friendships.decline`).
* `DELETE friendships/{friendship}/cancel` (`friendships.cancel`).
* `DELETE friendships/{friendship}` (`friendships.destroy`, eliminar amigo).

Las cuatro últimas llevan `throttle:30,1,<nombre de ruta>`.

### Respuesta de las acciones

JSON `{result: string, state: FriendshipState, message: string}`. El código HTTP sale de `FriendshipOperationResult::httpStatus()`: 200 si tuvo éxito y 409 si se rechazó por una regla de negocio (`AlreadyPending`, `AlreadyFriends`, `IncomingRequestExists`, `CooldownActive`, `PendingLimitReached`, `ReceiverUnavailable`, `NoLongerPending`). Además: 403 si la policy lo niega (antes de validar), 404 si el modelo no existe y 429 por throttle.

### Respuesta de la búsqueda

JSON con el envoltorio de `CursorPaginator`: `data`, `path`, `per_page`, `next_cursor`, `next_page_url`, `prev_cursor`, `prev_page_url`. Cada elemento es `{id, name, profilePicture, friendshipState, friendshipId, mutualFriendsCount}`, nunca el email. `q` es obligatorio (entre 2 y 50 caracteres); si no, 422. Se excluyen el propio usuario y las cuentas suspendidas.

### Eventos de dominio

* `FriendRequestSent(Friendship $friendship)`.
* `FriendRequestAccepted(Friendship $friendship)`.
* `FriendshipEnded(int $friendshipId, User $actor, User $otherUser, FriendshipOperationResult $reason)`.

Se despachan dentro de la transacción del servicio y solo cuando hubo un cambio real. Quien los consuma en cola (las notificaciones de SOC-85) debe usar `afterCommit`.

### Configuración

`app/Modules/Friendship/config/friendship.php`, mezclada como `module.friendship`: `decline_cooldown_days` (`SOCIALHUB_FRIENDSHIP_DECLINE_COOLDOWN_DAYS`, 7), `max_pending_sent` (`SOCIALHUB_FRIENDSHIP_MAX_PENDING_SENT`, 50) y `search_page_size` (15).

## Existing Code Leverage

- `app/Modules/Like/Providers/LikeServiceProvider.php` y `LikeRouteServiceProvider.php`: forma de los providers.
- `app/Modules/Like/Database/Migrations/2026_10_15_221529_add_unique_user_likeable_index_to_likes_table.php`: índice único con nombre en una constante.
- `app/Modules/Like/Controllers/LikeController.php`: respuesta JSON y captura de `UniqueConstraintViolationException`.
- `app/Modules/ActivityLog/Providers/ActivityLogServiceProvider.php` y `config/activity-log.php`: `mergeConfigFrom` y valores leídos de variables de entorno.
- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php`: `#[Authorize]`.
- `app/User/Modules/AccountStatus/Services/AccountStatusService.php`: transacción con `lockForUpdate` y servicio que no autoriza.
- `app/Modules/Post/Resources/PostResource.php`: foto de perfil con `UserImageType::PROFILE_PICTURE`.
- `tests/Feature/Translation/SettingTranslationKeysTest.php`: forma de la prueba de paridad.
- `app/Modules/Like/Tests/Models/LikeUniquenessTest.php` y `Tests/Security/LikeThrottleTest.php`: pruebas de unicidad y de throttle.

## Tasks

### TASK-001: Scaffold the Friendship module providers and routes file

Crear `FriendshipServiceProvider` y `FriendshipRouteServiceProvider` en el módulo nuevo y un `routes/routes.php` con un grupo `auth`+`verified` todavía vacío.

El ServiceProvider hace tres cosas, como Like y ActivityLog:
* en `register()`, mezcla `config/friendship.php` como `module.friendship` y registra el RouteServiceProvider;
* en `boot()`, llama a `loadMigrationsFrom(__DIR__.'/../Database/Migrations')`.

Los providers viejos siguen registrados hasta TASK-018, así que las rutas viejas siguen respondiendo mientras tanto.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Providers/FriendshipServiceProvider.php`
- `app/Modules/Friendship/Providers/FriendshipRouteServiceProvider.php`
- `app/Modules/Friendship/routes/routes.php`

**Acceptance Criteria:**

1. Ambos providers existen en `App\Modules\Friendship\Providers`, con `declare(strict_types=1)` y `#[Override]`, siguiendo la forma de `LikeServiceProvider` y `LikeRouteServiceProvider`.
2. Ni el archivo de rutas ni los providers declaran rutas con nombres que choquen con `friends.search` o `friends.send`: las rutas viejas siguen registradas y sin cambios hasta TASK-018.
3. PHPStan no reporta errores en los archivos nuevos (la config referenciada se crea en TASK-002, que es su dependiente).

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Friendship/Providers app/Modules/Friendship/routes --memory-limit=2G`

### TASK-002: Add the module config and register the provider

Crear `config/friendship.php` con `decline_cooldown_days`, `max_pending_sent` y `search_page_size`, cada valor leído de su variable de entorno y convertido a `int`.

Registrar `App\Modules\Friendship\Providers\FriendshipServiceProvider` en `bootstrap/providers.php`, junto al viejo (que se elimina en TASK-018), y agregar `app/Modules/Friendship/config` a `configDirectories` de `phpstan.neon`.

**Type:** infra  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** TASK-001  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/config/friendship.php`
- `bootstrap/providers.php`
- `phpstan.neon`

**Acceptance Criteria:**

1. `php artisan config:show module.friendship` muestra `decline_cooldown_days: 7`, `max_pending_sent: 50` y `search_page_size: 15`.
2. Con `SOCIALHUB_FRIENDSHIP_DECLINE_COOLDOWN_DAYS=0`, el valor leído es el entero `0` y no la cadena `'0'`: los valores siempre se convierten a `int`.
3. `composer phpstan` acepta el `env()` del archivo de configuración del módulo.

**validateCommand:** `php artisan config:show module.friendship && composer phpstan`

### TASK-003: Move the friendships migration and rebuild the schema for one row per pair

Tres cambios en la base de datos:

* Mover `database/migrations/2025_09_26_144358_create_friendships_table.php` a `app/Modules/Friendship/Database/Migrations/` **con el mismo nombre** (`git mv`), para que una base que ya la corrió no la ejecute de nuevo.
* Crear una migración de reconstrucción que vacía la tabla (el módulo nunca estuvo en producción) y agrega:
  * `accepted_at`;
  * `user_low_id` y `user_high_id` como columnas `storedAs('LEAST(requester_id, receiver_id)')` y `storedAs('GREATEST(requester_id, receiver_id)')`;
  * el índice único `friendships_user_low_id_user_high_id_unique`;
  * los índices `(receiver_id, status)` y `(requester_id, status)`.

  Su `down()` revierte todo.
* Crear la migración de `friend_request_declines` según el contrato.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** TASK-001  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Database/Migrations/2026_11_18_000000_rebuild_friendships_for_unique_pairs.php`
- `app/Modules/Friendship/Database/Migrations/2026_11_18_000001_create_friend_request_declines_table.php`
- `app/Modules/Friendship/Database/Migrations/2025_09_26_144358_create_friendships_table.php (moved from database/migrations/)`

**Acceptance Criteria:**

1. `php artisan migrate:fresh` crea `friendships` con las columnas generadas y el índice único, y también `friend_request_declines`. `php artisan migrate:status` lista la migración original con su nombre de siempre.
2. Un insert crudo de `(requester 1, receiver 2)` seguido de `(requester 2, receiver 1)` falla con una violación de unicidad: se verifica en TASK-008.
3. `php artisan migrate:rollback --step=2` revierte ambas migraciones nuevas sin errores y deja la tabla `friendships` con su forma original.

**validateCommand:** `php artisan migrate:fresh --env=testing --force && php artisan migrate:rollback --step=2 --env=testing --force && php artisan migrate --env=testing --force`

### TASK-004: Create the friendship enums

Crear tres enums:

* **`FriendshipStatus`:** `Pending`, `Accepted`, en PascalCase y con los mismos valores en minúsculas.
* **`FriendshipState`:** el estado visto por quien consulta: `None`, `RequestSent`, `RequestReceived`, `Friends`. Tiene `forViewer(?Friendship, User $viewer)`.
* **`FriendshipOperationResult`:** `Sent`, `Accepted`, `Declined`, `Canceled`, `Removed`, `AlreadyPending`, `AlreadyFriends`, `IncomingRequestExists`, `CooldownActive`, `PendingLimitReached`, `ReceiverUnavailable`, `NoLongerPending`. Sus métodos son:
  * `isSuccessful()`;
  * `httpStatus()`: 200 si tuvo éxito, 409 si no;
  * `messageKey()`: `friendship/actions.results.<valor>`;
  * `message()` con `TranslatedText::of()`.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** TASK-001  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Enums/FriendshipStatus.php`
- `app/Modules/Friendship/Enums/FriendshipState.php`
- `app/Modules/Friendship/Enums/FriendshipOperationResult.php`

**Acceptance Criteria:**

1. `FriendshipStatus` tiene solo `Pending='pending'` y `Accepted='accepted'`: no existe `Declined`.
2. `FriendshipState::forViewer(null, $viewer)` es `None`. Para una pendiente devuelve `RequestSent` si el viewer es el emisor y `RequestReceived` si es el receptor. Para una aceptada devuelve `Friends` en ambos casos.
3. Todo caso de éxito devuelve 200 en `httpStatus()` y todo caso de rechazo devuelve 409 (se prueba con un dataset en TASK-005).

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Friendship/Enums --memory-limit=2G`

### TASK-005: Add the friendship action messages in English and Spanish with a parity test

Crear `lang/{en,es}/friendship/actions.php` con un texto por cada caso de `FriendshipOperationResult` (`results.<valor>`) y los mensajes de validación de la búsqueda.

Los textos de `CooldownActive` y `ReceiverUnavailable` son neutros: no revelan un rechazo ni una suspensión. `IncomingRequestExists` invita a aceptar la solicitud desde las recibidas.

La prueba del módulo comprueba:
* que haya las mismas claves en `en` y `es`;
* que no haya textos vacíos;
* que los placeholders coincidan;
* que todo caso del enum tenga su texto en ambos idiomas.

Para ello reutiliza `translationFilesIn()` y `translationFileTexts()`, más un dataset de `httpStatus()`.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-004, TASK-007  
**Review:** claude

**writeScope:**

- `lang/en/friendship/actions.php`
- `lang/es/friendship/actions.php`
- `app/Modules/Friendship/Tests/Translations/FriendshipTranslationKeysTest.php`

**Acceptance Criteria:**

1. Por cada caso de `FriendshipOperationResult`, `translatedTextIn(PreferredLocale::En|Es, $case->messageKey())` devuelve un texto que no es la clave.
2. La prueba falla si a `lang/es/friendship/actions.php` le falta una clave que existe en `en` o si los placeholders difieren.
3. Ningún texto de `results.cooldown_active` ni de `results.receiver_unavailable` contiene 'rechaz', 'declin' ni 'suspend'.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Translations`

### TASK-006: Create the Friendship and FriendRequestDecline models and the Friendship factory

**`Models/Friendship`:**
* casts: `status`, `accepted_at` immutable y los ids;
* `#[Fillable]` solo `requester_id`, `receiver_id`, `status` y `accepted_at`, nunca las columnas generadas;
* `#[UseFactory]`;
* relaciones `requester()` y `receiver()`;
* scopes `#[Scope]`: `involving(User)`, `between(User, User)` (vía `user_low_id`/`user_high_id`), `pending()` y `accepted()`;
* helpers `isPending()`, `isAccepted()` y `otherUser(User)`.

**`Models/FriendRequestDecline`:** casts, relaciones `requester()`/`receiver()`, scope `activeFor(User $requester, User $receiver, int $cooldownDays)` y `#[UseFactory]` (su factory se crea en TASK-008).

**`Database/Factories/FriendshipFactory`:** completo, con los estados `pending()`, `accepted()` (pone `accepted_at`) y `between(User $requester, User $receiver)`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** TASK-003, TASK-004  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Models/Friendship.php`
- `app/Modules/Friendship/Models/FriendRequestDecline.php`
- `app/Modules/Friendship/Database/Factories/FriendshipFactory.php`

**Acceptance Criteria:**

1. `Friendship::query()->between($a, $b)` y `between($b, $a)` encuentran la misma fila (se prueba en TASK-008).
2. Asignar `user_low_id` en masa no es posible porque no está en `#[Fillable]`: la columna generada solo la escribe la base de datos.
3. La factory por defecto crea dos usuarios distintos y una fila `pending` sin `accepted_at`; `accepted()` siempre pone `accepted_at`.

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Friendship/Models app/Modules/Friendship/Database/Factories --memory-limit=2G`

### TASK-007: Register the Friendship test suite and shared test helpers

Registrar `app/Modules/Friendship/Tests` como suite `Friendship` en `phpunit.xml` y en el `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in(...)` de `tests/Pest.php`.

Agregar a `tests/Pest.php` los helpers que comparten las pruebas del módulo:
* `createFriendRequest(?User $requester = null, ?User $receiver = null): Friendship`;
* `createFriendship(?User $first = null, ?User $second = null): Friendship` (aceptada).

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** TASK-006  
**Review:** claude

**writeScope:**

- `phpunit.xml`
- `tests/Pest.php`

**Acceptance Criteria:**

1. `php artisan test --list-suites` (o la suite `Friendship` en `phpunit.xml`) apunta a `app/Modules/Friendship/Tests`, y las pruebas del módulo corren con `RefreshDatabase`.
2. Los helpers devuelven filas persistidas con el estado correcto, y `createFriendship()` siempre pone `accepted_at`.
3. No se rompe ninguna prueba existente que use `tests/Pest.php` (se corre una suite vecina, Like).

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Like/Tests`

### TASK-008: Add the decline factory and model tests for pair uniqueness and scopes

Crear `FriendRequestDeclineFactory` completo (`requester_id`, `receiver_id`, `declined_at`, con un estado `declinedDaysAgo(int)`).

Pruebas de modelos:
* `friendships` admite una sola fila por par en cualquier dirección: el insert cruzado lanza `UniqueConstraintViolationException`;
* las columnas generadas se calculan solas;
* los scopes `between`, `involving`, `pending` y `accepted`, y `otherUser()`;
* `FriendRequestDecline::activeFor()` respeta los días de espera, en los dos lados del límite;
* borrar un usuario borra sus filas en cascada.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-006, TASK-007  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Database/Factories/FriendRequestDeclineFactory.php`
- `app/Modules/Friendship/Tests/Models/FriendshipModelTest.php`
- `app/Modules/Friendship/Tests/Models/FriendRequestDeclineModelTest.php`

**Acceptance Criteria:**

1. Crear A→B y luego B→A lanza `UniqueConstraintViolationException`, y la tabla queda con una sola fila.
2. Con una espera de 7 días, un rechazo de hace 6 días está activo y uno de hace 8 no. Un rechazo de B hacia A no afecta a A hacia B.
3. Borrar al usuario A elimina en cascada sus amistades y sus rechazos, en los dos roles.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Models`

### TASK-009: Add friendship relations and preloaded counters to User

En `App\User\Models\User`, agregar:
* las relaciones `sentFriendRequests()` y `receivedFriendRequests()` (`HasMany` pendientes);
* `friendshipsAsRequester()`/`friendshipsAsReceiver()` si hacen falta para los conteos;
* `friends(): Builder<User>`, que son los usuarios del otro lado de las amistades aceptadas, mediante una subconsulta y sin cargar ids en PHP;
* `friendshipWith(User): ?Friendship`;
* el scope `withFriendshipCounts()`, que precarga `friends_count` y `pending_received_friend_requests_count` con subconsultas;
* `friendsCount()` y `pendingReceivedFriendRequestsCount()`, que reutilizan esos valores y solo consultan si no están precargados.

Después, regenerar `_ide_helper_models.php` con `composer ide-helper` y revertir los cambios ajenos que introduzca.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-006, TASK-007  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Tests/Models/UserFriendshipRelationsTest.php`
- `app/User/Models/User.php`
- `_ide_helper_models.php`

**Acceptance Criteria:**

1. `$user->friends()->pluck('id')` devuelve los amigos aceptados sin importar quién envió la solicitud, y excluye las pendientes.
2. Con `User::query()->withFriendshipCounts()` sobre 5 usuarios, llamar a `friendsCount()` en todos no ejecuta consultas extra: se mide con `DB::enableQueryLog()`.
3. `friendshipWith()` devuelve `null` entre desconocidos y la misma fila desde ambos lados.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Models/UserFriendshipRelationsTest.php && composer phpstan`

### TASK-010: Create the friendship domain events

Crear `Events/FriendRequestSent`, `Events/FriendRequestAccepted` y `Events/FriendshipEnded` según el contrato de eventos, con `Dispatchable` y propiedades `readonly`.

`FriendshipEnded` guarda el id de la fila y no el modelo, porque la fila ya se borró cuando se despacha.

En esta tarea nadie los escucha: los consumen SOC-85 (notificaciones y activity log).

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-004, TASK-006  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Events/FriendRequestSent.php`
- `app/Modules/Friendship/Events/FriendRequestAccepted.php`
- `app/Modules/Friendship/Events/FriendshipEnded.php`

**Acceptance Criteria:**

1. `FriendshipEnded` acepta solo `Declined`, `Canceled` o `Removed` como motivo: cualquier otro caso lanza `InvalidArgumentException` en el constructor.
2. Ningún evento lleva el email ni texto libre: solo modelos, ids y el enum.
3. PHPStan no reporta errores en los eventos.

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Friendship/Events --memory-limit=2G`

### TASK-011: Implement FriendshipService as the only writer, with locks, cooldown and pending limit

`FriendshipService` (`final readonly`) tiene cinco operaciones:
* `send(User $requester, User $receiver)`;
* `accept(Friendship, User $actor)`;
* `decline(Friendship, User $actor)`;
* `cancel(Friendship, User $actor)`;
* `remove(Friendship, User $actor)`.

Todas devuelven un `FriendshipOperationResult`, corren en `DB::transaction` y vuelven a leer la fila con `lockForUpdate`. Si la fila desapareció o cambió de estado, devuelven `NoLongerPending`.

**`send()`:**
* bloquea la fila del emisor;
* rechaza a un receptor suspendido (`ReceiverUnavailable`);
* si existe una fila del par, devuelve `AlreadyFriends`, `AlreadyPending` o `IncomingRequestExists`;
* aplica la espera (`CooldownActive`) y el límite de pendientes (`PendingLimitReached`);
* trata una `UniqueConstraintViolationException` como una carrera y vuelve a evaluar el estado.

**Las demás:**
* `accept()` pone `accepted_at`;
* `decline()` borra la fila y hace upsert en `friend_request_declines`;
* `cancel()` y `remove()` borran la fila.

Cada cambio real despacha su evento. El servicio no autoriza; un actor que no corresponde es una `LogicException`, porque la policy ya lo filtró antes.

**Type:** feature  
**Effort:** L  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002, TASK-008, TASK-009, TASK-010  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Services/FriendshipService.php`
- `app/Modules/Friendship/Tests/Services/FriendshipServiceSendTest.php`
- `app/Modules/Friendship/Tests/Services/FriendshipServiceRespondTest.php`

**Acceptance Criteria:**

1. Corrige el bug viejo: dos emisores distintos pueden tener solicitudes pendientes hacia el mismo receptor a la vez, y un segundo envío del mismo par devuelve `AlreadyPending` sin crear filas.
2. Si B ya le envió a A, `send(A, B)` devuelve `IncomingRequestExists` y la fila de B sigue pendiente. Tras `decline()`, el reenvío dentro de la espera devuelve `CooldownActive`, después de la espera devuelve `Sent`, y el que rechazó sí puede enviar.
3. Con `max_pending_sent=2`, el tercer envío devuelve `PendingLimitReached`. Ningún resultado de rechazo despacha eventos ni escribe filas (`Event::fake` y conteo de filas).

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Services`

### TASK-012: Add FriendshipPolicy and attach it to the model

`FriendshipPolicy` tiene estas habilidades:
* `create(User $actor, User $receiver)`: no es uno mismo, el receptor no está suspendido y el actor no está suspendido;
* `accept` y `decline`: el receptor, con la solicitud pendiente;
* `cancel`: el emisor, con la solicitud pendiente;
* `delete`: cualquiera de los dos, con la amistad aceptada;
* `view`: cualquiera de los dos.

Niega explícitamente `update`, `restore` y `forceDelete`. Se asocia al modelo con `#[UsePolicy(FriendshipPolicy::class)]`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-006, TASK-007  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Policies/FriendshipPolicy.php`
- `app/Modules/Friendship/Tests/Policies/FriendshipPolicyTest.php`
- `app/Modules/Friendship/Models/Friendship.php`

**Acceptance Criteria:**

1. Un tercero ajeno al par recibe `false` en `accept`, `decline`, `cancel`, `delete` y `view`. El emisor no puede aceptar su propia solicitud y el receptor no puede cancelarla.
2. `create` niega enviarse a uno mismo y enviar a una cuenta suspendida (dataset).
3. `Gate::getPolicyFor(Friendship::class)` resuelve `FriendshipPolicy`, y `update`, `restore` y `forceDelete` son `false` para todos.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Policies`

### TASK-013: Implement the safe user search with state and mutual friends

**`Requests/FriendshipSearchRequest`** (`HasFluentRules`): `q` obligatorio, entre 2 y 50 caracteres, con mensajes de `friendship/actions`.

**`Services/FriendshipSearchService::search(User $viewer, string $term)`:**
* escapa `\`, `%` y `_` y busca con `LIKE 'term%'` por nombre;
* excluye al viewer y a las cuentas suspendidas;
* precarga con `addSelect` y subconsultas el id y el estado de la fila del par con el viewer, y el número de amigos en común;
* carga `media` de golpe;
* ordena por nombre e id, y pagina con `cursorPaginate(module.friendship.search_page_size)`.

**`Resources/FriendshipSearchResultResource`:** `{id, name, profilePicture, friendshipState, friendshipId, mutualFriendsCount}`.

**Type:** feature  
**Effort:** L  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002, TASK-005, TASK-009  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Requests/FriendshipSearchRequest.php`
- `app/Modules/Friendship/Services/FriendshipSearchService.php`
- `app/Modules/Friendship/Resources/FriendshipSearchResultResource.php`

**Acceptance Criteria:**

1. La consulta trata `%` y `_` como texto literal: buscar `a%` no devuelve 'abc' (se prueba en TASK-015).
2. El recurso nunca incluye `email`, `status` ni ningún campo fuera de los seis del contrato.
3. Serializar una página no ejecuta una consulta por usuario: el número de consultas es el mismo con 3 resultados y con 15 (se prueba en TASK-015).

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Friendship/Requests app/Modules/Friendship/Services app/Modules/Friendship/Resources --memory-limit=2G`

### TASK-014: Add the FriendshipController, routes and action endpoint tests

**`FriendshipController`**, con una acción por método:
* `search`: responde el `CursorPaginator` pasado por el recurso con `through()`;
* `store(User $user)`: `#[Authorize('create', [Friendship::class, 'user'])]`. Antes de usar el atributo, verificar con search-docs que acepta la clase y el parámetro de ruta; si no, usar `Gate::authorize`;
* `accept`, `decline`, `cancel` y `destroy`: cada uno con `#[Authorize('<habilidad>', 'friendship')]`.

Todas las acciones responden `{result, state, message}` con `httpStatus()`. Las rutas siguen el contrato, con nombres y throttles con prefijo.

Las pruebas Crud cubren el camino feliz y los 409 de cada acción a través de HTTP.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-011, TASK-012, TASK-013  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Controllers/FriendshipController.php`
- `app/Modules/Friendship/Tests/Crud/FriendshipActionsTest.php`
- `app/Modules/Friendship/routes/routes.php`

**Acceptance Criteria:**

1. `POST friendships/{user}` responde 200 `{result: 'sent', state: 'request_sent', message}`. Un segundo envío responde 409 `already_pending`, y el envío cruzado responde 409 `incoming_request_exists` con el mensaje en el idioma del usuario.
2. Aceptar, rechazar, cancelar y eliminar responden 200 con el estado resultante (`friends` o `none`). Repetir la acción sobre una fila ya borrada responde 404.
3. `php artisan route:list --name=friendships` muestra exactamente las seis rutas del contrato, cada una con `auth`, `verified` y su throttle con prefijo.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Crud/FriendshipActionsTest.php`

### TASK-015: Add search and security regression tests

**`Tests/Crud/FriendshipSearchTest`:**
* forma exacta de cada elemento, sin `email`;
* `friendshipState` y `friendshipId` correctos en los cuatro estados;
* `mutualFriendsCount`;
* exclusión de cuentas suspendidas y del propio usuario;
* comodines `%` y `_` como texto literal;
* paginación por cursor;
* 422 si `q` falta o tiene menos de 2 caracteres.

**`Tests/Security/FriendshipSecurityTest`:**
* un extraño recibe 403 en accept, decline, cancel y destroy **aunque mande una entrada inválida**;
* un invitado es redirigido al login;
* los throttles responden 429 de forma independiente por ruta;
* la búsqueda hace el mismo número de consultas con 3 y con 15 resultados.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-014  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Tests/Crud/FriendshipSearchTest.php`
- `app/Modules/Friendship/Tests/Security/FriendshipSecurityTest.php`

**Acceptance Criteria:**

1. Ninguna respuesta de la búsqueda contiene la clave `email` ni el email de ningún usuario creado en la prueba (`assertDontSee`).
2. Agotar `friendships.store` (20) no afecta al budget de `friendships.accept`: cada ruta responde 429 por separado.
3. El número de consultas de la búsqueda es igual para 3 y para 15 resultados con amigos en común y estados mezclados.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Crud/FriendshipSearchTest.php app/Modules/Friendship/Tests/Security`

### TASK-016: Add FriendshipSeeder and run it after AccountStatusSeeder

`FriendshipSeeder` usa cantidades sesgadas con `SkewedRandom`:
* por usuario, entre 0 y 25 amistades aceptadas (skew 2) y entre 0 y 5 solicitudes pendientes, con fechas creíbles dentro de los últimos 60 días (`accepted_at` posterior a `created_at`);
* algunos rechazos recientes y antiguos en `friend_request_declines`;
* Angel (`UserSeeder::TEST_USER_EMAIL`) tiene amigos, solicitudes recibidas y solicitudes enviadas;
* nunca crea un par repetido (`insertOrIgnore` sobre el índice único) ni una relación con uno mismo;
* no hace nada si `friendships` ya tiene filas.

Se registra en `DatabaseSeeder` después de `AccountStatusSeeder` y antes de `AnnouncementSeeder`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-008, TASK-009  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Database/Seeders/FriendshipSeeder.php`
- `app/Modules/Friendship/Tests/Seeders/FriendshipSeederTest.php`
- `database/seeders/DatabaseSeeder.php`

**Acceptance Criteria:**

1. Tras sembrar, no existen dos filas del mismo par ni filas con `requester_id = receiver_id`, y toda fila aceptada tiene `accepted_at >= created_at`.
2. Angel tiene al menos un amigo, una solicitud recibida y una enviada.
3. Correr el seeder dos veces no cambia el número de filas. Las pruebas comprueban rangos y propiedades, sin depender de `mt_srand`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Seeders`

### TASK-017: Adapt HomeSearchHeader to the new JSON endpoints

Reescribir la búsqueda del diálogo de `HomeSearchHeader` sin cambiar su aspecto general:
* usa `useHttp` contra `FriendshipController.search` (Wayfinder, regenerado) con `q`;
* muestra la foto real o las iniciales con el `Avatar` de shadcn, si existe;
* el botón 'Agregar' usa `FriendshipController.store` y refleja el `state` devuelto;
* un 409 o 429 muestra el `message` del servidor como texto local, con `onHttpException` y `onNetworkError` devolviendo `false`;
* sin `console.log` ni avatar de dicebear.

Los tipos `FriendSearchResult` y `FriendshipState` (espejo del enum PHP) van en `resources/js/modules/home/types/friendSearch.ts`. Se quita `search_results` de `SharedData`.

Los textos siguen en español a mano: el módulo `home` no está traducido (fuera de alcance, SOC-84).

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-014  
**Review:** claude

**writeScope:**

- `resources/js/modules/home/types/friendSearch.ts`
- `resources/js/modules/home/components/header/HomeSearchHeader.tsx`
- `resources/js/shared/types/index.d.ts`

**Acceptance Criteria:**

1. No queda ninguna referencia a `search_results`, `sendRequest` ni a `App/Friendship/` en `resources/js` (`grep`).
2. Buscar devuelve resultados y 'Agregar' cambia el botón a 'Solicitud enviada'. Un 409 muestra el mensaje del servidor sin abrir el modal de error de Inertia (se verifica en el navegador).
3. Pasan `npm run types`, `npm run lint:check` y `npm run format:check`.

**validateCommand:** `php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder && npm run types && npm run lint:check && npm run format:check`

### TASK-018: Remove the old app/Friendship module and its global factory

Eliminar:
* `app/Friendship/` completo (controlador, enum, modelo, providers y rutas);
* `database/factories/FriendshipFactory.php`;
* el registro de `App\Friendship\Providers\FriendshipServiceProvider` en `bootstrap/providers.php`.

Después, regenerar Wayfinder y `_ide_helper_models.php` (revirtiendo los cambios ajenos) y comprobar que nada referencie `App\Friendship\`.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-014, TASK-016, TASK-017  
**Review:** claude

**writeScope:**

- `app/Friendship/ (deleted)`
- `database/factories/FriendshipFactory.php (deleted)`
- `bootstrap/providers.php`

**Acceptance Criteria:**

1. `grep -rn 'App\\Friendship\\' app bootstrap database tests resources/js` no devuelve nada, y `app/Friendship` no existe.
2. `php artisan route:list` ya no muestra `friends.search` ni `friends.send`; sí muestra las seis rutas `friendships.*`.
3. `composer phpstan` y la suite `Friendship` pasan sin el módulo viejo.

**validateCommand:** `composer phpstan && php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests`

### TASK-019: Document the Friendship module and update the project map

Crear `docs/architecture/modules/friendship.md`, que explica el **porqué** de cada decisión:
* una fila por par con columnas generadas, frente a dos filas u ordenar en PHP;
* bloqueos y carreras;
* el rechazo de la solicitud cruzada;
* la espera y su mensaje neutro;
* el límite de pendientes;
* el contrato JSON y los códigos HTTP;
* la búsqueda segura;
* los eventos para SOC-85 (`afterCommit`);
* el seeder y la configuración.

En `CLAUDE.md`, la fila de Friendship apunta a `app/Modules/Friendship/` y al documento nuevo. En `docs/architecture/infrastructure.md`, se actualiza el orden de seeders.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Depends on:** TASK-018  
**Review:** claude

**writeScope:**

- `docs/architecture/modules/friendship.md`
- `CLAUDE.md`
- `docs/architecture/infrastructure.md`

**Acceptance Criteria:**

1. `CLAUDE.md` ya no menciona `app/Friendship/` como ruta del módulo, y su fila enlaza a `docs/architecture/modules/friendship.md`.
2. El documento nombra cada tabla, ruta, evento, variable de configuración y resultado tal como existen en el código (contrastado con `grep`).
3. El orden de seeders documentado coincide con `database/seeders/DatabaseSeeder.php`.

**validateCommand:** `grep -n 'app/Modules/Friendship' CLAUDE.md && test -f docs/architecture/modules/friendship.md`

### TASK-020: Run the full backend and frontend quality gates

Ejecutar, en este orden:
1. `vendor/bin/pint --dirty --format agent`;
2. `composer phpstan`;
3. `composer rector-clear-cache` → `composer rector-dry` → `composer rector`, sobre todo el proyecto;
4. `composer test --recreate-databases` (hubo migraciones nuevas);
5. `composer doctor`;
6. `npm run format:check`, `lint:check`, `types`, `build` y `build:ssr`.

Corregir lo que salga sin agregar alcance nuevo.

**Type:** chore  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P3  
**Depends on:** TASK-005, TASK-015, TASK-019  
**Review:** claude

**writeScope:**

- (sin archivos: verificación)

**Acceptance Criteria:**

1. Pint, PHPStan (max) y `composer rector-dry` no reportan cambios ni errores después de aplicar Rector.
2. La suite completa en paralelo pasa con bases recreadas, y no se ejecuta a la vez que la suite de otra tarea.
3. Los dos builds de frontend compilan.

**validateCommand:** `composer phpstan && composer rector-dry && composer test -- --recreate-databases && npm run build && npm run build:ssr`

## Failure Modes

- Carrera de dos envíos simultáneos del mismo par: el índice único gana, el servicio captura `UniqueConstraintViolationException`, vuelve a evaluar y devuelve `AlreadyPending`, `IncomingRequestExists` o `AlreadyFriends`, sin errores 500.
- Aceptar y cancelar a la vez: ambos bloquean la fila. El segundo encuentra la fila borrada o ya aceptada y devuelve `NoLongerPending` (409); el binding previo pudo cargarla, por eso se relee con bloqueo.
- Límite de pendientes con envíos concurrentes del mismo emisor: el bloqueo de la fila del emisor serializa el conteo.
- Un receptor suspendido entre la búsqueda y el envío: la policy responde 403 o el servicio devuelve `ReceiverUnavailable`.
- Usuario borrado: `cascadeOnDelete` limpia sus amistades y rechazos. Los eventos ya despachados no dependen de la fila.
- Migración de reconstrucción en una base local con datos viejos: vacía `friendships` a propósito, ya que nunca hubo datos en producción. Está documentado.
- El frontend viejo queda roto entre TASK-003 y TASK-017, porque la tabla cambió y el controlador viejo sigue apuntándole: no se pausa en un PR entre esas tareas.

## Ship Cut

El mínimo entregable es TASK-001 a TASK-015 más TASK-017 y TASK-018: el dominio corregido y la búsqueda segura funcionando en el header. Si la ejecución se corta antes, no se publica un PR, porque el controlador viejo no sirve con el esquema nuevo. El seeder (TASK-016) y la documentación (TASK-019) son recortables solo si el usuario lo aprueba, porque la Definition of Done exige la documentación.

## Test Coverage Map

| Comportamiento | Tarea |
| --- | --- |
| Una fila por par, columnas generadas y cascada | TASK-008 |
| Relaciones y contadores sin N+1 | TASK-009 |
| Bug viejo, solicitud cruzada, espera, límite y eventos | TASK-011 |
| Policy: actores correctos, uno mismo, cuentas suspendidas | TASK-012 |
| Contrato JSON de acciones y rutas | TASK-014 |
| Búsqueda sin email, LIKE literal, estados, amigos en común, 422 | TASK-015 |
| 403 antes de validar, throttles independientes, consultas constantes | TASK-015 |
| Paridad de traducciones y mensajes neutros | TASK-005 |
| Seeder sin pares repetidos e idempotente | TASK-016 |
| Frontend: tipos, lint y formato, más comprobación manual en el navegador | TASK-017 |

## Execution Summary

- **Tareas:** 20
- **Capa 1:** TASK-001
- **Capa 2:** TASK-002, TASK-003, TASK-004
- **Capa 3:** TASK-006
- **Capa 4:** TASK-007, TASK-010
- **Capa 5:** TASK-005, TASK-008, TASK-009, TASK-012
- **Capa 6:** TASK-011, TASK-013, TASK-016
- **Capa 7:** TASK-014
- **Capa 8:** TASK-015, TASK-017
- **Capa 9:** TASK-018
- **Capa 10:** TASK-019
- **Capa 11:** TASK-020
- **Ruta crítica:** TASK-001 → TASK-003 → TASK-006 → TASK-007 → TASK-008 → TASK-011 → TASK-014 → TASK-017 → TASK-018 → TASK-019 → TASK-020

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-001 |
| TASK-004 | TASK-001 |
| TASK-005 | TASK-004, TASK-007 |
| TASK-006 | TASK-003, TASK-004 |
| TASK-007 | TASK-006 |
| TASK-008 | TASK-006, TASK-007 |
| TASK-009 | TASK-006, TASK-007 |
| TASK-010 | TASK-004, TASK-006 |
| TASK-011 | TASK-002, TASK-008, TASK-009, TASK-010 |
| TASK-012 | TASK-006, TASK-007 |
| TASK-013 | TASK-002, TASK-005, TASK-009 |
| TASK-014 | TASK-011, TASK-012, TASK-013 |
| TASK-015 | TASK-014 |
| TASK-016 | TASK-008, TASK-009 |
| TASK-017 | TASK-014 |
| TASK-018 | TASK-014, TASK-016, TASK-017 |
| TASK-019 | TASK-018 |
| TASK-020 | TASK-005, TASK-015, TASK-019 |
