# Plan: SOC-84 — Friends page, profile friendship button and sidebars

**Linear:** SOC-84 (épica SOC-82) · **Modo:** EXPANSION · **Revisión por defecto:** claude

## Overview

Completar la interfaz de amistades sobre la base de SOC-83. Hoy desde la búsqueda solo se puede **enviar** una solicitud; no hay dónde aceptar, rechazar, cancelar ni eliminar. Se agregan cuatro piezas:

* **Página de amigos** (`GET friendships`): pestañas Amigos, Recibidas y Enviadas en la URL, con un filtro por nombre y scroll infinito virtualizado.
* **`FriendshipButton` reutilizable**: con todos los estados, se usa en la búsqueda, la página de amigos, el perfil y el lateral derecho.
* **Perfil**: el número real de amigos, los amigos en común y el botón.
* **Laterales del `HomeLayout`**, hoy vacíos: el izquierdo pasa a ser una navegación con el contador de solicitudes recibidas, y el derecho muestra las últimas solicitudes recibidas para aceptarlas o rechazarlas ahí mismo.

## Scope Challenge

**Qué existe hoy:**

- Desde SOC-83: las acciones JSON de `FriendshipController` (`store`, `accept`, `decline`, `cancel` y `destroy`), `FriendshipPolicy`, `FriendshipSearchController` y la página `friendship/Search`.
- Desde SOC-83: `User::friends()`, `sentFriendRequests()`, `receivedFriendRequests()` y `withFriendshipCounts()`, y `FriendshipSearchResultRow`, que incluye un botón que solo sabe enviar.
- `HomeLayout` con `HomeSidebarLeft` y `HomeSidebarRight` vacíos ("Sidebar left" y "Sidebar right"), usados por Home, Perfil, Notificaciones y la búsqueda.
- `Profile.tsx` con un "344 amigos" fijo; `ProfileController::index` y `show` renderizan `profile/Profile`.
- `NotificationProps` como ejemplo de prop compartida diferida (`unreadNotificationsCount`), registrada con `Inertia::share` en `HandleInertiaRequests`. `ALWAYS_SHARED_TRANSLATION_GROUPS` solo tiene `dates`.
- Un `search_results` viejo que `HandleInertiaRequests` todavía comparte.

**Qué se reutiliza:**

- El patrón de controlador invocable para páginas (`FriendshipSearchController`, `TrustedDeviceIndexController`).
- `Inertia::scroll` + `<InfiniteScroll>` + `useVirtualizer` con `scrollMargin` de `Search.tsx`, que se extrae a un componente común.
- El escape de `LIKE` de `FriendshipSearchController`, que se extrae a un helper común.
- El hook `useFriendshipSearch` (debounce, `committedTerm`, `hasInteractedRef`) como modelo del filtro de la página de amigos.
- El patrón de `NotificationProps` para la prop compartida diferida de solicitudes.
- `FriendshipState::forViewer()` y `User::friendshipWith()` para el estado del perfil.

**Qué es nuevo:**

- `friendshipId` en las respuestas de las acciones, para que el botón pueda cancelar o eliminar justo después de enviar o aceptar.
- `FriendshipIndexController`, `FriendshipIndexRequest`, el enum `FriendshipListTab` y `FriendshipListItemResource`.
- `FriendshipProps` (prop compartida diferida `friendRequests`) y `ProfileFriendshipProps` (la amistad entre quien mira y el dueño del perfil).
- Los catálogos `friendship/button`, `friendship/friends` y `home/sidebar`.
- En React: `FriendshipButton`, el diálogo de eliminar amigo, `VirtualInfiniteList`, la página `Friends.tsx` con su hook y su fila, la navegación lateral, el panel de solicitudes y los cambios en el perfil.
- El componente `tabs` de shadcn en `shared/components/shadcn/ui/`, instalado con su CLI.

**Decisiones confirmadas:**

- **Pestañas en la URL** (decisión del usuario): `GET friendships?tab=friends|received|sent&q=…`. El servidor solo consulta la pestaña activa con `Inertia::scroll`, y la pestaña se puede compartir y recargar. Las pestañas usan el componente `Tabs` de shadcn (decisión del usuario), que se instala con su CLI en TASK-022. No suma dependencias: sale del paquete `radix-ui`, que ya está instalado. `Tabs` se controla con la pestaña de la URL (`value` + `onValueChange`) y usa `activationMode="manual"`, para que moverse con las flechas no dispare una visita por pestaña.
- **Navegación en el lateral izquierdo** (decisión del usuario): Inicio, Buscar personas, Amigos (con el contador de recibidas), Notificaciones (con el contador de no leídas) y Mi perfil, con el enlace activo resaltado.
- **Lateral derecho** (propuesto por mí): hasta 5 solicitudes recibidas, con aceptar y rechazar en línea y un enlace a "ver todas". Sin sugerencias de personas, porque la épica las excluye.
- **Extras de EXPANSION** (usuario): el número real de amigos en el perfil, los amigos en común en el perfil, el lateral como navegación y el filtro por nombre en la página de amigos.
- **Agregados por mí**: `friendshipId` en las respuestas de las acciones (sin él, el botón no puede cancelar después de enviar), la limpieza de `search_results` y la extracción de `LikePattern` y `VirtualInfiniteList`, que pasan a tener dos usos.
- **Actualización optimista**: el botón muestra el estado nuevo al instante y lo revierte si el servidor responde 409, 429 o un error de red, en cuyo caso muestra el mensaje del servidor o el genérico.
- **Cuentas suspendidas**: no aparecen en ninguna lista ni en el panel; la fila de la amistad se conserva.

