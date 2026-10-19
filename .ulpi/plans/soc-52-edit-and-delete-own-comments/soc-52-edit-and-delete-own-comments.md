# Plan: SOC-52: Editar y eliminar comentarios propios

## Overview

Hoy el módulo Comment solo tiene comments.store (y comments.like en el módulo Like); el test de seguridad [H8] afirma que no hay rutas para editar o eliminar. Este plan permite que el autor edite el contenido de su comentario (marca edited_at, mismo límite y normalización que al crear) y lo elimine con borrado mixto: con respuestas, borrado lógico y tombstone 'Comentario eliminado' que conserva el hilo; sin respuestas, borrado físico junto con sus likes en la misma transacción. Añade CommentPolicy (solo el autor), rutas PATCH y DELETE con throttle propio, tombstones en los listados sin romper el número constante de consultas y la UI (menú, diálogo de edición, confirmación con el Dialog existente, 'editado' y tombstone).

**Issue:** [SOC-52](https://linear.app/social-hub-ang/issue/SOC-52/feature-edit-and-delete-own-comments)  
**Modo:** HOLD · **Revisión por defecto:** claude  
**Estilo de ejecución:** Paso a paso: una tarea y pausa para revisión (regla .ai/rules/linear.md).

## Scope Challenge

Modo HOLD con decisiones del usuario: (1) borrado mixto (lógico con respuestas, físico sin ellas, likes borrados explícitamente porque likes no tiene FK); (2) confirmación con el Dialog existente: shadcn no tiene alert-dialog y añadirlo sería una dependencia nueva; (3) sin ventana de edición, columna edited_at, mismo límite de 1000 y throttle propio por ruta. Hallazgos del repositorio: SoftDeletes cambia las consultas por defecto de todo el proyecto: Post::comments(), User::comments() y el panel Admin (CommentResource, widgets y relation managers) dejarán de ver los tombstones; se acepta porque la moderación desde Admin está fuera de alcance, y el plan lo cubre corriendo la suite completa. Casos límite resueltos explícitamente: un tombstone se limpia (borrado físico) cuando se elimina su última respuesta; no se puede comentar sobre un tombstone pero sí responder a respuestas de su hilo; las respuestas nunca son tombstones porque no tienen hijos. La descripción de la issue menciona AlertDialog, docs/architecture/backend.md y el skill: este plan usa Dialog y, además, corrige en la documentación la omisión de SOC-51 (Like seguía listado como arquitectura vieja).

## Prerequisites

- SOC-51 está mergeada en main: existe app/Modules/Like/ con Like::likes() de Comment, el índice único y comments.like.
- Se leyeron .ai/rules/{app,backend,frontend,linear}.md: sin variables de una letra, docblocks con máximo 3 líneas, factories completas, un test = una conducta, helpers de test al inicio del archivo.
- Las migraciones existentes de comments no se editan: edited_at y deleted_at llegan en una migración nueva dentro de app/Modules/Comment/Database/Migrations/.
- Para la suite completa en local hay que usar php -d memory_limit=2G vendor/bin/pest (artisan test agota los 128M por un test de imágenes de Admin).
- Rama propia de GitButler para SOC-52 (rama sugerida: soc-52-feature-edit-and-delete-own-comments).

## Non-Goals

- Moderación desde el panel Admin (ocultar o eliminar comentarios ajenos) y permisos Admin dedicados.
- Historial de versiones de un comentario editado.
- Notificaciones y anidación de más de un nivel.
- Restaurar comentarios eliminados.
- Ventana de tiempo para editar.
- Añadir @radix-ui/react-alert-dialog o cualquier dependencia nueva.
- replied_to_comment_id: la mención sigue dependiendo solo de replied_to_user_id.

## Contracts

- Persistencia: comments.edited_at (timestamp nullable) y comments.deleted_at (softDeletes). Comment usa SoftDeletes, edited_at en #[Fillable] con cast a fecha. La clave de borrado: un comentario eliminado lógicamente siempre tuvo respuestas al borrarse y es siempre de primer nivel.
- Policy: CommentPolicy::update/delete solo para el autor (user_id == id del usuario) y nunca sobre un comentario eliminado; registrada con #[UsePolicy] en Comment y aplicada en los controladores con #[Authorize('update'|'delete', 'comment')] (corre tras el route model binding y antes de la validación).
- Rutas (auth + verified): PATCH comments/{comment} → comments.update, throttle:10,1,comments.update; DELETE comments/{comment} → comments.destroy, throttle:10,1,comments.destroy. Ambas responden Inertia::flash success y back() como comments.store; 403 si no es el autor, 404 si no existe o ya está eliminado, 429 al exceder. Un único CommentController con los métodos store, update y destroy (el invocable CommentStoreController se fusionó en él antes de SOC-52 TASK-007).
- Edición: CommentUpdateRequest solo acepta content (obligatorio, máximo 1000) con la misma normalización que CommentStoreRequest (concern NormalizesCommentContent compartido); edited_at = now() solo si el contenido cambió.
- Borrado (DB::transaction): con respuestas → delete() lógico y likes conservados; sin respuestas → $comment->likes()->delete() y forceDelete(); si era una respuesta y su padre está eliminado lógicamente y no le quedan más respuestas → forceDelete del padre y de sus likes. El contenido de un tombstone permanece en la base pero nunca se serializa.
- Listados: CommentThreadProps lista los raíz con withTrashed() y busca el padre de las respuestas con withTrashed(); sigue habiendo un número constante de consultas. CommentStoreController da 404 al comentar sobre un tombstone y, al responder a una respuesta de un hilo con raíz eliminado, busca el padre con withTrashed().
- Resource: CommentResource añade editedAt, isDeleted e isMine (true solo si el usuario autenticado es el autor; false para invitados; se calcula comparando user_id, sin Gate ni consultas); un tombstone serializa content null, user null, isMine false, likesInfo en cero y conserva id, createdAt y repliesInfo. Cada bloque (author, repliedTo, repliesInfo, likesInfo, isMine) vive en un método privado. Sin consultas adicionales.
- Frontend: Comment.editedAt, Comment.isMine, Comment.isDeleted y content/user nullables; CommentActionsMenu (DropdownMenu), CommentEditDialog (PATCH con useForm) y CommentDeleteDialog (Dialog existente, router.delete) con acciones de Wayfinder (named imports de CommentController, p. ej. update y destroy); errores 429/red sin modal de Inertia (onHttpException/onNetworkError devuelven false); tras editar o eliminar se refresca el hilo con uploadedComments.
- Protecciones previas intactas: throttle:6,1,comments.store, throttle de comments.like, normalización de content, profundidad de un nivel y consultas constantes.

## Existing Code Leverage

- app/Auth/Modules/TrustedDevice/Policies/TrustedDevicePolicy.php y #[UsePolicy]/#[Authorize] en TrustedDeviceController: patrón de la policy y de su aplicación.
- app/Modules/Comment/Requests/CommentStoreRequest.php: normalización y reglas FluentRule que se comparten.
- app/Modules/Comment/Controllers/CommentController.php (antes CommentStoreController): store() con Inertia::flash()->back() y el aplanado de hilos.
- app/Modules/Comment/routes/routes.php y Tests/Security/{CommentThrottleTest,CommentSecurityAuditTest,CommentQueryCountTest}.php: rutas con throttle de prefijo propio y pruebas de límites, [H8] y consultas constantes.
- app/Modules/Comment/Props/CommentThreadProps.php y Resources/CommentResource.php: listados paginados y serialización con precarga de contadores.
- app/Modules/Like/: Comment::likes() para borrar likes explícitamente.
- tests/Pest.php: createComment(), createCommentOn(), createCommentsOn() y createLike().
- resources/js/modules/comment/components/{CommentItem,CommentList,CommentRepliesList,forms/CommentForm,dialog/CommentInputDialog}.tsx y hooks/useCommentPaginated.ts: patrón de diálogos, formularios, errores sin modal de Inertia y refresco del hilo (uploadedComments).
- shadcn ya instalado: dialog, dropdown-menu, textarea, button, alert, skeleton (no existe alert-dialog).

## Tasks

### TASK-001: Migración y modelo: edited_at y borrado lógico en comments

Crea una migración nueva en app/Modules/Comment/Database/Migrations/ (la original no se edita; CommentServiceProvider ya la registra con loadMigrationsFrom) que añade edited_at (timestamp nullable) y softDeletes() a comments. Comment usa SoftDeletes, añade edited_at a #[Fillable], lo castea a datetime inmutable y documenta las propiedades. Con SoftDeletes, las consultas por defecto (Admin, User::comments(), Post::comments()) dejan de ver los comentarios eliminados lógicamente.

**Type:** feature  
**Priority:** P0  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- [ ] Comment::delete() deja la fila con deleted_at, el comentario desaparece de Comment::query() y de Post::comments(), y Comment::withTrashed() lo sigue encontrando.
- [ ] edited_at es null para un comentario nuevo y se castea a fecha al asignarlo; la migración original create_comments_table y la de replied_to_user_id no se modifican.
- [ ] migrate:fresh en testing aplica las tres migraciones sin error y down() de la nueva elimina ambas columnas.

**writeScope:**

- `app/Modules/Comment/Database/Migrations/<timestamp>_add_edited_at_and_soft_deletes_to_comments_table.php` (generada con php artisan make:migration --path)
- `app/Modules/Comment/Models/Comment.php` (SoftDeletes, Fillable, cast, docblock)
- `app/Modules/Comment/Tests/Models/CommentModelTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Models/CommentModelTest.php`

### TASK-002: Factory completa: edited_at, deleted_at y estado edited()

CommentFactory declara edited_at y deleted_at como null (regla de factories completas) y añade los estados edited() (fecha posterior a created_at) y trashed() (deleted_at = ahora); el Factory de Laravel no trae un trashed() propio, así que se define aquí.

**Type:** feature  
**Priority:** P0  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] Comment::factory()->create() persiste edited_at y deleted_at en null; ->edited()->create() deja edited_at posterior a created_at.
- [ ] CommentFactory::new()->trashed()->create() persiste deleted_at y el comentario no aparece en Comment::query() pero sí en withTrashed().
- [ ] Los helpers createComment()/createCommentOn() de tests/Pest.php siguen funcionando sin cambios.

