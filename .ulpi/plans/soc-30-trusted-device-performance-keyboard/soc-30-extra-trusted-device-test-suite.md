# Plan: SOC-30 — Extra: Suite, coverage y estructura de entorno TrustedDevice

**Tipo:** Extra complementario de SOC-30. **Modo:** EXPANSION. **Revisión predeterminada:** codex.

## Overview

El plan extra agregó una suite PHPUnit llamada TrustedDevice para ejecutar las pruebas del módulo por nombre, sin incluirlas dos veces en Feature. También ordenó `.env.example` y `.env.ci`, cubrió el comando de purga, documentó coverage y ubicó el comando dentro del módulo. Esta expansión incorpora la revisión de Pullfrog y la falla de Laravel Doctor en el PR #31: comprobar con una regresión que Eloquent excluye los dispositivos revocados de las estadísticas, actualizar `league/commonmark` frente a dos advisories y clasificar `APP_ENV=ci` correctamente para Doctor.

## Scope Challenge

`phpunit.xml` ya separa los 79 tests TrustedDevice de Feature y el comando `php artisan test --testsuite=TrustedDevice --compact` pasa. La expansión de entorno compara tres archivos: `.env.testing.example` usa secciones con separadores y explicación; `.env.example` y `.env.ci` tienen poca agrupación. Los workflows copian `.env.ci` a `.env`, así que debe seguir siendo mínimo. Una corrida previa de coverage con una selección limitada a TrustedDevice ejecutó 79 tests y reportó `TrustedDevicePurge` con 0.0%; el comando `herd coverage` falló al resolver PHP antes de llegar a Pest, por lo que se ejecutó Pest con el PHP de Herd y Xdebug habilitado. La guía usa ahora el comando sin path para ejecutar toda la suite; `phpunit.xml` incluye `app` como fuente medida.

Se confirmó limitar la actualización de los archivos de entorno a estructura y comentarios, preservando las claves, su estado activo o comentado y sus valores actuales. No se copiarán claves entre archivos. Se mantienen el modo EXPANSION y la revisión codex.

Pullfrog señaló que los conteos de estadísticas podrían incluir dispositivos revocados. `TrustedDevice` usa `SoftDeletes`, y `User::trustedDevices()` no llama a `withTrashed()`, por lo que Eloquent aplica `whereNull(deleted_at)` automáticamente; el contador `revoked` consulta eventos por separado. El workflow falló por `composer audit`: `league/commonmark` 2.10.1 tiene dos advisories cuyo parche es 2.10.2. Los avisos sobre bootstrap y cola aparecen porque Doctor trata el nombre de entorno `ci` como producción al no estar configurado.

## Prerequisites

- El módulo tiene pruebas bajo `app/Auth/Modules/TrustedDevice/Tests`.
- El proyecto usa PHPUnit 13.3.4, Pest 5.2.1 y Laravel 13.32.0; la configuración vigente es `phpunit.xml`.
- `.env.testing.example` es la referencia de formato; los workflows de CI copian `.env.ci` a `.env`.
- `TrustedDevicePurge` valida días positivos, permite usar el valor configurado o `--days`, y elimina permanentemente solo dispositivos soft-deleted anteriores al corte. Se ubicó en `app/Auth/Modules/TrustedDevice/Console/Commands/TrustedDevicePurge.php`; `AuthServiceProvider` lo registra y programa.
- Los agentes ejecutan coverage de Pest con Herd; la corrida previa confirmó que el wrapper puede fallar antes de iniciar Pest y que el informe sigue el alcance de `<source>` en `phpunit.xml`.

## Non-Goals

- Cambiar pruebas existentes ajenas a esta expansión o convenciones Pest; agregar dependencias nuevas o actualizar paquetes ajenos a los advisories detectados.
- Crear un comando Artisan personalizado para aceptar el argumento posicional `TrustedDevice`.
- Cambiar, agregar o eliminar claves de entorno, cambiar sus valores, activar ejemplos comentados o copiar claves entre los tres archivos.

