# Plan: SOC-48: Reforzar tests del backend y separar los gates de calidad

## Overview

Cinco workflows de backend independientes y paralelos (Test, Pint, Rector, PHPStan, Laravel Doctor) reemplazan al workflow monolitico; `composer test` corre con 2G; se revisan los 53 clones de PHPCPD y se limpian pruebas duplicadas y helpers sin uso de tests/Pest.php; al final se instala Sloppy como dependencia de desarrollo fuera de CI.

- **Linear:** [SOC-48](https://linear.app/social-hub-ang/issue/SOC-48/chore-strengthen-backend-tests-and-split-code-quality-gates) · Backlog · Prioridad Media
- **Modo:** EXPANSION · **Revision por defecto:** claude
- **Ejecucion:** Paso a paso: una tarea y pausa para revision (regla .ai/rules/linear.md). Sloppy es el ultimo paso (TASK-023), por decision del usuario.

## Scope Challenge

EXPANSION confirmada por el usuario. Ademas del alcance de SOC-48 se incluyen la actualizacion de README/backend-commands (regla de mantenimiento de docs), un triage previo de PHPCPD que asigna duena a cada clon y una verificacion integral antes de Sloppy. Decisiones del usuario: workflows separados (un archivo por gate), MySQL como servicio en el workflow de tests y revision por defecto `claude`. Se descarto un composite action compartido para el preparado de PHP/Composer porque anade un archivo nuevo (preferencia del proyecto por menor huella); la duplicacion de pasos de preparacion entre workflows se acepta y se compensa con caches de Composer y de herramientas con claves compartidas.

## Prerequisites

- Existen composer.json con los scripts test, pint-ci, rector-dry, phpstan, ide-helper, doctor-ci y phpcpd, y `script-descriptions`.
- El workflow actual `.github/workflows/backend-code-quality.yml` define triggers push/pull_request en main y develop, PHP 8.5, caches de Composer/PHPStan/Rector y usa `.env.ci` con sqlite.
- `.env.testing.example` apunta a MySQL (127.0.0.1:3306, DB social-hub-testing, root sin clave) y `tests/TestCase.php` exige `.env.testing`.
- phpstan.neon escanea `_ide_helper_models.php` (ignorado por git) y `tests/Pest.php`; rector.php escanea app, config, database, routes y tests y no arranca la aplicacion.
- `composer phpcpd` ya existe y no bloquea CI.

## Non-Goals

- Incluir PHPCPD o Sloppy en GitHub Actions.
- Cambiar las reglas de Pint, Rector, PHPStan o Doctor, o el nivel de PHPStan.
- Tocar el workflow del frontend (SOC-11) o los workflows claude.yml y pullfrog.yml.
- Eliminar `CreateTestingDatabase` (falso positivo de 'orphaned symbol': lo usa `composer setup`).
- Ajustar la proteccion de ramas en GitHub; es configuracion manual del repositorio.
- Debilitar o borrar aserciones para reducir lineas duplicadas.

## Contracts

- Nomenclatura: name `Backend Code Quality <Gate>` y archivo `backend-code-quality-<gate>.yml` con gates test, pint, rector, phpstan, doctor.
- Todos los workflows nuevos conservan los triggers `push`/`pull_request` en `[main, develop]`, PHP 8.5 y `actions/checkout@v7`, `actions/cache@v6` del workflow actual.
- Dependencias entre gates: PHPStan necesita IDE Helper (que necesita BD migrada); Doctor necesita .env + migraciones; Rector y Pint no necesitan ni .env ni BD; Test necesita .env.testing y MySQL.
- Las suites se ejecutan siempre con `composer test`, nunca con un comando alterno en CI.
- Las funciones globales de Pest comparten espacio de nombres: ninguna tarea puede crear un helper con nombre ya usado.
- Ningun cambio de pruebas edita tests/Pest.php salvo TASK-020.

## Existing Code Leverage

- `.github/workflows/backend-code-quality.yml` (fuente de pasos, caches y claves a migrar).
- `composer.json` scripts `test`, `pint-ci`, `rector-dry`, `phpstan`, `ide-helper`, `doctor-ci`, `phpcpd`.
- `.env.ci`, `.env.testing.example`, `app/Console/Commands/CreateTestingDatabase.php` (`db:create-testing`).
- Hallazgos de `composer phpcpd` ejecutado durante la planificacion (53 clones, 889 lineas, 4.14%; huerfanos: `paginatedPropData`, `paginatedPropTotal`).
- docs/development/backend-commands.md y README.md (docs a sincronizar).

## Tasks

### TASK-001: Subir el limite de memoria de composer test a 2G

Actualizar el script `test` de composer.json para que la suite corra con memory_limit=2G, manteniendo `composer test` como unico comando local y de CI. El script actual es `@php artisan config:clear --ansi` + `@php artisan test`. OJO: `artisan test` lanza la suite en un proceso hijo, asi que `php -d memory_limit=2G artisan test` puede no propagar el limite; la tarea debe medir el limite efectivo dentro de un test y elegir la forma que funcione (por ejemplo `@php -d memory_limit=2G artisan test` si el hijo lo hereda, o invocar `vendor/bin/pest` con el flag). No se toca `script-descriptions` salvo para reflejar el limite en la descripcion de `test`.

**Type:** chore  
**Priority:** P0  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- Un test temporal (no commiteado) que imprime ini_get('memory_limit') dentro del proceso de Pest, ejecutado con `composer test`, muestra 2G; sin el cambio muestra el valor por defecto del php.ini.
- `composer test` completo termina en verde sin variables de entorno nuevas. Nota (hallazgo de la ejecucion): composer reenvia los argumentos a TODOS los comandos del script, por lo que `composer test -- <ruta>` falla en `config:clear`; para filtrar se usa `php artisan test -d memory_limit=2G <ruta>`.
- Falla/borde: si el limite no llega al proceso hijo de `artisan test`, la tarea NO se da por cumplida con solo anadir el flag; debe cambiar la invocacion hasta que la medicion del primer criterio de 2G.

**writeScope:**

- `composer.json`

**validateCommand:** `composer test`

### TASK-002: Crear workflow Backend Code Quality Test con servicio MySQL

Nuevo `.github/workflows/backend-code-quality-test.yml`, name `Backend Code Quality Test`, mismos triggers que el actual (push y pull_request en main y develop), PHP 8.5 con las extensiones del workflow actual mas `pdo_mysql`, cache de Composer con la misma clave base, `composer install` identico y un job con servicio `mysql` (healthcheck, MYSQL_ALLOW_EMPTY_PASSWORD, MYSQL_DATABASE=social-hub-testing, puerto 3306). El job crea `.env.testing` desde `.env.testing.example` (que ya apunta a mysql 127.0.0.1:3306, DB `social-hub-testing`, usuario root sin clave), genera la APP_KEY de testing (`php artisan key:generate --env=testing`) y ejecuta `composer test`. `tests/TestCase.php` exige `.env.testing`, por eso se crea antes. No se anade Node ni build de Vite; si algun test falla por falta del manifest de Vite, se documenta y se decide aqui (fake de Vite o paso de build), no se ignora.

**Type:** chore  
**Priority:** P1  
**Effort:** M  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** TASK-001

**Acceptance Criteria:**

- El YAML parsea con Symfony Yaml, tiene `name: Backend Code Quality Test`, los triggers `push`/`pull_request` con ramas `[main, develop]` y un unico paso de pruebas que ejecuta exactamente `composer test`.
- Los pasos replican localmente en orden (copiar .env.testing.example, key:generate --env=testing, db:create-testing o la BD del servicio, composer test) y la suite termina en verde contra MySQL.
- Falla/borde: si el servicio MySQL no esta sano el job no ejecuta `composer test` (healthcheck con reintentos definido) y el `.env.testing` no contiene credenciales reales ni se sube al repositorio.

**writeScope:**

- `.github/workflows/backend-code-quality-test.yml`

**validateCommand:** `php -r "require 'vendor/autoload.php'; Symfony\\Component\\Yaml\\Yaml::parseFile('.github/workflows/backend-code-quality-test.yml');"`

### TASK-003: Crear workflow Backend Code Quality Pint (y decidir su alcance)

Nuevo `.github/workflows/backend-code-quality-pint.yml`, name `Backend Code Quality Pint`, mismos triggers, PHP 8.5, cache de Composer, install y `composer pint-ci`. Hallazgo a resolver: `pint-ci` es `vendor/bin/pint --dirty --test`; en un checkout limpio de CI no hay archivos 'dirty', asi que el gate puede pasar sin revisar nada. La tarea mide el comportamiento (ejecutar `vendor/bin/pint --test` completo en local) y PAUSA para que el usuario elija: (a) conservar `pint-ci` tal cual, o (b) cambiarlo a `vendor/bin/pint --test` (revision completa). Solo con la opcion (b) se edita composer.json (y su descripcion). Pint no necesita .env ni base de datos.

**Type:** chore  
**Priority:** P1  
**Effort:** S  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** TASK-001

**Acceptance Criteria:**

- El workflow parsea, usa los triggers actuales y ejecuta solo Pint (sin Rector, PHPStan ni Doctor ni migraciones).
- El resultado de `vendor/bin/pint --test` completo queda reportado al usuario y la decision (a)/(b) queda registrada en el PR; si es (b), `composer pint-ci` pasa en local sobre todo el repositorio.
- Falla/borde: si la opcion (b) revela archivos mal formateados, se corrigen con `vendor/bin/pint --format agent` solo en esos archivos o se vuelve a (a); no se deja el gate en rojo.

**writeScope:**

- `composer.json`
- `.github/workflows/backend-code-quality-pint.yml`

**validateCommand:** `php -r "require 'vendor/autoload.php'; Symfony\\Component\\Yaml\\Yaml::parseFile('.github/workflows/backend-code-quality-pint.yml');" && composer pint-ci`

### TASK-004: Crear workflow Backend Code Quality Rector

Nuevo `.github/workflows/backend-code-quality-rector.yml`, name `Backend Code Quality Rector`, mismos triggers, PHP 8.5, cache de Composer, creacion de `storage/rector`, cache de Rector con la clave actual (`composer.lock` + `rector.php`) y `composer rector-dry`. rector.php solo escanea app, config, database, routes y tests y no arranca la aplicacion, por lo que no necesita .env ni migraciones ni IDE Helper (verificar leyendo rector.php y ejecutando `composer rector-dry` sin .env generado por CI).

**Type:** chore  
**Priority:** P1  
**Effort:** S  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- El workflow parsea y sus pasos son solo: checkout, PHP, cache Composer, install, mkdir storage/rector, cache Rector, `composer rector-dry`.
- `composer rector-dry` termina en verde en local sin haber generado `.env` ni `_ide_helper_models.php`.
- Falla/borde: la clave de cache de Rector incluye `**/rector.php` para que un cambio de configuracion invalide la cache; con cache fria el job igual pasa.

**writeScope:**

- `.github/workflows/backend-code-quality-rector.yml`

**validateCommand:** `php -r "require 'vendor/autoload.php'; Symfony\\Component\\Yaml\\Yaml::parseFile('.github/workflows/backend-code-quality-rector.yml');" && composer rector-dry`

### TASK-005: Crear workflow Backend Code Quality PHPStan con IDE Helper previo

Nuevo `.github/workflows/backend-code-quality-phpstan.yml`, name `Backend Code Quality PHPStan`, mismos triggers. Conserva la preparacion que PHPStan necesita: phpstan.neon hace `scanFiles` de `_ide_helper_models.php` (ignorado por git, se genera) y `ide-helper:models -RM` necesita la BD. Pasos: install, `mkdir -p storage/phpstan`, cache de PHPStan (clave `composer.lock` + `phpstan.neon*`), `.env` desde `.env.ci`, `database/database.sqlite`, key:generate, `migrate --force`, `composer ide-helper`, `composer phpstan`. Sin Pint, Rector ni Doctor.

**Type:** chore  
**Priority:** P1  
**Effort:** M  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- El workflow parsea y el orden de pasos es: .env.ci -> sqlite -> key:generate -> migrate -> `composer ide-helper` -> `composer phpstan`, con `composer ide-helper` estrictamente antes de PHPStan.
- Replicado en local (borrando antes `_ide_helper_models.php` y usando sqlite), `composer ide-helper && composer phpstan` termina en verde.
- Falla/borde: sin el paso de IDE Helper, PHPStan falla por el archivo `_ide_helper_models.php` ausente (confirmado una vez, para dejar justificada la dependencia en el PR).

**writeScope:**

- `.github/workflows/backend-code-quality-phpstan.yml`

**validateCommand:** `php -r "require 'vendor/autoload.php'; Symfony\\Component\\Yaml\\Yaml::parseFile('.github/workflows/backend-code-quality-phpstan.yml');"`

### TASK-006: Crear workflow Backend Code Quality Laravel Doctor

Nuevo `.github/workflows/backend-code-quality-doctor.yml`, name `Backend Code Quality Laravel Doctor`, mismos triggers. Doctor inspecciona la aplicacion, por eso conserva la preparacion actual: install, `.env` desde `.env.ci`, `database/database.sqlite`, key:generate, `migrate --force` y `composer doctor-ci`. No necesita IDE Helper ni caches de PHPStan/Rector (verificar ejecutando Doctor sin `_ide_helper_models.php`).

**Type:** chore  
**Priority:** P1  
**Effort:** S  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- El workflow parsea y ejecuta `composer doctor-ci` despues de preparar .env, sqlite, key y migraciones; no incluye IDE Helper ni pasos de otros gates.
- `composer doctor-ci` termina en verde en local sin `_ide_helper_models.php`.
- Falla/borde: con `.env.ci` y sqlite vacio sin migrar, Doctor reporta el fallo esperado; el workflow garantiza `migrate --force` antes (probado una vez).

**writeScope:**

- `.github/workflows/backend-code-quality-doctor.yml`

**validateCommand:** `php -r "require 'vendor/autoload.php'; Symfony\\Component\\Yaml\\Yaml::parseFile('.github/workflows/backend-code-quality-doctor.yml');"`

### TASK-007: Retirar el workflow monolitico backend-code-quality.yml

Una vez que los cinco workflows nuevos existen y estan verificados, borrar `.github/workflows/backend-code-quality.yml` (crear-luego-borrar, sin renombrar). Confirmar con grep que ningun otro archivo del repositorio referencia el nombre o el archivo viejo, salvo README.md y docs/development/backend-commands.md, que actualiza TASK-021. Registrar en el PR que los checks requeridos de la rama protegida (job `backend-code-quality`) deben reemplazarse en GitHub por los cinco nuevos; eso es un ajuste manual de configuracion del repositorio.

**Type:** chore  
**Priority:** P2  
**Effort:** S  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** TASK-002, TASK-003, TASK-004, TASK-005, TASK-006

**Acceptance Criteria:**

- `.github/workflows/` contiene exactamente backend-code-quality-{test,pint,rector,phpstan,doctor}.yml, frontend-code-quality.yml, claude.yml y pullfrog.yml; el archivo viejo ya no existe.
- Falla/borde: `grep -rn 'backend-code-quality.yml' .` solo devuelve README.md y docs/development/backend-commands.md (pendientes de TASK-021), ninguna referencia en `.github/`.
- Los triggers, versiones de PHP (8.5), extensiones y claves de cache del workflow viejo estan presentes en los nuevos (comparacion paso a paso documentada en el PR).

**writeScope:**

- (sin cambios de archivos)

**validateCommand:** `test ! -e .github/workflows/backend-code-quality.yml && ls .github/workflows`

### TASK-008: Triage de hallazgos de PHPCPD y registro de decisiones

Ejecutar `composer phpcpd` (hoy: 53 clones, 889 lineas duplicadas, 4.14%, 264 archivos; ver `--explain` para el detalle) y clasificar cada hallazgo en: duplicacion real en codigo de produccion (a corregir), repeticion intencional en pruebas (se justifica) o falso positivo. Candidatos observados en codigo de produccion: TrustedDeviceController (update/renew/store), AdminRolePermissionsCommand vs AdminUserRoleCommand (cabecera 3-42), PostActivityOverview vs PostsOverview vs UserActivityOverview, CommentsRelationManager vs PostsRelationManager (canViewForRecord). Falso positivo ya identificado: el 'orphaned symbol' `CreateTestingDatabase` esta auto-descubierto y lo usa `composer setup` (`db:create-testing`); no se elimina. Las decisiones se publican como comentario en SOC-48 (Linear) y se resumen en el PR. Esta tarea no cambia codigo.

**Type:** docs  
**Priority:** P0  
**Effort:** M  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** ninguna

**Acceptance Criteria:**

- Cada uno de los 53 clones aparece clasificado (corregir / justificado / falso positivo) con una razon de una linea, y el comentario existe en SOC-48.
- Cada clon marcado 'corregir' en produccion queda asignado a una de TASK-009, TASK-010, TASK-011 o a una de las tareas de pruebas TASK-012 a TASK-019; ninguno queda sin dueno.
- Falla/borde: los clones de pruebas que son intencionales (casos paralelos con datos distintos) quedan explicitamente justificados en lugar de forzar un dataset que debilite aserciones.

**writeScope:**

- (sin cambios de archivos)

**validateCommand:** `composer phpcpd; test $? -le 1`

### TASK-009: Deduplicar AdminRolePermissionsCommand y AdminUserRoleCommand

Candidato de TASK-008. Segun el triage, extraer solo la logica realmente repetida (validacion de accion/argumentos y proteccion del ultimo `admin.access`) extendiendo una clase existente del modulo Admin antes de crear nada nuevo (preferencia del proyecto: no anadir archivos ni helpers de un solo uso). Si el triage concluye que la repeticion es solo la cabecera/firma de comando, cerrar la tarea como justificada sin cambios.

**Type:** refactor  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- Las suites AdminUserRoleCommandTest y AdminRolePermissionsCommandTest pasan sin modificar ninguna asercion.
- Falla/borde: los comandos siguen rechazando roles/permisos fuera de los enums y protegiendo el ultimo grant de `admin.access` (cubierto por las pruebas existentes).
- `composer phpcpd` ya no lista este par, o la justificacion queda en el comentario de TASK-008.

**writeScope:**

- `app/Modules/Admin/Console/Commands/AdminRolePermissionsCommand.php`
- `app/Modules/Admin/Console/Commands/AdminUserRoleCommand.php`

**validateCommand:** `php artisan test --compact app/Modules/Admin/Tests/Commands`

### TASK-010: Deduplicar update/renew/store de TrustedDeviceController

Candidato de TASK-008. Las lineas 46-64 y 84-102 de TrustedDeviceController repiten el flujo de flash/cookie. Despues del split controlador-servicio de SOC-37 la autorizacion vive en `#[Authorize]` y el estado en `TrustedDeviceService`; extraer solo si el cuerpo repetido es identico en sustancia, reutilizando `TrustedDeviceOperationResult` (que ya expone `message()`/`payload()`) en vez de un helper privado de un solo uso. Si no hay una extraccion limpia, cerrar como justificada. RESULTADO DEL TRIAGE (TASK-008, confirmado por el usuario): cerrada como justificada sin cambios; solo se repiten el guard 401 y una llamada al servicio con flash. Decision publicada en el comentario de SOC-48.

**Type:** refactor  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- Todas las pruebas de `app/Auth/Modules/TrustedDevice/Tests` pasan sin cambios de aserciones.
- Falla/borde: la autorizacion (404/403 por dispositivo ajeno), la cola de cookies y el flash de error de `AlreadyActive` siguen comportandose igual.
- `composer phpcpd` ya no lista el par update/renew/store, o la justificacion queda registrada.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php`

**validateCommand:** `php artisan test --compact app/Auth/Modules/TrustedDevice/Tests`

### TASK-011: Deduplicar widgets de actividad de Posts y Users en Filament

Candidato de TASK-008. PostActivityOverview, PostsOverview y UserActivityOverview comparten getStats/getDailyActivityChartData/canView. Extraer solo lo comun hacia una de las clases existentes (p. ej. una base ya presente en el modulo) y no crear un trait si solo se usa una vez; si la duplicacion es solo estructural de Filament, cerrar como justificada. Los widgets son solo lectura y no exponen datos nuevos.

**Type:** refactor  
**Priority:** P3  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- Las suites `app/Modules/Admin/Tests/Filament` de widgets y del dashboard pasan sin modificar aserciones.
- Falla/borde: la autorizacion por permiso (`admin.posts.viewAny`, `admin.users.viewAny`, `admin.dashboard.view`) de cada widget no cambia.
- `composer phpcpd` ya no lista el trio, o la justificacion queda registrada.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Posts/Widgets/PostActivityOverview.php`
- `app/Modules/Admin/Filament/Resources/Posts/Widgets/PostsOverview.php`
- `app/Modules/Admin/Filament/Resources/Users/Widgets/UserActivityOverview.php`

**validateCommand:** `php artisan test --compact app/Modules/Admin/Tests/Filament`

### TASK-012: Limpiar duplicacion en pruebas TrustedDevice/Queries

TrustedDeviceIndexTest (lineas 92-102 y 137-147) y TrustedDeviceDashboardCacheTest (18-32 y 117-131). Reducir preparacion repetida con `beforeEach`, datasets o un helper local de uso multiple. Las funciones globales de Pest comparten espacio de nombres: un helper nuevo no puede repetir el nombre de otro (fatal por redeclaracion). Mantener intencion y aserciones; no mover nada a tests/Pest.php (eso lo decide TASK-020).

**Type:** test  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- Las dos suites pasan con el mismo numero de tests y de aserciones que antes (conteo registrado antes/despues).
- Falla/borde: ninguna asercion se elimina ni se debilita; cada caso conserva su dato distintivo.
- No se crea ninguna funcion global con nombre ya existente (`vendor/bin/pest` carga toda la suite sin 'Cannot redeclare').

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceIndexTest.php`
- `app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php`

**validateCommand:** `php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Queries`

### TASK-013: Limpiar duplicacion en pruebas TrustedDevice/Listeners

TrustedDeviceInvalidateTest (24-42, 62-66, 75-114, 85-102) y TrustedDeviceRememberTest (76-125); hay tambien un clon entre TrustedDeviceInvalidateTest y TrustedDeviceDashboardCacheTest (62-66 vs 372-377) que se resuelve aqui sin tocar el segundo archivo (si requiere cambio compartido, anotarlo para TASK-020).

**Type:** test  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- Las suites de Listeners pasan con el mismo numero de tests y aserciones.
- Falla/borde: se siguen cubriendo los caminos de invalidacion y de recordar dispositivo con sus fallos (cookie ausente, dispositivo revocado).
- Sin redeclaraciones de funciones globales al cargar toda la suite.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php`
- `app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceRememberTest.php`

**validateCommand:** `php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Listeners`

### TASK-014: Limpiar duplicacion en pruebas TrustedDevice/Crud

TrustedDeviceStoreTest (62-78 vs 82-97) y TrustedDeviceDestroyTest vs TrustedDeviceForceDestroyTest (19-37 / 20-38). Parametrizar con datasets o preparacion compartida solo donde el cuerpo sea equivalente.

**Type:** test  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- Las tres suites pasan con el mismo numero de tests y aserciones.
- Falla/borde: el caso de soft delete y el de borrado definitivo siguen verificando estados distintos (`deleted_at` vs fila inexistente).
- Sin redeclaraciones de funciones globales.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceStoreTest.php`
- `app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceDestroyTest.php`
- `app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceForceDestroyTest.php`

**validateCommand:** `php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Crud`

### TASK-015: Limpiar duplicacion en TrustedDeviceServiceTest

Lineas 189-206 vs 208-225 de TrustedDeviceServiceTest (casos paralelos con un literal distinto). Convertir a dataset solo si cada caso conserva nombre y expectativa propios.

**Type:** test  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- La suite pasa con el mismo numero de casos (el dataset expande a los dos originales).
- Falla/borde: los helpers locales `parsedChromeWindowsDetector` y `trustedDeviceStoreRequest` no se duplican ni cambian de nombre.
- Sin redeclaraciones de funciones globales.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php`

**validateCommand:** `php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Services`

### TASK-016: Limpiar duplicacion en pruebas de comandos Admin

AdminUserRoleCommandTest (74-89/167-182, 79-92/222-234, 97-113/170-186, 170-186/214-235), AdminRolePermissionsCommandTest (13-28/35-50, 90-108/112-126, 160-178) y AdminBootstrapCommandTest (34-39 vs AdminUserRoleCommandTest 29-34). Es el grupo con mas clones: preferir datasets sobre los enums `AdminRole`/`AdminPermission` en lugar de bloques copiados.

**Type:** test  
**Priority:** P2  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- Las tres suites pasan; el numero total de casos (con datasets expandidos) no baja respecto al original.
- Falla/borde: siguen cubiertos el rechazo de identificadores fuera de los enums y la proteccion del ultimo `admin.access`.
- Sin redeclaraciones de funciones globales.

**writeScope:**

- `app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php`
- `app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`
- `app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Admin/Tests/Commands`

### TASK-017: Limpiar duplicacion en pruebas de relation managers Admin

PostsRelationManagerTest (33-56/94-114 y 118-152 vs TrustedDevicesRelationManagerTest 144-175), CommentsRelationManagerTest (152-174/200-223 y 282-306 vs TrustedDevicesRelationManagerTest 176-200). Los helpers locales `mountedCommentViewActionData` y `assertTrustedDevicesRelationIsForbidden` son candidatos a reutilizarse; si dos archivos necesitan uno, anotarlo para TASK-020 en vez de duplicarlo.

**Type:** test  
**Priority:** P2  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- Las tres suites pasan sin bajar el numero de casos ni aserciones.
- Falla/borde: se mantiene la verificacion de acceso denegado sin permiso `admin.users.view`/`admin.posts.view` y de solo lectura.
- Sin redeclaraciones de funciones globales.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/TrustedDevicesRelationManagerTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/RelationManagers/CommentsRelationManagerTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Admin/Tests/Filament/Resources`

### TASK-018: Limpiar duplicacion en pruebas de Comment y del seeder Admin

CommentResourceTest (42-55/59-69), CommentValidationHardeningTest (17-37/73-93) y AdminAuthorizationSeederTest (98-109/164-175). En las pruebas de seguridad de Comment cada caso representa un vector distinto: solo se parametriza el armado de la peticion, nunca se funden los vectores.

**Type:** test  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- Las suites de Comment/Resources, Comment/Security y Admin/Database pasan con las mismas aserciones.
- Falla/borde: cada vector de validacion (control chars, longitud, respuesta a respuesta) sigue produciendo su propio caso.
- Sin redeclaraciones de funciones globales.

**writeScope:**

- `app/Modules/Comment/Tests/Resources/CommentResourceTest.php`
- `app/Modules/Comment/Tests/Security/CommentValidationHardeningTest.php`
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**validateCommand:** `php artisan test --compact app/Modules/Comment/Tests/Resources app/Modules/Comment/Tests/Security app/Modules/Admin/Tests/Database`

### TASK-019: Limpiar duplicacion en pruebas de Auth (Email y Password)

EmailVerificationTest (52-68/162-178) y PasswordResetTest (101-106) vs PasswordTrustedDeviceRevocationTest (97-105).

**Type:** test  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-008

**Acceptance Criteria:**

- Las suites Email y Password pasan con las mismas aserciones y numero de casos.
- Falla/borde: siguen cubiertos el enlace de verificacion invalido/expirado y la revocacion de dispositivos al cambiar o restablecer la contrasena.
- Sin redeclaraciones de funciones globales.

**writeScope:**

- `app/Auth/Modules/Email/Tests/EmailVerificationTest.php`
- `app/Auth/Modules/Password/Tests/PasswordResetTest.php`
- `app/Auth/Modules/Password/Tests/PasswordTrustedDeviceRevocationTest.php`

**validateCommand:** `php artisan test --compact app/Auth/Modules/Email/Tests app/Auth/Modules/Password/Tests`

### TASK-020: Centralizar utilidades compartidas y retirar las huerfanas de tests/Pest.php

Revision final de tests/Pest.php despues de los refactors de tests. Retirar solo funciones sin referencias confirmadas con grep -w en `app/` y `tests/` (excluyendo Pest.php): hoy `paginatedPropData`, `paginatedPropTotal` (PHPCPD y grep coinciden) y la expectation `toBeOne` (0 referencias; es el ejemplo del scaffold de Pest), y limpiar los `use` que queden sin usar (p. ej. `TestResponse`). Reconfirmar con grep despues de TASK-012 a TASK-019, porque pueden haber dejado nuevas referencias. Si dos o mas archivos necesitan un mismo helper local (anotado por esas tareas), promoverlo aqui; si lo usa un solo archivo, se queda local. `tests/Pest.php` esta en `scanFiles` de PHPStan: correr PHPStan tras el cambio.

**Type:** test  
**Priority:** P2  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Review:** claude  
**Depends on:** TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019

**Acceptance Criteria:**

- Cada funcion o expectation retirada tiene 0 referencias por `grep -rnw` en app/ y tests/ (resultado pegado en el PR); las que tienen al menos una referencia permanecen.
- `vendor/bin/pest` carga la suite completa sin errores de funcion indefinida ni redeclarada.
- Falla/borde: `composer phpstan` sigue en verde con tests/Pest.php en `scanFiles` y sin imports sin usar.

**writeScope:**

- `tests/Pest.php`

**validateCommand:** `composer phpstan && php artisan test --compact app/Modules/Post/Tests`

### TASK-021: Actualizar documentacion de comandos, workflows y PHPCPD

Sincronizar la documentacion en el mismo PR (regla de mantenimiento de docs): README.md seccion 'Calidad de codigo' (hoy cita backend-code-quality.yml; pasa a citar los cinco workflows y el limite de 2G de `composer test`) y docs/development/backend-commands.md (descripcion de `composer test` con 2G, referencias de CI de `doctor-ci`, tabla de workflows y el estado actual de PHPCPD, que hoy dice '8 clones, 119 lineas, 1.14%' y debe reflejar la medicion posterior a TASK-009 a TASK-020). Sloppy se documenta en TASK-023, no aqui.

**Type:** docs  
**Priority:** P3  
**Effort:** S  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** TASK-001, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-020

**Acceptance Criteria:**

- README.md y backend-commands.md ya no mencionan `backend-code-quality.yml` (ni como archivo ni como unico workflow) y listan los cinco workflows nuevos con su comando.
- La cifra de PHPCPD documentada coincide con una ejecucion real de `composer phpcpd` hecha en esta tarea.
- Falla/borde: `grep -rn 'backend-code-quality.yml' README.md docs` no devuelve resultados.

**writeScope:**

- `README.md`
- `docs/development/backend-commands.md`

**validateCommand:** `! grep -rn 'backend-code-quality.yml' README.md docs`

### TASK-022: Verificacion integral de gates antes de instalar Sloppy

Sin cambios de archivos: ejecutar en local y en orden los gates del proyecto sobre el estado final: `vendor/bin/pint --dirty --format agent`, `composer rector-dry`, `composer ide-helper` + `composer phpstan`, `composer test`, y `composer doctor` al final (regla: Doctor es el ultimo gate local). Confirmar que los cinco workflows quedaron verdes en el PR en GitHub Actions. Dejar el resumen de DoD en Linear (comentario con cambios, decisiones de PHPCPD y resultado de gates) y el PR con la plantilla del proyecto.

**Type:** chore  
**Priority:** P2  
**Effort:** S  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-009, TASK-010, TASK-011, TASK-020, TASK-021

**Acceptance Criteria:**

- Pint, Rector, PHPStan, test y Doctor terminan en verde en ese orden; Doctor se ejecuta al final.
- Los cinco checks del PR en GitHub Actions estan en verde y aparecen como checks independientes.
- Falla/borde: si cualquiera falla, se corrige en la tarea duena del archivo y no se instala Sloppy hasta volver a verde.

**writeScope:**

- (sin cambios de archivos)

**validateCommand:** `composer rector-dry && composer phpstan && composer test && composer doctor`

### TASK-023: Instalar heyosseus/sloppy como gate experimental (ultimo paso)

Ultimo paso por decision del usuario. `composer require --dev heyosseus/sloppy` (la dependencia nueva la autoriza este plan), descubrir su binario/uso con `vendor/bin/sloppy --help`, anadir un script Composer claro (nombre `sloppy`, siguiendo el estilo de `phpcpd`) con su entrada en `script-descriptions` marcandolo como experimental, y documentarlo en docs/development/backend-commands.md. No se agrega a ningun workflow de `.github/workflows` (igual que PHPCPD). Si el paquete no resuelve con PHP 8.5 o con las dependencias actuales, detenerse y avisar antes de forzar versiones.

**Type:** chore  
**Priority:** P3  
**Effort:** S  
**Agent:** general-purpose  
**Review:** claude  
**Depends on:** TASK-022

**Acceptance Criteria:**

- `composer sloppy` se ejecuta (puede devolver codigo distinto de 0 si encuentra hallazgos, igual que phpcpd, y eso queda documentado) y su descripcion aparece en `composer list` / `script-descriptions`.
- Falla/borde: `grep -rni sloppy .github` no devuelve nada; el paquete esta solo en `require-dev` y `composer.lock` se actualiza sin cambiar versiones de otros paquetes (revision de diff del lock).
- `composer test` y `composer phpstan` siguen en verde despues de la instalacion.

**writeScope:**

- `composer.json`
- `composer.lock`
- `docs/development/backend-commands.md`

**validateCommand:** `composer sloppy; ! grep -rni sloppy .github`

## Failure Modes

- Los checks requeridos de la rama protegida apuntan al job viejo `backend-code-quality`; hay que reemplazarlos a mano en GitHub tras el merge o el PR queda bloqueado.
- `artisan test` ejecuta la suite en un proceso hijo y puede ignorar `-d memory_limit`; se mide el limite efectivo en TASK-001.
- El workflow de tests puede fallar por MySQL aun no listo o por falta de manifest de Vite; TASK-002 define healthcheck y trata el manifest explicitamente.
- `pint-ci` usa `--dirty`: en un checkout limpio no revisa nada; TASK-003 lo mide y pide decision.
- Quitar helpers de Pest.php puede romper PHPStan (esta en `scanFiles`); TASK-020 reconfirma por grep y corre PHPStan.
- Sloppy puede no resolver con PHP 8.5; TASK-023 se detiene en ese caso.

## Ship Cut

Si la ejecucion se corta: TASK-001 a TASK-007 forman un corte entregable (tests con 2G, workflows separados y viejo retirado); TASK-008 a TASK-021 son limpieza de calidad independiente; TASK-023 (Sloppy) puede diferirse sin afectar lo demas.

## Test Coverage Map

| Area | Tareas | Cobertura |
|---|---|---|
| composer test (2G) | TASK-001 | medicion del limite efectivo + suite Post |
| workflows | TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007 | parseo YAML local, replica local de pasos y checks en GitHub Actions |
| codigo de produccion con clones | TASK-009, TASK-010, TASK-011 | suites existentes de Admin y TrustedDevice |
| limpieza de pruebas | TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020 | suite propia + conteo de casos antes/despues + grep de referencias |
| verificacion final | TASK-022 | Pint, Rector, PHPStan, test y Doctor en orden |

## Execution Summary

- **Tareas:** 23 · **Camino critico:** TASK-001 -> TASK-002 -> TASK-007 -> TASK-021 -> TASK-022 -> TASK-023 (6 pasos)
- **Capa 0:** TASK-001, TASK-004, TASK-005, TASK-006, TASK-008
- **Capa 1:** TASK-002, TASK-003, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019
- **Capa 2:** TASK-007, TASK-020
- **Capa 3:** TASK-021
- **Capa 4:** TASK-022
- **Capa 5:** TASK-023

## Task Dependencies

| Tarea | Depende de |
|---|---|
| TASK-001 | - |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-001 |
| TASK-004 | - |
| TASK-005 | - |
| TASK-006 | - |
| TASK-007 | TASK-002, TASK-003, TASK-004, TASK-005, TASK-006 |
| TASK-008 | - |
| TASK-009 | TASK-008 |
| TASK-010 | TASK-008 |
| TASK-011 | TASK-008 |
| TASK-012 | TASK-008 |
| TASK-013 | TASK-008 |
| TASK-014 | TASK-008 |
| TASK-015 | TASK-008 |
| TASK-016 | TASK-008 |
| TASK-017 | TASK-008 |
| TASK-018 | TASK-008 |
| TASK-019 | TASK-008 |
| TASK-020 | TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019 |
| TASK-021 | TASK-001, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-020 |
| TASK-022 | TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-009, TASK-010, TASK-011, TASK-020, TASK-021 |
| TASK-023 | TASK-022 |
