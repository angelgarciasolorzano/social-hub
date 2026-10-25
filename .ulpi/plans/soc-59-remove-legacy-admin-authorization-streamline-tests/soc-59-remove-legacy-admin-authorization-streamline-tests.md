# Plan: SOC-59: Quitar la compatibilidad heredada de Admin y depurar la suite de tests

Linear: [SOC-59](https://linear.app/social-hub-ang/issue/SOC-59/refactor-remove-obsolete-admin-authorization-code-and-streamline) — Refactor: Remove obsolete Admin authorization code and streamline project tests

## Overview

Eliminar renameLegacyPermissions()/renameLegacyRoles() y sus dos tests, dejar de adjuntar imágenes reales en las factories por defecto (estado explícito para los seeders), habilitar la ejecución paralela de la suite, auditar las 16 suites registradas en phpunit.xml para consolidar solo lo redundante u obsoleto, sincronizar la documentación y comparar la duración con la línea base.

## Scope Challenge

**Modo:** EXPANSION · **Revisión por defecto:** claude

EXPANSION confirmada por el usuario: además de quitar la compatibilidad heredada y auditar todas las suites, el plan optimiza la infraestructura de tests. La exploración encontró dos palancas de infraestructura con evidencia: (1) UserFactory y PostFactory adjuntan imágenes reales de seeding al azar en cada create() (50 % de los usuarios, 60 % de los posts), sin Storage::fake, en el disco public real (storage/app/public ocupa 30 GB y 8394 archivos); eso vuelve intermitente UserImageUpdateTest ('rejects a file that is not an image' falla cuando la factory ya adjuntó una portada) y encarece cada createUser()/createPost(); (2) paratest está instalado pero composer test corre en serie. La auditoría de redundancias no puede decidir qué consolidar sin leer los tests, así que produce primero un informe por grupo de suites y el plan se extiende después con las tareas de consolidación concretas (memoria extend-latest-plan-for-followups).

**Ejecución:** Implementar una tarea atómica por vez, revisar el diff con /code-review y pausar para revisión del usuario, según .ai/rules/linear.md.

### Evidencia sobre los nombres heredados

- Los nombres users.viewAny, users.view y users-reader nacieron en 0021e7f6 (SOC-44, 2026-10-11) y se renombraron en el commit siguiente, abb4e6cc (SOC-47, 2026-10-12), que agregó la compatibilidad.
- En la historia de primer padre de origin/main el único commit que tiene SOC-44 sin SOC-47 es el propio 0021e7f6; no hay tags ni releases.
- El repositorio no tiene workflows ni configuración de despliegue (solo gates de calidad y bots en .github/workflows; ningún Envoy/Forge/Cloud/Docker/Procfile).
- Ninguna migración crea esos nombres; solo los usan el seeder y AdminAuthorizationSeederTest (dos tests: líneas 192-248 y 250-303).
- La base local no tiene ningún permiso users.viewAny/users.view ni rol users-reader (consulta de solo lectura con Boost).
- El usuario confirma que esa implementación no llegó a staging ni a producción.

### Línea base

- Comando: php -d memory_limit=2G vendor/bin/pest --compact (tras php artisan config:clear), con los cambios sin commitear del usuario en User.php y .gitignore
- 1209 tests, 13666 assertions, 74.2 s, 1 fallo
- Fallo: app/User/Tests/Crud/UserImageUpdateTest.php 'rejects a file that is not an image' (intermitente: UserFactory adjunta una portada real al 50 % de los usuarios desde que el glob de User.php vuelve a encontrar las imágenes)
- Suites más lentas: Admin 306 tests 21.0 s, Comment 220 tests 16.7 s, TrustedDevice 220 tests 14.1 s, ActivityLog 145 tests 5.2 s, Post 69 tests 4.5 s

## Prerequisites

- Línea base medida antes de cualquier cambio: 1209 tests, 74.2 s, 1 fallo intermitente (ver evidence.baseline).
- Los cambios sin commitear del usuario en app/User/Models/User.php y .gitignore (rutas de imágenes de seeding) no son parte de SOC-59 y no se tocan.
- La suite usa MySQL (.env.testing y servicio mysql:8.4 en CI, usuario root sin contraseña) con RefreshDatabase; paratest 7 está instalado como dependencia de Pest 5.
- El disco de MediaLibrary es public (config/media-library.php, MEDIA_DISK).

## Non-Goals

- No cambiar el comportamiento de la aplicación ni los contratos HTTP, de autorización o de persistencia.
- No eliminar tests solo para bajar el conteo; cada eliminación o consolidación debe citar el test que conserva la cobertura.
- No limpiar los 30 GB existentes de storage/app/public (es estado local del usuario; se informa, no se borra).
- No agregar tests de frontend ni tocar resources/js.
- No cambiar los datos que producen los seeders (siguen adjuntando imágenes, ahora a través de un estado explícito).

## Contracts

- AdminAuthorizationSeeder sigue creando todo el catálogo AdminRole/AdminPermission bajo el guard admin, asigna los bundles solo al crear cada rol y preserva cambios operativos al re-ejecutarse.
- createUser()/createPost() y User::factory()/Post::factory() dejan de adjuntar media por defecto; UserFactory::withImages() y PostFactory::withImage() conservan el comportamiento aleatorio anterior y los seeders los usan.
- composer test sigue siendo el comando del gate local y de CI; con --parallel Laravel crea una base por proceso (<DB_DATABASE>_test_N) y el usuario de CI puede crearlas.
- Las suites registradas en phpunit.xml y tests/Pest.php no cambian de nombre ni de ubicación.

## Existing Code Leverage

- app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php — el seeder con las dos rutinas de compatibilidad.
- app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php — conserva 'preserves role assignments and revoked permissions when the seeder runs again', que cubre la idempotencia que también probaban los tests heredados.
- app/User/Database/Factories/UserFactory.php y app/Modules/Post/Database/Factories/PostFactory.php — configure()/afterCreating con las imágenes aleatorias.
- app/User/Database/Seeders/UserSeeder.php y app/Modules/Post/Database/Seeders/PostSeeder.php — consumidores de las factories al sembrar.
- app/Modules/Post/Tests/Factories/PostFactoryTest.php — ya prueba el máximo de una imagen por post.
- composer.json (script test) y docs/development/backend-commands.md (sección composer test).

## Tasks

### TASK-001: Eliminar la compatibilidad heredada de AdminAuthorizationSeeder

Quitar las llamadas y los métodos renameLegacyPermissions() y renameLegacyRoles() del seeder, y los dos tests que solo cubren ese camino ('renames the legacy users reader role…' y 'renames legacy permissions in place…'). Se conservan los imports, el helper usersReaderPermissionNames() y el test de re-ejecución, que siguen en uso.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0 · **Review:** claude

**Acceptance Criteria:**

- [ ] grep -rn "renameLegacy\|users-reader'\|'users\.view" app database tests no devuelve resultados fuera de admin-users-reader/admin.users.*.
- [ ] AdminAuthorizationSeederTest pasa y sigue verificando que re-ejecutar el seeder preserva asignaciones de roles y permisos revocados.
- [ ] Pint, PHPStan y Rector (con caché limpia, sobre todo el proyecto) quedan limpios; no queda ningún import sin usar en el seeder ni en el test.

**writeScope:** `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`, `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

### TASK-002: UserFactory sin imágenes por defecto, con estado withImages() para el seeder

Mover el afterCreating de imágenes de UserFactory::configure() a un estado withImages() que siempre adjunta una imagen de perfil y una de portada (determinista y probable); el sorteo del 50 % pasa a UserSeeder con WeightedRandom::chance(), así la distribución sembrada no cambia. Agregar un test de factory que fije que create() no adjunta media y que withImages() adjunta perfil y portada, con imágenes falsas en un app_path() temporal porque las imágenes reales de seeding no están versionadas.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1 · **Review:** claude

**Acceptance Criteria:**

- [ ] createUser() nunca tiene media en profile_picture ni cover_image (comprobado sobre varias creaciones).
- [ ] UserFactory::new()->withImages() adjunta una imagen de perfil y una de portada (Storage::fake('public') e imágenes falsas en un app_path() temporal); UserSeeder adjunta imágenes al 50 % de los usuarios con WeightedRandom::chance().
- [ ] UserImageUpdateTest pasa de forma determinista (15 ejecuciones seguidas sin fallos; antes fallaba ~50 % en local).

**writeScope:** `app/User/Database/Factories/UserFactory.php`, `app/User/Database/Seeders/UserSeeder.php`, `app/User/Tests/Factories/UserFactoryTest.php`

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/User/Tests`

### TASK-003: PostFactory sin imagen por defecto, con estado withImage() para el seeder

Mover el afterCreating de PostFactory::configure() a un estado withImage(int $chancePercent = 100) que sortea por post con WeightedRandom::chance() (determinista por defecto); PostSeeder lo usa con 60 %, así la distribución sembrada no cambia. PostFactoryTest fija que create() no adjunta imagen, que withImage() adjunta exactamente una y que withImage(0) no adjunta ninguna. Alcance ampliado por la regla 'Reuse utility functions' (.ai/rules/app.md): el helper de imágenes falsas en un app_path() temporal pasó de UserFactoryTest a tests/Pest.php (withTemporarySeedingImages) y lo usan las dos factories.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1 · **Review:** claude

**Acceptance Criteria:**

- [ ] createPost() nunca tiene media en posts_images.
- [ ] PostFactory::new()->withImage() adjunta exactamente una imagen, withImage(0) ninguna, y PostSeeder siembra imágenes al 60 % de los posts.
- [ ] Las suites Post y Admin (que leen imágenes de posts en recursos y páginas de Filament) siguen en verde.

**writeScope:** `app/Modules/Post/Database/Factories/PostFactory.php`, `app/Modules/Post/Database/Seeders/PostSeeder.php`, `app/Modules/Post/Tests/Factories/PostFactoryTest.php`, `tests/Pest.php`, `app/User/Tests/Factories/UserFactoryTest.php`

**validateCommand:** `php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Post/Tests app/Modules/Admin/Tests/Filament/Resources/Posts`

### TASK-004: Ejecutar la suite completa en paralelo

Agregar --parallel al script composer test y documentarlo en docs/development/backend-commands.md (bases por proceso, cómo correr en serie para depurar). Depende de TASK-002/003 porque, con varias bases, los ids de media se repiten entre procesos y escribirían en las mismas carpetas del disco público.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1 · **Review:** claude

**Depends on:** TASK-002, TASK-003

**Acceptance Criteria:**

- [ ] composer test pasa completo en paralelo en local, con el mismo número de tests que en serie.
- [ ] Si un test depende de estado compartido entre procesos (archivos, caché fuera de array), se detecta y se corrige o se documenta antes de cerrar la tarea.
- [ ] La documentación explica la base por proceso y el comando en serie para depurar un fallo.

**writeScope:** `composer.json`, `docs/development/backend-commands.md`

**validateCommand:** `composer test`

### TASK-005: Auditar la suite Admin

Leer app/Modules/Admin/Tests (306 tests, 21 s) y registrar en un informe los casos que detectan el mismo fallo, assertions redundantes, pruebas de comportamiento del framework o de Filament y setup repetido o costoso, con la propuesta concreta y el test que conserva la cobertura. Solo escribe el informe.

**Type:** research · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2 · **Review:** claude

**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] El informe cubre todos los archivos de la suite Admin y clasifica cada hallazgo (redundante, framework, obsoleto, setup).
- [ ] Cada propuesta de eliminación o consolidación cita el test que conserva la cobertura; los tests de autorización, seguridad, validación y persistencia independientes se marcan como conservados.
- [ ] No modifica código de la aplicación ni tests.

