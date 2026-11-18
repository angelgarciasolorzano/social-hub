# Plan: SOC-80: Prueba E2E del login con Pest Browser y Chromium

## Overview

Agregar Pest Browser y Playwright con Chromium, probar el formulario real de login como invitado y establecer una arquitectura que permita sumar pruebas Browser por módulo. Las pruebas viven en `Tests/Browser`, comparten la preparación Vite mediante un hook Pest, se agrupan como `browser` y se ejecutan con `composer test:browser`, que compila una vez y excluye ese grupo del comando regular paralelo. Documentar instalación, ejecución y organización.

## Scope Challenge

El usuario confirmó inicialmente HOLD con revisión Codex para SOC-80. Después pidió ampliar el alcance para que el comando cubra una suite Browser futura, las pruebas de módulos vivan bajo `Tests/Browser` y la arquitectura quede documentada; confirmó EXPANSION dentro de SOC-80 y mantiene revisión Codex. Ya existe la ruta y página de login, un selector estable para el botón y una suite de pruebas del submódulo Login.

## Prerequisites

- Las dependencias PHP y npm existentes están instaladas (`composer install` y `npm install` o `composer setup`).
- Existe `.env.testing` y MySQL está disponible con la base `social-hub-testing`, como requiere `Tests\TestCase`.
- La instalación de Chromium se hace mediante Playwright en el cache local del sistema; no se versiona el binario.

## Non-Goals

- Añadir pasos o configuración de CI.
- Probar Firefox, WebKit u otros navegadores.
- Probar el envío de credenciales, autenticación, registro, MFA u otros flujos.
- Crear `.env.e2e`, una base de datos adicional o un seeder E2E.
- Cambiar `Tests\TestCase`, la configuración global de Vite o la página de login.

## Contracts

- `GET /login` usa la ruta nombrada `login`, renderiza `auth/login/Login` y permanece accesible para una sesión invitada.
- La prueba navega con un contexto de navegador nuevo y comprueba visibilidad real de `input[name="email"]`, `input[name="password"]` y `button[data-test="login-button"]`; presencia en el DOM no basta.
- `Tests\TestCase::setUp()` llama a `withoutVite()` para la suite habitual. El comando browser debe construir `public/build` antes de iniciar el servidor de prueba para que el navegador cargue React real; no debe eliminar el mock global.
- Cada prueba E2E de un módulo vive en `app/<Domain>/Tests/Browser/` o `app/<Domain>/Modules/<Feature>/Tests/Browser/`; `tests/Pest.php` conserva `Tests\TestCase` y `RefreshDatabase`, y asigna el grupo `browser` y su preparación Vite a esas carpetas.
- El hook `beforeEach` compartido en `tests/Pest.php` restaura Vite para las pruebas de navegador y usa un hot file temporal sin crear para forzar el manifest compilado, sin cambiar el comportamiento de `Tests\TestCase` para las demás pruebas.
- `composer test:browser` compila los assets una vez y ejecuta todo el grupo `browser`; `composer test` excluye ese grupo de la suite paralela habitual.
- El test usa la configuración `.env.testing` existente y `social-hub-testing`; no requiere datos de usuario porque visita el login como invitado.
- La compatibilidad prevista es Pest 5.2.1 con `pestphp/pest-plugin-browser` 5.0.1 o una versión compatible. Playwright es una dependencia npm de desarrollo; solo se instala Chromium.
- La guía oficial de Pest documenta el plugin y sus aserciones de visibilidad: https://pestphp.com/docs/browser-testing/. La metadata del plugin 5.0.1 declara Pest `^5.0.4`: https://github.com/pestphp/pest-plugin-browser/blob/v5.0.1/composer.json. Playwright documenta la instalación puntual del navegador: https://playwright.dev/docs/browsers.

## Existing Code Leverage

