# Plan: SOC-46 (refactor 2): Renombrar el modulo Comment del frontend y mover/corregir su migracion a model morph

## Overview

Dos limpiezas de nombres que ya tocan frontend y backend a la vez. (1) La tabla comments usa morphs('commentable') (commentable_type/commentable_id) en una migracion global; se mueve a app/Modules/Comment/Database/Migrations/ y se corrige a morphs('model') (model_type/model_id, como hacen spatie/laravel-permission y spatie/laravel-medialibrary). Eso renombra las columnas, los atributos del modelo, el factory, el seeder, los campos del POST /comments y las pruebas, pero NO la relacion Comment::commentable(), que se conserva con columnas explicitas porque describe mejor el dominio. (2) El modulo del frontend deja de llamarse comments y pasa a comment (como el modulo del backend), la carpeta modal pasa a dialog, RepliesList pasa a CommentRepliesList y el enum commentableType.ts pasa a commentType.ts (CommentType/CommentTypeValues, igual que el enum del backend). El modulo del frontend se crea completo en modules/comment y el viejo se borra al final (crear-luego-borrar, sin renombre directo de carpeta).

**Issue:** [SOC-46](https://linear.app/social-hub-ang/issue/SOC-46/refactor-standardize-comment-data-flow-across-backend-and-frontend) · Segundo plan dentro de SOC-46 (decision del usuario); mismo PR.  
**Modo:** HOLD · **Revision por defecto:** claude  
**Estilo de ejecucion:** Paso a paso: una tarea y pausa para revision (regla .ai/rules/linear.md). TASK-001 a TASK-008 forman una ventana contigua de backend en la que la suite de Comment esta en rojo (el esquema cambia antes que el codigo); las tareas mecanicas de pruebas (TASK-005 a TASK-008) se pueden ejecutar juntas si el usuario lo pide.

## Scope Challenge

Modo HOLD. Decisiones del usuario: (a) editar la migracion original y moverla al modulo (la BD local requiere migrate:fresh --seed; las pruebas no, por RefreshDatabase); (b) el renombre llega a columnas, atributos del modelo, factory, seeder y campos del payload (model_type/model_id), pero la relacion commentable() y las props TS commentableId/commentableType se conservan porque dicen que es el destino; (c) el componente se llama CommentRepliesList; (d) se registra dentro de SOC-46 y el mismo PR. Hallazgos de la exploracion: CommentThreadProps no usa nombres de columna (solo la relacion comments()), por lo que no cambia; Post::comments() usa Comment::MORPH_COLUMN, asi que basta cambiar esa constante; hay 16 archivos de prueba con commentable_ (12 de Comment, 1 de Post y 3 de Admin/Filament) y _ide_helper_models.php (ignorado por git) los declara y lo escanea PHPStan; el unico consumidor del modulo del frontend es PostCard; ya existe una asercion que escanea resources/js/modules/comments.

## Prerequisites

- El plan anterior de SOC-46 (soc-46-refactor-standardize-comment-data-flow) esta completo hasta TASK-014 y su trabajo ya esta en commits.
- Se leyeron .ai/rules/{app,backend,frontend,linear}.md: sin variables de una letra, hooks de React tipados explicitamente, docblocks de metodo con maximo 3 lineas de prosa, factories completas con los campos persistidos.
- La BD de pruebas es MySQL (social-hub-testing, .env.testing) y RefreshDatabase la recrea con las migraciones; la BD local (social-hub) tiene datos reales de seed y NO se toca sin aprobacion explicita (TASK-016).
- Hay trabajo en curso ajeno en app/Modules/Admin (roles y permisos: AdminRole, AdminAuthorizationSeeder y su prueba) que no se mezcla; solo se tocan 3 pruebas de Filament por sus columnas de comments.
- AuthServiceProvider y UserServiceProvider ya usan loadMigrationsFrom en boot(): ese es el patron que sigue CommentServiceProvider.

## Non-Goals

- Mover la migracion de posts (create_posts_table sigue en database/migrations, pendiente de su propia tarea) ni tocar la de likes.
- Renombrar la relacion Comment::commentable(), las props TS commentableId/commentableType, las variables locales $commentable, los parametros de URL comments_post/replies_comment, las props comments/replies, el hook usePaginatedComments ni los valores del enum ('post', 'comment').
- Cambiar rutas, permisos, throttles, reglas de validacion o comportamiento observable: es solo renombre y movimiento.
- Cambios en el modulo Admin mas alla de las tres pruebas de Filament.
- Preservar los datos de la BD local: migrate:fresh --seed es una consecuencia aceptada, y solo se ejecuta con confirmacion.

## Contracts

- Esquema: tabla comments con id, user_id, model_type, model_id (indice compuesto por morphs('model')), content y timestamps; migracion en app/Modules/Comment/Database/Migrations/2025_09_26_143542_create_comments_table.php registrada con loadMigrationsFrom en CommentServiceProvider::boot().
- Modelo: Comment::MORPH_COLUMN = 'model'; fillable y casts con model_id y model_type (cast CommentType); Comment::commentable() = morphTo('commentable', 'model_type', 'model_id'); Comment::comments() y Post::comments() usan morphMany(..., 'model').
- HTTP: POST /comments (comments.store, sin cambios de ruta, middleware ni throttle) recibe content, model_type (enum CommentType) y model_id; el error de 'no se puede responder a una respuesta' se reporta en la clave model_id.
- Frontend: carpeta resources/js/modules/comment/ (singular) con components/{CommentItem,CommentList,CommentRepliesList,CommentLoadStates}.tsx, components/dialog/{CommentDialog,CommentInputDialog}.tsx, components/forms/CommentForm.tsx, enums/commentType.ts (CommentType, CommentTypeValues), hooks/usePaginatedComments.ts, types/comment.ts, index.ts; CommentFormData = {content, model_id, model_type}; import publico '@/modules/comment'.
- Sin cambios: nombres de ruta y URL, props comments/replies, parametros de consulta comments_post/replies_comment/comments_cursor/replies_cursor, morph map ('post', 'comment'), valores del enum.

## Existing Code Leverage

- app/Auth/Providers/AuthServiceProvider.php y app/User/Providers/UserServiceProvider.php: patron loadMigrationsFrom(__DIR__.'/../Database/Migrations') en boot().
- docs/architecture/backend.md ya describe Database/{Migrations,Factories,Seeders}/ como parte del modulo; la migracion de Comment era la excepcion.
- La suite de Comment (115 pruebas) y las de Post y Admin/Filament como red de seguridad; la estrategia crear-luego-borrar ya usada con CommentController -> CommentStoreController.

## Tasks

### TASK-001: Mover la migracion de comments al modulo y corregir morphs('commentable') por morphs('model')

Crear app/Modules/Comment/Database/Migrations/2025_09_26_143542_create_comments_table.php con el mismo nombre de archivo (conserva su identidad en la tabla migrations) y morphs('model'), que genera model_type, model_id y su indice. Registrar el directorio con loadMigrationsFrom en CommentServiceProvider::boot() (mismo patron que AuthServiceProvider y UserServiceProvider) y despues borrar la migracion global. Crear primero y borrar despues, sin renombrar directo.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Review:** claude

**Acceptance Criteria:**
- [ ] migrate:fresh sobre la BD de pruebas crea comments con model_type y model_id (y un indice sobre ambas) y sin columnas commentable_*; users y posts ya existen cuando corre (el orden por nombre de archivo se mantiene).
- [ ] Existe una sola create_comments_table: la del modulo; database/migrations ya no la contiene, de lo contrario Laravel falla por clase duplicada.
- [ ] migrate:status en el entorno de pruebas la lista como ejecutada desde la ruta del modulo, y down() la elimina con dropIfExists.

**writeScope:**
- `app/Modules/Comment/Database/Migrations/2025_09_26_143542_create_comments_table.php` (nuevo)
- `app/Modules/Comment/Providers/CommentServiceProvider.php`
- `database/migrations/2025_09_26_143542_create_comments_table.php`

**validateCommand:** `php artisan migrate:fresh --force --env=testing >/dev/null && php artisan tinker --env=testing --execute 'echo implode(",", Illuminate\Support\Facades\Schema::getColumnListing("comments"));' | grep -q "model_type,model_id" && test ! -e database/migrations/2025_09_26_143542_create_comments_table.php`

**Integration notes:** Inicio de la ventana contigua de backend (TASK-001 a TASK-008): el esquema cambia antes que el codigo, asi que las pruebas de Comment fallan hasta TASK-005. La BD de desarrollo (social-hub) NO se toca aqui: migrate:fresh --seed se hace en TASK-016 y solo con tu aprobacion.

### TASK-002: Renombrar las columnas en el modelo Comment y su factory

En Comment.php: MORPH_COLUMN pasa a 'model' (lo usan Comment::comments() y Post::comments()), #[Fillable] y casts usan model_id y model_type, y la relacion se conserva como commentable() con morphTo('commentable', 'model_type', 'model_id'). En CommentFactory los valores por defecto usan model_id y model_type. CommentModelTest se actualiza a los nombres nuevos.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Review:** claude
**Depends on:** TASK-001

**Acceptance Criteria:**
- [ ] Comment::commentable() sigue resolviendo el padre (Post o Comment) y Post::comments() y Comment::comments() crean y leen filas con model_type y model_id.
- [ ] model_type se sigue casteando al enum CommentType y un valor fuera del enum sigue sin poder asignarse silenciosamente (misma falla que antes).
- [ ] El factory deja model_id y model_type completos con Post por defecto y ya no hay ninguna referencia a commentable_ en los tres archivos.

**writeScope:**
- `app/Modules/Comment/Models/Comment.php`
- `app/Modules/Comment/Database/Factories/CommentFactory.php`
- `app/Modules/Comment/Tests/Models/CommentModelTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Models/CommentModelTest.php`

### TASK-003: Renombrar las columnas en el seeder y en las pruebas de factory y de Post

CommentSeeder usa model_id y model_type; CommentFactoryTest y PostModelTest se actualizan a los nombres nuevos (PostModelTest comprueba $comment->model_type).

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-002

**Acceptance Criteria:**
- [ ] CommentSeederTest y CommentFactoryTest pasan: el seeder crea comentarios sobre posts y respuestas sobre comentarios con model_type y model_id.
- [ ] PostModelTest sigue demostrando que $post->comments() devuelve solo los comentarios del post (no los de otro post ni respuestas).
- [ ] No queda commentable_ en esos tres archivos.

**writeScope:**
- `app/Modules/Comment/Database/Seeders/CommentSeeder.php`
- `app/Modules/Comment/Tests/Database/CommentFactoryTest.php`
- `app/Modules/Post/Tests/Models/PostModelTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Database app/Modules/Post/Tests/Models/PostModelTest.php`

### TASK-004: Renombrar los campos del POST /comments (model_type, model_id) en request y controlador

CommentStoreRequest valida model_type (enum CommentType) y model_id (entero requerido) en lugar de commentable_*; CommentStoreController los lee con los accesores tipados y la excepcion de 'no se puede responder a una respuesta' pasa a la clave model_id; la comprobacion de profundidad usa $commentable->model_type. CommentStoreRequestTest se actualiza.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Review:** claude
**Depends on:** TASK-002

**Acceptance Criteria:**
- [ ] Una publicacion valida con model_type, model_id y content crea el comentario en la fila correcta; los campos antiguos commentable_type y commentable_id ya no se aceptan (faltan model_type y model_id => errores de validacion en esas claves).
- [ ] Responder a una respuesta sigue rechazandose con el mismo mensaje, ahora en la clave model_id, y no crea ningun comentario.
- [ ] Reglas, mensajes, normalizacion de content y limite de 1000 caracteres no cambian.

**writeScope:**
- `app/Modules/Comment/Requests/CommentStoreRequest.php`
- `app/Modules/Comment/Controllers/CommentStoreController.php`
- `app/Modules/Comment/Tests/Requests/CommentStoreRequestTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Requests/CommentStoreRequestTest.php`

**Integration notes:** Este es el cambio de contrato HTTP: desde aqui el frontend viejo (modules/comments) deja de poder publicar hasta TASK-013.

### TASK-005: Actualizar las pruebas de store, validacion y throttle al contrato nuevo

CommentStoreTest, CommentValidationHardeningTest y CommentThrottleTest envian y comprueban model_type y model_id (y las claves de error model_id/model_type).

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-003, TASK-004

**Acceptance Criteria:**
- [ ] Publicar sobre un post, sobre un comentario y sobre un comentario inexistente (404) se comprueba con los campos nuevos y pasa.
- [ ] Las pruebas de endurecimiento siguen cubriendo tipo no soportado ('user'), id inexistente, contenido en blanco o solo con caracteres de control, y user_id forjado, sin debilitar ninguna.
- [ ] El throttle de store (6/min) y su independencia respecto al de lectura siguen cubiertos; sin commentable_ en los tres archivos.

**writeScope:**
- `app/Modules/Comment/Tests/Crud/CommentStoreTest.php`
- `app/Modules/Comment/Tests/Security/CommentValidationHardeningTest.php`
- `app/Modules/Comment/Tests/Security/CommentThrottleTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Crud/CommentStoreTest.php app/Modules/Comment/Tests/Security/CommentValidationHardeningTest.php app/Modules/Comment/Tests/Security/CommentThrottleTest.php`

### TASK-006: Actualizar las pruebas de props, paginas anfitrionas y consultas al esquema nuevo

CommentThreadPropsTest, CommentThreadHostPagesTest y CommentQueryCountTest crean comentarios con model_id y model_type. CommentThreadProps no cambia (solo usa la relacion comments() y variables locales).

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-003

**Acceptance Criteria:**
- [ ] Las props comments y replies siguen paginando por cursor, en orden descendente, sin mezclar hilos, y los 404 y el throttle de lectura siguen pasando.
- [ ] El numero de consultas sigue siendo constante al crecer comentarios y respuestas (sin N+1).
- [ ] Sin commentable_ en los tres archivos; los parametros de URL comments_post y replies_comment no cambian.

**writeScope:**
- `app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php`
- `app/Modules/Comment/Tests/Props/CommentThreadHostPagesTest.php`
- `app/Modules/Comment/Tests/Security/CommentQueryCountTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Props app/Modules/Comment/Tests/Security/CommentQueryCountTest.php`

### TASK-007: Actualizar las pruebas de auditoria, endurecimiento del controlador y Resource

CommentSecurityAuditTest (19 referencias), CommentControllerHardeningTest y CommentResourceTest usan model_type y model_id; la clave de error de profundidad pasa a model_id. El escaneo de dangerouslySetInnerHTML sigue apuntando a resources/js/modules/comments hasta TASK-014.

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-004, TASK-003

**Acceptance Criteria:**
- [ ] H2, H3, H4, H8 y la prueba de profundidad siguen cubriendo los mismos riesgos con los campos nuevos (id inexistente, whitespace, caracteres de control, responder a una respuesta, user_id forjado, tipo no soportado) y pasan.
- [ ] CommentResourceTest sigue comprobando que el Resource solo expone id, content, createdAt, user y repliesInfo.
- [ ] Sin commentable_ en los tres archivos.

**writeScope:**
- `app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php`
- `app/Modules/Comment/Tests/Security/CommentControllerHardeningTest.php`
- `app/Modules/Comment/Tests/Resources/CommentResourceTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php app/Modules/Comment/Tests/Security/CommentControllerHardeningTest.php app/Modules/Comment/Tests/Resources/CommentResourceTest.php`

### TASK-008: Actualizar las pruebas de Filament (Admin) que crean comentarios

CommentsRelationManagerTest, PostsRelationManagerTest y UserActivityOverviewTest usan model_id y model_type al crear comentarios. Solo se tocan esas tres pruebas: el modulo Admin y su trabajo en curso (roles/permisos) quedan fuera.

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-003

**Acceptance Criteria:**
- [ ] Las tres pruebas de Filament siguen pasando: el relation manager de comentarios del post, el de posts del usuario y el widget de actividad cuentan los mismos comentarios.
- [ ] No se modifica ningun otro archivo de app/Modules/Admin (el diff de Admin se limita a estas tres pruebas).
- [ ] Sin commentable_ en los tres archivos.

**writeScope:**
- `app/Modules/Admin/Tests/Filament/Resources/Posts/RelationManagers/CommentsRelationManagerTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/Widgets/UserActivityOverviewTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/RelationManagers/CommentsRelationManagerTest.php app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php app/Modules/Admin/Tests/Filament/Resources/Users/Widgets/UserActivityOverviewTest.php`

**Integration notes:** Fin de la ventana contigua de backend: la suite de Comment vuelve a verde.

### TASK-009: Gates de backend y regeneracion del ide-helper

Verificacion sin cambios de codigo: regenerar _ide_helper_models.php contra la BD de pruebas ya migrada (ide-helper:models -RM --env=testing), porque PHPStan lo escanea y todavia declara commentable_type y commentable_id; despues Pint, Rector (dry-run) y PHPStan, y las suites de Comment, Post y Filament de Admin.

**Type:** test  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P2  
**Review:** none
**Depends on:** TASK-005, TASK-006, TASK-007, TASK-008

**Acceptance Criteria:**
- [ ] PHPStan nivel maximo, Pint y Rector (dry-run) terminan sin errores y _ide_helper_models.php declara model_type y model_id para Comment.
- [ ] Las suites de Comment, Post y de Admin/Filament pasan completas; la unica falla conocida de Admin (AdminUserRoleCommandTest, trabajo en curso ajeno) se reporta aparte y no se corrige aqui.
- [ ] No queda commentable_ en app/ salvo la relacion Comment::commentable() y variables locales.

**writeScope:**
- (ninguno: tarea de verificacion)

**validateCommand:** `php artisan ide-helper:models -RM --env=testing >/dev/null && vendor/bin/pint --dirty --format agent && composer rector-dry && composer phpstan && php artisan test --compact app/Modules/Comment/Tests app/Modules/Post/Tests app/Modules/Admin/Tests/Filament`

**Integration notes:** _ide_helper_models.php esta en .gitignore: la regeneracion no produce cambios versionados.

### TASK-010: Crear modules/comment: enum commentType, tipos y estados de carga

Crear resources/js/modules/comment/ con enums/commentType.ts (const CommentType y tipo CommentTypeValues; los valores 'post' y 'comment' no cambian), types/comment.ts (CommentFormData con model_id y model_type; el resto igual) y components/CommentLoadStates.tsx (movido sin cambios de comportamiento). El modulo viejo modules/comments sigue intacto: se crea lo nuevo y se borra lo viejo al final.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-004

**Acceptance Criteria:**
- [ ] tsc compila con las dos carpetas conviviendo; en modules/comment ningun archivo importa de modules/comments.
- [ ] CommentFormData expone exactamente content, model_id y model_type, y CommentType.POST/COMMENT conservan 'post' y 'comment'.
- [ ] CommentLoadStates conserva sus dos componentes (CommentSkeletonList, CommentLoadError) con las mismas props.

**writeScope:**
- `resources/js/modules/comment/enums/commentType.ts` (nuevo)
- `resources/js/modules/comment/types/comment.ts` (nuevo)
- `resources/js/modules/comment/components/CommentLoadStates.tsx` (nuevo)

**validateCommand:** `npx eslint resources/js/modules/comment && npm run types`

### TASK-011: Crear el hook, el formulario y el dialogo de entrada en modules/comment

Crear hooks/usePaginatedComments.ts (usa CommentType/CommentTypeValues), components/forms/CommentForm.tsx (inputs ocultos name='model_type' y name='model_id'; las props siguen siendo commentableType y commentableId) y components/dialog/CommentInputDialog.tsx (carpeta dialog en lugar de modal; useForm con model_id y model_type).

**Type:** refactor  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-010

**Acceptance Criteria:**
- [ ] El hook conserva su comportamiento y firma (carga, cursor, refresco, loadFailure, retryLoad) y los parametros de URL comments_post, replies_comment y sus cursores no cambian.
- [ ] CommentForm envia model_type y model_id con los valores del destino y content, y sigue mostrando solo el error de content.
- [ ] tsc y eslint pasan con las dos carpetas conviviendo.

**writeScope:**
- `resources/js/modules/comment/hooks/usePaginatedComments.ts` (nuevo)
- `resources/js/modules/comment/components/forms/CommentForm.tsx` (nuevo)
- `resources/js/modules/comment/components/dialog/CommentInputDialog.tsx` (nuevo)

**validateCommand:** `npx eslint resources/js/modules/comment && npm run types`

### TASK-012: Crear CommentItem, CommentRepliesList y CommentList en modules/comment

Crear components/CommentItem.tsx, components/CommentRepliesList.tsx (componente renombrado de RepliesList a CommentRepliesList, con su archivo) y components/CommentList.tsx, importando del enum commentType, de ./dialog/CommentInputDialog y de ./CommentRepliesList. CommentItem y CommentRepliesList se importan entre si (ciclo que ya existia), por eso van en la misma tarea.

**Type:** refactor  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-011

**Acceptance Criteria:**
- [ ] No queda ningun nombre RepliesList en modules/comment y CommentItem monta <CommentRepliesList> con las mismas props (commentId, refreshCounter, showReplies, setShowReplies).
- [ ] Skeleton, error con reintento, refresco al publicar y contador de respuestas conservan el mismo comportamiento (solo cambian nombres e imports).
- [ ] tsc y eslint pasan con las dos carpetas conviviendo.

**writeScope:**
- `resources/js/modules/comment/components/CommentItem.tsx` (nuevo)
- `resources/js/modules/comment/components/CommentRepliesList.tsx` (nuevo)
- `resources/js/modules/comment/components/CommentList.tsx` (nuevo)

**validateCommand:** `npx eslint resources/js/modules/comment && npm run types`

### TASK-013: Crear CommentDialog y el barril en modules/comment y cambiar el import de PostCard

Crear components/dialog/CommentDialog.tsx e index.ts (exporta CommentDialog desde ./components/dialog/CommentDialog) en modules/comment, y cambiar el import de PostCard.tsx de '@/modules/comments' a '@/modules/comment'. Con esto la app usa el modulo nuevo; el viejo queda sin consumidores.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-012

**Acceptance Criteria:**
- [ ] PostCard importa CommentDialog de '@/modules/comment' y ningun archivo fuera de modules/comments importa ya '@/modules/comments'.
- [ ] npm run types y npm run build pasan y, en la app, el dialogo abre, carga, pagina y publica (se verifica a mano en TASK-016).
- [ ] El barril solo exporta CommentDialog, igual que el del modulo viejo.

**writeScope:**
- `resources/js/modules/comment/components/dialog/CommentDialog.tsx` (nuevo)
- `resources/js/modules/comment/index.ts` (nuevo)
- `resources/js/modules/post/components/PostCard.tsx`

**validateCommand:** `npx eslint resources/js/modules/comment resources/js/modules/post/components/PostCard.tsx && npm run types && npm run build`

### TASK-014: Borrar el modulo viejo modules/comments y actualizar la prueba que lo escanea

Con el modulo nuevo en uso, borrar la carpeta resources/js/modules/comments completa (11 archivos, solo despues de comprobar con grep que nada la importa) y cambiar CommentSecurityAuditTest para que el escaneo de dangerouslySetInnerHTML apunte a resources/js/modules/comment.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-013, TASK-007

**Acceptance Criteria:**
- [ ] resources/js/modules/comments no existe y grep de 'modules/comments' en resources/js, app y tests no devuelve nada fuera de la documentacion.
- [ ] H3 de CommentSecurityAuditTest escanea modules/comment y sigue fallando si algun archivo usa dangerouslySetInnerHTML (no se vuelve vacia por apuntar a una carpeta inexistente).
- [ ] npm run types y npm run build pasan sin referencias rotas.

**writeScope:**
- `resources/js/modules/comments`
- `app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php`

**validateCommand:** `test ! -d resources/js/modules/comments && php artisan test --compact app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php && npm run types && npm run build`

**Integration notes:** writeScope lista el directorio completo como un solo borrado logico (excepcion documentada al limite de 3 archivos).

### TASK-015: Actualizar CLAUDE.md y la documentacion de arquitectura

CLAUDE.md: Comment con Database/Migrations/ y columnas model_type/model_id (relacion commentable() conservada), payload del POST con model_type/model_id, modules/comment (singular), carpeta dialog, CommentRepliesList y enums/commentType.ts. docs/architecture/frontend.md: el ejemplo de enum apunta a modules/comment/enums/commentType.ts con CommentType/CommentTypeValues. docs/architecture/backend.md: solo si menciona comentarios o morphs con los nombres viejos.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Review:** claude
**Depends on:** TASK-009, TASK-014

**Acceptance Criteria:**
- [ ] Ni CLAUDE.md ni docs/architecture mencionan modules/comments, commentableType.ts, CommentableType, RepliesList, components/modal ni las columnas commentable_type/commentable_id como vigentes.
- [ ] La documentacion explica por que la relacion se llama commentable() aunque las columnas sean model_*, y que la migracion de Comment vive en el modulo.

**writeScope:**
- `CLAUDE.md`
- `docs/architecture/frontend.md`
- `docs/architecture/backend.md`

**validateCommand:** `! grep -rnE 'modules/comments|commentableType\.ts|CommentableType[^s]|RepliesList|components/modal|commentable_type|commentable_id' CLAUDE.md docs/architecture/backend.md docs/architecture/frontend.md`

### TASK-016: Gates finales, migracion de la BD local y verificacion en navegador

Gates de frontend (types, lint:check, format:check, build) y de backend; luego, SOLO con aprobacion explicita del usuario, php artisan migrate:fresh --seed sobre la BD de desarrollo (borra los datos locales, incluidos los comentarios de prueba de SOC-46, y vuelve a sembrar ~146k comentarios) y verificacion manual en el navegador: abrir el dialogo, paginar, publicar un comentario y una respuesta, expandir respuestas, y confirmar que la URL no cambia.

**Type:** test  
**Effort:** M  
**Agent:** general-purpose  
**Priority:** P3  
**Review:** none
**Depends on:** TASK-015

**Acceptance Criteria:**
- [ ] types, lint:check (0 errores), format:check y build pasan; Pint, Rector, PHPStan y las suites de Comment, Post y Admin/Filament pasan (salvo la falla conocida ajena de Admin).
- [ ] Con la BD local migrada, publicar un comentario y una respuesta funciona y la fila queda en comments con model_type y model_id; sin la aprobacion del usuario no se ejecuta migrate:fresh y la verificacion manual queda pendiente.
- [ ] Se pide al usuario correr php artisan test --compact completo y se deja el resumen del refactor en Linear (SOC-46).

**writeScope:**
- (ninguno: tarea de verificacion)

**validateCommand:** `npm run types && npm run lint:check && npm run format:check && npm run build && vendor/bin/pint --dirty --format agent && composer phpstan && php artisan test --compact app/Modules/Comment/Tests app/Modules/Post/Tests`

**Integration notes:** migrate:fresh --seed es destructivo para la BD local: pedir confirmacion antes y no dar por hecha la autorizacion previa.

## Failure Modes

- La BD local conserva commentable_type/commentable_id porque la migracion editada no se vuelve a ejecutar: la app de desarrollo falla al leer o publicar comentarios hasta migrate:fresh --seed (TASK-016, con confirmacion).
- Si la migracion queda en ambas rutas, Laravel falla por clase anonima duplicada o por nombre repetido: TASK-001 borra la global en la misma tarea.
- Entre TASK-001 y TASK-005 la suite de Comment esta en rojo por diseno; entre TASK-004 y TASK-013 el frontend viejo envia commentable_* y el POST responde 422.
- PHPStan falla leyendo _ide_helper_models.php desactualizado: TASK-009 lo regenera contra la BD de pruebas.
- El escaneo de dangerouslySetInnerHTML podria volverse vacio si apunta a una carpeta que no existe: TASK-014 exige que apunte a modules/comment y siga activo.
- Mezclar el trabajo en curso de Admin con este refactor: solo se tocan las tres pruebas de Filament listadas.

## Ship Cut

El backend (TASK-001 a TASK-009) y el frontend (TASK-010 a TASK-014) deben ir juntos: el cambio del payload deja el frontend viejo sin poder publicar. Si se detiene a medias, el primer corte seguro es despues de TASK-013 (la app ya usa el modulo nuevo con el contrato nuevo); TASK-014 (borrado), TASK-015 (docs) y TASK-016 (gates y verificacion local) pueden diferirse sin romper comportamiento.

## Test Coverage Map

- Esquema y migracion movida: validacion de TASK-001 (columnas model_type/model_id y ausencia de la migracion global).
- Modelo, relaciones y factory: CommentModelTest, CommentFactoryTest, PostModelTest y CommentSeederTest (TASK-002, TASK-003).
- Contrato HTTP del store: CommentStoreRequestTest, CommentStoreTest, CommentValidationHardeningTest y CommentThrottleTest (TASK-004, TASK-005).
- Props, paginas anfitrionas y consultas constantes: Tests/Props y CommentQueryCountTest (TASK-006); auditoria, endurecimiento y Resource (TASK-007); Filament (TASK-008).
- Frontend: npm run types, lint:check, format:check y build en cada tarea FE; verificacion manual en navegador en TASK-016.

## Execution Summary

- Tareas: 16
- Ruta critica: TASK-001 -> TASK-002 -> TASK-004 -> TASK-010 -> TASK-011 -> TASK-012 -> TASK-013 -> TASK-014 -> TASK-015 -> TASK-016
- Capas de ejecucion:
  1. TASK-001
  2. TASK-002
  3. TASK-003, TASK-004
  4. TASK-005, TASK-006, TASK-007, TASK-008, TASK-010
  5. TASK-009, TASK-011
  6. TASK-012
  7. TASK-013
  8. TASK-014
  9. TASK-015
  10. TASK-016

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | - |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-002 |
| TASK-004 | TASK-002 |
| TASK-005 | TASK-003, TASK-004 |
| TASK-006 | TASK-003 |
| TASK-007 | TASK-004, TASK-003 |
| TASK-008 | TASK-003 |
| TASK-009 | TASK-005, TASK-006, TASK-007, TASK-008 |
| TASK-010 | TASK-004 |
| TASK-011 | TASK-010 |
| TASK-012 | TASK-011 |
| TASK-013 | TASK-012 |
| TASK-014 | TASK-013, TASK-007 |
| TASK-015 | TASK-009, TASK-014 |
| TASK-016 | TASK-015 |