**writeScope:** `.ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/audit-admin.md`

**validateCommand:** `test -s .ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/audit-admin.md`

### TASK-006: Auditar las suites de contenido (Comment, Like, Post)

Igual que TASK-005 para app/Modules/Comment/Tests (220 tests, 16.7 s), app/Modules/Like/Tests y app/Modules/Post/Tests.

**Type:** research · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2 · **Review:** claude

**Acceptance Criteria:**

- [ ] El informe cubre todos los archivos de las tres suites y clasifica cada hallazgo.
- [ ] Cada propuesta cita el test que conserva la cobertura; los tests de Security/ y de conteo de consultas se marcan como conservados salvo duplicación exacta.
- [ ] No modifica código de la aplicación ni tests.

**writeScope:** `.ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/audit-content.md`

**validateCommand:** `test -s .ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/audit-content.md`

### TASK-007: Auditar las suites de Auth y User

Igual que TASK-005 para Login, Register, Email, Password, TrustedDevice (220 tests, 14.1 s), TwoFactor, AccountSettings, AccountStatus y User.

**Type:** research · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2 · **Review:** claude

**Acceptance Criteria:**

- [ ] El informe cubre todos los archivos de las nueve suites y clasifica cada hallazgo.
- [ ] Cada propuesta cita el test que conserva la cobertura; los límites de seguridad (OTP, throttle, cuentas suspendidas, dispositivos de confianza) se marcan como conservados salvo duplicación exacta.
- [ ] No modifica código de la aplicación ni tests.