- `app/Auth/routes/login.php` define la ruta GET nombrada `login`.
- `app/Auth/Modules/Login/Controllers/LoginSessionController.php` renderiza `auth/login/Login`.
- `resources/js/modules/auth/login/Login.tsx` contiene los campos `email` y `password` y el botón `data-test="login-button"`.
- `app/Auth/Modules/Login/Tests/AuthenticationTest.php` ya verifica el componente Inertia del login; `LoginBrowserTest.php` se trasladará a `app/Auth/Modules/Login/Tests/Browser/`.
- `phpunit.xml` y `tests/Pest.php` ya registran el submódulo Login y aplican `Tests\TestCase` con `RefreshDatabase`; no requieren nuevo registro de suite.
- `package.json`, `package-lock.json`, `composer.json`, `composer.lock`, `.gitignore`, `AGENTS.md`, `CLAUDE.md` y `docs/development/backend-commands.md` existen.

## Tasks

### TASK-001: Instalar Pest Browser compatible con Pest 5

Agregar `pestphp/pest-plugin-browser` como dependencia de desarrollo en Composer y actualizar el lockfile. Mantener Pest dentro de la versión mayor instalada; el tag 5.0.1 del plugin acepta Pest `^5.0.4`, compatible con Pest 5.2.1.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0
**Depends on:** —

**Acceptance Criteria:**
1. `composer.json` declara el plugin solo en `require-dev` y `composer.lock` resuelve una versión compatible con Pest 5.2.1.
2. `composer validate --no-check-publish` y `composer show pestphp/pest-plugin-browser` terminan correctamente.
3. La resolución no actualiza Pest fuera de su restricción `^5.2` ni introduce el plugin como dependencia de producción.

**writeScope:**
- `composer.json`
- `composer.lock`

**validateCommand:** `rtk composer validate --no-check-publish && rtk composer show pestphp/pest-plugin-browser`

### TASK-002: Añadir Playwright y provisionar solo Chromium

Agregar `playwright` como dependencia npm de desarrollo y actualizar `package-lock.json`. Instalar Chromium con `npx playwright install chromium`; ignorar los directorios de trazas y capturas que Pest Browser puede generar (`tests/Browser/Traces/` y `tests/Browser/Screenshots/`). El binario se descarga al cache de Playwright del sistema y no se agrega al repositorio.

**Type:** infra · **Effort:** S · **Agent:** nodejs-cli-senior-engineer · **Priority:** P0
**Depends on:** —

**Acceptance Criteria:**
1. `package.json` y `package-lock.json` incluyen Playwright como herramienta de desarrollo.
2. `.gitignore` excluye `tests/Browser/Traces/` y `tests/Browser/Screenshots/`.
3. `npx playwright install chromium` y `npx playwright install --list` confirman Chromium; no se descargan Firefox ni WebKit.

**writeScope:**
- `package.json`
- `package-lock.json`
- `.gitignore`

**validateCommand:** `rtk npm ls playwright --depth=0 && rtk npx playwright install chromium && rtk npx playwright install --list`

### TASK-003: Crear la prueba de login y el comando aislado

Crear `LoginBrowserTest.php` en la suite existente de Login. Como invitado, visitar `route('login', absolute: false)` en Chromium, confirmar la ruta `/login` y usar `assertVisible` para los dos campos y el botón. Añadir `composer test:browser-login`, que ejecute `npm run build` y luego `php artisan test --compact app/Auth/Modules/Login/Tests/LoginBrowserTest.php`; así se carga el bundle real y se ejecuta solo esta prueba, sin el modo paralelo de `composer test`.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1
**Depends on:** TASK-001, TASK-002

**Acceptance Criteria:**
1. La prueba abre `/login` con una sesión invitada y confirma que el navegador permanece en esa ruta.
2. La prueba falla si alguno de `input[name="email"]`, `input[name="password"]` o `button[data-test="login-button"]` falta o no es visible.
3. `composer test:browser-login` compila los assets y ejecuta únicamente `LoginBrowserTest.php` con `.env.testing`; si Chromium no está instalado o falla el render React, el comando falla sin omitir el test.

**writeScope:**
- `app/Auth/Modules/Login/Tests/LoginBrowserTest.php`
- `composer.json`

**validateCommand:** `rtk composer test:browser-login`

### TASK-004: Documentar instalación y ejecución del test browser