## Contracts

- La suite TrustedDevice apunta a `app/Auth/Modules/TrustedDevice/Tests`.
- La suite Feature contiene solo `tests/Feature`, para no registrar dos veces los tests del módulo.
- Comando de selección: `php artisan test --testsuite=TrustedDevice --compact`; `--list-suites` muestra TrustedDevice como suite separada.
- `.env.example` y `.env.ci` adoptan los encabezados separadores y comentarios de propósito de `.env.testing.example`, adaptados a sus propias claves.
- En cada archivo de entorno se conservan exactamente las claves, valores y estado activo o comentado; `.env.ci` sigue siendo mínimo para CI.
- El comando de coverage para el proyecto es `herd coverage ./vendor/bin/pest --coverage`, sin ruta de módulo; ejecuta la suite completa y mide las fuentes configuradas en `phpunit.xml`.
- La purga elimina solo filas soft-deleted cuyo `deleted_at` sea anterior al corte; opciones inválidas no borran datos.
- `AGENTS.md` documenta el comando de coverage para toda la suite y la alternativa con PHP de Herd y Xdebug si el wrapper `herd coverage` no llega a invocar Pest.
- `TrustedDevicePurge` vive bajo `app/Auth/Modules/TrustedDevice/Console/Commands`; conserva el nombre Artisan, opciones y comportamiento, y `AuthServiceProvider` registra la nueva clase.
- Las estadísticas de dispositivos siguen excluyendo soft-deleted por el scope global Eloquent; el historial de revocación se cuenta desde eventos.
- `league/commonmark` queda en una versión parcheada que resuelve los advisories reportados sin ignorarlos ni ampliar restricciones innecesariamente.
- Laravel Doctor trata `ci` y `testing` como entornos no productivos y mantiene controles de producción para `production` y `staging`.
- Los agentes ejecutan `composer doctor` como último gate de una tarea backend, después de implementar y pasar los demás gates. `composer doctor-ci` es el comando del workflow con anotaciones GitHub.

## Existing Code Leverage

- `phpunit.xml` ya declara las suites Unit y Feature.
- Laravel TestCommand reenvía las opciones de selección a Pest/PHPUnit.
- `php artisan test --testsuite=Unit --list-test-files` confirma que Artisan reenvía `--testsuite`.
- `.env.testing.example` ya documenta grupos con separadores y comentarios explicativos.
- `.github/workflows/backend-code-quality.yml` y `frontend-code-quality.yml` copian `.env.ci` como entorno de CI.
- `app/Auth/Modules/TrustedDevice/Console/Commands/TrustedDevicePurge.php` contiene el comando; `AuthServiceProvider` es su punto de registro y `TrustedDeviceFactory` con las utilidades Pest permiten probar el borrado.
- `phpunit.xml` define el origen de cobertura; `AGENTS.md` contiene las instrucciones compartidas para agentes.
- `TrustedDeviceDashboardCache::buildStats()` consulta `trustedDevices()`; `SoftDeletingScope::apply()` agrega `whereNull(deleted_at)` automáticamente.
- `composer.lock` resuelve `league/commonmark` 2.10.3; Composer ya no informa los dos advisories.
- Laravel Doctor permite mapear nombres de entorno en `config/doctor.php`; `.env.ci` define `APP_ENV=ci` y usa cola síncrona deliberadamente.

## Tasks

### TASK-001: Registrar una suite dedicada para TrustedDevice

**Description:** Mover el directorio de pruebas del módulo fuera de Feature y registrarlo en una suite TrustedDevice para ejecutar el módulo por nombre.

**Type:** chore  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- `phpunit.xml`

**Acceptance Criteria:**

- `php artisan test --list-suites` muestra TrustedDevice como suite separada de Feature.
- `php artisan test --testsuite=TrustedDevice --compact` ejecuta la suite TrustedDevice y pasa.
- La carpeta del módulo ya no aparece dentro de Feature, evitando pruebas duplicadas en la ejecución global.

