# Plan: SOC-80: Prueba E2E del login con Pest Browser y Chromium

## Overview

Agregar Pest Browser y Playwright con Chromium, una prueba de navegador que compruebe como invitado la visibilidad del formulario real de login, un comando Composer que ejecute esa prueba de forma aislada y la documentación de instalación/ejecución. El comando compila primero los assets Vite porque Tests\TestCase desactiva Vite para las pruebas HTTP habituales.

## Scope Challenge

El usuario confirmó HOLD con revisión Codex: mantener el alcance exacto de SOC-80. Ya existe la ruta y página de login, un selector estable para el botón y una suite de pruebas del submódulo Login; no están instalados Pest Browser ni Playwright. La prueba debe usar Chromium y .env.testing existente.

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
- El test usa la configuración `.env.testing` existente y `social-hub-testing`; no requiere datos de usuario porque visita el login como invitado.
- La compatibilidad prevista es Pest 5.2.1 con `pestphp/pest-plugin-browser` 5.0.1 o una versión compatible. Playwright es una dependencia npm de desarrollo; solo se instala Chromium.
- La guía oficial de Pest documenta el plugin y sus aserciones de visibilidad: https://pestphp.com/docs/browser-testing/. La metadata del plugin 5.0.1 declara Pest `^5.0.4`: https://github.com/pestphp/pest-plugin-browser/blob/v5.0.1/composer.json. Playwright documenta la instalación puntual del navegador: https://playwright.dev/docs/browsers.

## Existing Code Leverage

- `app/Auth/routes/login.php` define la ruta GET nombrada `login`.
- `app/Auth/Modules/Login/Controllers/LoginSessionController.php` renderiza `auth/login/Login`.
- `resources/js/modules/auth/login/Login.tsx` contiene los campos `email` y `password` y el botón `data-test="login-button"`.
- `app/Auth/Modules/Login/Tests/AuthenticationTest.php` ya verifica el componente Inertia del login.
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

## Failure Modes

- Playwright y el binario Chromium quedan en versiones desalineadas: la prueba falla al iniciar el navegador; volver a ejecutar `npx playwright install chromium` con el lockfile vigente.
- El navegador encuentra el contenedor Inertia pero no la interfaz porque Vite no tiene manifest: el comando aislado debe compilar assets antes de Pest.
- Un elemento está en el DOM pero oculto o el componente React no cargó: `assertVisible` debe hacer fallar la prueba.
- Falta `.env.testing`, MySQL o la base `social-hub-testing`: la ejecución falla según la configuración existente; no debe crear ni usar una base alterna.

## Ship Cut

La entrega de SOC-80 requiere completar las cuatro tareas: dependencias PHP y npm, Chromium, test con comando aislado y documentación. La instalación de Pest Browser y Playwright puede hacerse en paralelo técnicamente; la prueba depende de ambas y la documentación depende del comando final. La regla `.ai/rules/linear.md` exige ejecutar un paso atómico por ciclo y pausar para revisión antes de continuar.

## Test Coverage Map

- **Instalación y compatibilidad de Pest Browser:** TASK-001
- **Playwright y disponibilidad de Chromium:** TASK-002
- **Login como invitado y visibilidad del formulario real:** TASK-003
- **Ejecución aislada y documentación:** TASK-003, TASK-004

## Execution Summary

- **Mode:** HOLD
- **Default review:** codex
- **Task count:** 4
- **Layers:** [TASK-001, TASK-002] → [TASK-003] → [TASK-004]
- **Critical path:** TASK-001 → TASK-003 → TASK-004

## Task Dependencies

- **TASK-001:** none
- **TASK-002:** none
- **TASK-003:** TASK-001, TASK-002
- **TASK-004:** TASK-003

