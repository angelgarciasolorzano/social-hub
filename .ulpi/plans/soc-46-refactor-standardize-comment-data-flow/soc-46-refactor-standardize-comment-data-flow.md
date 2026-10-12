# Plan: SOC-46: Estandarizar el flujo de datos de Comment entre backend y frontend

## Overview

Hoy CommentController::index() entrega la pagina de comentarios como Inertia::flash, CommentCollection reconstruye a mano data/meta/links y usePaginatedComments lee data.flash.comments. Nuevo contrato: HomeController y ProfileController (index y show) incluyen una clase ProvidesInertiaProperties (CommentThreadProps) que expone dos props Inertia::optional, 'comments' (raiz de un post) y 'replies' (respuestas de un comentario), serializadas con cursorPaginate()->through(CommentResource) dentro de Inertia::scroll. Nada se carga hasta que el dialogo o el hilo de respuestas se abre; el cliente hace un partial reload de la pagina anfitriona (only + preserveUrl) con el hilo y el cursor como parametros. Como la lista ya no pasa por una ruta propia, la ruta comments.index se RETIRA junto con CommentController::index y CommentCollection; el throttle de 60/min por usuario se traslada a CommentThreadProps. CommentController queda con una sola accion (store), por lo que se reemplaza por el controlador invocable CommentStoreController, que usa un Data object con los datos validados. El flash queda solo para la notificacion de store().

