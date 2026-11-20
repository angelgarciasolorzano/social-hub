# Plan: SOC-86 — Friends' posts in the home feed

**Linear:** SOC-86 (épica SOC-82) · **Modo:** EXPANSION · **Revisión por defecto:** claude

## Overview

Hoy el inicio lista publicaciones de cualquier persona, así que tener amigos no cambia nada. Este plan hace que el inicio muestre solo las publicaciones propias y las de los amigos aceptados, y aprovecha para dejar el inicio sin errores y más claro:

* **Feed de amigos:** un scope en `Post` con una subconsulta, un índice nuevo en `posts` y un estado vacío traducido que lleva a la búsqueda de personas.
* **Integridad de `posts`:** la migración pasa al módulo Post, se agrega la llave foránea de `user_id`, y borrar la cuenta borra antes las publicaciones con sus likes, comentarios e imágenes.
* **Errores del inicio:** el bucle `Maximum update depth` después del login y el doble `createRoot` de React, ya diagnosticados.
* **Experiencia del feed:** aviso de "cargando más" y de fin del feed, y el panel de solicitudes recarga lo que depende de la amistad en la página donde estás (en el inicio, el feed).

## Scope Challenge

**Qué existe hoy:**

- `app/Home/Controllers/HomeController.php` lista `Post::with(['user.media', 'media'])->withEngagementFor($viewer)->orderByDesc('id')->cursorPaginate(10)` de cualquier autor, con el filtro por amigos comentado.
- `Post::withEngagementFor(User)` ya precarga contadores y "me gusta" en la misma consulta; `PostResource` trae su propio autor.
- `User::friends()` (desde SOC-83) devuelve un `Builder<User>` que une los ids de amigos por los dos índices `(<lado>_id, status)`.
- `posts` solo tiene la clave primaria: `foreignIdFor(User::class)` sin `->constrained()` no crea índice ni llave foránea. La migración sigue en `database/migrations/` aunque Post ya está en `app/Modules/Post/`.
- `UserController::destroy` borra la cuenta con `$user->delete()` y deja sus publicaciones huérfanas. No existe ninguna ruta que borre publicaciones.
- `Home.tsx` tiene su propio `useVirtualizer` sin aviso de carga ni de fin, y no tiene estado vacío.
- `FriendRequestsPanel` (SOC-84) recarga solo `friendRequests` después de aceptar o rechazar.

**Qué se reutiliza:**

- `User::friends()` como subconsulta de ids de amigos.
- `VirtualInfiniteList` de SOC-84, que pasa a `shared/components/` porque ahora tiene tres usos.
- El patrón de `CommentMigration` de SOC-46: mover una migración al módulo con su nombre original y registrarla con `loadMigrationsFrom`.
- `home/sidebar` y la prueba de paridad de `lang/*/home/` (SOC-84) para el catálogo nuevo `home/feed`.

**Qué es nuevo:**

- El scope `Post::visibleInFeedOf(User)` y el método `Post::deleteWithEngagement()`.
- Una migración con el índice `(user_id, id)` y la llave foránea de `posts.user_id`.
- El catálogo `home/feed` (estado vacío, carga, fin del feed).
- `DocumentLanguageSync` en su propio archivo y las devtools de TanStack solo en desarrollo.

**Decisiones confirmadas:**

- **Feed** (alcance de Linear): publicaciones propias y de amigos aceptados, sin autores suspendidos, ordenadas por id descendente como hoy, con una subconsulta y no ids cargados en PHP.
- **Índice `(user_id, id)`**: sirve al filtro por autor y al orden por id, y también le sirve a la llave foránea, así que no hace falta otro índice.
- **Migración en el módulo** (decisión del usuario): `create_posts_table` pasa a `app/Modules/Post/Database/Migrations/` con su nombre original, así una base que ya la corrió no la repite.
- **Llave foránea con `cascadeOnDelete`** (extra del usuario): como `likes` no tiene llave foránea y los comentarios son polimórficos, la cascada sola dejaría likes, comentarios e imágenes huérfanos. Por eso borrar la cuenta borra antes cada publicación con `Post::deleteWithEngagement()`, y la cascada queda como red de seguridad. La migración falla con un mensaje claro si hay publicaciones huérfanas, en vez de borrarlas en silencio.
- **Errores del inicio, ya diagnosticados** (pedido del usuario):
  * *Maximum update depth / getSnapshot*: en `useNotificationDropdown` y `useNotificationHistory`, los selectores de Zustand devuelven un `[]` nuevo cuando el store todavía no está iniciado para el usuario (justo después del login). `useSyncExternalStore` ve un valor distinto en cada lectura y renderiza sin fin. Se corrige con un arreglo vacío constante.
  * *Doble `createRoot` y `removeChild`*: `app.tsx` define un componente (`DocumentLanguageSync`), así que Fast Refresh le agrega un autoimport. Después de editar con Vite corriendo, ese autoimport lleva `?t=` y el navegador ejecuta dos copias de `app.tsx`, cada una con su `createInertiaApp`. Se corrige sacando el componente del archivo de entrada.
