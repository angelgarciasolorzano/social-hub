# Plan: SOC-88 — Polish the friends page and the home layout

**Linear:** SOC-88 (épica SOC-82) · **Modo:** EXPANSION · **Revisión por defecto:** claude

## Overview

La vista de amigos funciona, pero se ve simple. Este plan aplica la propuesta B del lienzo de Claude Design y mejora el layout de inicio:

* **Vista de amigos:**
  * cuadrícula de tarjetas con `<InfiniteScroll>`, sin virtualizar;
  * bloque azul con las solicitudes recibidas;
  * pestañas subrayadas con contadores de color;
  * filtro con foco al abrir y al cambiar de pestaña;
  * selector de orden (recientes o por nombre) en las tres pestañas;
  * skeleton de tarjetas al cambiar de pestaña;
  * estados vacíos con ícono y color;
  * barra fija al hacer scroll;
  * atajos de teclado con `@tanstack/react-hotkeys`.
* **Backend:**
  * amigos en común por tarjeta, en la misma consulta de la lista, con la lógica de la búsqueda extraída a una clase compartida;
  * parámetro `sort` validado;
  * las solicitudes recientes de `friendRequests` con sus amigos en común.
* **Layout:**
  * menos margen;
  * sidebars del mismo ancho;
  * el derecho reservado para módulos futuros;
  * el izquierdo rediseñado con `lucide-react` y badges de color;
  * en pantallas chicas, el menú pasa a un panel lateral (`Sheet`) y los sidebars se ocultan.

## Scope Challenge

**Qué existe hoy:**

- `FriendshipIndexController` arma cada pestaña con `tabQuery()` (otra persona activa, filtro con `LikePattern`), ordena por `accepted_at`/`created_at` + `id` y pagina con `cursorPaginate` (`module.friendship.list_page_size`, 20) a través de `Inertia::scroll`.
- `FriendshipSearchController` ya calcula los amigos en común en la misma consulta (`mutualFriendsCount()` + `friendIdsOf()`, dos subconsultas por lado del par).
- `FriendshipListItemResource` serializa `{friendshipId, friendshipState, since, user}` y lanza `LogicException` si falta la relación de la otra persona. `FriendshipProps` lo reutiliza para `friendRequests.recent` (5 solicitudes).
- `Friends.tsx`:
  * renderiza `Tabs` de shadcn con un `Loader2Icon` en la pestaña pendiente y contadores grises;
  * usa `VirtualInfiniteList` y un `main` con `max-w-3xl`.
  * `useFriendsFilters` guarda `tab` y `q` en la URL.
- `HomeLayout` monta `HomeSidebarLeft` (`w-64`, íconos de `react-icons/lu`, badges negros) y `HomeSidebarRight` (`w-64`, solo `FriendRequestsPanel`); el contenido central suma `px-4`.
- `@tanstack/react-hotkeys` ya se usa en `useTrustedDeviceShortcuts` con `useHotkey` y `useHotkeySequences`, `ignoreInputs` y un diálogo de atajos.
- shadcn ya trae `Skeleton`, `Sheet`, `Select`, `Kbd`, `Tabs`, `Card`, `DropdownMenu` y `Avatar`.

**Qué se reutiliza:**

- La subconsulta de amigos en común de la búsqueda, extraída a `Queries/MutualFriendsQuery`.
- `FriendshipButton` en cada tarjeta y en el bloque de solicitudes, con sus estados, menús y manejo de 409/429.
- `reloadFriendshipDependentProps` para recargar contadores, lista y `friendRequests` después de una acción.
- El patrón de `useTrustedDeviceShortcuts` y su diálogo de atajos.

**Qué es nuevo:**

- El enum `FriendshipListSort` (`recent`, `name`) y su espejo en TypeScript.
- `mutualFriendsCount` en `FriendshipListItemResource`.
- Los componentes `FriendshipCard`, `FriendshipCardSkeleton`, `FriendshipCardGrid`, `FriendRequestsHighlight`, `FriendsEmptyState`, `FriendsKeyboardShortcutsDialog` y `HomeMobileNavigation`, y el hook `useFriendsShortcuts`.

**Decisiones confirmadas:**

