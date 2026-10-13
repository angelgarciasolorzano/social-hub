# Plan: SOC-41: Responder a respuestas con mención "Respondiendo a @nombre" manteniendo hilos de un solo nivel

## Overview

Hoy responder a una respuesta se rechaza con un error de validación en commentable_id y la UI sigue ofreciendo el botón. Este plan cambia la regla: el cliente envía el comentario al que pulsa "Responder" (commentable_type/commentable_id, sin campos nuevos); CommentStoreController aplana al padre del hilo y guarda en la nueva columna nullable replied_to_user_id al autor de la respuesta respondida. CommentResource expone repliedTo {id, name}, CommentThreadProps precarga la relación para conservar el número constante de consultas, y el frontend oculta "Mostrar N respuestas" dentro de respuestas, titula el diálogo "Responder a {nombre}", muestra "Respondiendo a @nombre" y enseña los errores de commentable_id y del servidor (incluido 429).

**Issue:** [SOC-41](https://linear.app/social-hub-ang/issue/SOC-41/feature-reply-to-replies-with-a-replying-to-mention-while-keeping)  
**Modo:** HOLD · **Revisión por defecto:** claude  
**Estilo de ejecución:** Paso a paso: una tarea y pausa para revisión (regla .ai/rules/linear.md).

## Scope Challenge

Modo HOLD con decisiones del usuario: (1) columna replied_to_user_id (FK a users, nullOnDelete) en vez de replied_to_comment_id, porque la mención solo necesita el nombre y sobrevive si se borra el comentario respondido; (2) sin campo extra en CommentStoreRequest: el backend deriva usuario y padre del comentario destino, por lo que mencionar un comentario de otro hilo es imposible por construcción (el criterio de aceptación pasa a verificarse con la regla de aplanado). La descripción de la issue quedó desactualizada tras SOC-46: el controlador es CommentStoreController (invocable), el frontend vive en modules/comment/ (singular) y los listados salen de CommentThreadProps con cursorPaginate, no de una ruta comments.index. No hay cambios de rutas, así que Wayfinder no se regenera.

## Prerequisites

- SOC-46 está mergeado en main: el módulo ya usa CommentStoreController, CommentThreadProps y modules/comment/.
- Se leyeron .ai/rules/{app,backend,frontend,linear}.md: sin variables de una letra, docblocks de método con máximo 3 líneas de prosa, factories completas con los campos persistidos, un test = una conducta.
- La migración existente create_comments_table no se edita: la columna llega en una migración nueva dentro de app/Modules/Comment/Database/Migrations/ (CommentServiceProvider ya la registra con loadMigrationsFrom).
- Working tree sin cambios ajenos a SOC-41 (rama de GitButler aparte).

## Non-Goals

- Notificaciones al usuario mencionado.
- Anidación de más de un nivel.
- Editar o eliminar comentarios.
- Me gusta en comentarios.
- Guardar replied_to_comment_id o enlazar la mención al comentario original.
- Cambiar rutas, el payload de POST /comments o regenerar Wayfinder.

## Contracts

- Persistencia: comments.replied_to_user_id, unsignedBigInteger nullable, FK a users con nullOnDelete. Modelo Comment: replied_to_user_id en #[Fillable] y relación repliedToUser(): BelongsTo<User>.
- Payload de POST /comments sin cambios (content, commentable_type, commentable_id). Aplanado en CommentStoreController: si el destino es un Comment cuyo commentable_type es COMMENT, el padre es Comment::findOrFail($destino->commentable_id) y replied_to_user_id = $destino->user_id; si el destino es un Post o un Comment raíz, el padre es el destino y replied_to_user_id = null. Profundidad máxima 1 siempre. Responder a una respuesta propia guarda la mención igualmente (decisión por defecto, ver riesgos).
- Resource: CommentResource añade repliedTo: null | {id:int, name:string} usando whenLoaded('repliedToUser') para no provocar lazy loading; nunca expone email ni otros campos de User.
- Consultas: CommentThreadProps carga ->with(['user', 'repliedToUser:id,name']) en la misma consulta paginada; CommentQueryCountTest sigue exigiendo el mismo número de consultas con 1 o N comentarios y menciones.
- Frontend: tipo Comment.repliedTo?: {id:number; name:string} | null; CommentItem con isReply no renderiza "Mostrar N respuestas" ni CommentRepliesList; el diálogo de una respuesta se titula "Responder a {nombre}" y envía commentable_id = id de esa respuesta; al publicar refresca la lista del padre mediante uploadedComments heredado de CommentRepliesList.
- Errores en CommentForm: errors.commentable_id se muestra con InputError; un error HTTP del servidor (p. ej. 429) se muestra en un mensaje local usando onHttpException que devuelve false para no abrir el modal de Inertia, igual que useCommentPaginated.
- Protecciones de SOC-40 intactas: throttle:6,1,comments.store, normalización de content en prepareForValidation(), profundidad de un nivel.

## Existing Code Leverage

- app/Modules/Comment/Controllers/CommentStoreController.php: ya resuelve el destino con CommentType::modelClass() y lanza ValidationException al responder a una respuesta; esa rama se reemplaza por el aplanado.
- app/Modules/Comment/Props/CommentThreadProps.php y Tests/Security/CommentQueryCountTest.php: patrón de precarga y de conteo de consultas.
- tests/Pest.php: helpers createComment(), createCommentOn() y createCommentsOn() para crear hilos en los tests.
- resources/js/modules/comment/hooks/useCommentPaginated.ts: patrón onHttpException => false con loadFailure.
- resources/js/modules/comment/components/CommentLoadStates.tsx: CommentLoadError y estilos de error reutilizables.

## Tasks

### TASK-001: Migración, relación y fillable de replied_to_user_id

Crea la migración nueva que añade replied_to_user_id a comments (nullable, FK a users, nullOnDelete) en app/Modules/Comment/Database/Migrations/, añade el atributo a #[Fillable] de Comment y la relación repliedToUser() BelongsTo<User>, y cubre la relación y el nullOnDelete en CommentModelTest. CommentServiceProvider ya registra la carpeta con loadMigrationsFrom, por lo que no requiere cambios.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P0  
**Review:** claude
**Write scope:**
- app/Modules/Comment/Database/Migrations/<timestamp>_add_replied_to_user_id_to_comments_table.php (migración nueva generada con php artisan make:migration; sustituir <timestamp> por el real)
- app/Modules/Comment/Models/Comment.php (Fillable + relación repliedToUser + docblock de propiedad)
- app/Modules/Comment/Tests/Models/CommentModelTest.php (casos de relación y nullOnDelete)
**Validate:** `php artisan test --compact app/Modules/Comment/Tests/Models/CommentModelTest.php`
**Acceptance Criteria:**
- [ ] Comment::repliedToUser() devuelve el User mencionado y devuelve null cuando replied_to_user_id es null.
- [ ] Al eliminar el User mencionado, replied_to_user_id queda en null y el comentario sigue existiendo (nullOnDelete).
- [ ] La migración original create_comments_table no se modifica y migrate:fresh en testing aplica ambas sin error.

### TASK-002: Factory con replied_to_user_id y estado repliedTo

CommentFactory define replied_to_user_id => null por defecto (regla de factories completas) y expone un estado repliedTo(User) para crear respuestas con mención. CommentFactoryTest cubre ambos.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-001
**Write scope:**
- app/Modules/Comment/Database/Factories/CommentFactory.php (campo por defecto + estado repliedTo)
- app/Modules/Comment/Tests/Database/CommentFactoryTest.php
**Validate:** `php artisan test --compact app/Modules/Comment/Tests/Database/CommentFactoryTest.php`
**Acceptance Criteria:**
- [ ] Un Comment creado con la factory por defecto tiene replied_to_user_id null.
- [ ] El estado repliedTo($user) persiste el id de ese usuario en replied_to_user_id.
- [ ] Los casos existentes de CommentFactoryTest siguen en verde.

### TASK-003: Seeder: respuestas con mención

CommentSeeder hace que parte de las respuestas del hilo mencione a otro autor del mismo hilo para poblar datos de desarrollo con la nueva columna, sin romper la profundidad de un nivel.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-002
**Write scope:**
- app/Modules/Comment/Database/Seeders/CommentSeeder.php
- app/Modules/Comment/Tests/Database/CommentSeederTest.php
**Validate:** `php artisan test --compact app/Modules/Comment/Tests/Database/CommentSeederTest.php`
**Acceptance Criteria:**
- [ ] Tras sembrar, al menos una respuesta tiene replied_to_user_id no nulo y ese usuario existe.
- [ ] Ninguna respuesta sembrada tiene como padre otra respuesta (todas siguen siendo de un solo nivel).
- [ ] Los casos existentes de CommentSeederTest siguen en verde.

### TASK-004: Controlador: aplanar respuestas a respuestas y guardar la mención

Sustituye la ValidationException de CommentStoreController por el aplanado definido en Contracts y pasa replied_to_user_id a create(). Actualiza CommentStoreTest: el caso 'rejects a reply to a reply' pasa a afirmar que se crea una hija del comentario padre con la mención del autor respondido, y se añaden casos de comentario raíz sin mención, post sin mención y destino inexistente con 404.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-001
**Write scope:**
- app/Modules/Comment/Controllers/CommentStoreController.php
- app/Modules/Comment/Tests/Crud/CommentStoreTest.php
**Validate:** `php artisan test --compact app/Modules/Comment/Tests/Crud/CommentStoreTest.php`
**Acceptance Criteria:**
- [ ] POST /comments con commentable_type=comment y commentable_id de una respuesta crea un comentario cuyo commentable_id es el padre, con replied_to_user_id igual al user_id de la respuesta respondida y sin crear un nivel extra.
- [ ] Responder a un comentario raíz o a un post guarda replied_to_user_id null; un commentable_id inexistente responde 404 y no crea filas.
- [ ] El éxito sigue devolviendo el flash {type: success, message} y la redirección back().

### TASK-005: Resource: exponer repliedTo {id, name} sin datos privados

CommentResource añade repliedTo usando whenLoaded('repliedToUser') y devuelve solo id y name (o null). CommentResourceTest verifica la forma, que no se filtra email y que sin precarga la clave se omite sin consultas extra.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-001
**Write scope:**
- app/Modules/Comment/Resources/CommentResource.php
- app/Modules/Comment/Tests/Resources/CommentResourceTest.php
**Validate:** `php artisan test --compact app/Modules/Comment/Tests/Resources/CommentResourceTest.php`
**Acceptance Criteria:**
- [ ] Con la relación cargada y un usuario mencionado, repliedTo es exactamente {id, name}; sin mención es null.
- [ ] El JSON serializado no contiene email, password ni ningún otro campo del usuario mencionado.
- [ ] Serializar sin cargar repliedToUser no ejecuta consultas adicionales (la clave se omite).

### TASK-006: Listados: precargar la mención y mantener consultas constantes

CommentThreadProps carga repliedToUser:id,name junto a user en la consulta paginada de comentarios y respuestas. CommentQueryCountTest pasa a incluir respuestas con mención y exige el mismo número de consultas con pocos y muchos comentarios; CommentThreadPropsTest fija que cada elemento de replies incluye repliedTo.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-002, TASK-005
**Write scope:**
- app/Modules/Comment/Props/CommentThreadProps.php
- app/Modules/Comment/Tests/Security/CommentQueryCountTest.php
- app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php
**Validate:** `php artisan test --compact app/Modules/Comment/Tests/Security/CommentQueryCountTest.php app/Modules/Comment/Tests/Props/CommentThreadPropsTest.php`
**Acceptance Criteria:**
- [ ] El listado de respuestas con menciones usa el mismo número de consultas con 2 que con 12 respuestas.
- [ ] Cada elemento de props.replies.data incluye repliedTo con {id, name} cuando hay mención y null cuando no; el sobre del paginador (data, path, per_page, next_cursor, ...) no cambia.
- [ ] Una respuesta cuyo usuario mencionado fue eliminado se lista con repliedTo null sin error.

### TASK-007: Regresión de seguridad: un nivel, normalización y throttle con mención

Añade a las pruebas de Security casos que confirman que las protecciones de SOC-40 siguen vigentes con la nueva regla: encadenar respuestas nunca supera un nivel, el contenido de una respuesta a una respuesta se normaliza igual y el throttle comments.store cuenta también estas peticiones.

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Review:** claude
**Depends on:** TASK-004
**Write scope:**
- app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php
- app/Modules/Comment/Tests/Security/CommentThrottleTest.php
**Validate:** `php artisan test --compact app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php app/Modules/Comment/Tests/Security/CommentThrottleTest.php`
**Acceptance Criteria:**
- [ ] Responder tres veces en cadena a la última respuesta deja todas las hijas colgando del mismo padre; ningún comentario tiene como padre una respuesta.
- [ ] El contenido de una respuesta a una respuesta se guarda sin caracteres de control y recortado.
- [ ] La séptima petición de un minuto a comments.store, sea a un post, a un comentario o a una respuesta, responde 429.

### TASK-008: Frontend: tipo repliedTo y errores visibles en CommentForm

types/comment.ts añade repliedTo?: {id, name} | null a Comment. CommentForm muestra errors.commentable_id con InputError y un mensaje local para errores HTTP del servidor (429 incluido) con onHttpException devolviendo false para no abrir el modal de Inertia; limpia el mensaje al reintentar. Verificar la firma de onHttpException con search-docs/Inertia v3 antes de implementar.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Write scope:**
- resources/js/modules/comment/types/comment.ts
- resources/js/modules/comment/components/forms/CommentForm.tsx
**Validate:** `npm run types && npx eslint resources/js/modules/comment/types/comment.ts resources/js/modules/comment/components/forms/CommentForm.tsx && npx prettier --check resources/js/modules/comment/types/comment.ts resources/js/modules/comment/components/forms/CommentForm.tsx`
**Acceptance Criteria:**
- [ ] Un error de validación en commentable_id se renderiza bajo el textarea con InputError.
- [ ] Una respuesta 429 del servidor muestra un mensaje legible en el formulario, no abre el modal de error de Inertia y el diálogo permanece abierto.
- [ ] npm run types y eslint no reportan errores en los dos archivos y el mensaje se limpia al reenviar.

### TASK-009: Frontend: CommentItem para respuestas con mención

En CommentItem, cuando isReply: oculta el botón "Mostrar N respuestas" y no renderiza CommentRepliesList; el diálogo se titula "Responder a {nombre}" (comentario raíz mantiene su título actual) y envía commentable_id de esa respuesta; muestra "Respondiendo a @nombre" bajo el encabezado cuando repliedTo no es null; tras publicar llama al uploadedComments heredado para refrescar la lista del padre.

**Type:** feature  
**Effort:** M  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Review:** claude
**Depends on:** TASK-008
**Write scope:**
- resources/js/modules/comment/components/CommentItem.tsx
**Validate:** `npm run types && npx eslint resources/js/modules/comment/components/CommentItem.tsx && npx prettier --check resources/js/modules/comment/components/CommentItem.tsx`
**Acceptance Criteria:**
- [ ] Una respuesta con repliedTo muestra "Respondiendo a @nombre"; una respuesta sin repliedTo o un comentario raíz no muestran la cabecera.
- [ ] Una respuesta no muestra "Mostrar N respuestas" ni monta CommentRepliesList anidado, aunque showReplies coincida con su id.
- [ ] El botón "Responder" de una respuesta abre el diálogo titulado "Responder a {nombre}" y, al publicar, la lista de respuestas del padre se refresca.

### TASK-010: Documentación: hilos de un nivel con mención

Reemplaza la regla "responder a una respuesta se rechaza" por la de hilos de un nivel con mención en CLAUDE.md (viñeta de comportamiento de Comment y modelo Comment), documenta replied_to_user_id y repliedTo en docs/architecture/backend.md y actualiza la referencia del skill social-hub-conventions si menciona el comportamiento anterior.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Review:** claude
**Depends on:** TASK-004, TASK-006, TASK-009
**Write scope:**
- CLAUDE.md
- docs/architecture/backend.md
- .claude/skills/social-hub-conventions/SKILL.md
**Validate:** `! grep -rn "replying to a reply is rejected\|No se puede responder a una respuesta" CLAUDE.md docs .claude/skills/social-hub-conventions/SKILL.md`
**Acceptance Criteria:**
- [ ] CLAUDE.md ya no afirma que responder a una respuesta se rechaza y describe el aplanado y replied_to_user_id.
- [ ] docs/architecture/backend.md describe el contrato de la mención (columna, relación, repliedTo, precarga).
- [ ] grep de "No se puede responder a una respuesta" y "replying to a reply is rejected" no devuelve coincidencias en CLAUDE.md, docs y el skill.

### TASK-011: Compuertas finales y _ide_helper_models.php

Regenera _ide_helper_models.php con composer ide-helper (nueva propiedad y relación) y ejecuta todas las compuertas: Pint, PHPStan, Rector dry-run y rector, suite Pest del módulo Comment y las compuertas de frontend. Después se pide al usuario la suite completa, abrir el PR con el template y dejar el comentario resumen en SOC-41.

**Type:** chore  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P3  
**Review:** claude
**Depends on:** TASK-003, TASK-006, TASK-007, TASK-010
**Write scope:**
- _ide_helper_models.php (archivo generado por composer ide-helper)
**Validate:** `composer ide-helper && vendor/bin/pint --dirty --format agent && composer phpstan && composer rector-dry && php artisan test --compact app/Modules/Comment/Tests && npm run types && npm run lint:check && npm run format:check && npm run build`
**Acceptance Criteria:**
- [ ] vendor/bin/pint --dirty --format agent, composer phpstan, composer rector-dry (y composer rector) terminan sin hallazgos.
- [ ] php artisan test --compact app/Modules/Comment/Tests termina en verde.
- [ ] npm run types, npm run lint:check, npm run format:check y npm run build terminan sin errores.

## Failure Modes

- Destino inexistente: findOrFail responde 404 (sin cambio).
- Padre del destino eliminado fuera de banda: Comment::findOrFail del padre responde 404 en vez de 500.
- Usuario mencionado eliminado: nullOnDelete deja repliedTo en null y la UI no pinta la cabecera.
- Respuestas antiguas sin mención: columna nullable, repliedTo null, sin backfill.
- Lazy loading de la mención: sin precarga el Resource omitiría repliedTo en vez de disparar una consulta por fila.
- 429 al publicar: el formulario muestra el mensaje en vez de ignorarlo.

## Ship Cut

Si la ejecución se detiene tras TASK-007 el backend ya acepta responder a respuestas con mención y está cubierto por tests, pero la UI sigue mostrando solo lo antiguo; no queda nada roto. Un corte útil mínimo es TASK-001 a TASK-009.

## Test Coverage Map

- TASK-001: CommentModelTest: relación repliedToUser y nullOnDelete.
- TASK-002: CommentFactoryTest: valor por defecto y estado repliedTo.
- TASK-003: CommentSeederTest: menciones sembradas y un solo nivel.
- TASK-004: CommentStoreTest: aplanado, mención, raíz/post sin mención, 404.
- TASK-005: CommentResourceTest: forma de repliedTo, sin email, sin consultas extra.
- TASK-006: CommentQueryCountTest y CommentThreadPropsTest: consultas constantes y sobre del paginador.
- TASK-007: CommentSecurityAuditTest y CommentThrottleTest: un nivel, normalización, throttle.
- TASK-008: Sin runner de JS en el proyecto: npm run types, ESLint, Prettier; comprobación manual del 429.
- TASK-009: Sin runner de JS en el proyecto: npm run types, ESLint, Prettier; comprobación manual en navegador.
- TASK-010: grep de frases obsoletas.
- TASK-011: Compuertas completas de backend y frontend.

## Execution Summary

- Tareas: 11
- Capas: 5
- Capa 1: TASK-001, TASK-008
- Capa 2: TASK-002, TASK-004, TASK-005, TASK-009
- Capa 3: TASK-003, TASK-006, TASK-007
- Capa 4: TASK-010
- Capa 5: TASK-011

## Task Dependencies

- TASK-001 ← —
- TASK-002 ← TASK-001
- TASK-003 ← TASK-002
- TASK-004 ← TASK-001
- TASK-005 ← TASK-001
- TASK-006 ← TASK-002, TASK-005
- TASK-007 ← TASK-004
- TASK-008 ← —
- TASK-009 ← TASK-008
- TASK-010 ← TASK-004, TASK-006, TASK-009
- TASK-011 ← TASK-003, TASK-006, TASK-007, TASK-010