**¿Ejecución directa?** No: son más de 25 archivos entre backend, frontend, pruebas y documentación.

## Prerequisites

- SOC-83 está mergeada: el módulo `app/Modules/Friendship/`, las acciones JSON, la página de búsqueda y las relaciones de `User`.
- La base de desarrollo tiene el esquema de SOC-83 (`migrate:fresh --seed`), y Angel tiene amigos y solicitudes en los dos sentidos gracias a `FriendshipSeeder`.

## Non-Goals

- Notificaciones en tiempo real de solicitudes y el activity log (SOC-85).
- El feed de amigos (SOC-86) y el panel de Filament (SOC-87).
- Sugerencias de "personas que quizá conozcas", bloquear usuarios y privacidad por publicación.
- Traducir el resto del header de `home` (la lupa, el botón de publicar y el menú del perfil), que sigue en español a mano.
- Una versión móvil de los laterales: siguen ocultos o fijos como hoy; adaptarlos es otra tarea.
- El bucle `Maximum update depth exceeded` después del login, que es preexistente y va en su propia tarea.

## Contracts

### Respuesta de las acciones

`{result, state, message, friendshipId}`. `friendshipId` es el id de la fila del par después de la acción, o `null` si ya no existe (rechazada, cancelada o eliminada). Los códigos HTTP no cambian (200, 409, 403, 404, 429).

### Página de amigos

`GET friendships` (`friendships.index`, `throttle:60,1,friendships.index`), servida por `FriendshipIndexController`, que es invocable. `tab` es opcional (`friends` por defecto; un valor desconocido da un error de validación) y `q` también (máximo 50, filtro por inicio del nombre con `LikePattern`). Renderiza `friendship/Friends` con estas props:
* `filters: {tab, q}`;
* `tabCounts: {friends, received, sent}`, sin filtro;
* `friendships`: `Inertia::scroll` de `FriendshipListItemResource`, con la forma `{friendshipId, friendshipState, since, user: {id, name, profilePicture}}`, sin email;
* `searchTermMinLength` no hace falta: el filtro funciona desde 1 carácter.

Orden: amigos por `accepted_at` descendente; solicitudes por `created_at` descendente; el id desempata.

### Prop compartida friendRequests

`FriendshipProps`, compartida con `Inertia::share` en `HandleInertiaRequests` como `NotificationProps`. Expone `friendRequests: Inertia::defer(…)` con la forma `{pendingCount, recent: FriendshipListItem[] (hasta 5, las más recientes)}`, o `null` para un invitado. Excluye remitentes suspendidos y hace un número constante de consultas.

### Prop del perfil profileFriendship

`ProfileFriendshipProps::for(User $profileUser)`, usado por `ProfileController::index` y `show`. Devuelve `profileFriendship: {friendsCount, isOwnProfile, friendshipState, friendshipId, mutualFriendsCount}`. En el perfil propio, `friendshipState` es `null` y `mutualFriendsCount` es `0`.

### Traducciones

`friendship/button`: los estados, el menú, el diálogo de eliminar y el error genérico. Se comparte siempre, porque el botón aparece en varias páginas y en el lateral.
`friendship/friends`: la página de amigos, registrada por su controlador.
`home/sidebar`: los dos laterales, compartido siempre.

`friendship/search.button.*` se elimina cuando la fila de búsqueda pasa a usar `FriendshipButton`.

## Existing Code Leverage

- `app/Modules/Friendship/Controllers/FriendshipSearchController.php`: página invocable, consulta en métodos privados, escape de `LIKE`.
- `resources/js/modules/friendship/Search.tsx` y `resources/js/modules/friendship/hooks/useFriendshipSearch.ts`: InfiniteScroll + virtualización con `scrollMargin`, término en la URL con debounce.
- `resources/js/modules/friendship/components/FriendshipSearchResultRow.tsx`: el botón actual y su manejo de 409, 429 y errores de red con `useHttp`.
- `app/Modules/Notification/Props/NotificationProps.php`: prop compartida diferida.
- `app/Http/Middleware/HandleInertiaRequests.php`: `Inertia::share`, `ALWAYS_SHARED_TRANSLATION_GROUPS`.
- `resources/js/modules/notification/components/NotificationDropdown.tsx`: badge de contador con `Deferred`.
- `app/Modules/Comment/Props/CommentThreadProps.php`: props inyectados en `ProfileController`.