**Issue:** [SOC-46](https://linear.app/social-hub-ang/issue/SOC-46/refactor-standardize-comment-data-flow-across-backend-and-frontend) · bloquea SOC-41  
**Modo:** HOLD · **Revision por defecto:** claude  
**Estilo de ejecucion:** Paso a paso: se implementa una tarea y se pausa para revision del usuario (regla .ai/rules/linear.md). TASK-011 es una compuerta: si <InfiniteScroll> de Inertia no funciona en el dialogo se revierte y se conserva el hook manual de TASK-008. Las tareas TASK-004 a TASK-007 dejan la suite de Comment en rojo entre una y otra (se retira una ruta y se migran sus pruebas): ejecutarlas de forma contigua.

## Scope Challenge

Modo HOLD: alcance de SOC-46 sin funcionalidades nuevas. Decisiones del usuario: (1) comments.index se retira porque dejaria de usarse; (2) store pasa a controlador invocable; (3) intentar <InfiniteScroll preserveUrl> en el dialogo y, si da problemas, conservar la via manual estabilizada; (4) Inertia::optional (carga al abrir el dialogo / al expandir respuestas) y no Inertia::defer, que cargaria los comentarios de todos los posts apenas renderice la pagina; skeletons mientras la prop es undefined. Nota: la issue dice 'No cambiar rutas' y 'no cambiar throttles'; retirar comments.index es una desviacion consciente, y el throttle se conserva con el mismo limite (60/min por usuario, presupuesto separado de comments.store). Conviene dejarlo asentado en el comentario de cierre de Linear. Exploracion: el unico consumidor frontend es PostCard -> CommentDialog -> CommentList/RepliesList -> usePaginatedComments; showReplies es un unico id, por lo que solo un hilo de respuestas esta abierto a la vez y una sola prop 'replies' basta. Los anfitriones ya usan Inertia::scroll para 'posts' con el cursor por defecto, asi que comentarios/respuestas necesitan nombres de cursor propios. Siguiendo la preferencia del usuario, el controlador nuevo se crea y el viejo se borra despues, sin rename directo.

## Prerequisites

- SOC-40 mergeada (Comment vive en app/Modules/Comment con tests de CRUD, seguridad, validacion y consultas).
- Se leyeron .ai/rules/{app,backend,frontend,linear}.md: sin variables de una letra (incluidos closures), docblocks con maximo 3 lineas de prosa, hooks de React tipados explicitamente, dialogos nunca mutan la URL (router.reload con preserveUrl + replace), Data solo para estructuras no-modelo y Resources para serializar modelos.
- Inertia v3 (Inertia::optional, Inertia::scroll, ProvidesInertiaProperties, preserveUrl, InfiniteScroll) verificado en la documentacion del MCP inertia-docs.
- El suite 'Comment' ya apunta a app/Modules/Comment/Tests en phpunit.xml y tests/Pest.php, por lo que las subcarpetas nuevas (Tests/Props) no requieren registro adicional.
- Las rutas anfitrionas (home y profile) estan bajo autenticacion; TASK-002 lo confirma con una prueba de invitado.
- Las pruebas que hoy usan route('comments.index') (CommentIndexTest, CommentThrottleTest, CommentQueryCountTest, CommentSecurityAuditTest) se migran, y el usuario aprobo (2026-10-12) borrar CommentIndexTest una vez migrada su cobertura.
- Working tree sin cambios ajenos a SOC-46 (hoy hay modificaciones sin commitear en app/Modules/Admin/ que no deben mezclarse).

## Non-Goals

- Menciones, 'replying to', responder a una respuesta o cambios en el formulario (SOC-41); la profundidad de un nivel se sigue rechazando en store().
- Cambiar la ruta comments.store (POST comments), permisos, autenticacion, tipos de recurso aceptados, limite de 1000 caracteres, normalizacion, el throttle de store u orden descendente por id.
- Likes, edicion, eliminacion, notificaciones nuevas o cambios de esquema.
- Rediseno visual: los skeletons y estados de error reutilizan los estilos existentes.
- Usar Inertia::defer o useHttp como transporte de la lista.

## Contracts

- Carga de la lista (sin ruta propia): el cliente hace router.reload sobre la pagina anfitriona con data {comments_post: <id>, comments_cursor?} para comentarios raiz o {replies_comment: <id>, replies_cursor?} para respuestas, only ['comments'] o ['replies'], preserveState, preserveScroll, preserveUrl:true y replace:true. Cada solicitud lleva el objetivo explicito, de modo que no depende de la query de la URL visible.
- Props en los anfitriones (HomeController::__invoke, ProfileController::index/show) via CommentThreadProps: 'comments' y 'replies', ambas Inertia::optional, evaluadas solo si el partial reload las pide. Cada una es Inertia::scroll(cursorPaginate(10, cursorName: 'comments_cursor'|'replies_cursor') con ->through(fn => new CommentResource(...)->resolve(request))). Parametro no entero o commentable inexistente => 404.
- Throttle: la evaluacion de 'comments' o 'replies' consume un presupuesto de 60 solicitudes por minuto por usuario autenticado (RateLimiter con clave propia 'comments.index', separada de la de comments.store); al excederlo responde 429 (ThrottleRequestsException) y el cliente lo trata como error reintentable.
- Consulta: with('user') + withCount('comments') + orderByDesc('id') en la query; CommentResource sigue sin disparar consultas ocultas (repliesCount reutiliza comments_count) y el numero de consultas es constante al crecer comentarios y respuestas.
- Forma serializada de cada comentario sin cambios: {id, content, createdAt, user:{id,name}, repliesInfo:{hasReplies, repliesCount}}. La forma exacta de la envoltura (data/links/meta) la fija el comportamiento real de Inertia::scroll y se registra en TASK-001; TASK-008 adapta los tipos TS a ella.
- Escritura: POST comments (nombre comments.store, auth+verified, throttle:6,1,comments.store) pasa a App\Modules\Comment\Controllers\CommentStoreController (invocable). Recibe CommentStoreRequest, construye CommentStoreData desde $request->validated(), mantiene el rechazo de responder a una respuesta (error en commentable_id) y el flash de exito {type:'success', message}.
- Se retiran: ruta comments.index, CommentController (reemplazado por CommentStoreController), CommentCollection, el import Wayfinder de index en el frontend y hasPaginatedKey si queda sin consumidores.

## Existing Code Leverage

- app/Auth/Modules/TrustedDevice/Props/TrustedDeviceCurrentProps.php: patron de ProvidesInertiaProperties con Inertia::optional (se toma la idea, no el diseno: aqui las cargas son paginadas y parametrizadas por hilo).
- app/Modules/Comment/Resources/CommentResource.php y Comment::repliesCount() ya evitan N+1 cuando existe comments_count.
- app/Home/Controllers/HomeController.php y app/User/Profile/Controllers/ProfileController.php ya usan Inertia::scroll para 'posts'.
- resources/js/modules/comments/ (CommentList, RepliesList, CommentItem, CommentForm) conservan su UI; solo cambia el origen de datos.
- Pruebas existentes de SOC-40 (CommentIndexTest, CommentResourceTest, CommentQueryCountTest, CommentSecurityAuditTest, CommentControllerHardeningTest, CommentStoreTest, CommentStoreRequestTest, CommentThrottleTest, CommentValidationHardeningTest) como red de seguridad del refactor.

## Tasks

### TASK-001: Crear CommentThreadProps (props optional 'comments' y 'replies' con throttle)

Crear app/Modules/Comment/Props/CommentThreadProps.php implementando Inertia\ProvidesInertiaProperties. Lee de la request los parametros comments_post y replies_comment, valida que sean enteros, resuelve el commentable con findOrFail y devuelve dos props Inertia::optional con Inertia::scroll(cursorPaginate(10, cursorName: ...)) usando ->through() con CommentResource. Antes de resolver cada prop consume el presupuesto de RateLimiter (60/min por usuario, clave 'comments.index') y lanza ThrottleRequestsException al excederlo. Registrar en la prueba la forma real de la envoltura que produce Inertia::scroll (fija el contrato TS) y confirmar que el cursor propio no choca con el de 'posts'.

**Type:** refactor  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Review:** claude

**Acceptance Criteria:**
- [ ] Sin parametros del hilo las props no se evaluan ni ejecutan consultas; con comments_post valido devuelve 10 comentarios por pagina en orden descendente de id y un cursor siguiente propio (comments_cursor); con replies_comment valido devuelve sus respuestas con replies_cursor.
- [ ] Con comments_post o replies_comment no entero, o de un id inexistente, responde 404; la solicitud 61 del mismo usuario en un minuto responde 429, otro usuario conserva su propio presupuesto, y el limite de comments.store (6/min) no se ve afectado.
- [ ] La consulta carga user y comments_count en la query: el numero de consultas es el mismo con 12 que con 30 comentarios, y el payload no contiene campos fuera de {id, content, createdAt, user, repliesInfo}.

**writeScope:**
- `app/Modules/Comment/Props/CommentThreadProps.php` (nuevo)
- `app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php` (nuevo)

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php`

**Integration notes:** El wiring en los anfitriones es responsabilidad de TASK-002. No requiere registro en provider: es una clase instanciada por los controladores.

### TASK-002: Incluir CommentThreadProps en Home y Profile (index y show)

Agregar CommentThreadProps a Inertia::render en HomeController::__invoke y ProfileController::index/show. Crear una prueba de integracion que pida cada pagina con y sin partial reload y como invitado.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Review:** claude
**Depends on:** TASK-001

**Acceptance Criteria:**
- [ ] Una visita normal a home y a profile (propio y ajeno) no incluye 'comments' ni 'replies'; un partial reload con only=comments y comments_post={id} la incluye en las tres rutas, y lo mismo con only=replies y replies_comment={id}.
- [ ] Un partial reload con comments_post inexistente responde 404 en las tres rutas, y un invitado sigue siendo redirigido a login.
- [ ] Las props 'posts' y 'user' de cada anfitrion no cambian de forma ni de paginacion.

**writeScope:**
- `app/Home/Controllers/HomeController.php`
- `app/User/Profile/Controllers/ProfileController.php`
- `app/Modules/Comment/Tests/Props/CommentThreadHostPagesTest.php` (nuevo)

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Props/CommentThreadHostPagesTest.php`

**Integration notes:** Confirmar al implementar como se pasa un ProvidesInertiaProperties en el arreglo de props (revisar el uso existente de TrustedDeviceCurrentProps).

### TASK-003: Crear CommentStoreController invocable y CommentStoreData

Crear app/Modules/Comment/Controllers/CommentStoreController.php con __invoke(CommentStoreRequest) que mueve la logica de CommentController::store sin cambios de comportamiento, y app/Modules/Comment/Data/CommentStoreData.php (commentable_type tipado como CommentType, commentable_id int, content string) construido desde $request->validated(). Apuntar la ruta comments.store al controlador nuevo en routes.php; comments.index y CommentController siguen intactos hasta TASK-004. Las reglas FluentRule se quedan en el FormRequest.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Review:** claude

**Acceptance Criteria:**
- [ ] CommentStoreTest, CommentStoreRequestTest y CommentValidationHardeningTest pasan sin modificarse (mismas reglas, mensajes, normalizacion y flash de exito), y comments.store conserva auth+verified y throttle:6,1,comments.store.
- [ ] Responder a una respuesta sigue rechazandose con el error de validacion en commentable_id y no se crea ningun comentario.
- [ ] CommentStoreController no usa input() ni anotaciones @var para tipo, id o contenido, y php artisan route:list --name=comments.store muestra CommentStoreController como accion.

**writeScope:**
- `app/Modules/Comment/Controllers/CommentStoreController.php` (nuevo)
- `app/Modules/Comment/Data/CommentStoreData.php` (nuevo)
- `app/Modules/Comment/routes/routes.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Requests/CommentStoreRequestTest.php app/Modules/Comment/Tests/Crud/CommentStoreTest.php app/Modules/Comment/Tests/Security/CommentValidationHardeningTest.php`

**Integration notes:** Crear-luego-borrar: CommentController se elimina en TASK-004, no se renombra. Los controladores se auto-descubren por namespace; no requiere registro en provider.

### TASK-004: Retirar comments.index, CommentController y CommentCollection

Con las props ya disponibles (TASK-002) y el store invocable (TASK-003), eliminar la ruta comments.index de routes.php, borrar app/Modules/Comment/Controllers/CommentController.php (solo conserva index) y app/Modules/Comment/Resources/CommentCollection.php. Verificar con rg que no queda ningun import.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-002, TASK-003

**Acceptance Criteria:**
- [ ] php artisan route:list --name=comments solo muestra comments.store, y GET comments/post/{id} responde 404.
- [ ] rg 'CommentController|CommentCollection|comments\.index' app/Modules/Comment/routes app/Modules/Comment/Controllers app/Modules/Comment/Resources no devuelve resultados.
- [ ] CommentStoreTest sigue en verde tras el borrado.

**writeScope:**
- `app/Modules/Comment/routes/routes.php`
- `app/Modules/Comment/Controllers/CommentController.php`
- `app/Modules/Comment/Resources/CommentCollection.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Crud/CommentStoreTest.php && ! rg -n 'CommentController|CommentCollection|comments\.index' app/Modules/Comment/routes app/Modules/Comment/Controllers app/Modules/Comment/Resources`

**Integration notes:** Deja en rojo las pruebas que aun usan la ruta o las clases retiradas; TASK-005 a TASK-007 las migran. Inicio de la ventana contigua de TASK-004 a TASK-007.

### TASK-005: Migrar la cobertura de CommentIndexTest a las pruebas de Props y retirarlo

Mapear cada caso de CommentIndexTest (invitado a login, orden descendente y cursor, respuestas de un comentario, ultima pagina sin cursor, commentable inexistente, tipo invalido) a CommentThreadPropsTest o CommentThreadHostPagesTest, completando los que falten. Una vez migrada la cobertura, borrar CommentIndexTest (aprobado por el usuario).

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-004

**Acceptance Criteria:**
- [ ] Cada caso de CommentIndexTest tiene un equivalente nombrado en las pruebas de Props o HostPages, incluidos los de falla: commentable inexistente => 404 y tipo/parametro invalido => 404 o ignorado segun el contrato.
- [ ] No queda ninguna referencia a route('comments.index') en las pruebas de Comment y CommentIndexTest se borra (aprobado por el usuario) solo despues de migrar su cobertura.

**writeScope:**
- `app/Modules/Comment/Tests/Crud/CommentIndexTest.php`
- `app/Modules/Comment/Tests/Props/CommentThreadHostPagesTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Props`

### TASK-006: Migrar pruebas de throttle y de consultas constantes

Actualizar CommentThrottleTest (los casos de comments.index pasan a verificar el limite de 60/min de CommentThreadProps via partial reload; los de comments.store no cambian) y CommentQueryCountTest (medir consultas de la primera pagina de comentarios y respuestas por el partial reload).

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-004

**Acceptance Criteria:**
- [ ] La solicitud 61 de comentarios/respuestas del mismo usuario responde 429, los usuarios son independientes y el limite de store (6/min) no consume el presupuesto de lectura ni viceversa.
- [ ] Cargar la primera pagina de comentarios y de respuestas ejecuta el mismo numero de consultas con pocos y con muchos comentarios o respuestas (sin N+1).

**writeScope:**
- `app/Modules/Comment/Tests/Security/CommentThrottleTest.php`
- `app/Modules/Comment/Tests/Security/CommentQueryCountTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Security/CommentThrottleTest.php app/Modules/Comment/Tests/Security/CommentQueryCountTest.php`

### TASK-007: Adaptar pruebas de Resource, auditoria y hardening a la nueva estructura

CommentResourceTest deja de afirmar el orden ['data','meta','links'] de CommentCollection; CommentSecurityAuditTest actualiza H1 (throttle ahora en CommentThreadProps), H5, la lectura del flash y la lista esperada de rutas (solo comments.store) y la lectura del codigo fuente; CommentControllerHardeningTest apunta a CommentStoreController.php en lugar de CommentController.php.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-004

**Acceptance Criteria:**
- [ ] No queda ninguna referencia a CommentCollection, comments.index, SessionKey::FLASH_DATA['comments'] ni a Controllers/CommentController.php en app/Modules/Comment/Tests.
- [ ] La prueba de privacidad sigue verificando que el payload no expone email, password ni otros campos de User, ahora sobre la prop serializada, y la de rutas afirma que solo existe comments.store.
- [ ] La prueba de hardening sigue afirmando que store() responde por un unico canal de flash y sin ramas de error que lo eviten.

**writeScope:**
- `app/Modules/Comment/Tests/Resources/CommentResourceTest.php`
- `app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php`
- `app/Modules/Comment/Tests/Security/CommentControllerHardeningTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Resources/CommentResourceTest.php app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php app/Modules/Comment/Tests/Security/CommentControllerHardeningTest.php && php artisan test --compact app/Modules/Comment/Tests`

**Integration notes:** Fin de la ventana contigua: aqui toda la suite de Comment debe volver a verde.

### TASK-008: Reescribir usePaginatedComments sobre props de Inertia y ajustar tipos

Reemplazar la lectura de data.flash.comments por usePage().props.comments|replies. El hook hace router.reload sobre la pagina anfitriona con el objetivo explicito (comments_post / replies_comment y cursor) con only, preserveState, preserveScroll, preserveUrl y replace; usa reset al cambiar de objetivo; combina paginas deduplicando por id; expone un estado de error distinto del vacio, libera el cursor tras un fallo (404, 429, red) para permitir reintento y elimina el setTimeout fragil del montaje. Ajustar PaginatedComments a la forma registrada en TASK-001. Tipar explicitamente todos los hooks de React.

**Type:** refactor  
**Effort:** L  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-001

**Acceptance Criteria:**
- [ ] Carga inicial, siguiente cursor y refresco tras publicar fusionan paginas sin duplicar ids y conservan el orden descendente; hasMoreComments refleja el cursor siguiente del servidor.
- [ ] Una respuesta con error (404, 429 o red) deja error=true (no lista vacia), libera el cursor y la siguiente llamada reintenta la misma pagina.
- [ ] Ninguna solicitud del hook cambia window.location (preserveUrl), y ya no existen referencias a data.flash.comments ni al import Wayfinder comments.index en resources/js/modules/comments.

**writeScope:**
- `resources/js/modules/comments/hooks/usePaginatedComments.ts`
- `resources/js/modules/comments/types/comment.ts`

**validateCommand:** `npx eslint resources/js/modules/comments/hooks/usePaginatedComments.ts resources/js/modules/comments/types/comment.ts`

**Integration notes:** El contrato del hook cambia (error, retry); npm run types puede fallar en los consumidores hasta TASK-010, por eso esta tarea valida solo con eslint y TASK-010 corre types y build.

### TASK-009: Regenerar Wayfinder y adaptar CommentForm al controlador invocable

Ejecutar php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder y cambiar el import de CommentForm.tsx de la accion 'store' de CommentController a la accion del controlador invocable CommentStoreController (confirmar la firma generada). El resto del formulario no cambia.

**Type:** refactor  
**Effort:** XS  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-004

**Acceptance Criteria:**
- [ ] CommentForm.tsx importa la accion de CommentStoreController y envia a la misma URL (/comments, POST); no queda ningun import de CommentController en resources/js fuera de shared/wayfinder.
- [ ] npx eslint sobre CommentForm.tsx pasa y la accion importada existe tras regenerar Wayfinder (un import de un controlador retirado falla en tsc).

**writeScope:**
- `resources/js/modules/comments/components/forms/CommentForm.tsx`

**validateCommand:** `php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder && npx eslint resources/js/modules/comments/components/forms/CommentForm.tsx`

**Integration notes:** shared/wayfinder esta en .gitignore: la regeneracion no genera cambios versionados.

### TASK-010: Adaptar CommentList, RepliesList y CommentItem (skeleton, error y reintento)

Consumir el nuevo contrato del hook: mostrar skeleton mientras la prop es undefined (sin rediseno visual), un estado de error con boton de reintento distinto del estado vacio, y mantener el refresco tras publicar. Aplicar reset al cambiar de post o de hilo para no mostrar datos del anterior.

**Type:** refactor  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-008, TASK-009

**Acceptance Criteria:**
- [ ] Al abrir el dialogo aparece un skeleton hasta que llega la prop 'comments'; al expandir respuestas aparece un skeleton hasta que llega 'replies'; cambiar de hilo no muestra datos del hilo anterior.
- [ ] Si la carga falla se muestra un mensaje con reintento, no el estado vacio, y al reintentar se carga la pagina.
- [ ] Publicar un comentario o una respuesta refresca la lista correspondiente y actualiza repliesCount sin recargar posts, y la URL del anfitrion no cambia.

**writeScope:**
- `resources/js/modules/comments/components/CommentList.tsx`
- `resources/js/modules/comments/components/RepliesList.tsx`
- `resources/js/modules/comments/components/CommentItem.tsx`

**validateCommand:** `npx eslint resources/js/modules/comments && npm run types && npm run build`

**Integration notes:** Corte entregable: tras esta tarea el flujo completo funciona de punta a punta con el hook manual. Verificar manualmente en el navegador (Home y Profile) antes de pausar.

### TASK-011: Compuerta: probar <InfiniteScroll preserveUrl> en el dialogo (con reversion)

Intento acotado de reemplazar el sentinel con IntersectionObserver de CommentList y RepliesList por <InfiniteScroll data='comments' preserveUrl> y <InfiniteScroll data='replies' preserveUrl>. Riesgo conocido: la URL visible del anfitrion no contiene el parametro del hilo, por lo que la solicitud de la pagina siguiente podria perder el objetivo. Si el componente no resuelve el contenedor con scroll del dialogo, la pagina siguiente sin objetivo o el reintento, se revierte a la implementacion de TASK-010 y se documenta el motivo en la revision.

**Type:** refactor  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P3  
**Review:** claude
**Depends on:** TASK-010

**Acceptance Criteria:**
- [ ] Con InfiniteScroll: dentro del dialogo la siguiente pagina de comentarios y de respuestas carga al llegar al final del contenedor con scroll, sin duplicados y sin cambiar window.location.
- [ ] Un fallo de carga permite reintentar y no se confunde con la lista vacia; si no se puede garantizar, se revierte a TASK-010 y el diff queda sin cambios en CommentList.tsx y RepliesList.tsx.
- [ ] npm run types, lint:check y build pasan tanto con InfiniteScroll como tras la reversion.

**writeScope:**
- `resources/js/modules/comments/components/CommentList.tsx`
- `resources/js/modules/comments/components/RepliesList.tsx`

**validateCommand:** `npx eslint resources/js/modules/comments && npm run types && npm run build`

**Integration notes:** Tarea de decision: el resultado (adoptar o revertir) lo decide el usuario en la pausa de revision. Si se adopta, usePaginatedComments puede simplificarse en un seguimiento.

### TASK-012: Retirar helpers y tipos obsoletos

Eliminar hasPaginatedKey (y su export) si ya nadie lo consume, y limpiar tipos que hayan quedado sin uso tras el cambio de contrato (por ejemplo PaginatedResponse o PaginationMeta/Links si solo los usaba Comment). Verificar con rg antes de borrar.

**Type:** chore  
**Effort:** XS  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-011

**Acceptance Criteria:**
- [ ] rg hasPaginatedKey resources/js no devuelve resultados tras el borrado, y cualquier tipo de paginacion que siga usado por otro modulo se conserva.
- [ ] npm run types y npm run build pasan sin referencias rotas al helper ni a los tipos retirados.

**writeScope:**
- `resources/js/shared/lib/hasPaginatedKey.ts`
- `resources/js/shared/lib/index.ts`
- `resources/js/shared/types/pagination.ts`

**validateCommand:** `npm run types && npm run lint:check`

**Integration notes:** Si rg encuentra otro consumidor de hasPaginatedKey o de los tipos, esa pieza se conserva y se anota en la revision.

### TASK-013: Actualizar CLAUDE.md con el nuevo flujo de datos de Comment

Actualizar la seccion Comment y la de Inertia/Frontend de CLAUDE.md: CommentThreadProps, props optional 'comments' y 'replies', cursores propios, throttle de lectura trasladado a CommentThreadProps, retiro de la ruta comments.index, CommentStoreController invocable con CommentStoreData, flash solo para notificacion, hook manual (o InfiniteScroll segun TASK-011) y retiro de CommentController/CommentCollection/hasPaginatedKey.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Review:** claude
**Depends on:** TASK-007, TASK-012

**Acceptance Criteria:**
- [ ] CLAUDE.md describe el contrato final y no menciona comments.index, CommentCollection, CommentController, data.flash.comments ni hasPaginatedKey como vigentes.
- [ ] La documentacion refleja la decision real de TASK-011 (InfiniteScroll adoptado o hook manual conservado).

**writeScope:**
- `CLAUDE.md`

**validateCommand:** `! rg -n 'CommentCollection|data\.flash\.comments|hasPaginatedKey' CLAUDE.md`

### TASK-014: Gates finales backend y frontend

Verificacion sin cambios de codigo: Pint, PHPStan, Rector (dry-run y apply si corresponde), suite Pest de Comment mas las pruebas de Home y Profile, y los gates frontend. Despues, dejar el resumen en el issue de Linear (Definition of Done), incluyendo la desviacion de retirar comments.index, y abrir el PR con la plantilla.

**Type:** test  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Review:** none
**Depends on:** TASK-013

**Acceptance Criteria:**
- [ ] vendor/bin/pint --dirty --format agent, composer phpstan, composer rector-dry y php artisan test --compact app/Modules/Comment/Tests terminan sin errores ni cambios pendientes.
- [ ] npm run types, npm run lint:check, npm run format:check y npm run build terminan sin errores.
- [ ] Se solicita al usuario correr php artisan test --compact completo, como indica la regla de Pest del proyecto.

**writeScope:**
- (ninguno: tarea de verificacion)

**validateCommand:** `vendor/bin/pint --dirty --format agent && composer phpstan && composer rector-dry && php artisan test --compact app/Modules/Comment/Tests && npm run types && npm run lint:check && npm run format:check && npm run build`

## Failure Modes

- Parametro de hilo no entero, ausente o commentable inexistente en el partial reload: 404, nunca lista vacia silenciosa.
- Paginacion de comentarios choca con el cursor de 'posts': se evita con cursorName propio por prop.
- Presupuesto de throttle compartido con comments.store: se evita con una clave de RateLimiter propia, probada en TASK-001.
- <InfiniteScroll preserveUrl> en el dialogo pide la pagina siguiente sin el parametro del hilo porque la URL visible no lo contiene: TASK-011 debe probarlo; si falla, se revierte al hook manual que siempre envia el objetivo explicito.
- Carga fallida (404, 429, red) mostrada como lista vacia o cursor bloqueado: el hook expone un estado de error distinto del vacio y libera el cursor para reintentar.
- Cambiar de post o de hilo muestra datos del anterior: el cliente usa reset:['comments'|'replies'] al cambiar de objetivo y muestra skeleton hasta que la prop llega.
- Entre TASK-004 y TASK-007 hay pruebas que referencian la ruta o la clase retiradas y fallan hasta migrarse: no mezclar con otro trabajo ni commitear a medias.
- Wayfinder para un controlador invocable exporta una accion por defecto y no 'store': la firma exacta se confirma al regenerar en TASK-009.

## Ship Cut

Si la ejecucion se detiene antes de TASK-010 el frontend sigue leyendo el flash (ya retirado) o la ruta comments.index (ya retirada) y el flujo se rompe: no es un corte seguro. El primer corte entregable es TASK-010 (backend + hook manual + consumidores). TASK-011 (InfiniteScroll), TASK-012 (limpieza) y TASK-013 (docs) pueden recortarse sin romper comportamiento.

## Test Coverage Map

- Props optional, forma serializada, cursor propio, orden descendente, 404 de parametros invalidos, throttle de 60/min por usuario con presupuesto independiente: app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php (TASK-001).
- Pagina anfitriona no incluye comments/replies en visitas normales, los entrega en partial reload, invitados redirigidos a login: app/Modules/Comment/Tests/Props/CommentThreadHostPagesTest.php (TASK-002).
- Store invocable: CommentStoreTest, CommentStoreRequestTest, CommentValidationHardeningTest y CommentThrottleTest (parte de store) sin cambios de comportamiento (TASK-003).
- Cobertura de CommentIndexTest migrada a las pruebas de Props antes de borrarla (TASK-005); throttle y consultas constantes (TASK-006); privacidad, rutas esperadas y hardening sobre los archivos nuevos (TASK-007).
- Frontend: npm run types, lint:check, format:check y build (TASK-008 a TASK-013); verificacion manual de dialogo, respuestas, reintento y URL intacta en TASK-010/011.

## Execution Summary

- Tareas: 14
- Ruta critica: TASK-001 -> TASK-002 -> TASK-004 -> TASK-009 -> TASK-010 -> TASK-011 -> TASK-012 -> TASK-013 -> TASK-014
- Capas de ejecucion:
  1. TASK-001, TASK-003
  2. TASK-002, TASK-008
  3. TASK-004
  4. TASK-005, TASK-006, TASK-007, TASK-009
  5. TASK-010
  6. TASK-011
  7. TASK-012
  8. TASK-013
  9. TASK-014

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | - |
| TASK-002 | TASK-001 |
| TASK-003 | - |
| TASK-004 | TASK-002, TASK-003 |
| TASK-005 | TASK-004 |
| TASK-006 | TASK-004 |
| TASK-007 | TASK-004 |
| TASK-008 | TASK-001 |
| TASK-009 | TASK-004 |
| TASK-010 | TASK-008, TASK-009 |
| TASK-011 | TASK-010 |
| TASK-012 | TASK-011 |
| TASK-013 | TASK-007, TASK-012 |
| TASK-014 | TASK-013 |
