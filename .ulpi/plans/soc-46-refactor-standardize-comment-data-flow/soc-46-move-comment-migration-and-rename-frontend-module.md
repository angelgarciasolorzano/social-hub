# Plan: SOC-46 (refactor 2): Mover la migracion de comments a su modulo y renombrar el modulo Comment del frontend

## Overview

Dos limpiezas de organizacion. (1) La migracion de la tabla comments vive en database/migrations, fuera del modulo; se mueve a app/Modules/Comment/Database/Migrations/ con el mismo nombre de archivo y el mismo esquema (morphs('commentable'), que ya sigue la convencion de Laravel y es coherente con likes/likeable) y se registra con loadMigrationsFrom en CommentServiceProvider. (2) El modulo del frontend deja de llamarse comments y pasa a comment (como el del backend), la carpeta modal pasa a dialog, RepliesList pasa a CommentRepliesList y el enum commentableType.ts pasa a commentType.ts (CommentType/CommentTypeValues, igual que el enum del backend). No cambia el esquema, el modelo, el payload ni el comportamiento. El modulo del frontend se crea completo en modules/comment y el viejo se borra al final (crear-luego-borrar, sin renombre directo de carpeta).

**Issue:** [SOC-46](https://linear.app/social-hub-ang/issue/SOC-46/refactor-standardize-comment-data-flow-across-backend-and-frontend) · Segundo plan dentro de SOC-46 (decision del usuario); mismo PR.  
**Modo:** HOLD · **Revision por defecto:** claude  
**Estilo de ejecucion:** Paso a paso: una tarea y pausa para revision (regla .ai/rules/linear.md). El backend es una sola tarea y no deja la suite en rojo; el frontend se crea completo en modules/comment y el viejo se borra al final.

## Scope Challenge

Modo HOLD. La version anterior de este plan renombraba commentable a model (columnas, atributos, payload y 16 archivos de prueba, con migrate:fresh --seed en la BD local). Se DESCARTO tras revisar la convencion: Laravel documenta el patron {adjetivo}able para relacion y columnas (commentable_type/commentable_id con commentable()), el proyecto ya lo sigue en likes (likeable) y model_type/model_id es la convencion de los paquetes de Spatie, cuyas tablas son genericas (Media Library, Permission). Decision del usuario (opcion A): conservar commentable_* y limitar el refactor al movimiento de la migracion y a los nombres del frontend. El componente se llama CommentRepliesList y todo va dentro de SOC-46 con el mismo PR. Hallazgos: el unico consumidor del modulo del frontend es PostCard; ya existe una asercion (H3 de CommentSecurityAuditTest) que escanea resources/js/modules/comments; no hay ningun otro consumidor de la migracion.

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

## Failure Modes

- Si el nombre de archivo de la migracion cambia, un entorno ya migrado intentaria recrear la tabla y fallaria (tabla existente): se conserva el nombre exacto.
- Si la migracion queda en ambas rutas, Laravel falla por clase anonima duplicada o por nombre repetido: TASK-001 borra la global en la misma tarea.
- Si CommentServiceProvider no registra la ruta, php artisan migrate:fresh no crearia comments y toda la suite de Comment fallaria: la validacion de TASK-001 ejecuta migrate:fresh y una prueba del modelo.
- El escaneo de dangerouslySetInnerHTML (H3) podria volverse vacio si apunta a una carpeta que no existe: TASK-006 exige que apunte a modules/comment y siga activo.
- Entre TASK-002 y TASK-005 conviven modules/comments y modules/comment: nada del modulo nuevo debe importar del viejo y PostCard cambia de import solo en TASK-005.

## Ship Cut

El backend (TASK-001) es independiente del frontend (TASK-002 a TASK-006): cualquiera de los dos puede entregarse solo. En el frontend el primer corte seguro es TASK-005 (la app ya usa el modulo nuevo); TASK-006 (borrado del viejo), TASK-007 (docs) y TASK-008 (gates y verificacion) pueden diferirse sin romper comportamiento, aunque dejarian ambos modulos en el repo.

## Test Coverage Map

- Migracion movida: validacion de TASK-001 (migrate:fresh en pruebas crea commentable_type/commentable_id, la global ya no existe) y CommentModelTest; la suite de Comment completa corre en TASK-008.
- Frontend: npm run types, lint:check, format:check y build en cada tarea FE; H3 de CommentSecurityAuditTest sobre modules/comment (TASK-006); verificacion manual en navegador (TASK-008).

## Execution Summary

- Tareas: 8
- Ruta critica: TASK-002 -> TASK-003 -> TASK-004 -> TASK-005 -> TASK-006 -> TASK-007 -> TASK-008
- Capas de ejecucion:
  1. TASK-001, TASK-002
  2. TASK-003
  3. TASK-004
  4. TASK-005
  5. TASK-006
  6. TASK-007
  7. TASK-008

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
