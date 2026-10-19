# Plan: SOC-50: Conteo real de comentarios, respuestas de la más antigua a la más nueva y contador de caracteres

## Overview

PostCard muestra '122 me gustas' y '6000 comentarios' como texto fijo, las respuestas de un hilo salen de la más nueva a la más antigua (la respuesta nueva aparece antes del mensaje al que contesta) y el límite de 1000 caracteres solo se descubre al recibir el error del servidor. Este plan (1) calcula el conteo de comentarios de cada publicación en la misma consulta del listado (raíz vivos más respuestas, sin N+1) y lo expone por PostResource junto a un likesInfo real; como EXPANSION, también hace funcionar el 'Me gusta' de los posts reutilizando el módulo Like (LikeController unificado, hook compartido useLikeToggle); (2) lista las respuestas en orden ascendente, con un refresco que carga hasta el final y desplaza a la respuesta nueva; y (3) añade un contador de caracteres 'n / 1000 caracteres' con aviso desde el 90% y bloqueo de envío al superar el límite, tanto al publicar como al editar, resuelto solo en el frontend siguiendo el contador de biografía de AccountSettingsProfileForm.

**Issue:** [SOC-50](https://linear.app/social-hub-ang/issue/SOC-50/improvement-show-real-comment-counts-list-replies-oldest-first-and-add)  
**Modo:** EXPANSION · **Revisión por defecto:** claude  
**Estilo de ejecución:** Paso a paso: una tarea y pausa para revisión (regla .ai/rules/linear.md).

## Scope Challenge

Modo EXPANSION con decisiones del usuario: (1) el conteo es 'raíz vivos + todas las respuestas' (los tombstones no cuentan, sus respuestas sí) calculado con subconsultas correlacionadas dentro de un scope de Post, en una sola consulta por listado; (2) tras publicar una respuesta, el hilo recarga desde la primera página siguiendo el cursor hasta el final y baja a la nueva respuesta, sin parámetros nuevos de servidor; (3) el contador de caracteres vive solo en el frontend (constante propia, sin prop de Inertia), con aviso cerca del límite, bloqueo del envío por encima y también en el diálogo de edición, sin maxLength para no truncar al pegar; (4) EXPANSION añade me gusta reales en posts. Hallazgos del repositorio: PostResource solo serializa id, content, image y createdAt; HomeController y ProfileController (index y show) arman la consulta de posts con cursorPaginate; useCommentPaginated asume que una página nueva va después y un refresco antes, lo que ya no sirve para respuestas ascendentes; el módulo Like tiene un invocable por acción y aquí se unifica en LikeController como se hizo con Comment; la constante 1000 está duplicada en los dos FormRequest del backend, por lo que se centraliza en Comment::CONTENT_MAX_LENGTH aunque el frontend mantenga su espejo (riesgo documentado). La actualización del conteo en la tarjeta al publicar o eliminar se resuelve con deltas locales (+1 publicar, -1 eliminar) porque los posts usan Inertia::scroll y recargarlos mezclaría páginas.

## Prerequisites

- SOC-51 y SOC-52 están en main: existen el módulo Like con comments.like, el índice único, CommentController con store/update/destroy, tombstones con SoftDeletes y CommentThreadProps con withTrashed.
- Se leyeron .ai/rules/{app,backend,frontend,linear}.md, incluida la regla de orden de componentes y de interfaces junto a su componente.
- Para la suite completa en local usar php -d memory_limit=2G vendor/bin/pest (artisan test agota 128M por un test de imágenes de Admin).
- La migración del índice único de likes puede seguir pendiente en la base local; no bloquea este plan.
- Rama propia de GitButler para SOC-50 (sugerida: soc-50-improvement-show-real-comment-counts-list-replies-oldest).

## Non-Goals

- Notificaciones, ordenar comentarios por relevancia o por likes y moderación desde Admin.
- Lista de 'quién dio me gusta' y me gusta en la vista de detalle de Admin.
- Enviar el máximo de caracteres desde el backend al frontend (decisión: constante propia del frontend).
- Cambiar el orden de los comentarios de primer nivel (siguen del más nuevo al más antiguo).
- Actualizar en tiempo real el conteo cuando otros usuarios comentan (solo cambios propios por deltas locales).
- Denormalizar un comments_count en la tabla posts.

## Contracts

- Conteo: Total de comentarios = comentarios raíz del post con deleted_at nulo + todas las respuestas a los raíz del post (incluidas las de un tombstone). Se calcula en Post::withEngagementFor(User) con dos subconsultas correlacionadas (atributos root_comments_count y replies_count) en la misma consulta del listado; Post::commentsTotal() suma ambos atributos y solo consulta si faltan.
- Post::withEngagementFor(User $viewer) precarga también likes_count y liked_by_me; Post::likesCount() e isLikedBy(User) siguen el patrón de Comment. PostResource añade commentsCount y likesInfo {count, likedByMe}, cada bloque en un método privado.
- Rutas de like: POST comments/{comment}/like (comments.like) y POST posts/{post}/like (posts.like), ambas auth + verified y throttle:30,1 con prefijo propio (comments.like, posts.like), servidas por LikeController::toggleComment y togglePost, con respuesta JSON {liked: bool, likesCount: int} (404 inexistente, 429 con Retry-After).
- Orden: CommentThreadProps lista comments (raíz de un post) por id descendente y replies por id ascendente, ambos con cursorPaginate(10) y sus nombres de cursor actuales; el número de consultas es constante.
- Refresco de respuestas: uploadedComments de un hilo de respuestas recarga la primera página reemplazando el estado y sigue el cursor hasta que next_cursor sea null, una petición a la vez, sin duplicar ids; avisa con afterReload para desplazar el viewport. Los hilos raíz mantienen el refresco actual. reloadComments (usado al eliminar) sigue reemplazando por la primera página.
- Contador de caracteres: COMMENT_CONTENT_MAX_LENGTH = 1000 en modules/comment/constants/commentContent.ts (espejo de Comment::CONTENT_MAX_LENGTH); CharacterCounter compartido pinta 'n / max caracteres', advertencia desde el 90% y text-destructive por encima; Publicar y Guardar se deshabilitan por encima del máximo; los Textarea no usan maxLength; el servidor sigue validando.
- Conteo en la tarjeta: PostCard guarda commentsCount en estado local y recibe deltas: +1 al publicar un raíz o una respuesta, -1 al eliminar un comentario o una respuesta; editar no cambia nada.
- Frontend de likes: shared/types/likes.ts (LikesInfo, LikeToggleResponse) y shared/hooks/useLikeToggle.ts reemplazan a useCommentLike; los botones usan la acción de Wayfinder de LikeController (import nombrado).
- Protecciones previas intactas: throttle de comments.store/update/destroy y comments.like, tombstones, consultas constantes y normalización de content.

## Existing Code Leverage

- app/Modules/Comment/Models/Comment.php: repliesCount(), likesCount() e isLikedBy() como patrón de atributos precargados.
- app/Modules/Comment/Props/CommentThreadProps.php y Tests/{Props,Security}: orden, cursores, throttle y conteo de consultas.
- app/Modules/Admin/Filament/Resources/Posts/Widgets/PostActivityOverview.php: consulta de respuestas por whereIn sobre los ids de los comentarios del post.
- app/Modules/Like/Controllers/LikeCommentToggleController.php y Tests: lógica de toggle con índice único y su suite de pruebas.
- resources/js/modules/setting/modules/accountSettings/components/AccountSettingsProfileForm.tsx: contador '{n} / 160 caracteres' con aria-describedby.
- resources/js/modules/comment/hooks/useCommentLike.ts y useCommentPaginated.ts: hook de like y paginación por cursor con merge y reload.
- resources/js/modules/post/components/PostCard.tsx, app/Modules/Post/Resources/PostResource.php, HomeController y ProfileController: superficie actual de los posts.
- resources/js/shared/lib/dateTime.ts y components/form (InputError): helpers y barrel compartidos.

## Tasks

### TASK-001: Post: scope de engagement y totales de comentarios y me gusta

En Post añade el scope #[Scope] withEngagementFor(User $viewer) que, en la misma consulta del listado, precarga: withCount('likes'), withExists(['likes as liked_by_me' => whereBelongsTo($viewer)]) y dos subconsultas correlacionadas con selectSub: root_comments_count (comentarios raíz vivos del post, con el scope de SoftDeletes) y replies_count (todas las respuestas a los raíz del post, incluidas las de un tombstone porque siguen visibles; join de comments como replies con comments como roots por commentable_id). Añade Post::commentsTotal() y Post::likesCount() (reutilizan los atributos precargados y consultan solo si faltan) y Post::isLikedBy(User) (reutiliza liked_by_me), siguiendo el patrón de Comment::repliesCount()/isLikedBy().

**Type:** feature  
**Priority:** P0  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- [ ] commentsTotal() de un post cuenta raíz vivos más todas sus respuestas: con 2 raíz vivos, 1 raíz eliminado (tombstone) con 3 respuestas y 1 respuesta en un raíz vivo da 2 + 3 + 1 = 6, y no cuenta comentarios de otros posts.
- [ ] withEngagementFor ejecuta una sola consulta para N posts (el conteo de consultas es el mismo con 1 o con 10 posts con comentarios, respuestas y likes) y liked_by_me es true solo para el viewer.
- [ ] Post::commentsTotal() (suma de root_comments_count y replies_count), likesCount() e isLikedBy() devuelven el mismo valor con los atributos precargados y consultando directamente, y 0/false para un post sin actividad.

**writeScope:**

- `app/Modules/Post/Models/Post.php` (scope withEngagementFor, commentsTotal, likesCount, isLikedBy, docblock de propiedades)
- `app/Modules/Post/Tests/Models/PostModelTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Post/Tests/Models/PostModelTest.php`

### TASK-002: Comment::CONTENT_MAX_LENGTH como única fuente del límite en el backend

Añade a Comment la constante public const int CONTENT_MAX_LENGTH = 1000 y úsala en CommentStoreRequest y CommentUpdateRequest en lugar del literal ->max(1000). Sin cambios de comportamiento: los tests de ambos requests lo demuestran. El frontend mantiene su propia constante (decisión del usuario: no se envía por props) y se documenta que debe coincidir con esta.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- [ ] CommentStoreRequestTest y CommentUpdateRequestTest siguen pasando sin cambiar aserciones: 1000 caracteres pasan y 1001 fallan con la regla max.
- [ ] Ningún archivo bajo app/Modules/Comment/Requests contiene ya el literal 1000; ambos requests leen Comment::CONTENT_MAX_LENGTH.
- [ ] Cambiar la constante a otro valor en un test no es necesario: basta que los dos requests referencien exactamente la misma constante (comprobado por PHPStan y por lectura de las reglas con FluentRule).

**writeScope:**

- `app/Modules/Comment/Models/Comment.php` (constante)
- `app/Modules/Comment/Requests/CommentStoreRequest.php`
- `app/Modules/Comment/Requests/CommentUpdateRequest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Requests`

### TASK-003: PostResource: commentsCount y likesInfo

PostResource añade commentsCount (Post::commentsTotal()) y likesInfo {count, likedByMe}, este último con el mismo formato que CommentResource (likedByMe usa $request->user() y es false para invitados), cada bloque en un método privado como en CommentResource. El resto de claves y el orden existente no cambian salvo por las nuevas claves al final.

**Type:** feature  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] commentsCount y likesInfo reflejan los valores del post (conteo de comentarios raíz vivos más respuestas, likes totales y likedByMe del usuario de la request).
- [ ] Para un invitado likedByMe es false sin consultas, y con los atributos precargados por withEngagementFor el Resource no ejecuta consultas adicionales.
- [ ] Las claves anteriores (id, content, image, createdAt) siguen en el mismo orden y con los mismos valores.