## Tasks

### TASK-001: Add the shared friendship button texts in English and Spanish

Crear `lang/{en,es}/friendship/button.php` con los textos del botón reutilizable:
* los estados: Agregar amigo, Solicitud enviada, Aceptar, Rechazar, Amigos;
* el menú: Cancelar solicitud, Eliminar amigo;
* el diálogo de eliminar amigo: título con `:name`, descripción, confirmar y cancelar;
* los textos accesibles mientras se procesa (con `:name`) y el error genérico.

`FriendshipTranslationKeysTest` ya recorre `friendship/*`.

**Type:** feature  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `lang/en/friendship/button.php`
- `lang/es/friendship/button.php`

**Acceptance Criteria:**

1. Los dos idiomas tienen las mismas claves y placeholders, y `FriendshipTranslationKeysTest` pasa.
2. Cada texto accesible que menciona a una persona usa `:name`, nunca concatenación.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Translations`

### TASK-002: Add the friends page texts in English and Spanish

Crear `lang/{en,es}/friendship/friends.php` con los textos de la página de amigos:
* título, descripción y la navegación de pestañas (etiqueta accesible);
* las pestañas con su contador en plural;
* el filtro: etiqueta, `placeholder` y borrar;
* los estados vacíos por pestaña, con y sin filtro (`:term`), y los de carga;
* "amigos desde :date" y "enviada :date", con la fecha formateada en React;
* el mensaje de validación de `q` (`:max`) y el de `tab`.

**Type:** feature  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `lang/en/friendship/friends.php`
- `lang/es/friendship/friends.php`

**Acceptance Criteria:**

1. Paridad de claves y placeholders en `en` y `es` (`FriendshipTranslationKeysTest`).
2. Los contadores de pestañas usan el plural de Laravel con `:count`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Translations`

### TASK-003: Add the sidebar texts in English and Spanish

Crear `lang/{en,es}/home/sidebar.php`:
* la navegación: etiqueta accesible, Inicio, Buscar personas, Amigos, Notificaciones y Mi perfil;
* los contadores accesibles en plural (":count solicitudes pendientes");
* el panel de solicitudes: título, vacío, ver todas y carga.

Como el grupo `home/` no tiene prueba de paridad, se agrega la carpeta al dataset de la prueba de paridad global.

**Type:** feature  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `lang/en/home/sidebar.php`
- `lang/es/home/sidebar.php`
- `tests/Feature/Translation/SettingTranslationKeysTest.php`

**Acceptance Criteria:**

1. `home/sidebar` tiene las mismas claves y placeholders en `en` y `es`, y la prueba de paridad lo cubre (falla si se borra una clave en `es`).
2. Las etiquetas de los contadores usan plural con `:count`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact tests/Feature/Translation/SettingTranslationKeysTest.php`

### TASK-004: Return the friendship id in the action responses

`FriendshipController::respond()` agrega `friendshipId` (la fila del par después de la acción, o `null`) a `{result, state, message}`.

Sin este dato, el botón no puede cancelar justo después de enviar, ni eliminar justo después de aceptar, sin recargar la página.

Se actualizan los cuerpos exactos de `FriendshipActionsTest`.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Controllers/FriendshipController.php`
- `app/Modules/Friendship/Tests/Crud/FriendshipActionsTest.php`

**Acceptance Criteria:**

1. Después de `store` y de `accept`, `friendshipId` es el id de la fila; después de `decline`, `cancel` y `destroy` es `null`.
2. Un 409 (por ejemplo `incoming_request_exists`) devuelve el `friendshipId` de la fila que ya existe, para poder aceptarla desde el botón.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Crud/FriendshipActionsTest.php`

### TASK-005: Extract the LIKE escaping into a shared helper

Crear `app/Support/Database/LikePattern.php` con `startsWith(string $term): string`, que escapa `\`, `%` y `_` y agrega `%`, más su prueba unitaria.

`FriendshipSearchController` deja su método privado `escapedForLike()` y usa el helper. La página de amigos (TASK-007) será su segundo consumidor.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Support/Database/LikePattern.php`
- `tests/Unit/Support/Database/LikePatternTest.php`
- `app/Modules/Friendship/Controllers/FriendshipSearchController.php`

**Acceptance Criteria:**