**validateCommand:** `php artisan test --list-suites && php artisan test --testsuite=TrustedDevice --compact`

### TASK-002: Documentar y agrupar las variables de entorno

**Description:** Reorganizar `.env.example` y `.env.ci` con secciones separadas y comentarios de propósito siguiendo el estilo de `.env.testing.example`, agrupando las claves que ya existen según su función y sin modificar sus asignaciones.

**Type:** chore  
**Priority:** P1  
**Effort:** S  
**Agent:** `general-purpose`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- `.env.example`
- `.env.ci`

**Acceptance Criteria:**

- Los dos archivos usan encabezados de sección con separadores y comentarios que explican la finalidad de cada grupo, siguiendo el formato de `.env.testing.example`.
- `.env.example` agrupa por separado aplicación, localización, mantenimiento, servidor local, hashing, logs, base de datos, sesiones, servicios de broadcast/archivos/colas, caché, Memcached, Redis, correo, AWS, frontend y configuración propia de Social Hub. `.env.ci` agrupa aplicación, base de datos, servicios de ejecución (caché, cola y sesión), correo, broadcast/archivos y logs.
- Se conservan exactamente las claves, sus valores y su estado activo o comentado en cada archivo; no se copian claves de testing o locales a `.env.ci`.
- `.env.ci` continúa siendo el entorno CI mínimo usado por los workflows y las asignaciones mantienen sintaxis válida sin claves duplicadas.

**validateCommand:** `rtk php -r '$files = [".env.example", ".env.ci"]; foreach ($files as $file) { $keys = []; foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) { if ($line === "" || str_starts_with(ltrim($line), "#")) { continue; } if (! preg_match("/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/", $line, $matches)) { fwrite(STDERR, "Invalid assignment in {$file}" . PHP_EOL); exit(1); } if (isset($keys[$matches[1]])) { fwrite(STDERR, "Duplicate key in {$file}: {$matches[1]}" . PHP_EOL); exit(1); } $keys[$matches[1]] = true; } }'`

### TASK-003: Cubrir el comando de purga de TrustedDevice

**Description:** Añadir pruebas Pest para la retención configurada, el override `--days` y la validación de valores inválidos, verificando los registros que permanecen y los que se eliminan permanentemente.

**Type:** test  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** TASK-001

**writeScope:**

- Create: `app/Auth/Modules/TrustedDevice/Tests/Commands/TrustedDevicePurgeTest.php`

**Acceptance Criteria:**

- Con la retención configurada, solo se purgan dispositivos soft-deleted anteriores al corte; dispositivos activos, recientes o justo en el límite permanecen.
- `--days` reemplaza la configuración para calcular el corte y el comando informa el número purgado con estado exitoso.
- Con cero o días negativos, el comando devuelve estado inválido, informa el error y no elimina registros.

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Commands/TrustedDevicePurgeTest.php`

### TASK-004: Documentar coverage de Pest para agentes

**Description:** Añadir a las instrucciones de testing para agentes el comando Herd que genera coverage para toda la suite y las notas necesarias para interpretar o desbloquear el reporte.

**Type:** docs  
**Priority:** P1  
**Effort:** S  
**Agent:** `general-purpose`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- Modify: `AGENTS.md`

**Acceptance Criteria:**

- `AGENTS.md` documenta literalmente `herd coverage ./vendor/bin/pest --coverage` para ejecutar coverage de toda la suite, sin pasar una ruta de módulo.
- La guía aclara que el comando sin ruta ejecuta toda la suite y que los archivos fuente medidos los determina `<source>` en `phpunit.xml` (actualmente `app`).
- Si `herd coverage` falla antes de arrancar Pest al resolver PHP, la guía indica usar el PHP de Herd con Xdebug/`XDEBUG_MODE=coverage` y la configuración debug de Herd, sin presentar ese fallo del wrapper como un fallo de Pest.

**validateCommand:** `rtk rg -n 'herd coverage ./vendor/bin/pest --coverage|XDEBUG_MODE=coverage|phpunit.xml' AGENTS.md`

### TASK-005: Mover el comando de purga al módulo TrustedDevice

**Description:** Reubicar el comando Artisan de purga en la estructura del módulo y actualizar el proveedor que lo registra, sin cambiar su firma pública ni su comportamiento.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** TASK-003

**writeScope:**

- Move: `app/Auth/Console/Commands/TrustedDevicePurge.php` → `app/Auth/Modules/TrustedDevice/Console/Commands/TrustedDevicePurge.php`
- Modify: `app/Auth/Providers/AuthServiceProvider.php`

**Acceptance Criteria:**

- El comando usa el namespace del módulo y `AuthServiceProvider` registra la nueva clase; la clase anterior desaparece.
- `trusted-devices:purge`, su opción `--days`, su programación diaria y sus salidas conservan el contrato actual.
- La prueba existente pasa y Artisan sigue listando la firma; no se registra dos veces.

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Commands/TrustedDevicePurgeTest.php && rtk php artisan list --raw | rtk rg '^trusted-devices:purge'`