**writeScope:**

- `app/Modules/Post/Resources/PostResource.php`
- `app/Modules/Post/Tests/Resources/PostResourceTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Post/Tests/Resources/PostResourceTest.php`

### TASK-004: Home y Profile precargan el engagement de los posts sin N+1

HomeController::__invoke y ProfileController::index/show añaden ->withEngagementFor(el usuario autenticado) a la consulta de posts antes del cursorPaginate, de modo que PostResource lea commentsCount y likesInfo de atributos precargados. Un test nuevo de listados en el módulo Post comprueba el conteo de consultas constante de la página y los valores servidos.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-003

**Acceptance Criteria:**

- [ ] La prop posts de Home, de Profile propio y de Profile de otro usuario incluye commentsCount y likesInfo correctos para el usuario autenticado.
- [ ] El número de consultas de la página es el mismo con 1 post o con 10 posts con comentarios, respuestas y likes (test de conteo constante).
- [ ] Un post sin comentarios devuelve commentsCount 0 y uno con un tombstone cuenta solo sus respuestas; no se rompe el paginado por cursor ni el orden descendente.

**writeScope:**

- `app/Home/Controllers/HomeController.php`
- `app/User/Profile/Controllers/ProfileController.php`
- `app/Modules/Post/Tests/Queries/PostListingTest.php` (la suite Post ya cubre la carpeta nueva)