1. `LikePattern::startsWith('a%_\\b')` devuelve `'a\\%\\_\\\\b%'`, con el backslash escapado primero.
2. `FriendshipSearchTest` sigue pasando sin cambios: la búsqueda conserva los comodines como texto literal.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact tests/Unit/Support/Database app/Modules/Friendship/Tests/Crud/FriendshipSearchTest.php`

### TASK-006: Add the friends page request, tab enum and list item resource

Crear tres piezas:
* **`Enums/FriendshipListTab`**: `Friends`, `Received`, `Sent`.
* **`Requests/FriendshipIndexRequest`** (`HasFluentRules`): `tab` opcional dentro del enum y `q` opcional con máximo 50, con mensajes de `friendship/friends`.
* **`Resources/FriendshipListItemResource`**: sobre una `Friendship`, con el otro usuario ya cargado, devuelve `{friendshipId, friendshipState, since, user: {id, name, profilePicture}}`. `since` es `accepted_at` para amigos y `created_at` para solicitudes, en ISO 8601. No incluye el email y no hace consultas propias.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Enums/FriendshipListTab.php`
- `app/Modules/Friendship/Requests/FriendshipIndexRequest.php`
- `app/Modules/Friendship/Resources/FriendshipListItemResource.php`

**Acceptance Criteria:**

1. El recurso elige al otro usuario según quién mira: el receptor si mira el emisor, y el emisor si mira el receptor.
2. Un `tab` desconocido o un `q` de 51 caracteres no pasan la validación (se prueba en TASK-008).
3. PHPStan pasa.

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Friendship/Enums app/Modules/Friendship/Requests app/Modules/Friendship/Resources --memory-limit=2G`

### TASK-007: Add FriendshipIndexController and its route

**`FriendshipIndexController` (invocable):**
* registra `friendship/friends` y `notification/center`;
* resuelve la pestaña activa y consulta solo esa;
* amigos: las amistades aceptadas donde participa el usuario;
* recibidas y enviadas: las solicitudes pendientes por `receiver_id` o por `requester_id`;
* en todos los casos excluye al otro usuario si está suspendido y filtra por el inicio de su nombre con `LikePattern`;
* precarga `requester.media` y `receiver.media` y pagina con cursor.

`tabCounts` cuenta cada pestaña con la misma consulta base que su lista, sin el filtro de nombre: así excluye también a las cuentas suspendidas y cada contador coincide con su lista. Son 3 `count()`, un número constante de consultas. (No usa `User::withFriendshipCounts()`, que cuenta también a las personas suspendidas.)

**Ruta:** `GET friendships` (`friendships.index`, `throttle:60,1,friendships.index`) en el archivo de rutas del módulo. **Configuración:** `module.friendship.list_page_size` (20).

**Type:** feature  
**Effort:** L  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-001, TASK-002, TASK-005, TASK-006  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Controllers/FriendshipIndexController.php`
- `app/Modules/Friendship/routes/routes.php`
- `app/Modules/Friendship/config/friendship.php`

**Acceptance Criteria:**

1. `GET friendships?tab=received&q=An` renderiza `friendship/Friends` solo con las solicitudes recibidas cuyo remitente empieza por "An".
2. La consulta de las otras pestañas no se ejecuta, y `tabCounts` no depende del filtro.
3. La ruta tiene `auth`, `verified` y su throttle con prefijo, y no choca con `POST friendships/{user}`.

**validateCommand:** `composer phpstan && php artisan route:list --name=friendships.index`

### TASK-008: Test the friends page

`Tests/Crud/FriendshipIndexTest` cubre:
* cada pestaña, con el contenido y el orden correctos;
* el filtro por nombre, incluidos los comodines literales;
* la forma exacta de cada elemento, sin email;
* `tabCounts`;
* la exclusión de cuentas suspendidas;
* la pestaña por defecto y los errores de `tab` y `q`;
* la paginación con el cursor de `scrollProps`.

`Tests/Security/FriendshipIndexSecurityTest` cubre:
* que un invitado es redirigido al login;
* que nunca se listan amistades de otras personas;
* el throttle de 60;
* el mismo número de consultas con 3 y con 20 filas.

Va después de TASK-015: `assertInertia` exige que exista el componente de la página (`inertia.testing.ensure_pages_exist` está activado), y `friendship/Friends.tsx` se crea ahí.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-007, TASK-015  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Tests/Crud/FriendshipIndexTest.php`
- `app/Modules/Friendship/Tests/Security/FriendshipIndexSecurityTest.php`

**Acceptance Criteria:**

1. Una amistad entre otras dos personas nunca aparece en ninguna pestaña.
2. `?tab=unknown` y un `q` de 51 caracteres fallan en la validación, y sin `tab` se muestra `friends`.
3. El número de consultas es igual con 3 y con 20 filas.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Crud/FriendshipIndexTest.php app/Modules/Friendship/Tests/Security/FriendshipIndexSecurityTest.php`

### TASK-009: Share the received friend requests as a deferred prop

**`Props/FriendshipProps`** (`ProvidesInertiaProperties`, como `NotificationProps`) expone `friendRequests: Inertia::defer(…)`:
* `pendingCount`;
* `recent`: las 5 solicitudes recibidas más recientes, con `FriendshipListItemResource`;
* excluye remitentes suspendidos;
* para un invitado, `null`.

