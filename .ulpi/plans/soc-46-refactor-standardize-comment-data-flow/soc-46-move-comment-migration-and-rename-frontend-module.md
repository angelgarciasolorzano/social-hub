# Plan: SOC-46 (refactor 2): Mover la migracion de comments a su modulo y renombrar el modulo Comment del frontend

## Overview

Dos limpiezas de organizacion. (1) La migracion de la tabla comments vive en database/migrations, fuera del modulo; se mueve a app/Modules/Comment/Database/Migrations/ con el mismo nombre de archivo y el mismo esquema (morphs('commentable'), que ya sigue la convencion de Laravel y es coherente con likes/likeable) y se registra con loadMigrationsFrom en CommentServiceProvider. (2) El modulo del frontend deja de llamarse comments y pasa a comment (como el del backend), la carpeta modal pasa a dialog, RepliesList pasa a CommentRepliesList y el enum commentableType.ts pasa a commentType.ts (CommentType/CommentTypeValues, igual que el enum del backend). No cambia el esquema, el modelo, el payload ni el comportamiento. El modulo del frontend se crea completo en modules/comment y el viejo se borra al final (crear-luego-borrar, sin renombre directo de carpeta).

**Issue:** [SOC-46](https://linear.app/social-hub-ang/issue/SOC-46/refactor-standardize-comment-data-flow-across-backend-and-frontend) · Segundo plan dentro de SOC-46 (decision del usuario); mismo PR.  
**Modo:** HOLD · **Revision por defecto:** claude  
**Estilo de ejecucion:** Paso a paso: una tarea y pausa para revision (regla .ai/rules/linear.md). El backend es una sola tarea y no deja la suite en rojo; el frontend se crea completo en modules/comment y el viejo se borra al final.

## Scope Challenge

Modo HOLD. La version anterior de este plan renombraba commentable a model (columnas, atributos, payload y 16 archivos de prueba, con migrate:fresh --seed en la BD local). Se DESCARTO tras revisar la convencion: Laravel documenta el patron {adjetivo}able para relacion y columnas (commentable_type/commentable_id con commentable()), el proyecto ya lo sigue en likes (likeable) y model_type/model_id es la convencion de los paquetes de Spatie, cuyas tablas son genericas (Media Library, Permission). Decision del usuario (opcion A): conservar commentable_* y limitar el refactor al movimiento de la migracion y a los nombres del frontend. El componente se llama CommentRepliesList y todo va dentro de SOC-46 con el mismo PR. Hallazgos: el unico consumidor del modulo del frontend es PostCard; ya existe una asercion (H3 de CommentSecurityAuditTest) que escanea resources/js/modules/comments; no hay ningun otro consumidor de la migracion. Ampliacion posterior (TASK-009 y TASK-010): el hook usePaginatedComments era el unico archivo de modules/comment sin el prefijo Comment y pasa a useCommentPaginated. Ampliacion posterior (TASK-011 a TASK-015): revision de las pruebas del modulo a peticion del usuario; alcance aprobado 'helpers + duplicados exactos' (no se tocan los solapes entre pruebas de reglas y de HTTP ni se fusionan las de throttle). Ampliacion posterior (TASK-016 y TASK-017): sin <InfiniteScroll> en el frontend, las props de comentarios dejan de usar Inertia::scroll y devuelven el paginador. Ampliacion posterior (TASK-018 a TASK-021): el modulo no usaba Button, ScrollArea, Card, Alert ni Spinner de shadcn; alcance aprobado por el usuario: botones, ScrollArea, colores a tokens del tema, Alert/Spinner y Card para el comentario. Condicion del usuario sobre ScrollArea: si resulta muy complejo, se deja como esta (compuerta en TASK-019 y TASK-020).

## Prerequisites

- El plan anterior de SOC-46 (soc-46-refactor-standardize-comment-data-flow) esta completo hasta TASK-014 y su trabajo ya esta en commits.
- Se leyeron .ai/rules/{app,backend,frontend,linear}.md: sin variables de una letra, hooks de React tipados explicitamente, docblocks de metodo con maximo 3 lineas de prosa.
- AuthServiceProvider y UserServiceProvider ya usan loadMigrationsFrom en boot(): ese es el patron que sigue CommentServiceProvider.
- La BD de pruebas es MySQL (social-hub-testing) y RefreshDatabase la recrea; la BD local (social-hub) no se modifica en este refactor.
- Hay trabajo en curso ajeno en app/Modules/Admin que no se mezcla; este refactor no toca Admin.

## Non-Goals

- Renombrar commentable a model en columnas, atributos, relacion, payload o pruebas (descartado: ver Scope Challenge).
- Cambiar el esquema de comments, la BD local (no hay migrate:fresh) o cualquier comportamiento observable.
- Mover la migracion de posts (create_posts_table sigue en database/migrations, pendiente de su propia tarea) ni tocar la de likes.
- Renombrar las props TS commentableId/commentableType, el hook usePaginatedComments, las props comments/replies ni los parametros de URL comments_post/replies_comment.
- Cambios en el modulo Admin.
- Cambiar la ruta comments.store o la URL POST /comments: el plural es la convencion de Laravel y coincide con la tabla (decision del usuario); post.store (POST /post) queda pendiente aparte.

## Contracts

- Migracion: app/Modules/Comment/Database/Migrations/2025_09_26_143542_create_comments_table.php, mismo nombre de archivo y mismo esquema (id, user_id, morphs('commentable'), content, timestamps), registrada con loadMigrationsFrom en CommentServiceProvider::boot(). database/migrations ya no contiene create_comments_table.
- Frontend: carpeta resources/js/modules/comment/ (singular) con components/{CommentItem,CommentList,CommentRepliesList,CommentLoadStates}.tsx, components/dialog/{CommentDialog,CommentInputDialog}.tsx, components/forms/CommentForm.tsx, enums/commentType.ts (CommentType, CommentTypeValues con los mismos valores 'post' y 'comment'), hooks/usePaginatedComments.ts, types/comment.ts, index.ts; import publico '@/modules/comment'.
- Sin cambios: esquema, modelo Comment y su relacion commentable(), payload de POST /comments (content, commentable_type, commentable_id), rutas, throttles, props comments/replies y parametros de consulta.

## Existing Code Leverage

- app/Auth/Providers/AuthServiceProvider.php y app/User/Providers/UserServiceProvider.php: patron loadMigrationsFrom en boot().
- docs/architecture/backend.md ya describe Database/{Migrations,Factories,Seeders}/ como parte del modulo; la migracion de Comment era la excepcion.
- La suite de Comment (115 pruebas) como red de seguridad, y la estrategia crear-luego-borrar ya usada con CommentController -> CommentStoreController.

## Tasks

### TASK-001: Mover la migracion de comments al modulo Comment sin cambiar el esquema

Crear app/Modules/Comment/Database/Migrations/2025_09_26_143542_create_comments_table.php con el mismo nombre de archivo y el mismo contenido (morphs('commentable') se conserva), registrar el directorio con loadMigrationsFrom(__DIR__.'/../Database/Migrations') en CommentServiceProvider::boot() (mismo patron que AuthServiceProvider y UserServiceProvider) y despues borrar la migracion global. Crear primero y borrar despues, sin renombrar directo. El nombre de archivo no cambia a proposito: asi la tabla migrations de cualquier entorno ya migrado la sigue considerando ejecutada.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Review:** claude

**Acceptance Criteria:**
- [ ] migrate:fresh en el entorno de pruebas crea comments con commentable_type y commentable_id (sin cambios de esquema) y la suite de Comment sigue pasando sin tocar ninguna prueba.
- [ ] Existe una sola create_comments_table (la del modulo); database/migrations ya no la contiene, de lo contrario Laravel falla por clase duplicada. Los datos de ejemplo no se pierden: no hay migrate:fresh en la BD local.
- [ ] migrate:status en la BD local la sigue mostrando como ejecutada (mismo nombre de archivo), de modo que php artisan migrate no intenta recrear la tabla.

**writeScope:**
- `app/Modules/Comment/Database/Migrations/2025_09_26_143542_create_comments_table.php` (nuevo)
- `app/Modules/Comment/Providers/CommentServiceProvider.php`
- `database/migrations/2025_09_26_143542_create_comments_table.php`

**validateCommand:** `php artisan migrate:fresh --force --env=testing >/dev/null && php artisan tinker --env=testing --execute 'echo implode(",", Illuminate\Support\Facades\Schema::getColumnListing("comments"));' | grep -q "commentable_type,commentable_id" && test ! -e database/migrations/2025_09_26_143542_create_comments_table.php && php artisan test --compact app/Modules/Comment/Tests/Models/CommentModelTest.php`

**Integration notes:** Unico cambio de backend del refactor: el esquema, el modelo, el payload y las pruebas no se tocan (se descarto renombrar commentable a model, ver Scope Challenge). La migracion de posts sigue en database/migrations, fuera de alcance.

### TASK-002: Crear modules/comment: enum commentType, tipos y estados de carga

Crear resources/js/modules/comment/ con enums/commentType.ts (const CommentType y tipo CommentTypeValues; los valores 'post' y 'comment' no cambian), types/comment.ts (igual que el actual salvo que importa CommentTypeValues; CommentFormData conserva commentable_id y commentable_type) y components/CommentLoadStates.tsx (movido tal cual). El modulo viejo modules/comments sigue intacto: se crea lo nuevo y se borra lo viejo al final.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude

**Acceptance Criteria:**
- [ ] tsc compila con las dos carpetas conviviendo; en modules/comment ningun archivo importa de modules/comments.
- [ ] CommentType.POST y CommentType.COMMENT conservan 'post' y 'comment', y CommentFormData expone exactamente content, commentable_id y commentable_type (el payload no cambia).
- [ ] CommentLoadStates conserva sus dos componentes (CommentSkeletonList, CommentLoadError) con las mismas props.

**writeScope:**
- `resources/js/modules/comment/enums/commentType.ts` (nuevo)
- `resources/js/modules/comment/types/comment.ts` (nuevo)
- `resources/js/modules/comment/components/CommentLoadStates.tsx` (nuevo)

**validateCommand:** `npx eslint resources/js/modules/comment && npm run types`

### TASK-003: Crear el hook, el formulario y el dialogo de entrada en modules/comment

Crear hooks/usePaginatedComments.ts (usa CommentType y CommentTypeValues), components/forms/CommentForm.tsx (props y campos sin cambios: commentableType, commentableId, inputs ocultos commentable_type y commentable_id) y components/dialog/CommentInputDialog.tsx (carpeta dialog en lugar de modal).

**Type:** refactor  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-002

**Acceptance Criteria:**
- [ ] El hook conserva comportamiento y firma (carga, cursor, refresco, loadFailure, retryLoad) y los parametros de URL comments_post, replies_comment y sus cursores no cambian.
- [ ] CommentForm sigue enviando commentable_type, commentable_id y content, y solo muestra el error de content.
- [ ] tsc y eslint pasan con las dos carpetas conviviendo.

**writeScope:**
- `resources/js/modules/comment/hooks/usePaginatedComments.ts` (nuevo)
- `resources/js/modules/comment/components/forms/CommentForm.tsx` (nuevo)
- `resources/js/modules/comment/components/dialog/CommentInputDialog.tsx` (nuevo)

**validateCommand:** `npx eslint resources/js/modules/comment && npm run types`

### TASK-004: Crear CommentItem, CommentRepliesList y CommentList en modules/comment

Crear components/CommentItem.tsx, components/CommentRepliesList.tsx (componente y archivo renombrados de RepliesList a CommentRepliesList) y components/CommentList.tsx, importando del enum commentType, de ./dialog/CommentInputDialog y de ./CommentRepliesList. CommentItem y CommentRepliesList se importan entre si (ciclo que ya existia), por eso van en la misma tarea.

**Type:** refactor  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-003

**Acceptance Criteria:**
- [ ] No queda ningun nombre RepliesList sin el prefijo Comment en modules/comment y CommentItem monta <CommentRepliesList> con las mismas props (commentId, refreshCounter, showReplies, setShowReplies).
- [ ] Skeleton, error con reintento, refresco al publicar y contador de respuestas conservan el mismo comportamiento (solo cambian nombres e imports).
- [ ] tsc y eslint pasan con las dos carpetas conviviendo.

**writeScope:**
- `resources/js/modules/comment/components/CommentItem.tsx` (nuevo)
- `resources/js/modules/comment/components/CommentRepliesList.tsx` (nuevo)
- `resources/js/modules/comment/components/CommentList.tsx` (nuevo)

**validateCommand:** `npx eslint resources/js/modules/comment && npm run types`

### TASK-005: Crear CommentDialog y el barril en modules/comment y cambiar el import de PostCard

Crear components/dialog/CommentDialog.tsx e index.ts (exporta CommentDialog desde ./components/dialog/CommentDialog) en modules/comment, y cambiar el import de PostCard.tsx de '@/modules/comments' a '@/modules/comment'. Con esto la app usa el modulo nuevo y el viejo queda sin consumidores.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-004

**Acceptance Criteria:**
- [ ] PostCard importa CommentDialog de '@/modules/comment' y ningun archivo fuera de modules/comments importa ya '@/modules/comments'.
- [ ] npm run types y npm run build pasan y, en la app, el dialogo abre, carga, pagina y publica (se verifica a mano en TASK-008).
- [ ] El barril solo exporta CommentDialog, igual que el del modulo viejo.

**writeScope:**
- `resources/js/modules/comment/components/dialog/CommentDialog.tsx` (nuevo)
- `resources/js/modules/comment/index.ts` (nuevo)
- `resources/js/modules/post/components/PostCard.tsx`

**validateCommand:** `npx eslint resources/js/modules/comment resources/js/modules/post/components/PostCard.tsx && npm run types && npm run build`

### TASK-006: Borrar el modulo viejo modules/comments y actualizar la prueba que lo escanea

Con el modulo nuevo en uso, borrar la carpeta resources/js/modules/comments completa (11 archivos, solo despues de comprobar con grep que nada la importa) y cambiar CommentSecurityAuditTest para que el escaneo de dangerouslySetInnerHTML (H3) apunte a resources/js/modules/comment.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-005

**Acceptance Criteria:**
- [ ] resources/js/modules/comments no existe y grep de 'modules/comments' en resources/js, app y tests no devuelve nada fuera de la documentacion.
- [ ] H3 de CommentSecurityAuditTest escanea modules/comment y sigue siendo capaz de fallar: comprobado a mano agregando temporalmente un dangerouslySetInnerHTML (no se vuelve vacia por apuntar a una carpeta inexistente).
- [ ] npm run types y npm run build pasan sin referencias rotas.

**writeScope:**
- `resources/js/modules/comments`
- `app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php`

**validateCommand:** `test ! -d resources/js/modules/comments && php artisan test --compact app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php && npm run types && npm run build`

**Integration notes:** writeScope lista el directorio completo como un solo borrado logico (excepcion documentada al limite de 3 archivos).

### TASK-007: Actualizar CLAUDE.md y la documentacion de arquitectura

CLAUDE.md: Comment con Database/Migrations/ (la migracion vive en el modulo), modules/comment (singular), carpeta dialog, CommentRepliesList y enums/commentType.ts (CommentType/CommentTypeValues). docs/architecture/frontend.md: el ejemplo de enum apunta a modules/comment/enums/commentType.ts. docs/architecture/backend.md: solo si menciona la migracion de comments fuera del modulo.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Review:** claude
**Depends on:** TASK-001, TASK-006

**Acceptance Criteria:**
- [ ] Ni CLAUDE.md ni docs/architecture mencionan modules/comments, commentableType.ts, CommentableType, RepliesList (sin prefijo Comment) ni components/modal como vigentes.
- [ ] La documentacion indica que la migracion de comments vive en app/Modules/Comment/Database/Migrations/ y que las columnas y la relacion commentable siguen la convencion de Laravel (igual que likeable en Like).

**writeScope:**
- `CLAUDE.md`
- `docs/architecture/frontend.md`
- `docs/architecture/backend.md`

**validateCommand:** `! grep -rnE 'modules/comments|commentableType\.ts|CommentableType[^s]|[^t]RepliesList|components/modal' CLAUDE.md docs/architecture/backend.md docs/architecture/frontend.md`

### TASK-008: Gates finales y verificacion en navegador

Gates de frontend (types, lint:check, format:check, build) y de backend (Pint, Rector dry-run, PHPStan, suites de Comment y Post); verificacion manual en el navegador contra la BD local, que NO necesita migrate:fresh porque el esquema no cambia: abrir el dialogo, paginar, publicar un comentario y una respuesta, expandir respuestas, forzar un error con reintento y confirmar que la URL no cambia. Dejar el resumen del refactor en Linear (SOC-46).

**Type:** test  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Review:** none
**Depends on:** TASK-007

**Acceptance Criteria:**
- [ ] types, lint:check (0 errores), format:check y build pasan; Pint, Rector, PHPStan y las suites de Comment y Post pasan.
- [ ] En el navegador el dialogo de comentarios y de respuestas funciona igual que antes del refactor (carga, cursor, publicar con refresco, error con reintento) y la URL no cambia.
- [ ] Se pide al usuario correr php artisan test --compact completo y se deja el resumen en Linear.

**writeScope:**
- (ninguno: tarea de verificacion)

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && npm run build && vendor/bin/pint --dirty --format agent && composer rector-dry && composer phpstan && php artisan test --compact app/Modules/Comment/Tests app/Modules/Post/Tests`

### TASK-009: Crear useCommentPaginated y pasar los dos consumidores al hook nuevo

Crear hooks/useCommentPaginated.ts como copia del hook actual con la funcion renombrada a useCommentPaginated y su interfaz de retorno a UseCommentPaginatedReturn (misma logica, mismos campos de retorno), y cambiar import y llamada en CommentList.tsx y CommentRepliesList.tsx. El archivo viejo usePaginatedComments.ts sigue existiendo hasta TASK-002 (crear primero, borrar despues).

**Type:** refactor  
**Effort:** XS  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-006

**Acceptance Criteria:**
- [ ] CommentList y CommentRepliesList importan y llaman a useCommentPaginated con los mismos argumentos y usan los mismos campos devueltos; ningun archivo nuevo importa usePaginatedComments.
- [ ] El diff del hook nuevo contra el viejo solo muestra los nombres (funcion e interfaz): comportamiento intacto, incluido el manejo de fallos y reintento.
- [ ] tsc y eslint pasan con los dos hooks conviviendo.

**writeScope:**
- `resources/js/modules/comment/hooks/useCommentPaginated.ts` (nuevo)
- `resources/js/modules/comment/components/CommentList.tsx`
- `resources/js/modules/comment/components/CommentRepliesList.tsx`

**validateCommand:** `npx eslint resources/js/modules/comment && npm run types`

**Integration notes:** Ejecutada (2026-10-12). Se agrego a este plan en lugar de crear uno nuevo, como indica la regla de continuar el plan existente; el renombre del hook quedo como ultima tarea porque modules/comment era el unico archivo sin el prefijo Comment.

### TASK-010: Borrar usePaginatedComments y actualizar CLAUDE.md

Con los consumidores en el hook nuevo, borrar hooks/usePaginatedComments.ts (despues de comprobar con grep que nada lo importa) y actualizar CLAUDE.md, que menciona usePaginatedComments(commentableType, commentableId), para que nombre useCommentPaginated. Verificar tambien en el navegador que el dialogo de comentarios y de respuestas sigue cargando, paginando y publicando.

**Type:** refactor  
**Effort:** XS  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-009, TASK-007

**Acceptance Criteria:**
- [ ] Ningun archivo de resources/js, app, tests, docs ni CLAUDE.md contiene usePaginatedComments (los planes historicos de .ulpi quedan fuera) y npm run types y npm run build pasan.
- [ ] En el navegador el dialogo carga 10 comentarios, pagina por cursor, publica y muestra el error con reintento, con la URL sin cambios.

**writeScope:**
- `resources/js/modules/comment/hooks/usePaginatedComments.ts`
- `CLAUDE.md`

**validateCommand:** `test ! -e resources/js/modules/comment/hooks/usePaginatedComments.ts && ! grep -rn 'usePaginatedComments' resources/js app tests docs CLAUDE.md && npm run types && npm run build`

### TASK-011: Crear los helpers compartidos de pruebas y adoptarlos en HostPages y Throttle

En tests/Pest.php agregar createCommentOn(Post|Comment $commentable, ?User $author = null, array $attributes = []): Comment (crea el comentario con commentable_id y commentable_type tomados del morph del destino) e inertiaPartialHeaders(TestCase $testCase, string $component, string $prop): array (hace el GET a home para leer la version de Inertia y devuelve las cabeceras de un partial reload). CommentThreadHostPagesTest deja de definir commentHostPartialHeaders y usa los helpers; CommentThrottleTest reescribe loadCommentsAs sobre inertiaPartialHeaders y usa createCommentOn si aplica. Es la primera de las tareas de limpieza de pruebas del modulo (aprobada por el usuario: helpers + duplicados exactos).

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-010

**Acceptance Criteria:**
- [ ] createCommentOn crea un comentario sobre un Post y sobre un Comment con el alias de morph correcto ('post' o 'comment') y el autor indicado; recibe solo Post|Comment, asi que un destino de otro tipo (por ejemplo un User) falla con TypeError en lugar de crear una fila invalida.
- [ ] HostPagesTest y ThrottleTest pasan sin perder ningun caso (mismo numero de pruebas) y ya no existen commentHostPartialHeaders ni cabeceras X-Inertia-Partial-* escritas a mano en esos dos archivos.
- [ ] Pest.php sigue cargando para el resto de suites (la suite de Comment completa sigue verde).

**writeScope:**
- `tests/Pest.php`
- `app/Modules/Comment/Tests/Props/CommentThreadHostPagesTest.php`
- `app/Modules/Comment/Tests/Security/CommentThrottleTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Props/CommentThreadHostPagesTest.php app/Modules/Comment/Tests/Security/CommentThrottleTest.php`

### TASK-012: Adoptar los helpers en QueryCount, Props y Model y quitar la prueba duplicada de QueryCount

CommentQueryCountTest usa inertiaPartialHeaders en listCommentsCountingQueries y createCommentOn en sus 9 creaciones, y se borra su tercera prueba ('still counts replies when the resource is built without the preloaded count'), que es una copia de la tercera asercion de CommentResourceTest. CommentThreadPropsTest elimina su helper local commentsOnPost, que se promueve a tests/Pest.php como createCommentsOn(Post|Comment, int $total, ?User = null) (crea varios comentarios o respuestas con createCommentOn) y tambien lo usa QueryCountTest. CommentModelTest usa createCommentOn en sus 6 creaciones.

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-011

**Acceptance Criteria:**
- [ ] QueryCountTest conserva 'reports the exact replies info...' y 'uses the same number of queries...' y deja de tener la prueba duplicada; el conteo de consultas sigue siendo el mismo para 1 y para muchos comentarios y respuestas.
- [ ] PropsTest y ModelTest conservan todas sus pruebas y aserciones con createCommentOn/createCommentsOn; commentsOnPost ya no existe como funcion local.
- [ ] La cobertura del repliesCount sin conteo precargado sigue presente en CommentResourceTest (tercera prueba) y CommentModelTest.

**writeScope:**
- `tests/Pest.php`
- `app/Modules/Comment/Tests/Security/CommentQueryCountTest.php`
- `app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php`
- `app/Modules/Comment/Tests/Models/CommentModelTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Security/CommentQueryCountTest.php app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php app/Modules/Comment/Tests/Models/CommentModelTest.php`

**Integration notes:** EJECUTADA. Desviacion: commentsOnPost creaba varios comentarios (9 usos) y createCommentOn solo uno, asi que en vez de esparcir bucles se promovio a createCommentsOn en tests/Pest.php (cuarto archivo del alcance). Las pruebas del modulo pasan de 115 a 114 (se borro la tercera de QueryCount).

### TASK-013: Mover a CommentStoreTest el unico caso de ControllerHardening y adoptar el helper en ResourceTest

En CommentStoreTest, la prueba 'returns 404 and creates nothing when the commentable does not exist' pasa a un dataset con post y comment (absorbe 'still returns 404 when replying to a comment that does not exist' de CommentControllerHardeningTest, el unico caso que no estaba cubierto). CommentResourceTest usa createCommentOn.

**Type:** test  
**Effort:** XS  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-011

**Acceptance Criteria:**
- [ ] El 404 se comprueba para commentable_type post y comment con un id inexistente y no se crea ningun comentario en ninguno de los dos casos.
- [ ] CommentResourceTest conserva sus 5 pruebas con createCommentOn.
- [ ] El numero de casos del 404 en CommentStoreTest pasa de 1 a 2 (dataset) y el resto de CommentStoreTest queda igual.

**writeScope:**
- `app/Modules/Comment/Tests/Crud/CommentStoreTest.php`
- `app/Modules/Comment/Tests/Resources/CommentResourceTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Crud/CommentStoreTest.php app/Modules/Comment/Tests/Resources/CommentResourceTest.php`

**Integration notes:** EJECUTADA. El 404 de CommentStoreTest quedo como dataset [post, comment]; ResourceTest uso createCommentOn/createCommentsOn. La suite de Comment queda en 115 pruebas (el dataset agrega 1 caso). Nota de proceso: vendor/bin/pint --dirty tambien reordeno los imports de app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php (trabajo en curso ajeno); desde aqui se ejecuta Pint solo sobre las rutas propias.

### TASK-014: Borrar CommentControllerHardeningTest y las pruebas duplicadas de CommentSecurityAuditTest

Aprobado por el usuario. Borrar CommentControllerHardeningTest.php completo (sus 5 pruebas son copias de CommentStoreTest, de H4 y de H5, salvo el 404 ya movido en TASK-013). En CommentSecurityAuditTest borrar H1 (middleware de comments.store y comprobacion del codigo de CommentThreadProps), H2, H3 caracteres de control, H4, H5, H6 y H8 tipo no soportado, junto con los helpers que quedan sin uso (commentRouteMiddleware, commentListingQueryCount) y los imports sobrantes. Antes de borrar se verifico la cobertura y se encontro un hueco: la regla de un solo nivel (no responder a una respuesta) solo estaba probada en H4 y en CommentControllerHardeningTest, que eran copias entre si. Se MOVIO la version mas estricta de ControllerHardening a CommentStoreTest y se borro H4 (no se pierde cobertura). Conservar H3 whitespace-only, H3 escaneo de dangerouslySetInnerHTML, H7, H8 rutas y H8 user_id forjado, y usar createCommentOn donde aplique.

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-013, TASK-011

**Acceptance Criteria:**
- [ ] CommentSecurityAuditTest queda con exactamente 5 pruebas (H3 whitespace, H3 escaneo del frontend, H7, H8 rutas y H8 user_id forjado) y el escaneo de H3 sigue pudiendo fallar (comprobado con un dangerouslySetInnerHTML temporal).
- [ ] Cada comportamiento de las pruebas borradas sigue cubierto en otro archivo: reply-to-reply (movida a CommentStoreTest) y 404 en CommentStoreTest, caracteres de control en CommentValidationHardeningTest/CommentStoreRequestTest, tipo no soportado en CommentStoreTest/CommentStoreRequestTest, throttle en CommentThrottleTest y CommentThreadPropsTest, consultas constantes en CommentQueryCountTest y CommentThreadPropsTest, flash en CommentStoreTest.
- [ ] No queda ningun helper ni import sin uso en esos archivos y CommentControllerHardeningTest.php ya no existe.

**writeScope:**
- `app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php`
- `app/Modules/Comment/Tests/Security/CommentControllerHardeningTest.php`
- `app/Modules/Comment/Tests/Crud/CommentStoreTest.php`

**validateCommand:** `test ! -e app/Modules/Comment/Tests/Security/CommentControllerHardeningTest.php && php artisan test --compact app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php`

**Integration notes:** Borrado de pruebas autorizado explicitamente por el usuario (regla del proyecto: no borrar pruebas sin aprobacion). Se borran 5 + 8 + 1 (TASK-012) = 14 pruebas y el dataset de TASK-013 agrega 1 caso: la suite de Comment pasa de 115 a ~102. EJECUTADA: se borraron 13 pruebas (5 de CommentControllerHardeningTest y 8 de CommentSecurityAuditTest: H1 x2, H2, H3 control, H4, H5, H6 y H8 tipo) y se movio 1 a CommentStoreTest (reply-to-reply); la suite de Comment pasa de 115 a 103.

### TASK-015: Gates de la limpieza de pruebas

Pint, Rector (dry-run), PHPStan y las suites de Comment, Post y Filament de Admin; comprobar que no quedan helpers repetidos ni sin uso en app/Modules/Comment/Tests y que el recuento de pruebas coincide con lo esperado.

**Type:** test  
**Effort:** XS  
**Agent:** general-purpose  
**Priority:** P3  
**Review:** none
**Depends on:** TASK-012, TASK-014

**Acceptance Criteria:**
- [ ] Pint, Rector (dry-run) y PHPStan sin hallazgos y las suites de Comment, Post y Admin/Filament pasan (salvo la falla conocida ajena de Admin).
- [ ] Ninguna funcion global de pruebas del modulo esta definida mas de una vez ni queda sin uso (grep de definiciones frente a usos).
- [ ] El recuento de pruebas de Comment es 103 (antes 115) con 1.964 aserciones; Comment + Post suman 133 y Admin/Filament 47, todas en verde.

**writeScope:**
- (ninguno: tarea de verificacion)

**validateCommand:** `vendor/bin/pint --dirty --format agent && composer rector-dry && composer phpstan && php artisan test --compact app/Modules/Comment/Tests app/Modules/Post/Tests`

**Integration notes:** EJECUTADA (2026-10-12): Pint (solo rutas propias), Rector dry-run y PHPStan sin hallazgos. Inventario de helpers: 10 funciones globales en el modulo y en Pest.php, ninguna duplicada ni sin uso; 0 cabeceras X-Inertia-Partial escritas a mano fuera de tests/Pest.php; 0 usos del idioma viejo createComment(.., ['commentable_id' => ..]).

### TASK-016: CommentThreadProps devuelve el paginador sin Inertia::scroll

Tras descartar <InfiniteScroll> en el frontend, Inertia::scroll solo agregaba al page object mergeProps ['comments.data'] y un bloque scrollProps (pageName, previousPage, nextPage, currentPage, reset) que nada consume: el hook hace reload con reset y copia la pagina a su estado local. CommentThreadProps::thread() pasa a devolver el CursorPaginator (ya transformado con through()) y las props quedan como Inertia::optional(fn () => paginador). La forma de props.comments y props.replies no cambia (data, path, per_page, next_cursor, next_page_url, prev_cursor, prev_page_url). CommentThreadPropsTest deja de resolver un ScrollProp: se elimina resolveCommentThreadScrollProp y la prueba 'wraps the paginator in a scroll prop...' se reemplaza por una que fija la envoltura del paginador.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-015

**Acceptance Criteria:**
- [ ] Una recarga parcial de comments y de replies devuelve exactamente las mismas claves en props (data, path, per_page, next_cursor, next_page_url, prev_cursor, prev_page_url) y la respuesta ya no contiene scrollProps ni mergeProps; el cursor propio (comments_cursor / replies_cursor) sigue funcionando para paginar.
- [ ] Los casos de falla no cambian: id ausente, no positivo o no entero responde 404, hilo inexistente lanza ModelNotFoundException, invitado 401 y el throttle de 60/min responde 429.
- [ ] El numero de consultas sigue siendo constante y el payload no expone campos fuera de {id, content, createdAt, user, repliesInfo}.

**writeScope:**
- `app/Modules/Comment/Props/CommentThreadProps.php`
- `app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Props app/Modules/Comment/Tests/Security`

**Integration notes:** Home y Profile siguen usando Inertia::scroll para 'posts' (alli si hay <InfiniteScroll>): solo cambia la lista de comentarios. EJECUTADA: medido con una prueba temporal, la respuesta de un partial reload pasa de [component,props,url,version,sharedProps,mergeProps,scrollProps] a [component,props,url,version,sharedProps], y props.comments conserva las mismas 7 claves. El tipo de retorno se documenta como CursorPaginator<int, array<array-key, mixed>> porque JsonResource::resolve() devuelve array sin forma. Verificado tambien en el navegador (carga, cursor, respuestas, publicar con refresco, error con reintento). La suite de Comment queda en 103 pruebas.

### TASK-017: Actualizar CLAUDE.md y backend.md tras quitar Inertia::scroll de los comentarios y verificar en navegador

CLAUDE.md (Comment data flow) deja de decir que las props son Inertia::scroll y que Inertia desenvuelve un ScrollProp dentro de optional; backend.md corrige la regla de serializacion de listados por cursor (la regla de through() sobre la Resource se mantiene, pero 'dentro de Inertia::scroll' solo aplica a listados que usan <InfiniteScroll>, como posts). Verificar en el navegador carga inicial, cursor, publicar con refresco, respuestas, y error con reintento.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-016

**Acceptance Criteria:**
- [ ] Ni CLAUDE.md ni docs/architecture/backend.md afirman que las props de comentarios usan Inertia::scroll, y la documentacion explica por que no (el hook usa reset y estado local).
- [ ] En el navegador el dialogo carga 10 comentarios, pagina por cursor, publica y refresca, carga respuestas y muestra el error con reintento, con la URL sin cambios.

**writeScope:**
- `CLAUDE.md`
- `docs/architecture/backend.md`

**validateCommand:** `! grep -n 'optional(fn () => Inertia::scroll\|returned from an `optional` closure\|unwraps a `ScrollProp`' CLAUDE.md docs/architecture/backend.md`

**Integration notes:** EJECUTADA: CLAUDE.md (Comment data flow) y docs/architecture/backend.md corregidos. La regla de backend.md ahora dice que el paginador se devuelve tal cual y que Inertia::scroll solo aplica a listados con <InfiniteScroll> (verificado en Home.tsx y Profile.tsx, que usan <InfiniteScroll data='posts'>). La verificacion en navegador se hizo en TASK-016, justo tras el cambio de payload; TASK-017 solo toca documentacion.

### TASK-018: CommentItem con Button, Card y tokens del tema

En CommentItem.tsx reemplazar los 3 <button> nativos del pie ('Me gusta', 'Responder' y 'Mostrar/Ocultar respuestas') por el Button de shadcn (variant ghost o link, size xs, type='button'), envolver el comentario de primer nivel en Card/CardContent con overrides para conservar la densidad actual (sin padding de 24px ni sombra) dejando las respuestas (isReply) sin borde, y sustituir los colores fijos (gray-* y hex) por tokens del tema. Mapeo de colores acordado: text-gray-500/600/700 y dark:text-gray-300/400 -> text-muted-foreground; dark:border-[#343434] -> el token border por defecto; bg-gray-50/50 dark:bg-[#1D1D1D]/30 -> bg-muted/50. No cambia ningun handler ni prop.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-017

**Acceptance Criteria:**
- [ ] No queda ningun <button> nativo en CommentItem: 'Responder' sigue abriendo el dialogo de respuesta, 'Mostrar/Ocultar respuestas' sigue alternando showReplies y 'Me gusta' conserva su estado actual (sin accion), todos como Button con type='button'.
- [ ] El comentario de primer nivel se renderiza dentro de una Card y las respuestas (isReply) siguen sin borde y con el mismo margen; el pie de acciones queda fuera de la Card como hoy.
- [ ] No quedan clases text-gray-*, bg-gray-* ni colores hex en el archivo; el aspecto se compara en el navegador en tema claro y oscuro y se mantienen jerarquia y densidad.

**writeScope:**
- `resources/js/modules/comment/components/CommentItem.tsx`

**validateCommand:** `npx eslint resources/js/modules/comment/components/CommentItem.tsx && npm run types && npm run build`

**Integration notes:** EJECUTADA (2026-10-12): 3 Button (ghost, xs, type=button, cursor-pointer; 'Me gusta' hover:text-destructive), Card con overrides (gap-0 py-0 rounded-md bg-muted/50 shadow-none; las respuestas con border-0 bg-transparent) y CardContent p-2; el texto del comentario y el nombre heredan el color de la Card (text-card-foreground) en lugar de colores fijos; el tiempo usa text-muted-foreground. El pie usa -ml-2 para alinear el texto de los botones con el borde de la tarjeta. Cambio menor de texto: se quito un espacio final en 'Mostrar N respuestas '. Verificado en tema oscuro y claro (el app sigue el tema del sistema: appearance=system; para probar el claro se emula prefers-color-scheme, no basta alternar la clase dark).

### TASK-019: Alert en el estado de error y ScrollArea en los dialogos

CommentLoadStates.tsx: el bloque de error de CommentLoadError pasa a Alert/AlertDescription (conservando el boton Reintentar y role='alert') y sus colores a tokens. CommentDialog.tsx: el panel de la publicacion (overflow-y-auto con borde derecho) pasa a ScrollArea. CommentInputDialog.tsx: el contenedor '-mx-4 max-h-[62vh] overflow-y-auto px-4' pasa a ScrollArea con el tope de altura aplicado al viewport, porque en el Root de ScrollArea un max-h no genera scroll (el viewport es size-full). Mapeo de colores acordado: text-gray-500/600/700 y dark:text-gray-300/400 -> text-muted-foreground; dark:border-[#343434] -> el token border por defecto; bg-gray-50/50 dark:bg-[#1D1D1D]/30 -> bg-muted/50.  COMPUERTA (decision del usuario): si implementar ScrollArea resulta demasiado complejo, se conserva el contenedor actual y no se fuerza. Cuenta como demasiado complejo cuando: (a) haria falta editar shadcn/ui; (b) no se logra que la paginacion siga disparandose de forma fiable; o (c) quedan regresiones de layout que no se arreglan solo con className (scroll doble, desborde horizontal con texto largo, tope de altura que no se respeta). En ese caso se revierte solo la parte de ScrollArea de esa tarea (lo demas, como Button, Alert, Spinner y tokens, se conserva), se deja la razon en integrationNotes y en CLAUDE.md.

**Type:** refactor  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-018

**Acceptance Criteria:**
- [ ] Una publicacion larga se desplaza dentro del panel de CommentDialog y un contexto de vista previa largo se desplaza dentro de CommentInputDialog sin superar 62vh ni mostrar una segunda barra de scroll. Si se activa la compuerta de ScrollArea, CommentDialog.tsx y CommentInputDialog.tsx conservan sus contenedores overflow-y-auto actuales (sin diff en esa parte) y el Alert de CommentLoadStates se conserva igualmente.
- [ ] Un fallo de carga muestra un Alert con el mensaje y el boton Reintentar que sigue funcionando (mismo onRetry), y CommentSkeletonList conserva su aspecto con tokens.
- [ ] Un texto con una palabra muy larga no provoca desbordamiento horizontal en ninguno de los dos ScrollArea (Radix envuelve el contenido en un display:table).

**writeScope:**
- `resources/js/modules/comment/components/CommentLoadStates.tsx`
- `resources/js/modules/comment/components/dialog/CommentDialog.tsx`
- `resources/js/modules/comment/components/dialog/CommentInputDialog.tsx`

**validateCommand:** `npx eslint resources/js/modules/comment && npm run types && npm run build`

**Integration notes:** COMPUERTA (decision del usuario): si implementar ScrollArea resulta demasiado complejo, se conserva el contenedor actual y no se fuerza. Cuenta como demasiado complejo cuando: (a) haria falta editar shadcn/ui; (b) no se logra que la paginacion siga disparandose de forma fiable; o (c) quedan regresiones de layout que no se arreglan solo con className (scroll doble, desborde horizontal con texto largo, tope de altura que no se respeta). En ese caso se revierte solo la parte de ScrollArea de esa tarea (lo demas, como Button, Alert, Spinner y tokens, se conserva), se deja la razon en integrationNotes y en CLAUDE.md.

### TASK-020: ScrollArea con raiz del observador y Spinner en CommentList y CommentRepliesList

Crear hooks/useScrollAreaViewport.ts: devuelve un ref callback para el Root de ScrollArea y el elemento Viewport ([data-slot='scroll-area-viewport']) como estado, sin editar shadcn/ui, para que el useIntersectionObserver de CommentList y de CommentRepliesList use el viewport como root (mantiene rootMargin 200px) y se elimine setScrollRoot de ambos. Reemplazar los contenedores con scroll (overflow-y-auto y max-h-72 overflow-auto) por ScrollArea (en CommentRepliesList el tope de 18rem va al viewport), las filas 'Cargando mas...' por Spinner y los colores por tokens. La logica del sentinel, del temporizador de 300 ms y del manejo de fallos no cambia. COMPUERTA (decision del usuario): si implementar ScrollArea resulta demasiado complejo, se conserva el contenedor actual y no se fuerza. Cuenta como demasiado complejo cuando: (a) haria falta editar shadcn/ui; (b) no se logra que la paginacion siga disparandose de forma fiable; o (c) quedan regresiones de layout que no se arreglan solo con className (scroll doble, desborde horizontal con texto largo, tope de altura que no se respeta). En ese caso se revierte solo la parte de ScrollArea de esa tarea (lo demas, como Button, Alert, Spinner y tokens, se conserva), se deja la razon en integrationNotes y en CLAUDE.md. Aqui la compuerta es la mas probable: si se activa, CommentList y CommentRepliesList conservan su contenedor y su setScrollRoot, el hook useScrollAreaViewport no se crea, y el Spinner y los tokens se conservan.

**Type:** refactor  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-019

**Acceptance Criteria:**
- [ ] Al hacer scroll al final de la lista de comentarios y de la de respuestas se sigue cargando la pagina siguiente por cursor (10 -> mas) usando el viewport del ScrollArea como root del observador, sin duplicados y con la URL sin cambios. Si se activa la compuerta de ScrollArea, el hook useScrollAreaViewport no existe, CommentList y CommentRepliesList conservan su contenedor con scroll y su setScrollRoot, y la paginacion funciona exactamente como antes (Spinner y tokens se mantienen).
- [ ] El fallo al cargar mas sigue mostrando el aviso con Reintentar y no entra en bucle de peticiones; el scroll de respuestas se limita a 18rem y no aparece scroll horizontal con texto largo.
- [ ] setScrollRoot ya no existe en ninguno de los dos componentes y la logica de obtener el viewport vive solo en useScrollAreaViewport (sin copias).

**writeScope:**
- `resources/js/modules/comment/hooks/useScrollAreaViewport.ts` (nuevo)
- `resources/js/modules/comment/components/CommentList.tsx`
- `resources/js/modules/comment/components/CommentRepliesList.tsx`

**validateCommand:** `npx eslint resources/js/modules/comment && npm run types && npm run build`

**Integration notes:** Tarea mas delicada: si el observador no recibe el viewport correcto, la paginacion deja de dispararse. Verificar en el navegador (comentarios y respuestas, con scroll programatico) antes de pausar. COMPUERTA (decision del usuario): si implementar ScrollArea resulta demasiado complejo, se conserva el contenedor actual y no se fuerza. Cuenta como demasiado complejo cuando: (a) haria falta editar shadcn/ui; (b) no se logra que la paginacion siga disparandose de forma fiable; o (c) quedan regresiones de layout que no se arreglan solo con className (scroll doble, desborde horizontal con texto largo, tope de altura que no se respeta). En ese caso se revierte solo la parte de ScrollArea de esa tarea (lo demas, como Button, Alert, Spinner y tokens, se conserva), se deja la razon en integrationNotes y en CLAUDE.md.

### TASK-021: Gates, verificacion en tema claro y oscuro y documentacion

Gates de frontend (types, lint:check, format:check, build); verificacion manual en el navegador en tema claro y oscuro (dialogo, paginacion, respuestas, publicar con refresco, error con reintento, texto largo); y documentar en CLAUDE.md que el modulo usa los componentes de shadcn (Button, Card, Alert, Spinner, ScrollArea) y que useScrollAreaViewport entrega el viewport como raiz del IntersectionObserver.

**Type:** test  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Review:** none
**Depends on:** TASK-018, TASK-019, TASK-020

**Acceptance Criteria:**
- [ ] types, lint:check (0 errores), format:check y build pasan, y en modules/comment no quedan <button> nativos ni colores gray-*/hex; los contenedores overflow-y-auto solo pueden quedar donde se activo la compuerta de ScrollArea (TASK-019/TASK-020), con la razon documentada.
- [ ] En el navegador, en tema claro y oscuro, todos los flujos del dialogo funcionan igual que antes (carga, cursor, publicar con refresco, respuestas, error con reintento) y la URL no cambia.
- [ ] CLAUDE.md describe el uso de shadcn y, segun el resultado, el hook useScrollAreaViewport o la razon por la que ScrollArea no se adopto en cada contenedor.

**writeScope:**
- `CLAUDE.md`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && npm run build && ! grep -rnE '<button|text-gray-|bg-gray-|#[0-9A-Fa-f]{6}' resources/js/modules/comment`

## Failure Modes

- Si el nombre de archivo de la migracion cambia, un entorno ya migrado intentaria recrear la tabla y fallaria (tabla existente): se conserva el nombre exacto.
- Si la migracion queda en ambas rutas, Laravel falla por clase anonima duplicada o por nombre repetido: TASK-001 borra la global en la misma tarea.
- Si CommentServiceProvider no registra la ruta, php artisan migrate:fresh no crearia comments y toda la suite de Comment fallaria: la validacion de TASK-001 ejecuta migrate:fresh y una prueba del modelo.
- El escaneo de dangerouslySetInnerHTML (H3) podria volverse vacio si apunta a una carpeta que no existe: TASK-006 exige que apunte a modules/comment y siga activo.
- Entre TASK-002 y TASK-005 conviven modules/comments y modules/comment: nada del modulo nuevo debe importar del viejo y PostCard cambia de import solo en TASK-005.
- ScrollArea no expone el ref de su Viewport: si el observador de paginacion sigue usando el Root como root, la carga de mas comentarios deja de dispararse (TASK-020 lo resuelve con useScrollAreaViewport y lo verifica en el navegador).
- En ScrollArea un max-h aplicado al Root no genera scroll porque el Viewport es size-full: el tope de altura de CommentRepliesList (18rem) y de CommentInputDialog (62vh) se aplica al viewport.
- Cambiar colores fijos por tokens del tema puede alterar el aspecto: se compara en tema claro y oscuro antes de dar por buena cada tarea.
- Si ScrollArea resulta demasiado complejo (editar shadcn/ui, paginacion no fiable o regresiones de layout sin arreglo con className), se conservan los contenedores actuales: la compuerta de TASK-019/TASK-020 revierte solo esa parte.

## Ship Cut

El backend (TASK-001) es independiente del frontend (TASK-002 a TASK-006): cualquiera de los dos puede entregarse solo. En el frontend el primer corte seguro es TASK-005 (la app ya usa el modulo nuevo); TASK-006 (borrado del viejo), TASK-007 (docs) y TASK-008 (gates y verificacion) pueden diferirse sin romper comportamiento, aunque dejarian ambos modulos en el repo.

## Test Coverage Map

- Migracion movida: validacion de TASK-001 (migrate:fresh en pruebas crea commentable_type/commentable_id, la global ya no existe) y CommentModelTest; la suite de Comment completa corre en TASK-008.
- Frontend: npm run types, lint:check, format:check y build en cada tarea FE; H3 de CommentSecurityAuditTest sobre modules/comment (TASK-006); verificacion manual en navegador (TASK-008).
- Renombre del hook (TASK-009, TASK-010): npm run types, eslint y build; verificacion manual en navegador al borrar el hook viejo (no hay pruebas unitarias de frontend).
- Limpieza de pruebas (TASK-011 a TASK-015): helpers unificados en tests/Pest.php, 14 pruebas duplicadas retiradas con aprobacion del usuario y su cobertura verificada en otros archivos del modulo.
- Uso de componentes shadcn (TASK-018 a TASK-021): eslint, types y build por tarea; verificacion manual en navegador en tema claro y oscuro (no hay pruebas unitarias de frontend), con foco en la paginacion al cambiar el contenedor con scroll.

## Execution Summary

- Tareas: 21
- Ruta critica: TASK-002 -> TASK-003 -> TASK-004 -> TASK-005 -> TASK-006 -> TASK-007 -> TASK-008 -> TASK-009 -> TASK-010 -> TASK-011 -> TASK-012 -> TASK-014 -> TASK-015 -> TASK-016 -> TASK-017 -> TASK-018 -> TASK-019 -> TASK-020 -> TASK-021
- Capas de ejecucion:
  1. TASK-001, TASK-002
  2. TASK-003
  3. TASK-004
  4. TASK-005
  5. TASK-006
  6. TASK-007
  7. TASK-008
  8. TASK-009
  9. TASK-010
  10. TASK-011
  11. TASK-012, TASK-013
  12. TASK-014
  13. TASK-015
  14. TASK-016
  15. TASK-017
  16. TASK-018
  17. TASK-019
  18. TASK-020
  19. TASK-021

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | - |
| TASK-002 | - |
| TASK-003 | TASK-002 |
| TASK-004 | TASK-003 |
| TASK-005 | TASK-004 |
| TASK-006 | TASK-005 |
| TASK-007 | TASK-001, TASK-006 |
| TASK-008 | TASK-007 |
| TASK-009 | TASK-006 |
| TASK-010 | TASK-009, TASK-007 |
| TASK-011 | TASK-010 |
| TASK-012 | TASK-011 |
| TASK-013 | TASK-011 |
| TASK-014 | TASK-013, TASK-011 |
| TASK-015 | TASK-012, TASK-014 |
| TASK-016 | TASK-015 |
| TASK-017 | TASK-016 |
| TASK-018 | TASK-017 |
| TASK-019 | TASK-018 |
| TASK-020 | TASK-019 |
| TASK-021 | TASK-018, TASK-019, TASK-020 |