**validateCommand:** `php artisan test --compact app/Modules/Post/Tests/Queries/PostListingTest.php`

### TASK-005: Unificar los toggles de like en LikeController (comentario y post)

Sustituye el invocable LikeCommentToggleController por un único LikeController en el módulo Like con los métodos toggleComment(Comment) y togglePost(Post), que comparten un método privado de toggle (borra el like existente o lo crea capturando UniqueConstraintViolationException) y responden JSON {liked, likesCount} (mismo contrato que hoy). routes/routes.php declara POST comments/{comment}/like (comments.like, throttle:30,1,comments.like) y el nuevo POST posts/{post}/like (posts.like, throttle:30,1,posts.like) con Route::controller como en Comment y TrustedDevice. Los nombres de ruta y las URLs de comentarios no cambian.

**Type:** refactor  
**Priority:** P1  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- [ ] POST comments/{comment}/like sigue respondiendo exactamente como antes (200 {liked, likesCount}, 404, 401 para invitados, 429) y LikeCommentToggleTest y LikeThrottleTest siguen pasando sin cambios de aserciones.
- [ ] POST posts/{post}/like alterna el like del usuario sobre el post y responde {liked, likesCount}; un post inexistente da 404 y un invitado 401.
- [ ] El archivo LikeCommentToggleController.php deja de existir y no queda ninguna referencia a él en app/ ni en routes.