Documentar el comando, la instalación puntual de Chromium y el requisito de dependencias/assets en `docs/development/backend-commands.md`. Añadir el comando a los resúmenes de pruebas en `CLAUDE.md` y las instrucciones de Pest de `AGENTS.md`, manteniendo explícitos la base existente y los límites de alcance.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P2
**Depends on:** TASK-003

**Acceptance Criteria:**
1. `AGENTS.md` y `CLAUDE.md` identifican la instalación de Chromium y el comando `composer test:browser-login`.
2. `docs/development/backend-commands.md` explica cómo preparar Chromium y ejecutar solo el test, incluyendo que el comando construye los assets.
3. La documentación coincide con `composer.json` y especifica que el test usa `social-hub-testing`, sin CI, otra base o más navegadores.

**writeScope:**
- `AGENTS.md`
- `CLAUDE.md`
- `docs/development/backend-commands.md`

**validateCommand:** `rtk rg -n 'composer test:browser-login|playwright install chromium|social-hub-testing' AGENTS.md CLAUDE.md docs/development/backend-commands.md`

### TASK-005: Centralizar la configuración común de Browser

Centralizar en `tests/Pest.php` la preparación de las pruebas Browser: restaurar Vite después del mock global, configurar un hot file de pruebas que no exista para servir el manifest compilado y asignar el grupo `browser` a la prueba existente y a las futuras carpetas Browser por módulo. Mantener `Tests\TestCase` como clase base y, por ahora, la ruta y el comando específico actuales para que este paso siga siendo ejecutable.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1
**Depends on:** TASK-003

**Acceptance Criteria:**
1. El hook Pest se ejecuta después de `Tests\TestCase::setUp()`, conserva el `withoutVite()` global para las pruebas normales y restaura Vite para usar el manifest compilado en las pruebas Browser.
2. `tests/Pest.php` mantiene `Tests\TestCase` y `RefreshDatabase`, y aplica la preparación Vite y el grupo `browser` a la prueba actual y a las carpetas `Tests/Browser/` de las arquitecturas nueva y antigua.
3. `composer test:browser-login` sigue pasando y falla si React o Chromium no puede ejecutarse; no se cambia `Tests\TestCase` ni el Vite global de la aplicación.

**writeScope:**
- `tests/Pest.php`
- `app/Auth/Modules/Login/Tests/LoginBrowserTest.php`

**validateCommand:** `rtk composer test:browser-login`

### TASK-006: Organizar y ejecutar la suite Browser genérica

Mover la prueba a `app/Auth/Modules/Login/Tests/Browser/LoginBrowserTest.php`. Cambiar el comando específico por `composer test:browser`, que construye Vite y ejecuta `--group=browser`; excluir ese grupo de `composer test` para mantener las pruebas con Chromium fuera de la suite paralela habitual.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1
**Depends on:** TASK-005

**Acceptance Criteria:**
1. La prueba de login queda bajo la carpeta `Tests/Browser/` de su módulo y Pest la reconoce como parte del grupo `browser`.
2. `composer test:browser` compila los assets una vez y ejecuta las pruebas de ese grupo, fallando si no encuentra pruebas o si alguna falla.
3. El comando `composer test` excluye el grupo `browser`, así ejecutar el conjunto habitual no requiere iniciar Chromium.

**writeScope:**
- `app/Auth/Modules/Login/Tests/LoginBrowserTest.php`
- `app/Auth/Modules/Login/Tests/Browser/LoginBrowserTest.php`
- `composer.json`

**validateCommand:** `rtk composer test:browser`

### TASK-007: Documentar la arquitectura y los comandos Browser

Documentar en la guía de arquitectura que las pruebas browser de un módulo viven bajo `Tests/Browser/`, usan el grupo `browser` y comparten la preparación definida en `tests/Pest.php`. Actualizar la guía de comandos con instalación de Chromium, `composer test:browser`, la compilación Vite y la exclusión de la suite normal.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P2
**Depends on:** TASK-006