**writeScope:**

- `app/Modules/Comment/Database/Factories/CommentFactory.php`
- `app/Modules/Comment/Tests/Factories/CommentFactoryTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Factories/CommentFactoryTest.php`

### TASK-003: CommentPolicy: solo el autor edita y elimina

Crea CommentPolicy en app/Modules/Comment/Policies/ con update y delete (true solo si comment->user_id es el id del usuario; un comentario eliminado lógicamente no se puede editar) y la registra con #[UsePolicy(CommentPolicy::class)] en Comment (la autodetección por nombre no la encontraría bajo Modules/, igual que TrustedDevicePolicy). Admin no tiene permiso propio: la moderación queda fuera de alcance.

**Type:** feature  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] El autor puede update y delete su comentario; otro usuario recibe false en ambas habilidades (Gate::forUser).
- [ ] Un comentario con deleted_at ya no es editable ni siquiera por su autor (update false), y delete sobre uno ya eliminado no se ofrece.
- [ ] La policy se resuelve sin registro manual: Gate::getPolicyFor(Comment::class) devuelve CommentPolicy.

**writeScope:**

- `app/Modules/Comment/Policies/CommentPolicy.php`
- `app/Modules/Comment/Models/Comment.php` (#[UsePolicy])
- `app/Modules/Comment/Tests/Policies/CommentPolicyTest.php` (la suite Comment ya cubre la carpeta nueva)

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Policies/CommentPolicyTest.php`

### TASK-004: Extraer la normalización del contenido a un concern compartido

Mueve la normalización de prepareForValidation() de CommentStoreRequest (quitar caracteres de control conservando tabulaciones y saltos de línea, y trim) a un trait Concerns/NormalizesCommentContent.php en la raíz del módulo (como app/Auth/Modules/TrustedDevice/Concerns/), y hace que CommentStoreRequest lo use. Sin cambios de comportamiento: los tests existentes de CommentStoreRequest y de hardening lo demuestran.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- [ ] CommentStoreRequestTest y CommentValidationHardeningTest siguen pasando sin cambiar ninguna aserción.
- [ ] El trait normaliza solo cuando content es string: con content ausente o no string no lanza ni lo crea.
- [ ] CommentStoreRequest ya no contiene la expresión regular de normalización (vive en un único lugar).

**writeScope:**

- `app/Modules/Comment/Concerns/NormalizesCommentContent.php`
- `app/Modules/Comment/Requests/CommentStoreRequest.php` (usa el trait)

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Requests/CommentStoreRequestTest.php app/Modules/Comment/Tests/Security/CommentValidationHardeningTest.php`

### TASK-005: CommentUpdateRequest con la misma normalización y límite de 1000

Crea CommentUpdateRequest (HasFluentRules, NormalizesCommentContent) con solo content (obligatorio, máximo 1000, mismo mensaje que al crear). authorize() devuelve true: la autorización por propietario la aplica la policy en el controlador, que corre antes de la validación (#[Authorize] actúa como middleware tras el route model binding).

**Type:** feature  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-004

**Acceptance Criteria:**

- [ ] Un content con caracteres de control o espacios alrededor se normaliza igual que en CommentStoreRequest y uno vacío tras normalizar falla con el mensaje 'El comentario es obligatorio'.
- [ ] Un content de 1001 caracteres falla y uno de 1000 pasa; campos ajenos como commentable_id o user_id no aparecen en validated().
- [ ] Un content que no es string (array o número) falla la validación sin lanzar excepciones.

**writeScope:**

- `app/Modules/Comment/Requests/CommentUpdateRequest.php`
- `app/Modules/Comment/Tests/Requests/CommentUpdateRequestTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Requests/CommentUpdateRequestTest.php`

### TASK-006: Ruta y método update() de edición (PATCH comments/{comment})

Crea el método update() de CommentController (el controlador único del módulo, que agrupa store, update y destroy en vez de un invocable por acción; CommentStoreController se fusionó en él) con #[Authorize('update', 'comment')] (patrón de TrustedDeviceController) y añade a routes/routes.php PATCH comments/{comment} con nombre comments.update, grupo auth+verified y throttle:10,1,comments.update. Guarda content y marca edited_at = now() solo si el contenido cambió; responde Inertia::flash success y back(), como comments.store. El comentario eliminado lógicamente da 404 por el route model binding.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001, TASK-003, TASK-005

**Acceptance Criteria:**

- [ ] El autor edita: content se actualiza normalizado, edited_at se llena y la respuesta es un redirect con flash success; mandar el mismo texto no cambia edited_at.
- [ ] Otro usuario recibe 403 sin modificar la fila, un invitado es redirigido al login y un comentario inexistente o eliminado lógicamente responde 404.
- [ ] Un content inválido (vacío o de 1001 caracteres) responde con error de validación en content y no toca el comentario.

**writeScope:**

- `app/Modules/Comment/Controllers/CommentController.php`
- `app/Modules/Comment/routes/routes.php` (PATCH comments.update)
- `app/Modules/Comment/Tests/Crud/CommentUpdateTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Crud/CommentUpdateTest.php`

### TASK-007: Ruta y método destroy() de borrado mixto (DELETE comments/{comment})

Añade el método destroy() a CommentController con #[Authorize('delete', 'comment')] y añade a routes/routes.php DELETE comments/{comment} (comments.destroy, auth+verified, throttle:10,1,comments.destroy). Dentro de DB::transaction: si el comentario tiene respuestas, borrado lógico (delete()) conservando sus likes; si no tiene, borra sus likes ($comment->likes()->delete(), la tabla no tiene FK) y lo elimina de forma física (forceDelete()). Si era una respuesta y su padre ya estaba eliminado lógicamente y no le quedan más respuestas, elimina también al padre y sus likes de forma física para no dejar tombstones vacíos. Responde Inertia::flash success y back().

**Type:** feature  
**Priority:** P1  
**Effort:** L  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-002, TASK-003, TASK-006

**Acceptance Criteria:**

- [ ] Un comentario sin respuestas se elimina físicamente junto con sus likes (Comment::withTrashed() y Like ya no lo encuentran); uno con respuestas queda con deleted_at, sus respuestas y mensajes intactos y sus likes conservados.
- [ ] Eliminar la última respuesta de un comentario ya eliminado lógicamente borra también al padre y sus likes; si quedan otras respuestas el padre permanece como tombstone.
- [ ] Otro usuario recibe 403, un invitado va al login, un comentario inexistente o ya eliminado responde 404 y nada se borra; si algo falla dentro de la transacción no queda un borrado a medias.

**writeScope:**

- `app/Modules/Comment/Controllers/CommentController.php`
- `app/Modules/Comment/routes/routes.php` (DELETE comments.destroy)
- `app/Modules/Comment/Tests/Crud/CommentDestroyTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Crud/CommentDestroyTest.php`

### TASK-008: Throttle de editar y eliminar y actualización del test [H8]

Añade a CommentThrottleTest casos para comments.update y comments.destroy (10 por minuto por usuario, 429 al exceder, presupuestos independientes entre sí y de comments.store y comments.like) y actualiza CommentSecurityAuditTest: el test '[H8] exposes no route to update or delete comments' pasa a afirmar exactamente las rutas esperadas (comments.destroy, comments.like, comments.store, comments.update) y se renombra para reflejarlo.

**Type:** test  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-007

**Acceptance Criteria:**

- [ ] La 11ª edición y el 11º borrado del minuto responden 429, y cada ruta tiene su propio presupuesto (agotar update no bloquea destroy ni store).
- [ ] El test [H8] falla si aparece una ruta comments.* inesperada y pasa con las cuatro actuales, con el nombre actualizado.
- [ ] Los tests existentes de throttle de comments.store siguen pasando sin cambios.

**writeScope:**

- `app/Modules/Comment/Tests/Security/CommentThrottleTest.php`
- `app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Security/CommentThrottleTest.php app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php`

### TASK-009: CommentResource: editedAt, isMine y carga útil de tombstone

CommentResource añade editedAt (ISO 8601 o null) e isMine (true solo si el usuario autenticado es el autor, sin Gate ni consultas; la autorización real sigue en CommentPolicy). Cada bloque del payload (author, repliedTo, repliesInfo, likesInfo, isMine) vive en un método privado. Para un comentario eliminado lógicamente (trashed) devuelve la forma de tombstone: id, createdAt, isDeleted true, repliesInfo, content null, user null, likesInfo en cero e isMine en false, sin exponer contenido ni autor. Para los demás isDeleted es false.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001, TASK-002, TASK-003

**Acceptance Criteria:**

- [ ] isMine es true para el autor y false para otro usuario o invitado; editedAt refleja edited_at (null si nunca se editó).
- [ ] Un comentario trashed serializa con content null, user null, isDeleted true y sin filtrar el texto original (json_encode no contiene el contenido ni el nombre del autor).
- [ ] Con los atributos precargados el Resource no ejecuta consultas adicionales, también para el tombstone.

**writeScope:**

- `app/Modules/Comment/Resources/CommentResource.php`
- `app/Modules/Comment/Tests/Resources/CommentResourceTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Resources/CommentResourceTest.php`

### TASK-010: CommentThreadProps lista los tombstones sin romper el número de consultas

En CommentThreadProps los comentarios raíz de un post se listan con withTrashed() (los eliminados lógicamente siempre tienen respuestas y se muestran como tombstone), y la búsqueda del padre de las respuestas también usa withTrashed() para que las respuestas de un tombstone sigan cargando en vez de dar 404. Las respuestas nunca son tombstones: se eliminan de forma física porque no tienen hijos.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-009, TASK-002

**Acceptance Criteria:**

- [ ] El listado de comentarios de un post incluye al tombstone (isDeleted true) en su posición, y el listado de respuestas de un comentario eliminado lógicamente responde 200 con sus respuestas.
- [ ] CommentQueryCountTest sigue exigiendo el mismo número de consultas con 1 o muchos comentarios, incluidos tombstones, ediciones y likes.
- [ ] Un post inexistente o un id inválido siguen respondiendo 404, y el sobre del paginador y el orden descendente por id no cambian.

**writeScope:**

- `app/Modules/Comment/Props/CommentThreadProps.php`
- `app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php`
- `app/Modules/Comment/Tests/Security/CommentQueryCountTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Props app/Modules/Comment/Tests/Security/CommentQueryCountTest.php`

### TASK-011: Publicar comentarios respeta los tombstones

CommentController::store() responde 404 al comentar sobre un comentario eliminado lógicamente (el findOrFail del destino ya excluye los trashed por SoftDeletes), pero al responder a una respuesta cuyo comentario raíz está eliminado lógicamente busca el padre con withTrashed() para que el hilo siga vivo y la respuesta se aplane al raíz.

**Type:** feature  
**Priority:** P1  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-001, TASK-002

**Acceptance Criteria:**

- [ ] Comentar sobre o responder directamente a un tombstone responde 404 y no crea nada.
- [ ] Responder a una respuesta de un hilo cuyo raíz es un tombstone crea la respuesta bajo ese raíz con la mención del autor respondido (aplanado intacto).
- [ ] Comentar sobre un post o un comentario normal funciona igual que antes (CommentStoreTest existente sin cambios de aserciones).

**writeScope:**

- `app/Modules/Comment/Controllers/CommentController.php`
- `app/Modules/Comment/Tests/Crud/CommentStoreTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Crud/CommentStoreTest.php`

### TASK-012: Sembrar comentarios editados y tombstones

CommentSeeder marca alrededor del 10% de los comentarios como editados (edited_at posterior a created_at) y elimina lógicamente algunos comentarios raíz que tienen respuestas, para poder ver 'editado' y el tombstone con datos locales. Sus pruebas validan rangos y propiedades, no valores concretos (regla de los seeders aleatorios).

**Type:** feature  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-002

**Acceptance Criteria:**

- [ ] Tras el seeder existen comentarios con edited_at posterior a created_at y todo comentario eliminado lógicamente tiene al menos una respuesta.
- [ ] Ningún comentario eliminado lógicamente es una respuesta y los límites de comentarios y respuestas por hilo no cambian.
- [ ] Los tests existentes del seeder (límites, un nivel, menciones) siguen pasando.

**writeScope:**

- `app/Modules/Comment/Database/Seeders/CommentSeeder.php`
- `app/Modules/Comment/Tests/Seeders/CommentSeederTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Seeders/CommentSeederTest.php`

### TASK-013: Tipos de frontend y Wayfinder para editar y eliminar

Regenera Wayfinder (php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder; está en .gitignore) y amplía types/comment.ts: Comment.editedAt, Comment.isMine, Comment.isDeleted, y content y user pasan a admitir null para el tombstone (con un tipo auxiliar si hace falta), de modo que tsc obligue a tratar el caso eliminado en los consumidores.

**Type:** feature  
**Priority:** P1  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-009

**Acceptance Criteria:**

- [ ] npm run types pasa y marca como error cualquier uso de comment.content o comment.user sin tratar el caso null (CommentItem se ajusta en TASK-017).
- [ ] Las acciones update y destroy de CommentController existen tras regenerar y se importan desde @/shared/wayfinder/actions/App/Modules/Comment/Controllers/.
- [ ] types/comment.ts no duplica la forma de likesInfo ni de repliesInfo ya existentes.

**writeScope:**

- `resources/js/modules/comment/types/comment.ts`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-014: Diálogo de edición de comentario

Crea components/dialog/CommentEditDialog.tsx: Dialog existente con un Textarea precargado con el contenido, contador/errores con InputError, envío PATCH con useForm y la acción update de CommentController (Wayfinder), y errores de servidor (429 y red) con onHttpException/onNetworkError devolviendo false como CommentForm. Al guardar cierra el diálogo y llama a onUpdated para refrescar la lista del hilo. Limite de 1000 caracteres como en el backend. Colores solo con tokens del tema.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-013, TASK-006

**Acceptance Criteria:**

- [ ] Guardar un texto válido llama a onUpdated una sola vez, cierra el diálogo y no cierra el diálogo padre de comentarios.
- [ ] Un content vacío o de más de 1000 caracteres muestra el error de validación del servidor en el campo y mantiene el diálogo abierto; un 429 o un fallo de red muestran un mensaje local sin abrir el modal de errores de Inertia.
- [ ] Reabrir el diálogo muestra el contenido actual del comentario (no el borrador anterior) y npm run types/lint/format pasan.

**writeScope:**

- `resources/js/modules/comment/components/dialog/CommentEditDialog.tsx`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-015: Diálogo de confirmación de borrado

Crea components/dialog/CommentDeleteDialog.tsx con el Dialog existente (sin añadir @radix-ui/react-alert-dialog): texto de confirmación, botones Cancelar y Eliminar (variant destructive, deshabilitado mientras procesa), envío DELETE con router.delete y la acción destroy de CommentController (Wayfinder), preserveScroll y manejo de 429/red como los demás formularios. Al terminar cierra y llama a onDeleted.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-013, TASK-007

**Acceptance Criteria:**

- [ ] Confirmar llama a onDeleted una sola vez y cierra el diálogo; Cancelar cierra sin enviar ninguna petición.
- [ ] Mientras la petición está en curso el botón Eliminar queda deshabilitado (no se envían dos borrados) y un 429 o fallo de red muestra el mensaje sin cerrar el diálogo ni abrir el modal de Inertia.
- [ ] El diálogo no contiene colores hard-coded ni <button> nativos y npm run types/lint/format pasan.

**writeScope:**

- `resources/js/modules/comment/components/dialog/CommentDeleteDialog.tsx`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-016: Menú de acciones del comentario (Editar / Eliminar)

Crea components/CommentActionsMenu.tsx con el DropdownMenu de shadcn (trigger Button ghost de icono con aria-label) que ofrece Editar y Eliminar cuando comment.isMine es true, abre CommentEditDialog y CommentDeleteDialog y reenvía onUpdated/onDeleted. No se renderiza nada si isMine es false.

**Type:** feature  
**Priority:** P1  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-014, TASK-015

**Acceptance Criteria:**

- [ ] Con isMine en false el componente no renderiza nada; con isMine en true ofrece Editar y Eliminar.
- [ ] Elegir Editar o Eliminar abre el diálogo correspondiente y el menú es operable con teclado (aria-label en el trigger, foco devuelto al cerrar).
- [ ] Usa solo componentes shadcn y tokens del tema; npm run types/lint/format pasan.

**writeScope:**

- `resources/js/modules/comment/components/CommentActionsMenu.tsx`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-017: Integrar editar, 'editado' y tombstone en CommentItem

CommentItem muestra el menú de acciones en el encabezado del comentario, el texto 'editado' (con la fecha completa en title) cuando editedAt existe y, para isDeleted, un tombstone 'Comentario eliminado' sin autor, contenido, me gusta ni 'Responder', conservando 'Mostrar N respuestas'. Tras editar o eliminar refresca el hilo con uploadedComments (y, en respuestas, también el de la lista raíz como ya hace al responder). CommentList y CommentRepliesList pasan los callbacks necesarios.

**Type:** feature  
**Priority:** P1  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Review:** claude  
**Depends on:** TASK-016, TASK-010, TASK-011

**Acceptance Criteria:**

- [ ] Editar un comentario propio refresca la lista sin cerrar el diálogo de comentarios y muestra 'editado'; eliminar uno sin respuestas lo quita de la lista y uno con respuestas lo deja como tombstone con 'Mostrar N respuestas' funcionando.
- [ ] En comentarios ajenos y en el tombstone no aparece el menú ni el botón 'Responder'; eliminar una respuesta actualiza el conteo de respuestas del comentario padre.
- [ ] Verificación visual en el navegador en claro y oscuro (prefers-color-scheme) con edición, borrado, tombstone y error 429, y npm run types/lint/format pasan.

**writeScope:**

- `resources/js/modules/comment/components/CommentItem.tsx`
- `resources/js/modules/comment/components/CommentList.tsx`
- `resources/js/modules/comment/components/CommentRepliesList.tsx`

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-018: Documentación: CLAUDE.md, docs de arquitectura y skill de convenciones

Actualiza CLAUDE.md (reglas del módulo Comment: SoftDeletes, borrado mixto, tombstone, policy, rutas comments.update/destroy con sus throttles, edited_at, isMine, frontend de edición y borrado), docs/architecture/backend.md y el skill .claude/skills/social-hub-conventions/SKILL.md (la regla de rutas del módulo cambia). Además corrige una omisión de SOC-51: docs/architecture/backend.md y el skill todavía dicen que Like vive en app/<Domain>/ (arquitectura vieja) cuando ya está en app/Modules/Like/.

**Type:** docs  
**Priority:** P3  
**Effort:** S  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** TASK-008, TASK-010, TASK-011, TASK-012, TASK-017

**Acceptance Criteria:**

- [ ] CLAUDE.md, docs/architecture/backend.md y el skill describen el borrado mixto, el tombstone, la policy y las dos rutas nuevas con sus throttles, y ninguno dice ya que 'no existe ruta para editar o eliminar'.
- [ ] Ninguno de los tres archivos afirma que Like sigue en la arquitectura vieja; todos lo listan junto a Post y Comment como módulo migrado.
- [ ] Se revisan también docs/architecture/frontend.md y AGENTS.md y solo se editan si mencionan los comentarios o su ruta antigua (grep sin referencias obsoletas).

**writeScope:**

- `CLAUDE.md`
- `docs/architecture/backend.md`
- `.claude/skills/social-hub-conventions/SKILL.md`

**validateCommand:** `grep -n 'Like.*arquitectura vieja\|Like.*old architecture\|Like. and .User. still live' CLAUDE.md docs/architecture/backend.md .claude/skills/social-hub-conventions/SKILL.md; test $? -eq 1`

## Failure Modes

- Tombstone huérfano: sin la limpieza al eliminar la última respuesta quedarían tombstones sin hijos para siempre; TASK-007 lo cubre.
- Likes huérfanos: likes no tiene FK ni cascada; el borrado físico los elimina explícitamente dentro de la transacción y el lógico los conserva (nunca se serializan para un tombstone).
- Borrado a medias: todo el borrado va en DB::transaction; un fallo no deja likes borrados con el comentario vivo.
- Fuga de contenido: un tombstone conserva el texto en la base; la prueba de TASK-009 exige que ni el contenido ni el autor aparezcan en el JSON.
- Cambio global de SoftDeletes: Admin, Post::comments() y User::comments() dejan de ver los tombstones; se verifica con la suite completa y queda documentado.
- Doble envío o carrera al borrar o editar: el segundo borrado responde 404 y la edición repetida es idempotente; el botón se deshabilita mientras procesa.
- N+1 en isMine y tombstones: isMine solo compara user_id con el usuario autenticado, sin Gate ni consultas; CommentQueryCountTest lo vigila con tombstones y ediciones.
- Autor eliminado o comentario ajeno: 403 por policy sin tocar la fila; invitado al login; ids inexistentes 404.
- 429 al editar o eliminar: los diálogos muestran un mensaje local y no abren el modal de errores de Inertia.
- Wayfinder desactualizado: sin regenerar tras las rutas, tsc falla en el import; TASK-013 lo regenera primero.

## Ship Cut

Tras TASK-008 el backend completo de editar y eliminar (migración, policy, rutas, throttles y [H8]) está probado, pero los listados todavía no muestran tombstones: no desplegar entre TASK-007 y TASK-010. Corte útil mínimo: TASK-001 a TASK-011 (backend consistente: tombstones en listados y publicación). La UI (TASK-013 a TASK-017) puede ir en un segundo PR y el seeder y la documentación (TASK-012, TASK-018) se pueden diferir, pero la documentación debe actualizarse antes de cerrar la issue.

## Test Coverage Map

- **TASK-001**: CommentModelTest: borrado lógico, edited_at y exclusión de consultas por defecto.
- **TASK-002**: CommentFactoryTest: edited_at/deleted_at por defecto, estados edited() y trashed().
- **TASK-003**: CommentPolicyTest: autor, otro usuario, comentario eliminado.
- **TASK-004**: CommentStoreRequestTest y CommentValidationHardeningTest sin cambios (refactor).
- **TASK-005**: CommentUpdateRequestTest: normalización, límite de 1000, tipos incorrectos.
- **TASK-006**: CommentUpdateTest: autor, edited_at, 403, login, 404, validación.
- **TASK-007**: CommentDestroyTest: borrado mixto, likes, limpieza del tombstone, 403/404, transacción.
- **TASK-008**: CommentThrottleTest (update/destroy) y CommentSecurityAuditTest [H8].
- **TASK-009**: CommentResourceTest: editedAt, isMine, tombstone sin fuga y sin consultas.
- **TASK-010**: CommentThreadPropsTest y CommentQueryCountTest: tombstones listados y consultas constantes.
- **TASK-011**: CommentStoreTest: 404 sobre tombstone y aplanado con raíz eliminado.
- **TASK-012**: CommentSeederTest: editados, tombstones con respuestas y límites previos.
- **TASK-013**: tsc (los consumidores deben tratar content/user null).
- **TASK-014**: tsc, ESLint, Prettier; comportamiento verificado en TASK-017 (sin infraestructura de tests de frontend).
- **TASK-015**: tsc, ESLint, Prettier; comportamiento verificado en TASK-017.
- **TASK-016**: tsc, ESLint, Prettier; comportamiento verificado en TASK-017.
- **TASK-017**: tsc, ESLint, Prettier y verificación visual en el navegador (claro/oscuro, edición, borrado, tombstone, 429).
- **TASK-018**: grep de referencias obsoletas.

## Execution Summary

- Tareas: 18
- Capas (paralelizables dentro de cada una):
  - Capa 0: TASK-001, TASK-004
  - Capa 1: TASK-002, TASK-003, TASK-005
  - Capa 2: TASK-006, TASK-009, TASK-011, TASK-012
  - Capa 3: TASK-007, TASK-010, TASK-013
  - Capa 4: TASK-008, TASK-014, TASK-015
  - Capa 5: TASK-016
  - Capa 6: TASK-017
  - Capa 7: TASK-018
- Ruta crítica: TASK-001 → TASK-002 → TASK-009 → TASK-013 → TASK-014 → TASK-016 → TASK-017 → TASK-018
- Gates finales por tarea que toca PHP: `vendor/bin/pint --dirty --format agent`, `composer rector-clear-cache` → `composer rector-dry` → `composer rector`, `composer phpstan` (con --memory-limit=2G); en frontend: `npm run lint:check`, `npm run format:check`, `npm run types`, `npm run build`.
- Al terminar: correr la suite completa con `php -d memory_limit=2G vendor/bin/pest` (incluye Admin, que ve los comentarios a través de SoftDeletes) y pedir al usuario `php artisan test --compact`.

## Task Dependencies

- TASK-001 ← —
- TASK-002 ← TASK-001
- TASK-003 ← TASK-001
- TASK-004 ← —
- TASK-005 ← TASK-004
- TASK-006 ← TASK-001, TASK-003, TASK-005
- TASK-007 ← TASK-002, TASK-003, TASK-006
- TASK-008 ← TASK-007
- TASK-009 ← TASK-001, TASK-002, TASK-003
- TASK-010 ← TASK-009, TASK-002
- TASK-011 ← TASK-001, TASK-002
- TASK-012 ← TASK-002
- TASK-013 ← TASK-009
- TASK-014 ← TASK-013, TASK-006
- TASK-015 ← TASK-013, TASK-007
- TASK-016 ← TASK-014, TASK-015
- TASK-017 ← TASK-016, TASK-010, TASK-011
- TASK-018 ← TASK-008, TASK-010, TASK-011, TASK-012, TASK-017
