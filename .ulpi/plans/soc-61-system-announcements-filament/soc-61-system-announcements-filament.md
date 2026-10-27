# Plan: SOC-61: Anuncios del sistema gestionados desde el panel de Filament

## Overview

Módulo nuevo `app/Modules/Announcement/`: los administradores con el rol `admin-announcements-manager` crean, programan, editan, duplican, despublican y borran anuncios desde `/admin/announcements` (primer CRUD real del panel), y la app React los muestra como banner a la audiencia que corresponde, con descarte persistente por usuario. Todo cambio administrativo queda en el activity log sin el texto del anuncio.

**Linear:** [SOC-61](https://linear.app/social-hub-ang/issue/SOC-61/feature-add-system-announcements-managed-from-the-filament-admin-panel) · **Modo:** HOLD · **Revisión predeterminada:** Claude

## Scope Challenge

HOLD con revisión de Claude. Decisión del usuario (2026-10-27): se aprueba la dependencia nueva `filament/spatie-laravel-media-library-plugin` para la imagen con `SpatieMediaLibraryFileUpload` (no estaba instalada). Hallazgos: `symfony/html-sanitizer` ya llega con Filament y `RichContentRenderer` sanea el HTML, así que no hace falta otra librería; `HandleInertiaRequests` está excluido de PHPStan, por eso la regla de visibilidad vive en el scope `visibleTo` y la serialización en `AnnouncementBannerResource`, ambos analizados; `FlashToaster` ya lee props desde los eventos del router dentro de `withApp`, el mismo mecanismo sirve para mostrar el banner en todas las páginas, incluidas las de invitado. Las notificaciones en tiempo real siguen fuera, como pidió el usuario.

## Prerequisites

- Panel Filament 5 con autorización en policies de modelo (SOC-44, SOC-57).
- `ActivityLogger` y `ActivityLogAdminEvent` (SOC-54).
- `spatie/laravel-medialibrary` 11 y la morph map forzada en `AppServiceProvider`.
- Aprobación de la dependencia `filament/spatie-laravel-media-library-plugin` (dada el 2026-10-27).

## Non-Goals

- Notificaciones en tiempo real (Reverb, campana del panel).
- Correo o push.
- Traducciones del mensaje por idioma.
- Estadísticas de vistas o descartes.
- Reflejar los anuncios sembrados en `ActivityLogSeeder`.

## Contracts

- `Announcement::visibleTo(?User)` es la única regla de qué anuncio ve quién; el listado del panel, la prop compartida y la policy `dismiss` la reutilizan.
- Toda escritura desde el panel pasa por `AnnouncementService`, que registra la auditoría en la misma transacción; el activity log nunca guarda título ni cuerpo.
- El HTML del cuerpo solo llega al navegador saneado por `RichContentRenderer` (`bodyHtml`); el frontend nunca recibe el HTML crudo.
- `AnnouncementBannerResource` no expone autor, audiencia ni fechas.
- La prop `announcements` tiene un costo fijo de consultas y es perezosa.
- El tipo del frontend es espejo de `AnnouncementType`: se cambian juntos.

## Existing Code Leverage

- `LikeServiceProvider` / `LikeRouteServiceProvider`: patrón de proveedores del módulo.
- `AccountStatusService` (SOC-35): servicio con transacción y auditoría.
- `UserPolicy`, `PostPolicy` y `#[UsePolicy]`: policies que Filament consulta.
- `ListUsersAccountStatusTest`: prueba de conteo de consultas con cachés calientes.
- `FlashToaster`: leer props compartidas desde los eventos del router dentro de `withApp`.
- `useLikeToggle`: `useHttp` con actualización optimista y errores locales.
- `Post::registerMediaCollections()`: colección `singleFile()`.

## Tasks

### TASK-001: Instalar el plugin oficial de MediaLibrary para Filament

Dependencia aprobada por el usuario (2026-10-27): `composer require filament/spatie-laravel-media-library-plugin` en la versión compatible con `filament/filament` 5.10. Aporta `SpatieMediaLibraryFileUpload` y `SpatieMediaLibraryImageEntry`, que usan las colecciones de `spatie/laravel-medialibrary` ya instalado. No se publica configuración.

**Type:** chore · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `composer show filament/spatie-laravel-media-library-plugin` muestra una versión 5.x.
2. `composer test` sigue verde y PHPStan no reporta errores nuevos tras la instalación.

**writeScope:**

- `composer.json`
- `composer.lock`

**validateCommand:** `rtk composer show filament/spatie-laravel-media-library-plugin && rtk composer phpstan`

**Review:** claude

### TASK-002: Proveedor del módulo Announcement

Crear `App\Modules\Announcement\Providers\AnnouncementServiceProvider` (patrón de `LikeServiceProvider`): `boot()` llama a `loadMigrationsFrom(__DIR__.'/../Database/Migrations')`. Registrarlo en `bootstrap/providers.php` después de `LikeServiceProvider`. El proveedor de rutas lo agrega TASK-023.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `php artisan about` lista el proveedor sin errores.
2. Sin carpeta de migraciones todavía, `php artisan migrate:status` no falla.

**writeScope:**

- `app/Modules/Announcement/Providers/AnnouncementServiceProvider.php` (nuevo)
- `bootstrap/providers.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Announcement/Providers/AnnouncementServiceProvider.php`

**Review:** claude

### TASK-003: Registrar la suite de pruebas Announcement

Agregar `app/Modules/Announcement/Tests` como suite `Announcement` en `phpunit.xml` y en el `->in(...)` de `tests/Pest.php` (TestCase + RefreshDatabase), igual que SOC-35 con `AccountStatus`.

**Type:** chore · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Una prueba colocada en esa carpeta recibe `RefreshDatabase`.
2. La suite completa sigue corriendo aunque la carpeta todavía no exista.

**writeScope:**

- `phpunit.xml`
- `tests/Pest.php`

**validateCommand:** `rtk php artisan test --compact --testsuite=Unit`

**Review:** claude

### TASK-004: Enums AnnouncementType y AnnouncementAudience

`AnnouncementType` (`info`, `warning`, `critical`) con `label()` en inglés para el panel y `severity()` para ordenar (crítico primero). `AnnouncementAudience` (`all`, `authenticated`, `without_two_factor`) con `label()`. Prueba de los valores y del orden de severidad.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-003

**Acceptance Criteria:**

1. Los valores exactos de ambos enums quedan fijados por la prueba.
2. `severity()` ordena critical > warning > info; un valor desconocido en `tryFrom` es `null`.

**writeScope:**

- `app/Modules/Announcement/Enums/AnnouncementType.php` (nuevo)
- `app/Modules/Announcement/Enums/AnnouncementAudience.php` (nuevo)
- `app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php`

**Review:** claude

### TASK-005: Migración de announcements y announcement_dismissals

Una migración en `app/Modules/Announcement/Database/Migrations/`: `announcements` (`id`, `title` string 120, `body` text con el HTML del RichEditor, `type`, `audience`, `starts_at`, `ends_at` nullable, `button_label` nullable 40, `button_url` nullable 2048, `is_dismissible` bool default true, `created_by` FK nullable `nullOnDelete`, timestamps, índice `(starts_at, ends_at)`) y `announcement_dismissals` (`id`, `announcement_id` FK `cascadeOnDelete`, `user_id` FK `cascadeOnDelete`, `created_at`, único `(announcement_id, user_id)`). `down()` borra ambas.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002, TASK-003

**Acceptance Criteria:**

1. Rollback y migrate dejan las dos tablas con sus índices.
2. Insertar dos descartes del mismo usuario y anuncio viola el índice único; borrar el anuncio o el usuario borra sus descartes; borrar al autor deja `created_by` en null.

**writeScope:**

- `app/Modules/Announcement/Database/Migrations/2026_10_27_000000_create_announcements_tables.php` (nuevo)
- `app/Modules/Announcement/Tests/Database/AnnouncementMigrationTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Database/AnnouncementMigrationTest.php`

**Review:** claude

### TASK-006: Modelo Announcement y su factory

`App\Modules\Announcement\Models\Announcement` (`HasMedia` + `InteractsWithMedia`, colección `announcement_image` `singleFile()`, `MORPH_NAME = 'announcement'`, `TITLE_MAX_LENGTH`, `BUTTON_LABEL_MAX_LENGTH`), casts de `type`, `audience`, fechas inmutables e `is_dismissible`; relaciones `creator()` y `dismissedBy()` (BelongsToMany a `User` vía `announcement_dismissals`). `AnnouncementFactory` completa con estados `scheduled()`, `active()`, `expired()`, `withButton()`, `notDismissible()`, `forAudience()` y `createdBy(User)`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-004, TASK-005

**Acceptance Criteria:**

1. La factory crea anuncios válidos en cada estado con fechas coherentes.
2. `dismissedBy()` devuelve solo los usuarios que descartaron ese anuncio.
3. Un anuncio cuyo autor fue borrado devuelve `creator = null` sin error.

**writeScope:**

- `app/Modules/Announcement/Models/Announcement.php` (nuevo)
- `app/Modules/Announcement/Database/Factories/AnnouncementFactory.php` (nuevo)
- `app/Modules/Announcement/Tests/Models/AnnouncementModelTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Models/AnnouncementModelTest.php`

**Review:** claude

### TASK-007: Morph map de Announcement

Registrar `Announcement::MORPH_NAME` en `AppServiceProvider::registerMorphMap()`: la morph map está forzada y la necesitan tanto MediaLibrary (`media.model_type`) como el activity log (sujeto). Prueba propia.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-006

**Acceptance Criteria:**

1. `Relation::getMorphedModel('announcement')` devuelve `Announcement`.
2. Adjuntar una imagen a `announcement_image` guarda `model_type = 'announcement'` (con `Storage::fake`).

**writeScope:**

- `app/Modules/Announcement/Tests/Models/AnnouncementMorphMapTest.php` (nuevo)
- `app/Providers/AppServiceProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Models/AnnouncementMorphMapTest.php`

**Review:** claude

### TASK-008: Scope de visibilidad: anuncios vigentes para un usuario

Agregar a `Announcement` el scope `#[Scope] visibleTo(?User $user)`: `starts_at <= now`, `ends_at` nulo o futuro; audiencia `all` para todos, `authenticated` solo con usuario, `without_two_factor` solo con usuario sin 2FA confirmado (`two_factor_confirmed_at` nulo); excluye los descartados por ese usuario; ordena por severidad del tipo y luego `starts_at` descendente, y limita a `MAX_VISIBLE` (3). Es la única fuente de qué se muestra.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006

**Acceptance Criteria:**

1. Un invitado ve solo anuncios `all` vigentes; programados y vencidos nunca aparecen.
2. Un usuario con 2FA confirmado no ve `without_two_factor`; uno descartado ya no aparece para ese usuario pero sí para otros.
3. Con más de 3 vigentes devuelve 3, críticos primero.

**writeScope:**

- `app/Modules/Announcement/Tests/Queries/AnnouncementVisibilityTest.php` (nuevo)
- `app/Modules/Announcement/Models/Announcement.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Queries/AnnouncementVisibilityTest.php`

**Review:** claude

### TASK-009: Permisos y rol admin-announcements-manager

Agregar a `AdminPermission` `admin.announcements.viewAny`, `view`, `create`, `update` y `delete`; a `AdminRole` `AdminAnnouncementsManager = 'admin-announcements-manager'`; y en `AdminAuthorizationSeeder` el paquete del rol con los cinco permisos. No se asigna automáticamente (TASK-031 lo da a Angel en local).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Tras el seeder existen el rol y los cinco permisos bajo el guard `admin`.
2. Las pruebas existentes del seeder y de los comandos Admin siguen verdes; `admin:user-role grant <email> admin-announcements-manager` funciona.

**writeScope:**

- `app/Modules/Admin/Enums/AdminPermission.php`
- `app/Modules/Admin/Enums/AdminRole.php`
- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php app/Modules/Admin/Tests/Commands`

**Review:** claude

### TASK-010: AnnouncementPolicy

Crear `App\Modules\Announcement\Policies\AnnouncementPolicy` y asociarla con `#[UsePolicy]`: `viewAny`/`view`/`create`/`update`/`delete` con sus permisos; `replicate` con `create`; `deleteAny`, `restore`, `restoreAny`, `forceDelete` y `forceDeleteAny` en `false` (Filament permite lo que la policy no define). Además `dismiss(User, Announcement)` para la app: el usuario puede descartarlo solo si `is_dismissible` y le es visible (`visibleTo`). Prueba de la policy y fijar el paquete del rol en `AdminAuthorizationSeederTest`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-009

**Acceptance Criteria:**

1. Cada habilidad del panel se permite solo con su permiso; las de borrado masivo y restauración se niegan siempre.
2. `dismiss` se niega para un anuncio no cerrable, vencido o de otra audiencia.

**writeScope:**

- `app/Modules/Announcement/Policies/AnnouncementPolicy.php` (nuevo)
- `app/Modules/Announcement/Tests/Policies/AnnouncementPolicyTest.php` (nuevo)
- `app/Modules/Announcement/Models/Announcement.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Policies/AnnouncementPolicyTest.php`

**Review:** claude

### TASK-011: AnnouncementData: entrada tipada del formulario

`App\Modules\Announcement\Data\AnnouncementData` (spatie/laravel-data, regla del proyecto): título recortado, cuerpo, `AnnouncementType`, `AnnouncementAudience`, fechas, `isDismissible`, y botón: si `hasButton` es falso, `buttonLabel` y `buttonUrl` quedan en null aunque vengan con valor. `toModelAttributes()` mapea a columnas. Las reglas de validación viven en el formulario de Filament (TASK-015), no aquí.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**

1. Se construye desde el estado del formulario con los enums tipados.
2. Con `hasButton = false` el botón se guarda vacío aunque lleguen texto y enlace.

**writeScope:**

- `app/Modules/Announcement/Data/AnnouncementData.php` (nuevo)
- `app/Modules/Announcement/Tests/Data/AnnouncementDataTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Data/AnnouncementDataTest.php`

**Review:** claude

### TASK-012: AnnouncementService con auditoría

`App\Modules\Announcement\Services\AnnouncementService`: `create(AnnouncementData, User $administrator)`, `update(Announcement, AnnouncementData, User)`, `unpublish(Announcement, User)` (fija `ends_at = now`; `LogicException` si ya venció), `duplicate(Announcement, User)` (copia el contenido y la imagen; queda programada para `now()->addDay()` y sin `ends_at`, así no se publica sola antes de revisarla) y `delete(Announcement, User)`. Cada uno en `DB::transaction` y registra con `ActivityLogger` `admin.announcement_created|updated|unpublished|duplicated|deleted` (nuevos casos de `ActivityLogAdminEvent`), con el anuncio como sujeto y solo ids o `changed_attributes` (nunca título ni cuerpo).

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-007, TASK-008, TASK-011

**Acceptance Criteria:**

1. Crear guarda `created_by` y registra `admin.announcement_created`; actualizar sin cambios no registra nada.
2. Despublicar un anuncio vencido lanza `LogicException` sin escribir; ningún registro contiene el texto del anuncio.
3. `ActivityLogPrivacyTest` pasa con los eventos nuevos.

**writeScope:**

- `app/Modules/Announcement/Services/AnnouncementService.php` (nuevo)
- `app/Modules/Announcement/Tests/Services/AnnouncementServiceTest.php` (nuevo)
- `app/Modules/ActivityLog/Enums/Events/ActivityLogAdminEvent.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Announcement/Tests/Services/AnnouncementServiceTest.php app/Modules/ActivityLog/Tests/Security/ActivityLogPrivacyTest.php`

**Review:** claude

### TASK-013: Recurso Filament: listado con pestañas y tabla

Crear con `make:filament-resource` (moviendo a la jerarquía del módulo Admin) `Announcements/AnnouncementResource.php` (modelo `Announcement`, etiqueta 'Announcements', solo la página `index` por ahora), `Pages/ListAnnouncements.php` con `getTabs()`: Scheduled, Active, Expired, All; y `Tables/AnnouncementsTable.php`: título, tipo (badge), audiencia, rango de fechas, autor ('Deleted user' si es null), filtros por tipo y audiencia, `creator` precargado.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-010

**Acceptance Criteria:**

1. `/admin/announcements` existe y la policy decide el acceso (sin `can*()` en el recurso).
2. Las pestañas separan programados, activos y vencidos según la misma regla de fechas que `visibleTo`.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Announcements/AnnouncementResource.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Announcements/Pages/ListAnnouncements.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Announcements/Tables/AnnouncementsTable.php` (nuevo)

**validateCommand:** `rtk php artisan route:list --name=filament.admin.resources.announcements && rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Announcements`

**Review:** claude

### TASK-014: Pruebas del listado

`ListAnnouncementsTest`: acceso con y sin permiso, pestañas, filtros, 'Deleted user' y número constante de consultas con 3 y 20 anuncios (calentar cachés antes de medir, como `ListUsersAccountStatusTest`).

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-013

**Acceptance Criteria:**

1. Sin `admin.announcements.viewAny` el listado responde 403.
2. Cada pestaña muestra solo sus anuncios; la tabla no agrega consultas por fila.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ListAnnouncementsTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ListAnnouncementsTest.php`

**Review:** claude

### TASK-015: Formulario y página de creación

`Schemas/AnnouncementForm.php`: `TextInput` título (requerido, max), `RichEditor` cuerpo (requerido; barra sin adjuntos ni HTML crudo), `Select` tipo y audiencia (enums), `DateTimePicker` desde (requerido) y hasta (`after:starts_at`), `Toggle` 'Has button' con `live()` que muestra texto y enlace (`requiredIf`, enlace URL https o ruta interna `/...`), `Toggle` dismissible, `SpatieMediaLibraryFileUpload` en `announcement_image` (imagen, máx. 2 MB). `Pages/CreateAnnouncement.php` sobrescribe `handleRecordCreation()` para llamar a `AnnouncementService::create` con `AnnouncementData` y el administrador autenticado; la imagen la guarda el componente tras crear. Registrar `create` en `getPages()`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001, TASK-012, TASK-013

**Acceptance Criteria:**

1. El formulario oculta los campos del botón mientras 'Has button' está apagado.
2. Crear pasa por el servicio (queda `created_by` y su registro de actividad).

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Announcements/Schemas/AnnouncementForm.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Announcements/Pages/CreateAnnouncement.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Announcements/AnnouncementResource.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Announcements`

**Review:** claude

### TASK-016: Pruebas de creación y validación

`CreateAnnouncementTest` con `mountAction`/`set`/`call` o `fillForm` según lo que PHPStan acepte (en SOC-35 se evitaron los macros dinámicos): creación válida con imagen (`Storage::fake`), título vacío, 'hasta' anterior a 'desde', botón activo sin texto o con `javascript:` como enlace, y sin permiso `create`.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-015

**Acceptance Criteria:**

1. Un anuncio válido se guarda con su imagen en `announcement_image`.
2. Cada regla inválida devuelve su error y no crea nada; sin permiso la página responde 403.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/CreateAnnouncementTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/CreateAnnouncementTest.php`

**Review:** claude

### TASK-017: Página de edición con borrado

`Pages/EditAnnouncement.php`: reutiliza `AnnouncementForm`, `handleRecordUpdate()` llama a `AnnouncementService::update`, y la cabecera tiene `DeleteAction` que usa `AnnouncementService::delete`. Registrar `edit` en `getPages()`. Prueba `EditAnnouncementTest`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-016

**Acceptance Criteria:**

1. Editar guarda los cambios y registra solo los nombres de atributos cambiados.
2. Sin `update` la página responde 403; sin `delete` la acción de borrar no aparece y el borrado se rechaza.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Announcements/Pages/EditAnnouncement.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/EditAnnouncementTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Announcements/AnnouncementResource.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/EditAnnouncementTest.php`

**Review:** claude

### TASK-018: Página de detalle con infolist

`Schemas/AnnouncementInfolist.php` (título, tipo, audiencia, fechas, botón, dismissible, imagen con `SpatieMediaLibraryImageEntry`, autor y el cuerpo renderizado con `RichContentRenderer`, que sanea el HTML) y `Pages/ViewAnnouncement.php`; registrar `view` en `getPages()`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-017

**Acceptance Criteria:**

1. `/admin/announcements/{id}` muestra los datos y el cuerpo sin ejecutar HTML peligroso.
2. Sin `view` responde 403.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Announcements/Schemas/AnnouncementInfolist.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Announcements/Pages/ViewAnnouncement.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Announcements/AnnouncementResource.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Resources/Announcements`

**Review:** claude

### TASK-019: Pruebas del detalle

`ViewAnnouncementTest`: campos visibles, un `<script>` del cuerpo no aparece en el HTML, 'Deleted user' para un autor borrado y 403 sin permiso.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-018

**Acceptance Criteria:**

1. El detalle muestra los datos del anuncio.
2. El HTML peligroso del cuerpo no llega a la página.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ViewAnnouncementTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ViewAnnouncementTest.php`

**Review:** claude

### TASK-020: Acciones de la tabla: duplicar, despublicar y vista previa

En `AnnouncementsTable`: `ReplicateAction` 'Duplicate' que llama a `AnnouncementService::duplicate` (autorizada con `replicate`), 'Unpublish now' con confirmación, visible solo para anuncios activos y `->authorize('update')`, y 'Preview' que abre un modal con la vista Blade `app/Modules/Admin/UI/Views/filament/announcements/preview.blade.php` (carpetas en minúscula, regla del backend) con el banner como lo vería el usuario. Prueba `AnnouncementTableActionsTest`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-014, TASK-018

**Acceptance Criteria:**

1. Duplicar crea una copia programada sin `ends_at`; despublicar mueve el anuncio a la pestaña Expired.
2. Sin `update` 'Unpublish now' no aparece y su llamada directa no cambia nada; la vista previa no ejecuta el HTML del cuerpo.

**writeScope:**

- `app/Modules/Admin/UI/Views/filament/announcements/preview.blade.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Tables/AnnouncementTableActionsTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Announcements/Tables/AnnouncementsTable.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Tables/AnnouncementTableActionsTest.php`

**Review:** claude

### TASK-021: AnnouncementBannerResource

`App\Modules\Announcement\Resources\AnnouncementBannerResource` (JsonResource, regla del proyecto): `id`, `title`, `bodyHtml` (renderizado y saneado con `RichContentRenderer`), `type`, `buttonLabel`, `buttonUrl`, `isDismissible`, `imageUrl` (o null). Nunca expone autor, audiencia ni fechas.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-007

**Acceptance Criteria:**

1. El shape exacto queda fijado por la prueba.
2. Un `<script>` u `onerror=` del cuerpo no sobrevive en `bodyHtml`.

**writeScope:**

- `app/Modules/Announcement/Resources/AnnouncementBannerResource.php` (nuevo)
- `app/Modules/Announcement/Tests/Resources/AnnouncementBannerResourceTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Resources/AnnouncementBannerResourceTest.php`

**Review:** claude

### TASK-022: Prop compartida announcements

En `HandleInertiaRequests::share()` agregar `'announcements' => fn () => AnnouncementBannerResource::collection(Announcement::query()->visibleTo($request->user())->with('media')->get())->resolve($request)` (lazy, como `notification`). Prueba `SharedAnnouncementsTest` contra una página real (home para usuario, login para invitado).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-021

**Acceptance Criteria:**

1. Un invitado en `/login` recibe solo anuncios `all`; un usuario sin 2FA recibe también los `without_two_factor`.
2. Un anuncio descartado no llega; la prop cuesta un número fijo de consultas sin importar cuántos anuncios haya.

**writeScope:**

- `app/Modules/Announcement/Tests/Props/SharedAnnouncementsTest.php` (nuevo)
- `app/Http/Middleware/HandleInertiaRequests.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Props/SharedAnnouncementsTest.php`

**Review:** claude

### TASK-023: Endpoint de descarte

`Controllers/AnnouncementDismissalController.php` (invocable, `#[Authorize('dismiss', 'announcement')]`): inserta en `announcement_dismissals` con `insertOrIgnore` (idempotente ante doble clic o dos pestañas) y responde JSON `{dismissed: true}`. `routes/routes.php`: `POST announcements/{announcement}/dismiss` (`announcements.dismiss`, `auth` + `verified`, `throttle:20,1,announcements.dismiss`). `Providers/AnnouncementRouteServiceProvider.php` carga las rutas bajo `web`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-010

**Acceptance Criteria:**

1. Las tres piezas existen y PHPStan pasa.
2. El registro del proveedor de rutas lo hace TASK-024 para no tocar el mismo archivo dos veces.

**writeScope:**

- `app/Modules/Announcement/Controllers/AnnouncementDismissalController.php` (nuevo)
- `app/Modules/Announcement/routes/routes.php` (nuevo)
- `app/Modules/Announcement/Providers/AnnouncementRouteServiceProvider.php` (nuevo)

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Announcement/Controllers app/Modules/Announcement/Providers`

**Review:** claude

### TASK-024: Registrar las rutas y probar el descarte

`AnnouncementServiceProvider::register()` registra `AnnouncementRouteServiceProvider`. Prueba `AnnouncementDismissTest`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-002, TASK-023

**Acceptance Criteria:**

1. Descartar un anuncio cerrable lo oculta de la prop para ese usuario y repetirlo no duplica filas.
2. Un invitado recibe 401/redirección; un anuncio no cerrable, vencido o de otra audiencia responde 403; el throttle responde 429.

**writeScope:**

- `app/Modules/Announcement/Tests/Crud/AnnouncementDismissTest.php` (nuevo)
- `app/Modules/Announcement/Providers/AnnouncementServiceProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Crud/AnnouncementDismissTest.php`

**Review:** claude

### TASK-025: Tipos y componente AnnouncementBanner

`types/announcement.ts` (`Announcement`, `AnnouncementType`, espejo del enum del backend: cambiar ambos juntos) y `announcements: Announcement[]` en `SharedData`. `components/AnnouncementBanner.tsx`: una franja por anuncio con `Alert` de shadcn, color por tipo con tokens del tema (modo claro y oscuro), título, cuerpo con `dangerouslySetInnerHTML` solo sobre `bodyHtml` ya saneado en el servidor, imagen opcional, botón (`Button` + `Link` interno o `<a>` externo con `rel="noopener"`) y la ✕ con `aria-label` solo si `isDismissible`.

**Type:** feature · **Effort:** M · **Agent:** react-vite-tailwind-engineer · **Priority:** P1

**Depends on:** TASK-021

**Acceptance Criteria:**

1. `npm run types`, ESLint y Prettier pasan.
2. Sin anuncios no renderiza nada; sin `isDismissible` no hay botón de cerrar.

**writeScope:**

- `resources/js/modules/announcement/types/announcement.ts` (nuevo)
- `resources/js/modules/announcement/components/AnnouncementBanner.tsx` (nuevo)
- `resources/js/shared/types/index.d.ts`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/announcement && rtk npx prettier --check resources/js/modules/announcement`

**Review:** claude

### TASK-026: Descarte en el frontend y montaje global

`hooks/useAnnouncementDismiss.ts`: `useHttp` contra la acción Wayfinder de `AnnouncementDismissalController`, actualización optimista (oculta al instante y revierte si falla), y 429/red como mensaje local (`onHttpException`/`onNetworkError` devuelven `false`). Montar el banner en `withApp` de `app/app.tsx`, leyendo `page.props.announcements` del evento `navigate` del router como hace `FlashToaster`, para que aparezca en todas las páginas, incluidas las de invitado. Revisión en el navegador en modo claro y oscuro.

**Type:** feature · **Effort:** M · **Agent:** react-vite-tailwind-engineer · **Priority:** P1

**Depends on:** TASK-022, TASK-024, TASK-025

**Acceptance Criteria:**

1. El banner aparece en home, configuración y login según la audiencia, y desaparece al cerrarlo sin recargar.
2. Si el descarte falla el banner vuelve a mostrarse con un mensaje; los checks de frontend pasan.

**writeScope:**

- `resources/js/modules/announcement/hooks/useAnnouncementDismiss.ts` (nuevo)
- `resources/js/app/app.tsx`
- `resources/js/modules/announcement/components/AnnouncementBanner.tsx`

**validateCommand:** `rtk npm run types && rtk npm run lint:check && rtk npm run format:check`

**Review:** claude

### TASK-027: AnnouncementSeeder

`Database/Seeders/AnnouncementSeeder.php`: anuncios de ejemplo en español en cada estado (programado, dos activos de tipos y audiencias distintas, uno con botón a `/setting/two-factor` para `without_two_factor`, uno no cerrable, uno vencido), autor un administrador sembrado. Escribe con la factory, sin el servicio, para no llenar `activity_log` antes de `ActivityLogSeeder`. Registrarlo en `DatabaseSeeder` después de `AccountStatusSeeder`. No hace nada si ya hay anuncios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-006, TASK-009

**Acceptance Criteria:**

1. Tras sembrar hay al menos un anuncio en cada pestaña y no hay filas en `activity_log` escritas por este seeder.
2. Correrlo dos veces no duplica anuncios.

**writeScope:**

- `app/Modules/Announcement/Database/Seeders/AnnouncementSeeder.php` (nuevo)
- `app/Modules/Announcement/Tests/Seeders/AnnouncementSeederTest.php` (nuevo)
- `database/seeders/DatabaseSeeder.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Seeders/AnnouncementSeederTest.php`

**Review:** claude

### TASK-028: Accesos rápidos del dashboard

Agregar 'Announcements' a `AdminQuickLinksWidget` cuando la policy permite `viewAny` (Gate, no el permiso directo). Ampliar `AdminQuickLinksWidgetTest`.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-013

**Acceptance Criteria:**

1. Con `admin-announcements-manager` aparece el enlace a `/admin/announcements`.
2. Sin el permiso no aparece.

**writeScope:**

- `app/Modules/Admin/Filament/Widgets/AdminQuickLinksWidget.php`
- `app/Modules/Admin/Tests/Filament/Widgets/AdminQuickLinksWidgetTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Widgets/AdminQuickLinksWidgetTest.php`

**Review:** claude

### TASK-029: Angel recibe el rol de anuncios en local

Como hizo SOC-35 con el moderador: `AdminUserSeeder` da a Angel `admin-announcements-manager` en `local`/`testing`; los administradores aleatorios no lo reciben. Actualizar las dos pruebas que fijan los roles de Angel.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-009

**Acceptance Criteria:**

1. Angel tiene el rol tras sembrar y correrlo dos veces no duplica nada.
2. Ningún administrador aleatorio recibe el rol.

**writeScope:**

- `app/Modules/Admin/Database/Seeders/AdminUserSeeder.php`
- `app/Modules/Admin/Tests/Database/AdminUserSeederTest.php`
- `app/Modules/Admin/Tests/Database/AdminUserSeederRandomAdminsTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database`

**Review:** claude

### TASK-030: Documentación: CLAUDE.md y AGENTS.md

Documentar el módulo Announcement (estructura, modelo, scope `visibleTo`, servicio, auditoría, prop compartida, endpoint de descarte, seeder), la morph map `announcement`, el rol y los permisos nuevos (tabla de roles y listas de los comandos), el plugin de MediaLibrary para Filament y el banner del frontend.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-012, TASK-020, TASK-026, TASK-027, TASK-028, TASK-029

**Acceptance Criteria:**

1. CLAUDE.md y AGENTS.md describen el módulo sin contradecir SOC-35, SOC-54 ni SOC-57.
2. Las listas de roles y permisos coinciden con los enums.

**writeScope:**

- `CLAUDE.md`
- `AGENTS.md`

**validateCommand:** `rtk grep -n 'admin-announcements-manager' CLAUDE.md AGENTS.md`

**Review:** claude

### TASK-031: Documentación de arquitectura y gates finales

Actualizar `docs/architecture/backend.md` (tabla de roles, módulo) y `docs/architecture/frontend.md` (banner). Correr todos los gates: Pint, Rector (clear-cache → dry → aplicar), PHPStan, `composer test`, Prettier, ESLint, tsc, `npm run build` y `npm run build:ssr`.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-030

**Acceptance Criteria:**

1. Los documentos de arquitectura mencionan el módulo y el rol.
2. Todos los gates pasan.

**writeScope:**

- `docs/architecture/backend.md`
- `docs/architecture/frontend.md`

**validateCommand:** `rtk vendor/bin/pint --dirty --format agent && rtk composer rector-clear-cache && rtk composer rector-dry && rtk composer phpstan && rtk composer test && rtk npm run format:check && rtk npm run lint:check && rtk npm run types && rtk npm run build && rtk npm run build:ssr`

**Review:** claude

## Failure Modes

- HTML malicioso en el cuerpo: lo sanea `RichContentRenderer` en el servidor (TASK-021, TASK-018, TASK-020) y se prueba con `<script>` y `onerror`.
- Doble clic o dos pestañas al cerrar: `insertOrIgnore` + índice único (TASK-005, TASK-023).
- Anuncio editado o despublicado mientras otro administrador lo tiene abierto: el servicio relee dentro de la transacción y `unpublish` rechaza uno ya vencido.
- Prop compartida cara en cada página: perezosa, limitada a 3 y con prueba de conteo de consultas (TASK-022).
- La morph map forzada rompe MediaLibrary si falta el alias: TASK-007 va antes de cualquier subida.

## Ship Cut

TASK-001..016 entregan la creación desde el panel con auditoría. TASK-021..026 son la mitad visible para los usuarios y pueden ir en paralelo con edición y detalle. TASK-027..029 son extras independientes; TASK-030..031 cierran.

## Test Coverage Map

| Área | Tareas |
| --- | --- |
| Migración y modelo | TASK-005, TASK-006, TASK-007 |
| Visibilidad y audiencias | TASK-008, TASK-022 |
| Autorización | TASK-010, TASK-014, TASK-016, TASK-017, TASK-019, TASK-020 |
| Servicio y auditoría | TASK-012 |
| Formulario y validación | TASK-016, TASK-017 |
| Saneamiento del HTML | TASK-019, TASK-020, TASK-021 |
| Descarte | TASK-024 |
| Seeders y dashboard | TASK-027, TASK-028, TASK-029 |

## Execution Summary

- Tareas: 31
- Ruta crítica: TASK-003 → TASK-004 → TASK-006 → TASK-008 → TASK-010 → TASK-013 → TASK-015 → TASK-016 → TASK-017 → TASK-018 → TASK-020 → TASK-030 → TASK-031
- Capas paralelas:

  1. TASK-001, TASK-002, TASK-003, TASK-009
  2. TASK-004, TASK-005, TASK-029
  3. TASK-006, TASK-011
  4. TASK-007, TASK-008, TASK-027
  5. TASK-010, TASK-012, TASK-021
  6. TASK-013, TASK-022, TASK-023, TASK-025
  7. TASK-014, TASK-015, TASK-024, TASK-028
  8. TASK-016, TASK-026
  9. TASK-017
  10. TASK-018
  11. TASK-019, TASK-020
  12. TASK-030
  13. TASK-031

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | — |
| TASK-004 | TASK-003 |
| TASK-005 | TASK-002, TASK-003 |
| TASK-006 | TASK-004, TASK-005 |
| TASK-007 | TASK-006 |
| TASK-008 | TASK-006 |
| TASK-009 | — |
| TASK-010 | TASK-008, TASK-009 |
| TASK-011 | TASK-004 |
| TASK-012 | TASK-007, TASK-008, TASK-011 |
| TASK-013 | TASK-010 |
| TASK-014 | TASK-013 |
| TASK-015 | TASK-001, TASK-012, TASK-013 |
| TASK-016 | TASK-015 |
| TASK-017 | TASK-016 |
| TASK-018 | TASK-017 |
| TASK-019 | TASK-018 |
| TASK-020 | TASK-014, TASK-018 |
| TASK-021 | TASK-007 |
| TASK-022 | TASK-008, TASK-021 |
| TASK-023 | TASK-010 |
| TASK-024 | TASK-002, TASK-023 |
| TASK-025 | TASK-021 |
| TASK-026 | TASK-022, TASK-024, TASK-025 |
| TASK-027 | TASK-006, TASK-009 |
| TASK-028 | TASK-013 |
| TASK-029 | TASK-009 |
| TASK-030 | TASK-012, TASK-020, TASK-026, TASK-027, TASK-028, TASK-029 |
| TASK-031 | TASK-030 |