**writeScope:**

- `app/Modules/Like/Controllers/LikeController.php`
- `app/Modules/Like/routes/routes.php` (rutas comments.like y posts.like)
- `app/Modules/Like/Controllers/LikeCommentToggleController.php` (eliminado)

**validateCommand:** `php artisan test --compact app/Modules/Like/Tests`

### TASK-006: Tests del toggle de like en posts y su límite de peticiones

Añade LikePostToggleTest (dar y quitar like a un post, otro usuario no afecta, 404 y 401) y casos de throttle de posts.like en LikeThrottleTest (30 por minuto, 429 con Retry-After, presupuesto propio independiente de comments.like y de otras rutas).

**Type:** test  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-005

**Acceptance Criteria:**

- [ ] Dar like a un post sin like devuelve {liked: true, likesCount: 1} y repetir lo quita; un like de otro usuario cuenta pero no cambia liked del primero.
- [ ] Tras 30 peticiones por minuto a posts.like la siguiente responde 429 con Retry-After, y agotar posts.like no bloquea comments.like ni comments.store.
- [ ] Un post inexistente responde 404 y un invitado 401, sin crear ningún like.

**writeScope:**

- `app/Modules/Like/Tests/Crud/LikePostToggleTest.php`
- `app/Modules/Like/Tests/Security/LikeThrottleTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Like/Tests/Crud/LikePostToggleTest.php app/Modules/Like/Tests/Security/LikeThrottleTest.php`

### TASK-007: CommentThreadProps lista las respuestas de la más antigua a la más nueva

En CommentThreadProps el orden pasa a ser un parámetro de thread(): los comentarios raíz de un post siguen ordenados por id descendente y las respuestas (REPLIES_PARAMETER) por id ascendente, con la misma paginación por cursor (cursorPaginate(10)) y el mismo número constante de consultas. Se actualizan los tests para fijar ambos órdenes y la continuidad del cursor entre páginas.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- [ ] Las respuestas de un comentario salen en orden de id ascendente y los comentarios raíz de un post en orden descendente, cada uno con su sobre de paginador intacto.
- [ ] Con 11 respuestas la primera página trae las 10 más antiguas y la segunda la más nueva sin repetir ni saltarse ninguna (cursor ascendente), y los comentarios raíz mantienen su paginado descendente.
- [ ] CommentQueryCountTest sigue exigiendo el mismo número de consultas con 1 o muchas respuestas, y el 404 por id inválido o inexistente no cambia.

**writeScope:**

- `app/Modules/Comment/Props/CommentThreadProps.php`
- `app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Props app/Modules/Comment/Tests/Security/CommentQueryCountTest.php`

### TASK-008: Componente compartido CharacterCounter

Crea components/form/CharacterCounter.tsx en shared: recibe id, current y max, y pinta '{current} / {max} caracteres' (mismo texto que el contador de biografía de AccountSettingsProfileForm) como <p> enlazable con aria-describedby. Usa tokens del tema: texto muted normal, color de advertencia desde el 90% del máximo y text-destructive al superarlo, con role="status" aria-live="polite" solo cuando cambia de estado de aviso. Se exporta desde el barrel components/form/index.ts.