- **Extras de EXPANSION** (usuario): recargar el feed al aceptar desde el panel, traducir los textos del feed y la llave foránea. **Agregados por mí** como mejoras de la interfaz: el aviso de fin del feed, "cargando más", el panel que recarga lo que depende de la amistad en cualquier página (feed, contadores de la página de amigos, datos del perfil) y las devtools solo en desarrollo.

**¿Ejecución directa?** No: son unos 25 archivos entre migraciones, backend, frontend, pruebas y docs.

## Prerequisites

- SOC-84 está mergeada: `User::friends()`, `FriendRequestsPanel`, `VirtualInfiniteList` y la prueba de paridad de `lang/*/home/`.
- La base local no tiene publicaciones huérfanas (comprobado: 0 de 2628).

## Non-Goals

- Privacidad por publicación, un algoritmo de ranking y sugerencias de personas (fuera de alcance en Linear).
- Notificaciones y activity log de amistades (SOC-85) y Filament (SOC-87).
- Una ruta o un botón para borrar una publicación: solo se agrega el método que usa el borrado de la cuenta.
- Los comentarios y likes que la persona dejó en publicaciones de otros al borrar su cuenta: se conservan como hoy.
- Traducir `PostCard` y el header de `home`, que siguen en español a mano.

## Contracts

### Scope del feed

`Post::visibleInFeedOf(User $user)` (`#[Scope]`): publicaciones cuyo `user_id` es el del usuario o está en `$user->friends()->select('users.id')`, y cuyo autor está activo. No carga ids en PHP y se combina con `withEngagementFor()` sin consultas extra.

### Props del inicio

`home/Home` recibe `posts` como hoy (`Inertia::scroll`). `HomeController` registra el grupo `home/feed` además de `notification/center`.

### Esquema de posts

Índice `posts_user_id_id_index` sobre `(user_id, id)` y llave foránea `posts_user_id_foreign` a `users.id` con `cascadeOnDelete`.

### Borrado de la cuenta

Antes de `$user->delete()`, `UserController::destroy` llama a `deleteWithEngagement()` en cada publicación del usuario. Ese método, en una transacción, borra los likes de la publicación y de sus comentarios, fuerza el borrado de los comentarios (usan `SoftDeletes`) y borra la publicación con Eloquent, para que MediaLibrary quite su imagen.

### Lista virtual compartida

`resources/js/shared/components/VirtualInfiniteList.tsx` con las props de SOC-84 más un `endLabel` opcional, que se muestra cuando `usePage().scrollProps[data].nextPage` es `null`.

## Existing Code Leverage

- `app/Home/Controllers/HomeController.php`: la consulta del feed que se cambia.
- `app/Modules/Post/Models/Post.php`: `withEngagementFor()` como ejemplo de `#[Scope]`.
- `app/User/Models/User.php`: `friends()`.
- `app/Modules/Comment/Providers/CommentServiceProvider.php`: `loadMigrationsFrom` de un módulo.
- `app/Modules/Comment/Controllers/CommentController.php`: borra los likes de un comentario de forma explícita.
- `resources/js/modules/friendship/components/VirtualInfiniteList.tsx`: la lista que se comparte.

## Tasks

### TASK-001: Add the home feed texts in English and Spanish

Crear `lang/{en,es}/home/feed.php` con los textos del feed:
* el estado vacío: título, descripción y el enlace "Buscar personas";
* "cargando más publicaciones" y el aviso de fin del feed ("Ya viste todas las publicaciones").