**writeScope:** `.ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/audit-auth-user.md`

**validateCommand:** `test -s .ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/audit-auth-user.md`

### TASK-008: Auditar ActivityLog y las suites globales (tests/Unit, tests/Feature)

Igual que TASK-005 para app/Modules/ActivityLog/Tests (145 tests), tests/Unit y tests/Feature, incluyendo los helpers de tests/Pest.php que ya no use ningún test.

**Type:** research · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2 · **Review:** claude

**Acceptance Criteria:**

- [ ] El informe cubre todos los archivos de las tres suites y lista los helpers de tests/Pest.php sin uso.
- [ ] Cada propuesta cita el test que conserva la cobertura; los tests de privacidad del registro de actividad se marcan como conservados.
- [ ] No modifica código de la aplicación ni tests.

**writeScope:** `.ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/audit-activitylog-global.md`

**validateCommand:** `test -s .ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/audit-activitylog-global.md`

### TASK-009: Extender el plan con las tareas de consolidación aprobadas

Convertir los hallazgos aprobados por el usuario en tareas TASK-0NN de como máximo 3 archivos cada una, con dependencias y comando de validación, y agregarlas a este plan (JSON y markdown).

**Type:** planning · **Effort:** S · **Agent:** general-purpose · **Priority:** P2 · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-007, TASK-008