**Type:** feature  
**Priority:** P1  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- [ ] Con current por debajo del 90% de max el contador usa el estilo muted; entre el 90% y max usa el de advertencia; con current mayor que max usa text-destructive.
- [ ] El contador se exporta desde '@/shared/components/form' y no usa colores hard-coded ni <button> nativos; el texto coincide con '{n} / {max} caracteres'.
- [ ] npm run types, lint:check y format:check pasan y el componente no rompe cuando max es 0 o current es negativo (se trata como 0).

**writeScope:**

- `resources/js/shared/components/form/CharacterCounter.tsx`
- `resources/js/shared/components/form/index.ts` (export)

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-009: Contador y bloqueo en el formulario de comentarios

Crea modules/comment/constants/commentContent.ts con COMMENT_CONTENT_MAX_LENGTH = 1000 (con un comentario de que debe coincidir con Comment::CONTENT_MAX_LENGTH del backend). CommentForm muestra el CharacterCounter bajo el Textarea (aria-describedby) y CommentInputDialog deshabilita 'Publicar' cuando el contenido supera el máximo. El Textarea no usa maxLength para no truncar en silencio al pegar.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- [ ] Al escribir, el formulario muestra 'n / 1000 caracteres' y pasa a advertencia desde 900 y a destructivo por encima de 1000.
- [ ] Con más de 1000 caracteres el botón Publicar queda deshabilitado y no se envía ninguna petición; con exactamente 1000 se puede publicar.
- [ ] Pegar un texto de 1500 caracteres no se trunca y el contador muestra 1500 / 1000; npm run types, lint y format pasan.

**writeScope:**

- `resources/js/modules/comment/constants/commentContent.ts`
- `resources/js/modules/comment/components/forms/CommentForm.tsx`
- `resources/js/modules/comment/components/dialog/CommentInputDialog.tsx`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-010: Contador y bloqueo en el diálogo de edición

CommentEditDialog muestra el CharacterCounter bajo el Textarea con COMMENT_CONTENT_MAX_LENGTH, y deshabilita 'Guardar' cuando el contenido supera el máximo. Sin maxLength en el Textarea.

**Type:** feature  
**Priority:** P1  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-009

**Acceptance Criteria:**

- [ ] El diálogo de edición muestra el contador con la longitud actual del texto precargado y cambia de estado como el formulario de publicación.
- [ ] Con más de 1000 caracteres Guardar queda deshabilitado y no se envía la petición PATCH.
- [ ] Reabrir el diálogo reinicia el contador con el contenido actual; npm run types, lint y format pasan.

**writeScope:**

- `resources/js/modules/comment/components/dialog/CommentEditDialog.tsx`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-011: Hook y tipo compartidos para alternar me gusta

Regenera Wayfinder (php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder; ignorado por git) y crea shared/types/likes.ts con LikesInfo {count, likedByMe} y LikeToggleResponse {liked, likesCount}, y shared/hooks/useLikeToggle.ts: generaliza useCommentLike para recibir la acción de Wayfinder (toggleUrl) y el LikesInfo inicial; mismo comportamiento (useHttp con optimistic y rollback, ignora clics mientras procesa, 429/red como mensaje local sin modal de Inertia).

**Type:** refactor  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-005

**Acceptance Criteria:**

- [ ] useLikeToggle(toggleUrl, initialLikesInfo) devuelve {error, isPending, likesInfo, toggleLike} y actualiza al instante count y likedByMe, reemplazándolos por la respuesta del servidor al terminar.
- [ ] Si la petición falla (429, 404 o red) el estado vuelve al valor previo, error trae un mensaje en español y no se abre el modal de Inertia; una segunda llamada mientras procesa se ignora.
- [ ] LikesInfo y LikeToggleResponse viven en shared/types/likes.ts y npm run types, lint y format pasan.

**writeScope:**

- `resources/js/shared/types/likes.ts`
- `resources/js/shared/hooks/useLikeToggle.ts`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-012: Migrar CommentLikeButton al hook compartido

CommentLikeButton usa useLikeToggle con la acción toggleComment de LikeController (Wayfinder, import nombrado como en los demás módulos). Se elimina hooks/useCommentLike.ts y types/comment.ts reutiliza LikesInfo/LikeToggleResponse de shared en lugar de CommentLikesInfo/CommentLikeToggleResponse propios. Sin cambios visibles.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-011

**Acceptance Criteria:**

- [ ] Dar y quitar 'Me gusta' en un comentario y en una respuesta se comporta exactamente como antes (actualización inmediata, rollback ante error, mensaje 429 local).
- [ ] useCommentLike.ts ya no existe y no queda ninguna referencia a él, a LikeCommentToggleController ni a CommentLikesInfo en resources/js.
- [ ] npm run types, lint:check y format:check pasan.

