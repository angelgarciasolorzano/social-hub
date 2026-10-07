# Plan: SOC-40: Mover el módulo Comment a app/Modules, agregar tests y corregir hallazgos de seguridad

## Overview

SOC-38 migró Post a app/Modules/Post y fijó el patrón. SOC-40 repite el patrón con Comment (11 archivos hoy en app/Comment/): crear el módulo nuevo en paralelo, cambiar consumidores, flipear el registro, borrar lo viejo, regenerar Wayfinder y actualizar los 2 imports del frontend. Comment no tiene tests, así que se crea la suite del módulo, se migra CommentStoreRequest a FluentValidation y se audita el módulo en busca de vulnerabilidades: primero un paso de auditoría con tests que confirman o descartan cada hipótesis, y después correcciones separadas que se aprueban una a una.

**Issue:** [SOC-40](https://linear.app/social-hub-ang/issue/SOC-40/refactor-move-comment-module-into-appmodules-add-module-tests-and-fix) — Refactor: Move Comment module into app/Modules, add module tests and fix security findings
**Modo:** HOLD · **Estilo:** Paso a paso: se implementa una tarea y se pausa para revisión (regla .ai/rules/linear.md). La auditoría de seguridad (TASK-018) se detiene para que el usuario apruebe cada corrección una a una. · **Review por defecto:** claude,codex,user

## Scope Challenge

Modo HOLD: alcance de la issue (mover Comment backend+frontend/Wayfinder, tests, auditoría de seguridad con correcciones y docs/skill/reglas) sin ampliar ni recortar. Ya existe el patrón de SOC-38 (plan en .ulpi/plans/soc-38-*), la nomenclatura Database/{Factories,Seeders} y el tooling (Wayfinder con --path, ide-helper + Pint, FluentRulesTester). Decisión del usuario sobre seguridad: auditar primero, corregir después con aprobación individual. Hallazgos de la exploración: el módulo tiene 2 rutas (comments.index/comments.store) bajo auth+verified, solo 2 consumidores frontend (CommentForm.tsx, usePaginatedComments.ts), ningún uso de dangerouslySetInnerHTML en resources/js/modules/comments, y CommentFactory deja user_id/commentable_* en null.

## Prerequisites

- SOC-38 está mergeada en main (Post ya vive en app/Modules/Post) y la rama de SOC-40 parte de main actualizado.
- Se leyeron .ai/rules/{app,backend,linear}.md: sin variables de una letra, docblocks con máximo 3 líneas de prosa, factories completas con los campos persistidos, FluentRule en FormRequests, Pint/PHPStan/Rector como gates.
- composer.json autocarga App\ => app/, por lo que App\Modules\Comment no requiere cambios de autoload; no se modifican dependencias.
- Estrategia del proyecto: crear lo nuevo y luego borrar lo viejo, no un rename directo.
- Working tree sin cambios ajenos a SOC-40 (hoy hay modificaciones sin commitear en TrustedDevice*Dialog.tsx que no deben mezclarse).

## Non-Goals

- Mover Auth, Friendship, Home, Like, MediaLibrary o User a app/Modules.
- Cambiar nombres de rutas (comments.index, comments.store) o la UI de comentarios; el frontend solo cambia imports/Wayfinder.
- Agregar funcionalidad nueva (editar/borrar comentarios, notificaciones) salvo que un hallazgo de seguridad confirmado lo exija.
- Tests de módulos distintos de Comment.

## Contracts

- Namespace final: App\Modules\Comment\{Controllers,Database\Factories,Database\Seeders,Enums,Models,Providers,Requests,Resources}; rutas comments.index y comments.store idénticas (middleware auth+verified); morph key 'comment' idéntica; CommentType sigue mapeando 'post'→Post y 'comment'→Comment.
- Regla de arquitectura heredada de SOC-38: dentro de app/Modules/ = módulos de dominio; fuera = archivos globales. Tras SOC-40 los módulos migrados son Post y Comment.
- Propiedad del registro: TASK-006 (bootstrap/providers.php, morph map, DatabaseSeeder). TASK-014 posee el registro de la carpeta de tests y el helper createComment(). TASK-011 posee la regeneración de Wayfinder (siempre con --path=resources/js/shared/wayfinder). TASK-013 posee ide-helper.
- Cambios de comportamiento permitidos únicamente: (a) FluentValidation en CommentStoreRequest con las mismas reglas y mensajes, (b) completar CommentFactory, (c) las correcciones de seguridad TASK-019..022 que el usuario apruebe tras TASK-018.
- Seguridad: TASK-018 solo escribe tests y reporta; no modifica código de producción. Cada corrección quita el ->todo() de su hallazgo y agrega un test de regresión.

## Existing Code Leverage

- app/Comment/ (11 archivos) es el origen exacto de la copia.
- Patrón completo de SOC-38: .ulpi/plans/soc-38-refactor-move-post-module-into-app-modules/ y app/Modules/Post/ como módulo de referencia (estructura, tests, PostRequest con FluentRule).
- Helpers de tests en tests/Pest.php (createUser, createPost, createTrustedDevice) como modelo para createComment().
- password.update con throttle:6,1 como patrón de rate limiting existente en app/Auth/routes/password.php.
- FluentRulesTester (sandermuller/laravel-fluent-validation) para tests del request.

## Tasks

### TASK-001: Crear Model, Enum y Factory de Comment en app/Modules/Comment

Crear copias de Comment, CommentType y CommentFactory bajo App\Modules\Comment\{Models,Enums,Database\Factories} sin tocar los originales (crear-nuevo-luego-borrar-viejo). Comment conserva #[UseFactory], MORPH_NAME='comment', MORPH_COLUMN='commentable', el cast de commentable_type a CommentType y las relaciones user/commentable/comments/likes. Única desviación de comportamiento: la factory se completa según .ai/rules/backend.md (hoy user_id/commentable_id/commentable_type son null): user_id => User::factory() y, por defecto, un comentario sobre un Post (commentable_id => Post::factory(), commentable_type => Post::MORPH_NAME); los consumidores existentes (CommentSeeder) siempre pasan estos tres campos explícitos.

**Type:** refactor
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user

**Acceptance Criteria:**

- Los 3 archivos declaran namespace App\Modules\Comment\... y apuntan al Post de App\Modules\Post; Comment usa #[UseFactory] con la factory nueva y CommentType::modelClass() sigue devolviendo Post::class/Comment::class.
- CommentFactory::new()->create() crea un comentario válido por sí sola (user y Post reales) y create([...]) con los tres campos explícitos sigue sobrescribiendo los defaults.
- Caso límite: los originales de app/Comment/ siguen intactos y la app arranca (php artisan route:list no falla por clases duplicadas). PHPStan solo puede mostrar el error conocido @mixin IdeHelperComment hasta TASK-013.

**writeScope:**

- `app/Modules/Comment/Models/Comment.php` (crea)
- `app/Modules/Comment/Enums/CommentType.php` (crea)
- `app/Modules/Comment/Database/Factories/CommentFactory.php` (crea)

**validateCommand:** `php -l app/Modules/Comment/Models/Comment.php && php -l app/Modules/Comment/Enums/CommentType.php && php -l app/Modules/Comment/Database/Factories/CommentFactory.php && php artisan route:list --name=comments && vendor/bin/pint --dirty --format agent`

**Notas:** No se registra todavía: el registro (providers/morph map/seeder) lo posee TASK-006. PHPStan se valida completo en TASK-013 y TASK-026 porque @mixin IdeHelperComment resuelve tras regenerar _ide_helper_models.php.

### TASK-002: Crear Resources y Seeder de Comment en app/Modules/Comment

Crear copias de CommentResource, CommentCollection y CommentSeeder bajo App\Modules\Comment\{Resources,Database\Seeders}. Sin cambios de lógica; CommentSeeder importa Post y Comment nuevos.

**Type:** refactor
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-001

**Acceptance Criteria:**

- CommentResource conserva las llaves id, content, createdAt, user{id,name}, repliesInfo{hasReplies,repliesCount}; CommentCollection conserva data/meta/links con el cursor.
- CommentSeeder usa App\Modules\Comment\Models\Comment y App\Modules\Post\Models\Post y mantiene 5-10 comentarios raíz por post con 5-8 respuestas cada uno.
- Caso límite: PHPStan nivel max pasa sobre los 3 archivos y no queda 'App\Comment\' dentro de app/Modules/Comment/.

**writeScope:**

- `app/Modules/Comment/Resources/CommentResource.php` (crea)
- `app/Modules/Comment/Resources/CommentCollection.php` (crea)
- `app/Modules/Comment/Database/Seeders/CommentSeeder.php` (crea)

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Comment/Resources/CommentResource.php app/Modules/Comment/Resources/CommentCollection.php app/Modules/Comment/Database/Seeders/CommentSeeder.php --memory-limit=1G && ! grep -rn 'App\\Comment\\' app/Modules/Comment && vendor/bin/pint --dirty --format agent`

### TASK-003: Crear Request (FluentValidation) y Controller de Comment en app/Modules/Comment

CommentStoreRequest nuevo usa HasFluentRules + FluentRule::* con mensajes inline y sin messages(): content string requerido máx. 1000, commentable_type requerido en post/comment (CommentType), commentable_id entero requerido; mismos mensajes (content.required = 'El comentario es obligatorio'). CommentController se copia sin cambios de lógica (index y store); los cambios de comportamiento de seguridad quedan para las tareas de la fase de auditoría.

**Type:** refactor
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-002

**Acceptance Criteria:**

- CommentStoreRequest nuevo no declara messages(), usa HasFluentRules y FluentRule::* y mantiene reglas y mensajes equivalentes a los originales.
- CommentController nuevo mantiene firma y respuestas de index (CommentType + int, cursorPaginate(10), orderByDesc id, flash 'comments') y store (flash 'Comentario publicado correctamente').
- Caso límite: PHPStan nivel max pasa sobre ambos archivos y no queda 'App\Comment\' en app/Modules/Comment/.

**writeScope:**

- `app/Modules/Comment/Requests/CommentStoreRequest.php` (crea)
- `app/Modules/Comment/Controllers/CommentController.php` (crea)

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Comment/Requests/CommentStoreRequest.php app/Modules/Comment/Controllers/CommentController.php --memory-limit=1G && ! grep -rn 'App\\Comment\\' app/Modules/Comment && vendor/bin/pint --dirty --format agent`

### TASK-004: Crear providers y rutas de Comment en app/Modules/Comment

Crear CommentServiceProvider, CommentRouteServiceProvider y routes/routes.php bajo App\Modules\Comment\Providers. routes.php importa el CommentController nuevo y mantiene comments.index (GET comments/{commentableType}/{commentableId}) y comments.store (POST comments) bajo auth+verified. Aún no se registran.

**Type:** refactor
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-003

**Acceptance Criteria:**

- routes.php define exactamente comments.index y comments.store con el controller de App\Modules\Comment y el middleware ['auth','verified'].
- CommentRouteServiceProvider carga ../routes/routes.php dentro del grupo 'web' y CommentServiceProvider lo registra.
- Caso límite: mientras no se registre, route:list --name=comments sigue mostrando solo 2 rutas (las del módulo viejo), sin duplicados.

**writeScope:**

- `app/Modules/Comment/Providers/CommentServiceProvider.php` (crea)
- `app/Modules/Comment/Providers/CommentRouteServiceProvider.php` (crea)
- `app/Modules/Comment/routes/routes.php` (crea)

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Comment/Providers/CommentServiceProvider.php app/Modules/Comment/Providers/CommentRouteServiceProvider.php app/Modules/Comment/routes/routes.php --memory-limit=1G && php artisan route:list --name=comments && vendor/bin/pint --dirty --format agent`

**Notas:** Registro en bootstrap/providers.php: TASK-006.

### TASK-005: Apuntar User, Post y PostModelTest al namespace nuevo de Comment

Reemplazar imports App\Comment\Models\Comment y App\Comment\Enums\CommentType por App\Modules\Comment\... en User, Post y PostModelTest. Sin cambios de lógica.

**Type:** refactor
**Priority:** P1
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-001

**Acceptance Criteria:**

- Los 3 archivos importan solo App\Modules\Comment\...; User::comments() y Post::comments() siguen tipados con el Comment nuevo y PHPStan pasa.
- Caso límite: grep 'App\\Comment\\' sobre estos 3 archivos no devuelve coincidencias.

**writeScope:**

- `app/User/Models/User.php` (modifica)
- `app/Modules/Post/Models/Post.php` (modifica)
- `app/Modules/Post/Tests/Models/PostModelTest.php` (modifica)

**validateCommand:** `! grep -n 'App\\Comment\\' app/User/Models/User.php app/Modules/Post/Models/Post.php app/Modules/Post/Tests/Models/PostModelTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** PHPStan queda para TASK-013/026 por @mixin IdeHelperComment. Hasta TASK-006 el morph map sigue en estado mixto (previsto).

### TASK-006: Flip de registro: providers, morph map y DatabaseSeeder al módulo Comment nuevo

Reemplazar CommentServiceProvider (viejo) por App\Modules\Comment\Providers\CommentServiceProvider en bootstrap/providers.php, el import de Comment en el morph map de AppServiceProvider (clave 'comment') y CommentSeeder en DatabaseSeeder. Aquí el módulo nuevo pasa a estar activo.

**Type:** refactor
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-004, TASK-005

**Acceptance Criteria:**

- php artisan route:list --name=comments muestra exactamente 2 rutas cuyo controller es App\Modules\Comment\Controllers\CommentController.
- Relation::getMorphedModel('comment') devuelve App\Modules\Comment\Models\Comment y el morph map conserva user/post/like/comment.
- Caso límite: la app bootea sin errores (php artisan about) y ningún archivo fuera de app/Comment/ importa ya App\Comment\.

**writeScope:**

- `bootstrap/providers.php` (modifica)
- `app/Providers/AppServiceProvider.php` (modifica)
- `database/seeders/DatabaseSeeder.php` (modifica)

**validateCommand:** `php artisan route:list --name=comments && php artisan about --only=environment && ! grep -rn 'App\\Comment\\' app bootstrap database routes config --include='*.php' --exclude-dir=Comment && vendor/bin/pint --dirty --format agent`

**Notas:** El grep excluye app/Comment hasta que TASK-007..010 lo eliminen (--exclude-dir=Comment excluye también app/Modules/Comment, ya verificado en TASK-002/003).

### TASK-007: Eliminar providers y rutas del módulo viejo app/Comment

Borrar app/Comment/Providers/* y app/Comment/routes/routes.php (ya no están registrados tras TASK-006) y sus directorios vacíos. Solo eliminación.

**Type:** refactor
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-006

**Acceptance Criteria:**

- Los 3 archivos ya no existen y route:list --name=comments sigue mostrando solo 2 rutas.
- Caso límite: ningún archivo restante referencia App\Comment\Providers\.

**writeScope:**

- `app/Comment/Providers/CommentServiceProvider.php` (elimina)
- `app/Comment/Providers/CommentRouteServiceProvider.php` (elimina)
- `app/Comment/routes/routes.php` (elimina)

**validateCommand:** `! ls app/Comment/Providers/CommentServiceProvider.php app/Comment/Providers/CommentRouteServiceProvider.php app/Comment/routes/routes.php 2>/dev/null && php artisan route:list --name=comments`

### TASK-008: Eliminar Controller, Request y CommentResource del módulo viejo

Borrar CommentController, CommentStoreRequest y CommentResource viejos y los directorios vacíos. Solo eliminación.

**Type:** refactor
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-007

**Acceptance Criteria:**

- Los 3 archivos ya no existen y route:list --name=comments sigue funcionando.
- Caso límite: grep de 'App\\Comment\\(Controllers|Requests|Resources)' sobre app/, bootstrap/, routes/, database/ y tests/ no devuelve coincidencias.

**writeScope:**

- `app/Comment/Controllers/CommentController.php` (elimina)
- `app/Comment/Requests/CommentStoreRequest.php` (elimina)
- `app/Comment/Resources/CommentResource.php` (elimina)

**validateCommand:** `! ls app/Comment/Controllers/CommentController.php app/Comment/Requests/CommentStoreRequest.php app/Comment/Resources/CommentResource.php 2>/dev/null && php artisan route:list --name=comments`

### TASK-009: Eliminar CommentCollection, CommentType y CommentSeeder del módulo viejo

Borrar CommentCollection, CommentType y CommentSeeder viejos y los directorios vacíos. Solo eliminación.

**Type:** refactor
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-008

**Acceptance Criteria:**

- Los 3 archivos ya no existen y php artisan about arranca sin errores.
- Caso límite: ninguna clase del módulo nuevo ni del resto de la app referencia App\Comment\Enums\ ni App\Comment\Seeders\.

**writeScope:**

- `app/Comment/Resources/CommentCollection.php` (elimina)
- `app/Comment/Enums/CommentType.php` (elimina)
- `app/Comment/Seeders/CommentSeeder.php` (elimina)

**validateCommand:** `! ls app/Comment/Resources/CommentCollection.php app/Comment/Enums/CommentType.php app/Comment/Seeders/CommentSeeder.php 2>/dev/null && php artisan about --only=environment`

### TASK-010: Eliminar Model y Factory del módulo viejo y el directorio app/Comment

Borrar Comment y CommentFactory viejos y los directorios vacíos restantes, de modo que app/Comment/ deje de existir. Solo eliminación.

**Type:** refactor
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-009

**Acceptance Criteria:**

- app/Comment/ ya no existe en disco.
- Caso límite: grep 'App\\Comment\\' sobre app/, bootstrap/, database/, routes/, config/ y tests/ no devuelve coincidencias.

**writeScope:**

- `app/Comment/Models/Comment.php` (elimina)
- `app/Comment/Factories/CommentFactory.php` (elimina)

**validateCommand:** `! test -d app/Comment && ! grep -rn 'App\\Comment\\' app bootstrap database routes config tests --include='*.php'`

### TASK-011: Regenerar Wayfinder para el namespace App/Modules/Comment

Ejecutar php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder (sin --path escribe en resources/js/actions y resources/js/routes). Salida autogenerada y gitignoreada: no se edita a mano ni aparece en el diff; el writeScope es el directorio generado (excepción documentada al tope de 3 archivos). resources/js/shared/wayfinder/routes/comments/ no cambia (depende del nombre de ruta).

**Type:** refactor
**Priority:** P1
**Effort:** XS
**Agent:** react-vite-tailwind-engineer
**Review:** claude,codex,user
**Depends on:** TASK-010

**Acceptance Criteria:**

- Existe resources/js/shared/wayfinder/actions/App/Modules/Comment/Controllers/CommentController.ts con index y store apuntando a las rutas comments.index/comments.store.
- Caso límite: ya no existe resources/js/shared/wayfinder/actions/App/Comment/ y no aparecen carpetas nuevas en resources/js/ (actions/, routes/, wayfinder/) fuera de shared/wayfinder.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder && test -f resources/js/shared/wayfinder/actions/App/Modules/Comment/Controllers/CommentController.ts && ! test -d resources/js/shared/wayfinder/actions/App/Comment && ! test -d resources/js/actions`

**Notas:** writeScope real: resources/js/shared/wayfinder/actions/App/** (generado, gitignoreado).

### TASK-012: Actualizar imports de CommentController en el frontend

Cambiar '@/shared/wayfinder/actions/App/Comment/Controllers/CommentController' por '@/shared/wayfinder/actions/App/Modules/Comment/Controllers/CommentController' en CommentForm.tsx y usePaginatedComments.ts (únicos consumidores detectados). Sin cambios de UI.

**Type:** refactor
**Priority:** P1
**Effort:** XS
**Agent:** react-vite-tailwind-engineer
**Review:** claude,codex,user
**Depends on:** TASK-011

**Acceptance Criteria:**

- Ambos archivos importan desde actions/App/Modules/Comment/Controllers/CommentController y npm run types pasa sin errores.
- Caso límite: grep 'App/Comment' sobre resources/js (incluido wayfinder) no devuelve coincidencias; ESLint y Prettier pasan sobre ambos archivos.

**writeScope:**

- `resources/js/modules/comments/components/forms/CommentForm.tsx` (modifica)
- `resources/js/modules/comments/hooks/usePaginatedComments.ts` (modifica)

**validateCommand:** `npx eslint resources/js/modules/comments/components/forms/CommentForm.tsx resources/js/modules/comments/hooks/usePaginatedComments.ts && npx prettier --check resources/js/modules/comments/components/forms/CommentForm.tsx resources/js/modules/comments/hooks/usePaginatedComments.ts && npm run types && ! grep -rn 'App/Comment' resources/js`

### TASK-013: Regenerar _ide_helper_models.php y cerrar el gate de PHPStan

Ejecutar composer ide-helper para que _ide_helper_models.php declare App\Modules\Comment\Models\Comment / IdeHelperComment. Archivo autogenerado y gitignoreado; ide-helper reescribe docblocks de otros modelos (quita una línea ' *' antes de @mixin), así que se corre Pint después para restaurarlos y se confirma que no queda diff fuera del módulo.

**Type:** chore
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-010

**Acceptance Criteria:**

- _ide_helper_models.php contiene el namespace App\Modules\Comment\Models y el @method de CommentFactory apunta a App\Modules\Comment\Database\Factories\CommentFactory.
- Caso límite: tras Pint, git status no muestra cambios en otros modelos (Post, User, Like, Friendship, TrustedDevice*) y composer phpstan pasa con 0 errores.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `composer ide-helper && vendor/bin/pint --dirty --format agent && ! grep -n 'App\\Comment\\' _ide_helper_models.php && composer phpstan`

**Notas:** Archivo gitignoreado: la tarea regenera el archivo local; el diff versionado debe quedar vacío.

### TASK-014: Registrar tests de Comment, helper createComment() y cubrir comments.store

Agregar __DIR__.'/../app/Modules/Comment/Tests' al ->in() de tests/Pest.php y un <testsuite name="Comment"> en phpunit.xml; agregar el helper global createComment(?User $user = null, array $attributes = []): Comment (mismo patrón que createPost()/createTrustedDevice(), con el workaround #[UseFactory]). Crear Tests/Crud/CommentStoreTest.php (Pest): guest redirige a login; validación (content vacío, > 1000 caracteres, commentable_type fuera de post/comment, commentable_id no entero); comentario sobre un Post persiste con el usuario autenticado como autor y flash 'Comentario publicado correctamente'; respuesta a otro Comment; comentable inexistente devuelve 404.

**Type:** test
**Priority:** P1
**Effort:** M
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-006

**Acceptance Criteria:**

- vendor/bin/pest app/Modules/Comment/Tests/Crud/CommentStoreTest.php pasa y cubre guest, las 4 validaciones, comentario sobre Post, respuesta a Comment y autoría del usuario autenticado.
- Caso de falla: commentable_id de un Post inexistente devuelve 404 y no se crea ningún comentario; commentable_type inválido no crea nada y devuelve error de validación.
- tests/Pest.php y phpunit.xml registran app/Modules/Comment/Tests (suite 'Comment' ejecutable con vendor/bin/pest --testsuite=Comment).

**writeScope:**

- `tests/Pest.php` (modifica)
- `phpunit.xml` (modifica)
- `app/Modules/Comment/Tests/Crud/CommentStoreTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Comment/Tests/Crud/CommentStoreTest.php && vendor/bin/pest --testsuite=Comment && vendor/bin/pint --dirty --format agent`

**Notas:** Dueño del registro de la carpeta de tests y del helper createComment(). GitButler puede bloquear los hunks de tests/Pest.php si otra rama aplicada modificó líneas adyacentes (ver SOC-38); en ese caso se commitean tras el merge de esa rama.

### TASK-015: Tests de comments.index y de CommentStoreRequest

Crear Tests/Crud/CommentIndexTest.php (guest redirige a login; devuelve comentarios del Post paginados con cursor de 10 en orden descendente por id; respuestas de un Comment; incluye el usuario; commentable inexistente → 404; commentableType inválido → 404) y Tests/Requests/CommentStoreRequestTest.php con FluentRulesTester (content requerido y máx. 1000, commentable_type solo post|comment, commentable_id entero, mensaje 'El comentario es obligatorio', no autorizado para guest).

**Type:** test
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-014

**Acceptance Criteria:**

- CommentIndexTest verifica el límite de 10, el orden descendente por id, has_more/next_cursor y que otro Post no contamina el listado.
- Caso de falla: commentableType fuera del enum y commentableId inexistente responden 404 sin tocar la base de datos.
- CommentStoreRequestTest pasa con datos válidos (content de 1000 caracteres exactos) y falla solo en la regla correcta para cada entrada inválida.

**writeScope:**

- `app/Modules/Comment/Tests/Crud/CommentIndexTest.php` (crea)
- `app/Modules/Comment/Tests/Requests/CommentStoreRequestTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Comment/Tests/Crud/CommentIndexTest.php app/Modules/Comment/Tests/Requests/CommentStoreRequestTest.php && vendor/bin/pint --dirty --format agent`

### TASK-016: Tests del modelo Comment y del enum CommentType

Crear Tests/Models/CommentModelTest.php (relaciones user, commentable, comments con user cargado, likes; hasReplies()/repliesCount() con y sin respuestas; cast de commentable_type a CommentType; morph map 'comment') y Tests/Enums/CommentTypeTest.php (valores 'post'/'comment' y modelClass() de cada caso; tryFrom de un valor inválido devuelve null).

**Type:** test
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-014

**Acceptance Criteria:**

- CommentModelTest verifica cada relación con registros reales, repliesCount() exacto y que las respuestas llegan con user cargado (sin lazy loading).
- Caso límite: un comentario sin respuestas devuelve hasReplies() false y repliesCount() 0; Relation::getMorphedModel('comment') === Comment::class.
- CommentTypeTest cubre ambos casos del enum y el valor inválido.

**writeScope:**

- `app/Modules/Comment/Tests/Models/CommentModelTest.php` (crea)
- `app/Modules/Comment/Tests/Enums/CommentTypeTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Comment/Tests/Models/CommentModelTest.php app/Modules/Comment/Tests/Enums/CommentTypeTest.php && vendor/bin/pint --dirty --format agent`

### TASK-017: Tests de CommentResource/CommentCollection, factory y seeder

Crear Tests/Resources/CommentResourceTest.php (llaves exactas de CommentResource incluyendo user{id,name} y repliesInfo; CommentCollection con data/meta/links y cursor), Tests/Database/CommentFactoryTest.php (crea un comentario válido por defecto y respeta los atributos explícitos) y Tests/Database/CommentSeederTest.php (con 1 Post y 2 usuarios: entre 5 y 10 comentarios raíz y entre 5 y 8 respuestas por comentario raíz; sin posts no crea nada).

**Type:** test
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-014

**Acceptance Criteria:**

- CommentResourceTest verifica las llaves exactas ['id','content','createdAt','user','repliesInfo'] y que user solo contiene id y name (sin email ni otros campos).
- Caso límite: CommentCollection devuelve next_cursor null y has_more false cuando hay menos de 10 comentarios.
- CommentSeederTest verifica los rangos 5-10 / 5-8 y que sin Post no se crea ningún comentario.

**writeScope:**

- `app/Modules/Comment/Tests/Resources/CommentResourceTest.php` (crea)
- `app/Modules/Comment/Tests/Database/CommentFactoryTest.php` (crea)
- `app/Modules/Comment/Tests/Database/CommentSeederTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Comment/Tests/Resources/CommentResourceTest.php app/Modules/Comment/Tests/Database/CommentFactoryTest.php app/Modules/Comment/Tests/Database/CommentSeederTest.php && vendor/bin/pint --dirty --format agent`

### TASK-018: Auditoría de seguridad de Comment: tests que confirman o descartan cada hipótesis

Crear Tests/Security/CommentSecurityAuditTest.php con un test por hipótesis de SOC-40 que describe el comportamiento SEGURO esperado: (1) comments.store con throttle (N+1 solicitudes seguidas terminan en 429); (2) commentable_id inexistente se rechaza en validación y no filtra información; (3) content con HTML/espacios/caracteres de control se normaliza o se devuelve tal cual de forma inocua (confirmar además por búsqueda que ningún componente de resources/js/modules/comments usa dangerouslySetInnerHTML); (4) las respuestas anidadas tienen un límite de profundidad; (5) store usa un único canal de flash coherente; (6) CommentResource no ejecuta consultas por comentario (conteo de queries constante); (7) CommentResource no expone email ni otros campos del usuario; (8) no existen rutas de update/delete y commentable_type solo admite post|comment. Se ejecuta la suite y se presenta al usuario una tabla hallazgo → confirmado/descartado. Los tests de hipótesis confirmadas como vulnerabilidad se marcan ->todo('SOC-40 hallazgo N') para mantener la suite en verde; cada tarea de corrección posterior quita su ->todo(). Los descartados quedan como tests de regresión activos.

**Type:** test
**Priority:** P1
**Effort:** M
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-014, TASK-017

**Acceptance Criteria:**

- Hay un test por hipótesis (8) y la ejecución produce una tabla confirmado/descartado que se entrega al usuario antes de seguir; no se corrige nada en esta tarea.
- Caso de falla: cada hipótesis confirmada aparece como test fallando contra el código actual (evidencia) y queda marcada ->todo() solo después de registrar la evidencia en la tabla.
- La suite completa sigue en verde (php artisan test --compact) con los hallazgos marcados como todo.

**writeScope:**

- `app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Comment/Tests/Security/CommentSecurityAuditTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** PAUSA OBLIGATORIA: el plan se detiene aquí para que el usuario revise los hallazgos y apruebe cada tarea de corrección (TASK-019..022). Una tarea de corrección cuya hipótesis fue descartada se marca como no aplicable en el plan, con la razón, y se omite.

### TASK-019: Corrección: rate limiting en comments.store y comments.index (si TASK-018 lo confirma)

Agregar throttle a comments.store (y a comments.index si la auditoría lo justifica) siguiendo el patrón de password.update (middleware 'throttle:N,M'), con límites definidos tras la auditoría. Quitar el ->todo() del test correspondiente de TASK-018 y agregar CommentThrottleTest.php como regresión.

**Type:** fix
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-018

**Acceptance Criteria:**

- Superado el límite configurado, comments.store responde 429 y no se crean comentarios adicionales; dentro del límite sigue respondiendo con el flash de éxito.
- Caso límite: el límite es por usuario autenticado/IP y no afecta a otro usuario; el test del límite no depende del reloj real (usa tiempo congelado o limpia RateLimiter).

**writeScope:**

- `app/Modules/Comment/routes/routes.php` (modifica)
- `app/Modules/Comment/Tests/Security/CommentThrottleTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Comment/Tests/Security/CommentThrottleTest.php app/Modules/Comment/Tests/Crud && vendor/bin/pint --dirty --format agent`

**Notas:** Condicional a TASK-018: si el hallazgo se descarta, la tarea se marca no aplicable y no se ejecuta.

### TASK-020: Corrección: endurecer la validación de CommentStoreRequest (si TASK-018 lo confirma)

Endurecer CommentStoreRequest según lo confirmado: existencia de commentable_id según commentable_type (exists contra posts/comments), normalización del content (trim, sin caracteres de control) y mínimo razonable de contenido no vacío tras normalizar. Mensajes en español inline. Quitar el ->todo() de los tests correspondientes y cubrir con CommentValidationHardeningTest.php.

**Type:** fix
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-018

**Acceptance Criteria:**

- commentable_id inexistente para el commentable_type indicado falla en validación (422/errors) y no depende de findOrFail; con datos válidos todo sigue funcionando.
- Caso límite: content compuesto solo por espacios o caracteres de control falla con 'El comentario es obligatorio'; content de 1000 caracteres válidos sigue pasando.

**writeScope:**

- `app/Modules/Comment/Requests/CommentStoreRequest.php` (modifica)
- `app/Modules/Comment/Tests/Security/CommentValidationHardeningTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Comment/Tests/Security/CommentValidationHardeningTest.php app/Modules/Comment/Tests/Requests app/Modules/Comment/Tests/Crud/CommentStoreTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** Condicional a TASK-018.

### TASK-021: Corrección: coherencia del controller y límite de profundidad de respuestas (si TASK-018 lo confirma)

Eliminar la rama inalcanzable de store que usa back()->with([...]) y unificar la respuesta con Inertia::flash; definir y aplicar un límite de profundidad de respuestas (valor configurable, documentado en el plan de ejecución tras la auditoría). Quitar los ->todo() correspondientes y cubrir con CommentControllerHardeningTest.php.

**Type:** fix
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-018

**Acceptance Criteria:**

- Responder a un comentario más allá de la profundidad máxima falla con un error de validación claro y no crea el comentario; dentro del límite sigue funcionando.
- Caso límite: store ya no contiene la rama back()->with(['type','message']) y toda respuesta de éxito/error usa Inertia::flash con la forma {type, message}.

**writeScope:**

- `app/Modules/Comment/Controllers/CommentController.php` (modifica)
- `app/Modules/Comment/Tests/Security/CommentControllerHardeningTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Comment/Tests/Security/CommentControllerHardeningTest.php app/Modules/Comment/Tests/Crud && vendor/bin/pint --dirty --format agent`

**Notas:** Condicional a TASK-018. Si la profundidad requiere un helper/config, se acuerda con el usuario antes de crearlo (no se crean carpetas base nuevas sin aprobación).

### TASK-022: Corrección: eliminar el N+1 de CommentResource (si TASK-018 lo confirma)

Reemplazar hasReplies()/repliesCount() por withCount('comments') cargado en el query de index (y en cualquier otra carga del Resource) para que el costo sea constante por página; ajustar CommentResource para leer comments_count sin consultas adicionales. Quitar el ->todo() correspondiente y cubrir con CommentQueryCountTest.php. Nota: el controller ya cambia en TASK-021, por lo que esta tarea depende de ella para no pisar el mismo archivo.

**Type:** fix
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-018, TASK-021

**Acceptance Criteria:**

- El listado de 10 comentarios ejecuta un número constante de consultas (no crece con la cantidad de comentarios ni de respuestas); repliesInfo conserva exactamente los mismos valores.
- Caso límite: un comentario sin respuestas devuelve hasReplies false y repliesCount 0 sin consultas extra.

**writeScope:**

- `app/Modules/Comment/Resources/CommentResource.php` (modifica)
- `app/Modules/Comment/Tests/Security/CommentQueryCountTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Comment/Tests/Security/CommentQueryCountTest.php app/Modules/Comment/Tests/Resources app/Modules/Comment/Tests/Crud/CommentIndexTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** Si el withCount debe agregarse en CommentController::index, esa edición pertenece a esta tarea y su writeScope se amplía a 3 archivos (CommentController incluido) tras TASK-021.

### TASK-023: Actualizar CLAUDE.md: Post y Comment migrados a app/Modules

En CLAUDE.md: Project Overview (migrados: Post y Comment), lista de dominios (Comment → app/Modules/Comment/ con su estructura), 'hoy solo Post' en la sección de tests, modelos (App\Modules\Comment\Models\Comment, CommentType) y un párrafo con los cambios de seguridad aplicados en SOC-40 (solo los que se confirmaron y corrigieron).

**Type:** docs
**Priority:** P2
**Effort:** S
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-006, TASK-022

**Acceptance Criteria:**

- CLAUDE.md declara que Post y Comment son los módulos migrados y describe la estructura de Comment; las menciones a 'only Post' se actualizan.
- Caso límite: grep 'app/Comment' y 'App\\Comment' sobre CLAUDE.md no devuelve coincidencias y los cambios de seguridad documentados coinciden con los realmente aplicados.

**writeScope:**

- `CLAUDE.md` (modifica)

**validateCommand:** `! grep -nE 'app/Comment|App\\Comment' CLAUDE.md && grep -n 'app/Modules/Comment' CLAUDE.md`

**Notas:** GitButler puede bloquear hunks de CLAUDE.md pegados a líneas modificadas por otras ramas aplicadas; evitar insertar texto adyacente a líneas ajenas.

### TASK-024: Actualizar AGENTS.md y docs/architecture/backend.md

AGENTS.md (regla de ubicación de tests) y docs/architecture/backend.md (sección 0: estado de la migración con Post y Comment migrados; ejemplo canónico; mención de las convenciones de seguridad del módulo si aplica) reflejan app/Modules/Comment/.

**Type:** docs
**Priority:** P2
**Effort:** S
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-006, TASK-022

**Acceptance Criteria:**

- backend.md y AGENTS.md dicen que Post y Comment están en app/Modules y que el resto sigue en la arquitectura vieja.
- Caso límite: ni AGENTS.md ni backend.md contienen 'app/Comment/' ni 'App\\Comment'; Prettier pasa sobre backend.md.

**writeScope:**

- `AGENTS.md` (modifica)
- `docs/architecture/backend.md` (modifica)

**validateCommand:** `! grep -nE 'app/Comment|App\\Comment' AGENTS.md docs/architecture/backend.md && npx prettier --check docs/architecture/backend.md`

### TASK-025: Actualizar el skill social-hub-conventions y revisar .ai/rules

SKILL.md (fuente canónica): migrados = Post y Comment. .ai/rules/app.md y backend.md solo se editan si contienen texto que asuma app/<Domain>/. Verificar que los symlinks de .agents/, .windsurf/ y .codex/ siguen apuntando a .claude/skills/social-hub-conventions.

**Type:** docs
**Priority:** P2
**Effort:** XS
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-006, TASK-022

**Acceptance Criteria:**

- SKILL.md indica que Post y Comment están migrados y usa App\Modules\Comment como ejemplo donde corresponda.
- Caso límite: los 3 symlinks siguen siendo symlinks (ls -la muestra '->') y .ai/rules/* no queda con texto que contradiga la nueva ubicación.

**writeScope:**

- `.claude/skills/social-hub-conventions/SKILL.md` (modifica)
- `.ai/rules/app.md` (modifica)
- `.ai/rules/backend.md` (modifica)

**validateCommand:** `! grep -n 'app/Comment' .claude/skills/social-hub-conventions/SKILL.md && ls -la .agents/skills/social-hub-conventions .windsurf/skills/social-hub-conventions .codex/skills/social-hub-conventions | grep -c -- '->'`

**Notas:** No se usa record-rule: solo se edita texto existente.

### TASK-026: QA backend: Pint, PHPStan, Rector y suites

Gate backend completo: vendor/bin/pint --dirty --format agent, composer phpstan, composer rector-dry (y composer rector tras revisar el diff; solo correcciones mecánicas), vendor/bin/pest --testsuite=Comment y la suite completa (php artisan test --compact, varias corridas por posibles tests con datos aleatorios).

**Type:** chore
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-010, TASK-013, TASK-017, TASK-022

**Acceptance Criteria:**

- Pint, PHPStan (nivel max) y Rector (dry-run sin cambios pendientes) pasan.
- Caso límite: la suite Comment y la suite completa pasan en al menos 3 corridas seguidas; ningún test marcado ->todo() sin una tarea de corrección asociada.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `vendor/bin/pint --dirty --format agent && composer phpstan && composer rector-dry && vendor/bin/pest --testsuite=Comment && php artisan test --compact`

**Notas:** writeScope vacío: solo correcciones mecánicas que los gates exijan.

### TASK-027: QA frontend: lint, format, types y build

Gates frontend: npm run lint:check, npm run format:check, npm run types y npm run build para confirmar que el bundle compila contra las rutas de Wayfinder nuevas.

**Type:** chore
**Priority:** P2
**Effort:** XS
**Agent:** react-vite-tailwind-engineer
**Review:** claude,codex,user
**Depends on:** TASK-011, TASK-012

**Acceptance Criteria:**

- lint:check, format:check y types terminan sin errores (los warnings preexistentes no cuentan).
- Caso límite: npm run build completa sin 'Could not resolve' y 'App/Comment/' no aparece en public/build.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `npm run lint:check && npm run format:check && npm run types && npm run build && ! grep -rl 'App/Comment/' public/build`

### TASK-028: Barrido final de referencias viejas y symlinks

Verificación solo lectura: no quedan referencias a App\Comment\, app/Comment ni actions/App/Comment en código, config, frontend, docs, skill ni reglas; los symlinks del skill siguen siendo symlinks.

**Type:** chore
**Priority:** P3
**Effort:** XS
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-023, TASK-024, TASK-025, TASK-026, TASK-027

**Acceptance Criteria:**

- grep -rnE 'App\\Comment\\|app/Comment|App/Comment' sobre app, bootstrap/providers.php, config, database, routes, tests, resources/js, docs, CLAUDE.md, AGENTS.md, .claude, .ai y .github devuelve vacío (bootstrap/ssr es artefacto gitignoreado).
- Caso límite: git status/but status no muestra archivos huérfanos de app/Comment ni copias físicas del skill.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `! grep -rnE 'App\\Comment\\|app/Comment|App/Comment' app bootstrap/providers.php config database routes tests resources/js docs CLAUDE.md AGENTS.md .claude .ai .github`

**Notas:** No incluir .ulpi/plans/soc-40-* en el barrido: el plan menciona las rutas viejas por diseño.

### TASK-029: Cierre: PR con template y comentario resumen en SOC-40

Definition of Done: abrir el PR con .github/PULL_REQUEST_TEMPLATE y dejar en SOC-40 el comentario resumen, incluyendo la tabla de hallazgos de seguridad (confirmados y corregidos / descartados con su razón). Requiere pedido explícito del usuario; no se hace automáticamente.

**Type:** chore
**Priority:** P3
**Effort:** XS
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-028

**Acceptance Criteria:**

- El PR existe con el template completo y enlaza SOC-40; SOC-40 tiene un comentario resumen con cambios, hallazgos de seguridad y gates ejecutados.
- Caso límite: si algún gate se omitió o falló se declara explícitamente; no se mergea ni activa auto-merge sin pedido.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `gh pr view --json url,title`

**Notas:** Acción de salida (publica contenido): solo con confirmación explícita del usuario.

## Failure Modes

- Estado mixto durante el cutover (dos clases Comment/CommentType): se mitiga ordenando los consumidores (TASK-005) antes del flip de registro (TASK-006).
- Morph map y enum: si 'comment' o CommentType::modelClass() apuntan a la clase equivocada se rompen respuestas anidadas y likes polimórficos; TASK-006 y TASK-016 lo verifican.
- PHPStan: Comment declara @mixin IdeHelperComment, que resuelve solo tras TASK-013 (ide-helper); hasta entonces el único error permitido es class.notFound de IdeHelperComment.
- Wayfinder sin --path escribe en resources/js/actions|routes|wayfinder (carpetas basura); TASK-011 lo verifica.
- CommentFactory hoy no es utilizable sola (user_id/commentable_* null); TASK-001 la completa y el seeder sigue pasando los 3 campos explícitos.
- GitButler puede bloquear hunks de tests/Pest.php y CLAUDE.md si otra rama aplicada modificó líneas adyacentes (visto en SOC-38); si ocurre, commitear tras el merge de esa rama.
- Correcciones de seguridad que cambian comportamiento (throttle, validación más estricta, límite de profundidad) pueden romper tests o flujos del frontend; por eso se aprueban una a una y se prueban contra el frontend (CommentForm).
- ide-helper reescribe docblocks de otros modelos (línea ' *' antes de @mixin); TASK-013 corre Pint después y verifica que no queda diff.

## Ship Cut

Si la ejecución se detiene a mitad de camino: tras TASK-001..010 el módulo ya vive en app/Modules/Comment y app/Comment/ no existe (backend consistente), pero el frontend aún importa la ruta vieja de Wayfinder, así que TASK-011 y TASK-012 deben ir juntos con ese corte. TASK-014..017 (tests) pueden entregarse antes de la auditoría; TASK-018 (auditoría) es el punto de decisión: las correcciones TASK-019..022 son opcionales por hallazgo y se aprueban individualmente. La Definition of Done exige docs, QA y PR (TASK-023..029) antes de cerrar SOC-40.

## Test Coverage Map

| Superficie | Tarea |
| --- | --- |
| POST comments (comments.store): auth, validación, post/respuesta, autoría, 404 | TASK-014 |
| GET comments/{type}/{id} (comments.index): cursor de 10, orden, 404 | TASK-015 |
| CommentStoreRequest (FluentValidation) | TASK-015 |
| Comment model, relaciones, cast y morph map | TASK-016 |
| CommentType enum | TASK-016 |
| CommentResource / CommentCollection | TASK-017 |
| CommentFactory y CommentSeeder | TASK-017 |
| Auditoría de seguridad (8 hipótesis) | TASK-018 |
| Correcciones de seguridad con regresión | TASK-019, TASK-020, TASK-021, TASK-022 |
| Frontend compila contra Wayfinder nuevo | TASK-027 |

## Execution Summary

- **Tareas:** 29
- **Camino crítico (14):** TASK-001 → TASK-002 → TASK-003 → TASK-004 → TASK-006 → TASK-007 → TASK-008 → TASK-009 → TASK-010 → TASK-011 → TASK-012 → TASK-027 → TASK-028 → TASK-029
- **Capas de ejecución (paralelizables dentro de cada capa):**
  - Capa 0: TASK-001
  - Capa 1: TASK-002, TASK-005
  - Capa 2: TASK-003
  - Capa 3: TASK-004
  - Capa 4: TASK-006
  - Capa 5: TASK-007, TASK-014
  - Capa 6: TASK-008, TASK-015, TASK-016, TASK-017
  - Capa 7: TASK-009, TASK-018
  - Capa 8: TASK-010, TASK-019, TASK-020, TASK-021
  - Capa 9: TASK-011, TASK-013, TASK-022
  - Capa 10: TASK-012, TASK-023, TASK-024, TASK-025, TASK-026
  - Capa 11: TASK-027
  - Capa 12: TASK-028
  - Capa 13: TASK-029

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-002 |
| TASK-004 | TASK-003 |
| TASK-005 | TASK-001 |
| TASK-006 | TASK-004, TASK-005 |
| TASK-007 | TASK-006 |
| TASK-008 | TASK-007 |
| TASK-009 | TASK-008 |
| TASK-010 | TASK-009 |
| TASK-011 | TASK-010 |
| TASK-012 | TASK-011 |
| TASK-013 | TASK-010 |
| TASK-014 | TASK-006 |
| TASK-015 | TASK-014 |
| TASK-016 | TASK-014 |
| TASK-017 | TASK-014 |
| TASK-018 | TASK-014, TASK-017 |
| TASK-019 | TASK-018 |
| TASK-020 | TASK-018 |
| TASK-021 | TASK-018 |
| TASK-022 | TASK-018, TASK-021 |
| TASK-023 | TASK-006, TASK-022 |
| TASK-024 | TASK-006, TASK-022 |
| TASK-025 | TASK-006, TASK-022 |
| TASK-026 | TASK-010, TASK-013, TASK-017, TASK-022 |
| TASK-027 | TASK-011, TASK-012 |
| TASK-028 | TASK-023, TASK-024, TASK-025, TASK-026, TASK-027 |
| TASK-029 | TASK-028 |
