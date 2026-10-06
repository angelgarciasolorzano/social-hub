# Plan: SOC-38: Mover el módulo Post a app/Modules y definir la nueva arquitectura de módulos

## Overview

Los dominios viven sueltos en la raíz de app/ junto a código global. SOC-38 introduce app/Modules/<Domain>/ como ubicación de los módulos de dominio (todo lo que quede fuera de Modules es global) y migra solo Post como primer módulo. El plan crea el módulo nuevo en paralelo al viejo (crear-nuevo-luego-borrar-viejo), actualiza los consumidores y flipea el registro, elimina app/Post, regenera Wayfinder y actualiza el import del frontend, crea los tests que Post no tiene y sincroniza documentación, skill y reglas.

**Issue:** [SOC-38](https://linear.app/social-hub-ang/issue/SOC-38/refactor-move-post-module-into-appmodules-and-define-the-new-modules) — Refactor: Move Post module into app/Modules and define the new modules architecture
**Modo:** HOLD · **Estilo:** Paso a paso: se implementa una tarea y se pausa para revisión (regla .ai/rules/linear.md). · **Review por defecto:** claude,codex,user

## Scope Challenge

Modo HOLD: se mantiene el alcance de la issue (mover Post backend+frontend/Wayfinder, tests de Post, docs/skill/reglas) sin ampliarlo ni recortarlo. Hallazgos que afinan el alcance: (1) Post tiene un solo endpoint (POST /post → PostController invocable, post.store), por lo que los tests cubren creación/validación/imagen y no CRUD completo; (2) el único consumidor frontend es PostForm.tsx; (3) resources/js/shared/wayfinder está versionado y la carpeta routes/post no cambia porque depende del nombre de ruta; (4) composer.json ya autocarga App\ => app/, no requiere cambios; (5) phpstan.neon/rector.php no mencionan Post. Review por defecto: claude, codex y revisión del usuario.

## Prerequisites

- Se leyeron .ai/rules/{app,backend,linear}.md: sin variables de una letra, docblocks con máximo 3 líneas de prosa, Pint/PHPStan/Rector como gates, plan en .ulpi/plans/soc-38-*.
- composer.json autoload psr-4 'App\\' => 'app/' ya cubre App\Modules\Post sin cambios; no se modifican dependencias.
- Estrategia acordada en el proyecto: crear lo nuevo y luego borrar lo viejo, no un rename directo.
- Working tree limpio de cambios ajenos a SOC-38 (hoy hay modificaciones sin commitear en TwoFactorDisabled/TwoFactorEnable.tsx; no deben mezclarse).

## Non-Goals

- Mover Auth, Comment, Friendship, Home, Like, MediaLibrary o User a app/Modules (tareas separadas; Auth pasará a app/Modules/Auth/ en su momento).
- Cambiar comportamiento, rutas, nombres de rutas o UI; el frontend solo cambia imports/Wayfinder.
- Reorganizar el interior de Post (Controllers/Factories/Models/Providers/Requests/Resources/Seeders/routes se conservan).
- Cambiar comportamiento: la única excepción deliberada es la migración de PostRequest a FluentValidation (mismas reglas y mensajes; corrige el espacio en 'mimes:png,jpg, webp').
- Agregar tests a módulos distintos de Post.
- Corregir la discrepancia image/image_file de PostRequest vs PostController ni la regla 'mimes:png,jpg, webp' (se documenta, se cubre con tests de comportamiento actual y queda como seguimiento).

## Contracts

- Namespace final: App\Modules\Post\{Controllers,Factories,Models,Providers,Requests,Resources,Seeders}; ruta post.store (POST /post, middleware auth+verified) idéntica; morph key 'post' idéntica.
- Regla de arquitectura: dentro de app/Modules/ = módulos de dominio; fuera (listeners, policies, providers, middleware, eventos, comandos) = archivos globales. app/<Domain>/ y app/<Domain>/Modules/<Feature>/ son la arquitectura vieja en transición.
- Propiedad del registro: TASK-007 (bootstrap/providers.php, morph map en AppServiceProvider, DatabaseSeeder). TASK-014 posee el registro de la carpeta de tests (tests/Pest.php + phpunit.xml). TASK-011 posee la regeneración de Wayfinder.
- Persistencia/media: Post::PATH='posts' y la colección posts_images no cambian, de modo que la ruta de almacenamiento de MediaLibraryCustomPathGenerator es idéntica y no se mueven archivos subidos. Única diferencia funcional: TEST_IMAGES_GLOB_PATH se actualiza porque se resuelve con app_path().
- Wayfinder: actions/App/Post/ → actions/App/Modules/Post/; routes/post/ sin cambios. El directorio generado se versiona.

## Existing Code Leverage

- app/Post/ (9 archivos) es el origen exacto de la copia; no hay código nuevo de negocio.
- Patrón de registro por dominio (<Domain>ServiceProvider + <Domain>RouteServiceProvider en bootstrap/providers.php) se reutiliza sin cambios.
- Tests de referencia: app/User/Modules/AccountSettings/Tests/Crud/* (estilo Pest, createUser(), Inertia testing) y el helper createUser() de tests/Pest.php.
- Planes previos de migración a Modules (SOC-31, SOC-32, SOC-33) como precedente de pasos y gates.
- composer ide-helper, php artisan wayfinder:generate --with-form, composer phpstan/rector-dry/pint ya existen como gates.

## Tasks

### TASK-001: Crear capa de datos de Post en app/Modules/Post (Model, Factory, Seeder)

Crear copias de Post, PostFactory y PostSeeder bajo el namespace App\Modules\Post\{Models,Factories,Seeders} sin tocar los originales (estrategia crear-nuevo-luego-borrar-viejo). Post conserva #[UseFactory], la colección posts_images, MORPH_NAME y las relaciones user/comments/likes. TEST_IMAGES_GLOB_PATH se actualiza a 'Modules/Post/Seeders/Images/*.{jpg,jpeg,png}' porque se resuelve con app_path(); es la única diferencia funcional y es solo de ruta.

**Type:** refactor
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user

**Acceptance Criteria:**

- Los 3 archivos nuevos declaran namespace App\Modules\Post\... y PHPStan nivel max pasa sin errores sobre ellos; Post usa #[UseFactory(PostFactory::class)] apuntando a la factory nueva.
- Post::TEST_IMAGES_GLOB_PATH apunta a Modules/Post/Seeders/Images/*.{jpg,jpeg,png}; la factory sigue siendo tolerante cuando el glob no encuentra imágenes (hoy el directorio Images/ no existe en el repo).
- Caso límite: los archivos originales de app/Post/ siguen intactos y la app sigue arrancando (php artisan route:list no falla por clases duplicadas).

**writeScope:**

- `app/Modules/Post/Models/Post.php` (crea)
- `app/Modules/Post/Factories/PostFactory.php` (crea)
- `app/Modules/Post/Seeders/PostSeeder.php` (crea)

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Post/Models/Post.php app/Modules/Post/Factories/PostFactory.php app/Modules/Post/Seeders/PostSeeder.php --memory-limit=1G && php artisan route:list --name=post.store && vendor/bin/pint --dirty --format agent`

**Notas:** Archivos nuevos: no se registran todavía. El registro (providers/morph map/seeder) lo posee TASK-004.

### TASK-002: Crear capa HTTP de Post en app/Modules/Post (Request con FluentValidation, Resource, Controller)

Crear copias de PostRequest, PostResource y PostController bajo App\Modules\Post\{Requests,Resources,Controllers}. PostResource y PostController importan el Post nuevo y PostRequest nuevo. PostController y PostResource se copian sin cambios de lógica (incluida la rareza actual de validar 'image' pero leer 'image_file'; ver Failure Modes). PostRequest se migra a FluentValidation (HasFluentRules + FluentRule::*, mensajes inline, sin messages()) con las mismas reglas y mensajes en español; el typo 'mimes:png,jpg, webp' pasa a mimes('png','jpg','webp').

**Type:** refactor
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-001

**Acceptance Criteria:**

- PostRequest nuevo usa HasFluentRules y FluentRule::string()->required()->min(10) para content y FluentRule::file()->nullable()->mimes('png','jpg','webp')->max(5120) para image, con los 4 mensajes en español inline y sin messages().
- PostController nuevo usa App\Modules\Post\Requests\PostRequest y mantiene idénticos los mensajes de notificación y el flash con action 'Ver publicación' → route('profile.index').
- Caso límite: PHPStan nivel max pasa sobre los 3 archivos y no queda ningún import a App\Post\ dentro de app/Modules/Post/.

**writeScope:**

- `app/Modules/Post/Requests/PostRequest.php` (crea)
- `app/Modules/Post/Resources/PostResource.php` (crea)
- `app/Modules/Post/Controllers/PostController.php` (crea)

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Post/Requests/PostRequest.php app/Modules/Post/Resources/PostResource.php app/Modules/Post/Controllers/PostController.php --memory-limit=1G && ! grep -rn 'App\\Post\\' app/Modules/Post && vendor/bin/pint --dirty --format agent`

### TASK-003: Crear providers y rutas de Post en app/Modules/Post

Crear PostServiceProvider, PostRouteServiceProvider y routes/routes.php bajo App\Modules\Post\Providers. routes.php importa el PostController nuevo; PostRouteServiceProvider carga ../routes/routes.php dentro del middleware 'web'. La ruta post.store (POST /post, middleware auth+verified) no cambia. Aún no se registran en bootstrap/providers.php.

**Type:** refactor
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-002

**Acceptance Criteria:**

- routes.php define Route::post('post', PostController::class)->name('post.store') dentro de Route::middleware(['auth','verified']) con el controller de App\Modules\Post.
- PostServiceProvider registra PostRouteServiceProvider y este carga routes.php relativo a su propio directorio (__DIR__.'/../routes/routes.php').
- Caso límite: mientras no se registre el provider nuevo, route:list sigue mostrando una sola ruta post.store (la del módulo viejo), sin duplicados.

**writeScope:**

- `app/Modules/Post/Providers/PostServiceProvider.php` (crea)
- `app/Modules/Post/Providers/PostRouteServiceProvider.php` (crea)
- `app/Modules/Post/routes/routes.php` (crea)

**validateCommand:** `vendor/bin/phpstan analyse app/Modules/Post/Providers/PostServiceProvider.php app/Modules/Post/Providers/PostRouteServiceProvider.php app/Modules/Post/routes/routes.php --memory-limit=1G && php artisan route:list --name=post.store && vendor/bin/pint --dirty --format agent`

**Notas:** Registro en bootstrap/providers.php: TASK-004.

### TASK-004: Apuntar Home, Profile y User al namespace nuevo de Post

Reemplazar imports App\Post\Models\Post y App\Post\Resources\PostResource por App\Modules\Post\... en HomeController, ProfileController y User. Sin cambios de lógica. Se ordena antes del flip de registro (TASK-007) para que el cambio de providers/morph map sea el último paso del cutover.

**Type:** refactor
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-002

**Acceptance Criteria:**

- Los 3 archivos importan solo App\Modules\Post\...; User::posts() sigue devolviendo HasMany<Post> con el Post nuevo y PHPStan nivel max pasa.
- Caso límite: grep no encuentra 'App\Post\' en estos 3 archivos y el orden de imports respeta Pint.
- Las props que HomeController y ProfileController entregan a Inertia mantienen la misma forma (PostResource sin cambios de llaves).

**writeScope:**

- `app/Home/Controllers/HomeController.php` (modifica)
- `app/User/Profile/Controllers/ProfileController.php` (modifica)
- `app/User/Models/User.php` (modifica)

**validateCommand:** `vendor/bin/phpstan analyse app/Home/Controllers/HomeController.php app/User/Profile/Controllers/ProfileController.php app/User/Models/User.php --memory-limit=1G && ! grep -n 'App\\Post\\' app/Home/Controllers/HomeController.php app/User/Profile/Controllers/ProfileController.php app/User/Models/User.php && vendor/bin/pint --dirty --format agent`

### TASK-005: Apuntar Comment al namespace nuevo de Post

Actualizar el import de Post en CommentController, CommentSeeder y CommentType (enum con el mapeo a Post::class). Sin cambios de lógica.

**Type:** refactor
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-001

**Acceptance Criteria:**

- Los 3 archivos importan App\Modules\Post\Models\Post y PHPStan nivel max pasa.
- CommentType sigue resolviendo el tipo 'post' al modelo Post nuevo (verificable con grep del mapeo y con los tests de Comment existentes si los hay).
- Caso límite: ninguna referencia a App\Post\ queda en app/Comment/.

**writeScope:**

- `app/Comment/Controllers/CommentController.php` (modifica)
- `app/Comment/Seeders/CommentSeeder.php` (modifica)
- `app/Comment/Enums/CommentType.php` (modifica)

**validateCommand:** `vendor/bin/phpstan analyse app/Comment/Controllers/CommentController.php app/Comment/Seeders/CommentSeeder.php app/Comment/Enums/CommentType.php --memory-limit=1G && ! grep -rn 'App\\Post\\' app/Comment && vendor/bin/pint --dirty --format agent`

### TASK-006: Apuntar MediaLibraryCustomPathGenerator al namespace nuevo de Post

Actualizar el import de Post en MediaLibraryCustomPathGenerator. Verificar que la ruta de almacenamiento generada (Post::PATH = 'posts') no cambia, para no mover los archivos ya subidos.

**Type:** refactor
**Priority:** P1
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-001

**Acceptance Criteria:**

- El generador importa App\Modules\Post\Models\Post y usa Post::PATH sin cambios; PHPStan nivel max pasa.
- Caso límite: la ruta generada para media de un Post existente es idéntica a la anterior (Post::PATH no se modificó).

**writeScope:**

- `app/MediaLibrary/MediaLibraryCustomPathGenerator.php` (modifica)

**validateCommand:** `vendor/bin/phpstan analyse app/MediaLibrary/MediaLibraryCustomPathGenerator.php --memory-limit=1G && vendor/bin/pint --dirty --format agent`

### TASK-007: Flip de registro: providers, morph map y DatabaseSeeder al módulo nuevo

Reemplazar PostServiceProvider (viejo) por App\Modules\Post\Providers\PostServiceProvider en bootstrap/providers.php, el import de Post en el morph map de AppServiceProvider (clave 'post') y PostSeeder en DatabaseSeeder. Este es el punto donde el módulo nuevo pasa a estar activo; los originales de app/Post/ quedan huérfanos.

**Type:** refactor
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-003, TASK-004, TASK-005, TASK-006

**Acceptance Criteria:**

- php artisan route:list --name=post.store muestra exactamente una ruta (POST post) cuyo controller es App\Modules\Post\Controllers\PostController.
- Relation::getMorphedModel('post') devuelve App\Modules\Post\Models\Post (verificable con tinker) y enforceMorphMap sigue incluyendo user/post/like/comment.
- Caso límite: la app bootea sin errores (php artisan about) y ningún archivo fuera de app/Post/ importa ya App\Post\.

**writeScope:**

- `bootstrap/providers.php` (modifica)
- `app/Providers/AppServiceProvider.php` (modifica)
- `database/seeders/DatabaseSeeder.php` (modifica)

**validateCommand:** `vendor/bin/phpstan analyse bootstrap/providers.php app/Providers/AppServiceProvider.php database/seeders/DatabaseSeeder.php --memory-limit=1G && php artisan route:list --name=post.store && php artisan about --only=environment && ! grep -rn 'App\\Post\\' app bootstrap database routes config --include=*.php --exclude-dir=Post && vendor/bin/pint --dirty --format agent`

**Notas:** El grep excluye el directorio app/Post (módulo viejo) hasta que TASK-008..010 lo eliminen.

### TASK-008: Eliminar providers y rutas del módulo viejo app/Post

Borrar app/Post/Providers/* y app/Post/routes/routes.php. Son los primeros en caer porque ya no están registrados tras TASK-007. Tarea de solo eliminación.

**Type:** refactor
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-007

**Acceptance Criteria:**

- Los 3 archivos ya no existen y php artisan route:list --name=post.store sigue mostrando una sola ruta.
- Caso límite: ningún archivo restante referencia App\Post\Providers\ o App\Post\Controllers\ (grep vacío fuera de app/Post/Controllers).

**writeScope:**

- `app/Post/Providers/PostServiceProvider.php` (elimina)
- `app/Post/Providers/PostRouteServiceProvider.php` (elimina)
- `app/Post/routes/routes.php` (elimina)

**validateCommand:** `! ls app/Post/Providers/PostServiceProvider.php app/Post/Providers/PostRouteServiceProvider.php app/Post/routes/routes.php 2>/dev/null && php artisan route:list --name=post.store`

### TASK-009: Eliminar Controller, Request y Resource del módulo viejo app/Post

Borrar PostController, PostRequest y PostResource viejos. Tarea de solo eliminación.

**Type:** refactor
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-008

**Acceptance Criteria:**

- Los 3 archivos ya no existen y php artisan route:list sigue funcionando.
- Caso límite: grep de 'App\Post\(Controllers|Requests|Resources)' sobre app/, bootstrap/, routes/, database/ y tests/ no devuelve coincidencias.

**writeScope:**

- `app/Post/Controllers/PostController.php` (elimina)
- `app/Post/Requests/PostRequest.php` (elimina)
- `app/Post/Resources/PostResource.php` (elimina)

**validateCommand:** `! ls app/Post/Controllers/PostController.php app/Post/Requests/PostRequest.php app/Post/Resources/PostResource.php 2>/dev/null && php artisan route:list --name=post.store`

### TASK-010: Eliminar Model, Factory y Seeder del módulo viejo y el directorio app/Post

Borrar Post, PostFactory y PostSeeder viejos y los directorios vacíos restantes, de modo que app/Post/ deje de existir. Tarea de solo eliminación.

**Type:** refactor
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-009

**Acceptance Criteria:**

- app/Post/ ya no existe en disco ni en git.
- Caso límite: PHPStan sobre todo app/ pasa (composer phpstan) sin referencias rotas al namespace viejo.

**writeScope:**

- `app/Post/Models/Post.php` (elimina)
- `app/Post/Factories/PostFactory.php` (elimina)
- `app/Post/Seeders/PostSeeder.php` (elimina)

**validateCommand:** `! test -d app/Post && composer phpstan`

### TASK-011: Regenerar Wayfinder para el namespace App/Modules/Post

Ejecutar php artisan wayfinder:generate --with-form (equivale a formVariants:true de vite.config.ts). El generador escribe resources/js/shared/wayfinder/actions/App/Modules/Post/Controllers/PostController.ts (más sus index.ts) y debe dejar de emitir actions/App/Post/. Salida autogenerada, no se edita a mano; el writeScope es el directorio generado (excepción documentada al tope de 3 archivos). resources/js/shared/wayfinder/routes/post/ no cambia porque depende del nombre de ruta, no del namespace.

**Type:** refactor
**Priority:** P1
**Effort:** S
**Agent:** react-vite-tailwind-engineer
**Review:** claude,codex,user
**Depends on:** TASK-010

**Acceptance Criteria:**

- Existe resources/js/shared/wayfinder/actions/App/Modules/Post/Controllers/PostController.ts y exporta la acción de post.store (POST /post).
- Caso límite: ya no existe resources/js/shared/wayfinder/actions/App/Post/ (si el generador deja restos se eliminan a mano) y resources/js/shared/wayfinder/routes/post/index.ts queda sin cambios.
- El diff del directorio wayfinder solo contiene el movimiento de Post (no se regeneran ni modifican otros dominios).

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `php artisan wayfinder:generate --with-form && test -f resources/js/shared/wayfinder/actions/App/Modules/Post/Controllers/PostController.ts && ! test -d resources/js/shared/wayfinder/actions/App/Post`

**Notas:** writeScope real: resources/js/shared/wayfinder/actions/App/** (generado). Se generó antes de los tests/docs para que el frontend compile contra la ruta final.

### TASK-012: Actualizar el import de PostController en PostForm.tsx

Cambiar '@/shared/wayfinder/actions/App/Post/Controllers/PostController' por '@/shared/wayfinder/actions/App/Modules/Post/Controllers/PostController' en PostForm.tsx (único consumidor detectado). Sin cambios de UI ni de comportamiento.

**Type:** refactor
**Priority:** P1
**Effort:** XS
**Agent:** react-vite-tailwind-engineer
**Review:** claude,codex,user
**Depends on:** TASK-011

**Acceptance Criteria:**

- PostForm.tsx importa PostController desde actions/App/Modules/Post/Controllers/PostController y npm run types pasa sin errores.
- Caso límite: grep 'App/Post' sobre resources/js (incluyendo wayfinder/) no devuelve coincidencias; ESLint y Prettier pasan sobre el archivo.

**writeScope:**

- `resources/js/modules/post/components/forms/PostForm.tsx` (modifica)

**validateCommand:** `npx eslint resources/js/modules/post/components/forms/PostForm.tsx && npx prettier --check resources/js/modules/post/components/forms/PostForm.tsx && npm run types && ! grep -rn 'App/Post' resources/js`

### TASK-013: Regenerar _ide_helper_models.php

Ejecutar composer ide-helper para que _ide_helper_models.php (escaneado por PHPStan) declare App\Modules\Post\Models\Post / IdeHelperPost en vez del namespace viejo. Archivo autogenerado.

**Type:** chore
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-010

**Acceptance Criteria:**

- _ide_helper_models.php contiene el bloque namespace App\Modules\Post\Models y el @method de PostFactory apunta a App\Modules\Post\Factories\PostFactory.
- Caso límite: no queda ninguna referencia a App\Post\ en _ide_helper_models.php y composer phpstan sigue en verde (el @mixin IdeHelperPost del modelo resuelve).

**writeScope:**

- `_ide_helper_models.php` (modifica)

**validateCommand:** `composer ide-helper && ! grep -n 'App\\Post\\' _ide_helper_models.php && composer phpstan`

**Notas:** Si el repo no versiona _ide_helper_models.php el diff será vacío; en ese caso la tarea solo regenera el archivo local.

### TASK-014: Registrar la carpeta de tests de Post y cubrir POST /post (Crud/PostStoreTest)

Agregar __DIR__.'/../app/Modules/Post/Tests' al ->in() de tests/Pest.php y un <testsuite name="Post"> en phpunit.xml. Crear Tests/Crud/PostStoreTest.php (Pest, createUser()) cubriendo el único endpoint de Post (post.store): guest redirige a login; usuario sin verificar es redirigido; content requerido y min:10 con los mensajes en español; creación exitosa persiste el Post del usuario y emite el flash 'Publicación creada correctamente' con action 'Ver publicación'; subida con image_file se guarda en la colección posts_images; archivo que excede media-library.max_file_size (ajustado vía config) produce la notificación de error 'No fue posible subir la imagen' y deja el Post creado. Nota de alcance: Post no tiene update/delete/show, por eso no se testean.

**Type:** test
**Priority:** P1
**Effort:** M
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-007

**Acceptance Criteria:**

- vendor/bin/pest app/Modules/Post/Tests/Crud/PostStoreTest.php pasa y cubre: guest, no verificado, content vacío, content < 10 caracteres, creación exitosa con flash, subida de imagen, y archivo demasiado grande.
- Caso de falla: con image_file mayor al límite configurado, la respuesta contiene notification.type='error' con el mensaje de subida fallida y la colección posts_images queda vacía.
- tests/Pest.php y phpunit.xml registran la carpeta app/Modules/Post/Tests (suite 'Post' ejecutable con vendor/bin/pest --testsuite=Post).

**writeScope:**

- `tests/Pest.php` (modifica)
- `phpunit.xml` (modifica)
- `app/Modules/Post/Tests/Crud/PostStoreTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Post/Tests/Crud/PostStoreTest.php && vendor/bin/pest --testsuite=Post && vendor/bin/pint --dirty --format agent`

**Notas:** Dueño del registro de la carpeta de tests de Post. TASK-015 y TASK-016 solo agregan archivos dentro de esa carpeta ya registrada.

### TASK-015: Tests del modelo y la factory de Post

Crear Tests/Models/PostModelTest.php (relaciones user/comments/likes; morph map 'post' resuelve a Post::class; la colección posts_images es singleFile, la segunda imagen reemplaza a la primera) y Tests/Factories/PostFactoryTest.php (la factory crea un Post con content y user_id válidos, acepta ->for($user), y no falla cuando no hay imágenes de prueba disponibles).

**Type:** test
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-014

**Acceptance Criteria:**

- PostModelTest verifica user() (BelongsTo), comments() y likes() (MorphMany) con registros reales y que Relation::getMorphedModel('post') === Post::class.
- Caso límite: agregar una segunda imagen a posts_images deja una sola media (singleFile) y la nueva reemplaza a la anterior.
- PostFactoryTest comprueba que Post::factory()->create() persiste content no vacío y un user_id existente, y que ->for($user) lo asigna al usuario dado.

**writeScope:**

- `app/Modules/Post/Tests/Models/PostModelTest.php` (crea)
- `app/Modules/Post/Tests/Factories/PostFactoryTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Post/Tests/Models/PostModelTest.php app/Modules/Post/Tests/Factories/PostFactoryTest.php && vendor/bin/pint --dirty --format agent`

### TASK-016: Tests del PostRequest (FluentRulesTester), PostResource y PostSeeder

Crear Tests/Requests/PostRequestTest.php con FluentRulesTester::for(PostRequest::class) (content requerido y min:10; image nullable, mimes png/jpg/webp, max 5120 KB; mensajes en español exactos; autorización falla para guest), Tests/Resources/PostResourceTest.php (llaves exactas id, content, image, createdAt; createdAt en ISO-8601; image vacía sin media y con URL cuando hay media en posts_images) y Tests/Seeders/PostSeederTest.php (con 2 usuarios, cada uno recibe entre 5 y 20 posts propios).

**Type:** test
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-014

**Acceptance Criteria:**

- PostResourceTest verifica las llaves exactas ['id','content','image','createdAt'], createdAt en ISO-8601 e image vacía sin media (con URL no vacía cuando hay imagen en posts_images).
- PostSeederTest verifica que cada usuario tiene entre 5 y 20 posts y que ningún post queda sin usuario.
- Caso de falla (PostRequestTest, con FluentRulesTester): content vacío y < 10 caracteres fallan con sus mensajes; image PDF falla en mimes, image de más de 5120 KB falla en max; content válido sin image pasa.

**writeScope:**

- `app/Modules/Post/Tests/Resources/PostResourceTest.php` (crea)
- `app/Modules/Post/Tests/Seeders/PostSeederTest.php` (crea)
- `app/Modules/Post/Tests/Requests/PostRequestTest.php` (crea)

**validateCommand:** `vendor/bin/pest app/Modules/Post/Tests/Resources/PostResourceTest.php app/Modules/Post/Tests/Seeders/PostSeederTest.php app/Modules/Post/Tests/Requests/PostRequestTest.php && vendor/bin/pint --dirty --format agent`

### TASK-017: Actualizar CLAUDE.md con la nueva arquitectura app/Modules

En CLAUDE.md: Project Overview (línea 9, 'under app/<Domain>/'), lista de dominios (Post → app/Modules/Post/), ubicación de tests (líneas 72 y 321), 'Modular service-provider pattern' (líneas 92-93) y Models/relations (App\Modules\Post\Models\Post). Documentar la regla: dentro de app/Modules/ = módulos de dominio; fuera (listeners, policies, providers, middleware, etc.) = global. Declarar app/<Domain>/ y app/<Domain>/Modules/<Feature>/ como arquitectura vieja en transición (solo Post migrado; Auth pasará a app/Modules/Auth/). Mencionar Wayfinder bajo App/Modules/<Domain>/.

**Type:** docs
**Priority:** P2
**Effort:** S
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-007

**Acceptance Criteria:**

- CLAUDE.md declara explícitamente: app/Modules/ = dominios; fuera de Modules = global; Post es el único módulo migrado y el resto sigue en la estructura vieja hasta sus propias tareas.
- Caso límite: grep 'app/Post' y 'App\\Post' sobre CLAUDE.md no devuelve coincidencias, y las referencias a app/Auth/Modules/... se marcan como arquitectura vieja, no se borran.

**writeScope:**

- `CLAUDE.md` (modifica)

**validateCommand:** `! grep -nE 'app/Post|App\\Post' CLAUDE.md && grep -n 'app/Modules' CLAUDE.md`

### TASK-018: Actualizar AGENTS.md y docs/architecture/backend.md

AGENTS.md (línea 181, ubicación de tests por módulo) y docs/architecture/backend.md (líneas 33, 95, 104, 117, 122, 171-172 y 230: estructura app/<Module>/, submódulos, tests y árbol app/Post/) reflejan app/Modules/<Domain>/, la regla modules=dominio / fuera=global y el estado de transición. El árbol de Post pasa a app/Modules/Post/ incluyendo la nueva carpeta Tests/.

**Type:** docs
**Priority:** P2
**Effort:** S
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-007

**Acceptance Criteria:**

- backend.md muestra el árbol app/Modules/Post/ (con Tests/) y explica la regla modules=dominio / fuera=global y la transición.
- Caso límite: ni AGENTS.md ni backend.md contienen 'app/Post/' ni 'App\Post'; las menciones a app/<Domain>/Modules/ quedan marcadas como arquitectura vieja.

**writeScope:**

- `AGENTS.md` (modifica)
- `docs/architecture/backend.md` (modifica)

**validateCommand:** `! grep -nE 'app/Post|App\\Post' AGENTS.md docs/architecture/backend.md && grep -n 'app/Modules' docs/architecture/backend.md`

### TASK-019: Actualizar el skill social-hub-conventions y las reglas .ai/rules

SKILL.md (fuente canónica; líneas 19-40: layout modular, models bajo app/Post/Models, listeners, submódulos): documentar app/Modules/<Domain>/ vs global y el estado de transición. .ai/rules/app.md y backend.md ya usan el glob app/**/*.php (cubre app/Modules/); solo se editan si contienen texto que asuma app/<Domain>/. Verificar que los symlinks de .agents/, .windsurf/ y .codex/ siguen apuntando a .claude/skills/social-hub-conventions (no copias físicas).

**Type:** docs
**Priority:** P2
**Effort:** S
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-007

**Acceptance Criteria:**

- SKILL.md describe app/Modules/<Domain>/ como destino de los módulos, la regla 'fuera de Modules = global' y usa App\Modules\Post\Models\Post como ejemplo en lugar de app/Post/Models/Post.php.
- Caso límite: ls -la de los 3 symlinks (.agents, .windsurf, .codex) sigue mostrando '-> ../../.claude/skills/social-hub-conventions'; .ai/rules/* no queda con texto que contradiga la nueva ubicación.

**writeScope:**

- `.claude/skills/social-hub-conventions/SKILL.md` (modifica)
- `.ai/rules/app.md` (modifica)
- `.ai/rules/backend.md` (modifica)

**validateCommand:** `! grep -n 'app/Post' .claude/skills/social-hub-conventions/SKILL.md && ls -la .agents/skills/social-hub-conventions .windsurf/skills/social-hub-conventions .codex/skills/social-hub-conventions | grep -c -- '->'`

**Notas:** No se usa record-rule: solo se edita texto existente. Si .ai/rules no requiere cambios, se deja intacto y se reporta.

### TASK-020: QA backend: Pint, PHPStan, Rector y suite de Post

Correr el gate backend completo: vendor/bin/pint --dirty --format agent, composer phpstan, composer rector-dry y, si propone cambios, composer rector; luego la suite Post. Aplicar solo correcciones mecánicas que los gates pidan.

**Type:** chore
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-010, TASK-013, TASK-014, TASK-015, TASK-016

**Acceptance Criteria:**

- Pint, PHPStan (nivel max, 100% type coverage) y Rector (dry-run sin cambios pendientes) pasan.
- Caso límite: vendor/bin/pest --testsuite=Post y la suite que ejercita Home/Profile/Comment (php artisan test --compact) pasan; si algo falla fuera de Post se reporta sin ocultarlo.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `vendor/bin/pint --dirty --format agent && composer phpstan && composer rector-dry && vendor/bin/pest --testsuite=Post`

**Notas:** writeScope vacío: solo se permiten correcciones mecánicas de formato/tipos que los gates exijan. La suite completa se pide al usuario (regla del proyecto).

### TASK-021: QA frontend: lint, format, types y build

Correr los gates frontend: npm run lint:check, npm run format:check, npm run types y npm run build para confirmar que el bundle compila contra las rutas de Wayfinder nuevas.

**Type:** chore
**Priority:** P2
**Effort:** XS
**Agent:** react-vite-tailwind-engineer
**Review:** claude,codex,user
**Depends on:** TASK-011, TASK-012

**Acceptance Criteria:**

- npm run lint:check, npm run format:check y npm run types terminan sin errores.
- Caso límite: npm run build completa sin 'Could not resolve' sobre actions/App/Post y no aparece ese path en el bundle.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `npm run lint:check && npm run format:check && npm run types && npm run build`

### TASK-022: Barrido final de referencias viejas y symlinks

Verificación solo lectura: no quedan referencias a App\Post\, app/Post ni actions/App/Post en código, config, frontend, docs, skill ni reglas; los symlinks del skill siguen siendo symlinks.

**Type:** chore
**Priority:** P3
**Effort:** XS
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-017, TASK-018, TASK-019, TASK-020, TASK-021

**Acceptance Criteria:**

- grep -rnE 'App\\Post\\|app/Post|App/Post' sobre app, bootstrap, config, database, routes, tests, resources/js, docs, CLAUDE.md, AGENTS.md, .claude, .ai devuelve vacío (ignorando bootstrap/ssr y node_modules, que son artefactos de build).
- Caso límite: git status no muestra archivos huérfanos de app/Post ni copias físicas del skill en .agents/.windsurf/.codex.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `! grep -rnE 'App\\Post\\|app/Post|App/Post' app bootstrap/providers.php config database routes tests resources/js docs CLAUDE.md AGENTS.md .claude .ai`

**Notas:** bootstrap/ssr/ es artefacto compilado (se regenera con npm run build:ssr); no se versiona a mano.

### TASK-023: Cierre: PR con template y comentario resumen en SOC-38

Definition of Done: abrir el PR con .github/PULL_REQUEST_TEMPLATE y dejar el comentario resumen en SOC-38. Requiere pedido explícito del usuario antes de ejecutarse; no se hace automáticamente.

**Type:** chore
**Priority:** P3
**Effort:** XS
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-022

**Acceptance Criteria:**

- El PR existe con el template completo y enlaza SOC-38; SOC-38 tiene un comentario resumen con cambios y gates ejecutados.
- Caso límite: si algún gate no pasó o se omitió se declara explícitamente en el PR y en el comentario; no se mergea ni activa auto-merge sin pedido.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `gh pr view --json url,title`

**Notas:** Acción de salida (publica contenido): solo con confirmación explícita del usuario.

## Failure Modes

- Estado mixto durante el cutover (dos clases Post): se mitiga ordenando los consumidores (TASK-004..006) antes del flip de registro (TASK-007); mientras no se flipea, los providers nuevos no están registrados y no hay rutas duplicadas.
- Morph map apuntando a la clase equivocada: TASK-007 verifica Relation::getMorphedModel('post'); un desfase rompe polimórficos de Comment/Like.
- Rutas de media: si TEST_IMAGES_GLOB_PATH o Post::PATH cambian por error, las imágenes de seed o subidas quedarían en otra ruta; TASK-001/006 lo cubren con criterios explícitos.
- Wayfinder deja restos de actions/App/Post/ o el frontend importa la ruta vieja: TASK-011/012/021/022 lo detectan (types, build y grep).
- Discrepancia existente: PostRequest valida 'image' pero PostController lee 'image_file', por lo que la validación de la imagen no se aplica al upload real. No se corrige aquí; los tests documentan el comportamiento actual y debe abrirse una issue de seguimiento.
- Directorio app/Post/Seeders/Images/ no existe en el repo: la factory ya es tolerante (glob vacío); no se debe 'arreglar' creando imágenes dentro de esta tarea.
- bootstrap/ssr/ contiene artefactos compilados con rutas viejas; se regenera con npm run build:ssr y no se edita a mano.

## Ship Cut

Si la ejecución se detiene a mitad de camino: tras TASK-001..010 el módulo ya vive en app/Modules/Post y app/Post/ no existe (backend consistente), pero el frontend aún importa la ruta vieja de Wayfinder, así que TASK-011 y TASK-012 deben ir juntos con ese corte. TASK-014..016 (tests) y TASK-017..019 (docs) son independientes entre sí y pueden entregarse en PRs posteriores solo si el usuario lo decide; la Definition of Done exige tenerlos todos antes de cerrar SOC-38.

## Test Coverage Map

| Superficie | Tarea |
| --- | --- |
| POST /post (post.store): auth, verified, validación, creación, flash | TASK-014 |
| Subida de imagen y FileIsTooBig → notificación de error | TASK-014 |
| Post model: relaciones user/comments/likes, morph map 'post', singleFile posts_images | TASK-015 |
| PostFactory | TASK-015 |
| PostResource (llaves, ISO-8601, image) | TASK-016 |
| PostSeeder (5-20 posts por usuario) | TASK-016 |
| Cutover sin regresiones en Home/Profile/Comment | TASK-020 |
| Frontend compila contra Wayfinder nuevo | TASK-021 |

## Execution Summary

- **Tareas:** 23
- **Camino crítico (12):** TASK-001 → TASK-002 → TASK-003 → TASK-007 → TASK-008 → TASK-009 → TASK-010 → TASK-011 → TASK-012 → TASK-021 → TASK-022 → TASK-023
- **Capas de ejecución (paralelizables dentro de cada capa):**
  - Capa 0: TASK-001
  - Capa 1: TASK-002, TASK-005, TASK-006
  - Capa 2: TASK-003, TASK-004
  - Capa 3: TASK-007
  - Capa 4: TASK-008, TASK-014, TASK-017, TASK-018, TASK-019
  - Capa 5: TASK-009, TASK-015, TASK-016
  - Capa 6: TASK-010
  - Capa 7: TASK-011, TASK-013
  - Capa 8: TASK-012, TASK-020
  - Capa 9: TASK-021
  - Capa 10: TASK-022
  - Capa 11: TASK-023

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-002 |
| TASK-004 | TASK-002 |
| TASK-005 | TASK-001 |
| TASK-006 | TASK-001 |
| TASK-007 | TASK-003, TASK-004, TASK-005, TASK-006 |
| TASK-008 | TASK-007 |
| TASK-009 | TASK-008 |
| TASK-010 | TASK-009 |
| TASK-011 | TASK-010 |
| TASK-012 | TASK-011 |
| TASK-013 | TASK-010 |
| TASK-014 | TASK-007 |
| TASK-015 | TASK-014 |
| TASK-016 | TASK-014 |
| TASK-017 | TASK-007 |
| TASK-018 | TASK-007 |
| TASK-019 | TASK-007 |
| TASK-020 | TASK-010, TASK-013, TASK-014, TASK-015, TASK-016 |
| TASK-021 | TASK-011, TASK-012 |
| TASK-022 | TASK-017, TASK-018, TASK-019, TASK-020, TASK-021 |
| TASK-023 | TASK-022 |