- Modo EXPANSION y revisión `claude` después de cada tarea.
- Propuesta B de Claude Design (https://claude.ai/artifact/7QncCbdbsrP6bohSGEB3BX).
- `<InfiniteScroll>` sin virtualizar para la cuadrícula; `VirtualInfiniteList` no se toca porque el feed la usa.
- El orden aplica a las tres pestañas y vive en la URL (`?sort=name`; `recent` se omite).
- Extras de EXPANSION: atajo `/`, atajos de pestañas, orden y ayuda; estados vacíos con ícono; barra sticky; sidebars responsive.
- `friendRequests.recent` se queda: deja el sidebar derecho y alimenta el bloque de la vista de amigos, que muestra 2 solicitudes.

**¿Ejecución directa?** No: toca la consulta paginada de la lista, una clase compartida con la búsqueda, una prop compartida en todas las páginas, el layout de todas las páginas y dos pruebas de navegador.

## Prerequisites

- SOC-84, SOC-85 y SOC-86 están mergeadas: vista de amigos, notificaciones de amistad y feed.
- `FriendshipSeeder` deja amigos, solicitudes recibidas y enviadas en local para revisar a mano.
- Chromium instalado para `composer test:browser`.

## Non-Goals

- Contenido nuevo del panel derecho (sugerencias, actividad): solo se reserva el espacio.
- Mensajes entre amigos.
- Virtualizar la cuadrícula: se evalúa en otra tarea si el DOM llega a pesar.
- Cambios en las reglas de amistad, en sus endpoints de acción o en el panel de Filament.
- Usar el componente `Sidebar` de shadcn: solo se toma su estilo.

## Contracts

### Orden de la lista

`App\Modules\Friendship\Enums\FriendshipListSort: string` con `Recent = 'recent'` y `Name = 'name'`.

* **Request:** `FriendshipIndexRequest` valida `sort` como enum nullable (mensaje `friendship/friends.validation.sort_invalid`) y expone `sort(): FriendshipListSort` (`Recent` por defecto).
* **Orden `recent`:** como hoy, por `accepted_at` o `created_at` desc + `id` desc.
* **Orden `name`:** por el nombre de la otra persona asc + `friendships.id` asc. La otra persona se une con `users` sobre `CASE WHEN requester_id = viewer THEN receiver_id ELSE requester_id END`, para que `cursorPaginate` compare una columna real.
* **Props:** `filters` pasa a ser `{tab, q, sort}`.
* **Mirror en TypeScript:** `FriendshipListSort = "recent" | "name"` en `types/friendshipList.ts`.

### Amigos en común

`App\Modules\Friendship\Queries\MutualFriendsQuery`:

* `friendIdsOf(User): QueryBuilder` es la unión de los dos índices `(<side>_id, status)`.
* `countFor(User $viewer, string $otherUserIdColumn): QueryBuilder` es una subconsulta escalar que cuenta las amistades aceptadas de la otra persona cuyo otro lado está entre los amigos del viewer. Recibe solo un nombre de columna: PHPStan no acepta una `Expression` armada con el id del viewer.
* La búsqueda la usa con `users.id`; la lista, con la columna `id` del `users` unido sobre el `CASE` de la otra persona (en los dos órdenes); y `FriendshipProps`, con `friendships.requester_id`.
* **Resource:** `FriendshipListItemResource` agrega `mutualFriendsCount: int` y lanza `LogicException` si la consulta no seleccionó `mutual_friends_count`, igual que con las relaciones.

### friendRequests compartida

`{pendingCount: int, recent: FriendshipListItem[]}` con `RECENT_REQUESTS_LIMIT = 2`. Cada elemento trae `mutualFriendsCount`. Sigue siendo deferred y con consultas constantes; el badge del menú sigue leyendo `pendingCount`.

### Vista de amigos

* **Cuadrícula:** 3 columnas en `xl`, 2 en `md` y 1 en pantallas chicas, dentro de `<InfiniteScroll data="friendships" onlyNext preserveUrl>`.
* **Carga:** mientras carga una pestaña, la cuadrícula muestra 6 `FriendshipCardSkeleton`; el slot `loading` muestra 3 más.
* **Foco:** el filtro recibe el foco al montar la página y en `onSuccess` de cada cambio de pestaña.
* **Orden:** el selector recarga con `only: ['friendships', 'filters']` y `reset: ['friendships']`.
* **Bloque de solicitudes:** lee `friendRequests` con `<Deferred>`. Aparece solo si `pendingCount > 0`, en cualquier pestaña, y enlaza a `?tab=received`.
* **Atajos:** se ignoran dentro de inputs y con un diálogo o menú abierto:
  * `/` enfoca el filtro;
  * `1`, `2` y `3` cambian de pestaña;
  * `o` alterna el orden;
  * `Shift+?` abre la ayuda.

### Layout

* **Sidebars:** los dos miden `w-64` desde `lg`; por debajo, se ocultan.
* **Contenido central:** usa `px-3 lg:px-6`, y la vista de amigos usa `max-w-6xl`.
* **Pantallas chicas:** el header muestra un botón de menú que abre `HomeMobileNavigation` (`Sheet` a la izquierda) con la misma lista de navegación.
* **Panel derecho:** muestra un espacio reservado, con texto de `home/sidebar.reserved.*`.
* **Badges del menú:** Amigos en azul (`bg-blue-600`) y Notificaciones en naranja (`bg-orange-500`), con texto blanco.

## Existing Code Leverage

- `app/Modules/Friendship/Controllers/FriendshipSearchController.php`: amigos en común en la misma consulta.
- `app/Modules/Friendship/Controllers/FriendshipIndexController.php`: `tabQuery()`, `withOtherUser()` y la paginación.
- `resources/js/modules/friendship/components/FriendshipButton.tsx`: acciones por estado, menús `modal={false}` y errores.
- `resources/js/modules/friendship/components/FriendRequestsPanel.tsx`: lectura de `friendRequests` con `<Deferred>` y recarga.
- `resources/js/modules/setting/modules/trustedDevice/hooks/useTrustedDeviceShortcuts.ts`: atajos con `@tanstack/react-hotkeys`.
- `resources/js/shared/components/shadcn/ui/sheet.tsx`, `skeleton.tsx`, `select.tsx` y `kbd.tsx`.

## Tasks

### TASK-001: Add the texts of the redesigned friends page

En `lang/{en,es}/friendship/friends.php`:

* **`sort.*`:** etiqueta, `recent` y `name`.
* **`validation.sort_invalid`.**
* **`requests.*`:** título del bloque, `view_all` con `:count` y el texto de accesibilidad.
* **`card.*`:** amigos en común con plural, "Ver perfil", "Más opciones" y el texto alternativo de la foto.
* **`states.end_of_list`.**
* **`shortcuts.*`:** título, descripción y cada atajo.
* **Placeholder del filtro:** cambia a "Filtrar por nombre" / "Filter by name".

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `lang/en/friendship/friends.php`
- `lang/es/friendship/friends.php`

**Acceptance Criteria:**

1. Los dos idiomas tienen las mismas claves y placeholders; `card.mutual_friends` usa `trans_choice` con 0, 1 y varios.
2. La prueba de paridad falla si falta una clave nueva en `es`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Translations/FriendshipTranslationKeysTest.php`

### TASK-002: Add the texts of the reserved panel and the mobile menu

En `lang/{en,es}/home/sidebar.php`:

* **`reserved.{title, description}`:** el panel derecho reservado.
* **`mobile.{open, close, title}`:** el menú en pantallas chicas.
* **`navigation.section`:** la etiqueta de la sección del menú.

Las claves `friend_requests.*` se quedan hasta TASK-018.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `lang/en/home/sidebar.php`
- `lang/es/home/sidebar.php`

**Acceptance Criteria:**

1. Los dos idiomas tienen las mismas claves.
2. `SettingTranslationKeysTest` (paridad de `lang/*/home/`) falla si falta una clave en `es`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact tests/Feature/Translation`

### TASK-003: Validate the sort of the friends list

Crear `Enums/FriendshipListSort` (`recent`, `name`). En `FriendshipIndexRequest`, agregar la regla `sort` (enum nullable con `validation.sort_invalid`) y `sort(): FriendshipListSort`. Prueba nueva de la request.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** TASK-001  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Enums/FriendshipListSort.php`
- `app/Modules/Friendship/Tests/Requests/FriendshipIndexRequestTest.php`
- `app/Modules/Friendship/Requests/FriendshipIndexRequest.php`

**Acceptance Criteria:**

1. Sin `sort`, `sort()` devuelve `Recent`; con `name`, devuelve `Name`.
2. `sort=oldest` falla con el mensaje traducido de `validation.sort_invalid`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Requests/FriendshipIndexRequestTest.php`

### TASK-004: Extract the mutual friends count into a shared query

Crear `Queries/MutualFriendsQuery` con `friendIdsOf()` y `countFor()` según el contrato, y hacer que `FriendshipSearchController` lo use en lugar de sus métodos privados. La búsqueda no cambia de resultado ni de número de consultas.

**Type:** refactor  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Queries/MutualFriendsQuery.php`
- `app/Modules/Friendship/Tests/Queries/MutualFriendsQueryTest.php`
- `app/Modules/Friendship/Controllers/FriendshipSearchController.php`

**Acceptance Criteria:**

1. `countFor()` cuenta solo amistades aceptadas en común: no cuenta las pendientes ni al propio viewer.
2. Funciona con `users.id` y con una columna de una fila externa de `friendships`, sin chocar con las filas que cuenta.
3. `FriendshipSearchTest` sigue pasando sin cambios, incluida la de consultas constantes.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Queries/MutualFriendsQueryTest.php app/Modules/Friendship/Tests/Crud/FriendshipSearchTest.php`

### TASK-005: Sort the friends list and add the mutual friends count

**En `FriendshipIndexController`:**

* aplica `sort()`; `name` une `users` con la otra persona según el contrato;
* selecciona `mutual_friends_count` con `MutualFriendsQuery`;
* devuelve `filters.sort`.

**En `FriendshipListItemResource`:** agregar `mutualFriendsCount`.

**En `FriendshipIndexTest`:** casos de orden por nombre, cursor estable, amigos en común y consultas constantes.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-003, TASK-004  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Controllers/FriendshipIndexController.php`
- `app/Modules/Friendship/Resources/FriendshipListItemResource.php`
- `app/Modules/Friendship/Tests/Crud/FriendshipIndexTest.php`

**Acceptance Criteria:**

1. `sort=name` ordena por el nombre de la otra persona en las tres pestañas, y la segunda página del cursor sigue sin repetir ni saltar filas.
2. Cada elemento trae `mutualFriendsCount` correcto, y listar 3 o 15 filas hace las mismas consultas.
3. El resource lanza `LogicException` si la consulta no seleccionó `mutual_friends_count`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Crud/FriendshipIndexTest.php app/Modules/Friendship/Tests/Security/FriendshipIndexSecurityTest.php`

### TASK-006: Send two recent requests with their mutual friends

En `FriendshipProps`:

* `RECENT_REQUESTS_LIMIT = 2`;
* las solicitudes recientes seleccionan `mutual_friends_count` con `MutualFriendsQuery`;
* el docblock deja de mencionar el sidebar derecho.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-005  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Props/FriendshipProps.php`
- `app/Modules/Friendship/Tests/Props/FriendshipPropsTest.php`

**Acceptance Criteria:**

1. `recent` trae como máximo 2 solicitudes, las más nuevas, cada una con `mutualFriendsCount`.
2. Las solicitudes de cuentas suspendidas siguen fuera, y las consultas no dependen del número de solicitudes.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Friendship/Tests/Props/FriendshipPropsTest.php`

### TASK-007: Type the sort and keep it in the URL

**En `types/friendshipList.ts`:**

* `FriendshipListSort` y `filters.sort`;
* `mutualFriendsCount` en `FriendshipListItem`.

**En `useFriendsFilters`:**

* `sort` en la URL (se omite `recent`) y `changeSort()`;
* `loadingTab` en lugar de `pendingTab`, para el skeleton;
* `onTabLoaded`, que la página usa para enfocar el filtro.

**Type:** feature  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-005  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/types/friendshipList.ts`
- `resources/js/modules/friendship/hooks/useFriendsFilters.ts`

**Acceptance Criteria:**

1. Cambiar el orden hace una sola visita con `only: ['friendships', 'filters']` y `reset: ['friendships']`, y conserva `tab` y `q`.
2. Una visita cancelada por otra más nueva no deja `loadingTab` ni los filtros a medias.

**validateCommand:** `npm run types && npx eslint resources/js/modules/friendship/types/friendshipList.ts resources/js/modules/friendship/hooks/useFriendsFilters.ts && npx prettier --check resources/js/modules/friendship/types/friendshipList.ts resources/js/modules/friendship/hooks/useFriendsFilters.ts`

### TASK-008: Make the accept button blue

En `FriendshipButton`:

* "Aceptar" usa azul (`bg-blue-600 hover:bg-blue-700 text-white`, con foco visible);
* "Rechazar" queda en `outline`.

**Type:** feature  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/FriendshipButton.tsx`

**Acceptance Criteria:**

1. El botón "Aceptar" es azul en sus estados normal, hover y de carga, con contraste ≥ 4.5:1.
2. Los demás estados del botón y su manejo de 409/429 no cambian.

**validateCommand:** `npm run types && npx eslint resources/js/modules/friendship/components/FriendshipButton.tsx && npx prettier --check resources/js/modules/friendship/components/FriendshipButton.tsx`

### TASK-009: Build the friend card and its skeleton

**`FriendshipCard`:**

* portada de color derivada del id del usuario;
* `Avatar`, nombre con enlace al perfil, "Amigos desde" o "Enviada" y los amigos en común;
* "Ver perfil" y `FriendshipButton` para las acciones de cada estado.

**`FriendshipCardSkeleton`:** el `Skeleton` de shadcn con la misma forma.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-001, TASK-007, TASK-008  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/FriendshipCard.tsx`
- `resources/js/modules/friendship/components/FriendshipCardSkeleton.tsx`

**Acceptance Criteria:**

1. La tarjeta muestra el texto de amigos en común con plural, y lo omite cuando es 0.
2. La portada no usa imágenes externas y su color es estable para un mismo usuario.
3. El skeleton se anuncia como carga una sola vez, no por tarjeta.

**validateCommand:** `npm run types && npx eslint resources/js/modules/friendship/components/FriendshipCard.tsx resources/js/modules/friendship/components/FriendshipCardSkeleton.tsx && npx prettier --check resources/js/modules/friendship/components/FriendshipCard.tsx resources/js/modules/friendship/components/FriendshipCardSkeleton.tsx`

### TASK-010: Lay the cards in an infinite grid

`FriendshipCardGrid`: `<InfiniteScroll data="friendships" onlyNext preserveUrl>` con la cuadrícula del contrato. Sus slots:

* **siguiente página:** 3 skeletons;
* **fin de la lista:** "No hay más", cuando `scrollProps.friendships.nextPage` es `null`;
* **carga de pestaña:** 6 skeletons.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-009  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/FriendshipCardGrid.tsx`

**Acceptance Criteria:**

1. Al llegar al final se pide la siguiente página una sola vez, y las tarjetas nuevas se agregan sin mover el scroll.
2. Con la lista cargando muestra solo skeletons, nunca tarjetas de la pestaña anterior.

**validateCommand:** `npm run types && npx eslint resources/js/modules/friendship/components/FriendshipCardGrid.tsx && npx prettier --check resources/js/modules/friendship/components/FriendshipCardGrid.tsx`

### TASK-011: Highlight the received requests on the friends page

`FriendRequestsHighlight`: un bloque azul que lee `friendRequests` con `<Deferred>` (skeleton mientras llega). Muestra las 2 solicitudes recientes con `FriendshipButton` y un enlace "Ver las N" a `?tab=received`. Después de una acción llama a `reloadFriendshipDependentProps`.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-001, TASK-006, TASK-007, TASK-008  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/FriendRequestsHighlight.tsx`

**Acceptance Criteria:**

1. Con `pendingCount` en 0 no renderiza nada (ni el título).
2. Aceptar o rechazar desde el bloque actualiza los contadores, la lista y el badge del menú sin recargar la página.

**validateCommand:** `npm run types && npx eslint resources/js/modules/friendship/components/FriendRequestsHighlight.tsx && npx prettier --check resources/js/modules/friendship/components/FriendRequestsHighlight.tsx`

### TASK-012: Give each tab its own empty state

`FriendsEmptyState`, extraído de `Friends.tsx`. Cada pestaña lleva su ícono de lucide y su color suave: azul en Amigos, naranja en Recibidas y gris en Enviadas. Muestra "Buscar personas" en Amigos y Enviadas, y el mensaje de "nadie coincide" cuando hay filtro.

**Type:** feature  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Depends on:** TASK-001, TASK-007  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/FriendsEmptyState.tsx`

**Acceptance Criteria:**

1. Lee `committedFilters`, no los filtros en vivo, así que no parpadea mientras se escribe.
2. Recibidas no ofrece "Buscar personas".

**validateCommand:** `npm run types && npx eslint resources/js/modules/friendship/components/FriendsEmptyState.tsx && npx prettier --check resources/js/modules/friendship/components/FriendsEmptyState.tsx`

### TASK-013: Rebuild the friends page with the B design

`Friends.tsx`:

* `main` con `max-w-6xl`;
* el bloque de solicitudes;
* barra sticky con pestañas subrayadas, contadores de color, filtro y `Select` de orden;
* foco en el filtro al montar y al terminar de cargar una pestaña;
* skeleton de carga en lugar del spinner;
* la cuadrícula y el estado vacío.

Se mantiene ocultar la tarjeta que deja de pertenecer a la pestaña y recargar la lista cuando todas quedan ocultas.

**Type:** feature  
**Effort:** L  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-010, TASK-011, TASK-012  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/Friends.tsx`

**Acceptance Criteria:**

1. Ninguna pestaña muestra `Loader2Icon`. Mientras carga, la cuadrícula muestra skeletons y el foco vuelve al filtro al terminar.
2. Los contadores tienen color (azul, naranja y gris oscuro) con contraste ≥ 4.5:1 y su texto accesible con plural.
3. Aceptar una solicitud en Recibidas la quita de la cuadrícula y actualiza los contadores sin perder el scroll, como hoy.

**validateCommand:** `npm run types && npx eslint resources/js/modules/friendship/Friends.tsx && npx prettier --check resources/js/modules/friendship/Friends.tsx`

### TASK-014: Add keyboard shortcuts to the friends page

**`useFriendsShortcuts`** (`@tanstack/react-hotkeys`):

* `/` enfoca el filtro;
* `1`, `2` y `3` cambian de pestaña;
* `o` alterna el orden;
* `Shift+?` abre `FriendsKeyboardShortcutsDialog`, que lista los atajos con `Kbd`.

**Comportamiento:** usa `ignoreInputs` y no actúa con un diálogo o menú abierto. `Friends.tsx` lo conecta.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Depends on:** TASK-013  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/hooks/useFriendsShortcuts.ts`
- `resources/js/modules/friendship/components/FriendsKeyboardShortcutsDialog.tsx`
- `resources/js/modules/friendship/Friends.tsx`

**Acceptance Criteria:**

1. Escribir `/`, `1` u `o` dentro del filtro escribe el carácter y no dispara el atajo.
2. Con el menú de una tarjeta o un diálogo abierto, ningún atajo actúa.
3. El diálogo de ayuda lista cada atajo con su texto traducido.

**validateCommand:** `npm run types && npx eslint resources/js/modules/friendship/hooks/useFriendsShortcuts.ts resources/js/modules/friendship/components/FriendsKeyboardShortcutsDialog.tsx resources/js/modules/friendship/Friends.tsx && npx prettier --check resources/js/modules/friendship/hooks/useFriendsShortcuts.ts resources/js/modules/friendship/components/FriendsKeyboardShortcutsDialog.tsx resources/js/modules/friendship/Friends.tsx`

### TASK-015: Redesign the left sidebar with lucide icons

* **Íconos:** `homeSidebarNavigation.ts` pasa de `react-icons/lu` a `lucide-react` (`House`, `Search`, `Users`, `Bell`, `UserRound`).
* **Estilo:** `HomeSidebarLeft` toma el estilo minimalista de shadcn: etiqueta de sección, ítem activo con fondo suave azul y texto azul, e íconos de 18 px.
* **Badges:** Amigos en azul y Notificaciones en naranja.
* **Reutilización:** exporta la lista de navegación para el menú móvil.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Depends on:** TASK-002  
**Review:** claude

**writeScope:**

- `resources/js/modules/home/components/sidebar/HomeSidebarLeft.tsx`
- `resources/js/modules/home/data/homeSidebarNavigation.ts`

**Acceptance Criteria:**

1. El ítem de la página actual sigue con `aria-current="page"`, y los badges solo aparecen con un valor mayor que 0 (99+ arriba de 99).
2. Ningún archivo de `modules/home` importa `react-icons` para el menú.

**validateCommand:** `npm run types && npx eslint resources/js/modules/home/components/sidebar/HomeSidebarLeft.tsx resources/js/modules/home/data/homeSidebarNavigation.ts && npx prettier --check resources/js/modules/home/components/sidebar/HomeSidebarLeft.tsx resources/js/modules/home/data/homeSidebarNavigation.ts`

### TASK-016: Open the navigation from a sheet on small screens

`HomeMobileNavigation`: un `Sheet` a la izquierda con la lista de navegación exportada en TASK-015. `HomeHeader` muestra el botón de menú solo por debajo de `lg`, con su `aria-label`. El sheet se cierra al navegar.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Depends on:** TASK-015  
**Review:** claude

**writeScope:**

- `resources/js/modules/home/components/sidebar/HomeMobileNavigation.tsx`
- `resources/js/modules/home/components/header/HomeHeader.tsx`

**Acceptance Criteria:**

1. A 375 px de ancho el botón abre el menú con los mismos ítems y badges, y elegir uno cierra el sheet.
2. Desde `lg` el botón no se muestra.

**validateCommand:** `npm run types && npx eslint resources/js/modules/home/components/sidebar/HomeMobileNavigation.tsx resources/js/modules/home/components/header/HomeHeader.tsx && npx prettier --check resources/js/modules/home/components/sidebar/HomeMobileNavigation.tsx resources/js/modules/home/components/header/HomeHeader.tsx`

### TASK-017: Tighten the layout and reserve the right panel

* **`HomeLayout`:** padding central `px-3 lg:px-6`, y los dos sidebars ocultos por debajo de `lg`.
* **`HomeSidebarRight`:** `w-64` como el izquierdo, con el espacio reservado de `home/sidebar.reserved.*`.
* **`FriendRequestsPanel.tsx`:** se borra, porque su trabajo lo hace el bloque de TASK-011.

**Type:** feature  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Depends on:** TASK-002, TASK-011, TASK-015  
**Review:** claude

**writeScope:**

- `resources/js/modules/home/layouts/HomeLayout.tsx`
- `resources/js/modules/home/components/sidebar/HomeSidebarRight.tsx`
- `resources/js/modules/friendship/components/FriendRequestsPanel.tsx`

**Acceptance Criteria:**

1. Los dos sidebars miden lo mismo, y el derecho ya no muestra solicitudes.
2. Nada importa `FriendRequestsPanel`, y `npm run build` y `npm run build:ssr` compilan.

**validateCommand:** `npm run types && npx eslint resources/js/modules/home/layouts/HomeLayout.tsx resources/js/modules/home/components/sidebar/HomeSidebarRight.tsx && npx prettier --check resources/js/modules/home/layouts/HomeLayout.tsx resources/js/modules/home/components/sidebar/HomeSidebarRight.tsx && npm run build`

### TASK-018: Move the browser test to the requests block and drop the old panel texts

* **`FriendshipFriendsBrowserTest`:** acepta la solicitud desde el bloque de la vista de amigos en lugar del sidebar derecho, y comprueba la tarjeta, el contador de color y el badge.
* **Textos:** se quitan las claves `home/sidebar.friend_requests.*`, que ya nada usa.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-013, TASK-017  
**Review:** claude

**writeScope:**

- `app/Modules/Friendship/Tests/Browser/FriendshipFriendsBrowserTest.php`
- `lang/en/home/sidebar.php`
- `lang/es/home/sidebar.php`

**Acceptance Criteria:**

1. La prueba acepta desde el bloque, y la tarjeta aparece en Amigos con el badge del menú en uno menos.
2. `FriendshipNotificationsBrowserTest` y las pruebas de traducción siguen pasando sin `friend_requests`.

**validateCommand:** `composer test:browser && php -d memory_limit=2G vendor/bin/pest --compact tests/Feature/Translation`

### TASK-019: Document the redesigned friends page and layout

* **`friendship.md`:**
  * en Endpoints, `sort` y `mutualFriendsCount`;
  * en Shared props, `recent` de 2 para el bloque;
  * en Frontend, las tarjetas, `<InfiniteScroll>`, los atajos, el bloque, el layout y el menú móvil.
* **`CLAUDE.md`:** agregar `FriendshipListSort` a la lista de enums con espejo en TypeScript.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Depends on:** TASK-014, TASK-016, TASK-018  
**Review:** claude

**writeScope:**

- `docs/architecture/modules/friendship.md`
- `CLAUDE.md`

**Acceptance Criteria:**

1. La doc describe cada contrato del plan tal como quedó en el código.
2. `CLAUDE.md` lista `FriendshipListSort` junto a `FriendshipListTab`.

**validateCommand:** `grep -n "FriendshipListSort" CLAUDE.md docs/architecture/modules/friendship.md`

### TASK-020: Run the quality gates and check the pages by hand

**Gates:**

1. Pint, PHPStan y Rector;
2. `composer test`, `composer test:browser` y `composer doctor`;
3. `npm run format:check`, `npm run lint:check`, `npm run types`, `npm run build` y `npm run build:ssr`.

**Revisión a mano** (Chrome, en/es, claro y oscuro, 1440 px y 375 px):

* las pestañas;
* el orden;
* los atajos;
* el bloque;
* el menú móvil.

**Type:** chore  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-019  
**Review:** claude

**writeScope:**

- (sin archivos: verificación)

**Acceptance Criteria:**

1. Todos los gates pasan sin cambios pendientes de Pint ni de Rector.
2. En la revisión a mano no hay errores de consola, y el foco y los atajos funcionan en los dos idiomas.

**validateCommand:** `composer phpstan && composer rector-dry && composer test && composer doctor && npm run format:check && npm run lint:check && npm run types && npm run build && npm run build:ssr`

## Failure Modes

- **Orden por nombre con `cursorPaginate`:** el cursor necesita una columna real.
  * TASK-005 une `users` con la otra persona y ordena por `other_users.name` + `friendships.id`.
  * Un alias calculado rompería la segunda página; lo cubre la prueba del cursor.
- **`mutual_friends_count` faltante:** el resource lanza `LogicException`, así que `FriendshipProps` (TASK-006) debe seleccionarlo. Hasta que TASK-006 termine, `FriendshipPropsTest` queda en rojo; por eso TASK-006 depende de TASK-005.
- **DOM pesado:** sin virtualizar, la página crece con cientos de tarjetas. Se aceptó con el volumen actual; si molesta, se virtualiza por filas en otra tarea.
- **`FriendRequestsPanel` borrado antes de tiempo:** TASK-017 depende de TASK-011, así que el bloque nuevo existe antes del borrado.
- **Pruebas de navegador:** `FriendshipFriendsBrowserTest` busca el texto del panel viejo y queda en rojo entre TASK-017 y TASK-018. Por eso TASK-018 corre `composer test:browser` como validación.
- **Atajos dentro del filtro:** escribir `1` o `o` en el filtro no debe cambiar de pestaña. TASK-014 usa `ignoreInputs: true` y lo prueba a mano en TASK-020.

## Ship Cut

* **Mínimo entregable:** TASK-001, TASK-003 a TASK-011, TASK-013, TASK-017 y TASK-018: la vista B con orden, amigos en común, bloque, skeleton y foco, más el panel derecho reservado.
* **Recortables como bloque:** los atajos (TASK-014), los estados vacíos (TASK-012) y el menú móvil (TASK-016).
* **No recortables:** la doc y los gates.

## Test Coverage Map

| Comportamiento | Tarea |
| --- | --- |
| Paridad de textos de amigos y del layout | TASK-001, TASK-002 |
| Validación de `sort` | TASK-003 |
| Amigos en común compartidos con la búsqueda | TASK-004 |
| Orden, cursor, amigos en común y consultas de la lista | TASK-005 |
| `friendRequests.recent` con 2 y amigos en común | TASK-006 |
| Aceptar desde el bloque y tarjeta en Amigos (navegador) | TASK-018 |
| Tipos, lint y builds del frontend | TASK-007 a TASK-017, TASK-020 |
| Foco, atajos, responsive y modo oscuro (a mano) | TASK-020 |

## Execution Summary

- **Tareas:** 20
- **Capa 1:** TASK-001, TASK-002, TASK-004, TASK-008
- **Capa 2:** TASK-003, TASK-015
- **Capa 3:** TASK-005, TASK-016
- **Capa 4:** TASK-006, TASK-007
- **Capa 5:** TASK-009, TASK-011, TASK-012
- **Capa 6:** TASK-010, TASK-017
- **Capa 7:** TASK-013
- **Capa 8:** TASK-014, TASK-018
- **Capa 9:** TASK-019
- **Capa 10:** TASK-020
- **Ruta crítica:** TASK-001 → TASK-003 → TASK-005 → TASK-007 → TASK-009 → TASK-010 → TASK-013 → TASK-014 → TASK-019 → TASK-020

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | TASK-001 |
| TASK-004 | — |
| TASK-005 | TASK-003, TASK-004 |
| TASK-006 | TASK-005 |
| TASK-007 | TASK-005 |
| TASK-008 | — |
| TASK-009 | TASK-001, TASK-007, TASK-008 |
| TASK-010 | TASK-009 |
| TASK-011 | TASK-001, TASK-006, TASK-007, TASK-008 |
| TASK-012 | TASK-001, TASK-007 |
| TASK-013 | TASK-010, TASK-011, TASK-012 |
| TASK-014 | TASK-013 |
| TASK-015 | TASK-002 |
| TASK-016 | TASK-015 |
| TASK-017 | TASK-002, TASK-011, TASK-015 |
| TASK-018 | TASK-013, TASK-017 |
| TASK-019 | TASK-014, TASK-016, TASK-018 |
| TASK-020 | TASK-019 |