`SettingTranslationKeysTest` ya recorre `lang/*/home/` desde SOC-84.

**Type:** feature  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `lang/en/home/feed.php`
- `lang/es/home/feed.php`

**Acceptance Criteria:**

1. Los dos idiomas tienen las mismas claves y placeholders.
2. La prueba de paridad falla si se borra una clave en `es`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact tests/Feature/Translation/SettingTranslationKeysTest.php`

### TASK-002: Move the posts migration into the Post module

Mover `database/migrations/2025_09_25_202237_create_posts_table.php` a `app/Modules/Post/Database/Migrations/2025_09_25_202237_create_posts_table.php` sin cambiar su nombre ni su contenido, y registrar la carpeta en `PostServiceProvider::boot()` con `loadMigrationsFrom`, como `CommentServiceProvider`.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/Post/Database/Migrations/2025_09_25_202237_create_posts_table.php`
- `database/migrations/2025_09_25_202237_create_posts_table.php`
- `app/Modules/Post/Providers/PostServiceProvider.php`

**Acceptance Criteria:**

1. `php artisan migrate:status` muestra la migración como ejecutada, y `php artisan migrate --pretend` no tiene nada que correr en la base local.
2. La carpeta global ya no tiene el archivo, y la suite de Post pasa con las bases de prueba recreadas (`composer test:recreate`).

**validateCommand:** `php artisan migrate:status && php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Post/Tests`

### TASK-003: Index posts by author and add the user foreign key

Nueva migración `app/Modules/Post/Database/Migrations/2026_11_20_120000_add_user_index_and_foreign_key_to_posts_table.php`:
* si hay publicaciones cuyo `user_id` no existe, lanza una excepción con cuántas son, en vez de borrarlas;
* agrega el índice `(user_id, id)` y la llave foránea a `users.id` con `cascadeOnDelete`;
* `down()` quita primero la llave foránea y después el índice.

**Prueba:** `app/Modules/Post/Tests/Database/PostsTableTest.php`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002  
**Review:** claude

**writeScope:**

- `app/Modules/Post/Database/Migrations/2026_11_20_120000_add_user_index_and_foreign_key_to_posts_table.php`
- `app/Modules/Post/Tests/Database/PostsTableTest.php`

**Acceptance Criteria:**

1. `Schema::getIndexes('posts')` incluye `(user_id, id)` y `Schema::getForeignKeys('posts')` la llave a `users` con `cascade`.
2. Insertar una publicación con un `user_id` inexistente falla por la llave foránea.
3. La migración se revierte y se vuelve a aplicar sin errores (`migrate:rollback --step=1` y `migrate`).

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Post/Tests/Database`

### TASK-004: Add the home feed scope to Post

`Post::visibleInFeedOf(User $user)` (`#[Scope]`): publicaciones del usuario o de sus amigos aceptados, con una subconsulta sobre `$user->friends()`, y solo de autores activos.

**Prueba:** `app/Modules/Post/Tests/Models/PostFeedScopeTest.php`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `app/Modules/Post/Tests/Models/PostFeedScopeTest.php`
- `app/Modules/Post/Models/Post.php`

**Acceptance Criteria:**

1. Incluye las publicaciones propias y de amigos aceptados, de los dos lados del par.
2. Excluye a desconocidos, a solicitudes pendientes en los dos sentidos, a amistades eliminadas y a autores suspendidos.
3. La consulta no carga ids en PHP: el SQL contiene la subconsulta de `friendships`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Post/Tests/Models/PostFeedScopeTest.php`

### TASK-005: Delete the user's posts with their engagement when the account is deleted

`Post::deleteWithEngagement()`: en una transacción, borra los likes de la publicación y de sus comentarios, fuerza el borrado de los comentarios y borra la publicación con Eloquent (MediaLibrary quita su imagen).

`UserController::destroy` lo llama para cada publicación del usuario antes de `$user->delete()`.

**Prueba:** `app/User/Modules/AccountSettings/Tests/Crud/AccountDeletionPostsTest.php`.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-003, TASK-004  
**Review:** claude

**writeScope:**

- `app/User/Modules/AccountSettings/Tests/Crud/AccountDeletionPostsTest.php`
- `app/Modules/Post/Models/Post.php`
- `app/User/Controllers/UserController.php`

**Acceptance Criteria:**

1. Después de borrar la cuenta no quedan sus publicaciones, ni los likes ni los comentarios (tampoco los borrados lógicamente) de esas publicaciones, ni su imagen en el disco falso.
2. Los comentarios y likes que la persona dejó en publicaciones de otros siguen existiendo.
3. Con la contraseña equivocada no se borra nada.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/User/Modules/AccountSettings/Tests/Crud`

