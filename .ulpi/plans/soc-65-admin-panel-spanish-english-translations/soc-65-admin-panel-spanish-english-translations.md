# Plan: SOC-65: Traducir el panel de administración a español e inglés (Dashboard, Users, Backups)

## Overview

El panel de Filament pasa a tener dos idiomas, español e inglés. La carpeta de traducciones se mueve de `resources/lang` a `lang/` en la raíz. El plugin `bezhansalleh/filament-language-switch` pone un selector dentro del panel y en el login, el MFA y el perfil, y la elección se guarda en `users.preferred_locale`. Dashboard, Users (con la tabla de su pestaña Activity y los nombres de eventos) y Backups usan catálogos `lang/{en,es}/admin/*.php` con claves con nombre. Dos pruebas protegen el resultado: una exige las mismas claves en ambos idiomas y otra rechaza textos fijos.

**Linear:** [SOC-65](https://linear.app/social-hub-ang/issue/SOC-65/feature-translate-the-admin-panel-into-spanish-and-english-dashboard) · **Modo:** EXPANSION · **Revisión predeterminada:** Claude

## Scope Challenge

EXPANSION con revisión de Claude (2026-10-28). Decisiones del usuario: usar `bezhansalleh/filament-language-switch`; mover `resources/lang` a `lang/`; traducir también la tabla de la pestaña Activity de Users (`ActivityLogsTable`); respetar el orden de resolución del plugin, que sigue al `Accept-Language` del navegador. Extras incluidos: guardar el idioma en el usuario, el selector en login/MFA/perfil, una prueba contra textos fijos y traducir los nombres de eventos del Activity log. Hallazgos: `users.preferred_locale` (enum `PreferredLocale`: `en`/`es`, por defecto `en`) ya existe y la app de React la edita en la configuración de cuenta, así que se reutiliza en vez de crear `users.locale` (sin migración); como su valor por defecto es `en`, para un administrador con sesión la preferencia gana al navegador y el `Accept-Language` solo decide en el login. Los textos propios de Filament ya vienen traducidos. `AccountStatus::label()` solo se usa en el panel.

## Prerequisites

- SOC-63 cerrado; SOC-64 (flujo de aprobación) todavía no empieza, así que nadie más toca `AdminPanelProvider` ni `AdminServiceProvider` en paralelo.
- El usuario aprobó la dependencia nueva `bezhansalleh/filament-language-switch` (5.x, Filament 5).
- `users.preferred_locale` y `App\User\Enums\PreferredLocale` existen (SOC-34).
- El panel ya tiene un tema propio (`resources/css/filament/admin/theme.css`), requisito del plugin.

## Non-Goals

- Traducir Announcements, Posts, Comments y el resto del Activity log (infolist y widgets) del panel.
- Traducir la app de React, los correos (incluida la invitación de administradores), los comandos de Artisan o los mensajes de validación de Laravel.
- La página de aceptar invitación (`AcceptAdminInvitation`).
- Idiomas distintos de español e inglés.
- Auditar los cambios de idioma.

## Contracts

- Las traducciones viven en `lang/{en,es}/` en la raíz; `resources/lang` deja de existir.
- Los textos del panel usan `__('admin/<sección>.<grupo>.<clave>')` con claves con nombre; plurales con `trans_choice`; marcadores con parámetros.
- `en` y `es` tienen siempre las mismas claves y marcadores (`AdminTranslationKeysTest`); `fallback_locale` sigue siendo `en`.
- El idioma se resuelve en este orden: sesión, `?locale=`, `users.preferred_locale` del usuario del guard `admin`, `Accept-Language`, cookie y `app.locale`. Elegir un idioma lo guarda en `preferred_locale` sin auditarlo.
- Con `en` todo texto queda idéntico al actual, así que las aserciones en inglés de las pruebas existentes no cambian.
- `ActivityLogEventCatalog` traduce por el valor del evento y cae al headline si falta la clave, o al valor crudo si el evento ya no está catalogado.

## Existing Code Leverage

- `users.preferred_locale` y `PreferredLocale` (SOC-34): la preferencia ya existe, sin migración nueva.
- `AdminServiceProvider::boot()` con métodos privados (`registerBackupSchedule()`): mismo patrón para el selector y el listener.
- `DescribesAccountStatus` ya delega en `AccountStatus::label()`: traducir el enum cubre tabla, detalle e historial.
- `ActivityLogsTable::configure()` se reutiliza en la pestaña Activity: traducirla una vez cubre ambas.
- `ActivityLogEventCatalog` ya centraliza los nombres de eventos y logs.
- Las traducciones de Filament (`filament-panels::*`) cubren login, MFA, perfil, botones, paginación y filtros.
- `AdminFilamentTestContext::actingAsAdminWithRoles()` y las pruebas existentes de cada pantalla: se agrega un caso en `es` sin duplicar la preparación.

## Tasks

### TASK-001: Mover resources/lang a lang/ en la raíz

Mover con `git mv` `resources/lang/en/messages.php`, `resources/lang/es/messages.php` y `resources/lang/es/passwords.php` a `lang/` en la raíz y borrar `resources/lang/`. Laravel 13 usa `resources/lang` solo mientras existe (`Application::langPath()`), así que el cambio es de convención y no debe cambiar ningún texto. Revisar que nada (phpstan.neon, rector.php, pint.json, vite.config.ts, composer.json, `.github/`) apunte a la ruta vieja; los planes viejos de `.ulpi/plans` que la mencionan no se tocan.

**Type:** refactor · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `resources/lang/` ya no existe, `lang/en/messages.php`, `lang/es/messages.php` y `lang/es/passwords.php` conservan su historial (`git log --follow`) y su contenido idéntico.
2. `PasswordResetTest` y `ListUsersTest` (que usan `messages.*` y el texto 'Never') pasan sin cambios: si Laravel no encontrara `lang/`, devolverían la clave cruda y fallarían.
3. `grep -rn "resources/lang"` fuera de `vendor/`, `node_modules/` y `.ulpi/plans/` no devuelve nada.

**writeScope:**

- `lang/en/messages.php` (nuevo)
- `lang/es/messages.php` (nuevo)
- `lang/es/passwords.php` (nuevo)

**validateCommand:** `test ! -d resources/lang && test -f lang/es/passwords.php && rtk php -d memory_limit=2G vendor/bin/pest --compact app/Auth/Modules/Password/Tests/PasswordResetTest.php app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php`

**Review:** claude

### TASK-002: Instalar filament-language-switch y su @source

`composer require bezhansalleh/filament-language-switch:^5.0` (dependencia aprobada en SOC-65) y agregar `@source "../../../../vendor/bezhansalleh/filament-language-switch/**/*";` en `resources/css/filament/admin/theme.css`, junto a los `@source` existentes. Nada más se configura en esta tarea. Si Composer pide actualizar otros paquetes, parar y avisar en vez de aceptarlo.

**Type:** chore · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `composer show bezhansalleh/filament-language-switch` muestra una versión 5.x compatible con Filament 5.10 y Laravel 13.
2. `composer.lock` solo agrega el plugin (y sus dependencias nuevas, si tiene); ningún paquete existente cambia de versión.
3. `npm run build` termina sin errores con el `@source` nuevo.

**writeScope:**

- `composer.json`
- `composer.lock`
- `resources/css/filament/admin/theme.css`

**validateCommand:** `rtk composer show bezhansalleh/filament-language-switch && rtk npm run build`

**Review:** claude

### TASK-003: Configurar el selector dentro del panel

En `AdminServiceProvider::boot()` (método privado `configureLanguageSwitch()`, como `registerBackupSchedule()`) llamar a `LanguageSwitch::configureUsing()` con `locales(['es', 'en'])`, `labels(['es' => 'Español', 'en' => 'English'])` y `userPreferredLocale()` leyendo `Filament::auth()->user()?->preferred_locale` (enum `PreferredLocale`, la columna que ya existe en `users`; nunca `auth()->user()`, que en el panel es el guard `web`). Solo dentro de los paneles por ahora (`visible(insidePanels: true, outsidePanels: false)`). Se respeta el orden del plugin: sesión, `?locale=`, preferencia del usuario, `Accept-Language`, cookie y `app.locale` (decisión del usuario: seguir al navegador). Prueba nueva `Tests/Translations/AdminLanguageSwitchTest.php`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002

**Acceptance Criteria:**

1. Una página del panel muestra el selector con 'Español' y 'English'; con `?locale=es` la petición corre con `app()->getLocale() === 'es'` y la siguiente petición sin parámetro sigue en `es` (sesión).
2. Un administrador con `preferred_locale = es` y sesión nueva entra al panel en español; uno con `en` y `Accept-Language: es` entra en inglés (la preferencia gana al navegador).
3. `?locale=fr` (no soportado) se ignora y la página queda en el idioma anterior, sin error.

**writeScope:**

- `app/Modules/Admin/Tests/Translations/AdminLanguageSwitchTest.php` (nuevo)
- `app/Modules/Admin/Providers/AdminServiceProvider.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminLanguageSwitchTest.php`

**Review:** claude

### TASK-004: Guardar el idioma elegido en users.preferred_locale

Listener nuevo `App\Modules\Admin\Listeners\AdminPreferredLocaleListener` para `BezhanSalleh\LanguageSwitch\Events\LocaleChanged`, registrado en `AdminServiceProvider` (no hay `EventServiceProvider` en el módulo). Toma el usuario con `Filament::auth()->user()`, convierte el locale con `PreferredLocale::tryFrom()` y guarda solo si es válido y distinto. No dispara `AccountUpdated` ni escribe en `activity_log` (cambiar de idioma no se audita). Nota: la columna es la misma que el usuario edita en la configuración de cuenta de la app de React; elegir idioma en el panel la cambia también ahí.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. Elegir 'es' en el panel deja `preferred_locale = PreferredLocale::SPANISH` en el administrador autenticado con el guard `admin`.
2. Un locale fuera de `PreferredLocale`, un evento sin usuario del guard `admin` o el mismo valor que ya tenía no escriben nada y no lanzan excepción.
3. La cantidad de filas de `activity_log` no cambia y no se despacha `AccountUpdated` (`Event::fake([AccountUpdated::class])`).

**writeScope:**

- `app/Modules/Admin/Listeners/AdminPreferredLocaleListener.php` (nuevo)
- `app/Modules/Admin/Tests/Translations/AdminPreferredLocaleTest.php` (nuevo)
- `app/Modules/Admin/Providers/AdminServiceProvider.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminPreferredLocaleTest.php`

**Review:** claude

### TASK-005: Selector en login, MFA y perfil

Extiende la configuración de TASK-003: `visible(insidePanels: true, outsidePanels: true)`, `outsidePanelRoutes(['auth.login', 'auth.profile', 'auth.multi-factor-authentication.set-up-required'])` (rutas reales `filament.admin.auth.*`) y `outsidePanelPlacement(Placement::TopEnd, PlacementMode::Pinned)`. Los textos del login, el desafío MFA y el perfil los traduce Filament; aquí solo se pone el selector. La página de aceptar invitación (`AcceptAdminInvitation`, Livewire propio) queda fuera.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**

1. Como invitado, `/admin/login` muestra el selector y con `?locale=es` se ve en español (un texto de `filament-panels::auth/pages/login` traducido por Filament).
2. La página de configuración obligatoria de MFA y el perfil muestran el selector.
3. La página de aceptar invitación no muestra el selector y sigue respondiendo 200 a una invitación válida.

**writeScope:**

- `app/Modules/Admin/Providers/AdminServiceProvider.php`
- `app/Modules/Admin/Tests/Translations/AdminLanguageSwitchTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminLanguageSwitchTest.php app/Modules/Admin/Tests/Invitations/AcceptAdminInvitationTest.php`

**Review:** claude

### TASK-006: Catálogo admin/common

`lang/{en,es}/admin/common.php`: textos que comparten varias secciones, con claves con nombre en snake_case: `never` (Never / Nunca), `none`, `anonymous`, `deleted_user`, `deleted_record` (':type #:id (deleted)'), `yes`, `no`, `not_recorded` y los que aparezcan repetidos al leer los archivos de Dashboard, Users, Backups y `DescribesActivityLogParties`. Las tareas de código solo consumen claves; las agrega esta tarea leyendo el código (solo lectura).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Ambos archivos devuelven arrays con exactamente las mismas claves (comparadas por el `validateCommand`).
2. Los marcadores (`:type`, `:id`) son los mismos en `en` y `es`; un texto con marcador usa `__()` con parámetros, nunca concatenación.

**writeScope:**

- `lang/en/admin/common.php` (nuevo)
- `lang/es/admin/common.php` (nuevo)

**validateCommand:** `php -l lang/en/admin/common.php && php -l lang/es/admin/common.php && php -r 'function k(array $a, string $p = ""): array {$o = []; foreach ($a as $x => $v) {$o = is_array($v) ? [...$o, ...k($v, $p.$x.".")] : [...$o, $p.$x];} return $o;} $e = k(require "lang/en/admin/common.php"); $s = k(require "lang/es/admin/common.php"); sort($e); sort($s); exit($e === $s ? 0 : 1);'`

**Review:** claude

### TASK-007: Catálogo admin/dashboard

`lang/{en,es}/admin/dashboard.php` con grupos `quick_links`, `operational_summary`, `registration_summary` y `registrations_chart` (títulos, descripciones, etiquetas de estadísticas, rangos de fechas, leyendas y el `aria-label` de los accesos rápidos). Se arma leyendo `AdminQuickLinksWidget`, `admin-quick-links.blade.php`, `OperationalSummaryWidget`, `RegistrationSummaryWidget` y `RegistrationsChart`. Plurales con `trans_choice` donde el texto cuenta algo.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Cada texto visible de los cinco archivos tiene su clave; mismas claves en `en` y `es`.
2. Los textos con cantidades usan la sintaxis de plurales de Laravel (`{1} ... |[2,*] ...`) en ambos idiomas.

**writeScope:**

- `lang/en/admin/dashboard.php` (nuevo)
- `lang/es/admin/dashboard.php` (nuevo)

**validateCommand:** `php -l lang/en/admin/dashboard.php && php -l lang/es/admin/dashboard.php && php -r 'function k(array $a, string $p = ""): array {$o = []; foreach ($a as $x => $v) {$o = is_array($v) ? [...$o, ...k($v, $p.$x.".")] : [...$o, $p.$x];} return $o;} $e = k(require "lang/en/admin/dashboard.php"); $s = k(require "lang/es/admin/dashboard.php"); sort($e); sort($s); exit($e === $s ? 0 : 1);'`

**Review:** claude

### TASK-008: Catálogo admin/users

`lang/{en,es}/admin/users.php` con grupos `resource` (label, plural, navegación), `account_status` (active/suspended), `table`, `invite` (acción de `ListUsers`), `infolist`, `actions.suspend` y `actions.reactivate` (modal, campo 'Reason', notificaciones de éxito y error), `relations.status_history`, `relations.activity`, `relations.posts`, `relations.trusted_devices` y `widgets.activity_overview`. Se arma leyendo los archivos de `Filament/Resources/Users/` y `AccountStatus::label()`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Cada texto visible de `Filament/Resources/Users/**` tiene su clave; mismas claves en `en` y `es`.
2. Los textos en inglés coinciden con los actuales, para que las pruebas existentes sigan pasando sin cambiar sus aserciones.

**writeScope:**

- `lang/en/admin/users.php` (nuevo)
- `lang/es/admin/users.php` (nuevo)

**validateCommand:** `php -l lang/en/admin/users.php && php -l lang/es/admin/users.php && php -r 'function k(array $a, string $p = ""): array {$o = []; foreach ($a as $x => $v) {$o = is_array($v) ? [...$o, ...k($v, $p.$x.".")] : [...$o, $p.$x];} return $o;} $e = k(require "lang/en/admin/users.php"); $s = k(require "lang/es/admin/users.php"); sort($e); sort($s); exit($e === $s ? 0 : 1);'`

**Review:** claude

### TASK-009: Catálogo admin/backups

`lang/{en,es}/admin/backups.php` con grupos `page` (título, navegación, encabezado), `table`, `destination` (estado del destino del infolist), `actions.create`, `actions.download` (incluida la confirmación de contraseña), `notifications` y `widget`. Se arma leyendo `BackupManagement`, `BackupManagementInfolist`, `BackupsTable`, `BackupSummaryWidget` y `backup-management.blade.php`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Cada texto visible de `Filament/Pages/Backups/**` tiene su clave; mismas claves en `en` y `es`.
2. Los textos en inglés coinciden con los actuales (las aserciones de `BackupManagementTest` no cambian).

**writeScope:**

- `lang/en/admin/backups.php` (nuevo)
- `lang/es/admin/backups.php` (nuevo)

**validateCommand:** `php -l lang/en/admin/backups.php && php -l lang/es/admin/backups.php && php -r 'function k(array $a, string $p = ""): array {$o = []; foreach ($a as $x => $v) {$o = is_array($v) ? [...$o, ...k($v, $p.$x.".")] : [...$o, $p.$x];} return $o;} $e = k(require "lang/en/admin/backups.php"); $s = k(require "lang/es/admin/backups.php"); sort($e); sort($s); exit($e === $s ? 0 : 1);'`

**Review:** claude

### TASK-010: Catálogo admin/activity-log

`lang/{en,es}/admin/activity-log.php` con `table` (columnas, filtros de log, evento, causante y fechas), `parties` y `events`: una clave por cada caso de los seis enums de `Enums/Events/` (40 en total) usando su valor (`auth.login`, `admin.role_granted`, ...) como clave anidada, y `logs` con los seis `ActivityLogName`. En inglés el texto es el mismo headline que hoy calcula `ActivityLogEventCatalog` (p. ej. 'Password Updated'), para que nada cambie en `en`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Hay una clave en `events` por cada caso de los enums registrados en `ActivityLogEvent::ENUMS` y una en `logs` por cada `ActivityLogName`.
2. Mismas claves en `en` y `es`, y los textos en inglés son idénticos a los headlines actuales.

**writeScope:**

- `lang/en/admin/activity-log.php` (nuevo)
- `lang/es/admin/activity-log.php` (nuevo)

**validateCommand:** `php -l lang/en/admin/activity-log.php && php -l lang/es/admin/activity-log.php && php -r 'function k(array $a, string $p = ""): array {$o = []; foreach ($a as $x => $v) {$o = is_array($v) ? [...$o, ...k($v, $p.$x.".")] : [...$o, $p.$x];} return $o;} $e = k(require "lang/en/admin/activity-log.php"); $s = k(require "lang/es/admin/activity-log.php"); sort($e); sort($s); exit($e === $s ? 0 : 1);'`

**Review:** claude

### TASK-011: Prueba de paridad de claves es/en

`Tests/Translations/AdminTranslationKeysTest.php`: por cada archivo de `lang/en/admin/` exige el mismo archivo en `lang/es/admin/` con las mismas claves (aplanadas con punto), textos no vacíos y los mismos marcadores `:nombre`. Además, que `events` y `logs` de `admin/activity-log` cubran todos los casos de los enums (si alguien agrega un evento sin traducirlo, falla). Dataset por archivo para que el mensaje diga qué archivo y qué clave fallan.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006, TASK-007, TASK-008, TASK-009, TASK-010

**Acceptance Criteria:**

1. Pasa con los cinco catálogos de TASK-006..010.
2. Quitar una clave en `es`, cambiar un marcador o agregar un caso a un enum de eventos hace fallar la prueba con el nombre del archivo y la clave (comprobado a mano una vez y revertido).

**writeScope:**

- `app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**Review:** claude

### TASK-012: Traducir AdminQuickLinksWidget y su vista

Reemplazar los textos de `AdminQuickLinksWidget` (label y descripción de cada acceso) y de `admin-quick-links.blade.php` (heading, description, `aria-label`) por `__('admin/dashboard.quick_links.*')`. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-007

**Acceptance Criteria:**

1. Con `es`, el widget muestra el título, la descripción y los accesos en español; con `en`, los textos actuales.
2. Los enlaces siguen filtrados por permisos igual que antes: un administrador sin permisos sigue sin ver accesos en ambos idiomas.

**writeScope:**

- `app/Modules/Admin/Filament/Widgets/AdminQuickLinksWidget.php`
- `app/Modules/Admin/UI/Views/filament/widgets/admin-quick-links.blade.php`
- `app/Modules/Admin/Tests/Filament/Widgets/AdminQuickLinksWidgetTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Widgets/AdminQuickLinksWidgetTest.php`

**Review:** claude

### TASK-013: Traducir OperationalSummaryWidget

Títulos, descripciones y etiquetas de las estadísticas de cuentas, actividad y respaldos pasan a `__('admin/dashboard.operational_summary.*')`, con `trans_choice` para las cantidades. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006, TASK-007

**Acceptance Criteria:**

1. Con `es`, cada estadística visible se muestra en español con sus números intactos.
2. Las estadísticas que dependen de permisos siguen ocultas para quien no los tiene en ambos idiomas.

**writeScope:**

- `app/Modules/Admin/Filament/Widgets/OperationalSummaryWidget.php`
- `app/Modules/Admin/Tests/Filament/Widgets/OperationalSummaryWidgetTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Widgets/OperationalSummaryWidgetTest.php`

**Review:** claude

### TASK-014: Traducir RegistrationSummaryWidget

Etiquetas y descripciones del resumen de registros a `__('admin/dashboard.registration_summary.*')`. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-007

**Acceptance Criteria:**

1. Con `es`, el resumen se ve en español con los mismos totales.
2. Sin registros, el estado vacío también se muestra traducido.

**writeScope:**

- `app/Modules/Admin/Filament/Widgets/RegistrationSummaryWidget.php`
- `app/Modules/Admin/Tests/Filament/Widgets/RegistrationSummaryWidgetTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Widgets/RegistrationSummaryWidgetTest.php`

**Review:** claude

### TASK-015: Traducir RegistrationsChart

Encabezado, descripción, leyenda y opciones del filtro de rango a `__('admin/dashboard.registrations_chart.*')`. Las etiquetas de fecha del eje usan `translatedFormat()` para que los meses y días salgan en el idioma activo. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-007

**Acceptance Criteria:**

1. Con `es`, el título, los rangos del filtro y los nombres de mes del eje salen en español.
2. Cambiar de rango sigue devolviendo los mismos datos en ambos idiomas (solo cambian las etiquetas).

**writeScope:**

- `app/Modules/Admin/Filament/Widgets/RegistrationsChart.php`
- `app/Modules/Admin/Tests/Filament/Widgets/RegistrationsChartTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Widgets/RegistrationsChartTest.php`

**Review:** claude

### TASK-016: Traducir AccountStatus::label()

`App\User\Enums\AccountStatus::label()` devuelve `__('admin/users.account_status.active|suspended')`. `DescribesAccountStatus` ya lo usa, así que la tabla, el detalle y el historial cambian solos. `SUSPENDED_LOGIN_MESSAGE` (español fijo de la app) no se toca.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008

**Acceptance Criteria:**

1. Con `es` las etiquetas son 'Activa' y 'Suspendida'; con `en`, 'Active' y 'Suspended'.
2. El filtro de estado de `ListUsers` sigue filtrando por el valor guardado (`active`/`suspended`) aunque la etiqueta esté en español.

**writeScope:**

- `app/User/Enums/AccountStatus.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersAccountStatusTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersAccountStatusTest.php`

**Review:** claude

### TASK-017: Traducir UserResource y UsersTable

`UserResource` define `getModelLabel()`, `getPluralModelLabel()` y `getNavigationLabel()` con `__()` (propiedades estáticas no admiten llamadas). `UsersTable`: columnas, filtros, estado vacío y 'Never' pasan a `admin/users.table.*` y `admin/common.never`. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006, TASK-008

**Acceptance Criteria:**

1. Con `es`, la navegación, el título de la lista, las columnas y 'Nunca' (usuario sin último inicio de sesión) salen en español.
2. La búsqueda y el orden por columnas siguen funcionando igual con `es` (las claves de columna no cambian).

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/UserResource.php`
- `app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php app/Modules/Admin/Tests/Filament/Resources/Users/UserResourceTest.php`

**Review:** claude

### TASK-018: Traducir la acción de invitar administradores

En `ListUsers`, la acción 'Invite administrator' (label, modal, botón de envío, campo de email, ayuda, opción de invitar, notificaciones de éxito y error, y textos de validación propios) pasa a `admin/users.invite.*`. El correo de invitación queda fuera de alcance. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-017

**Acceptance Criteria:**

1. Con `es`, el modal de invitación y su notificación de éxito se muestran en español.
2. Un email inválido o ya invitado sigue rechazándose; su mensaje de error sale en el idioma activo.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/Pages/ListUsers.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php`

**Review:** claude

### TASK-019: Traducir las acciones de suspender y reactivar

En `ViewUser`: labels, modales, el campo 'Reason', sus ayudas y las notificaciones de éxito y de falla (`failureNotificationTitle`) pasan a `admin/users.actions.*`. El motivo que escribe el administrador se guarda tal cual. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006, TASK-008

**Acceptance Criteria:**

1. Con `es`, montar la acción de suspender muestra el modal en español (`assertMountedActionModalSee`) y suspender muestra la notificación en español.
2. Si el estado cambió mientras el modal estaba abierto, la acción falla con el aviso traducido y no escribe nada.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/Pages/ViewUser.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserAccountStatusActionsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserAccountStatusActionsTest.php`

**Review:** claude

### TASK-020: Traducir UserInfolist

Secciones y entradas del detalle de usuario a `admin/users.infolist.*` (y `admin/common.*` para 'Never', 'Yes', 'No'). Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006, TASK-008

**Acceptance Criteria:**

1. Con `es`, todas las secciones y etiquetas del detalle salen en español.
2. Credenciales y secretos de 2FA siguen sin mostrarse en ningún idioma (las aserciones de ausencia existentes siguen pasando).

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/Schemas/UserInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserTest.php`

**Review:** claude

### TASK-021: Traducir el historial de estados

`AccountStatusChangesRelationManager`: título de la pestaña, columnas, estado vacío y 'Deleted user' (`admin/common.deleted_user`). Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006, TASK-008

**Acceptance Criteria:**

1. Con `es`, la pestaña y sus columnas salen en español y el estado se muestra como 'Suspendida'/'Activa'.
2. Un cambio hecho por un administrador eliminado muestra 'Usuario eliminado'.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/AccountStatusChangesRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/AccountStatusChangesRelationManagerTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/AccountStatusChangesRelationManagerTest.php`

**Review:** claude

### TASK-022: Traducir la pestaña de publicaciones

`PostsRelationManager`: título, columnas, filtros y estado vacío a `admin/users.relations.posts.*`. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008

**Acceptance Criteria:**

1. Con `es`, la pestaña y sus columnas salen en español.
2. El contenido de las publicaciones no se traduce ni se altera.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/PostsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php`

**Review:** claude

### TASK-023: Traducir la pestaña de dispositivos de confianza

`TrustedDevicesRelationManager`: título, columnas, estados (activo, vencido, revocado) y estado vacío a `admin/users.relations.trusted_devices.*`. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008

**Acceptance Criteria:**

1. Con `es`, la pestaña, sus columnas y los estados de cada dispositivo salen en español.
2. Los datos del dispositivo (nombre, sistema, navegador, IP) se muestran sin traducir.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/TrustedDevicesRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/TrustedDevicesRelationManagerTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/TrustedDevicesRelationManagerTest.php`

**Review:** claude

### TASK-024: Traducir la pestaña Activity del usuario

`ActivityLogsRelationManager`: título de la pestaña ('Activity') a `admin/users.relations.activity.*`. La tabla la traduce TASK-025, que reutiliza. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-025

**Acceptance Criteria:**

1. Con `es`, la pestaña y la tabla que muestra salen en español (las columnas vienen de TASK-025).
2. La pestaña sigue oculta para quien no puede ver el usuario o la actividad, en ambos idiomas.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/ActivityLogsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/ActivityLogsRelationManagerTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/ActivityLogsRelationManagerTest.php`

**Review:** claude

### TASK-025: Traducir ActivityLogsTable y DescribesActivityLogParties

Columnas y filtros de `ActivityLogsTable` (los usa la lista del Activity log y la pestaña Activity de Users) a `admin/activity-log.table.*`. `DescribesActivityLogParties`: 'Anonymous', 'None', 'Deleted :type #:id' a `admin/common.*` y `admin/activity-log.parties.*`; el tipo de modelo también se traduce cuando tiene clave. El resto del Activity log (infolist, widgets) queda para su propia tarea. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006, TASK-010

**Acceptance Criteria:**

1. Con `es`, la lista del Activity log muestra columnas, filtros y 'Anónimo' en español.
2. Un causante eliminado se describe como 'Usuario eliminado #id' (con el marcador `:id`) y su enlace sigue sin aparecer.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/ActivityLogs/Tables/ActivityLogsTable.php`
- `app/Modules/Admin/Filament/Concerns/DescribesActivityLogParties.php`
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php`

**Review:** claude

### TASK-026: Traducir los nombres de eventos y logs

`ActivityLogEventCatalog::label()`, `logLabel()`, `eventOptions()` y `logOptions()` leen `admin/activity-log.events.<valor>` y `admin/activity-log.logs.<log>`; si una clave no existe (`Lang::has()`), caen en el headline actual, y un evento que ya no está catalogado sigue mostrando su valor crudo.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-010

**Acceptance Criteria:**

1. Con `es`, `label('auth.login')` y las opciones del filtro de eventos (agrupadas por log) devuelven los textos en español.
2. Con `en` todo devuelve exactamente lo de hoy; un valor no catalogado devuelve el valor crudo y un evento sin clave devuelve su headline.

**writeScope:**

- `app/Modules/ActivityLog/Support/ActivityLogEventCatalog.php`
- `app/Modules/ActivityLog/Tests/Support/ActivityLogEventCatalogTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/ActivityLog/Tests/Support/ActivityLogEventCatalogTest.php`

**Review:** claude

### TASK-027: Traducir UserActivityOverview

Estadísticas y descripciones del widget de actividad de usuarios a `admin/users.widgets.activity_overview.*`. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008

**Acceptance Criteria:**

1. Con `es`, cada estadística sale en español con los mismos números.
2. El widget sigue oculto sin permisos, en ambos idiomas.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/Widgets/UserActivityOverview.php`
- `app/Modules/Admin/Tests/Filament/Resources/Users/Widgets/UserActivityOverviewTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Users/Widgets/UserActivityOverviewTest.php`

**Review:** claude

### TASK-028: Traducir la página de respaldos

`BackupManagement`: título, grupo y etiqueta de navegación (con `getTitle()`, `getNavigationGroup()`, `getNavigationLabel()`) y la acción de crear respaldo a `admin/backups.*`. `BackupManagementService`: los errores de la descarga (respaldo no disponible, contraseña incorrecta, demasiados intentos) pasan de claves en inglés con `__()` a `admin/backups.errors.*`; el servicio solo lo usa esta página. La vista `backup-management.blade.php` no tiene texto propio y no se toca. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006, TASK-009

**Acceptance Criteria:**

1. Con `es`, el título, la navegación, el botón de crear respaldo y su notificación salen en español.
2. Descargar sigue exigiendo la contraseña; una contraseña incorrecta muestra el error en el idioma activo y no genera el enlace.

**writeScope:**

- `app/Modules/Admin/Filament/Pages/Backups/BackupManagement.php`
- `app/Modules/Admin/Services/BackupManagementService.php`
- `app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php`

**Review:** claude

### TASK-029: Traducir la tabla y el estado del destino de respaldos

`BackupsTable` (columnas, estado vacío) y `BackupManagementInfolist` (estado y salud del destino) a `admin/backups.table.*` y `admin/backups.destination.*`. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-028

**Acceptance Criteria:**

1. Con `es`, las columnas de la tabla y el estado del destino salen en español.
2. Los nombres de archivo de respaldo siguen sin mostrarse donde hoy no se muestran (las aserciones existentes pasan).

**writeScope:**

- `app/Modules/Admin/Filament/Pages/Backups/Tables/BackupsTable.php`
- `app/Modules/Admin/Filament/Pages/Backups/Schemas/BackupManagementInfolist.php`
- `app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php`

**Review:** claude

### TASK-030: Traducir BackupSummaryWidget

Estadísticas y descripciones del resumen de respaldos a `admin/backups.widget.*`, con `trans_choice` para las cantidades. Se agrega a la prueba un caso con `app()->setLocale('es')` que comprueba los textos en español; las aserciones en inglés existentes siguen pasando sin cambios.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-029

**Acceptance Criteria:**

1. Con `es`, el resumen sale en español con los mismos valores.
2. Sin respaldos, el estado vacío también sale traducido.

**writeScope:**

- `app/Modules/Admin/Filament/Pages/Backups/Widgets/BackupSummaryWidget.php`
- `app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php`

**Review:** claude

### TASK-031: Prueba contra textos fijos

`Tests/Translations/AdminHardcodedTextTest.php` recorre los archivos traducidos (widgets del Dashboard, `Filament/Resources/Users/**`, `Filament/Pages/Backups/**`, `ActivityLogsTable`, `DescribesActivityLogParties` y sus vistas Blade) y falla si encuentra un literal pasado a `label()`, `heading()`, `description()`, `title()`, `placeholder()`, `helperText()`, `tooltip()`, `modalHeading()`, `modalDescription()`, `modalSubmitActionLabel()`, `successNotificationTitle()`, `failureNotificationTitle()`, `emptyStateHeading()` o `emptyStateDescription()` (regex sobre `->metodo('Texto` y `"Texto`), o texto suelto en Blade fuera de `{{ }}` y atributos `aria-label`. El escáner es una función de la prueba, con casos propios.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-027, TASK-028, TASK-029, TASK-030

**Acceptance Criteria:**

1. Pasa sobre todos los archivos de Dashboard, Users y Backups ya traducidos.
2. Los casos del escáner detectan `->label('Status')` y `<x-slot name="heading">Quick access</x-slot>`, y no marcan `->label(__('admin/users.table.status'))` ni nombres de iconos o colores.

**writeScope:**

- `app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php`

**Review:** claude

### TASK-032: Quitar la clave messages.Never sin uso

Después de TASK-017, `messages.Never` ya no se usa (`UsersTable` pasa a `admin/common.never`). Quitarla de `lang/en/messages.php` y `lang/es/messages.php`; la del restablecimiento de contraseña se queda.

**Type:** chore · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-017

**Acceptance Criteria:**

1. `grep -rn "messages.Never" app resources` no devuelve nada y ambos archivos solo conservan la clave del restablecimiento de contraseña.
2. `PasswordResetTest` y `ListUsersTest` siguen pasando.

**writeScope:**

- `lang/en/messages.php`
- `lang/es/messages.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Auth/Modules/Password/Tests/PasswordResetTest.php app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php`

**Review:** claude

### TASK-033: Documentar idiomas en CLAUDE.md, AGENTS.md y arquitectura

Describir la carpeta `lang/` (y que `resources/lang` ya no se usa), el plugin y su configuración en `AdminServiceProvider` (idiomas, orden de resolución, `preferred_locale` compartida con la app, selector fuera del panel), los catálogos `admin/*`, la traducción de eventos con caída al headline, las pruebas de paridad y de textos fijos, y la convención: todo texto nuevo del panel con `__()` y su clave en `es` y `en`. Revisar el diff de CLAUDE.md/AGENTS.md por el hook `boost:update`.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-005, TASK-011, TASK-026, TASK-031, TASK-032

**Acceptance Criteria:**

1. CLAUDE.md, AGENTS.md y `docs/architecture/backend.md` nombran solo archivos y clases que existen.
2. La entrada del módulo Admin dice qué secciones están traducidas y cuáles faltan (Announcements, Activity log salvo su tabla, Posts, Comments).

**writeScope:**

- `CLAUDE.md`
- `AGENTS.md`
- `docs/architecture/backend.md`

**validateCommand:** `rtk grep -n "lang/" CLAUDE.md AGENTS.md docs/architecture/backend.md`

**Review:** claude

### TASK-034: Registrar la regla de textos traducibles

Agregar a `.ai/rules/backend.md` la regla: en el panel de Filament todo texto visible se escribe con `__('admin/<sección>.<clave>')` con su versión en `lang/en` y `lang/es`; nunca literales; plurales con `trans_choice`; marcadores con parámetros, no concatenación.

**Type:** docs · **Effort:** XS · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-031

**Acceptance Criteria:**

1. La regla aparece en `.ai/rules/backend.md` con un ejemplo correcto y uno incorrecto.
2. No contradice las reglas existentes del archivo (Blade `@use`, FluentRule).

**writeScope:**

- `.ai/rules/backend.md`

**validateCommand:** `rtk grep -n "__('admin/" .ai/rules/backend.md`

**Review:** claude

### TASK-035: Gates finales y verificación en el navegador

Pint (`--dirty --format agent`), Rector (`rector-clear-cache`, `rector-dry`, `rector`), PHPStan, `composer test`, `npm run build`. En el navegador: cambiar de idioma en el login y dentro del panel, recorrer Dashboard, Users (lista, invitar, detalle con sus pestañas, suspender) y Backups en `es` y `en` buscando textos mezclados o claves crudas, comprobar que la elección sobrevive a cerrar sesión (se guarda en `preferred_locale`), y revisar en modo claro y oscuro.

**Type:** test · **Effort:** M · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-033, TASK-034

**Acceptance Criteria:**

1. Todos los gates pasan; si alguno falla se reporta con su salida.
2. En el navegador no aparece ninguna clave cruda (`admin/...`) ni texto en el otro idioma en Dashboard, Users y Backups.

**writeScope:**


**validateCommand:** `rtk composer phpstan && rtk composer test && rtk npm run build`

**Review:** claude

## Failure Modes

- Una clave falta en `es`: se ve en inglés por `fallback_locale`, y `AdminTranslationKeysTest` falla antes de llegar a producción (TASK-011).
- Alguien agrega un texto fijo en una sección ya traducida: `AdminHardcodedTextTest` falla (TASK-031).
- Se agrega un evento al Activity log sin traducirlo: la prueba de paridad lo exige, y en ejecución se muestra su headline (TASK-011, TASK-026).
- El listener usa el guard equivocado (`auth()` es `web` en el panel): usa `Filament::auth()` y la prueba lo fija (TASK-004).
- `?locale=` con un idioma no soportado: se ignora sin error (TASK-003).
- Composer intenta actualizar otros paquetes al instalar el plugin: la tarea se detiene y se consulta (TASK-002).

## Ship Cut

TASK-001..005 dejan el selector funcionando y la preferencia guardada, sin textos propios traducidos. Los catálogos (TASK-006..010) avanzan en paralelo; después Dashboard (TASK-012..015), Users (TASK-016..027) y Backups (TASK-028..030) son independientes entre sí, y cada sección queda completa por separado. TASK-031..035 endurecen, documentan y cierran.

## Test Coverage Map

| Área | Tareas |
| --- | --- |
| Mover lang/ sin cambios | TASK-001, TASK-032 |
| Selector, resolución del idioma y login | TASK-003, TASK-005 |
| Preferencia guardada sin auditoría | TASK-004 |
| Paridad de claves y eventos | TASK-011 |
| Dashboard en es/en | TASK-012, TASK-013, TASK-014, TASK-015 |
| Users en es/en | TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-027 |
| Activity log (tabla y nombres de eventos) | TASK-025, TASK-026 |
| Backups en es/en | TASK-028, TASK-029, TASK-030 |
| Sin textos fijos | TASK-031 |
| Navegador | TASK-035 |

## Execution Summary

- Tareas: 35
- Ruta crítica: TASK-001 → TASK-006 → TASK-028 → TASK-029 → TASK-030 → TASK-031 → TASK-033 → TASK-035
- Capas paralelas:

  1. TASK-001, TASK-002
  2. TASK-003, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010
  3. TASK-004, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-025, TASK-026, TASK-027, TASK-028
  4. TASK-005, TASK-018, TASK-024, TASK-029, TASK-032
  5. TASK-030
  6. TASK-031
  7. TASK-033, TASK-034
  8. TASK-035

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | TASK-002 |
| TASK-004 | TASK-003 |
| TASK-005 | TASK-004 |
| TASK-006 | TASK-001 |
| TASK-007 | TASK-001 |
| TASK-008 | TASK-001 |
| TASK-009 | TASK-001 |
| TASK-010 | TASK-001 |
| TASK-011 | TASK-006, TASK-007, TASK-008, TASK-009, TASK-010 |
| TASK-012 | TASK-007 |
| TASK-013 | TASK-006, TASK-007 |
| TASK-014 | TASK-007 |
| TASK-015 | TASK-007 |
| TASK-016 | TASK-008 |
| TASK-017 | TASK-006, TASK-008 |
| TASK-018 | TASK-017 |
| TASK-019 | TASK-006, TASK-008 |
| TASK-020 | TASK-006, TASK-008 |
| TASK-021 | TASK-006, TASK-008 |
| TASK-022 | TASK-008 |
| TASK-023 | TASK-008 |
| TASK-024 | TASK-008, TASK-025 |
| TASK-025 | TASK-006, TASK-010 |
| TASK-026 | TASK-010 |
| TASK-027 | TASK-008 |
| TASK-028 | TASK-006, TASK-009 |
| TASK-029 | TASK-028 |
| TASK-030 | TASK-029 |
| TASK-031 | TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-027, TASK-028, TASK-029, TASK-030 |
| TASK-032 | TASK-017 |
| TASK-033 | TASK-005, TASK-011, TASK-026, TASK-031, TASK-032 |
| TASK-034 | TASK-031 |
| TASK-035 | TASK-033, TASK-034 |
