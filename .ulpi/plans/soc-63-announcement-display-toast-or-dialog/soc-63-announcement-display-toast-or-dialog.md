# Plan: SOC-63: Elegir cómo se presenta cada anuncio del sistema (toast o dialog)

## Overview

Cada anuncio guarda cómo se presenta (`display`: `toast` o `dialog`) y el administrador lo elige en el formulario; al elegir Critical el campo pasa solo a Dialog. La app muestra los toasts en una pila colapsada y los dialogs de uno en uno, el más grave primero. Todo anuncio se puede cerrar: con sesión y descartable en el servidor, como invitado en `localStorage` (y se sincroniza al iniciar sesión), y uno no descartable en `sessionStorage`, así que vuelve en la siguiente sesión.

**Linear:** [SOC-63](https://linear.app/social-hub-ang/issue/SOC-63/feature-let-administrators-choose-how-each-system-announcement-is) · **Modo:** EXPANSION · **Revisión predeterminada:** Claude

## Scope Challenge

EXPANSION con revisión de Claude (2026-10-28). Decisiones del usuario: el campo es explícito en el formulario (no se deduce del tipo); elegir Critical cambia la presentación a Dialog mientras el administrador no la haya elegido a mano; la vista previa del panel dibuja una maqueta de dialog en Blade. Extras incluidos: sincronizar los cierres del invitado al iniciar sesión, la pestaña 'Active dialogs' con su contador, el aviso de dialogs solapados al guardar y que el botón de acción del dialog también lo cierre. El usuario pidió además que al terminar se le recomienden más mejoras para el módulo. Hallazgos: `AnnouncementService::update()` ya audita los atributos sucios y `duplicate()` usa `replicate()`, así que la auditoría y el duplicado cubren `display` sin cambios; el `Dialog` de shadcn ya está instalado; `withApp` recibe `page` con `auth.user`, el mismo mecanismo que ya trae los anuncios.

## Prerequisites

- SOC-61 completo: módulo Announcement, `AnnouncementService`, `AnnouncementPolicy::dismiss`, `AnnouncementBannerResource` y `SystemAnnouncements` con su `Toaster` propio.
- El arreglo de Pullfrog en `AnnouncementForm::isAllowedButtonUrl()` (caracteres de control) ya está en la rama.
- Sin dependencias nuevas: `Dialog` de shadcn, Sonner, Filament `ToggleButtons` y `useHttp` ya existen.

## Non-Goals

- Notificaciones en tiempo real.
- Otras presentaciones (banner en el flujo, sheet, página completa).
- Presentación distinta según la audiencia.
- Estadísticas de vistas o cierres.
- Mostrar dialogs en el panel de Filament (solo la vista previa).

## Contracts

- `announcements.display` es `toast` o `dialog` (`AnnouncementDisplay`), por defecto `toast`; el tipo del frontend es su espejo y se cambian juntos.
- `AnnouncementBannerResource` agrega `display` y envía `isDismissible` tal como está guardado; el frontend decide dónde guardar cada cierre.
- Reglas de cierre: con sesión y descartable → `POST announcements/{announcement}/dismiss` (no vuelve en ningún dispositivo); invitado y descartable → `localStorage` (no vuelve en ese navegador); no descartable → `sessionStorage` (vuelve en la siguiente sesión). Todo toast y todo dialog se puede cerrar.
- `POST announcements/dismissals` (`announcements.dismissals.sync`) recibe `{announcement_ids: int[]}` (1 a 20), guarda solo los que la policy `dismiss` permite y responde `{dismissed: int[]}`; nunca falla por un id que ya no se puede descartar.
- Nunca hay más de un dialog visible; el orden es el del servidor (`visibleTo`: gravedad y luego el inicio más reciente).
- El botón de acción de un dialog cuenta como cerrarlo.
- Cambiar la presentación queda en la auditoría como `changed_attributes` con `display`, nunca con el texto.

## Existing Code Leverage

- `AnnouncementDismissalController` y `AnnouncementPolicy::dismiss`: la acción `sync` vive en el mismo controlador y repite la autorización por anuncio.
- `AnnouncementService::update()` y `duplicate()`: ya cubren `display` sin cambios.
- `AnnouncementToast` y `useAnnouncementDismiss` (SOC-61): base del contenido compartido y de las reglas de cierre.
- `Dialog` de shadcn (`resources/js/shared/components/shadcn/ui/dialog.tsx`).
- `useLikeToggle`: `useHttp` con errores locales y sin el modal de Inertia.
- `ListAnnouncements::getTabs()` con los scopes de fechas: la pestaña nueva suma `dialogs()`.
- El `afterStateHydrated` de 'Has button': patrón para inicializar `display_was_chosen` al editar.

## Tasks

### TASK-001: Enum AnnouncementDisplay

`App\Modules\Announcement\Enums\AnnouncementDisplay` (`Toast = 'toast'`, `Dialog = 'dialog'`) con `label()` en inglés para el panel ('Toast', 'Dialog') y `description()` con una frase de cuándo usar cada uno. Se agregan sus casos a `AnnouncementEnumsTest`.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Los valores exactos `toast` y `dialog` quedan fijados por la prueba, y un valor desconocido se rechaza con `ValueError`.
2. `label()` y `description()` devuelven un texto no vacío para cada caso.

**writeScope:**

- `app/Modules/Announcement/Enums/AnnouncementDisplay.php` (nuevo)
- `app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php`

**Review:** claude

### TASK-002: Migración de la columna display

Migración nueva `app/Modules/Announcement/Database/Migrations/2026_10_28_000000_add_display_to_announcements_table.php` (la de SOC-61 no se toca): `display` string(20) con valor por defecto `'toast'`, después de `audience`. `down()` la elimina. `AnnouncementServiceProvider` ya carga la carpeta, no hace falta registrarla.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Un anuncio que existía antes de la migración queda con `display = 'toast'`.
2. Rollback elimina solo la columna y vuelve a migrar sin errores; las tablas de SOC-61 siguen intactas.

**writeScope:**

- `app/Modules/Announcement/Database/Migrations/2026_10_28_000000_add_display_to_announcements_table.php` (nuevo)
- `app/Modules/Announcement/Tests/Database/AnnouncementDisplayMigrationTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Announcement/Tests/Database/AnnouncementDisplayMigrationTest.php`

**Review:** claude

### TASK-003: Modelo y factory con display

`Announcement`: `display` en `#[Fillable]`, cast a `AnnouncementDisplay` y `@property`. `AnnouncementFactory`: `display` en la definición (`Toast`) y estado `asDialog()`. Se amplía `AnnouncementModelTest`. Regenerar el IDE helper y revertir el ruido en otros modelos.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001, TASK-002

**Acceptance Criteria:**

1. Un anuncio nuevo sin `display` se lee como `AnnouncementDisplay::Toast`; `asDialog()` lo deja en `Dialog`.
2. Asignar un valor fuera del enum lanza `ValueError` al leer el atributo.

**writeScope:**

- `app/Modules/Announcement/Models/Announcement.php`
- `app/Modules/Announcement/Database/Factories/AnnouncementFactory.php`
- `app/Modules/Announcement/Tests/Models/AnnouncementModelTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Announcement/Tests/Models/AnnouncementModelTest.php`

**Review:** claude

### TASK-004: Scopes de dialogs y solapamiento

En `Announcement`: `#[Scope] dialogs()` (`display = dialog`) y `#[Scope] dialogsOverlapping(CarbonImmutable $startsAt, ?CarbonImmutable $endsAt, ?int $exceptId)`: dialogs cuya ventana `[starts_at, ends_at)` se cruza con la dada (un `ends_at` nulo es infinito), sin el anuncio `exceptId` y sin los ya vencidos. No filtra por audiencia: todo par de audiencias comparte usuarios (un usuario con sesión ve `all`, `authenticated` y, sin 2FA, `without_two_factor`), así que cualquier dialog que se cruza puede mostrarse a la misma persona. Lo usan el aviso del formulario (TASK-010) y la pestaña del listado (TASK-011).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. Dos dialogs con ventanas que se cruzan se encuentran; uno que terminó antes de que empiece el otro, no.
2. `ends_at` nulo en cualquiera de los dos cuenta como solapado; el propio anuncio (`exceptId`) y los toasts nunca se cuentan.

**writeScope:**

- `app/Modules/Announcement/Tests/Queries/AnnouncementDialogOverlapTest.php` (nuevo)
- `app/Modules/Announcement/Models/Announcement.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Announcement/Tests/Queries/AnnouncementDialogOverlapTest.php`

**Review:** claude

### TASK-005: AnnouncementData con display

`AnnouncementData` agrega `public AnnouncementDisplay $display`; `fromFormState()` lo lee con el mismo `enumFrom()` (acepta el valor o el caso) y usa `Toast` cuando falta; `toModelAttributes()` incluye `display`. Se amplía `AnnouncementDataTest`.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001

**Acceptance Criteria:**

1. `fromFormState(['display' => 'dialog', ...])` y con el caso `AnnouncementDisplay::Dialog` dan `Dialog`; sin la clave da `Toast`.
2. Un valor desconocido (`'popup'`) lanza `InvalidArgumentException`.

**writeScope:**

- `app/Modules/Announcement/Data/AnnouncementData.php`
- `app/Modules/Announcement/Tests/Data/AnnouncementDataTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Announcement/Tests/Data/AnnouncementDataTest.php`

**Review:** claude

### TASK-006: Auditoría y duplicado con display

`AnnouncementService` no necesita cambios: `update()` ya audita los atributos sucios y `duplicate()` usa `replicate()`. Esta tarea fija ese comportamiento en `AnnouncementServiceTest`.

**Type:** test · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-003, TASK-005

**Acceptance Criteria:**

1. Cambiar solo la presentación registra `admin.announcement_updated` con `changed_attributes = ['display']`.
2. Duplicar un dialog crea una copia que también es dialog; guardar sin cambios sigue sin registrar nada.

**writeScope:**

- `app/Modules/Announcement/Tests/Services/AnnouncementServiceTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Announcement/Tests/Services/AnnouncementServiceTest.php`

**Review:** claude

### TASK-007: Resource del banner con display y cierre para invitados

`AnnouncementBannerResource` agrega `display` (`'toast'|'dialog'`) y envía `isDismissible` tal como está guardado, sin depender de la sesión: el frontend decide dónde guardar el cierre (TASK-016). Se actualizan la prueba del resource (forma exacta, invitado con `isDismissible: true`) y `SharedAnnouncementsTest` (claves de la prop).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. La forma exacta del payload incluye `display` y sigue sin autor, audiencia ni fechas.
2. Un invitado recibe `isDismissible: true` en un anuncio descartable y `false` en uno no descartable.

**writeScope:**

- `app/Modules/Announcement/Resources/AnnouncementBannerResource.php`
- `app/Modules/Announcement/Tests/Resources/AnnouncementBannerResourceTest.php`
- `app/Modules/Announcement/Tests/Props/SharedAnnouncementsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Announcement/Tests/Resources/AnnouncementBannerResourceTest.php app/Modules/Announcement/Tests/Props/SharedAnnouncementsTest.php`

**Review:** claude

### TASK-008: Endpoint para sincronizar los cierres del invitado

`POST announcements/dismissals` (`announcements.dismissals.sync`, `auth` + `verified`, `throttle:10,1,announcements.dismissals.sync`) servido por el método `sync` de `AnnouncementDismissalController`, que pasa a tener dos acciones (`dismiss` y `sync`) en lugar de ser invocable, como `CommentController`; `routes/routes.php` agrupa ambas rutas con `Route::controller()->prefix('announcements')->name('announcements.')` y conserva sus nombres. `AnnouncementDismissalSyncRequest` (`HasFluentRules`): `announcement_ids` obligatorio, arreglo de 1 a 20 enteros positivos distintos. El controlador carga esos anuncios, se queda solo con los que `Gate::allows('dismiss', $announcement)` (descartables y vigentes para ese usuario), los guarda con `insertOrIgnore` y responde JSON `{dismissed: int[]}` con los ids aceptados; los demás se ignoran sin error, porque pudieron vencer o cambiar desde que el invitado los cerró.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. `route:list` muestra `announcements.dismissals.sync` con `auth`, `verified` y su throttle propio.
2. PHPStan pasa sobre las tres piezas; el FormRequest no acepta un arreglo vacío ni más de 20 ids.

**writeScope:**

- `app/Modules/Announcement/Requests/AnnouncementDismissalSyncRequest.php` (nuevo)
- `app/Modules/Announcement/Controllers/AnnouncementDismissalController.php`
- `app/Modules/Announcement/routes/routes.php`

**validateCommand:** `rtk php artisan route:list --name=announcements.dismissals && rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Announcement/Requests app/Modules/Announcement/Controllers`

**Review:** claude

### TASK-009: Pruebas del endpoint de sincronización

`AnnouncementDismissalSyncTest` en `Tests/Crud/`.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008

**Acceptance Criteria:**

1. Con sesión, guarda solo los ids descartables y vigentes para ese usuario y responde exactamente esos en `dismissed`; repetir la petición no duplica filas.
2. Un invitado recibe 401; ids vacíos, repetidos, no numéricos o más de 20 dan 422; la petición 11 en un minuto da 429.

**writeScope:**

- `app/Modules/Announcement/Tests/Crud/AnnouncementDismissalSyncTest.php` (nuevo)

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Announcement/Tests/Crud/AnnouncementDismissalSyncTest.php`

**Review:** claude

### TASK-010: Campo 'Show as' en el formulario

En la sección Display de `AnnouncementForm`: `ToggleButtons::make('display')` ('Show as') con las opciones de `AnnouncementDisplay`, sus descripciones e íconos, por defecto `toast`. Un `Hidden` `display_was_chosen` (no se guarda) se pone en `true` cuando el administrador toca el campo y, al editar, desde el inicio. `type` pasa a `live()` y su `afterStateUpdated` cambia `display` a `dialog` al elegir Critical, y de vuelta a `toast` al salir de Critical, solo mientras `display_was_chosen` sea falso. Un aviso (`hint` o `helperText` reactivo, no bloquea) dice cuántos dialogs se solapan con las fechas del formulario, usando `dialogsOverlapping()` sin contar el registro editado. Se amplían `CreateAnnouncementTest` y `EditAnnouncementTest`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004, TASK-005

**Acceptance Criteria:**

1. Crear sin tocar el campo guarda `toast`; elegir Critical lo cambia a `dialog`, y volver a Info lo devuelve a `toast`.
2. Si el administrador eligió Toast a mano, elegir Critical no lo cambia; al editar un dialog, cambiar el tipo no altera la presentación.
3. Con otro dialog solapado aparece el aviso con su número, y aun así el anuncio se guarda; sin solapamiento no aparece.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Announcements/Schemas/AnnouncementForm.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/CreateAnnouncementTest.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/EditAnnouncementTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/CreateAnnouncementTest.php app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/EditAnnouncementTest.php`

**Review:** claude

### TASK-011: Presentación en la tabla y contador de dialogs

`AnnouncementsTable`: columna 'Shown as' (badge con ícono) y `SelectFilter` por `display`. `ListAnnouncements`: pestaña nueva 'Active dialogs' (anuncios `running()->dialogs()`) con `badge()` del número, para ver de un vistazo cuántos dialogs están interrumpiendo a los usuarios. Se amplía `ListAnnouncementsTest`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-004

**Acceptance Criteria:**

1. La pestaña 'Active dialogs' muestra solo los dialogs vigentes y su badge coincide con ese número; los toasts y los dialogs vencidos o programados no cuentan.
2. El filtro por presentación devuelve solo ese tipo, y la tabla sigue sin agregar consultas por fila.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Announcements/Tables/AnnouncementsTable.php`
- `app/Modules/Admin/Filament/Resources/Announcements/Pages/ListAnnouncements.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ListAnnouncementsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ListAnnouncementsTest.php`

**Review:** claude

### TASK-012: Vista previa como dialog

`preview.blade.php` dibuja, cuando el anuncio es dialog, una maqueta de dialog (fondo atenuado, tarjeta centrada con título, mensaje saneado, imagen, botón del anuncio y 'Entendido') en lugar del toast, y una línea que dice cómo se mostrará. Los toasts se ven como hoy. Usa `@use` para las clases (regla de `.ai/rules/backend.md`). Se amplía `AnnouncementTableActionsTest`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-003

**Acceptance Criteria:**

1. La vista previa de un dialog muestra 'Entendido' y la de un toast no.
2. En ambos casos el HTML peligroso del cuerpo no llega al modal.

**writeScope:**

- `app/Modules/Admin/UI/Views/filament/announcements/preview.blade.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Tables/AnnouncementTableActionsTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Tables/AnnouncementTableActionsTest.php`

**Review:** claude

### TASK-013: Presentación en el detalle

`AnnouncementInfolist` muestra 'Shown as' con su badge en la sección Display. Se amplía `ViewAnnouncementTest`.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-003

**Acceptance Criteria:**

1. El detalle de un dialog muestra 'Dialog' y el de un toast 'Toast'.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Announcements/Schemas/AnnouncementInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ViewAnnouncementTest.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ViewAnnouncementTest.php`

**Review:** claude

### TASK-014: Tipos y almacenamiento local de los cierres

`types/announcement.ts`: `AnnouncementDisplay` (espejo del enum del backend, cambiar ambos juntos) y `display` en `Announcement`. `utils/announcementDismissalStorage.ts` (nuevo): leer y agregar ids en `localStorage` (`social-hub.announcements.dismissed`, cierres del invitado) y en `sessionStorage` (`social-hub.announcements.dismissed-this-session`, anuncios no descartables), y vaciar la lista local tras sincronizarla. Cada acceso va en `try/catch` y devuelve una lista vacía si el almacenamiento no existe o tiene basura.

**Type:** feature · **Effort:** S · **Agent:** react-vite-tailwind-engineer · **Priority:** P1

**Depends on:** TASK-007

**Acceptance Criteria:**

1. `npm run types`, ESLint y Prettier pasan.
2. Con `localStorage` bloqueado o un valor que no es un arreglo de números, las funciones devuelven `[]` y no lanzan.

**writeScope:**

- `resources/js/modules/announcement/utils/announcementDismissalStorage.ts` (nuevo)
- `resources/js/modules/announcement/types/announcement.ts`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/announcement/types/announcement.ts resources/js/modules/announcement/utils/announcementDismissalStorage.ts && rtk npx prettier --check resources/js/modules/announcement/types/announcement.ts resources/js/modules/announcement/utils/announcementDismissalStorage.ts`

**Review:** claude

### TASK-015: Contenido compartido, toast y dialog

`components/AnnouncementContent.tsx` (nuevo): el mensaje saneado, la imagen y el botón del anuncio (con `onAction` opcional), extraídos de `AnnouncementToast` para no duplicarlos. `AnnouncementToast` lo reutiliza y muestra la ✕ siempre (todos se pueden cerrar). `components/AnnouncementDialog.tsx` (nuevo): `Dialog` de shadcn con el ícono y el color del tipo, `DialogTitle`/`DialogDescription` enlazados, el contenido y un botón 'Entendido'; cerrar con 'Entendido', `Esc`, el fondo o el botón del anuncio llama a `onClose`.

**Type:** feature · **Effort:** M · **Agent:** react-vite-tailwind-engineer · **Priority:** P1

**Depends on:** TASK-014

**Acceptance Criteria:**

1. `npm run types`, ESLint y Prettier pasan.
2. El botón del anuncio en el dialog llama a `onClose` antes de navegar; un anuncio sin botón muestra solo 'Entendido'.
3. El toast conserva su aspecto, su `role` por tipo y el enlace interno con `Link` o externo con `rel="noopener noreferrer"`.

**writeScope:**

- `resources/js/modules/announcement/components/AnnouncementContent.tsx` (nuevo)
- `resources/js/modules/announcement/components/AnnouncementDialog.tsx` (nuevo)
- `resources/js/modules/announcement/components/AnnouncementToast.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/announcement/components/AnnouncementContent.tsx resources/js/modules/announcement/components/AnnouncementDialog.tsx resources/js/modules/announcement/components/AnnouncementToast.tsx && rtk npx prettier --check resources/js/modules/announcement/components/AnnouncementContent.tsx resources/js/modules/announcement/components/AnnouncementDialog.tsx resources/js/modules/announcement/components/AnnouncementToast.tsx`

**Review:** claude

### TASK-016: Reglas de cierre y sincronización al iniciar sesión

`useAnnouncementDismiss` recibe si hay sesión (de `auth.user` de la página inicial y de cada evento `navigate`) y cierra según la tabla de la tarea: con sesión y descartable, el endpoint actual con `optimistic`; invitado y descartable, `localStorage`; no descartable, `sessionStorage`. Los anuncios cerrados localmente se filtran antes de mostrarse. Devuelve `toastAnnouncements` y `dialogAnnouncements` (en el orden del servidor). `hooks/useGuestDismissalSync.ts` (nuevo): cuando hay sesión y `localStorage` tiene ids, los envía una vez con `useHttp` al endpoint de TASK-008 (acción de Wayfinder) y vacía la lista si responde bien; si falla, la deja para el siguiente intento, sin toast.

**Type:** feature · **Effort:** M · **Agent:** react-vite-tailwind-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-014

**Acceptance Criteria:**

1. `npm run types`, ESLint y Prettier pasan.
2. Un invitado que cierra un anuncio no hace ninguna petición; uno no descartable nunca llama al endpoint.
3. La sincronización se envía una sola vez por inicio de sesión aunque StrictMode monte el efecto dos veces.

**writeScope:**

- `resources/js/modules/announcement/hooks/useGuestDismissalSync.ts` (nuevo)
- `resources/js/modules/announcement/hooks/useAnnouncementDismiss.ts`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/announcement/hooks/useAnnouncementDismiss.ts resources/js/modules/announcement/hooks/useGuestDismissalSync.ts && rtk npx prettier --check resources/js/modules/announcement/hooks/useAnnouncementDismiss.ts resources/js/modules/announcement/hooks/useGuestDismissalSync.ts`

**Review:** claude

### TASK-017: Toasts colapsados y cola de dialogs

`SystemAnnouncements`: el `Toaster` pasa a pila colapsada (`expand={false}`) solo con `toastAnnouncements`, y se monta un único `AnnouncementDialog` con el primero de `dialogAnnouncements`; al cerrarlo aparece el siguiente. `app.tsx` pasa también si hay sesión desde el `page` de `withApp`. Comprobar en el navegador (invitado y Angel, modo claro y oscuro): la pila colapsada, un dialog a la vez, el cierre del invitado que sobrevive a recargar, el no descartable que vuelve en otra sesión, el botón del dialog que lo cierra y la sincronización al iniciar sesión.

**Type:** feature · **Effort:** M · **Agent:** react-vite-tailwind-engineer · **Priority:** P1

**Depends on:** TASK-015, TASK-016

**Acceptance Criteria:**

1. `npm run types`, ESLint, Prettier y `npm run build` pasan.
2. Con dos dialogs vigentes se ve uno; al cerrarlo aparece el otro, y nunca dos a la vez.
3. Un invitado que cerró un toast y luego inicia sesión no lo vuelve a ver, y la fila queda en `announcement_dismissals`.

**writeScope:**

- `resources/js/modules/announcement/components/SystemAnnouncements.tsx`
- `resources/js/app/app.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/announcement/components/SystemAnnouncements.tsx resources/js/app/app.tsx && rtk npx prettier --check resources/js/modules/announcement/components/SystemAnnouncements.tsx resources/js/app/app.tsx && rtk npm run build`

**Review:** claude

### TASK-018: Seeders con dialogs

`AnnouncementSeeder`: el anuncio fijo de 2FA (`TWO_FACTOR_TITLE`) y los críticos de muestra pasan a `dialog`, y los duplicados copian `display`. `ActivityLogSeeder`: agrega `['display']` a `ANNOUNCEMENT_CHANGED_ATTRIBUTES`. Se amplía `AnnouncementSeederTest`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-003

**Acceptance Criteria:**

1. Tras sembrar, el anuncio de 2FA es dialog y hay al menos un toast vigente.
2. Correrlo dos veces no duplica, y el activity log sembrado sigue sin texto de anuncios.

**writeScope:**

- `app/Modules/Announcement/Database/Seeders/AnnouncementSeeder.php`
- `app/Modules/Announcement/Tests/Seeders/AnnouncementSeederTest.php`
- `app/Modules/ActivityLog/Database/Seeders/ActivityLogSeeder.php`

**validateCommand:** `rtk php -d memory_limit=2G vendor/bin/pest --compact app/Modules/Announcement/Tests/Seeders/AnnouncementSeederTest.php app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php`

**Review:** claude

### TASK-019: CLAUDE.md y AGENTS.md

Documentar en la entrada Announcement: la columna y el enum, el campo del formulario con su cambio automático y su aviso, la pestaña 'Active dialogs', el endpoint de sincronización, `display` e `isDismissible` en el resource, la tabla de reglas de cierre, la cola de dialogs y los componentes del frontend. Actualizar el seeder. En AGENTS.md, la regla de cierre en el párrafo de SOC-61.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-006, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-017, TASK-018

**Acceptance Criteria:**

1. CLAUDE.md y AGENTS.md describen el campo, el enum, el endpoint, la cola de dialogs y las reglas de cierre sin contradecir SOC-61.
2. Ya no dicen que `isDismissible` es falso para invitados ni que los toasts se muestran desplegados.

**writeScope:**

- `CLAUDE.md`
- `AGENTS.md`

**validateCommand:** `rtk grep -n "AnnouncementDisplay\|announcements.dismissals.sync" CLAUDE.md AGENTS.md`

**Review:** claude

### TASK-020: Docs de arquitectura y gates finales

`docs/architecture/backend.md` y `frontend.md`: la presentación, el endpoint y las reglas de cierre. Correr todos los gates: Pint, Rector (clear-cache → dry → aplicar), PHPStan, `composer test`, Prettier, ESLint, tsc, `npm run build` y `npm run build:ssr`.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-019

**Acceptance Criteria:**

1. Los documentos de arquitectura mencionan la presentación y las reglas de cierre.
2. Todos los gates pasan.

**writeScope:**

- `docs/architecture/backend.md`
- `docs/architecture/frontend.md`

**validateCommand:** `rtk vendor/bin/pint --dirty --format agent && rtk composer rector-clear-cache && rtk composer rector-dry && rtk composer phpstan && rtk composer test && rtk npm run format:check && rtk npm run lint:check && rtk npm run types && rtk npm run build && rtk npm run build:ssr`

**Review:** claude

## Failure Modes

- `localStorage` o `sessionStorage` bloqueados (navegación privada, cuota): cada acceso va en `try/catch` y devuelve una lista vacía; el anuncio solo vuelve a mostrarse (TASK-014).
- Un id guardado por el invitado venció o dejó de ser descartable antes de iniciar sesión: el endpoint lo ignora y responde solo los aceptados (TASK-008, TASK-009).
- StrictMode monta dos veces el efecto de sincronización: una referencia evita enviar dos veces (TASK-016).
- Dos dialogs vigentes a la vez: se muestran de uno en uno y el formulario avisa al programarlos (TASK-010, TASK-017).
- El cambio automático pisa una elección manual: el campo oculto `display_was_chosen` lo impide (TASK-010).

## Ship Cut

TASK-001..007 dejan el dato guardado y servido con auditoría. TASK-010..013 completan el panel y TASK-014..017 la app; las dos mitades avanzan en paralelo después de TASK-007/008. TASK-018 es independiente; TASK-019..020 cierran.

## Test Coverage Map

| Área | Tareas |
| --- | --- |
| Enum, migración y modelo | TASK-001, TASK-002, TASK-003 |
| Solapamiento de dialogs | TASK-004, TASK-010 |
| Data, servicio y auditoría | TASK-005, TASK-006 |
| Resource y prop compartida | TASK-007 |
| Sincronización de cierres | TASK-009 |
| Panel (formulario, tabla, vista previa, detalle) | TASK-010, TASK-011, TASK-012, TASK-013 |
| Seeders | TASK-018 |
| Frontend (tsc, ESLint, Prettier, navegador) | TASK-014, TASK-015, TASK-016, TASK-017 |

## Execution Summary

- Tareas: 20
- Ruta crítica: TASK-001 → TASK-003 → TASK-007 → TASK-014 → TASK-015 → TASK-017 → TASK-019 → TASK-020
- Capas paralelas:

  1. TASK-001, TASK-002
  2. TASK-003, TASK-005
  3. TASK-004, TASK-006, TASK-007, TASK-008, TASK-012, TASK-013, TASK-018
  4. TASK-009, TASK-010, TASK-011, TASK-014
  5. TASK-015, TASK-016
  6. TASK-017
  7. TASK-019
  8. TASK-020

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | TASK-001, TASK-002 |
| TASK-004 | TASK-003 |
| TASK-005 | TASK-001 |
| TASK-006 | TASK-003, TASK-005 |
| TASK-007 | TASK-003 |
| TASK-008 | TASK-003 |
| TASK-009 | TASK-008 |
| TASK-010 | TASK-004, TASK-005 |
| TASK-011 | TASK-004 |
| TASK-012 | TASK-003 |
| TASK-013 | TASK-003 |
| TASK-014 | TASK-007 |
| TASK-015 | TASK-014 |
| TASK-016 | TASK-008, TASK-014 |
| TASK-017 | TASK-015, TASK-016 |
| TASK-018 | TASK-003 |
| TASK-019 | TASK-006, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-017, TASK-018 |
| TASK-020 | TASK-019 |