### TASK-006: Serve the friends feed on the home page

`HomeController` usa `visibleInFeedOf($viewer)` junto con `withEngagementFor()`, registra el grupo `home/feed` y se borra el código comentado.

**Pruebas:** `app/Modules/Post/Tests/Queries/HomeFeedTest.php` (contenido del feed y consultas constantes). `PostListingTest` se ajusta: sus casos del inicio crean publicaciones de amigos, porque las de desconocidos ya no aparecen.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-001, TASK-004  
**Review:** claude

**writeScope:**

- `app/Modules/Post/Tests/Queries/HomeFeedTest.php`
- `app/Home/Controllers/HomeController.php`
- `app/Modules/Post/Tests/Queries/PostListingTest.php`

**Acceptance Criteria:**

1. El inicio muestra solo publicaciones propias y de amigos, ordenadas por id descendente, y la página siguiente con el cursor sigue filtrada.
2. El número de consultas es el mismo con 2 y con 30 amigos.
3. El inicio comparte `translations.home/feed`.

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Post/Tests/Queries`

### TASK-007: Stop the notification store selectors from looping after login

En `useNotificationDropdown.ts` y `useNotificationHistory.ts`, los selectores devuelven una constante `EMPTY_NOTIFICATIONS` (definida fuera del hook) en lugar de un `[]` nuevo cuando el store no es del usuario actual.

`LoginBrowserTest` agrega un caso que inicia sesión con el formulario, llega a `/home` y llama a `assertNoJavaScriptErrors()`.

**Type:** bugfix  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `resources/js/modules/notification/hooks/useNotificationDropdown.ts`
- `resources/js/modules/notification/hooks/useNotificationHistory.ts`
- `app/Auth/Modules/Login/Tests/Browser/LoginBrowserTest.php`

**Acceptance Criteria:**

1. Ningún selector de `useNotificationStore` devuelve un arreglo u objeto literal nuevo (`grep` sin resultados).
2. Iniciar sesión lleva a `/home` con la página dibujada y sin errores de JavaScript, en la prueba de navegador y a mano con Vite.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && composer test:browser`

### TASK-008: Keep the app entry free of components and load the devtools only in development

Mover `DocumentLanguageSync` de `resources/js/app/app.tsx` a `resources/js/shared/components/DocumentLanguageSync.tsx`, para que Fast Refresh no le agregue a `app.tsx` un autoimport que, después de una edición, ejecuta dos copias del archivo.

`TanStackDevtools` se monta solo con `import.meta.env.DEV`.

**Type:** bugfix  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P0  
**Depends on:** none  
**Review:** claude

**writeScope:**

- `resources/js/shared/components/DocumentLanguageSync.tsx`
- `resources/js/app/app.tsx`

**Acceptance Criteria:**

1. Con Vite corriendo, después de editar un archivo y recargar, el navegador carga `app.tsx` una sola vez y la consola no tiene `createRoot` ni `removeChild`.
2. El build de producción no dibuja las devtools, y el idioma del documento sigue cambiando al navegar.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && npm run build && npm run build:ssr`

### TASK-009: Share the virtual infinite list and use it in the home feed

Crear `resources/js/shared/components/VirtualInfiniteList.tsx` a partir del de friendship, con un `endLabel` opcional que se muestra cuando `usePage().scrollProps[data].nextPage` es `null`.

`Home.tsx` lo usa en lugar de su propio virtualizador, con "cargando más", el aviso de fin y el estado vacío (título, descripción y enlace a la búsqueda), todo desde `home/feed`.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-001  
**Review:** claude

**writeScope:**

- `resources/js/shared/components/VirtualInfiniteList.tsx`
- `resources/js/modules/home/Home.tsx`

**Acceptance Criteria:**

1. Sin publicaciones, el inicio muestra el estado vacío traducido con el enlace a la búsqueda.
2. Al llegar al final del feed aparece el aviso de fin, y no aparece mientras queden páginas.
3. `Home.tsx` ya no importa `useVirtualizer` ni `InfiniteScroll`.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && php -d memory_limit=2G vendor/bin/pest --compact tests/Feature/Translation/FrontendTranslationKeysTest.php`

