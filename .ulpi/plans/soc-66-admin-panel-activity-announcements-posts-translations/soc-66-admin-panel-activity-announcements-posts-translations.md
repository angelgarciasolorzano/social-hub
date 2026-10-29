# Plan: SOC-66: Traducir Activity Log, Announcements y Posts en el panel Admin

## Overview

Completar la localización al español e inglés de las interfaces Filament de Activity Log, Announcements y Posts bajo el selector ya implementado por SOC-65. El plan amplía la issue con pruebas ejecutables por sección, guardrails para evitar textos literales y sincronización de la documentación existente.

**Linear:** [SOC-66](https://linear.app/social-hub-ang/issue/SOC-66/feature-translate-activity-log-announcements-and-posts-into-spanish) · **Modo:** EXPANSION · **Revisión predeterminada:** codex

## Scope Challenge

EXPANSION, con revisión predeterminada de Codex (2026-10-29), confirmado por el usuario. El núcleo conserva exactamente los tres dominios de SOC-66; la expansión agrega pruebas por cada superficie, detección de literales que hoy no cubre tabs/tooltips/hints, verificación de enums y actualización de documentación existente. El Activity Log ya tiene tabla, filtros, nombres de eventos/logs y parties traducidos; el detalle y widgets siguen pendientes. Announcements tiene resource, enums, formularios, tabs, acciones, preview y detalle con textos fijos. Posts incluye su tabla, detalle, relation manager de comentarios dentro del post y widgets. Comments independiente, contenido authored y React quedan fuera.

## Prerequisites

- SOC-65 está Done y su implementación está en `origin/main` (`a3698b94`); el selector del panel, `users.preferred_locale`, middleware persistente de Livewire y la convención de catálogos existen.
- Las traducciones de Activity Log ya cubren `ActivityLogsTable`, `DescribesActivityLogParties` y nombres de eventos/logs; deben conservarse.
- `AdminTranslationKeysTest` detecta todos los catálogos `lang/en/admin/*.php` y verifica paridad/no-vacíos/placeholders; `AdminHardcodedTextTest` actualmente escanea solo superficies ya traducidas.
- Las rutas usadas por el plan fueron inspeccionadas en `app/Modules/Admin/Filament/Resources/{ActivityLogs,Announcements,Posts}/`, sus pruebas y catálogos actuales.
- No se requiere una dependencia nueva ni cambio a rutas, esquema, roles, permisos o persistencia.

## Non-Goals

- Cambiar el selector de idioma, cómo se resuelve el locale o cómo se guarda `users.preferred_locale`.
- Traducir la aplicación React, el anuncio/publicación redactado por una persona, los correos, comandos o validaciones de Laravel.
- Traducir el recurso independiente `app/Modules/Admin/Filament/Resources/Comments/` o el gestor PostsRelationManager en la pantalla User.
- Cambiar valores persistidos de `AnnouncementType`, `AnnouncementAudience`, `AnnouncementDisplay`, eventos o logs.
- Cambiar permisos, auditoría, workflows o comportamiento de solo lectura de Activity Log y Posts.

## Contracts

- Todo texto propio del panel se obtiene de `lang/{en,es}/admin/<sección>.php` con claves con nombre; PHP usa `TranslatedText::of('admin/<sección>.<clave>')` y Blade usa `__()`.
- Los catálogos inglés/español conservan claves, valores no vacíos y placeholders; los plurales usan `trans_choice` y las fechas siguen el locale activo.
- Los labels y descripciones de enums son presentación. Sus backing values persistidos permanecen estables; colores e iconos se seleccionan por estado tipado, nunca comparando la etiqueta traducida.
- El texto authoreado de Announcement y Post no se traduce. Announcement body sigue renderizado por `safeBodyHtml()` y todas las escrituras/auditoría siguen pasando por `AnnouncementService`.
- Activity Log no reconstruye ni expone contenido de posts/comments; los widgets mantienen consulta, conteos, periodo y autorización.
- El Posts scope incluye el `CommentsRelationManager` anidado en `PostResource`, pero no la vista independiente de Comments a la que navega su acción View.

## Existing Code Leverage

- `ActivityLogsTable::configure()` y `ActivityLogEventCatalog` ya localizan tabla, filtros, eventos, logs y partes.
- `ActivityLogInfolist`, `ActivityLogOverview` y `ActivityLogSummaryWidget` centralizan las superficies faltantes del Activity Log; `ListActivityLogsTest` ya puede inspeccionar datos y accesibilidad del gráfico.
- `AnnouncementType`, `AnnouncementAudience` y `AnnouncementDisplay` centralizan labels/descripciones reutilizados por tabla, formulario, detalle y preview.
- `AnnouncementService` conserva las operaciones auditadas; `safeBodyHtml()` mantiene el sanitizado actual del body.
- `PostResource`, `PostsTable`, `PostInfolist`, `CommentsRelationManager`, `PostsOverview` y `PostActivityOverview` son las superficies existentes del área Posts.
- `AdminTranslationKeysTest` autodetecta nuevos catálogos; `AdminHardcodedTextTest` puede extender su lista de rutas/métodos sin introducir infraestructura nueva.

## Tasks

### TASK-001: Completar el catálogo del Activity Log

Añadir al catálogo existente `lang/{en,es}/admin/activity-log.php` las claves para navegación, detalle y widgets del registro. Reutilizar claves de `admin/common` para textos compartidos cuando existan. Mantener intactas las claves de eventos, logs y tipos de sujeto ya usadas por `ActivityLogEventCatalog`.

**Type:** feature · **Priority:** P0 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Las claves nuevas tienen valores en inglés y español, con la misma estructura y los mismos placeholders; no hay textos vacíos.
2. Las claves existentes de eventos, logs y tipos de sujeto conservan sus nombres y valores de búsqueda.
3. Una clave de detalle que represente ausencia de datos tiene una traducción útil en ambos locales, no un texto vacío ni la clave cruda.

**Files to create:**
- Ninguno

**Files to modify:**
- `lang/en/admin/activity-log.php`
- `lang/es/admin/activity-log.php`

**writeScope:**
- `lang/en/admin/activity-log.php`
- `lang/es/admin/activity-log.php`

**validateCommand:** `rtk php -l lang/en/admin/activity-log.php && rtk php -l lang/es/admin/activity-log.php`

### TASK-002: Traducir la navegación y el detalle del Activity Log

Sustituir los textos propios de Filament en `ActivityLogResource` y `ActivityLogInfolist` por claves del catálogo. Traducir secciones, descripciones, campos, placeholders y valores booleanos; formatear la fecha con el locale activo. Ampliar la prueba existente del detalle para ambos idiomas.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-001

**Acceptance Criteria:**

1. El label de navegación y todos los textos fijos del detalle se muestran en el locale activo `en` o `es`.
2. Los valores booleanos, propiedades ausentes y contexto HTTP no registrado aparecen traducidos, conservando el valor y el tratamiento de los datos.
3. El detalle continúa ocultando contenido de publicaciones y comentarios y sigue permitiendo ver los datos almacenados al administrador autorizado.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/ActivityLogs/ActivityLogResource.php`
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Schemas/ActivityLogInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ViewActivityLogTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/ActivityLogs/ActivityLogResource.php`
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Schemas/ActivityLogInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ViewActivityLogTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ViewActivityLogTest.php`

### TASK-003: Traducir las métricas de seguridad y administración del Activity Log

Localizar el heading, labels y descripciones de las estadísticas de `ActivityLogOverview`. Conservar sus consultas, ventana fija de siete días, autorización y colores semánticos. Añadir aserciones en español e inglés a la prueba del widget.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-001

**Acceptance Criteria:**

1. El heading, las tres etiquetas de estadística y sus descripciones se presentan en ambos locales.
2. Las métricas mantienen los mismos conteos y el rango de siete días al cambiar el locale.
3. Sin eventos recientes siguen apareciendo conteos cero, y el widget continúa denegado sin `admin.activity.viewAny`.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Widgets/ActivityLogOverview.php`
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Widgets/ActivityLogOverviewTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Widgets/ActivityLogOverview.php`
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Widgets/ActivityLogOverviewTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Widgets/ActivityLogOverviewTest.php`

### TASK-004: Traducir el gráfico y su resumen accesible del Activity Log

Localizar heading, estados vacíos, etiqueta del dataset, descripción con pluralización y contenido accesible de `ActivityLogSummaryWidget`. El valor centinela usado internamente por la consulta para eventos desconocidos debe seguir estable y convertirse a una etiqueta localizada solo al presentar el gráfico. Ampliar las pruebas del gráfico en `ListActivityLogsTest`.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-001

**Acceptance Criteria:**

1. La leyenda, el resumen accesible, la descripción singular/plural y los estados vacíos se muestran en inglés y español.
2. Un evento sin etiqueta catalogada muestra una etiqueta de fallback traducida sin cambiar el valor centinela ni los conteos de la consulta.
3. El gráfico vacío sigue devolviendo una serie vacía y el widget conserva su autorización actual.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Widgets/ActivityLogSummaryWidget.php`
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Widgets/ActivityLogSummaryWidget.php`
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php`

### TASK-005: Crear el catálogo de Announcements

Crear `lang/{en,es}/admin/announcements.php` con claves con nombre para la interfaz del recurso: navegación, formularios, lista, tabs, acciones, detalle, opciones de enums, notificaciones y preview. No incluir contenido escrito por administradores.

**Type:** feature · **Priority:** P0 · **Effort:** M · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Ambos catálogos devuelven arrays PHP válidos con las mismas claves y valores no vacíos.
2. Los placeholders y reglas de pluralización coinciden entre inglés y español.
3. Los catálogos contienen claves de interfaz, sin claves que pretendan traducir los valores de título, cuerpo o botón guardados en cada anuncio.

**Files to create:**
- `lang/en/admin/announcements.php`
- `lang/es/admin/announcements.php`

**Files to modify:**
- Ninguno

**writeScope:**
- `lang/en/admin/announcements.php`
- `lang/es/admin/announcements.php`

**validateCommand:** `rtk php -l lang/en/admin/announcements.php && rtk php -l lang/es/admin/announcements.php`

### TASK-006: Localizar tipos y audiencias de Announcements

Cambiar `AnnouncementType::label()` y `AnnouncementAudience::label()` para obtener sus textos del catálogo `admin/announcements`. Extender `AnnouncementEnumsTest` para probar ambos locales sin cambiar los valores persistidos de los enums.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-005

**Acceptance Criteria:**

1. Cada tipo y audiencia devuelve su etiqueta traducida según `app()->getLocale()`.
2. Los valores respaldados (`info`, `warning`, `critical`, `all`, `authenticated`, `without_two_factor`) no cambian.
3. Valores no reconocidos siguen rechazándose con `ValueError`; no se traducen ni normalizan para persistencia.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Announcement/Enums/AnnouncementType.php`
- `app/Modules/Announcement/Enums/AnnouncementAudience.php`
- `app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php`

**writeScope:**
- `app/Modules/Announcement/Enums/AnnouncementType.php`
- `app/Modules/Announcement/Enums/AnnouncementAudience.php`
- `app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php`

### TASK-007: Localizar la presentación toast/dialog de Announcements

Localizar `AnnouncementDisplay::label()` y `description()` mediante el catálogo. Ampliar `AnnouncementEnumsTest` para ambos locales y conservar la semántica de los valores de presentación.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-006

**Acceptance Criteria:**

1. Las etiquetas y descripciones para Toast y Dialog cambian con el locale activo.
2. Los valores respaldados `toast` y `dialog`, su uso en el formulario y la selección guardada permanecen iguales.
3. La descripción de cada opción no queda vacía en ninguno de los dos locales.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Announcement/Enums/AnnouncementDisplay.php`
- `app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php`

**writeScope:**
- `app/Modules/Announcement/Enums/AnnouncementDisplay.php`
- `app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php`

### TASK-008: Traducir navegación y formulario de Announcements

Localizar los labels del recurso, encabezados y secciones del formulario, hints, placeholders y campos condicionales. Usar getters en la clase Resource para los textos que hoy están en propiedades. Extender `CreateAnnouncementTest` para revisar español e inglés y conservar el comportamiento actual del formulario.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-005, TASK-006, TASK-007

**Acceptance Criteria:**

1. Navegación, singular/plural del recurso, secciones y textos del formulario aparecen completos en ambos locales.
2. El helper de solapamiento de diálogos y las opciones/descripciones de tipo, audiencia y display se traducen según el locale.
3. El toggle del botón, las reglas de URL y los campos condicionales conservan su comportamiento, incluso para URL inválidas.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Announcements/AnnouncementResource.php`
- `app/Modules/Admin/Filament/Resources/Announcements/Schemas/AnnouncementForm.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/CreateAnnouncementTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Announcements/AnnouncementResource.php`
- `app/Modules/Admin/Filament/Resources/Announcements/Schemas/AnnouncementForm.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/CreateAnnouncementTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/CreateAnnouncementTest.php`

### TASK-009: Traducir acciones, tabla y vista previa de Announcements

Localizar columnas, filtros, acciones, tooltips, modales y notificaciones de `AnnouncementsTable`, y los textos estáticos de la vista previa Blade. Las etiquetas de display/type deben seguir usando estados tipados para color e icono. Extender las pruebas existentes de acciones, incluyendo el contenido del preview.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-005, TASK-006, TASK-007

**Acceptance Criteria:**

1. La tabla, filtros, preview, duplicate y unpublish muestran sus textos fijos en el locale activo.
2. El título, cuerpo y texto del botón del anuncio se muestran exactamente como fueron guardados; el body continúa pasando por `safeBodyHtml()`.
3. El preview sigue separando el flujo toast/dialog y manteniendo color e icono basados en enums, no en etiquetas traducidas.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Announcements/Tables/AnnouncementsTable.php`
- `app/Modules/Admin/UI/Views/filament/announcements/preview.blade.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Tables/AnnouncementTableActionsTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Announcements/Tables/AnnouncementsTable.php`
- `app/Modules/Admin/UI/Views/filament/announcements/preview.blade.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Tables/AnnouncementTableActionsTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Tables/AnnouncementTableActionsTest.php`

### TASK-010: Traducir las pestañas de la lista de Announcements

Localizar tabs Scheduled, Active, Expired, Active dialogs y All, más el tooltip del badge. Mantener los nombres de clave de las pestañas y las consultas de estado. Ampliar `ListAnnouncementsTest` para locales `en` y `es`.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-005

**Acceptance Criteria:**

1. Todas las pestañas y el tooltip se muestran en el locale activo.
2. Active continúa como la pestaña inicial y cada tab mantiene su query scope actual.
3. Si no hay diálogos activos, el badge sigue mostrando cero y el tooltip no altera el resultado.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Announcements/Pages/ListAnnouncements.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ListAnnouncementsTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Announcements/Pages/ListAnnouncements.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ListAnnouncementsTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ListAnnouncementsTest.php`

### TASK-011: Traducir el detalle de Announcements

Localizar secciones, labels y placeholders del `AnnouncementInfolist`. Conservar la visualización del contenido generado por el administrador y los labels de enums ya localizados. Ampliar la prueba de detalle a ambos locales.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-005, TASK-006, TASK-007

**Acceptance Criteria:**

1. Labels, encabezados y placeholders del detalle aparecen completos en español e inglés.
2. Título, cuerpo enriquecido, imagen y texto del botón mantienen su contenido guardado sin traducirse.
3. El cuerpo del anuncio se presenta con el sanitizador actual; una entrada HTML peligrosa no se renderiza como código ejecutable.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Announcements/Schemas/AnnouncementInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ViewAnnouncementTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Announcements/Schemas/AnnouncementInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ViewAnnouncementTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ViewAnnouncementTest.php`

### TASK-012: Crear el catálogo de Posts

Crear `lang/{en,es}/admin/posts.php` con claves con nombre para la interfaz de administración de Posts: navegación, tablas, filtros, detalle, relation manager anidado y widgets. Las traducciones son para etiquetas de interfaz; no para contenido de las publicaciones.

**Type:** feature · **Priority:** P0 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Los dos archivos PHP son válidos y contienen el mismo árbol de claves.
2. Los valores de cada idioma no están vacíos y los placeholders están sincronizados.
3. No hay claves destinadas a modificar `Post::content` ni otros valores guardados.

**Files to create:**
- `lang/en/admin/posts.php`
- `lang/es/admin/posts.php`

**Files to modify:**
- Ninguno

**writeScope:**
- `lang/en/admin/posts.php`
- `lang/es/admin/posts.php`

**validateCommand:** `rtk php -l lang/en/admin/posts.php && rtk php -l lang/es/admin/posts.php`

### TASK-013: Traducir navegación y tabla de Posts

Añadir labels localizados al recurso Posts y reemplazar los textos fijos en `PostsTable`: imagen, columnas, filtros, placeholders y rango de fechas. Ampliar `ListPostsTest` para ambos locales, manteniendo la búsqueda, filtros y navegación al detalle.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-012

**Acceptance Criteria:**

1. Labels de recurso, columnas y filtros se muestran en español e inglés.
2. Contenido de post y nombre del autor siguen mostrándose como datos, sin ser traducidos ni modificados.
3. El filtro vacío, ambos límites de fecha y el enlace al detalle continúan funcionando.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Posts/PostResource.php`
- `app/Modules/Admin/Filament/Resources/Posts/Tables/PostsTable.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ListPostsTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Posts/PostResource.php`
- `app/Modules/Admin/Filament/Resources/Posts/Tables/PostsTable.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ListPostsTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ListPostsTest.php`

### TASK-014: Traducir el detalle de Posts

Localizar títulos, descripciones, labels y alt text fijo de las secciones del detalle en `PostInfolist`. Formatear fechas según el locale y mantener el contenido de la publicación como dato. Añadir casos en ambos idiomas a `ViewPostTest`.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-012

**Acceptance Criteria:**

1. Los textos fijos del detalle y alt text aparecen en el locale activo.
2. El mensaje del post y sus datos de autor conservan el valor original.
3. La imagen de fallback sigue funcionando cuando la publicación no tiene imagen.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Posts/Schemas/PostInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ViewPostTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Posts/Schemas/PostInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ViewPostTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ViewPostTest.php`

### TASK-015: Traducir el gestor de comentarios dentro de Posts

Localizar labels y filtros de `CommentsRelationManager`, que se muestra en el detalle de una publicación. El botón nativo de Filament conserva sus propias traducciones; la ruta a la pantalla independiente de Comments queda fuera del alcance. Ampliar su prueba para ambos locales.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-012

**Acceptance Criteria:**

1. Las columnas y filtros visibles dentro de la pantalla de Posts se muestran en el locale activo.
2. Búsqueda, filtros de autor/fecha/respuestas y navegación al detalle de comentario mantienen el comportamiento actual.
3. No se traduce ni modifica el recurso independiente `Resources/Comments` en esta tarea.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Posts/RelationManagers/CommentsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/RelationManagers/CommentsRelationManagerTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Posts/RelationManagers/CommentsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/RelationManagers/CommentsRelationManagerTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/RelationManagers/CommentsRelationManagerTest.php`

### TASK-016: Traducir el resumen del recurso Posts

Localizar el heading, etiquetas y descripciones de las estadísticas de `PostsOverview`. Crear una prueba dedicada en el directorio de widgets existente para comprobar ambos idiomas y conservar los datos del gráfico.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-012

**Acceptance Criteria:**

1. El heading, labels y descripciones de las métricas aparecen en ambos idiomas.
2. Los conteos, datos de las gráficas y colores semánticos no cambian al seleccionar otro locale.
3. La consulta continúa limitada a la ventana de siete días y mantiene la autorización de `admin.posts.viewAny`.

**Files to create:**
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Widgets/PostsOverviewTest.php`

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Posts/Widgets/PostsOverview.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Posts/Widgets/PostsOverview.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Widgets/PostsOverviewTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/Widgets/PostsOverviewTest.php`

### TASK-017: Traducir las métricas del detalle de Posts

Localizar las etiquetas y descripciones de `PostActivityOverview`, manteniendo los conteos y gráficos de comentarios, respuestas y likes. Ampliar la prueba del widget para español e inglés y para conservar su caso de registro inválido.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-012

**Acceptance Criteria:**

1. Las tres estadísticas y sus descripciones se muestran según el locale activo.
2. Conteos, rangos y series mantienen sus valores en ambos idiomas.
3. Un administrador sin permiso o un post que ya no es visible sigue sin recibir las métricas.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Posts/Widgets/PostActivityOverview.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Widgets/PostActivityOverviewTest.php`

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Posts/Widgets/PostActivityOverview.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Widgets/PostActivityOverviewTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/Widgets/PostActivityOverviewTest.php`

### TASK-018: Extender las protecciones de claves y textos fijos

Ampliar `AdminTranslationKeysTest` con comprobaciones de las claves de presentación de los enums de Announcements y ampliar `AdminHardcodedTextTest` para escanear ActivityLogs, Announcements, Posts y el preview Blade. El detector debe cubrir métodos de texto usados aquí (`hint`, `badgeTooltip`, `modalCancelActionLabel`) y títulos de tabs, sin marcar los valores dinámicos ni el contenido authored.

**Type:** test · **Priority:** P2 · **Effort:** M · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017

**Acceptance Criteria:**

1. Los catálogos en/es siguen teniendo las mismas claves, valores no vacíos y placeholders; los labels y descripciones de cada enum cubierto existen en ambos locales.
2. El detector falla ante un literal en un método de texto, un tab o el preview de las tres áreas, y pasa con una llamada traducida.
3. Los datos authored y el recurso independiente de Comments quedan fuera del detector de textos de interfaz de SOC-66.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`
- `app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php`

**writeScope:**
- `app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`
- `app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php`

### TASK-019: Sincronizar la documentación de idiomas del panel

Actualizar las secciones existentes sobre idiomas y estado de traducciones en `AGENTS.md`, `CLAUDE.md` y `docs/architecture/backend.md`. Reflejar la cobertura de Activity Log, Announcements y Posts, conservar los límites de Comments y contenido authored, y no crear documentación nueva.

**Type:** docs · **Priority:** P2 · **Effort:** S · **Agent:** general-purpose · **Review:** codex

**Depends on:** TASK-018

**Acceptance Criteria:**

1. Los tres documentos indican de forma consistente qué secciones del panel están traducidas después de SOC-66.
2. La documentación mantiene la convención de catálogos, `TranslatedText::of`, `__()`, locales en/es y las pruebas que protegen traducciones.
3. No quedan afirmaciones obsoletas que digan que Announcements/Posts o el detalle/widgets de Activity Log siguen totalmente en inglés, ni se crean archivos de documentación.

**Files to create:**
- Ninguno

**Files to modify:**
- `AGENTS.md`
- `CLAUDE.md`
- `docs/architecture/backend.md`

**writeScope:**
- `AGENTS.md`
- `CLAUDE.md`
- `docs/architecture/backend.md`

**validateCommand:** `rtk git diff --check -- AGENTS.md CLAUDE.md docs/architecture/backend.md`

## Failure Modes

- Una clave falta en español o cambia un placeholder: falla `AdminTranslationKeysTest` en TASK-018; las pruebas de página fijan los textos clave por locale.
- Una etiqueta se localiza comparando el texto ya traducido para decidir icono/color: se rompen estados semánticos; cada tarea conserva la rama por enum/record y sus pruebas.
- El evento desconocido del Activity Log se cambia en la consulta junto con su presentación: el centinela deja de coincidir; TASK-004 exige traducir solo la salida y conservar el conteo.
- El texto del preview se confunde con contenido authored: TASK-009 traduce copy estático y verifica que título/cuerpo/botón se mantienen y el body sigue sanitizado.
- El detector omite tabs, hints o cancel labels: TASK-018 añade esos patrones y pruebas positivas/negativas antes del cierre.

## Ship Cut

Los tres catálogos arrancan en paralelo. Cada dominio se puede desarrollar y validar en slices independientes; Activity Log queda traducido al terminar TASK-001..004, Announcements al terminar TASK-005..011 y Posts al terminar TASK-012..017. La issue no se considera lista para cerrar hasta que TASK-018 cubra todas las superficies y TASK-019 actualice los tres documentos existentes.

## Test Coverage Map

- **Catálogo Activity Log: sintaxis, claves y presentación detalle/widgets:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-018
- **Announcements: enums, formulario, tabs, tabla, acciones, preview y detalle:** TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-018
- **Posts: tabla, detalle, comentarios anidados y widgets:** TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018
- **Documentación existente y límites del scope:** TASK-019

## Execution Summary

- **Tasks:** 19
- **Layers:** L0: TASK-001, TASK-005, TASK-012; L1: TASK-002, TASK-003, TASK-004, TASK-006, TASK-010, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017; L2: TASK-007; L3: TASK-008, TASK-009, TASK-011; L4: TASK-018; L5: TASK-019
- **Critical path:** TASK-005 → TASK-006 → TASK-007 → TASK-008 → TASK-018 → TASK-019
- Los catálogos Activity Log, Announcements y Posts se inician en paralelo. Sus slices de UI avanzan por dominio y las pruebas guardrail/documentación cierran después de integrar todas las superficies.

## Task Dependencies

- **TASK-002** depende de: TASK-001
- **TASK-003** depende de: TASK-001
- **TASK-004** depende de: TASK-001
- **TASK-006** depende de: TASK-005
- **TASK-007** depende de: TASK-006
- **TASK-008** depende de: TASK-005, TASK-006, TASK-007
- **TASK-009** depende de: TASK-005, TASK-006, TASK-007
- **TASK-010** depende de: TASK-005
- **TASK-011** depende de: TASK-005, TASK-006, TASK-007
- **TASK-013** depende de: TASK-012
- **TASK-014** depende de: TASK-012
- **TASK-015** depende de: TASK-012
- **TASK-016** depende de: TASK-012
- **TASK-017** depende de: TASK-012
- **TASK-018** depende de: TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-017
- **TASK-019** depende de: TASK-018