**writeScope:**

- `resources/js/modules/comment/components/CommentLikeButton.tsx`
- `resources/js/modules/comment/types/comment.ts`
- `resources/js/modules/comment/hooks/useCommentLike.ts` (eliminado)

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-013: PostCard: conteo real de comentarios y me gusta funcional

types/post.ts añade commentsCount y likesInfo (LikesInfo compartido). Crea components/PostLikeButton.tsx (Button outline con corazón relleno al dar like, aria-pressed, deshabilitado mientras procesa y error en role="alert") sobre useLikeToggle con la acción togglePost de LikeController. PostCard sustituye los textos fijos '122 me gustas' y '6000 comentarios' por likesInfo.count y commentsCount (singular/plural: '1 me gusta', '1 comentario') y usa PostLikeButton en lugar del botón estático.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-003, TASK-004, TASK-011

**Acceptance Criteria:**

- [ ] PostCard muestra el número real de comentarios y de me gusta del post, con singular y plural correctos, y no queda ningún valor fijo en el componente.
- [ ] Pulsar 'Me gusta' en un post cambia el estado y el conteo al instante y lo persiste; si la petición falla el estado vuelve atrás y se muestra el mensaje sin romper la tarjeta.
- [ ] Verificación visual en el navegador (claro y oscuro) en Home y Profile, y npm run types, lint y format pasan.

**writeScope:**

- `resources/js/modules/post/types/post.ts`
- `resources/js/modules/post/components/PostLikeButton.tsx`
- `resources/js/modules/post/components/PostCard.tsx`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-014: El conteo de comentarios de PostCard se actualiza al publicar y eliminar

PostCard guarda commentsCount en estado local (inicial: post.commentsCount) y pasa onCommentsCountChange(delta) a CommentDialog → CommentList. CommentList lo llama con +1 al publicar un comentario raíz y al crear una respuesta (onReplyCreated) y con -1 al eliminar un comentario o una respuesta (onDeleted, que es -1 también cuando un raíz con respuestas queda como tombstone porque deja de contar). Editar no cambia el conteo.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-013, TASK-004

**Acceptance Criteria:**

- [ ] Publicar un comentario o una respuesta suma 1 al número de la tarjeta sin recargar la página, y eliminar uno resta 1 (incluido un raíz con respuestas, que pasa a tombstone).
- [ ] Editar un comentario no cambia el número y un error al publicar o eliminar (429, red) no lo modifica.
- [ ] Verificación visual en el navegador: crear raíz, responder, eliminar respuesta y eliminar raíz dejan el contador coherente con el servidor tras recargar; npm run types, lint y format pasan.

**writeScope:**

- `resources/js/modules/post/components/PostCard.tsx`
- `resources/js/modules/comment/components/dialog/CommentDialog.tsx`
- `resources/js/modules/comment/components/CommentList.tsx`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-015: useCommentPaginated: refrescar hasta el final cuando el hilo es ascendente

En useCommentPaginated, para los hilos de respuestas (CommentType.COMMENT), uploadedComments pasa a un nuevo modo 'cargar hasta el final': recarga la primera página reemplazando el estado y sigue pidiendo páginas con el cursor mientras next_cursor no sea null (una petición a la vez), sin duplicar ids, y avisa al terminar con un callback opcional (afterReload) para desplazar la vista. Los hilos de comentarios raíz (descendentes) conservan el refresco actual. Un fallo a mitad de camino deja lo ya cargado y muestra el error con reintento.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-007

**Acceptance Criteria:**

- [ ] Tras publicar una respuesta en un hilo con más de una página, el estado termina con todas las respuestas en orden ascendente, la nueva al final, sin ids repetidos.
- [ ] Un fallo de red o 429 en una de las páginas siguientes conserva las páginas cargadas, expone loadFailure y retryLoad() continúa desde el cursor pendiente sin reiniciar.
- [ ] Los comentarios raíz y el comportamiento de 'cargar más' al hacer scroll no cambian, y npm run types, lint y format pasan.

**writeScope:**

- `resources/js/modules/comment/hooks/useCommentPaginated.ts`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-016: CommentRepliesList: desplazar a la respuesta nueva

