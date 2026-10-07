# Plan: SOC-42: Tratar MediaLibrary como infraestructura global, mover su comando, proteger la limpieza y agregar tests

## Overview

app/MediaLibrary/ no es un módulo de dominio: es una integración transversal con spatie/laravel-medialibrary (usada por Post y User) sin modelos, rutas ni tests. El plan lo trata como código global: el comando de limpieza pasa a app/Console/Commands (auto-descubierto) y se elimina su provider innecesario; el path generator se mueve a la carpeta base global app/Support/MediaLibrary (aprobada por el usuario) y config/media-library.php se actualiza. Además se protege el comando destructivo (prohibido en producción) y se agregan tests globales en tests/Feature/MediaLibrary, y se corrige la documentación que lo clasificaba como dominio.

**Issue:** [SOC-40](https://linear.app/social-hub-ang/issue/SOC-42/refactor-treat-medialibrary-as-global-infrastructure-move-its-command) — Refactor: Treat MediaLibrary as global infrastructure, move its command to app/Console/Commands and add safety guard and tests
**Modo:** HOLD · **Estilo:** Paso a paso: se implementa una tarea y se pausa para revisión (regla .ai/rules/linear.md). · **Review por defecto:** claude,codex,user

## Scope Challenge

Modo HOLD: alcance de la issue sin ampliar ni recortar. Decisiones del usuario: (1) el path generator se mueve a app/Support/MediaLibrary/ (se aprueba la carpeta base nueva app/Support/) en lugar de quedarse en app/MediaLibrary/; (2) el comando se PROHIBE en producción con el trait Prohibitable (sin --force). Hallazgos de la exploración: el provider solo registra un comando que ya se auto-descubre (CreateTestingDatabase y Test viven en app/Console/Commands sin registro); el comando opera sobre storage_path()/public_path(), por lo que sus tests redirigen esas rutas con Application::useStoragePath()/usePublicPath(); no hay uso de MediaLibrary en el frontend ni rutas, así que no hay Wayfinder ni cambios de UI. Review por defecto: claude, codex y revisión del usuario.

## Prerequisites

- SOC-38 y SOC-40 están mergeadas en main (regla vigente: dentro de app/Modules = dominio; fuera = global).
- Se leyeron .ai/rules/{app,backend,linear}.md: sin variables de una letra, docblocks con máximo 3 líneas de prosa, Pint/PHPStan/Rector como gates.
- Illuminate\Console\Prohibitable existe en el framework instalado (Laravel 13) y Application::usePublicPath()/useStoragePath() permiten redirigir rutas en tests.
- composer.json autocarga App\ => app/, por lo que App\Support\ no requiere cambios de autoload; no se modifican dependencias.
- Estrategia del proyecto: crear lo nuevo y luego borrar lo viejo, no un rename directo.

## Non-Goals

- Mover otros dominios (Auth, Friendship, Home, Like, User).
- Cambiar las rutas de almacenamiento de los archivos ya subidos: el path generator debe devolver exactamente las mismas rutas.
- Cambiar la configuración de discos, conversiones o límites de config/media-library.php (solo se actualiza la clase del path generator).
- Limpiar la tabla media desde el comando (se documenta que deja filas huérfanas).
- Desacoplar el path generator de Post (modo EXPANSION, descartado).

## Contracts

- Firma del comando sin cambios: php artisan app:clean-media-library-folders; clase nueva App\Console\Commands\MediaLibraryCleanFoldersCommand (convención {Module}{Purpose}Command de backend.md). En producción el comando está prohibido (Prohibitable) y devuelve FAILURE sin borrar nada; en local/testing se comporta igual que hoy.
- Path generator: App\Support\MediaLibrary\MediaLibraryCustomPathGenerator conserva las rutas posts/{id}/ para Post, {id}/ para el resto, y las subcarpetas conversions/ y responsive-images/.
- Propiedad del registro: TASK-002 (bootstrap/providers.php sin MediaLibrary + MediaLibraryCleanFoldersCommand::prohibit(isProduction()) en AppServiceProvider::boot()); TASK-005 (config/media-library.php apunta a la clase nueva).
- Ubicación global: comandos en app/Console/Commands; clases de soporte compartidas en app/Support (carpeta base aprobada en SOC-42); tests globales en tests/Feature/MediaLibrary (no en app/Modules).
- Los tests del comando nunca tocan storage/ ni public/ reales: redirigen las rutas con useStoragePath()/usePublicPath() a un directorio temporal y restauran el estado (incluida prohibit(false)).

## Existing Code Leverage

- app/Console/Commands/CreateTestingDatabase.php y Test.php como precedente de comandos globales auto-descubiertos.
- Patrón de migración de SOC-38/SOC-40 (crear nuevo, cambiar consumidores, eliminar viejo) y sus planes en .ulpi/plans/.
- Illuminate\Console\Prohibitable (patrón de migrate:fresh) y Application::isProduction().
- Storage::fake('public') y los helpers createPost()/createUser() de tests/Pest.php para los tests de integración.
- Suite Feature de phpunit.xml y Pest.php que ya cubren tests/Feature/** con RefreshDatabase.

## Tasks

### TASK-001: Crear MediaLibraryCleanFoldersCommand en app/Console/Commands con protección de producción

Crear el comando nuevo en App\Console\Commands (namespace de los comandos globales existentes) a partir de MediaLibraryCleanFolders: misma firma app:clean-media-library-folders, misma descripción y mismo comportamiento de borrado, clase renombrada a MediaLibraryCleanFoldersCommand (convención documentada en backend.md). Agrega el trait Illuminate\Console\Prohibitable y, al inicio de handle(), corta con self::FAILURE cuando $this->isProhibited() (decisión del usuario: se prohíbe en producción, sin opción --force). El original queda intacto hasta TASK-003. La activación de la prohibición (MediaLibraryCleanFoldersCommand::prohibit($this->app->isProduction())) la posee TASK-002.

**Type:** refactor
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user

**Acceptance Criteria:**

- El archivo nuevo declara App\Console\Commands\MediaLibraryCleanFoldersCommand con la firma 'app:clean-media-library-folders' y la descripción original; PHPStan nivel max pasa.
- Con la prohibición activa (MediaLibraryCleanFoldersCommand::prohibit()), handle() devuelve self::FAILURE sin leer ni borrar nada; sin prohibición el comportamiento de borrado es idéntico al original.
- Caso límite: mientras exista el original, php artisan list no falla por dos comandos con la misma firma (el último registrado gana) y php artisan about arranca.

**writeScope:**

- `app/Console/Commands/MediaLibraryCleanFoldersCommand.php` (crea)

**validateCommand:** `php -l app/Console/Commands/MediaLibraryCleanFoldersCommand.php && vendor/bin/phpstan analyse app/Console/Commands/MediaLibraryCleanFoldersCommand.php --memory-limit=1G && php artisan list | grep -c clean-media-library-folders && vendor/bin/pint --dirty --format agent`

**Notas:** El comando se auto-descubre desde app/Console/Commands (igual que CreateTestingDatabase y Test, que no se registran en ningún provider). No requiere registro.

### TASK-002: Registrar la prohibición en producción y quitar el provider de MediaLibrary

En AppServiceProvider::boot() agregar MediaLibraryCleanFoldersCommand::prohibit($this->app->isProduction()) (patrón de Laravel para comandos destructivos como migrate:fresh) y quitar MediaLibraryServiceProvider de bootstrap/providers.php. Desde aquí el comando nuevo es el único registrado (el viejo deja de cargarse porque su provider ya no está).

**Type:** refactor
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-001

**Acceptance Criteria:**

- php artisan list muestra app:clean-media-library-folders resuelto por MediaLibraryCleanFoldersCommand y bootstrap/providers.php ya no referencia MediaLibrary.
- Con el entorno en production, ejecutar el comando termina con FAILURE mostrando el mensaje de comando prohibido de Laravel y sin borrar archivos; en local/testing el comando corre normal.
- Caso límite: la app bootea sin errores (php artisan about) y AppServiceProvider conserva intacto el morph map y el resto de su boot().

**writeScope:**

- `bootstrap/providers.php` (modifica)
- `app/Providers/AppServiceProvider.php` (modifica)

**validateCommand:** `! grep -n 'MediaLibrary' bootstrap/providers.php && vendor/bin/phpstan analyse bootstrap/providers.php app/Providers/AppServiceProvider.php --memory-limit=1G && php artisan about --only=environment && vendor/bin/pint --dirty --format agent`

**Notas:** Los hunks de AppServiceProvider/providers.php son líneas ajenas a otras ramas; revisar bloqueos de GitButler antes de commitear.

### TASK-003: Eliminar el provider y el comando viejos de app/MediaLibrary

Borrar app/MediaLibrary/Providers/MediaLibraryServiceProvider.php y app/MediaLibrary/Console/Commands/MediaLibraryCleanFolders.php con sus directorios vacíos. Solo eliminación.

**Type:** refactor
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-002

**Acceptance Criteria:**

- Los 2 archivos y los directorios Providers/ y Console/ ya no existen; php artisan list sigue mostrando app:clean-media-library-folders.
- Caso límite: ningún archivo restante referencia App\MediaLibrary\Console ni App\MediaLibrary\Providers.

**writeScope:**

- `app/MediaLibrary/Providers/MediaLibraryServiceProvider.php` (elimina)
- `app/MediaLibrary/Console/Commands/MediaLibraryCleanFolders.php` (elimina)

**validateCommand:** `! ls app/MediaLibrary/Providers/MediaLibraryServiceProvider.php app/MediaLibrary/Console/Commands/MediaLibraryCleanFolders.php 2>/dev/null && php artisan list | grep -c clean-media-library-folders && ! grep -rnE 'App\\MediaLibrary\\(Console|Providers)' app bootstrap config database routes tests --include='*.php'`

### TASK-004: Crear MediaLibraryCustomPathGenerator en app/Support/MediaLibrary

Crear la copia del path generator en App\Support\MediaLibrary (carpeta base nueva app/Support/ aprobada explícitamente por el usuario para código global compartido que no es comando, provider ni middleware). Mismo código y mismas rutas: Post::MORPH_NAME => Post::PATH/{id}/, resto => {id}/, y subcarpetas conversions/ y responsive-images/. El original queda hasta TASK-006.

**Type:** refactor
**Priority:** P0
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user

**Acceptance Criteria:**

- El archivo nuevo declara App\Support\MediaLibrary\MediaLibraryCustomPathGenerator, implementa Spatie PathGenerator y PHPStan nivel max pasa.
- Caso límite: el diff contra el original es solo namespace; getPath() devuelve 'posts/{id}/' para Post y '{id}/' para cualquier otro model_type.

**writeScope:**

- `app/Support/MediaLibrary/MediaLibraryCustomPathGenerator.php` (crea)

**validateCommand:** `php -l app/Support/MediaLibrary/MediaLibraryCustomPathGenerator.php && vendor/bin/phpstan analyse app/Support/MediaLibrary/MediaLibraryCustomPathGenerator.php --memory-limit=1G && diff <(sed 's/namespace App.MediaLibrary;/X/' app/MediaLibrary/MediaLibraryCustomPathGenerator.php) <(sed 's/namespace App.Support.MediaLibrary;/X/' app/Support/MediaLibrary/MediaLibraryCustomPathGenerator.php) && vendor/bin/pint --dirty --format agent`

**Notas:** composer.json autocarga App\ => app/, por lo que App\Support\ funciona sin cambios de autoload.

### TASK-005: Apuntar config/media-library.php al path generator nuevo

Cambiar el import y el valor 'path_generator' de config/media-library.php a App\Support\MediaLibrary\MediaLibraryCustomPathGenerator. Sin otros cambios de configuración (discos, conversiones y límites intactos).

**Type:** refactor
**Priority:** P1
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-004

**Acceptance Criteria:**

- config('media-library.path_generator') devuelve App\Support\MediaLibrary\MediaLibraryCustomPathGenerator y la clase se resuelve.
- Caso límite: subir una imagen a un Post sigue guardándola en 'posts/{media id}/' (verificado por tests en TASK-009) y php artisan config:show media-library no cambia ninguna otra clave.

**writeScope:**

- `config/media-library.php` (modifica)

**validateCommand:** `php artisan config:show media-library.path_generator && ! grep -n 'App.MediaLibrary' config/media-library.php && vendor/bin/pint --dirty --format agent`

### TASK-006: Eliminar el path generator viejo y el directorio app/MediaLibrary

Borrar app/MediaLibrary/MediaLibraryCustomPathGenerator.php; con TASK-003 el directorio app/MediaLibrary queda vacío y se elimina. Solo eliminación.

**Type:** refactor
**Priority:** P2
**Effort:** XS
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-003, TASK-005

**Acceptance Criteria:**

- app/MediaLibrary/ ya no existe y la app sigue generando las mismas rutas de almacenamiento.
- Caso límite: grep 'App\\MediaLibrary\\' sobre app/, bootstrap/, config/, database/, routes/ y tests/ no devuelve coincidencias.

**writeScope:**

- `app/MediaLibrary/MediaLibraryCustomPathGenerator.php` (elimina)

**validateCommand:** `! test -d app/MediaLibrary && ! grep -rn 'App\\MediaLibrary\\' app bootstrap config database routes tests --include='*.php' && composer phpstan`

### TASK-007: Tests del comando MediaLibraryCleanFoldersCommand (incluida la prohibición en producción)

Crear tests/Feature/MediaLibrary/MediaLibraryCleanFoldersCommandTest.php (Pest). Usa directorios temporales con $this->app->useStoragePath() y $this->app->usePublicPath() para NO tocar storage/ ni public/ reales, y restaura las rutas originales al terminar (afterEach). Casos: borra carpetas y archivos de storage/app/public y public/storage e informa los totales; avisa 'The path ... does not exist' cuando una ruta falta; con MediaLibraryCleanFoldersCommand::prohibit() devuelve FAILURE y no borra nada; con el entorno en production, el boot de AppServiceProvider deja el comando prohibido. afterEach restablece prohibit(false).

**Type:** test
**Priority:** P1
**Effort:** M
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-002

**Acceptance Criteria:**

- El comando borra todas las carpetas y archivos de las dos rutas temporales y el output informa 'Total deleted folders' y 'Total deleted files' con los números exactos.
- Caso de falla: con la prohibición activa el comando termina con FAILURE y los archivos temporales siguen existiendo; con la ruta inexistente muestra el aviso y termina con SUCCESS sin errores.
- Caso límite: ningún test lee ni escribe en el storage/ o public/ reales del proyecto (rutas redirigidas a un directorio temporal) y la prohibición no se filtra a otros tests (afterEach).

**writeScope:**

- `tests/Feature/MediaLibrary/MediaLibraryCleanFoldersCommandTest.php` (crea)

**validateCommand:** `vendor/bin/pest tests/Feature/MediaLibrary/MediaLibraryCleanFoldersCommandTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** Tests globales en tests/Feature (ya cubierto por la suite Feature de phpunit.xml y Pest.php); no van en app/Modules/. Si los hunks de tests/Pest.php se tocaran, revisar bloqueos de GitButler.

### TASK-008: Tests de MediaLibraryCustomPathGenerator

Crear tests/Feature/MediaLibrary/MediaLibraryCustomPathGeneratorTest.php: getPath() devuelve 'posts/{id}/' para model_type 'post' y '{id}/' para otros tipos (user, comment); getPathForConversions() y getPathForResponsiveImages() agregan 'conversions/' y 'responsive-images/' al path base. Usa instancias de Media sin persistir.

**Type:** test
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-004

**Acceptance Criteria:**

- getPath() cubre Post (posts/{id}/) y un tipo distinto (user) devolviendo '{id}/'; las rutas de conversiones y responsive terminan con el separador de directorio.
- Caso límite: un model_type desconocido o vacío cae en el default '{id}/' sin lanzar excepción.

**writeScope:**

- `tests/Feature/MediaLibrary/MediaLibraryCustomPathGeneratorTest.php` (crea)

**validateCommand:** `vendor/bin/pest tests/Feature/MediaLibrary/MediaLibraryCustomPathGeneratorTest.php && vendor/bin/pint --dirty --format agent`

### TASK-009: Tests de integración: ruta real de las imágenes de Post y User

Crear tests/Feature/MediaLibrary/MediaStoragePathTest.php con Storage::fake('public'): config('media-library.path_generator') es la clase de App\Support\MediaLibrary; subir una imagen a un Post la guarda en 'posts/{media id}/{archivo}'; subir una foto de perfil a un User la guarda en '{media id}/{archivo}'. Protege la compatibilidad con los archivos ya almacenados.

**Type:** test
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-005

**Acceptance Criteria:**

- El archivo de un Post existe en el disco falso en 'posts/{media id}/' y el de un User en '{media id}/', y config('media-library.path_generator') apunta a la clase nueva.
- Caso límite: ambas rutas coinciden con las que generaba el path generator viejo (mismas rutas, sin migración de archivos).

**writeScope:**

- `tests/Feature/MediaLibrary/MediaStoragePathTest.php` (crea)

**validateCommand:** `vendor/bin/pest tests/Feature/MediaLibrary/MediaStoragePathTest.php && vendor/bin/pint --dirty --format agent`

### TASK-010: Actualizar CLAUDE.md: MediaLibrary como infraestructura global

En CLAUDE.md: sacar MediaLibrary de la lista de dominios y documentar la sección de código global (app/Console/Commands, app/Support/MediaLibrary) incluyendo que app/Support/ es una carpeta base global aprobada en SOC-42; corregir 'Media uploads' (MediaLibraryCustomPathGenerator en app/Support/MediaLibrary/, y MediaLibraryCleanFoldersCommand prohibido en producción).

**Type:** docs
**Priority:** P2
**Effort:** S
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-006

**Acceptance Criteria:**

- CLAUDE.md ya no lista MediaLibrary como dominio, documenta app/Support/MediaLibrary y app/Console/Commands como global y usa los nombres reales de las clases.
- Caso límite: grep 'app/MediaLibrary' y 'CleanMediaLibraryFolders' sobre CLAUDE.md no devuelve coincidencias.

**writeScope:**

- `CLAUDE.md` (modifica)

**validateCommand:** `! grep -nE 'app/MediaLibrary|CleanMediaLibraryFolders|App\\MediaLibrary' CLAUDE.md && grep -n 'app/Support' CLAUDE.md`

**Notas:** GitButler puede bloquear hunks de CLAUDE.md pegados a líneas modificadas por otras ramas aplicadas; revisar con git blame antes de commitear.

### TASK-011: Actualizar docs/architecture/backend.md y AGENTS.md

backend.md: quitar MediaLibrary de la lista de dominios de la arquitectura vieja, agregar a la sección 0 qué vive fuera de app/Modules (app/Console/Commands, app/Support, providers/middleware) con MediaLibrary como ejemplo de integración de paquete, y confirmar que el ejemplo de comando MediaLibraryCleanFoldersCommand ya es real. AGENTS.md solo se edita si menciona MediaLibrary como dominio o necesita registrar que app/Support/ fue aprobada como carpeta base.

**Type:** docs
**Priority:** P2
**Effort:** S
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-006

**Acceptance Criteria:**

- backend.md explica dónde va el código global (Console/Commands, Support, Providers, Http) con MediaLibrary como ejemplo y ya no lo lista como dominio pendiente de migrar.
- Caso límite: ni AGENTS.md ni backend.md contienen 'app/MediaLibrary' ni 'App\\MediaLibrary'; Prettier pasa sobre backend.md.

**writeScope:**

- `docs/architecture/backend.md` (modifica)
- `AGENTS.md` (modifica)

**validateCommand:** `! grep -nE 'app/MediaLibrary|App\\MediaLibrary' AGENTS.md docs/architecture/backend.md && npx prettier --check docs/architecture/backend.md`

### TASK-012: Actualizar el skill social-hub-conventions y revisar .ai/rules

SKILL.md (fuente canónica): agregar la clasificación de código global (app/Console/Commands, app/Support) y que MediaLibrary ya no es un dominio. .ai/rules/app.md y backend.md solo se editan si contienen texto que asuma la ubicación vieja. Verificar los symlinks de .agents/, .windsurf/, .codex/ y .ai/skills/.

**Type:** docs
**Priority:** P2
**Effort:** XS
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-006

**Acceptance Criteria:**

- SKILL.md describe app/Console/Commands y app/Support como ubicaciones globales y no lista MediaLibrary como dominio.
- Caso límite: los 4 symlinks siguen siendo symlinks hacia ../../.claude/skills/social-hub-conventions y .ai/rules/* no queda con texto contradictorio.

**writeScope:**

- `.claude/skills/social-hub-conventions/SKILL.md` (modifica)
- `.ai/rules/app.md` (modifica)
- `.ai/rules/backend.md` (modifica)

**validateCommand:** `! grep -n 'app/MediaLibrary' .claude/skills/social-hub-conventions/SKILL.md && ls -la .agents/skills/social-hub-conventions .windsurf/skills/social-hub-conventions .codex/skills/social-hub-conventions .ai/skills/social-hub-conventions | grep -c -- '->'`

**Notas:** No se usa record-rule: solo se edita texto existente.

### TASK-013: QA backend: Pint, PHPStan, Rector y suites

Gate backend completo: vendor/bin/pint --dirty --format agent, composer phpstan, composer rector-dry (y composer rector tras revisar el diff; solo correcciones mecánicas) y la suite completa (php artisan test --compact, varias corridas).

**Type:** chore
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude,codex,user
**Depends on:** TASK-006, TASK-007, TASK-008, TASK-009

**Acceptance Criteria:**

- Pint, PHPStan (nivel max) y Rector (dry-run sin cambios pendientes) pasan.
- Caso límite: la suite completa pasa en al menos 3 corridas seguidas y ningún test dejó archivos fuera de directorios temporales (git status limpio de storage/ y public/).

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `vendor/bin/pint --dirty --format agent && composer phpstan && composer rector-dry && php artisan test --compact`

**Notas:** writeScope vacío: solo correcciones mecánicas que los gates exijan.

### TASK-014: Barrido final de referencias viejas y symlinks

Verificación solo lectura: no quedan referencias a App\MediaLibrary\, app/MediaLibrary ni a los nombres inexistentes (CleanMediaLibraryFolders, MediaLibraryServiceProvider) en código, config, docs, skill ni reglas; los symlinks del skill siguen siendo symlinks.

**Type:** chore
**Priority:** P3
**Effort:** XS
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-010, TASK-011, TASK-012, TASK-013

**Acceptance Criteria:**

- grep -rnE 'App\\MediaLibrary\\|app/MediaLibrary|CleanMediaLibraryFolders|MediaLibraryServiceProvider' sobre app, bootstrap/providers.php, config, database, routes, tests, docs, CLAUDE.md, AGENTS.md, .claude, .ai y .github devuelve vacío.
- Caso límite: git status/but status no muestra archivos huérfanos de app/MediaLibrary ni copias físicas del skill.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `! grep -rnE 'App\\MediaLibrary\\|app/MediaLibrary|CleanMediaLibraryFolders|MediaLibraryServiceProvider' app bootstrap/providers.php config database routes tests docs CLAUDE.md AGENTS.md .claude .ai .github`

**Notas:** No incluir .ulpi/plans/soc-42-* en el barrido: el plan menciona las rutas viejas por diseño.

### TASK-015: Cierre: PR y comentario resumen en SOC-42 (lo redacta el usuario)

Definition of Done: el usuario abre el PR con .github/PULL_REQUEST_TEMPLATE. Si el usuario lo pide, se redacta el comentario resumen para SOC-42 con los cambios, los hallazgos (comando destructivo prohibido en producción) y los gates ejecutados. No se publica nada automáticamente.

**Type:** chore
**Priority:** P3
**Effort:** XS
**Agent:** general-purpose
**Review:** claude,codex,user
**Depends on:** TASK-014

**Acceptance Criteria:**

- El PR (abierto por el usuario) enlaza SOC-42 y SOC-42 tiene un comentario resumen con cambios, hallazgos y gates ejecutados cuando el usuario lo pide.
- Caso límite: si algún gate se omitió o falló se declara explícitamente; no se mergea ni activa auto-merge sin pedido.

**writeScope:**

- (sin archivos propios; ver notas)

**validateCommand:** `gh pr view --json url,title`

**Notas:** Acción de salida (publica contenido): solo con confirmación explícita del usuario.

## Failure Modes

- Dos comandos con la misma firma durante la coexistencia (TASK-001 a 003): Laravel conserva el último registrado; TASK-002 quita el provider antes de borrar el viejo para evitar ambigüedad.
- Un test del comando que borre storage/ o public/ reales: se evita redirigiendo las rutas a un directorio temporal y verificándolo en el criterio de TASK-007 y TASK-013.
- La prohibición se filtra a otros tests (estado estático de Prohibitable): afterEach llama prohibit(false).
- Cambiar el path generator por error movería los archivos nuevos a otra ruta y dejaría los ya subidos inaccesibles: TASK-004 compara contra el original y TASK-009 fija las rutas de Post y User.
- config/media-library.php apuntando a una clase inexistente rompe cualquier subida de media: TASK-005 verifica config('media-library.path_generator') y TASK-009 sube archivos reales (fake).
- app/Support/ es una carpeta base nueva: solo se crea con la aprobación explícita del usuario, ya registrada en este plan, y debe quedar documentada (TASK-010/011).
- GitButler puede bloquear hunks de CLAUDE.md, tests/Pest.php o AppServiceProvider si otra rama aplicada modificó líneas adyacentes; revisar con git blame antes de commitear.
- El comando seguirá dejando filas huérfanas en la tabla media tras limpiar el disco; se documenta como limitación conocida.

## Ship Cut

Si la ejecución se detiene a mitad de camino: tras TASK-001..003 el comando ya vive en app/Console/Commands, protegido y sin provider; tras TASK-004..006 el path generator ya vive en app/Support y app/MediaLibrary desaparece. Los tests (TASK-007..009) y la documentación (TASK-010..012) son independientes entre sí y pueden entregarse después de TASK-006; la Definition of Done exige tenerlos todos antes de cerrar SOC-42.

## Test Coverage Map

| Superficie | Tarea |
| --- | --- |
| Comando: borrado, totales y rutas inexistentes | TASK-007 |
| Comando: prohibido en producción (sin borrar nada) | TASK-007 |
| MediaLibraryCustomPathGenerator: getPath/conversions/responsive | TASK-008 |
| Ruta real de las imágenes de Post y User | TASK-009 |
| Configuración apunta al generador nuevo | TASK-009 |

## Execution Summary

- **Tareas:** 15
- **Camino crítico (7):** TASK-001 → TASK-002 → TASK-003 → TASK-006 → TASK-010 → TASK-014 → TASK-015
- **Capas de ejecución (paralelizables dentro de cada capa):**
  - Capa 0: TASK-001, TASK-004
  - Capa 1: TASK-002, TASK-005, TASK-008
  - Capa 2: TASK-003, TASK-007, TASK-009
  - Capa 3: TASK-006
  - Capa 4: TASK-010, TASK-011, TASK-012, TASK-013
  - Capa 5: TASK-014
  - Capa 6: TASK-015

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-002 |
| TASK-004 | — |
| TASK-005 | TASK-004 |
| TASK-006 | TASK-003, TASK-005 |
| TASK-007 | TASK-002 |
| TASK-008 | TASK-004 |
| TASK-009 | TASK-005 |
| TASK-010 | TASK-006 |
| TASK-011 | TASK-006 |
| TASK-012 | TASK-006 |
| TASK-013 | TASK-006, TASK-007, TASK-008, TASK-009 |
| TASK-014 | TASK-010, TASK-011, TASK-012, TASK-013 |
| TASK-015 | TASK-014 |