**`HandleInertiaRequests`:**
* la comparte con `Inertia::share`;
* agrega `friendship/button` y `home/sidebar` a `ALWAYS_SHARED_TRANSLATION_GROUPS`, porque los laterales y el botón aparecen en todas las páginas con `HomeLayout`;
* quita el `search_results` viejo.

**Prueba:** `Tests/Props/FriendshipPropsTest`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-001, TASK-003, TASK-006  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Props/FriendshipProps.php`
- `app/Modules/Friendship/Tests/Props/FriendshipPropsTest.php`
- `app/Http/Middleware/HandleInertiaRequests.php`

**Acceptance Criteria:**

1. Con 7 solicitudes recibidas, `pendingCount` es 7 y `recent` trae las 5 más recientes. Una solicitud de una cuenta suspendida no cuenta ni aparece.
2. Un invitado recibe `null`, y la prop solo se resuelve en la petición diferida.
3. `search_results` ya no se comparte (`grep` sin resultados en `app/`).

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Props app/Modules/Notification/Tests/Props`

### TASK-010: Give the profile its friendship data

**`Props/ProfileFriendshipProps::for(User $profileUser)`** devuelve `profileFriendship`, según el contrato:
* `friendsCount` del dueño del perfil;
* `isOwnProfile`;
* para quien mira, `friendshipState` y `friendshipId`, con `FriendshipState::forViewer()` sobre `friendshipWith()`;
* `mutualFriendsCount`, con una sola consulta que intersecta los ids de amigos de los dos.

**`ProfileController::index` y `show`** lo incluyen en sus props.

**Prueba:** `Tests/Props/ProfileFriendshipPropsTest`, por la página real.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Props/ProfileFriendshipProps.php`
- `app/Modules/Friendship/Tests/Props/ProfileFriendshipPropsTest.php`
- `app/User/Profile/Controllers/ProfileController.php`

**Acceptance Criteria:**

1. En el perfil de otra persona: el estado y el id correctos para los cuatro estados, y 2 amigos en común cuando comparten dos.
2. En el perfil propio: `isOwnProfile` es `true`, `friendshipState` es `null`, `mutualFriendsCount` es `0` y `friendsCount` es real.
3. Las amistades pendientes no cuentan como amigos ni como amigos en común.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Props/ProfileFriendshipPropsTest.php`

### TASK-011: Build the reusable FriendshipButton

**`resources/js/modules/friendship/components/FriendshipButton.tsx`**, con un control por estado:
* `none`: Agregar amigo;
* `request_sent`: un menú con Cancelar solicitud;
* `request_received`: Aceptar y Rechazar;
* `friends`: un menú con Eliminar amigo, que pide confirmación.

**Comportamiento:**
* hace la actualización optimista y la revierte si el servidor responde 409, 429 o un error de red, mostrando el `message` del servidor o el error genérico, sin el modal de Inertia;
* toma `state` y `friendshipId` de la respuesta;
* bloquea los clics mientras hay una petición;
* tiene una prop `onChange(state, friendshipId)` para que la página o el panel recarguen sus contadores;
* usa `useHttp(() => ({}))`, con el estado inicial como función, por estilo.

**`resources/js/modules/friendship/components/FriendshipRemoveDialog.tsx`:** la confirmación con el `Dialog` de shadcn.

**`resources/js/modules/friendship/types/friendshipSearch.ts`:** agrega `friendshipId` a `FriendshipActionResponse`.

**Type:** feature  
**Effort:** L  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P0  
**Depends on:** TASK-001, TASK-004  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/FriendshipButton.tsx`
- `resources/js/modules/friendship/components/FriendshipRemoveDialog.tsx`
- `resources/js/modules/friendship/types/friendshipSearch.ts`

**Acceptance Criteria:**

1. Cada estado muestra el control que le corresponde, y cada acción llama a su ruta de Wayfinder (`store`, `accept`, `decline`, `cancel`, `destroy`) con el `friendshipId` vigente.
2. Un 409 revierte el estado optimista al `state` del servidor y muestra su mensaje. Eliminar amigo no se envía sin confirmar en el diálogo.
3. Todo el texto viene de `friendship/button`, y pasan `types`, `lint` y `format`.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-012: Use FriendshipButton in the search results and drop the old button texts

`FriendshipSearchResultRow` reemplaza su botón interno por `FriendshipButton`, pasándole el estado y el `friendshipId` del resultado.

Se borran las claves `button.*` de `lang/{en,es}/friendship/search.php`, que ya viven en `friendship/button`.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-011  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/FriendshipSearchResultRow.tsx`
- `lang/en/friendship/search.php`
- `lang/es/friendship/search.php`

**Acceptance Criteria:**