CommentRepliesList usa el nuevo modo de useCommentPaginated y, cuando termina de cargar tras publicar una respuesta (refreshCounter), desplaza el viewport del ScrollArea hasta el final para mostrar la respuesta nueva (useCommentScrollAreaViewport entrega el viewport). El primer despliegue del hilo no desplaza. Respuestas ordenadas de la más antigua a la más nueva en pantalla.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-015

**Acceptance Criteria:**

- [ ] Al publicar una respuesta en un hilo con varias páginas, la lista se carga hasta el final y el viewport baja hasta la respuesta nueva, que aparece en último lugar y sin duplicados.
- [ ] Abrir 'Mostrar N respuestas' por primera vez no desplaza el scroll, y eliminar una respuesta no lo desplaza al final.
- [ ] Verificación visual en el navegador con un hilo de más de 10 respuestas (orden ascendente, 'Respondiendo a @nombre' después del mensaje al que contesta), claro y oscuro; npm run types, lint y format pasan.

**writeScope:**

- `resources/js/modules/comment/components/CommentRepliesList.tsx`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-017: Documentación: CLAUDE.md, docs de arquitectura y skill

Actualiza CLAUDE.md, docs/architecture/backend.md y el skill social-hub-conventions: el orden de CommentThreadProps (comments descendente, replies ascendente) y su refresco hasta el final en el frontend; PostResource con commentsCount y likesInfo y el scope Post::withEngagementFor (conteo de raíz vivos más respuestas, sin N+1); LikeController con toggleComment y togglePost y las rutas comments.like y posts.like; Comment::CONTENT_MAX_LENGTH y su espejo en el frontend (modules/comment/constants/commentContent.ts, no se envía por props); CharacterCounter compartido y useLikeToggle/likes.ts; y el aviso de que los me gusta de PostCard ya son reales.

**Type:** docs  
**Priority:** P3  
**Effort:** S  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** TASK-006, TASK-007, TASK-010, TASK-012, TASK-014, TASK-016

**Acceptance Criteria:**

- [ ] CLAUDE.md y docs/architecture/backend.md describen el orden ascendente de las respuestas, el conteo real de comentarios de un post, los me gusta de posts y el controlador unificado de likes; ninguno menciona ya LikeCommentToggleController ni 'los me gusta de PostCard son texto fijo'.
- [ ] Se documenta que COMMENT_CONTENT_MAX_LENGTH del frontend debe coincidir con Comment::CONTENT_MAX_LENGTH y dónde vive el CharacterCounter compartido.
- [ ] grep de LikeCommentToggleController, useCommentLike y de 'orderByDesc' en la descripción de replies no devuelve referencias obsoletas en los tres archivos.

**writeScope:**

- `CLAUDE.md`
- `docs/architecture/backend.md`
- `.claude/skills/social-hub-conventions/SKILL.md`

**validateCommand:** `grep -n 'LikeCommentToggleController\|useCommentLike' CLAUDE.md docs/architecture/backend.md .claude/skills/social-hub-conventions/SKILL.md; test $? -eq 1`

## Failure Modes

- N+1 en el listado: el conteo de comentarios y de likes por post se resuelve con subconsultas correlacionadas en la misma consulta; el test de conteo constante lo vigila con comentarios, respuestas y likes.
- Conteo desfasado: si un cliente no recibe el delta (error de red) la tarjeta queda con el valor viejo hasta recargar; el servidor sigue siendo la fuente de verdad en cada carga de la página.
- Tombstones: un raíz eliminado deja de contar pero sus respuestas siguen contando; eliminar la última respuesta limpia el tombstone sin cambiar el conteo.
- Refresco hasta el final con hilos muy largos: se hacen tantas peticiones como páginas de 10; si una falla se conserva lo cargado y se ofrece reintento desde el cursor pendiente (no reinicia).
- Respuesta nueva fuera de la página cargada: el modo 'hasta el final' evita duplicados y saltos; el desplazamiento solo ocurre tras publicar.
- Pegado de texto largo: sin maxLength no se trunca; el contador pasa a destructivo y se bloquea el envío; el servidor valida igualmente (max 1000).
- Duplicación del 1000: backend (Comment::CONTENT_MAX_LENGTH) y frontend (COMMENT_CONTENT_MAX_LENGTH) pueden divergir; se documenta y queda a cargo de quien cambie el límite.
- Unificar controladores de like: cambiar de invocable a métodos cambia el import de Wayfinder; TASK-005 regenera y TASK-011/012 migran los consumidores; las URLs y nombres de ruta de comentarios no cambian.
- Like de post por doble clic o dos pestañas: el índice único y la captura de UniqueConstraintViolationException lo resuelven igual que en comentarios.

