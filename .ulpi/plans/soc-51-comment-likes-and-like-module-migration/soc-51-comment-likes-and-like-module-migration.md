# Plan: SOC-51 (sub-tarea 2 de SOC-49): Me gusta en comentarios y migración del módulo Like a app/Modules/Like/

## Overview

El módulo Like sigue en la arquitectura vieja (app/Like/Models/Like.php, con su factory en database/factories/ y su migración en database/migrations/) y no existe ninguna ruta ni UI para dar me gusta: el botón 'Me gusta' de CommentItem es estático. Este plan (1) migra Like a app/Modules/Like/ con el patrón de SOC-38/40 (crear lo nuevo, repuntar importadores, borrar lo viejo; la migración conserva su nombre), (2) añade una migración con índice único (user_id, likeable_type, likeable_id), (3) agrega el endpoint de alternar me gusta en comentarios (POST comments/{comment}/like, throttle propio) y (4) expone likesInfo {count, likedByMe} en los comentarios sin N+1 y con botón optimista en el frontend. Desbloquea SOC-52 (editar/eliminar comentarios propios), que decidirá qué pasa con los likes al borrar un comentario.

**Issue:** [SOC-51](https://linear.app/social-hub-ang/issue/SOC-51/feature-add-likes-to-comments-and-migrate-the-like-module-into)  
**Modo:** HOLD · **Revisión por defecto:** claude  
**Estilo de ejecución:** Paso a paso: una tarea y pausa para revisión (regla .ai/rules/linear.md).

## Scope Challenge

Modo HOLD con decisiones del usuario: toggle único por usuario (índice único en BD), contador y 'me gusta' propio sin N+1, throttle propio y migración + factory movidas al módulo. Solo comentarios: likes en posts, notificaciones por likes y ordenar comentarios por relevancia quedan fuera (descripción de SOC-49). Hallazgos del repositorio: la tabla likes usa foreignIdFor(User::class) sin constraint, así que no hay FK; no existe LikeSeeder ni LikeFactory útil (definition() vacío); CLAUDE.md y la morph map (AppServiceProvider) referencian App\Like\Models\Like; los widgets de Admin (PostsOverview, PostActivityOverview) y tres tests importan la clase vieja. La migración se mueve con su nombre original (patrón SOC-46) para no re-ejecutarse. No hay infraestructura de tests de frontend, así que la UI se valida con tsc/lint y verificación visual.

## Prerequisites

- SOC-41 y SOC-46 están en main: existen CommentStoreController, CommentThreadProps, CommentResource y modules/comment/.
- Se leyeron .ai/rules/{app,backend,frontend,linear}.md: sin variables de una letra, docblocks con máximo 3 líneas de prosa, factories completas, un test = una conducta, Resources para serializar modelos.
- La migración create_likes_table no se edita: se mueve con el mismo nombre; el índice único llega en una migración nueva.
- Working tree limpio y rama de GitButler propia para SOC-51 (rama sugerida: soc-51-feature-add-likes-to-comments-and-migrate-the-like-module).
- Inertia v3: confirmar con la documentación (useHttp) el transporte para una respuesta JSON antes de TASK-013.

## Non-Goals

- Me gusta en posts (aunque Post::likes() ya exista) y mostrarlos en la UI de posts.
- Notificaciones por likes o menciones.
- Editar o eliminar comentarios y decidir qué pasa con sus likes (SOC-52).
- Ordenar comentarios por relevancia o por likes.
- Lista de 'quién dio me gusta' ni moderación de likes desde Admin.
- Contador real de comentarios, orden de respuestas y contador de caracteres (sub-tarea 1).
- Añadir una FK real a likes.user_id o limpiar likes huérfanos de usuarios eliminados.

## Contracts

- Estructura: app/Modules/Like/{Controllers,Database/{Factories,Migrations,Seeders},Models,Providers,Tests,routes}; namespace App\Modules\Like\…. LikeServiceProvider registrado en bootstrap/providers.php; registra LikeRouteServiceProvider y llama loadMigrationsFrom.
- Migración create_likes_table: mismo nombre de archivo (2025_09_26_144232_create_likes_table.php), contenido idéntico, ubicada en app/Modules/Like/Database/Migrations/. La morph map conserva la clave 'like' (Like::MORPH_NAME) y MORPH_COLUMN = 'likeable'.
- Índice único: unique(user_id, likeable_type, likeable_id), añadido por migración nueva que primero depura duplicados (conserva el id menor).
- Endpoint: POST comments/{comment}/like, name comments.like, middleware auth + verified + throttle:30,1,comments.like. Respuesta 200 JSON {liked: bool, likesCount: int}. 404 si el comentario no existe, 429 con Retry-After al excederse. Cualquier usuario verificado puede dar like a cualquier comentario, incluido el propio y las respuestas. Sin flash.
- Concurrencia: alternar dentro de DB::transaction; una UniqueConstraintViolationException durante la creación se trata como 'el like ya existe' y la respuesta refleja el estado real.
- Lectura: CommentResource añade likesInfo {count:int, likedByMe:bool}. CommentThreadProps precarga withCount('likes') y withExists(likes as liked_by_me) en la misma consulta paginada; el número de consultas es constante. Comment::likesCount() e isLikedBy(User) reutilizan los atributos precargados y solo consultan si faltan.
- Frontend: Comment.likesInfo en types/comment.ts; useCommentLike (estado optimista con reversión, ignora llamadas mientras isPending) y CommentLikeButton usados desde CommentItem para comentarios y respuestas; acciones de Wayfinder importadas de @/shared/wayfinder/actions/App/Modules/Like/Controllers/CommentLikeToggleController.
- Protecciones de SOC-40/41/46 intactas: throttle:6,1,comments.store, sobre del paginador, profundidad de un nivel y consultas constantes.

## Existing Code Leverage

- app/Like/Models/Like.php: relaciones user() y likeable() y constantes MORPH_NAME/MORPH_COLUMN, que se trasladan tal cual.
- app/Modules/Comment/Providers/{CommentServiceProvider,CommentRouteServiceProvider}.php: plantilla de los providers del módulo Like.
- app/Modules/Comment/Database/Factories/CommentFactory.php y app/Modules/Post/Database/Seeders/PostSeeder.php: patrón de factory/seeder dentro del módulo.
- Comment::repliesCount() con withCount('comments'): patrón a seguir para likesCount().
- app/Modules/Comment/Props/CommentThreadProps.php y Tests/Security/CommentQueryCountTest.php: precarga y verificación de consultas constantes.
- app/Modules/Comment/Tests/Security/CommentThrottleTest.php: patrón de pruebas de límite de peticiones.
- App\Support\Seeding\SkewedRandom: conteos aleatorios sesgados para el seeder.
- resources/js/modules/comment/hooks/useCommentPaginated.ts: patrón de errores sin modal de Inertia (onHttpException/onNetworkError devuelven false).
- Button (ghost, xs) y tokens del tema ya usados en CommentItem.tsx.
- Documentación externa (no código local): Inertia v3 useHttp, consultada vía search-docs / servidor MCP de Inertia.

## Tasks

### TASK-001: Crear el módulo Like: modelo, factory completa y provider

Crea (sin borrar todavía lo antiguo) el modelo App\Modules\Like\Models\Like con #[UseFactory], su LikeFactory dentro de app/Modules/Like/Database/Factories/ (con user_id => User::factory() y un likeable por defecto Post::factory() con likeable_type Post::MORPH_NAME, como hace CommentFactory) y LikeServiceProvider, que por ahora solo registra loadMigrationsFrom(__DIR__.'/../Database/Migrations'). Mantiene las constantes MORPH_NAME = 'like' y MORPH_COLUMN = 'likeable', y la propiedad created_at. El provider todavía no se registra en bootstrap/providers.php (lo hace TASK-002 junto con la migración, para que la tabla no se cree dos veces).

**Type:** refactor  
**Priority:** P0  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- [ ] Like::factory()->create() persiste un like con user_id y likeable válidos, y ->for($comment, 'likeable') guarda likeable_type = 'comment' (la morph map ya lo resuelve).
- [ ] Like::user() devuelve el User y Like::likeable() devuelve el Post o Comment; un likeable_type no registrado en la morph map lanza ClassMorphViolationException (enforceMorphMap sigue activo).
- [ ] La clase antigua App\Like\Models\Like sigue intacta y los 400+ tests actuales no cambian de resultado.

**writeScope:**

- `app/Modules/Like/Models/Like.php` (copia con namespace nuevo, #[UseFactory(LikeFactory::class)], mismas constantes y relaciones user()/likeable())
- `app/Modules/Like/Database/Factories/LikeFactory.php` (factory completa con user_id y likeable)
- `app/Modules/Like/Providers/LikeServiceProvider.php` (loadMigrationsFrom; el provider de rutas llega en TASK-012)

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Like --no-progress`

### TASK-002: Mover la migración de likes al módulo y registrar LikeServiceProvider

Mueve database/migrations/2025_09_26_144232_create_likes_table.php a app/Modules/Like/Database/Migrations/ conservando el mismo nombre de archivo (patrón SOC-46: una base que ya la ejecutó la sigue considerando ejecutada) y registra LikeServiceProvider en bootstrap/providers.php. El movimiento y el registro van en la misma tarea porque, por separado, la tabla se crearía dos veces o nunca. Contenido de la migración sin cambios (la clase anónima importa App\User\Models\User, que no se mueve).

**Type:** refactor  
**Priority:** P0  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] El archivo existe solo en app/Modules/Like/Database/Migrations/ con el mismo nombre y database/migrations/ ya no lo contiene; php artisan migrate:status no muestra la migración como pendiente en una base que ya la había ejecutado.
- [ ] Con RefreshDatabase la tabla likes se crea exactamente una vez (los tests de Comment y Post que usan likes pasan, sin error de 'table likes already exists').
- [ ] LikeServiceProvider aparece en bootstrap/providers.php después de CommentServiceProvider y antes de FriendshipServiceProvider, sin romper el arranque (php artisan about).

**writeScope:**

- `app/Modules/Like/Database/Migrations/2025_09_26_144232_create_likes_table.php` (git mv desde database/migrations/, contenido idéntico)
- `database/migrations/2025_09_26_144232_create_likes_table.php` (eliminado por el movimiento)
- `bootstrap/providers.php` (registro de LikeServiceProvider)

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Models/CommentModelTest.php app/Modules/Post/Tests/Models/PostModelTest.php`

### TASK-003: Apuntar Comment, Post y User al nuevo modelo Like

Cambia el import de App\Like\Models\Like a App\Modules\Like\Models\Like en los tres modelos que declaran relaciones likes(). Sin cambios de comportamiento.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] Comment::likes(), Post::likes() y User::likes() devuelven relaciones cuyo modelo relacionado es App\Modules\Like\Models\Like (comprobable con ->getRelated()::class).
- [ ] Ningún archivo bajo app/Modules/Comment, app/Modules/Post ni app/User/Models importa ya App\Like (grep sin resultados en esos tres modelos).
- [ ] PostModelTest y CommentModelTest siguen pasando (los tests antiguos que usan new Like de la clase vieja guardan a través de la relación nueva sin error: la tabla es la misma).

**writeScope:**

- `app/Modules/Comment/Models/Comment.php` (import)
- `app/Modules/Post/Models/Post.php` (import)
- `app/User/Models/User.php` (import)

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Models/CommentModelTest.php app/Modules/Post/Tests/Models/PostModelTest.php`

### TASK-004: Apuntar la morph map y los widgets de Admin al nuevo Like

Cambia el import de Like en AppServiceProvider (la entrada Like::MORPH_NAME => Like::class de Relation::enforceMorphMap) y en los widgets PostsOverview y PostActivityOverview del panel Admin.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] Relation::getMorphedModel('like') devuelve App\Modules\Like\Models\Like y las claves user, post, like y comment siguen registradas.
- [ ] PostActivityOverviewTest sigue pasando: conteos de likes de 7 días del post y los de otro post no se mezclan.
- [ ] Un likeable_type inexistente sigue lanzando ClassMorphViolationException (enforceMorphMap no se relajó).

**writeScope:**

- `app/Providers/AppServiceProvider.php` (import)
- `app/Modules/Admin/Filament/Resources/Posts/Widgets/PostsOverview.php` (import)
- `app/Modules/Admin/Filament/Resources/Posts/Widgets/PostActivityOverview.php` (import)

**validateCommand:** `php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts`

### TASK-005: Apuntar los tests existentes al nuevo Like

Cambia el import de Like en CommentModelTest, PostModelTest y PostActivityOverviewTest. No agrega casos nuevos: solo deja de depender de la clase antigua para poder borrarla en TASK-006.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] Los tres archivos importan App\Modules\Like\Models\Like y ninguno menciona App\Like.
- [ ] Los casos de likes() de Comment y Post (guardar un like y leerlo) y el de conteos de Admin pasan sin cambios de aserciones.
- [ ] No se elimina ni se omite ningún test (regla de no borrar tests sin aprobación).

**writeScope:**

- `app/Modules/Comment/Tests/Models/CommentModelTest.php` (import)
- `app/Modules/Post/Tests/Models/PostModelTest.php` (import)
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Widgets/PostActivityOverviewTest.php` (import)

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Models/CommentModelTest.php app/Modules/Post/Tests/Models/PostModelTest.php app/Modules/Admin/Tests/Filament/Resources/Posts/Widgets/PostActivityOverviewTest.php`

### TASK-006: Eliminar el Like antiguo y regenerar _ide_helper_models.php

Con todo apuntando al módulo nuevo, borra app/Like/Models/Like.php (y con él la carpeta app/Like/) y database/factories/LikeFactory.php, y regenera _ide_helper_models.php con composer ide-helper para que el namespace App\Like\Models desaparezca y aparezca App\Modules\Like\Models (PHPStan escanea ese archivo).

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-002, TASK-003, TASK-004, TASK-005

**Acceptance Criteria:**

- [ ] grep -rn 'App\\Like' app bootstrap config database routes tests no devuelve resultados y las carpetas app/Like y database/factories/LikeFactory.php ya no existen.
- [ ] _ide_helper_models.php declara IdeHelperLike bajo App\Modules\Like\Models y vendor/bin/phpstan analyse termina sin errores nuevos.
- [ ] Los tests de Comment, Post y Admin siguen verdes tras el borrado (no queda ninguna referencia colgante).

**writeScope:**

- `_ide_helper_models.php` (regenerado)
- `app/Like/Models/Like.php` (eliminado)
- `database/factories/LikeFactory.php` (eliminado)

**validateCommand:** `vendor/bin/phpstan analyse --no-progress && php artisan test --compact app/Modules/Comment/Tests/Models app/Modules/Post/Tests/Models`

### TASK-007: Registrar la suite de tests del módulo Like y cubrir su modelo

Registra app/Modules/Like/Tests en phpunit.xml (testsuite 'Like') y en pest()->in() de tests/Pest.php (RefreshDatabase), y crea LikeModelTest con casos pequeños de relaciones y de morph.

**Type:** test  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-002

**Acceptance Criteria:**

- [ ] Los tests de app/Modules/Like/Tests se descubren y ejecutan con php artisan test --compact --testsuite=Like y usan RefreshDatabase.
- [ ] LikeModelTest cubre en casos separados: user(), likeable() hacia Post, likeable() hacia Comment y MORPH_NAME/MORPH_COLUMN.
- [ ] Un Like con likeable_type fuera de la morph map falla con ClassMorphViolationException al resolver likeable().

**writeScope:**

- `phpunit.xml` (testsuite Like)
- `tests/Pest.php` (ruta Like en pest()->in())
- `app/Modules/Like/Tests/Models/LikeModelTest.php`

**validateCommand:** `php artisan test --compact --testsuite=Like`

### TASK-008: Índice único (user_id, likeable) para impedir likes duplicados

Crea una migración nueva en app/Modules/Like/Database/Migrations/ (la original no se edita) que primero elimina los duplicados existentes conservando el like más antiguo de cada (user_id, likeable_type, likeable_id) y después añade unique(['user_id','likeable_type','likeable_id']). Incluye un test que prueba la restricción a nivel de base de datos.

**Type:** feature  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-007

**Acceptance Criteria:**

- [ ] Insertar dos likes del mismo usuario sobre el mismo comentario lanza UniqueConstraintViolationException; el mismo usuario puede dar like a otro comentario y otro usuario al mismo comentario.
- [ ] La migración depura duplicados previos sin fallar: con dos filas idénticas antes de migrar queda una sola (la de menor id) y la tabla acepta el índice.
- [ ] down() elimina el índice sin borrar la tabla y la migración original create_likes_table queda sin tocar.

**writeScope:**

- `app/Modules/Like/Database/Migrations/<timestamp>_add_unique_user_likeable_index_to_likes_table.php` (generada con php artisan make:migration y movida/ubicada con --path)
- `app/Modules/Like/Tests/Models/LikeUniquenessTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Like/Tests/Models/LikeUniquenessTest.php`

### TASK-009: Conteo de likes y 'me gusta' en Comment y CommentResource

Añade a Comment likesCount() (reutiliza el atributo likes_count si fue precargado, como repliesCount() con comments_count, y si no consulta) e isLikedBy(User) (reutiliza liked_by_me precargado o consulta existe). CommentResource expone likesInfo: {count:int, likedByMe:bool}. likedByMe es false cuando no hay usuario autenticado.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-003

**Acceptance Criteria:**

- [ ] CommentResource devuelve likesInfo.count igual al número de likes y likesInfo.likedByMe true solo para el usuario que dio like.
- [ ] Con likes_count y liked_by_me precargados el Resource no ejecuta consultas adicionales (se verifica con DB::getQueryLog o expectation de conteo).
- [ ] Sin usuario autenticado en la request likedByMe es false, y un comentario sin likes devuelve count 0.

**writeScope:**

- `app/Modules/Comment/Models/Comment.php` (likesCount, isLikedBy, docblock de propiedades)
- `app/Modules/Comment/Resources/CommentResource.php` (likesInfo)
- `app/Modules/Comment/Tests/Resources/CommentResourceTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Resources/CommentResourceTest.php`

### TASK-010: Precargar likes_count y liked_by_me en CommentThreadProps sin N+1

En CommentThreadProps::thread() añade withCount('likes') y withExists(['likes as liked_by_me' => fn (Builder $query) => $query->where('user_id', $user->id)]) a la misma consulta paginada. El número de consultas debe seguir siendo constante con 1 o N comentarios con likes.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-009, TASK-008

**Acceptance Criteria:**

- [ ] props.comments y props.replies incluyen likesInfo.count y likesInfo.likedByMe correctos para el usuario autenticado, en comentarios raíz y en respuestas.
- [ ] CommentQueryCountTest sigue exigiendo el mismo número de consultas con 1 comentario o con muchos comentarios con likes de varios usuarios.
- [ ] El sobre del paginador (data, path, per_page, next_cursor, ...) y el orden descendente por id no cambian (CommentThreadPropsTest).

**writeScope:**

- `app/Modules/Comment/Props/CommentThreadProps.php`
- `app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php`
- `app/Modules/Comment/Tests/Security/CommentQueryCountTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Props app/Modules/Comment/Tests/Security/CommentQueryCountTest.php`

### TASK-011: Controlador y rutas del toggle de me gusta en comentarios

Crea CommentLikeToggleController (invocable) en el módulo Like: recibe el Comment por route model binding, dentro de DB::transaction elimina el like del usuario autenticado si existe o lo crea si no (capturando UniqueConstraintViolationException de una carrera para tratarla como 'ya existe'), y responde JSON {liked: bool, likesCount: int}. Crea routes/routes.php (middleware auth + verified, POST comments/{comment}/like, name comments.like, throttle:30,1,comments.like) y LikeRouteServiceProvider (mismo patrón que CommentRouteServiceProvider). Todavía sin registrar el provider de rutas (lo hace TASK-012) para que php artisan route:list no apunte a una clase a medio cablear.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001, TASK-008

**Acceptance Criteria:**

- [ ] Dar like a un comentario sin like devuelve 200 {liked:true, likesCount:1}; repetir la petición lo quita y devuelve {liked:false, likesCount:0}.
- [ ] Un comentario inexistente responde 404 y una colisión del índice único se trata como 'ya existe' sin responder 500 (se simula creando el like entre la lectura y la escritura).
- [ ] El controlador usa nombres de variable completos, tipos estrictos y la respuesta usa un solo camino de salida (PHPStan level max sin errores en app/Modules/Like).

**writeScope:**

- `app/Modules/Like/Controllers/CommentLikeToggleController.php`
- `app/Modules/Like/Providers/LikeRouteServiceProvider.php`
- `app/Modules/Like/routes/routes.php`

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Like --no-progress`

### TASK-012: Registrar las rutas de Like y cubrir el toggle y su límite de peticiones

LikeServiceProvider registra LikeRouteServiceProvider en register() (como CommentServiceProvider). Tests en el módulo: CommentLikeToggleTest (flujo de dar/quitar like, invitado, no verificado, comentario inexistente, un usuario no afecta al like de otro) y LikeThrottleTest (31ª petición del minuto responde 429; el presupuesto es por usuario, no se comparte con comments.store).

**Type:** test  
**Priority:** P1  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-011, TASK-007, TASK-004

**Acceptance Criteria:**

- [ ] route('comments.like', $comment) existe en php artisan route:list y un invitado es redirigido al login y un usuario sin email verificado no puede dar like.
- [ ] Dar like como usuario A no cambia liked para el usuario B; el conteo refleja los likes de todos, y dar like a una respuesta (comentario con commentable_type comment) también funciona.
- [ ] Tras 30 peticiones en un minuto la siguiente responde 429 con Retry-After, y el límite de comments.store sigue siendo independiente.

**writeScope:**

- `app/Modules/Like/Providers/LikeServiceProvider.php` (register del provider de rutas)
- `app/Modules/Like/Tests/Crud/CommentLikeToggleTest.php`
- `app/Modules/Like/Tests/Security/LikeThrottleTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Like/Tests`

### TASK-013: Tipo y hook de frontend para dar me gusta con actualización optimista

Regenera Wayfinder (php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder; está en .gitignore) y añade a Comment el campo likesInfo: {count:number; likedByMe:boolean} en types/comment.ts. Crea hooks/useCommentLike.ts: recibe el comentario, mantiene el estado local {count, likedByMe}, actualiza de forma optimista, llama al endpoint con la acción de Wayfinder de CommentLikeToggleController y revierte si falla (429/red), exponiendo {likesInfo, isPending, error, toggleLike}. Consultar la documentación de Inertia v3 (useHttp) antes de elegir el transporte; no usar router.post porque la respuesta es JSON, no una página Inertia.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-012, TASK-009

**Acceptance Criteria:**

- [ ] toggleLike actualiza count y likedByMe al instante y los reemplaza por la respuesta del servidor al terminar (liked/likesCount).
- [ ] Si la petición falla (429, 404 o error de red) el estado vuelve al valor previo, error contiene un mensaje en español y no se abre el modal de errores de Inertia.
- [ ] Mientras isPending es true una segunda llamada se ignora (no se envían dos peticiones superpuestas) y npm run types y npm run lint:check pasan.

**writeScope:**

- `resources/js/modules/comment/types/comment.ts` (likesInfo)
- `resources/js/modules/comment/hooks/useCommentLike.ts`

**validateCommand:** `npm run types && npm run lint:check`

### TASK-014: Botón 'Me gusta' funcional en CommentItem

Crea CommentLikeButton.tsx con el Button ghost xs existente: muestra 'Me gusta' o 'Te gusta' con el conteo cuando es mayor que 0, aria-pressed según likedByMe, disabled mientras isPending y el mensaje de error del hook bajo el botón en text-destructive. CommentItem sustituye el botón estático por este componente (sirve para comentarios y respuestas). Colores solo con tokens del tema.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-013

**Acceptance Criteria:**

- [ ] Pulsar 'Me gusta' en un comentario y en una respuesta cambia el estado y el conteo sin recargar la lista ni cerrar el diálogo; volver a pulsar lo quita.
- [ ] Un fallo del servidor (p. ej. 429) deja el botón en su estado anterior y muestra el mensaje de error sin romper el resto del hilo.
- [ ] Verificación visual en el navegador integrado en modo claro y oscuro (emulando prefers-color-scheme), sin colores hard-coded ni <button> nativos, y npm run types/lint/format:check pasan.

**writeScope:**

- `resources/js/modules/comment/components/CommentLikeButton.tsx`
- `resources/js/modules/comment/components/CommentItem.tsx`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-015: Sembrar likes en comentarios (LikeSeeder)

Crea LikeSeeder en el módulo Like: por cada comentario, un número aleatorio y sesgado de usuarios distintos (SkewedRandom::count, sin repetir usuario por comentario para respetar el índice único) da like. Se añade a DatabaseSeeder después de CommentSeeder. LikeSeederTest valida rangos y propiedades, no valores concretos (regla de los seeders aleatorios).

**Type:** feature  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008, TASK-007

**Acceptance Criteria:**

- [ ] Tras DatabaseSeeder cada comentario tiene entre 0 y el máximo configurado de likes, todos de usuarios distintos y ninguno repetido (el índice único no se viola).
- [ ] Ejecutar LikeSeeder dos veces no falla ni duplica filas (idempotente respecto al índice único).
- [ ] Con pocos usuarios disponibles el seeder limita el número de likes al de usuarios existentes en vez de fallar.

**writeScope:**

- `database/seeders/DatabaseSeeder.php` (llamada a LikeSeeder)
- `app/Modules/Like/Database/Seeders/LikeSeeder.php`
- `app/Modules/Like/Tests/Seeders/LikeSeederTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Like/Tests/Seeders`

### TASK-016: Actualizar CLAUDE.md con el módulo Like y los me gusta en comentarios

Actualiza CLAUDE.md: Like pasa de app/Like/ a app/Modules/Like/ (nueva arquitectura: Controllers, Database/{Factories,Migrations,Seeders}, Models, Providers, Tests, routes), migración conservada con el mismo nombre, índice único, endpoint comments.like con throttle propio, likesInfo en CommentResource/CommentThreadProps, useCommentLike/CommentLikeButton en el frontend, y la lista de módulos migrados y de suites de tests (Like). Después de cerrar las tareas, el resumen en Linear lo deja el flujo de cierre del skill social-hub-conventions.

**Type:** docs  
**Priority:** P3  
**Effort:** S  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** TASK-006, TASK-010, TASK-012, TASK-014, TASK-015

**Acceptance Criteria:**

- [ ] CLAUDE.md ya no menciona app/Like/ ni App\Like y describe el módulo Like en app/Modules/Like/ con su provider, ruta y migración.
- [ ] La sección de Comment documenta likesInfo, la precarga sin N+1 y el límite comments.like; la sección de tests lista la suite Like.
- [ ] Ningún otro archivo de documentación del repositorio queda con referencias a la ruta antigua (grep en docs, README y AGENTS.md).

**writeScope:**

- `CLAUDE.md`

**validateCommand:** `grep -n 'app/Like' CLAUDE.md README.md AGENTS.md; test $? -eq 1`

## Failure Modes

- Doble creación de la tabla likes: mover la migración sin registrar el provider, o registrar el provider sin borrar la global, rompe migrate; TASK-002 hace ambas cosas juntas.
- Clases duplicadas durante la transición: las tareas 003-005 repuntan importadores antes de borrar el Like viejo en TASK-006; entre medias ambos modelos apuntan a la misma tabla.
- Duplicados previos en producción/local: la migración del índice único los depura antes de crear el índice.
- Carrera al alternar (doble clic o dos pestañas): índice único + captura de la violación; el cliente ignora pulsaciones mientras isPending.
- N+1 al serializar likes: sin withCount/withExists el Resource consultaría por fila; CommentQueryCountTest lo vigila.
- 429 o error de red al alternar: el hook revierte el estado optimista y muestra el error sin abrir el modal de Inertia.
- Usuario eliminado con likes: la tabla no tiene FK, quedan likes huérfanos que inflarían el conteo (fuera de alcance; anotado para SOC-52).
- Wayfinder desactualizado: sin regenerar tras TASK-012 el import de la acción falla en tsc; TASK-013 lo regenera primero.
- _ide_helper_models.php desfasado: PHPStan escanea ese archivo; TASK-006 lo regenera.

## Ship Cut

Tras TASK-008 el módulo Like ya está migrado, cubierto por tests y con el índice único, sin cambios visibles (refactor puro, seguro para desplegar). Tras TASK-012 el backend de me gusta en comentarios funciona y está probado, aunque la UI sigue mostrando el botón estático. Corte útil mínimo para SOC-52: TASK-001 a TASK-008. TASK-015 (seeder) y TASK-016 (documentación) se pueden diferir, pero CLAUDE.md debe actualizarse antes de cerrar la issue.

## Test Coverage Map

- **TASK-001**: Verificación estática (PHPStan); el comportamiento se cubre en TASK-007.
- **TASK-002**: CommentModelTest y PostModelTest: la tabla se crea una sola vez y likes() funciona.
- **TASK-003**: CommentModelTest y PostModelTest: relaciones apuntan al modelo nuevo.
- **TASK-004**: PostActivityOverviewTest: conteos de likes de Admin; morph map.
- **TASK-005**: Tests existentes repuntados, sin aserciones nuevas.
- **TASK-006**: PHPStan completo y tests de modelos; grep sin referencias a App\Like.
- **TASK-007**: LikeModelTest: user(), likeable() (Post/Comment), constantes y morph no registrado.
- **TASK-008**: LikeUniquenessTest: violación del índice, combinaciones válidas y depuración de duplicados.
- **TASK-009**: CommentResourceTest: likesInfo, sin consultas extra, invitado.
- **TASK-010**: CommentThreadPropsTest y CommentQueryCountTest: forma de likesInfo y consultas constantes.
- **TASK-011**: Verificación estática; el comportamiento HTTP se prueba en TASK-012.
- **TASK-012**: CommentLikeToggleTest y LikeThrottleTest: alternar, 404, invitado, no verificado, 429.
- **TASK-013**: tsc y ESLint; el comportamiento optimista se comprueba en TASK-014 (sin infraestructura de tests de frontend).
- **TASK-014**: tsc, ESLint, Prettier y verificación visual en el navegador (claro/oscuro, comentario y respuesta, error 429).
- **TASK-015**: LikeSeederTest: rangos, sin duplicados, idempotencia, pocos usuarios.
- **TASK-016**: grep de referencias antiguas.

## Execution Summary

- Tareas: 16
- Capas (paralelizables dentro de cada una):
  - Capa 0: TASK-001
  - Capa 1: TASK-002, TASK-003, TASK-004, TASK-005
  - Capa 2: TASK-006, TASK-007, TASK-009
  - Capa 3: TASK-008
  - Capa 4: TASK-010, TASK-011, TASK-015
  - Capa 5: TASK-012
  - Capa 6: TASK-013
  - Capa 7: TASK-014
  - Capa 8: TASK-016
- Ruta crítica: TASK-001 → TASK-002 → TASK-007 → TASK-008 → TASK-011 → TASK-012 → TASK-013 → TASK-014 → TASK-016
- Gates finales por tarea que toca PHP: `vendor/bin/pint --dirty --format agent`, `composer rector-clear-cache` → `composer rector-dry` → `composer rector`, `composer phpstan`; en frontend: `npm run lint:check`, `npm run format:check`, `npm run types`.
- Al terminar: pedir al usuario la suite completa (`php artisan test --compact`).

## Task Dependencies

- TASK-001 ← —
- TASK-002 ← TASK-001
- TASK-003 ← TASK-001
- TASK-004 ← TASK-001
- TASK-005 ← TASK-001
- TASK-006 ← TASK-002, TASK-003, TASK-004, TASK-005
- TASK-007 ← TASK-002
- TASK-008 ← TASK-007
- TASK-009 ← TASK-003
- TASK-010 ← TASK-009, TASK-008
- TASK-011 ← TASK-001, TASK-008
- TASK-012 ← TASK-011, TASK-007, TASK-004
- TASK-013 ← TASK-012, TASK-009
- TASK-014 ← TASK-013
- TASK-015 ← TASK-008, TASK-007
- TASK-016 ← TASK-006, TASK-010, TASK-012, TASK-014, TASK-015