1. Desde la búsqueda ahora también se puede aceptar, rechazar o cancelar sin salir de la página.
2. `grep 'friendship/search.button' resources/js` no devuelve nada, y las pruebas de traducción y de claves pasan.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Translations tests/Feature/Translation/FrontendTranslationKeysTest.php`

### TASK-013: Extract the virtualized infinite list into a shared component

**`resources/js/modules/friendship/components/VirtualInfiniteList.tsx`**, un componente genérico:
* recibe `data` (el nombre de la prop de `Inertia::scroll`), `items`, `getItemKey`, `renderItem`, `scrollElement` y `estimateSize`;
* contiene `<InfiniteScroll onlyNext preserveUrl>`, `useVirtualizer` con el `scrollMargin` medido en `useLayoutEffect` y el estado de "cargando más".

`Search.tsx` lo usa en lugar de su `VirtualSearchResults`.

**Type:** refactor  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/VirtualInfiniteList.tsx`
- `resources/js/modules/friendship/Search.tsx`

**Acceptance Criteria:**

1. La búsqueda sigue cargando las páginas al bajar y dibujando solo las filas visibles: `FriendshipSearchBrowserTest` pasa sin cambios.
2. `Search.tsx` ya no importa `useVirtualizer` ni `InfiniteScroll`.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && composer test:browser`

### TASK-022: Install the shadcn tabs component

Instalar el componente `tabs` de shadcn con su CLI (`npx shadcn@latest add tabs`), que lo escribe en `resources/js/shared/components/shadcn/ui/tabs.tsx` según `components.json`.

Importa `Tabs` del paquete `radix-ui`, que ya está instalado, así que `package.json` no cambia. Si la CLI intenta agregar `@radix-ui/react-tabs`, se descarta ese cambio y el import queda en `radix-ui`, como en `dialog.tsx`.

Se revisa que el archivo pase Prettier y ESLint sin tocar su lógica.

**Type:** chore  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `resources/js/shared/components/shadcn/ui/tabs.tsx`

**Acceptance Criteria:**

1. `tabs.tsx` exporta `Tabs`, `TabsList`, `TabsTrigger` y `TabsContent`, e importa de `radix-ui`.
2. `git diff package.json package-lock.json` no muestra cambios, y la CLI no sobrescribió ningún otro componente de `shadcn/ui`.

**validateCommand:** `test -f resources/js/shared/components/shadcn/ui/tabs.tsx && git diff --exit-code package.json package-lock.json && npm run types && npm run lint:check && npm run format:check`

### TASK-014: Create the friends list row

**`resources/js/modules/friendship/components/FriendshipListRow.tsx`:**
* foto, nombre enlazado al perfil y la fecha ("amigos desde" o "enviada") con `useDateTime`;
* `FriendshipButton` con el estado y el id del elemento;
* la misma container query que la fila de búsqueda.

**`resources/js/modules/friendship/types/friendshipList.ts`:** `FriendshipListItem`, `FriendshipListTab` (espejo del enum PHP), `FriendshipListFilters` y `FriendshipTabCounts`.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-002, TASK-011  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/FriendshipListRow.tsx`
- `resources/js/modules/friendship/types/friendshipList.ts`

**Acceptance Criteria:**

1. La fecha se formatea con `useDateTime()`, nunca con `dayjs(...).format(...)`.
2. La fila no tiene texto escrito a mano (`FrontendTranslationKeysTest` pasa).

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && php -d memory_limit=2G vendor/bin/pest --compact tests/Feature/Translation/FrontendTranslationKeysTest.php`

### TASK-015: Build the friends page

**`resources/js/modules/friendship/Friends.tsx`:**
* usa `HomeLayout` con `contentRef`, `Head` y un título;
* las pestañas usan `Tabs`, `TabsList`, `TabsTrigger` y `TabsContent` de shadcn: `value` es la pestaña ya aplicada, `onValueChange` llama al cambio de pestaña del hook y `activationMode="manual"`; cada `TabsTrigger` muestra su contador de `tabCounts`, y solo el `TabsContent` activo monta la lista;
* tiene el filtro por nombre y el estado vacío por pestaña (con y sin filtro, según el término ya aplicado);
* lista con `VirtualInfiniteList` y `FriendshipListRow`;
* tras una acción, recarga solo `tabCounts` y `friendRequests` (`router.reload({ only: [...] })`).

**`resources/js/modules/friendship/hooks/useFriendsFilters.ts`:** como `useFriendshipSearch`, pero con `tab` y `q` en la URL. Cambiar de pestaña aplica de inmediato y el filtro con debounce; usa `reset: ['friendships']`, `committedFilters` y `hasInteractedRef`.

**Type:** feature  
**Effort:** L  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-007, TASK-013, TASK-014, TASK-022  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/Friends.tsx`
- `resources/js/modules/friendship/hooks/useFriendsFilters.ts`

**Acceptance Criteria:**

1. Cambiar de pestaña actualiza `?tab=` y muestra solo esa lista. Recargar conserva la pestaña y el filtro, y la pestaña activa tiene `aria-selected="true"`. Moverse con las flechas no hace ninguna visita: Enter o Espacio activa la pestaña.
2. Durante el debounce, el estado vacío sigue el filtro ya aplicado y no el texto escrito, así que no muestra el mensaje equivocado. StrictMode no dispara una petición al montar.
3. Aceptar una solicitud en la pestaña Recibidas actualiza sus contadores sin recargar toda la página.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-016: Turn the left sidebar into the app navigation