### TASK-010: Point the friendship pages to the shared list

`Search.tsx` y `Friends.tsx` importan la lista de `resources/js/shared/components/`, y se borra `resources/js/modules/friendship/components/VirtualInfiniteList.tsx`.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Depends on:** TASK-009  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/Search.tsx`
- `resources/js/modules/friendship/Friends.tsx`
- `resources/js/modules/friendship/components/VirtualInfiniteList.tsx`

**Acceptance Criteria:**

1. No queda ningún import de `modules/friendship/components/VirtualInfiniteList`.
2. Las pruebas de navegador de la búsqueda y de la página de amigos siguen pasando sin cambios.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && composer test:browser`

### TASK-011: Reload what depends on the friendship after acting from the requests panel

Al aceptar o rechazar desde `FriendRequestsPanel`, además de `friendRequests` se recargan las props de la página actual que dependen de la amistad, según `usePage().component`:
* `home/Home`: `posts` con `reset`, para que aparezcan las publicaciones del nuevo amigo;
* `friendship/Friends`: `tabCounts` y `friendships` con `reset`;
* `profile/Profile`: `profileFriendship`.

**Type:** feature  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Depends on:** TASK-006  
**Review:** claude

**writeScope:**

- `resources/js/modules/friendship/components/FriendRequestsPanel.tsx`

**Acceptance Criteria:**

1. Aceptar desde el panel en el inicio muestra las publicaciones del nuevo amigo sin recargar la página (se prueba en TASK-012).
2. En una página sin esas props (por ejemplo, Notificaciones) solo se recarga `friendRequests`.

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-012: Add a browser test for the friends feed

`app/Modules/Post/Tests/Browser/HomeFeedBrowserTest.php`:
* un usuario ve la publicación de un amigo y no la de un desconocido;
* acepta una solicitud desde el panel y aparece la publicación de esa persona;
* un usuario sin amigos ni publicaciones ve el estado vacío y su enlace lleva a la búsqueda;
* `assertNoJavaScriptErrors()`.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-006, TASK-009, TASK-011  
**Review:** claude

**writeScope:**

- `app/Modules/Post/Tests/Browser/HomeFeedBrowserTest.php`

**Acceptance Criteria:**

1. La prueba recorre el feed, aceptar desde el panel y el estado vacío, y pasa con `composer test:browser`.
2. Falla si el feed vuelve a mostrar publicaciones de desconocidos.

**validateCommand:** `composer test:browser`

### TASK-013: Document the feed, the posts schema and the account deletion

`docs/architecture/modules/content.md`: el scope del feed, la migración movida, el índice, la llave foránea y `deleteWithEngagement()`.

`CLAUDE.md`: el mapa de módulos y una invariante: borrar una cuenta borra antes sus publicaciones con su actividad, porque `likes` no tiene llave foránea.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Depends on:** TASK-005, TASK-006  
**Review:** claude

**writeScope:**

- `docs/architecture/modules/content.md`
- `CLAUDE.md`

**Acceptance Criteria:**

1. Cada clase, método y migración que se nombra existe (`grep`).
2. `content.md` ya no dice que `create_posts_table` vive en la carpeta global ni que el inicio lista publicaciones de cualquier persona.

**validateCommand:** `grep -n 'visibleInFeedOf' docs/architecture/modules/content.md CLAUDE.md`

### TASK-014: Document the shared list, the panel reloads and the fixed frontend errors

`docs/architecture/modules/friendship.md`: la ruta nueva de `VirtualInfiniteList`, las recargas del panel por página y quitar la nota del bucle del login.

`docs/architecture/modules/notification.md`: por qué los selectores usan una constante.

`docs/architecture/infrastructure.md`: `app.tsx` sin componentes y las devtools solo en desarrollo.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Depends on:** TASK-007, TASK-008, TASK-010, TASK-011  
**Review:** claude

**writeScope:**

