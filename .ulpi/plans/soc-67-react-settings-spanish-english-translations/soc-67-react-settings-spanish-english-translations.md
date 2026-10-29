# Plan: SOC-67: Traducir al español e inglés las vistas de configuración de React (Perfil, 2FA, Dispositivos confiables)

- **Issue:** [SOC-67](https://linear.app/social-hub-ang/issue/SOC-67/feature-translate-the-settings-views-of-the-react-app-into-spanish-and)
- **Modo:** EXPANSION · **Revisión por defecto:** claude · **Fecha:** 2026-10-29

## Overview

Agregar español e inglés a la aplicación React empezando por Perfil, Autenticación en dos pasos y Dispositivos confiables, con `lang/` como única fuente para PHP y React. El servidor decide el idioma (`preferred_locale`, luego `Accept-Language`, luego `es`), comparte por Inertia solo los catálogos que registra cada página, ya fusionados con `fallback_locale`, y React los lee con un `useTranslation()` propio sin dependencias nuevas. Fechas, flashes y errores de validación siguen el mismo idioma.

## Scope Challenge

EXPANSION con revisión `claude` (confirmado el 2026-10-29). La ampliación sobre la issue: quitar el `'es'` forzado de `PasswordNewController`/`PasswordResetLinkController`, las fechas de comentarios y posts siguen el idioma (sus textos no), y el registro guarda el idioma resuelto. Transporte: se evaluaron la opción A (`laravel-react-i18n` 2.0.5: sin publicar desde 2024-10 y con `react ^18.2.0` como dependencia, lo que instalaría una segunda copia de React) y erag (`@erag/lang-sync-inertia` 3.1.0: peers obligatorios de Vue, Svelte, `@inertiajs/vue3` y `@inertiajs/svelte` —27 paquetes en un dry-run—, sin fallback por clave, y su paquete PHP reemplaza el alias global `Lang` y añade un middleware global). Se eligió una implementación propia al estilo erag (C mejorada): catálogos por página en `props.translations`, fusión con el fallback en el servidor y una prueba de PHP que valida cada clave usada en TS, en lugar de claves tipadas.

## Prerequisites

- SOC-65/66 están en `main`: `TranslatedText::of()`, `lang/` en la raíz y `AdminTranslationKeysTest`/`AdminHardcodedTextTest` existen.
- `users.preferred_locale` (enum `PreferredLocale` `en`/`es`, default `en`) existe y se edita en Perfil.
- `app.locale` y `app.fallback_locale` siguen en `en`: el selector del panel exige que el primer idioma coincida con `app.locale`.
- El SSR de Inertia está activo (`INERTIA_SSR_ENABLED` true por defecto): nada del traductor ni de las fechas puede guardar el idioma en estado de módulo.
- Decisión del usuario: no se cambia el default de la columna ni se migran datos (no hay producción); el registro guarda el idioma resuelto y Angel se siembra en `es`.

## Non-Goals

- Traducir otros módulos de React (auth, home, post, comment, profile público, announcement, Apariencia); comentarios y posts solo cambian sus fechas.
- Un selector de idioma fuera de Perfil (login, header).
- Correos y notificaciones por email; contenido escrito por usuarios; idiomas distintos de `en`/`es`.
- Tocar `SwitchLanguageLocale` ni la configuración de idiomas del panel.
- Renombrar las claves de `lang/*/messages.php` y `passwords.php` (tarea de auth).
- Instalar dependencias de i18n en npm o Composer.

## Contracts

- Clave = `<grupo>.<ruta.anidada>`, con el grupo como ruta bajo `lang/<locale>/` (`setting/trusted-devices.table.title`); la misma clave sirve en PHP (`TranslatedText::of`) y en React (`t`).
- `SetApplicationLocale` (grupo `web`, antes de `HandleInertiaRequests`): usuario → `preferred_locale` válido; invitado o valor inválido → `Accept-Language` limitado a `en`/`es` → `es`.
- `FrontendTranslations` tiene alcance de petición; los controladores registran sus grupos con `use()`; `HandleInertiaRequests` añade siempre `dates`. Cada grupo se envía fusionado con `fallback_locale` (`en`), así que una clave ausente en `es` llega en inglés (criterio 3 de la issue).
- Props compartidos nuevos: `locale: 'en' | 'es'` y `translations: Record<grupo, árbol>`, tipados en `SharedData`.
- `useTranslation()` → `{ t, tChoice, locale }`; plurales y marcadores con la sintaxis de Laravel; una clave inexistente devuelve la clave.
- `useDateTime()` → los formateadores de `dateTime.ts` ligados al idioma; se formatea con `dayjs(iso).locale(locale)`, nunca con `dayjs.locale()` global.
- Contrato de `data/*.ts`: conservan forma y nombres de campo; los textos pasan a ser claves que el consumidor envuelve con `t()`. `value`, filtros y orden no cambian (viajan a la URL y al backend).
- Los mensajes y flashes de backend de estas vistas usan `TranslatedText::of('setting/...')`; los nombres de campos de error no cambian.

## Existing Code Leverage

- `App\Support\Translation\TranslatedText::of()` (SOC-65) para todo texto de PHP.
- `AdminTranslationKeysTest` y `AdminHardcodedTextTest` como modelo de las pruebas de paridad y de textos fijos.
- `App\User\Enums\PreferredLocale` y el campo de idioma de `AccountSettingsProfileForm`, que ya guarda `preferred_locale`.
- `HandleInertiaRequests::share()` y `SharedData` (`resources/js/shared/types/index.d.ts`).
- `shared/lib/dateTime.ts` con sus siete formateadores, y `relativeTime` ya extendido en `app.tsx`.
- El diseño de erag/laravel-lang-sync-inertia (catálogos por página en `props`, `trans`/`trans_choice` con sintaxis de Laravel) como referencia externa, no como código local.

## Tasks

### TASK-001: Middleware que aplica el idioma del usuario o del navegador

Crear `App\Http\Middleware\SetApplicationLocale` y añadirlo al grupo `web` en `bootstrap/app.php` antes de `HandleInertiaRequests`. Orden: `preferred_locale` del usuario autenticado (leído con `getRawOriginal()` + `PreferredLocale::tryFrom()`, para que un valor no soportado en la BD no lance), luego `Accept-Language` limitado a `en`/`es` (comprobando el encabezado de forma explícita: `getPreferredLanguage()` devuelve el primer idioma ofrecido aunque nada coincida) y, si nada coincide, `es` como constante del middleware (`GUEST_FALLBACK_LOCALE`). No toca `app.locale` ni `fallback_locale` (siguen en `en`, como exige el selector del panel), ni el `SwitchLanguageLocale` del panel.

**Type:** feature · **Priority:** P0 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** —

**Acceptance Criteria:**

- Un usuario con `preferred_locale = es` recibe `app()->getLocale() === 'es'` y uno con `en` recibe `en`, aunque su navegador pida el otro idioma.
- Un invitado con `Accept-Language: en-US` recibe `en`; con `es-MX` recibe `es`; sin encabezado o con `fr` recibe `es`.
- Un usuario cuyo `preferred_locale` en la BD es `fr` (escrito sin el modelo) no provoca error: se resuelve como un invitado.

**writeScope:**

- `app/Http/Middleware/SetApplicationLocale.php` (nuevo)
- `tests/Feature/Http/SetApplicationLocaleTest.php` (nuevo)
- `bootstrap/app.php`

**validateCommand:** `rtk php artisan test --compact tests/Feature/Http/SetApplicationLocaleTest.php && rtk vendor/bin/phpstan analyse --no-progress app/Http/Middleware/SetApplicationLocale.php bootstrap/app.php`

### TASK-002: Servicio FrontendTranslations: catálogos de lang/ para React con fallback

Crear `App\Support\Translation\FrontendTranslations`, con alcance de petición (`#[Scoped]` del contenedor; verificar con search-docs que existe en Laravel 13, y si no, registrar `scoped()` en `AppServiceProvider` y anotarlo en el PR). API: `use(string ...$groups): static` registra grupos como `setting/trusted-devices`; `toArray(): array<string, array<mixed>>` devuelve, por grupo, `array_replace_recursive(grupo en fallback_locale, grupo en el locale activo)` leído con el `Translator` de Laravel (nunca `require` de archivos). Un grupo inexistente lanza `InvalidArgumentException` para que una errata no pase en silencio. Diseño tomado de erag/laravel-lang-sync-inertia, sin su alias global `Lang` ni su middleware global.

**Type:** feature · **Priority:** P0 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** —

**Acceptance Criteria:**

- Con locale `es`, una clave que solo existe en `lang/en/...` aparece con el texto inglés y las demás con el español (fallback por clave, no por archivo).
- Registrar el mismo grupo dos veces no lo duplica, y `toArray()` sin grupos registrados devuelve `[]`.
- Un grupo que no existe en ningún locale lanza `InvalidArgumentException` con el nombre del grupo.

**writeScope:**

- `app/Support/Translation/FrontendTranslations.php` (nuevo)
- `tests/Feature/Translation/FrontendTranslationsTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact tests/Feature/Translation/FrontendTranslationsTest.php && rtk vendor/bin/phpstan analyse --no-progress app/Support/Translation/FrontendTranslations.php`

### TASK-003: Catálogo dates para los formateadores de fecha

Crear `lang/{en,es}/dates.php` con los formatos de dayjs de `shared/lib/dateTime.ts` (`formats.long`, `formats.short`, `formats.full`, `formats.activation_date`, `formats.activation_time`) y sus textos (`never`, `never_lowercase`, `not_available`, `expired`). El español reproduce exactamente los formatos actuales (p. ej. `D [de] MMMM [del] YYYY, h:mm A`).

**Type:** feature · **Priority:** P0 · **Effort:** S · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** —

**Acceptance Criteria:**

- Las dos versiones tienen las mismas claves y ningún valor vacío.
- Los formatos en español son idénticos a las constantes actuales de `dateTime.ts`, así que la vista en español no cambia.

**writeScope:**

- `lang/en/dates.php` (nuevo)
- `lang/es/dates.php` (nuevo)

**validateCommand:** `rtk php -l lang/en/dates.php && rtk php -l lang/es/dates.php`

### TASK-004: HandleInertiaRequests comparte locale y translations

Añadir a `share()` `locale` (`app()->getLocale()`) y `translations` (closure que registra siempre el grupo `dates` y devuelve `FrontendTranslations::toArray()`), y tipar ambos en `SharedData` (`locale: 'en' | 'es'`, `translations: Record<string, TranslationTree>`). Como es un prop compartido, una recarga parcial con `only` no lo pide y React conserva el anterior.

**Type:** feature · **Priority:** P0 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-001, TASK-002, TASK-003

**Acceptance Criteria:**

- Una visita Inertia autenticada con `preferred_locale = en` recibe `locale: 'en'` y `translations.dates` en inglés; con `es`, en español.
- Una recarga parcial que pide solo otro prop no incluye `translations` (no se reenvían catálogos en cada recarga del diálogo de actividad).
- `<html lang>` de `app.blade.php` sigue saliendo de `app()->getLocale()`.

**writeScope:**

- `tests/Feature/Http/HandleInertiaRequestsLocaleTest.php` (nuevo)
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/shared/types/index.d.ts`

**validateCommand:** `rtk php artisan test --compact tests/Feature/Http/HandleInertiaRequestsLocaleTest.php && rtk npm run types`

### TASK-005: Traductor de React: t(), tChoice() y useTranslation()

Crear `shared/lib/translation.ts` (funciones puras: búsqueda `grupo.clave.anidada`, partiendo en el primer punto; marcadores `:name`/`:Name`/`:NAME` como Laravel; plurales `{0} …|{1} …|[2,*] …` y la forma simple `uno|varios`) y `shared/hooks/useTranslation.ts`, que lee `locale` y `translations` de `usePage()` y devuelve `{ t, tChoice, locale }`. Sin estado de módulo, para que el SSR no mezcle idiomas entre peticiones.

**Type:** feature · **Priority:** P0 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-004

**Acceptance Criteria:**

- `tChoice('x', 0|1|5)` elige el tramo correcto para `{0}`, `{1}`, `[2,*]` y la forma `uno|varios`, y rellena `:count`.
- `t('g.k', { name: 'ana' })` sustituye `:name` → `ana`, `:Name` → `Ana` y `:NAME` → `ANA`.
- Una clave o grupo inexistente devuelve la clave tal cual, sin lanzar.

**writeScope:**

- `resources/js/shared/lib/translation.ts` (nuevo)
- `resources/js/shared/hooks/useTranslation.ts` (nuevo)

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/shared/lib/translation.ts resources/js/shared/hooks/useTranslation.ts && rtk npx prettier --check resources/js/shared/lib/translation.ts resources/js/shared/hooks/useTranslation.ts`

### TASK-006: useDateTime(): formateadores de fecha ligados al idioma activo

Añadir a cada función de `shared/lib/dateTime.ts` un último parámetro opcional `DateTimeContext` (`{ locale, texts }`) y formatear con `dayjs(iso).locale(locale)`, nunca con el locale global, para no compartir estado en SSR. Sin contexto mantienen el comportamiento actual en español (compatibilidad temporal que TASK-052 elimina). Crear `shared/hooks/useDateTime.ts`, que construye el contexto desde `useTranslation()` y el grupo `dates` y devuelve las mismas funciones ya ligadas. `app.tsx` no cambia: `relativeTime` ya está extendido y el locale `en` viene con dayjs.

**Type:** feature · **Priority:** P0 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-003, TASK-005

**Acceptance Criteria:**

- Con `locale = en`, `formatLongDate` usa el formato y los nombres de mes en inglés, y `fromNow` dice 'x hours ago'; con `es`, el resultado es idéntico al actual.
- Con fecha `null`, cada función devuelve el texto del catálogo (`never`, `not_available`, `expired`), no un literal.
- Los llamadores existentes que aún no pasan contexto siguen compilando y mostrando español.

**writeScope:**

- `resources/js/shared/hooks/useDateTime.ts` (nuevo)
- `resources/js/shared/lib/dateTime.ts`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/shared/hooks/useDateTime.ts resources/js/shared/lib/dateTime.ts && rtk npx prettier --check resources/js/shared/hooks/useDateTime.ts resources/js/shared/lib/dateTime.ts`

### TASK-007: Catálogo setting/common

Textos compartidos del módulo: `SettingSidebar` y sus navs (incluido el ítem Apariencia, porque el sidebar también se ve en esa página), `SettingLayout`, `TwoFactorOtpField` y `TwoFactorCodeField` (etiquetas, enlace para cambiar de modo, ayudas). Claves con nombre (nunca el texto como clave), plurales con la sintaxis de Laravel y valores como marcadores (`:name`, `:count`). El español conserva el texto actual palabra por palabra.

**Type:** feature · **Priority:** P0 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** —

**Acceptance Criteria:**

- `lang/en` y `lang/es` tienen las mismas claves, ningún valor vacío y los mismos marcadores en cada texto.
- Cada texto que hoy depende de una cantidad tiene una sola clave plural (`{1} …|[2,*] …`) en lugar de dos claves elegidas en el código.
- Las claves en español reproducen el texto actual, así que con `es` la vista no cambia.

**writeScope:**

- `lang/en/setting/common.php` (nuevo)
- `lang/es/setting/common.php` (nuevo)

**validateCommand:** `rtk php -l lang/en/setting/common.php && rtk php -l lang/es/setting/common.php`

### TASK-008: Catálogo setting/account-settings

Textos de Perfil: `AccountSettings.tsx`, las cards, `data/accountSettingsSections.ts`, `AccountActionsPanel`, los diálogos de contraseña y de borrado, el badge de estado (`status.active`/`status.suspended`) y la sección `messages.*` para los flashes de `PasswordController` y de la actualización del perfil. Claves con nombre (nunca el texto como clave), plurales con la sintaxis de Laravel y valores como marcadores (`:name`, `:count`). El español conserva el texto actual palabra por palabra.

**Type:** feature · **Priority:** P0 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** —

**Acceptance Criteria:**

- `lang/en` y `lang/es` tienen las mismas claves, ningún valor vacío y los mismos marcadores en cada texto.
- Cada texto que hoy depende de una cantidad tiene una sola clave plural (`{1} …|[2,*] …`) en lugar de dos claves elegidas en el código.
- Las claves en español reproducen el texto actual, así que con `es` la vista no cambia.

**writeScope:**

- `lang/en/setting/account-settings.php` (nuevo)
- `lang/es/setting/account-settings.php` (nuevo)

**validateCommand:** `rtk php -l lang/en/setting/account-settings.php && rtk php -l lang/es/setting/account-settings.php`

### TASK-009: Catálogo setting/two-factor

Textos de Autenticación en dos pasos: vistas activada/desactivada, `data/twoFactorEnable.ts` y `data/twoFactorDisabled.ts`, los pasos del asistente, `useTwoFactorActivationFlow`/`useTwoFactorAuth`, los códigos de respaldo (incluido el texto del archivo descargado), `RegenerateCodesDialog`, `DisabledTwoFactorDialog` y `TwoFactorActivationDetailsDialog`, más `messages.*` y `validation.*` para `TwoFactorController` y sus FormRequests. Claves con nombre (nunca el texto como clave), plurales con la sintaxis de Laravel y valores como marcadores (`:name`, `:count`). El español conserva el texto actual palabra por palabra.

**Type:** feature · **Priority:** P0 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** —

**Acceptance Criteria:**

- `lang/en` y `lang/es` tienen las mismas claves, ningún valor vacío y los mismos marcadores en cada texto.
- Cada texto que hoy depende de una cantidad tiene una sola clave plural (`{1} …|[2,*] …`) en lugar de dos claves elegidas en el código.
- Las claves en español reproducen el texto actual, así que con `es` la vista no cambia.

**writeScope:**

- `lang/en/setting/two-factor.php` (nuevo)
- `lang/es/setting/two-factor.php` (nuevo)

**validateCommand:** `rtk php -l lang/en/setting/two-factor.php && rtk php -l lang/es/setting/two-factor.php`

### TASK-010: Catálogo setting/trusted-devices (página y backend)

Textos de la página de Dispositivos confiables: vista, resumen, cards, recomendaciones, tabla, columnas, toolbar, filtros, orden, paginación, chips, skeleton, sección de revocados, estados vacíos, `shared/utils/trustedDevice.tsx`; y los del backend: `results.*` (los 10 casos de `TrustedDeviceOperationResult`), `actions.*` (labels de `TrustedDeviceAction`) y `validation.*` (requests, `RequiresTwoFactorOtp`, `HasPasswordConfirmation`, el mensaje de `TrustedDeviceService`). Claves con nombre (nunca el texto como clave), plurales con la sintaxis de Laravel y valores como marcadores (`:name`, `:count`). El español conserva el texto actual palabra por palabra.

**Type:** feature · **Priority:** P0 · **Effort:** L · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** —

**Acceptance Criteria:**

- `lang/en` y `lang/es` tienen las mismas claves, ningún valor vacío y los mismos marcadores en cada texto.
- Cada texto que hoy depende de una cantidad tiene una sola clave plural (`{1} …|[2,*] …`) en lugar de dos claves elegidas en el código.
- Las claves en español reproducen el texto actual, así que con `es` la vista no cambia.

**writeScope:**

- `lang/en/setting/trusted-devices.php` (nuevo)
- `lang/es/setting/trusted-devices.php` (nuevo)

**validateCommand:** `rtk php -l lang/en/setting/trusted-devices.php && rtk php -l lang/es/setting/trusted-devices.php`

### TASK-011: Catálogo setting/trusted-device-dialogs

Textos de los 15 diálogos (`dialog/actions/` e `info/`) y de las piezas del diálogo de actividad (`table/trustedDeviceActivityDialog/`, `data/trustedDeviceActivityFilters.ts`), con un subárbol por diálogo (`add.*`, `revoke_all.*`, `activity.*`…). Claves con nombre (nunca el texto como clave), plurales con la sintaxis de Laravel y valores como marcadores (`:name`, `:count`). El español conserva el texto actual palabra por palabra.

**Type:** feature · **Priority:** P0 · **Effort:** L · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** —

**Acceptance Criteria:**

- `lang/en` y `lang/es` tienen las mismas claves, ningún valor vacío y los mismos marcadores en cada texto.
- Cada texto que hoy depende de una cantidad tiene una sola clave plural (`{1} …|[2,*] …`) en lugar de dos claves elegidas en el código.
- Las claves en español reproducen el texto actual, así que con `es` la vista no cambia.

**writeScope:**

- `lang/en/setting/trusted-device-dialogs.php` (nuevo)
- `lang/es/setting/trusted-device-dialogs.php` (nuevo)

**validateCommand:** `rtk php -l lang/en/setting/trusted-device-dialogs.php && rtk php -l lang/es/setting/trusted-device-dialogs.php`

### TASK-012: Mensajes de validación genéricos en inglés y español

Crear `lang/en/validation.php` (copia del de `laravel/framework` de la versión instalada) y `lang/es/validation.php` traducido, incluidas las reglas de `Password::default()` (`password.*`). Sin `lang:publish`, que publicaría más archivos de los necesarios.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** —

**Acceptance Criteria:**

- Los dos archivos tienen las mismas claves y los mismos marcadores (`:attribute`, `:max`, `:min`…).
- Con locale `es`, un `required` sin mensaje propio sale en español; con `en`, igual que hoy.

**writeScope:**

- `lang/en/validation.php` (nuevo)
- `lang/es/validation.php` (nuevo)

**validateCommand:** `rtk php -l lang/en/validation.php && rtk php -l lang/es/validation.php`

### TASK-013: Registrar los catálogos en las páginas de Perfil, Dispositivos y Apariencia

Inyectar `FrontendTranslations` en `TrustedDeviceIndexController` (`setting/common`, `setting/trusted-devices`, `setting/trusted-device-dialogs`), `AccountSettingsController::edit` (`setting/common`, `setting/account-settings`; además su flash de `update()`, 'Datos actualizados correctamente.', pasa a `TranslatedText::of('setting/account-settings.messages.profile_updated')`) y `PreferenceController` (`setting/common`, solo para el sidebar; la página Apariencia sigue fuera de alcance).

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-002, TASK-007, TASK-008, TASK-010, TASK-011

**Acceptance Criteria:**

- Cada controlador registra exactamente sus grupos antes de `Inertia::render()`.
- Las peticiones parciales del diálogo de actividad siguen devolviendo solo el prop pedido (las pruebas de `TrustedDeviceActivityTest` pasan).

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceIndexController.php`
- `app/User/Modules/AccountSettings/Controllers/AccountSettingsController.php`
- `app/User/Preferences/Controllers/PreferenceController.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Queries app/User/Modules/AccountSettings/Tests/Crud && rtk vendor/bin/phpstan analyse --no-progress app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceIndexController.php app/User/Modules/AccountSettings/Controllers/AccountSettingsController.php app/User/Preferences/Controllers/PreferenceController.php`

### TASK-014: TwoFactor: catálogos de la página, flashes y mensajes de validación

En `TwoFactorController` registrar `setting/common` y `setting/two-factor` y pasar sus flashes a `TranslatedText::of('setting/two-factor.messages.*')`; en `TwoFactorDisableRequest` y `TwoFactorRegenerateRecoveryCodesRequest` cambiar los `message:` literales por `TranslatedText::of('setting/two-factor.validation.*')`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-002, TASK-007, TASK-009

**Acceptance Criteria:**

- Ningún texto visible de los tres archivos queda escrito en PHP.
- Con locale `en`, deshabilitar 2FA con un código inválido devuelve el error `code` en inglés; con `es`, el texto actual.
- Las pruebas de `TwoFactorSettingsTest` (que no comprueban textos) siguen pasando.

**writeScope:**

- `app/User/Modules/TwoFactor/Controllers/TwoFactorController.php`
- `app/User/Modules/TwoFactor/Requests/TwoFactorDisableRequest.php`
- `app/User/Modules/TwoFactor/Requests/TwoFactorRegenerateRecoveryCodesRequest.php`

**validateCommand:** `rtk php artisan test --compact app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php && rtk vendor/bin/phpstan analyse --no-progress app/User/Modules/TwoFactor/Controllers/TwoFactorController.php app/User/Modules/TwoFactor/Requests/TwoFactorDisableRequest.php app/User/Modules/TwoFactor/Requests/TwoFactorRegenerateRecoveryCodesRequest.php`

### TASK-015: Pruebas de idioma y catálogos de las páginas de configuración

Crear `tests/Feature/Translation/SettingPageTranslationsTest.php`: cada página (Perfil, 2FA, Dispositivos, Apariencia) comparte `locale` y exactamente sus grupos en los dos idiomas; cambiar `preferred_locale` con `PATCH profile` hace que el siguiente `GET` llegue en el idioma nuevo.

**Type:** test · **Priority:** P1 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-004, TASK-013, TASK-014

**Acceptance Criteria:**

- Para cada página y cada idioma, `translations` contiene sus grupos y no los de otra página.
- Tras `PATCH profile` con `preferred_locale = en` desde `es`, el `GET` siguiente devuelve `locale: 'en'` y textos ingleses, sin recargar nada más.
- Un invitado que pide `/setting/profile` es redirigido al login sin error de traducción.

**writeScope:**

- `tests/Feature/Translation/SettingPageTranslationsTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact tests/Feature/Translation/SettingPageTranslationsTest.php`

### TASK-016: TrustedDevice: flashes de resultado y labels de acción traducidos

Cambiar `TrustedDeviceOperationResult::message()` y los labels de `TrustedDeviceAction` a `TranslatedText::of('setting/trusted-devices.results.*' | '.actions.*')`; los casos y `payload()` no cambian de forma. Crear `Tests/Translations/TrustedDeviceTranslatedMessagesTest.php` (dentro de la suite TrustedDevice existente).

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-010

**Acceptance Criteria:**

- Cada caso de los dos enums devuelve un texto distinto de su clave en `en` y en `es`, y el texto inglés difiere del español.
- `payload()` conserva la forma `{type, message}` y `isSuccessful()` no cambia (`TrustedDeviceServiceTest` pasa).

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Tests/Translations/TrustedDeviceTranslatedMessagesTest.php` (nuevo)
- `app/Auth/Modules/TrustedDevice/Enums/TrustedDeviceOperationResult.php`
- `app/Auth/Modules/TrustedDevice/Enums/TrustedDeviceAction.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Translations app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php && rtk vendor/bin/phpstan analyse --no-progress app/Auth/Modules/TrustedDevice/Enums/TrustedDeviceOperationResult.php app/Auth/Modules/TrustedDevice/Enums/TrustedDeviceAction.php`

### TASK-017: TrustedDevice: mensajes de los concerns y del servicio

Pasar a `TranslatedText::of('setting/trusted-devices.validation.*')` los textos de `RequiresTwoFactorOtp`, `HasPasswordConfirmation` y el único literal de `TrustedDeviceService`. Las pruebas que hoy comparan estos textos en español se actualizan en TASK-019.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-010

**Acceptance Criteria:**

- Ningún texto visible de los tres archivos queda escrito en PHP.
- Los nombres de los campos de error (`two_factor`, `otp_code`, `password`) no cambian.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Concerns/RequiresTwoFactorOtp.php`
- `app/Auth/Modules/TrustedDevice/Concerns/HasPasswordConfirmation.php`
- `app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Services && rtk vendor/bin/phpstan analyse --no-progress app/Auth/Modules/TrustedDevice/Concerns/RequiresTwoFactorOtp.php app/Auth/Modules/TrustedDevice/Concerns/HasPasswordConfirmation.php app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php`

### TASK-018: TrustedDevice: mensajes propios de los FormRequests

Cambiar los `message:` literales de `TrustedDeviceReactivateRequest`, `TrustedDeviceStoreRequest` y `TrustedDeviceUpdateRequest` por `TranslatedText::of('setting/trusted-devices.validation.*')`, con `:max` como marcador en vez del número escrito en el texto.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-010

**Acceptance Criteria:**

- Ningún `message:` de los tres archivos es un literal.
- Con locale `en`, renombrar un dispositivo con 51 caracteres devuelve el error `name` en inglés con el límite 50 sustituido.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceReactivateRequest.php`
- `app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceStoreRequest.php`
- `app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceUpdateRequest.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceUpdateTest.php && rtk vendor/bin/phpstan analyse --no-progress app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceReactivateRequest.php app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceStoreRequest.php app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceUpdateRequest.php`

### TASK-019: TrustedDevice: pruebas de errores en los dos idiomas

Actualizar las aserciones en español de `TrustedDeviceReactivateTest`, `TrustedDeviceRenewTest` y `TrustedDeviceStoreTest`: comparar con `TranslatedText::of()` de la clave y añadir un caso por idioma (usuario con `preferred_locale` `en` y `es`).

**Type:** test · **Priority:** P1 · **Effort:** S · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-001, TASK-017, TASK-018

**Acceptance Criteria:**

- Ninguna de las tres pruebas compara con un texto en español escrito a mano.
- Un código OTP incorrecto devuelve el error `otp_code` en inglés para un usuario `en` y en español para uno `es`.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceReactivateTest.php`
- `app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceRenewTest.php`
- `app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceStoreTest.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceReactivateTest.php app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceRenewTest.php app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceStoreTest.php`

### TASK-020: TwoFactor: pruebas de errores en los dos idiomas

Actualizar las aserciones en español de `TwoFactorDisableTest` y `TwoFactorRecoveryCodesTest` igual que TASK-019, cubriendo la contraseña incorrecta y el código inválido.

**Type:** test · **Priority:** P1 · **Effort:** S · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-001, TASK-014

**Acceptance Criteria:**

- Ninguna de las dos pruebas compara con un texto en español escrito a mano.
- La contraseña incorrecta al regenerar códigos devuelve el error en inglés para un usuario `en` y en español para uno `es`.

**writeScope:**

- `app/User/Modules/TwoFactor/Tests/TwoFactorDisableTest.php`
- `app/User/Modules/TwoFactor/Tests/TwoFactorRecoveryCodesTest.php`

**validateCommand:** `rtk php artisan test --compact app/User/Modules/TwoFactor/Tests`

### TASK-021: Password: flash traducido y sin locale 'es' forzado

`PasswordController` pasa su flash a `TranslatedText::of('setting/account-settings.messages.*')`. En `PasswordNewController` y `PasswordResetLinkController` (ampliación EXPANSION) quitar el `'es'` forzado de `__()` para que usen el idioma activo; las claves de `lang/*/passwords.php` y `messages.php` no cambian (renombrarlas es de la tarea de auth).

**Type:** feature · **Priority:** P2 · **Effort:** S · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-008

**Acceptance Criteria:**

- Ninguno de los tres controladores fuerza un locale.
- Un invitado sin `Accept-Language` sigue recibiendo los mensajes de restablecimiento en español (idioma por defecto del middleware).

**writeScope:**

- `app/Auth/Modules/Password/Controllers/PasswordController.php`
- `app/Auth/Modules/Password/Controllers/PasswordNewController.php`
- `app/Auth/Modules/Password/Controllers/PasswordResetLinkController.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/Password/Tests/PasswordUpdateTest.php && rtk vendor/bin/phpstan analyse --no-progress app/Auth/Modules/Password/Controllers/PasswordController.php app/Auth/Modules/Password/Controllers/PasswordNewController.php app/Auth/Modules/Password/Controllers/PasswordResetLinkController.php`

### TASK-022: Password: pruebas en los dos idiomas

Actualizar `PasswordResetTest` y `PasswordUpdateTest`: el caso actual queda como invitado sin encabezado (`es`) y se añade uno con `Accept-Language: en` y uno con usuario `en` para el flash de cambio de contraseña.

**Type:** test · **Priority:** P2 · **Effort:** S · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-001, TASK-021

**Acceptance Criteria:**

- El enlace de restablecimiento responde en inglés con `Accept-Language: en` y en español sin encabezado.
- El flash de contraseña actualizada sale en el idioma del usuario.

**writeScope:**

- `app/Auth/Modules/Password/Tests/PasswordResetTest.php`
- `app/Auth/Modules/Password/Tests/PasswordUpdateTest.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/Password/Tests`

### TASK-023: El registro guarda el idioma resuelto del navegador

En `RegisterUserController` guardar `preferred_locale = PreferredLocale::from(app()->getLocale())` (lo que ya resolvió TASK-001), para que un usuario nuevo no quede en el default `en` de la columna. La columna y `UserFactory` no cambian (no hay producción, no se migran datos). `UserSeeder` crea a Angel con `preferred_locale = es`. Actualizar `RegistrationTest`.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-001

**Acceptance Criteria:**

- Registrarse con `Accept-Language: es-MX` o sin encabezado guarda `es`; con `en-US`, guarda `en`.
- Un `preferred_locale` enviado en el formulario de registro se ignora (no está validado ni es asignable).

**writeScope:**

- `app/Auth/Modules/Register/Controllers/RegisterUserController.php`
- `app/Auth/Modules/Register/Tests/RegistrationTest.php`
- `app/User/Database/Seeders/UserSeeder.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/Register/Tests/RegistrationTest.php && rtk vendor/bin/phpstan analyse --no-progress app/Auth/Modules/Register/Controllers/RegisterUserController.php app/User/Database/Seeders/UserSeeder.php`

### TASK-024: Posts: fechas en el idioma activo

Cambiar los imports de `@/shared/lib/dateTime` por `useDateTime()`. Solo las fechas siguen el idioma; el resto de textos del módulo sigue en español hasta su propia tarea.

**Type:** feature · **Priority:** P2 · **Effort:** S · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-006

**Acceptance Criteria:**

- Con `locale = es` las fechas se ven igual que hoy; con `en`, en inglés.
- Ningún archivo importa formateadores de `@/shared/lib/dateTime` directamente.

**writeScope:**

- `resources/js/modules/post/components/PostCard.tsx`
- `resources/js/modules/post/components/PostDetail.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/post/components/PostCard.tsx resources/js/modules/post/components/PostDetail.tsx && rtk npx prettier --check resources/js/modules/post/components/PostCard.tsx resources/js/modules/post/components/PostDetail.tsx`

### TASK-025: Comentarios: fechas en el idioma activo

Cambiar los imports de `@/shared/lib/dateTime` por `useDateTime()`. Solo las fechas siguen el idioma; el resto de textos del módulo sigue en español hasta su propia tarea.

**Type:** feature · **Priority:** P2 · **Effort:** S · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-006

**Acceptance Criteria:**

- Con `locale = es` las fechas se ven igual que hoy; con `en`, en inglés.
- Ningún archivo importa formateadores de `@/shared/lib/dateTime` directamente.

**writeScope:**

- `resources/js/modules/comment/components/CommentItem.tsx`
- `resources/js/modules/comment/components/dialog/CommentDialog.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/comment/components/CommentItem.tsx resources/js/modules/comment/components/dialog/CommentDialog.tsx && rtk npx prettier --check resources/js/modules/comment/components/CommentItem.tsx resources/js/modules/comment/components/dialog/CommentDialog.tsx`

### TASK-026: Sidebar y layout de configuración, sin el relleno de la plantilla

Traducir `SettingSidebar`, `SettingSidebarNavMain` y `SettingLayout` con `setting/common`; los títulos de los ítems pasan a ser claves del arreglo estático. Quitar el relleno de la plantilla de shadcn en vez de traducirlo (decisión del usuario, 2026-10-29): la cabecera del sidebar muestra el `name` compartido (nombre de la app) en lugar de 'Acme Inc'/'Enterprise'; se elimina el pie con `SettingSidebarNavUser` y su usuario de ejemplo (el archivo lo borra TASK-058); y se elimina el breadcrumb de ejemplo ('Build Your Application'/'Data Fetching') junto con su separador, conservando el `SidebarTrigger`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-007

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/common` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- No quedan 'Acme Inc', 'Enterprise', el usuario `shadcn`/`m@example.com` ni el breadcrumb de ejemplo; en Apariencia (fuera de alcance) el sidebar también sale traducido, porque `PreferenceController` registra `setting/common` (TASK-013).

**writeScope:**

- `resources/js/modules/setting/shared/components/SettingSidebar.tsx`
- `resources/js/modules/setting/shared/components/SettingSidebarNavMain.tsx`
- `resources/js/modules/setting/shared/layouts/SettingLayout.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/shared/components/SettingSidebar.tsx resources/js/modules/setting/shared/components/SettingSidebarNavMain.tsx resources/js/modules/setting/shared/layouts/SettingLayout.tsx && rtk npx prettier --check resources/js/modules/setting/shared/components/SettingSidebar.tsx resources/js/modules/setting/shared/components/SettingSidebarNavMain.tsx resources/js/modules/setting/shared/layouts/SettingLayout.tsx`

### TASK-027: Campos de código 2FA compartidos

Traducir `TwoFactorOtpField` y `TwoFactorCodeField` (etiquetas, `aria-label`, ayudas y el enlace para cambiar entre TOTP y código de respaldo).

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-007

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/common` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.

**writeScope:**

- `resources/js/modules/setting/shared/components/TwoFactorOtpField.tsx`
- `resources/js/modules/setting/shared/components/TwoFactorCodeField.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/shared/components/TwoFactorOtpField.tsx resources/js/modules/setting/shared/components/TwoFactorCodeField.tsx && rtk npx prettier --check resources/js/modules/setting/shared/components/TwoFactorOtpField.tsx resources/js/modules/setting/shared/components/TwoFactorCodeField.tsx`

### TASK-028: Perfil: vista, secciones y panel de acciones

Traducir `AccountSettings.tsx`, `AccountActionsPanel` y `data/accountSettingsSections.ts`. Contrato de los `data/*.ts`: conservan su forma y nombres de campo, pero los textos pasan a ser claves que el componente envuelve con `t()`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-008

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/account-settings` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.

**writeScope:**

- `resources/js/modules/setting/modules/accountSettings/AccountSettings.tsx`
- `resources/js/modules/setting/modules/accountSettings/components/AccountActionsPanel.tsx`
- `resources/js/modules/setting/modules/accountSettings/data/accountSettingsSections.ts`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/accountSettings/AccountSettings.tsx resources/js/modules/setting/modules/accountSettings/components/AccountActionsPanel.tsx resources/js/modules/setting/modules/accountSettings/data/accountSettingsSections.ts && rtk npx prettier --check resources/js/modules/setting/modules/accountSettings/AccountSettings.tsx resources/js/modules/setting/modules/accountSettings/components/AccountActionsPanel.tsx resources/js/modules/setting/modules/accountSettings/data/accountSettingsSections.ts`

### TASK-029: Perfil: formulario y card de resumen

Traducir `AccountSettingsProfileForm` (incluido el selector de idioma, cuyas opciones muestran cada idioma en su propio nombre) y `AccountSettingsSummaryCard` (badge 'Activa'/'Suspendida' y fechas).

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-008

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/account-settings` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Las fechas usan `useDateTime()` (formatos y 'Nunca'/'No disponible'/'vencido' del catálogo `dates`) en lugar de importar los formateadores de `@/shared/lib/dateTime` sin contexto.

**writeScope:**

- `resources/js/modules/setting/modules/accountSettings/components/AccountSettingsProfileForm.tsx`
- `resources/js/modules/setting/modules/accountSettings/components/AccountSettingsSummaryCard.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/accountSettings/components/AccountSettingsProfileForm.tsx resources/js/modules/setting/modules/accountSettings/components/AccountSettingsSummaryCard.tsx && rtk npx prettier --check resources/js/modules/setting/modules/accountSettings/components/AccountSettingsProfileForm.tsx resources/js/modules/setting/modules/accountSettings/components/AccountSettingsSummaryCard.tsx`

### TASK-030: Perfil: diálogos de contraseña y de borrado

Traducir `AccountSettingsPasswordDialog` y `AccountSettingsDeleteDialog`; los errores de validación ya llegan traducidos del servidor.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-008

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/account-settings` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.

**writeScope:**

- `resources/js/modules/setting/modules/accountSettings/components/AccountSettingsPasswordDialog.tsx`
- `resources/js/modules/setting/modules/accountSettings/components/AccountSettingsDeleteDialog.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/accountSettings/components/AccountSettingsPasswordDialog.tsx resources/js/modules/setting/modules/accountSettings/components/AccountSettingsDeleteDialog.tsx && rtk npx prettier --check resources/js/modules/setting/modules/accountSettings/components/AccountSettingsPasswordDialog.tsx resources/js/modules/setting/modules/accountSettings/components/AccountSettingsDeleteDialog.tsx`

### TASK-031: 2FA: vista desactivada y sus opciones

Traducir `views/TwoFactorDisabled.tsx`, `data/twoFactorDisabled.ts` y `components/ui/OptionCard.tsx`, con el contrato de `data/*.ts` de TASK-028.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-009

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/two-factor` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/views/TwoFactorDisabled.tsx`
- `resources/js/modules/setting/modules/twoFactor/data/twoFactorDisabled.ts`
- `resources/js/modules/setting/modules/twoFactor/components/ui/OptionCard.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/twoFactor/views/TwoFactorDisabled.tsx resources/js/modules/setting/modules/twoFactor/data/twoFactorDisabled.ts resources/js/modules/setting/modules/twoFactor/components/ui/OptionCard.tsx && rtk npx prettier --check resources/js/modules/setting/modules/twoFactor/views/TwoFactorDisabled.tsx resources/js/modules/setting/modules/twoFactor/data/twoFactorDisabled.ts resources/js/modules/setting/modules/twoFactor/components/ui/OptionCard.tsx`

### TASK-032: 2FA: vista activada y dispositivos

Traducir `views/TwoFactorEnable.tsx`, `data/twoFactorEnable.ts` y `TwoFactorDevices.tsx` (que también lee `data/trustedDeviceOverview.ts`: sus etiquetas se envuelven con `t()` aunque TASK-037 aún no las haya convertido en claves).

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-009

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/two-factor` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Las fechas usan `useDateTime()` (formatos y 'Nunca'/'No disponible'/'vencido' del catálogo `dates`) en lugar de importar los formateadores de `@/shared/lib/dateTime` sin contexto.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/views/TwoFactorEnable.tsx`
- `resources/js/modules/setting/modules/twoFactor/data/twoFactorEnable.ts`
- `resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/twoFactor/views/TwoFactorEnable.tsx resources/js/modules/setting/modules/twoFactor/data/twoFactorEnable.ts resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx && rtk npx prettier --check resources/js/modules/setting/modules/twoFactor/views/TwoFactorEnable.tsx resources/js/modules/setting/modules/twoFactor/data/twoFactorEnable.ts resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx`

### TASK-033: 2FA: códigos de respaldo y regeneración

Traducir `TwoFactorRecoveryCodes`, `utils/downloadRecoveryCodes.ts` (el encabezado del archivo descargado; recibe los textos ya traducidos como parámetro, porque no es un hook) y `RegenerateCodesDialog`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-009

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/two-factor` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- El archivo descargado con `locale = en` tiene encabezado e instrucciones en inglés; los códigos no cambian.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorRecoveryCodes.tsx`
- `resources/js/modules/setting/modules/twoFactor/utils/downloadRecoveryCodes.ts`
- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/RegenerateCodesDialog.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorRecoveryCodes.tsx resources/js/modules/setting/modules/twoFactor/utils/downloadRecoveryCodes.ts resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/RegenerateCodesDialog.tsx && rtk npx prettier --check resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorRecoveryCodes.tsx resources/js/modules/setting/modules/twoFactor/utils/downloadRecoveryCodes.ts resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/RegenerateCodesDialog.tsx`

### TASK-034: 2FA: pasos de elección, configuración manual y verificación

Traducir `ChooseMethodStep`, `ManualSetupStep` y `VerifyOtpStep` del asistente de activación.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-009

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/two-factor` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/ChooseMethodStep.tsx`
- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/ManualSetupStep.tsx`
- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/VerifyOtpStep.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/ChooseMethodStep.tsx resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/ManualSetupStep.tsx resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/VerifyOtpStep.tsx && rtk npx prettier --check resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/ChooseMethodStep.tsx resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/ManualSetupStep.tsx resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/VerifyOtpStep.tsx`

### TASK-035: 2FA: paso final, alertas y flujo de activación

Traducir `TwoFactorSuccessStep`, `TwoFactorStepErrorAlert` y los textos que arma `useTwoFactorActivationFlow` (títulos de paso y errores locales de red/429).

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-009

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/two-factor` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Un error de red durante la activación muestra el mensaje local en el idioma activo, no el modal de error de Inertia.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/TwoFactorSuccessStep.tsx`
- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/TwoFactorStepErrorAlert.tsx`
- `resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorActivationFlow.ts`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/TwoFactorSuccessStep.tsx resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/TwoFactorStepErrorAlert.tsx resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorActivationFlow.ts && rtk npx prettier --check resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/TwoFactorSuccessStep.tsx resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/TwoFactorStepErrorAlert.tsx resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorActivationFlow.ts`

### TASK-036: 2FA: deshabilitar, detalles de activación y useTwoFactorAuth

Traducir `DisabledTwoFactorDialog`, `TwoFactorActivationDetailsDialog` y los textos de `useTwoFactorAuth`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-009

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/two-factor` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Las fechas usan `useDateTime()` (formatos y 'Nunca'/'No disponible'/'vencido' del catálogo `dates`) en lugar de importar los formateadores de `@/shared/lib/dateTime` sin contexto.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/DisabledTwoFactorDialog.tsx`
- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog.tsx`
- `resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorAuth.ts`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/DisabledTwoFactorDialog.tsx resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog.tsx resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorAuth.ts && rtk npx prettier --check resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/DisabledTwoFactorDialog.tsx resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog.tsx resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorAuth.ts`

### TASK-037: Dispositivos: vista, overview y resumen

Traducir `TrustedDevice.tsx` (incluida la sección colapsable de revocados y sus estados vacíos), `data/trustedDeviceOverview.ts` y `TrustedDeviceSummary`, con el contrato de `data/*.ts` de TASK-028.

**Type:** feature · **Priority:** P1 · **Effort:** L · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-010

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-devices` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Los conteos ('N dispositivos') usan `tChoice()` y son correctos para 0, 1 y varios.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx`
- `resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceOverview.ts`
- `resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummary.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceOverview.ts resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummary.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceOverview.ts resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummary.tsx`

### TASK-038: Dispositivos: cards de resumen, info y actividad reciente

Traducir `TrustedDeviceSummaryCard`, `TrustedDeviceInfoCard` y `TrustedDeviceRecentActivity`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-010

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-devices` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Las fechas usan `useDateTime()` (formatos y 'Nunca'/'No disponible'/'vencido' del catálogo `dates`) en lugar de importar los formateadores de `@/shared/lib/dateTime` sin contexto.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummaryCard.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceInfoCard.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecentActivity.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummaryCard.tsx resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceInfoCard.tsx resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecentActivity.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceSummaryCard.tsx resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceInfoCard.tsx resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecentActivity.tsx`

### TASK-039: Dispositivos: recomendaciones

Traducir `data/trustedDeviceRecommendations.ts`, `TrustedDeviceRecommendations` y `TrustedDeviceRecommendationsDialog`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-010, TASK-011

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-devices` y `setting/trusted-device-dialogs` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceRecommendations.ts`
- `resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecommendations.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceRecommendationsDialog.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceRecommendations.ts resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecommendations.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceRecommendationsDialog.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceRecommendations.ts resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecommendations.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceRecommendationsDialog.tsx`

### TASK-040: Dispositivos: tabla, columnas y skeleton

Traducir `TrustedDeviceTable` (estados vacíos según `committedFilters`), `data/trustedDeviceTableColumns.ts` y `TrustedDeviceTableSkeleton`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-010

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-devices` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Las fechas usan `useDateTime()` (formatos y 'Nunca'/'No disponible'/'vencido' del catálogo `dates`) en lugar de importar los formateadores de `@/shared/lib/dateTime` sin contexto.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTable.tsx`
- `resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceTableColumns.ts`
- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTableSkeleton.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTable.tsx resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceTableColumns.ts resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTableSkeleton.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTable.tsx resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceTableColumns.ts resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTableSkeleton.tsx`

### TASK-041: Dispositivos: toolbar, filtros y orden

Traducir `TrustedDeviceTableToolbar`, `data/trustedDeviceFilters.ts` y `data/trustedDeviceSort.ts`. Los `value` de filtros y orden no cambian: viajan a la URL y al backend.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-010

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-devices` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- La URL de filtros (`status`, `browser`, `lastAccess`, orden) es idéntica en los dos idiomas.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTableToolbar.tsx`
- `resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceFilters.ts`
- `resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceSort.ts`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTableToolbar.tsx resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceFilters.ts resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceSort.ts && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceTableToolbar.tsx resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceFilters.ts resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceSort.ts`

### TASK-042: Dispositivos: paginación y chips de filtros

Traducir `TrustedDevicePagination`, `TrustedDeviceActiveFilterChips` y `TrustedDeviceActiveFilterChipGroup`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-010

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-devices` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- 'Mostrando X–Y de Z' usa marcadores y `tChoice()`, sin concatenar fragmentos traducidos.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDevicePagination.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceActiveFilterChips.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/table/TrustedDeviceActiveFilterChipGroup.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDevicePagination.tsx resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceActiveFilterChips.tsx resources/js/modules/setting/modules/trustedDevice/components/table/TrustedDeviceActiveFilterChipGroup.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDevicePagination.tsx resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceActiveFilterChips.tsx resources/js/modules/setting/modules/trustedDevice/components/table/TrustedDeviceActiveFilterChipGroup.tsx`

### TASK-043: Dispositivos: redimensionado de columnas y util compartida

Traducir `TrustedDeviceColumnResizeHandle` (`aria-label`) y `setting/shared/utils/trustedDevice.tsx`.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-010

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-devices` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceColumnResizeHandle.tsx`
- `resources/js/modules/setting/shared/utils/trustedDevice.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceColumnResizeHandle.tsx resources/js/modules/setting/shared/utils/trustedDevice.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/table/trustedDevicePage/TrustedDeviceColumnResizeHandle.tsx resources/js/modules/setting/shared/utils/trustedDevice.tsx`

### TASK-044: Diálogos: añadir, ya registrado y expirado

Traducir `TrustedDeviceAddDialog`, `TrustedDeviceAlreadyRegisteredDialog`, `TrustedDeviceExpiredDialog` con `setting/trusted-device-dialogs` (los textos comunes, como Cancelar, de `setting/common`).

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-011

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-device-dialogs` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Las fechas usan `useDateTime()` (formatos y 'Nunca'/'No disponible'/'vencido' del catálogo `dates`) en lugar de importar los formateadores de `@/shared/lib/dateTime` sin contexto.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAddDialog.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAlreadyRegisteredDialog.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceExpiredDialog.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAddDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAlreadyRegisteredDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceExpiredDialog.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAddDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAlreadyRegisteredDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceExpiredDialog.tsx`

### TASK-045: Diálogos: detalles, renombrar y revocado

Traducir `TrustedDeviceDetailsDialog`, `TrustedDeviceRenameDialog`, `TrustedDeviceRevokedDialog` con `setting/trusted-device-dialogs` (los textos comunes, como Cancelar, de `setting/common`).

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-011

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-device-dialogs` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Las fechas usan `useDateTime()` (formatos y 'Nunca'/'No disponible'/'vencido' del catálogo `dates`) en lugar de importar los formateadores de `@/shared/lib/dateTime` sin contexto.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceDetailsDialog.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRenameDialog.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRevokedDialog.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceDetailsDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRenameDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRevokedDialog.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceDetailsDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRenameDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRevokedDialog.tsx`

### TASK-046: Diálogos: renovar, revocar y revocar todos

Traducir `TrustedDeviceRenewTrustDialog`, `TrustedDeviceRevokeDialog`, `TrustedDeviceRevokeAllDialog` con `setting/trusted-device-dialogs` (los textos comunes, como Cancelar, de `setting/common`).

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-011

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-device-dialogs` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Las fechas usan `useDateTime()` (formatos y 'Nunca'/'No disponible'/'vencido' del catálogo `dates`) en lugar de importar los formateadores de `@/shared/lib/dateTime` sin contexto. `TrustedDeviceRenewTrustDialog` deja de usar `dayjs` directo.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRenewTrustDialog.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRevokeDialog.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRevokeAllDialog.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRenewTrustDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRevokeDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRevokeAllDialog.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRenewTrustDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRevokeDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRevokeAllDialog.tsx`

### TASK-047: Diálogos: eliminar definitivamente, reactivar y atajos

Traducir `TrustedDeviceForceDestroyDialog`, `TrustedDeviceReactivationDialog`, `TrustedDeviceKeyboardShortcutsDialog` con `setting/trusted-device-dialogs` (los textos comunes, como Cancelar, de `setting/common`).

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-011

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-device-dialogs` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Las fechas usan `useDateTime()` (formatos y 'Nunca'/'No disponible'/'vencido' del catálogo `dates`) en lugar de importar los formateadores de `@/shared/lib/dateTime` sin contexto.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceForceDestroyDialog.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceReactivationDialog.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceKeyboardShortcutsDialog.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceForceDestroyDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceReactivationDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceKeyboardShortcutsDialog.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceForceDestroyDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceReactivationDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceKeyboardShortcutsDialog.tsx`

### TASK-048: Diálogos: resumen y actividad

Traducir `TrustedDeviceSummaryDialog`, `TrustedDeviceActivityDialog` con `setting/trusted-device-dialogs` (los textos comunes, como Cancelar, de `setting/common`).

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-011

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-device-dialogs` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- El diálogo de actividad sigue sin cambiar la URL del navegador y sigue mostrando el spinner mientras llega `activityDialog`.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceSummaryDialog.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceActivityDialog.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceSummaryDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceActivityDialog.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceSummaryDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceActivityDialog.tsx`

### TASK-049: Actividad: lista de eventos, popover de filtros y sus datos

Traducir `TrustedDeviceActivityEventList`, `TrustedDeviceActivityFiltersPopover` y `data/trustedDeviceActivityFilters.ts`. Los valores `action` y `since_days` no cambian.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-006, TASK-011

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-device-dialogs` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.
- Las fechas usan `useDateTime()` (formatos y 'Nunca'/'No disponible'/'vencido' del catálogo `dates`) en lugar de importar los formateadores de `@/shared/lib/dateTime` sin contexto.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityEventList.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityFiltersPopover.tsx`
- `resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters.ts`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityEventList.tsx resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityFiltersPopover.tsx resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters.ts && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityEventList.tsx resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityFiltersPopover.tsx resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceActivityFilters.ts`

### TASK-050: Actividad: combobox, paginación y chips

Traducir `TrustedDeviceActivityFilterCombobox`, `TrustedDeviceActivityPagination` y `TrustedDeviceActivityActiveFilterChips`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-005, TASK-011

**Acceptance Criteria:**

- Con `locale = en` los textos visibles de estos archivos salen en inglés y con `es` en español, desde `setting/trusted-device-dialogs` vía `t()`/`tChoice()`; no queda ningún texto visible literal.
- Una clave inexistente no rompe el render: se muestra la clave cruda (el servidor ya fusionó `fallback_locale`), y `npm run types`, ESLint y Prettier pasan.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityFilterCombobox.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityPagination.tsx`
- `resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityActiveFilterChips.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityFilterCombobox.tsx resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityPagination.tsx resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityActiveFilterChips.tsx && rtk npx prettier --check resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityFilterCombobox.tsx resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityPagination.tsx resources/js/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityActiveFilterChips.tsx`

### TASK-058: Borrar SettingSidebarNavUser, el menú de usuario de ejemplo

Eliminar `setting/shared/components/SettingSidebarNavUser.tsx` (relleno de la plantilla de shadcn con un usuario falso y 'Upgrade to Pro', 'Account', 'Billing', 'Notifications', 'Log out' sin acción), que deja de usarse cuando TASK-026 quita el pie del sidebar. Decisión del usuario del 2026-10-29: se quita en vez de traducirse.

**Type:** chore · **Priority:** P2 · **Effort:** S · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-026

**Acceptance Criteria:**

- El archivo no existe y ningún archivo de `resources/js` lo importa.
- `npm run types`, ESLint y `npm run build` siguen pasando sin el componente.

**writeScope:**

- `resources/js/modules/setting/shared/components/SettingSidebarNavUser.tsx`

**validateCommand:** `rtk npm run types && rtk npm run lint:check`

### TASK-051: Paridad de claves y marcadores para setting/, dates y validation

Mover a `tests/Pest.php` las funciones de `AdminTranslationKeysTest` que aplanan un catálogo y extraen marcadores (con nombres genéricos), hacer que `AdminTranslationKeysTest` las use sin cambiar lo que comprueba, y crear `SettingTranslationKeysTest` para todo `lang/{en,es}/setting/*.php`, `dates.php` y `validation.php`.

**Type:** test · **Priority:** P1 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-003, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012

**Acceptance Criteria:**

- Una clave presente en un idioma y ausente en el otro, un valor vacío o un marcador distinto hace fallar la prueba con el archivo y la clave.
- `AdminTranslationKeysTest` sigue pasando con el mismo número de aserciones.
- Un archivo nuevo en `lang/en/setting/` sin su par en `lang/es/setting/` hace fallar la prueba.

**writeScope:**

- `tests/Feature/Translation/SettingTranslationKeysTest.php` (nuevo)
- `tests/Pest.php`
- `app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

**validateCommand:** `rtk php artisan test --compact tests/Feature/Translation/SettingTranslationKeysTest.php app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

### TASK-052: dateTime exige el contexto de idioma

Quitar el valor por defecto en español del parámetro `DateTimeContext` de `shared/lib/dateTime.ts` (el puente temporal de TASK-006) y borrar sus textos literales. `npm run types` prueba que ningún llamador quedó sin migrar.

**Type:** refactor · **Priority:** P2 · **Effort:** S · **Agent:** `react-vite-tailwind-engineer` · **Review:** claude

**Depends on:** TASK-024, TASK-025, TASK-029, TASK-032, TASK-033, TASK-036, TASK-038, TASK-040, TASK-044, TASK-045, TASK-046, TASK-047, TASK-049

**Acceptance Criteria:**

- `dateTime.ts` no contiene ningún texto en español ni formato literal.
- Un import de un formateador sin contexto deja de compilar.

**writeScope:**

- `resources/js/shared/lib/dateTime.ts`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/shared/lib/dateTime.ts && rtk npx prettier --check resources/js/shared/lib/dateTime.ts`

### TASK-053: Cada clave usada en React existe en los dos idiomas

Crear `FrontendTranslationKeysTest`: busca en `resources/js/**/*.{ts,tsx}` todo literal con forma de clave (`setting/<grupo>.<clave>` o `dates.<clave>`), incluidos los de `data/*.ts`, y exige que exista como texto en `en` y en `es`. Suple el tipado de claves en TypeScript: una errata falla la suite.

**Type:** test · **Priority:** P1 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-026, TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032, TASK-033, TASK-034, TASK-035, TASK-036, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048, TASK-049, TASK-050, TASK-058

**Acceptance Criteria:**

- Una clave mal escrita en un `.tsx` o un `data/*.ts` hace fallar la prueba con el archivo, la línea y la clave.
- Una clave que apunta a un grupo (array) en vez de a un texto también falla.

**writeScope:**

- `tests/Feature/Translation/FrontendTranslationKeysTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact tests/Feature/Translation/FrontendTranslationKeysTest.php`

### TASK-054: Ningún texto fijo en las vistas traducidas

Crear `SettingHardcodedTextTest`, como `AdminHardcodedTextTest`, sobre `resources/js/modules/setting/{shared,modules/accountSettings,modules/twoFactor,modules/trustedDevice}`: falla con texto JSX que contiene letras, con literales en `title`, `placeholder`, `aria-label`, `alt`, `label`, `description` y con cualquier literal con caracteres del español. Una lista explícita y comentada permite excepciones (nombres propios, `className`, rutas).

**Type:** test · **Priority:** P1 · **Effort:** M · **Agent:** `laravel-senior-engineer` · **Review:** claude

**Depends on:** TASK-026, TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032, TASK-033, TASK-034, TASK-035, TASK-036, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048, TASK-049, TASK-050, TASK-058

**Acceptance Criteria:**

- Añadir `<p>Hola</p>` o `placeholder="Buscar"` a un archivo del alcance hace fallar la prueba con archivo y línea.
- `preference/` (Apariencia) y los módulos fuera de alcance no se analizan.
- Todo el alcance actual pasa sin entradas en la lista de excepciones salvo las justificadas en el propio test.

**writeScope:**

- `tests/Feature/Translation/SettingHardcodedTextTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact tests/Feature/Translation/SettingHardcodedTextTest.php`

### TASK-055: Documentar la decisión en CLAUDE.md, AGENTS.md y la regla de frontend

Describir: `lang/` como única fuente; por qué no hay paquete (laravel-react-i18n arrastra React 18; @erag/lang-sync-inertia exige Vue y Svelte como peers y no aplica fallback); `SetApplicationLocale` y el idioma por defecto `es`; `FrontendTranslations` y cómo una página registra sus grupos; `useTranslation`/`useDateTime`; el contrato de `data/*.ts`; los catálogos de `setting/`; y la convención para textos nuevos de React (regla en `.ai/rules/frontend.md`).

**Type:** docs · **Priority:** P3 · **Effort:** M · **Agent:** `general-purpose` · **Review:** claude

**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-026, TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032, TASK-033, TASK-034, TASK-035, TASK-036, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048, TASK-049, TASK-050, TASK-058, TASK-051, TASK-052, TASK-053, TASK-054

**Acceptance Criteria:**

- CLAUDE.md lista los cinco catálogos de `setting/`, `dates.php`, el middleware, el prop `translations` y los tres tests de guarda.
- La regla de `.ai/rules/frontend.md` dice cómo nombrar una clave, dónde ponerla y qué test fallará si se omite.

**writeScope:**

- `CLAUDE.md`
- `AGENTS.md`
- `.ai/rules/frontend.md`

**validateCommand:** `rtk git diff --stat -- CLAUDE.md AGENTS.md .ai/rules/frontend.md`

### TASK-056: Documentar la arquitectura y la regla de backend

Actualizar `docs/architecture/frontend.md` y `docs/architecture/backend.md` (flujo idioma → `share()` → `useTranslation`, fallback por clave, SSR sin estado global) y `.ai/rules/backend.md` (los mensajes de FormRequests y flashes de la app usan `TranslatedText::of()` igual que el panel).

**Type:** docs · **Priority:** P3 · **Effort:** S · **Agent:** `general-purpose` · **Review:** claude

**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-026, TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032, TASK-033, TASK-034, TASK-035, TASK-036, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048, TASK-049, TASK-050, TASK-058, TASK-051, TASK-052, TASK-053, TASK-054

**Acceptance Criteria:**

- Los dos documentos de arquitectura describen el mismo flujo con los mismos nombres de clases y archivos.
- Ningún documento menciona `laravel-react-i18n` como opción vigente.

**writeScope:**

- `docs/architecture/frontend.md`
- `docs/architecture/backend.md`
- `.ai/rules/backend.md`

**validateCommand:** `rtk git diff --stat -- docs/architecture .ai/rules/backend.md`

### TASK-057: Gates completos de backend y frontend

Verificación final sin archivos propios: Pint, `rector-clear-cache` → `rector-dry` → `rector`, PHPStan, la suite completa en paralelo, `npm run types`, `lint:check`, `format:check`, `build` y `build:ssr`, más una revisión manual con `preferred_locale = en` y `es` de las tres vistas en el navegador. Cualquier corrección vuelve a la tarea dueña del archivo.

**Type:** chore · **Priority:** P3 · **Effort:** M · **Agent:** `general-purpose` · **Review:** claude

**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-026, TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032, TASK-033, TASK-034, TASK-035, TASK-036, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048, TASK-049, TASK-050, TASK-058, TASK-051, TASK-052, TASK-053, TASK-054, TASK-055, TASK-056

**Acceptance Criteria:**

- Todos los gates pasan sin cambios pendientes de Pint o Rector.
- Con `en`, las tres vistas no muestran claves crudas ni textos en español (fechas, flashes y errores incluidos); con `es`, se ven como antes.

**writeScope:**

- (ninguno: tarea de verificación)

**validateCommand:** `rtk vendor/bin/pint --dirty --format agent && rtk composer rector-clear-cache && rtk composer rector-dry && rtk composer phpstan && rtk composer test && rtk npm run types && rtk npm run lint:check && rtk npm run format:check && rtk npm run build && rtk npm run build:ssr`

## Failure Modes

- Un usuario existente tiene `preferred_locale = en` (default de la columna) y ve Configuración en inglés: es esperado tras la decisión; puede cambiarlo en Perfil. Documentado en TASK-055.
- El traductor o las fechas guardan el idioma en estado de módulo y el SSR mezcla idiomas entre peticiones: el contrato lo prohíbe y TASK-006 formatea por instancia.
- Una errata en una clave de React no la detecta `tsc`: TASK-053 falla con archivo, línea y clave.
- Falta una clave en `es`: TASK-051 falla; en ejecución se muestra el texto inglés gracias a la fusión del servidor.
- Un texto queda literal en un `.tsx`: TASK-054 falla.
- Una recarga parcial reenvía los catálogos o los pierde: TASK-004 fija que `translations` no viaja en parciales y React conserva el valor anterior.
- Las pruebas que comparan textos en español fallan al aplicarse el idioma del usuario (`en` en la factory): TASK-019, 020 y 022 las migran a comparar por clave y por idioma.
- `getPreferredLanguage()` devuelve `en` cuando ningún idioma coincide: TASK-001 comprueba el encabezado explícitamente y lo cubre con `fr`.

## Ship Cut

Si la ejecución se corta: TASK-001..006 dejan la infraestructura lista sin cambiar nada visible para un usuario `es`, salvo que un usuario con `preferred_locale = en` recibe fechas en inglés, de forma coherente. Cada vista es entregable por separado al terminar su catálogo, el registro del controlador y sus tareas de frontend (Perfil: 008, 013, 028–030; 2FA: 009, 014, 027, 031–036; Dispositivos: 010, 011, 013, 016–019, 037–050). Mientras un `data/*.ts` ya devuelve claves y un consumidor aún no las envuelve con `t()`, esa pieza muestra claves crudas, así que una vista no se publica a medias. La issue no se cierra hasta TASK-051..057.

## Test Coverage Map

| Comportamiento | Tareas |
| --- | --- |
| Resolución del idioma (usuario, navegador, invitado, valor inválido) | TASK-001, TASK-023 |
| Catálogos para React con fallback por clave | TASK-002, TASK-004, TASK-015 |
| Grupos por página y cambio de idioma desde Perfil | TASK-015 |
| Flashes y validación de TrustedDevice en dos idiomas | TASK-016, TASK-019 |
| Validación y flashes de 2FA en dos idiomas | TASK-020 |
| Password y restablecimiento en dos idiomas | TASK-022 |
| Paridad de claves y marcadores | TASK-051 |
| Claves usadas en TS existen | TASK-053 |
| Sin textos fijos en las vistas traducidas | TASK-054 |
| Tipos, lint, formato, build y SSR | TASK-057 |

## Execution Summary

- **Tareas:** 58
- **Ruta crítica:** TASK-001 → TASK-004 → TASK-005 → TASK-006 → TASK-029 → TASK-053 → TASK-055 → TASK-057
- **Paralelismo:** Los cinco catálogos de `setting/`, `dates`, `validation`, el middleware y `FrontendTranslations` arrancan en paralelo; tras TASK-005/006 las 25 tareas de frontend son independientes entre sí (no comparten archivos) y corren en paralelo con las de backend.

| Capa | Tareas |
| --- | --- |
| 0 | TASK-001, TASK-002, TASK-003, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012 |
| 1 | TASK-004, TASK-013, TASK-014, TASK-016, TASK-017, TASK-018, TASK-021, TASK-023, TASK-051 |
| 2 | TASK-005, TASK-015, TASK-019, TASK-020, TASK-022 |
| 3 | TASK-006, TASK-026, TASK-027, TASK-028, TASK-030, TASK-031, TASK-034, TASK-035, TASK-037, TASK-039, TASK-041, TASK-042, TASK-043, TASK-048, TASK-050 |
| 4 | TASK-024, TASK-025, TASK-029, TASK-032, TASK-033, TASK-036, TASK-038, TASK-040, TASK-044, TASK-045, TASK-046, TASK-047, TASK-049, TASK-058 |
| 5 | TASK-052, TASK-053, TASK-054 |
| 6 | TASK-055, TASK-056 |
| 7 | TASK-057 |

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | — |
| TASK-004 | TASK-001, TASK-002, TASK-003 |
| TASK-005 | TASK-004 |
| TASK-006 | TASK-003, TASK-005 |
| TASK-007 | — |
| TASK-008 | — |
| TASK-009 | — |
| TASK-010 | — |
| TASK-011 | — |
| TASK-012 | — |
| TASK-013 | TASK-002, TASK-007, TASK-008, TASK-010, TASK-011 |
| TASK-014 | TASK-002, TASK-007, TASK-009 |
| TASK-015 | TASK-004, TASK-013, TASK-014 |
| TASK-016 | TASK-010 |
| TASK-017 | TASK-010 |
| TASK-018 | TASK-010 |
| TASK-019 | TASK-001, TASK-017, TASK-018 |
| TASK-020 | TASK-001, TASK-014 |
| TASK-021 | TASK-008 |
| TASK-022 | TASK-001, TASK-021 |
| TASK-023 | TASK-001 |
| TASK-024 | TASK-006 |
| TASK-025 | TASK-006 |
| TASK-026 | TASK-005, TASK-007 |
| TASK-027 | TASK-005, TASK-007 |
| TASK-028 | TASK-005, TASK-008 |
| TASK-029 | TASK-005, TASK-006, TASK-008 |
| TASK-030 | TASK-005, TASK-008 |
| TASK-031 | TASK-005, TASK-009 |
| TASK-032 | TASK-005, TASK-006, TASK-009 |
| TASK-033 | TASK-005, TASK-006, TASK-009 |
| TASK-034 | TASK-005, TASK-009 |
| TASK-035 | TASK-005, TASK-009 |
| TASK-036 | TASK-005, TASK-006, TASK-009 |
| TASK-037 | TASK-005, TASK-010 |
| TASK-038 | TASK-005, TASK-006, TASK-010 |
| TASK-039 | TASK-005, TASK-010, TASK-011 |
| TASK-040 | TASK-005, TASK-006, TASK-010 |
| TASK-041 | TASK-005, TASK-010 |
| TASK-042 | TASK-005, TASK-010 |
| TASK-043 | TASK-005, TASK-010 |
| TASK-044 | TASK-005, TASK-006, TASK-011 |
| TASK-045 | TASK-005, TASK-006, TASK-011 |
| TASK-046 | TASK-005, TASK-006, TASK-011 |
| TASK-047 | TASK-005, TASK-006, TASK-011 |
| TASK-048 | TASK-005, TASK-011 |
| TASK-049 | TASK-005, TASK-006, TASK-011 |
| TASK-050 | TASK-005, TASK-011 |
| TASK-058 | TASK-026 |
| TASK-051 | TASK-003, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012 |
| TASK-052 | TASK-024, TASK-025, TASK-029, TASK-032, TASK-033, TASK-036, TASK-038, TASK-040, TASK-044, TASK-045, TASK-046, TASK-047, TASK-049 |
| TASK-053 | TASK-026, TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032, TASK-033, TASK-034, TASK-035, TASK-036, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048, TASK-049, TASK-050, TASK-058 |
| TASK-054 | TASK-026, TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032, TASK-033, TASK-034, TASK-035, TASK-036, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048, TASK-049, TASK-050, TASK-058 |
| TASK-055 | TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-026, TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032, TASK-033, TASK-034, TASK-035, TASK-036, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048, TASK-049, TASK-050, TASK-058, TASK-051, TASK-052, TASK-053, TASK-054 |
| TASK-056 | TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-026, TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032, TASK-033, TASK-034, TASK-035, TASK-036, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048, TASK-049, TASK-050, TASK-058, TASK-051, TASK-052, TASK-053, TASK-054 |
| TASK-057 | TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025, TASK-026, TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032, TASK-033, TASK-034, TASK-035, TASK-036, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048, TASK-049, TASK-050, TASK-058, TASK-051, TASK-052, TASK-053, TASK-054, TASK-055, TASK-056 |