### TASK-006: Referenciar constantes desde la clase que las declara

**Description:** Importar la clase Symfony que declara `SUCCESS` e `INVALID` para evitar referenciar sus constantes heredadas mediante `IlluminateConsoleCommand`.

**Type:** chore  
**Priority:** P2  
**Effort:** XS  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** TASK-003

**writeScope:**

- Modify: `app/Auth/Modules/TrustedDevice/Tests/Commands/TrustedDevicePurgeTest.php`

**Acceptance Criteria:**

- El test importa `Symfony\Component\Console\Command\Command` al comparar códigos de salida.
- Las comprobaciones siguen validando los mismos códigos de éxito e inválido y la prueba continúa pasando.

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Commands/TrustedDevicePurgeTest.php`

### TASK-007: Afirmar el scope de SoftDeletes en las estadísticas

**Description:** Añadir un test de regresión que contraste un dispositivo vigente con uno revocado y confirme que el scope global de Eloquent excluye el revocado de las estadísticas, mientras el evento de revocación sigue contándose.

**Type:** test  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- Modify: `app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php`

**Acceptance Criteria:**

- Con un dispositivo vigente y otro soft-deleted, `total`, estados, agregados recientes y tipo de dispositivo incluyen solo el vigente.
- El contador `revoked` refleja el evento asociado, confirmando que se basa en el historial y no en incluir dispositivos soft-deleted.
- El test mantiene intacta la forma de las estadísticas y pasa junto con la suite afectada.

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php`

### TASK-008: Actualizar league/commonmark con los parches de seguridad

**Description:** Actualizar el paquete bloqueado `league/commonmark` a la versión parcheada 2.10.2 o posterior dentro del constraint transitivo actual de Laravel y comprobar ambos advisories con Composer.

**Type:** chore  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- Modify: `composer.lock`

**Acceptance Criteria:**

- `composer.lock` resuelve `league/commonmark` a 2.10.2 o posterior, compatible con la restricción declarada por Laravel.
- Ningún advisory se ignora o silencia; `composer audit --locked` no reporta estos advisories.
- El lockfile conserva consistencia y no actualiza dependencias ajenas sin necesidad.

**validateCommand:** `rtk composer show --locked league/commonmark && rtk composer audit --locked`

### TASK-009: Configurar expectativas de Laravel Doctor para CI

**Description:** Crear la configuración publicada de Doctor y clasificar `ci` y `testing` como entornos no productivos para que las advertencias de cache y cola no apliquen a `.env.ci`.

**Type:** chore  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- Create: `config/doctor.php`

**Acceptance Criteria:**