- `docs/architecture/modules/friendship.md`
- `docs/architecture/modules/notification.md`
- `docs/architecture/infrastructure.md`

**Acceptance Criteria:**

1. `friendship.md` ya no menciona el bucle del login como problema conocido ni la ruta vieja de la lista.
2. Cada archivo que se nombra existe.

**validateCommand:** `! grep -n 'Maximum update depth' docs/architecture/modules/friendship.md`

### TASK-015: Run the full gates

1. Pint, PHPStan y Rector (`rector-clear-cache` → `rector-dry` → `rector`);
2. `composer test:recreate`, `composer test` y `composer test:browser`;
3. `composer doctor`;
4. `npm run format:check`, `lint:check`, `types`, `build` y `build:ssr`;
5. a mano con Vite y el panel al frente: iniciar sesión, el feed en claro y oscuro, bajar hasta el aviso de fin, y la consola sin errores.

**Type:** chore  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P3  
**Depends on:** TASK-012, TASK-013, TASK-014  
**Review:** claude

**writeScope:**

- (sin archivos: verificación)

**Acceptance Criteria:**

1. Pint, PHPStan y Rector no reportan cambios ni errores, y las tres suites pasan.
2. Después de iniciar sesión la consola no muestra `Maximum update depth`, `getSnapshot`, `createRoot` ni `removeChild`.

**validateCommand:** `composer phpstan && composer rector-dry && composer test && composer test:browser && npm run build && npm run build:ssr`

## Failure Modes

- Una base con publicaciones huérfanas: la migración de TASK-003 falla con el número de filas y no borra nada en silencio.
- Borrar la cuenta sin limpiar antes: la cascada borraría las publicaciones y dejaría likes, comentarios e imágenes huérfanos. TASK-005 lo evita y su prueba lo comprueba.
- Un selector de Zustand con un literal nuevo vuelve a provocar el bucle: TASK-007 deja una prueba de navegador del login y TASK-014 lo documenta.
- Recargar `posts` sin `reset` después de aceptar: `Inertia::scroll` sumaría la primera página al final del feed.
- Las pruebas del inicio que crean publicaciones de desconocidos dejan de verlas: TASK-006 ajusta `PostListingTest`.

## Ship Cut

El mínimo entregable es TASK-001, TASK-004, TASK-006, TASK-007, TASK-008 y TASK-009: el feed de amigos con su estado vacío y el inicio sin errores. El índice y la llave foránea (TASK-002, TASK-003 y TASK-005) se recortan juntos como bloque; TASK-010 y TASK-011 son recortables. La documentación y los gates no se recortan.

## Test Coverage Map

| Comportamiento | Tarea |
| --- | --- |
| Paridad de home/feed | TASK-001 |
| Índice y llave foránea de posts | TASK-003 |
| Scope del feed: amigos, pendientes, eliminados, suspendidos | TASK-004 |
| Borrado de la cuenta con su actividad | TASK-005 |
| Feed del inicio y consultas constantes | TASK-006 |
| Login sin errores de JavaScript | TASK-007 |
| Búsqueda y amigos con la lista compartida | TASK-010 |
| Feed, aceptar desde el panel y estado vacío en el navegador | TASK-012 |

## Execution Summary

- **Tareas:** 15
- **Capa 1:** TASK-001, TASK-002, TASK-004, TASK-007, TASK-008
- **Capa 2:** TASK-003, TASK-006, TASK-009
- **Capa 3:** TASK-005, TASK-010, TASK-011
- **Capa 4:** TASK-012, TASK-013, TASK-014
- **Capa 5:** TASK-015
- **Ruta crítica:** TASK-001 → TASK-006 → TASK-011 → TASK-012 → TASK-015

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | TASK-002 |
| TASK-004 | — |
| TASK-005 | TASK-003, TASK-004 |
| TASK-006 | TASK-001, TASK-004 |
| TASK-007 | — |
| TASK-008 | — |
| TASK-009 | TASK-001 |
| TASK-010 | TASK-009 |
| TASK-011 | TASK-006 |
| TASK-012 | TASK-006, TASK-009, TASK-011 |
| TASK-013 | TASK-005, TASK-006 |
| TASK-014 | TASK-007, TASK-008, TASK-010, TASK-011 |
| TASK-015 | TASK-012, TASK-013, TASK-014 |