## Ship Cut

Tras TASK-004 el backend sirve el conteo real y los likes de los posts, con consultas constantes. Tras TASK-007 el backend ya ordena las respuestas de forma ascendente: no desplegar TASK-007 sin TASK-015 y TASK-016, porque el refresco actual del frontend insertaría la respuesta nueva al principio. Corte útil mínimo para el criterio de la issue: TASK-001 a TASK-004, TASK-008 a TASK-010 y TASK-013 (conteo real y contador). La parte de me gusta en posts (TASK-005, TASK-006, TASK-011, TASK-012 y la parte de likes de TASK-013) es la expansión y puede ir en un PR aparte; TASK-017 (documentación) debe cerrarse antes de la issue.

## Test Coverage Map

- **TASK-001**: PostModelTest: conteo (raíz vivos + respuestas, tombstone), consulta única, accesores con y sin precarga.
- **TASK-002**: CommentStoreRequestTest y CommentUpdateRequestTest sin cambios (refactor).
- **TASK-003**: PostResourceTest: commentsCount, likesInfo, invitado, sin consultas extra.
- **TASK-004**: PostListingTest: valores servidos y conteo de consultas constante en Home y Profile.
- **TASK-005**: LikeCommentToggleTest y LikeThrottleTest existentes sin cambios; ruta posts.like probada en TASK-006.
- **TASK-006**: LikePostToggleTest y LikeThrottleTest: alternar, 404, 401, 429 e independencia de presupuestos.
- **TASK-007**: CommentThreadPropsTest y CommentQueryCountTest: orden ascendente de replies, descendente de comments, cursor continuo.
- **TASK-008**: tsc, ESLint, Prettier; uso verificado en TASK-009/010.
- **TASK-009**: tsc, ESLint, Prettier y verificación visual del contador y del bloqueo al publicar.
- **TASK-010**: tsc, ESLint, Prettier y verificación visual en edición.
- **TASK-011**: tsc, ESLint, Prettier; comportamiento verificado en TASK-012 y TASK-013.
- **TASK-012**: tsc, ESLint, Prettier y verificación visual de like en comentario y respuesta.
- **TASK-013**: tsc, ESLint, Prettier y verificación visual de PostCard en Home y Profile (claro/oscuro).
- **TASK-014**: tsc, ESLint, Prettier y verificación visual del conteo al publicar, responder y eliminar.
- **TASK-015**: tsc, ESLint, Prettier; comportamiento verificado en TASK-016.
- **TASK-016**: tsc, ESLint, Prettier y verificación visual con un hilo de más de 10 respuestas.
- **TASK-017**: grep de referencias obsoletas.

## Execution Summary

- Tareas: 17
- Capas (paralelizables dentro de cada una):
  - Capa 0: TASK-001, TASK-002, TASK-005, TASK-007, TASK-008
  - Capa 1: TASK-003, TASK-006, TASK-009, TASK-011, TASK-015
  - Capa 2: TASK-004, TASK-010, TASK-012, TASK-016
  - Capa 3: TASK-013
  - Capa 4: TASK-014
  - Capa 5: TASK-017
- Ruta crítica: TASK-001 → TASK-003 → TASK-004 → TASK-013 → TASK-014 → TASK-017
- Gates finales por tarea que toca PHP: `vendor/bin/pint --dirty --format agent`, `composer rector-clear-cache` → `composer rector-dry` → `composer rector`, `composer phpstan` (con --memory-limit=2G); en frontend: `npm run lint:check` (limitado a modules, shared y app: los archivos blatui*.js de resources/js fallan por separado), `npm run format:check`, `npm run types`, `npm run build`.
- Al terminar: correr la suite completa con `php -d memory_limit=2G vendor/bin/pest` y pedir al usuario `php artisan test --compact`.

## Task Dependencies

- TASK-001 ← —
- TASK-002 ← —
- TASK-003 ← TASK-001
- TASK-004 ← TASK-003
- TASK-005 ← —
- TASK-006 ← TASK-005
- TASK-007 ← —
- TASK-008 ← —
- TASK-009 ← TASK-008
- TASK-010 ← TASK-009
- TASK-011 ← TASK-005
- TASK-012 ← TASK-011
- TASK-013 ← TASK-003, TASK-004, TASK-011
- TASK-014 ← TASK-013, TASK-004
- TASK-015 ← TASK-007
- TASK-016 ← TASK-015
- TASK-017 ← TASK-006, TASK-007, TASK-010, TASK-012, TASK-014, TASK-016