**Acceptance Criteria:**

- [ ] Cada tarea nueva referencia el hallazgo del informe que la justifica y el test que conserva la cobertura.
- [ ] Ninguna tarea nueva supera 3 archivos y el grafo sigue acíclico; TASK-010 y TASK-011 pasan a depender de ellas.
- [ ] Un hallazgo que el usuario rechace queda registrado como descartado en el informe, sin tarea.

**writeScope:** `.ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/soc-59-remove-legacy-admin-authorization-streamline-tests.json`, `.ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/soc-59-remove-legacy-admin-authorization-streamline-tests.md`

**validateCommand:** `python3 -I -c "import json,sys; json.load(open(sys.argv[1]))" .ulpi/plans/soc-59-remove-legacy-admin-authorization-streamline-tests/soc-59-remove-legacy-admin-authorization-streamline-tests.json`

### TASK-010: Sincronizar CLAUDE.md y AGENTS.md

Documentar los estados withImages()/withImage() de las factories (los tests ya no reciben imágenes de seeding), la ejecución paralela de composer test y la desaparición de la compatibilidad heredada del seeder de autorización.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P3 · **Review:** claude

**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-009

**Acceptance Criteria:**

- [ ] CLAUDE.md describe los estados de imagen de las factories en las secciones de seeders y la ejecución paralela en la sección de tests.
- [ ] Ninguna doc menciona ya renameLegacyPermissions/renameLegacyRoles ni que las factories adjuntan imágenes por defecto.
- [ ] AGENTS.md solo cambia si alguna de sus reglas de tests queda desactualizada.