**`HomeSidebarLeft.tsx`** es una `<nav>` con su etiqueta accesible y estos enlaces: Inicio, Buscar personas, Amigos, Notificaciones y Mi perfil.
* Amigos muestra el badge de `friendRequests.pendingCount`, y Notificaciones el de `unreadNotificationsCount`; los dos son props diferidas, con `Deferred` y un esqueleto.
* El enlace activo se resalta con `aria-current`, según `usePage().url`.
* Todo el texto viene de `home/sidebar`.

Los ítems van en `modules/home/data/homeSidebarNavigation.ts` (URL de Wayfinder, icono y clave de traducción).

`shared/types/index.d.ts` agrega `friendRequests` a `SharedData`.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-003, TASK-007, TASK-009  
**Review:** claude

**writeScope:**

- `resources/js/modules/home/data/homeSidebarNavigation.ts`
- `resources/js/modules/home/components/sidebar/HomeSidebarLeft.tsx`
- `resources/js/shared/types/index.d.ts`

**Acceptance Criteria:**

1. Cada enlace lleva a su página, y el de la página actual tiene `aria-current="page"`.
2. Los badges aparecen solo cuando el contador es mayor que 0, y su texto accesible está en plural y traducido.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-017: Show the received requests in the right sidebar

**`HomeSidebarRight.tsx`** muestra **`FriendRequestsPanel`**:
* lee `friendRequests` con `Deferred` y un esqueleto;
* muestra hasta 5 solicitudes con foto, nombre enlazado y `FriendshipButton` en estado `request_received`;
* tiene un estado vacío y "ver todas", que lleva a `friendships.index?tab=received`;
* tras aceptar o rechazar, recarga solo `friendRequests`.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-009, TASK-011, TASK-016  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/FriendRequestsPanel.tsx`
- `resources/js/modules/home/components/sidebar/HomeSidebarRight.tsx`

**Acceptance Criteria:**

1. Aceptar desde el panel quita la solicitud de la lista y baja el badge de la navegación sin recargar toda la página.
2. Sin solicitudes se ve el estado vacío, nunca un hueco en blanco.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-018: Show real friendship data on the profile

`Profile.tsx` lee `profileFriendship`:
* reemplaza el "344 amigos" fijo por `friendsCount` en plural, que enlaza a la página de amigos en el perfil propio;
* en el perfil de otra persona muestra los amigos en común (si hay) y `FriendshipButton`, que al cambiar recarga `profileFriendship`.

Los tipos van en `modules/profile/types/profileFriendship.ts`. Los textos nuevos van en `friendship/button` o `home/sidebar` (compartidos siempre) para no registrar otro grupo en el perfil.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-010, TASK-011  
**Review:** claude

**writeScope:**

- `resources/js/modules/profile/types/profileFriendship.ts`
- `resources/js/modules/profile/Profile.tsx`

**Acceptance Criteria:**

1. Ya no aparece "344 amigos" (`grep` sin resultados), y el número coincide con `friendsCount`.
2. En el perfil propio no hay botón de amistad ni amigos en común. En el de otra persona, el botón refleja el estado real.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-019: Add a browser test for the friends page and the sidebars

**`app/Modules/Friendship/Tests/Browser/FriendshipFriendsBrowserTest.php`**, con un usuario autenticado que tiene solicitudes recibidas y amigos:
* ve el badge de Amigos en el lateral izquierdo;
* acepta una solicitud desde el panel derecho, y el badge baja;
* abre la página de amigos y cambia de pestaña, comprobando `?tab=`;
* elimina un amigo confirmando el diálogo;
* llama a `assertNoJavaScriptErrors()`.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-015, TASK-016, TASK-017  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Tests/Browser/FriendshipFriendsBrowserTest.php`

**Acceptance Criteria:**

1. La prueba recorre aceptar, cambiar de pestaña y eliminar, y pasa con `composer test:browser`.
2. Comprueba en la base que la solicitud quedó aceptada y la amistad eliminada.

**validateCommand:** `composer test:browser`

### TASK-020: Document the friends page, the button, the profile and the sidebars

**`docs/architecture/modules/friendship.md`** documenta:
* la página de amigos: controlador, pestañas, filtro y props;
* `friendshipId` en las acciones;
* `FriendshipProps` y `ProfileFriendshipProps`;
* `FriendshipButton` con sus estados y la actualización optimista;
* `VirtualInfiniteList`, `LikePattern` y los catálogos.

**`CLAUDE.md`:** la descripción del módulo menciona la página de amigos; la tabla de enums con espejo en TypeScript agrega `FriendshipListTab`; y los laterales del `HomeLayout` ya no están vacíos.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Depends on:** TASK-012, TASK-018, TASK-019  
**Review:** claude