**Acceptance Criteria:**
1. La arquitectura del backend explica la ubicación module-owned `Tests/Browser/`, el grupo `browser` y el hook compartido de `tests/Pest.php`.
2. La guía de comandos describe `npx playwright install chromium` y que `composer test:browser` construye Vite y corre todos los tests del grupo.
3. La documentación indica `.env.testing`/`social-hub-testing`, Chromium como único browser y que la suite regular excluye el grupo; no agrega CI.

**writeScope:**
- `docs/architecture/backend.md`
- `docs/development/backend-commands.md`

**validateCommand:** `rtk rg -n 'Tests/Browser|beforeEach|composer test:browser|playwright install chromium|social-hub-testing' docs/architecture/backend.md docs/development/backend-commands.md`

### TASK-008: Actualizar los resúmenes de testing para Browser

Actualizar las instrucciones de pruebas de `AGENTS.md` y `CLAUDE.md` al comando `composer test:browser` y a la arquitectura compartida bajo `Tests/Browser/`.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P2
**Depends on:** TASK-006

**Acceptance Criteria:**
1. Ambas guías describen dónde van las pruebas browser de módulo y que pertenecen al grupo `browser`.
2. Ambas guías indican instalar solo Chromium y ejecutar `composer test:browser`, que compila los assets.
3. El texto coincide con Composer: las pruebas browser se omiten de `composer test` y usan `.env.testing` con `social-hub-testing`.

**writeScope:**
- `AGENTS.md`
- `CLAUDE.md`

**validateCommand:** `rtk rg -n 'Tests/Browser|tests/Pest.php|composer test:browser|playwright install chromium|social-hub-testing' AGENTS.md CLAUDE.md`

## Failure Modes

- Playwright y el binario Chromium quedan en versiones desalineadas: la prueba falla al iniciar el navegador; volver a ejecutar `npx playwright install chromium` con el lockfile vigente.
- El navegador encuentra el contenedor Inertia pero no la interfaz porque Vite no tiene manifest: el comando aislado debe compilar assets antes de Pest.
- Un elemento está en el DOM pero oculto o el componente React no cargó: `assertVisible` debe hacer fallar la prueba.
- Falta `.env.testing`, MySQL o la base `social-hub-testing`: la ejecución falla según la configuración existente; no debe crear ni usar una base alterna.
- Un `public/hot` residual puede apuntar a un Vite apagado: el hook compartido de `tests/Pest.php` debe usar un hot file de prueba propio y resolver los assets desde `public/build`.
- Un archivo nuevo bajo `Tests/Browser/` debe entrar automáticamente al grupo `browser` y quedar fuera de `composer test`; si no, la suite genérica podría omitirlo o ejecutar Chromium por accidente.

## Ship Cut

Las tareas 1 a 4 completaron la instalación y la primera prueba de login. La expansión añade configuración Browser reusable en Pest, mueve el test a Tests/Browser, generaliza el runner y sincroniza arquitectura/documentación. El primer corte revisable tras la expansión es TASK-005; la regla `.ai/rules/linear.md` exige ejecutar un paso atómico por ciclo y pausar para revisión.

## Test Coverage Map

- **Instalación y compatibilidad de Pest Browser:** TASK-001
- **Playwright y disponibilidad de Chromium:** TASK-002
- **Login como invitado y visibilidad del formulario real:** TASK-003
- **Ejecución inicial y documentación:** TASK-003, TASK-004
- **Configuración Browser reutilizable:** TASK-005
- **Carpeta Browser y runner genérico:** TASK-006
- **Documentación de arquitectura Browser:** TASK-007, TASK-008

## Execution Summary

- **Mode:** EXPANSION
- **Default review:** codex
- **Task count:** 8
- **Layers:** [TASK-001, TASK-002] → [TASK-003] → [TASK-004] → [TASK-005] → [TASK-006] → [TASK-007, TASK-008]
- **Critical path:** TASK-001 → TASK-003 → TASK-004 → TASK-005 → TASK-006 → TASK-007

## Task Dependencies

- **TASK-001:** none
- **TASK-002:** none
- **TASK-003:** TASK-001, TASK-002
- **TASK-004:** TASK-003
- **TASK-005:** TASK-003
- **TASK-006:** TASK-005
- **TASK-007:** TASK-006
- **TASK-008:** TASK-006