**writeScope:** `CLAUDE.md`, `AGENTS.md`

**validateCommand:** `grep -c "withImages" CLAUDE.md`

### TASK-011: Gates finales, comparación de duración y resumen en Linear

Ejecutar todos los gates backend, la suite completa en serie (para comparar con la línea base de 74.2 s) y en paralelo, y publicar en SOC-59 el resumen: evidencia, qué se eliminó o consolidó, cobertura conservada y tabla antes/después.

**Type:** chore · **Effort:** S · **Agent:** general-purpose · **Priority:** P3 · **Review:** claude

**Depends on:** TASK-010

**Acceptance Criteria:**

- [ ] Pint, PHPStan, Rector (caché limpia) y la suite completa pasan; si queda algún fallo se informa con su salida.
- [ ] La comparación usa el mismo comando que la línea base (serie) más el tiempo en paralelo, con número de tests y assertions antes y después.
- [ ] El comentario de Linear se publica solo con la aprobación explícita del usuario.

**writeScope:** ninguno (solo verificación y Linear)

**validateCommand:** `composer test`

## Failure Modes

- Un test dependía en silencio de las imágenes aleatorias de las factories: se detecta al correr las suites Post/Admin/User en TASK-002/003 y se le da el estado explícito.
- La ejecución paralela expone estado compartido (archivos en disco, RateLimiter fuera de array): TASK-004 no se cierra hasta corregirlo o documentarlo.
- CI no puede crear las bases por proceso: el usuario root sin contraseña del servicio mysql:8.4 sí puede; si fallara, se revierte --parallel sin afectar el resto del plan.
- Una consolidación oculta un límite de seguridad: cada propuesta exige citar el test que conserva la cobertura y el usuario aprueba los hallazgos antes de TASK-009.

## Ship Cut

TASK-001 sola ya cumple los criterios del seeder. TASK-001 a TASK-004 entregan la compatibilidad eliminada, el fallo intermitente corregido y la suite paralela aunque la auditoría quede para después. La consolidación de tests solo se ejecuta con hallazgos aprobados.

## Test Coverage Map

- Seeder de autorización: AdminAuthorizationSeederTest (catálogo, bundles, re-ejecución).
- Factories: app/User/Tests/Factories/UserFactoryTest.php (nuevo) y app/Modules/Post/Tests/Factories/PostFactoryTest.php.
- Subida de imágenes: app/User/Tests/Crud/UserImageUpdateTest.php, ahora determinista.
- Ejecución paralela: composer test completo.
- Consolidaciones: se definen en TASK-009 a partir de los informes.

## Execution Summary

- Tareas: 11
- Camino crítico: TASK-001 → TASK-005 → TASK-009 → TASK-010 → TASK-011
- Capas:
  1. TASK-001, TASK-002, TASK-003, TASK-006, TASK-007, TASK-008
  2. TASK-004, TASK-005
  3. TASK-009
  4. TASK-010
  5. TASK-011

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | — |
| TASK-004 | TASK-002, TASK-003 |
| TASK-005 | TASK-001 |
| TASK-006 | — |
| TASK-007 | — |
| TASK-008 | — |
| TASK-009 | TASK-005, TASK-006, TASK-007, TASK-008 |
| TASK-010 | TASK-001, TASK-002, TASK-003, TASK-004, TASK-009 |
| TASK-011 | TASK-010 |