- `ci` y `testing` usan las expectativas locales; `production` y `staging` mantienen expectativas de producción.
- `QUEUE_CONNECTION=sync` y el bootstrap sin cache de `.env.ci` no se tratan como errores de configuración de producción.
- No se omiten diagnósticos de Doctor ni se cambian los valores del entorno CI.

**validateCommand:** `rtk php artisan config:show doctor.environments`

### TASK-010: Documentar Laravel Doctor como gate de cierre

**Description:** Añadir a las instrucciones de agentes y a la referencia de comandos backend que Doctor se ejecuta al finalizar todos los cambios y gates, además de explicar el comando que usa CI.

**Type:** docs  
**Priority:** P1  
**Effort:** S  
**Agent:** `general-purpose`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- Modify: `AGENTS.md`
- Modify: `docs/development/backend-commands.md`

**Acceptance Criteria:**

- Ambas guías indican que `composer doctor` es el último gate de una tarea backend, después de tests, análisis y formatters.
- La documentación distingue `composer doctor` local de `composer doctor-ci`, que emplea el workflow de GitHub.
- No se presenta el Doctor de CI como diagnóstico que se ejecuta antes de completar la implementación.

**validateCommand:** `rtk rg -n -i 'doctor|último|final|después' AGENTS.md docs/development/backend-commands.md`

### TASK-011: Ejecutar los gates y Laravel Doctor al cierre

**Description:** Ejecutar las pruebas afectadas, gates backend y auditoría del lockfile. Una vez cerrados los cambios, ejecutar `composer doctor` y `composer doctor-ci` como las últimas verificaciones.

**Type:** test  
**Priority:** P1  
**Effort:** M  
**Agent:** `general-purpose`  
**Review:** `codex`  
**Depends on:** TASK-007, TASK-008, TASK-009, TASK-010

**writeScope:** `[]` — solo validación.

**Acceptance Criteria:**