**writeScope:**

- `docs/architecture/modules/friendship.md`
- `CLAUDE.md`

**Acceptance Criteria:**

1. Cada clase, prop, ruta y componente que nombra existe (contrastado con `grep`).
2. El documento ya no dice que la página de amigos, el botón o el contador quedan para SOC-84.

**validateCommand:** `grep -n 'FriendshipIndexController' docs/architecture/modules/friendship.md`

### TASK-021: Run the full gates

Ejecutar los gates completos:
1. Pint, PHPStan y Rector (`rector-clear-cache` → `rector-dry` → `rector`);
2. `composer test` y `composer test:browser`;
3. `composer doctor`;
4. `npm run format:check`, `lint:check`, `types`, `build` y `build:ssr`;
5. una comprobación manual en el navegador, en modo claro y oscuro, con la pestaña al frente (el scroll infinito no funciona en una pestaña oculta).

**Type:** chore  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P3  
**Depends on:** TASK-008, TASK-020  
**Review:** claude

**writeScope:**

- (sin archivos: verificación)

**Acceptance Criteria:**

1. Pint, PHPStan y Rector no reportan cambios ni errores, y `composer test` y `composer test:browser` pasan.
2. Los dos builds compilan, y la página de amigos y los laterales se ven bien en modo claro y oscuro.

**validateCommand:** `composer phpstan && composer rector-dry && composer test && composer test:browser && npm run build && npm run build:ssr`

## Failure Modes

- Acciones simultáneas desde dos lugares (el panel y la página): el servicio ya relee con bloqueo y responde `NoLongerPending`. El botón revierte al `state` del servidor y muestra su mensaje.
- Una prop diferida que tarda: los badges y el panel muestran un esqueleto (`Deferred`), nunca un 0 falso.
- Un `tab` manipulado en la URL: falla la validación, sin error 500.
- Cambiar de pestaña sin `reset: ['friendships']`: las filas de dos pestañas se mezclarían, porque `Inertia::scroll` concatena las páginas.
- Las pruebas de los catálogos fallan si se borra una clave en un idioma; TASK-003 agrega `home/` a la prueba de paridad.

## Ship Cut

El mínimo entregable es TASK-001 a TASK-015, más TASK-012: la página de amigos completa y el botón reutilizable, con lo que se cierra el flujo de enviar, aceptar, rechazar, cancelar y eliminar. Los laterales (TASK-016 y TASK-017) y el perfil (TASK-010 y TASK-018) son recortables como bloques, con la aprobación del usuario. La documentación y los gates no se recortan.

## Test Coverage Map

| Comportamiento | Tarea |
| --- | --- |
| friendshipId en las respuestas de las acciones | TASK-004 |
| LikePattern | TASK-005 |
| Página de amigos: pestañas, filtro, orden, exclusiones, validación, cursor | TASK-008 |
| Seguridad y consultas constantes de la página de amigos | TASK-008 |
| friendRequests compartida: conteo, últimas 5, suspendidas, invitado | TASK-009 |
| Datos de amistad del perfil | TASK-010 |
| Paridad de traducciones de home/sidebar | TASK-003 |
| Búsqueda tras extraer la lista virtual | TASK-013 |
| Aceptar desde el panel, pestañas y eliminar en el navegador | TASK-019 |

## Execution Summary

- **Tareas:** 22
- **Capa 1:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-010, TASK-013, TASK-022
- **Capa 2:** TASK-006, TASK-011
- **Capa 3:** TASK-007, TASK-009, TASK-012, TASK-014, TASK-018
- **Capa 4:** TASK-015, TASK-016
- **Capa 5:** TASK-008, TASK-017
- **Capa 6:** TASK-019
- **Capa 7:** TASK-020
- **Capa 8:** TASK-021
- **Ruta crítica:** TASK-002 → TASK-006 → TASK-007 → TASK-016 → TASK-017 → TASK-019 → TASK-020 → TASK-021

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | — |
| TASK-004 | — |
| TASK-005 | — |
| TASK-006 | TASK-002 |
| TASK-007 | TASK-001, TASK-002, TASK-005, TASK-006 |
| TASK-008 | TASK-007, TASK-015 |
| TASK-009 | TASK-001, TASK-003, TASK-006 |
| TASK-010 | — |
| TASK-011 | TASK-001, TASK-004 |
| TASK-012 | TASK-011 |
| TASK-013 | — |
| TASK-022 | — |
| TASK-014 | TASK-002, TASK-011 |
| TASK-015 | TASK-007, TASK-013, TASK-014, TASK-022 |
| TASK-016 | TASK-003, TASK-007, TASK-009 |
| TASK-017 | TASK-009, TASK-011, TASK-016 |
| TASK-018 | TASK-010, TASK-011 |
| TASK-019 | TASK-015, TASK-016, TASK-017 |
| TASK-020 | TASK-012, TASK-018, TASK-019 |
| TASK-021 | TASK-008, TASK-020 |