- La prueba de dashboard, Pint, Rector dry-run y Rector, PHPStan y `composer audit --locked` pasan.
- `composer doctor` y `composer doctor-ci` se ejecutan después de todos los cambios y gates, sin ejecutar otro gate después de Doctor.
- Doctor termina correctamente; cualquier limitación de servicio local se reporta separada de la salida de CI.

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php && rtk vendor/bin/pint --dirty --format agent && rtk composer rector-dry && rtk composer rector && rtk composer phpstan && rtk composer audit --locked && rtk composer doctor && rtk composer doctor-ci`

## Failure Modes

- Agregar `whereNull('deleted_at')` redundante a las métricas no corrige un defecto: el scope global de SoftDeletes ya filtra esas consultas. El test fijará este contrato y demostrará por qué el comentario de Pullfrog es falso positivo.
- Ignorar o silenciar advisories deja vulnerable una dependencia; el lockfile debe resolver ambos a 2.10.2 o posterior dentro de la restricción existente.
- Si `ci` queda como entorno desconocido, Doctor seguirá aplicando advertencias de producción a la cola síncrona y al bootstrap no cacheado del workflow.
- Ejecutar Doctor antes de acabar las implementaciones puede validar archivos o dependencias en estado intermedio; reservar `composer doctor` y `composer doctor-ci` para la verificación final.

- Si la carpeta del módulo sigue también dentro de Feature, la ejecución global puede registrar los mismos tests dos veces.
- Si el nombre de suite o su ruta están mal, `--testsuite=TrustedDevice` no podrá seleccionar las pruebas del módulo.
- El argumento posicional `TrustedDevice` se trata como un archivo y no sustituye a `--testsuite`.
- Cambiar valores de entorno mientras se reordenan líneas altera la configuración local o de CI.
- Activar accidentalmente claves comentadas o copiar claves de testing amplía el comportamiento de `.env.ci`.
- Una prueba de purga que no controle el tiempo o no distinga activos, soft-deleted recientes y anteriores al corte puede pasar sin proteger el límite de retención.
- El wrapper `herd coverage` falló en esta máquina antes de iniciar Pest por la resolución del binario PHP; la alternativa directa requiere seleccionar el PHP de Herd con su configuración Xdebug de coverage.
- El reporte global mide las fuentes incluidas por `phpunit.xml`; como incluye `app`, abarca los módulos de la aplicación y no representa un porcentaje exclusivo de TrustedDevice.
- Mover la clase sin actualizar `AuthServiceProvider` rompe el registro y la tarea programada; cambiar la firma Artisan rompe las invocaciones existentes.

## Ship Cut

Completar cuando los entregables previos sigan pasando, el test descarte el falso positivo de Pullfrog, Composer no reporte esos advisories, Laravel Doctor aplique expectativas adecuadas a CI, `AGENTS.md` y `backend-commands.md` indiquen cuándo ejecutar Doctor, y los gates de backend terminen con Doctor exitoso.

## Test Coverage Map

- PHPUnit `--list-suites` valida el registro de TrustedDevice como suite separada.
- `php artisan test --testsuite=TrustedDevice --compact` ejecuta las pruebas aisladas del módulo.
- Un chequeo local confirma que las líneas activas de ambos archivos mantienen sintaxis `KEY=value` y no tienen claves duplicadas; la revisión del diff confirma que solo cambian comentarios, espacios y orden de asignaciones.
- Pest cubre retención configurada, override, límite de fecha y entrada inválida del comando `trusted-devices:purge`.
- La guía de agentes contiene el comando de coverage para toda la suite y su fallback de Herd/Xdebug; el informe incluye el origen de cobertura configurado por PHPUnit.
- Pest crea un dispositivo vigente y uno soft-deleted; las estadísticas incluyen solo el vigente y el contador de revocaciones refleja el evento persistido.
- `composer show --locked league/commonmark` confirma 2.10.2 o posterior y `composer audit --locked` no informa advisories.
- `config/doctor.php` asigna `ci` y `testing` al modo local y conserva `production`/`staging` como modo de producción; `composer doctor-ci` valida el resultado al cierre.
- `AGENTS.md` y `docs/development/backend-commands.md` indican que `composer doctor` se ejecuta al final, después de los otros gates, y describen `composer doctor-ci` como contraparte de CI.

## Execution Summary

TASK-001 registró la suite TrustedDevice; TASK-002 organizó los dos archivos de entorno; TASK-003 añadió pruebas al comando de purga; TASK-004 documentó coverage para agentes; TASK-005 reubicó el comando en el módulo y TASK-006 ajustó la referencia de las constantes de Symfony. En esta expansión, TASK-007 pasó con 8 tests y 223 aserciones; Pint y PHPStan pasan, y Rector aplicó el renombre indicado por el dry-run. TASK-008 actualizó `league/commonmark` de 2.10.1 a 2.10.3 y `composer audit --locked` no encontró advisories. `boost:update` quitó reglas preexistentes de `AGENTS.md` y `CLAUDE.md`; se restauraron, dejando el cambio de implementación limitado al lockfile. TASK-009 publicó `config/doctor.php` y asignó `ci` y `testing` al modo local, manteniendo `production` y `staging` en producción; `config:show`, Pint, PHPStan y Rector pasaron. Doctor queda reservado para TASK-011. El comentario de Pullfrog es un falso positivo por el scope global de SoftDeletes. La revisión predeterminada codex se mantiene.

**Ruta crítica:** TASK-001 → TASK-003 → TASK-005 → TASK-011. TASK-002 y TASK-004 son independientes.

La ampliación agrega tareas independientes de regresión, seguridad, configuración y documentación, seguidas por una verificación final. No ejecutar Doctor antes de TASK-011.

## Task Dependencies

- TASK-001 → —
- TASK-002 → —
- TASK-003 → TASK-001
- TASK-004 → —
- TASK-005 → TASK-003
- TASK-006 → TASK-003
- TASK-007 → —
- TASK-008 → —
- TASK-009 → —
- TASK-010 → —
- TASK-011 → TASK-007, TASK-008, TASK-009, TASK-010
