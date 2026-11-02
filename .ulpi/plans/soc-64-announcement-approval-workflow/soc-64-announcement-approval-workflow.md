# Plan: SOC-64: Approval workflow for system announcements

## Overview

Implementar el flujo draft → in_review → approved/rejected para anuncios del sistema. Solo anuncios aprobados se sirven a la app. El panel permite enviar, aprobar y rechazar con historial, auditoría privada y notificaciones de base de datos. La expansión cierra los huecos de permisos de lectura, comentarios de aprobación, re revisión por cambios de imagen y protección de acciones obsoletas.

**Linear:** [SOC-64](https://linear.app/social-hub-ang/issue/SOC-64/feature-add-an-approval-workflow-for-system-announcements-draft-review) · **Modo:** EXPANSION · **Revisión predeterminada:** Codex

## Scope Challenge

EXPANSION confirmado por el usuario; revisión predeterminada Codex. Se conserva todo el alcance de SOC-64 y se incluyen los cierres necesarios identificados en la revisión: el rol reviewer obtiene lectura mínima; aprobación puede guardar comentario; cambios de imagen cuentan como contenido; una edición que vuelve a revisión identifica al nuevo remitente y notifica; las acciones de revisión comprueban el ID del último envío además del estado para rechazar decisiones obsoletas. SOC-67 queda fuera del alcance. Las tareas de documentación SOC-64 esperan a los pasos SOC-67 que editan los mismos documentos.

## Prerequisites

- SOC-61 y SOC-63 están implementadas: módulo Announcement, servicio, policy, resource Filament, prop compartida y presentación React.
- ActivityLogger, Admin RBAC, traducciones EN/ES del panel y los patrones de cambios append-only de AccountStatus están disponibles.
- User ya usa Laravel Notifiable; las notificaciones de Filament se habilitan con databaseNotifications() y la migración notifications global.
- No se añaden dependencias nuevas; Filament Database Notifications usa polling, sin Reverb ni correo.
- SOC-67 está en curso y permanece independiente: no se modifican sus archivos de React Settings ni su plan.

## Non-Goals

- Cambios funcionales en React/Inertia o agregar status al tipo frontend de anuncios.
- Correo, push, broadcasting, Reverb o aprobación automática.
- Varios niveles de aprobación o mínimo de aprobadores.
- Asignar automáticamente el rol reviewer a administradores de producción.

## Contracts

- Status persistido y tipado: draft, in_review, approved, rejected; la columna status usa draft por default para anuncios existentes y nuevos.
- Announcement::availableTo() y visibleTo() excluyen todo lo que no esté approved; visibleTo() sigue siendo la única regla de lo que recibe la aplicación.
- Transiciones permitidas: draft → in_review; in_review → approved/rejected; rejected → in_review; editar contenido aprobado → in_review. Duplicar crea draft; despublicar solo aplica a anuncios approved vigentes.
- No puede revisar el autor ni el actor del envío más reciente. Cada fila de announcement_reviews almacena en reviewed_by al actor de esa acción; el último submitted/resubmitted identifica el remitente actual.
- Una aprobación admite comentario opcional; rechazo requiere comentario de 1 a 1000 caracteres. Los comentarios viven en review history y en la notificación de decisión, nunca en activity log.
- Cada transición bloquea la fila y guarda status, review row y auditoría en una transacción. La acción Filament transmite el ID del último envío visto y falla si cambió.
- Cambios de imagen añadida, reemplazada o eliminada son cambios de contenido. El hook afterSave() de Filament detecta el estado de media ya persistido y pide al servicio iniciar la re revisión.
- El rol admin-announcements-reviewer recibe review, viewAny y view; no recibe create, update ni delete. Las notificaciones de envío excluyen al autor y al actor, las de decisión van al autor.
- Seeder y documentación reflejan estados, historial, permisos, notificaciones y eventos sin inventar aprobaciones históricas para los anuncios existentes.

## Existing Code Leverage

- Announcement::availableTo() y visibleTo() ya centralizan audiencia, fechas y descartes; basta sumar el filtro de estado en la misma composición.
- AnnouncementService centraliza escrituras y auditoría; unpublish() ya usa lockForUpdate() como referencia para las transiciones.
- AccountStatusService y AccountStatusChangesRelationManager muestran el patrón de bloqueo, tabla append-only e historial de solo lectura.
- AnnouncementPolicy, AdminPermission, AdminRole y AdminAuthorizationSeeder son las superficies RBAC existentes.
- AdminPanelProvider aún no llama databaseNotifications(); User ya implementa Notifiable.
- EditAnnouncement usa SpatieMediaLibraryFileUpload; Filament EditRecord documenta afterSave() después de guardar el formulario y sus relaciones.
- SOC-67 TASK-051 modifica AdminTranslationKeysTest.php; este plan usa una prueba de traducciones de anuncios separada.

## Tasks

### TASK-001: Definir enums del flujo y del historial

Crear `AnnouncementStatus` (`draft`, `in_review`, `approved`, `rejected`) y `AnnouncementReviewAction` (`submitted`, `approved`, `rejected`, `resubmitted`); ampliar la prueba existente de enums.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
1. Las pruebas fijan los cuatro valores exactos de ambos enums y verifican que `from()` convierte cada valor persistido al caso correspondiente.
2. Un valor de estado o acción desconocido se rechaza en vez de convertirse silenciosamente en otro estado.

**writeScope:**
- app/Modules/Announcement/Enums/AnnouncementStatus.php (nuevo)
- app/Modules/Announcement/Enums/AnnouncementReviewAction.php (nuevo)
- app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Enums/AnnouncementEnumsTest.php`

**Review:** codex

### TASK-002: Persistir el estado y el historial de revisión

Crear una migración nueva del módulo Announcement: `announcements.status` con default `draft` para anuncios existentes y nuevos, sin backfill a `approved` porque el módulo aún no está en producción; y tabla append-only `announcement_reviews` con FK en cascada al anuncio, acción, comentario nullable, actor nullable con `nullOnDelete` y `created_at`. Generar con `rtk php artisan make:migration add_approval_workflow_to_announcements_table --path=app/Modules/Announcement/Database/Migrations --no-interaction` y no modificar migraciones anteriores.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
1. Anuncios existentes y nuevos reciben `draft` por el default de la columna; no se realiza backfill a `approved`.
2. Las filas de revisión conservan la acción y el comentario; eliminar un usuario deja el actor en null y eliminar el anuncio elimina su historial.
3. Rollback elimina solo la tabla y columna nuevas y permite volver a migrar.

**writeScope:**
- app/Modules/Announcement/Database/Migrations/2026_10_30_173738_add_approval_workflow_to_announcements_table.php (nuevo)
- app/Modules/Announcement/Tests/Database/AnnouncementApprovalMigrationTest.php (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Database/AnnouncementApprovalMigrationTest.php`

**Review:** codex

### TASK-003: Conectar modelos, casts y relaciones

Agregar el cast de estado y la relación `reviews()` a `Announcement`; crear `AnnouncementReview` y su factory del módulo, con cast de acción y relaciones al anuncio y al actor nullable. Ampliar las pruebas del modelo.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001, TASK-002

**Acceptance Criteria:**
1. Un anuncio nuevo se lee como `AnnouncementStatus::Draft` y una fila de historial como su caso `AnnouncementReviewAction`.
2. La relación devuelve el historial ordenado por `created_at` e ID, y el actor eliminado aparece como null.

**writeScope:**
- app/Modules/Announcement/Models/AnnouncementReview.php (nuevo)
- app/Modules/Announcement/Database/Factories/AnnouncementReviewFactory.php (nuevo)
- app/Modules/Announcement/Models/Announcement.php
- app/Modules/Announcement/Tests/Models/AnnouncementModelTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Models/AnnouncementModelTest.php`

**Review:** codex

### TASK-004: Filtrar anuncios no aprobados de la aplicación

Agregar el requisito `approved` a `availableTo()` —y por composición a `visibleTo()`— para que también se aplique al permiso de descarte y a su sincronización. Mantener intactos el horario, la audiencia, los descartes y el límite actual; verificar la prop compartida de Inertia.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-003

**Acceptance Criteria:**
1. `visibleTo()` y `availableTo()` devuelven solo anuncios aprobados para invitado y usuario autenticado.
2. La prop compartida excluye `draft`, `in_review` y `rejected`, conservando las reglas existentes de fechas, audiencia y descarte.
3. Un intento de descartar un anuncio no aprobado no queda autorizado.

**writeScope:**
- app/Modules/Announcement/Database/Factories/AnnouncementFactory.php
- app/Modules/Announcement/Models/Announcement.php
- app/Modules/Announcement/Tests/Models/AnnouncementModelTest.php
- app/Modules/Announcement/Tests/Queries/AnnouncementVisibilityTest.php
- app/Modules/Announcement/Tests/Props/SharedAnnouncementsTest.php
- app/Modules/Announcement/Tests/Crud/AnnouncementDismissTest.php
- app/Modules/Announcement/Tests/Crud/AnnouncementDismissalSyncTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Models/AnnouncementModelTest.php app/Modules/Announcement/Tests/Queries/AnnouncementVisibilityTest.php app/Modules/Announcement/Tests/Props/SharedAnnouncementsTest.php app/Modules/Announcement/Tests/Crud/AnnouncementDismissTest.php app/Modules/Announcement/Tests/Crud/AnnouncementDismissalSyncTest.php`

**Review:** codex

### TASK-005: Agregar permiso y rol de revisión

Agregar `admin.announcements.review` y `admin-announcements-reviewer` a los enums canónicos de Admin. El rol de revisor incluye lectura mínima (`viewAny`, `view`) además del permiso `review`, y no recibe permisos de creación, edición o eliminación.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
1. Las pruebas fijan los valores exactos del permiso y el rol.
2. La configuración de enums conserva todos los valores existentes y no concede permisos de escritura al nuevo rol.

**writeScope:**
- app/Modules/Admin/Tests/Enums/AdminAnnouncementAuthorizationEnumsTest.php (nuevo)
- app/Modules/Admin/Enums/AdminPermission.php
- app/Modules/Admin/Enums/AdminRole.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Enums/AdminAnnouncementAuthorizationEnumsTest.php`

**Review:** codex

### TASK-006: Sembrar permisos de lectura y revisión

Registrar el nuevo permiso y el rol en `AdminAuthorizationSeeder`, otorgando al rol reviewer `admin.announcements.viewAny`, `view` y `review`. Añadir regresiones al test existente del seeder.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-005

**Acceptance Criteria:**
1. El seeder crea el rol reviewer con permisos de lectura y revisión en el guard `admin`.
2. El rol no recibe create, update ni delete; volver a ejecutar el seeder conserva las asignaciones existentes.

**writeScope:**
- app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php
- app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**Review:** codex

### TASK-007: Asignar el rol reviewer al administrador local

Actualizar `AdminUserSeeder` para asignar el rol reviewer a Angel en `local` y `testing`, manteniendo los roles actuales y el comportamiento de no alterar usuarios fuera de esos entornos.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006

**Acceptance Criteria:**
1. Angel recibe el rol reviewer en `local` y `testing` sin perder sus roles actuales.
2. Fuera de `local` y `testing`, el seeder no asigna el rol automáticamente.

**writeScope:**
- app/Modules/Admin/Database/Seeders/AdminUserSeeder.php
- app/Modules/Admin/Tests/Database/AdminUserSeederTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminUserSeederTest.php`

**Review:** codex

### TASK-008: Implementar transiciones de revisión y auditoría

Agregar en `AnnouncementService` las transiciones enviar, aprobar y rechazar. Cada transición relee y bloquea la fila dentro de una transacción, valida estado origen y actor, escribe `announcement_reviews` y la auditoría de actividad en la misma transacción. Aprobar/rechazar recibe el ID del último envío que vio el revisor y lo compara bajo bloqueo. `reviewed_by` identifica al actor de toda acción, incluidos envíos. El rechazo exige comentario de hasta 1000 caracteres; la aprobación acepta comentario opcional.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-003, TASK-005

**Acceptance Criteria:**
1. Solo se permiten `draft → in_review`, `in_review → approved/rejected` y `rejected → in_review`; los intentos fuera de transición fallan sin filas de historial ni auditoría.
2. El autor y el remitente más reciente no pueden aprobar ni rechazar; un ID de envío obsoleto o un estado ya cambiado falla sin escritura adicional.
3. Rechazar sin comentario o con más de 1000 caracteres falla; aprobar permite comentario opcional y el activity log nunca guarda comentarios ni contenido.

**writeScope:**
- app/Modules/Announcement/Services/AnnouncementService.php
- app/Modules/Announcement/Tests/Services/AnnouncementServiceTest.php
- app/Modules/ActivityLog/Enums/Events/ActivityLogAdminEvent.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Services/AnnouncementServiceTest.php`

**Review:** codex

### TASK-009: Aplicar autorización de envío y revisión

Extender `AnnouncementPolicy` con `submit` y `review`. Envío requiere que la persona tenga permiso de edición y sea el autor; revisión requiere `admin.announcements.review` y que la persona no sea ni autor ni remitente más reciente. El servicio conserva las mismas defensas para llamadas directas.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-006, TASK-008

**Acceptance Criteria:**
1. La policy permite enviar al autor autorizado en `draft` o `rejected` y deniega usuarios sin permiso o que no sean autores.
2. La policy deniega revisar al autor y al remitente más reciente, aunque tengan `admin.announcements.review`.
3. Un reviewer puede ver el recurso con el rol dedicado sin obtener permisos de escritura.

**writeScope:**
- app/Modules/Announcement/Policies/AnnouncementPolicy.php
- app/Modules/Announcement/Tests/Policies/AnnouncementPolicyTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Policies/AnnouncementPolicyTest.php`

**Review:** codex

### TASK-010: Reabrir anuncios aprobados al cambiar contenido

Ajustar `AnnouncementService::update()`, `duplicate()` y `unpublish()`: cambios de contenido de un anuncio aprobado lo devuelven a `in_review` y registran al editor como remitente mediante `resubmitted`; cambios solo de fechas no requieren otra aprobación; duplicar siempre crea `draft`; despublicar exige un anuncio aprobado y vigente. La edición relee y bloquea la fila dentro de su transacción para no pisar una decisión concurrente.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008

**Acceptance Criteria:**
1. Editar título, cuerpo, botón, tipo, audiencia, presentación o dismissibility en un aprobado lo pone en revisión; editar solo fechas conserva `approved`.
2. Duplicar un aprobado crea `draft`; un borrador, rechazado o en revisión no se puede despublicar.
3. La edición aprobada registra una fila `resubmitted` con el actor; una edición sin cambios no genera historial y una decisión concurrente nunca queda sobrescrita por datos obsoletos.

**writeScope:**
- app/Modules/Announcement/Services/AnnouncementService.php
- app/Modules/Announcement/Tests/Services/AnnouncementServiceTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Services/AnnouncementServiceTest.php`

**Review:** codex

### TASK-011: Incluir cambios de imagen en la re revisión

Usar el hook `afterSave()` de Filament `EditRecord`, que corre después de guardar relaciones y archivos, para detectar imagen añadida, reemplazada o eliminada y registrar la nueva revisión por medio de `AnnouncementService`. Mantener separado el guardado del archivo y la decisión transaccional del dominio; una misma edición que cambia campos e imagen produce una sola fila `resubmitted`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-010

**Acceptance Criteria:**
1. Añadir, reemplazar o quitar la imagen de un anuncio aprobado lo devuelve a `in_review` y atribuye el reenvío al editor.
2. Guardar sin cambiar la imagen no crea una entrada duplicada en el historial; combinar cambios de formulario e imagen produce una sola nueva revisión.
3. La edición de imagen de un anuncio no aprobado no lo publica ni lo saca de su estado actual.

**writeScope:**
- app/Modules/Admin/Filament/Resources/Announcements/Pages/EditAnnouncement.php
- app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/EditAnnouncementTest.php
- app/Modules/Announcement/Services/AnnouncementService.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/EditAnnouncementTest.php`

**Review:** codex

### TASK-012: Habilitar notificaciones de base de datos en Admin

Generar la migración global con `rtk php artisan make:notifications-table --no-interaction` y habilitar `databaseNotifications()` en `AdminPanelProvider`. El modelo User ya usa Laravel `Notifiable`; mantener el polling de Filament y no configurar broadcasting.

**Type:** infra · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**
1. La migración `notifications` se crea en `database/migrations/` y puede migrarse y revertirse.
2. El panel Admin habilita las notificaciones de base de datos y el usuario puede recibirlas.
3. La configuración no añade Reverb, broadcasting ni correo.

**writeScope:**
- database/migrations/*_create_notifications_table.php (nuevo)
- app/Modules/Admin/Tests/Filament/AdminPanelNotificationsTest.php (nuevo)
- app/Modules/Admin/Providers/AdminPanelProvider.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/AdminPanelNotificationsTest.php`

**Review:** codex

### TASK-013: Enviar notificaciones a revisores y autores

Después de confirmar cada transacción, guardar notificaciones Filament en la base de datos: al enviar o reenviar, a revisores con permiso excluyendo autor y actor; al aprobar o rechazar, al autor con el comentario de decisión. Las notificaciones muestran el comentario de revisión, nunca el contenido del anuncio.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-008, TASK-010, TASK-012

**Acceptance Criteria:**
1. Enviar y reenviar notifica a revisores elegibles; aprobar y rechazar notifica al autor con el comentario opcional.
2. Fallar una transición no crea notificaciones; eliminar al autor evita un envío sin romper la transacción.
3. El activity log y la notificación de envío no exponen el comentario de rechazo ni el cuerpo del anuncio.

**writeScope:**
- app/Modules/Announcement/Services/AnnouncementService.php
- app/Modules/Announcement/Tests/Services/AnnouncementServiceTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Services/AnnouncementServiceTest.php`

**Review:** codex

### TASK-014: Mostrar y filtrar el estado en la tabla

Agregar a la tabla de anuncios la columna de estado con badge de color y filtro por enum. Resolver colores desde el caso enum, no comparando etiquetas traducidas.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**
1. Cada estado se muestra con su badge y el filtro devuelve solo los anuncios del estado elegido.
2. Un valor nulo o inválido no se presenta como aprobado ni produce una etiqueta hardcodeada.

**writeScope:**
- app/Modules/Admin/Tests/Filament/Resources/Announcements/Tables/AnnouncementStatusTableTest.php (nuevo)
- app/Modules/Admin/Filament/Resources/Announcements/Tables/AnnouncementsTable.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Tables/AnnouncementStatusTableTest.php`

**Review:** codex

### TASK-015: Crear la pestaña de anuncios pendientes

Agregar la pestaña `Pending review` con consulta `in_review` y contador; mostrarla solo a quien puede revisar. Mantener las pestañas de fechas existentes y ajustar el contador de dialogs activos para contar solo aprobados.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-009, TASK-014

**Acceptance Criteria:**
1. Un reviewer ve la pestaña con los anuncios `in_review` y un contador correcto.
2. Un usuario sin capacidad de revisión no ve la pestaña y no obtiene registros por su consulta.
3. El contador de dialogs activos excluye borradores, rechazados y anuncios en revisión.

**writeScope:**
- app/Modules/Admin/Filament/Resources/Announcements/Pages/ListAnnouncements.php
- app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ListAnnouncementsTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/ListAnnouncementsTest.php`

**Review:** codex

### TASK-016: Agregar acciones de enviar, aprobar y rechazar

Crear un concern compartido para las acciones de revisión y conectarlo a la tabla y al detalle. Enviar aparece al autor en `draft` o `rejected`; aprobar y rechazar aparecen a revisores distintos del autor y del último remitente. Rechazo requiere comentario y aprobación permite uno opcional. La acción transmite el ID del envío que vio el revisor para detectar cambios concurrentes aunque el estado siga `in_review`.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-009, TASK-013, TASK-014

**Acceptance Criteria:**
1. Las tres acciones aparecen solo para los actores y estados permitidos en tabla y detalle.
2. El servidor rechaza una acción si cambió el estado o el ID del último envío desde que abrió el modal.
3. Un rechazo sin comentario no se guarda; aprobación con comentario opcional sí se guarda en el historial.

**writeScope:**
- app/Modules/Admin/Filament/Concerns/BuildsAnnouncementReviewActions.php (nuevo)
- app/Modules/Admin/Filament/Resources/Announcements/Tables/AnnouncementsTable.php
- app/Modules/Admin/Filament/Resources/Announcements/Pages/ViewAnnouncement.php

**validateCommand:** `rtk vendor/bin/phpstan analyse --memory-limit=2G app/Modules/Admin/Filament/Concerns/BuildsAnnouncementReviewActions.php app/Modules/Admin/Filament/Resources/Announcements/Tables/AnnouncementsTable.php app/Modules/Admin/Filament/Resources/Announcements/Pages/ViewAnnouncement.php`

**Review:** codex

### TASK-017: Cubrir permisos, validación y estado obsoleto de acciones

Crear pruebas de integración Filament para las acciones de revisión en tabla y detalle, incluyendo validación modal, autor/remitente, permisos, cambios concurrentes y rechazo desde una llamada de acción directa.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-016

**Acceptance Criteria:**
1. Las pruebas cubren los tres caminos permitidos y verifican estado, historial y notificación resultantes.
2. Las pruebas deniegan actor sin permiso, autor, último remitente, estado inválido y envío obsoleto sin escritura parcial.

**writeScope:**
- app/Modules/Admin/Tests/Filament/Resources/Announcements/Actions/AnnouncementReviewActionsTest.php (nuevo)

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Actions/AnnouncementReviewActionsTest.php`

**Review:** codex

### TASK-018: Mostrar estado y aviso de re aprobación en el formulario

Mostrar el estado en el detalle y un aviso no bloqueante en el formulario cuando editar contenido aprobado lo devolverá a revisión. Usar los textos traducidos y conservar las operaciones de fecha que no reabren la revisión.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-010, TASK-016

**Acceptance Criteria:**
1. El detalle muestra el estado actual del anuncio.
2. El formulario advierte sobre la re aprobación de cambios de contenido aprobados y permite guardar.
3. Editar solo fechas no muestra el aviso ni cambia el estado.

**writeScope:**
- app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/AnnouncementWorkflowFieldsTest.php (nuevo)
- app/Modules/Admin/Filament/Resources/Announcements/Schemas/AnnouncementForm.php
- app/Modules/Admin/Filament/Resources/Announcements/Schemas/AnnouncementInfolist.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/Pages/AnnouncementWorkflowFieldsTest.php`

**Review:** codex

### TASK-019: Exponer el historial de revisión de solo lectura

Crear y registrar un Relation Manager de solo lectura con fecha, acción, comentario y actor; un actor eliminado se presenta como `Deleted user`. Mantener el comentario fuera de cualquier actividad de auditoría.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**
1. El detalle enumera el historial cronológicamente y muestra el actor eliminado con el texto de respaldo.
2. El Relation Manager no ofrece crear, editar ni borrar filas.
3. Los comentarios de aprobación y rechazo se muestran desde `announcement_reviews` y no desde activity log.

**writeScope:**
- app/Modules/Admin/Filament/Resources/Announcements/RelationManagers/AnnouncementReviewsRelationManager.php (nuevo)
- app/Modules/Admin/Tests/Filament/Resources/Announcements/RelationManagers/AnnouncementReviewsRelationManagerTest.php (nuevo)
- app/Modules/Admin/Filament/Resources/Announcements/AnnouncementResource.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Announcements/RelationManagers/AnnouncementReviewsRelationManagerTest.php`

**Review:** codex

### TASK-020: Traducir el flujo en el panel EN/ES

Agregar claves para estados, acciones, tabs, filtros, historial, mensajes de formulario y notificaciones en `lang/{en,es}/admin/announcements.php`. Crear una prueba de traducciones propia para no editar `AdminTranslationKeysTest.php`, que está en el writeScope del plan SOC-67.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-013, TASK-016, TASK-018, TASK-019

**Acceptance Criteria:**
1. Todas las claves nuevas existen en inglés y español, no están vacías y usan placeholders equivalentes.
2. Los estados y acciones visibles del historial tienen etiquetas traducidas para cada caso enum.
3. Las pruebas verifican que los archivos nuevos de catálogo no dependen del idioma por defecto.

**writeScope:**
- app/Modules/Admin/Tests/Translations/AnnouncementWorkflowTranslationsTest.php (nuevo)
- lang/en/admin/announcements.php
- lang/es/admin/announcements.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Translations/AnnouncementWorkflowTranslationsTest.php`

**Review:** codex

### TASK-021: Traducir los nuevos eventos de auditoría

Agregar nombres en inglés y español para `announcement_submitted`, `announcement_approved` y `announcement_rejected`; ampliar la prueba de traducciones creada en TASK-020.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-008, TASK-020

**Acceptance Criteria:**
1. Cada evento nuevo se resuelve en ambos idiomas desde el catálogo `admin/activity-log`.
2. El test falla si falta una traducción de cualquier evento nuevo.

**writeScope:**
- lang/en/admin/activity-log.php
- lang/es/admin/activity-log.php
- app/Modules/Admin/Tests/Translations/AnnouncementWorkflowTranslationsTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Translations/AnnouncementWorkflowTranslationsTest.php`

**Review:** codex

### TASK-022: Sembrar estados e historial de revisión

Ampliar `AnnouncementSeeder` para conservar aprobados los anuncios vigentes, vencidos y despublicados, y crear algunos borradores, pendientes y rechazados con filas de historial y actores coherentes que no aprueben sus propios anuncios.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-003, TASK-007, TASK-008

**Acceptance Criteria:**
1. El seeder produce ejemplos en los cuatro estados y las filas de revisión coinciden con cada transición sembrada.
2. Los casos visibles existentes permanecen aprobados y siguen respetando fechas y audiencias.
3. Repetir el seeder no duplica anuncios ni historial.

**writeScope:**
- app/Modules/Announcement/Database/Seeders/AnnouncementSeeder.php
- app/Modules/Announcement/Tests/Seeders/AnnouncementSeederTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/Announcement/Tests/Seeders/AnnouncementSeederTest.php`

**Review:** codex

### TASK-023: Derivar la auditoría sembrada del historial

Extender `ActivityLogSeeder` para derivar envío, aprobación y rechazo desde `announcement_reviews`, igual que deriva los cambios de estado de cuenta. Nunca copiar `comment` ni texto de anuncio a las propiedades de activity log.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-008, TASK-022

**Acceptance Criteria:**
1. Cada fila sembrada de revisión genera el evento de auditoría correspondiente con actor, anuncio e ID de historial.
2. Ninguna propiedad del activity log incluye el comentario ni título/cuerpo.
3. Sin filas de historial, el seeder conserva los eventos actuales de anuncios.

**writeScope:**
- app/Modules/ActivityLog/Database/Seeders/ActivityLogSeeder.php
- app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php`

**Review:** codex

### TASK-024: Actualizar documentación de backend y convenciones

Documentar los estados y transiciones, tabla de historial, rol/permiso reviewer, asignación operativa a administradores existentes, notificaciones de panel y privacidad de comentarios en CLAUDE.md, AGENTS.md y arquitectura backend. Esperar a que SOC-67 complete sus ediciones concurrentes de esos mismos archivos.

**Type:** docs · **Effort:** M · **Agent:** general-purpose · **Priority:** P2

**Depends on:** TASK-007, TASK-013, TASK-019, TASK-021, TASK-023

**Dependencias externas:** SOC-67 TASK-055 (CLAUDE.md y AGENTS.md); SOC-67 TASK-056 (docs/architecture/backend.md)

**Acceptance Criteria:**
1. Las tres fuentes describen el mismo flujo, rol, permiso, eventos y política de visibilidad.
2. La documentación indica cómo conceder el rol reviewer a administradores existentes sin ampliar automáticamente sus permisos.
3. El procedimiento de operación no afirma que las notificaciones sean de tiempo real.

**writeScope:**
- CLAUDE.md
- AGENTS.md
- docs/architecture/backend.md

**validateCommand:** `rtk git diff --check -- CLAUDE.md AGENTS.md docs/architecture/backend.md`

**Review:** codex

### TASK-025: Documentar el contrato frontend de anuncios

Actualizar arquitectura frontend para dejar claro que React recibe el mismo payload de presentación y el backend entrega solo anuncios aprobados; el cliente no modela estados de revisión. Esperar a que SOC-67 actualice el mismo documento.

**Type:** docs · **Effort:** XS · **Agent:** general-purpose · **Priority:** P2

**Depends on:** TASK-004

**Dependencias externas:** SOC-67 TASK-056 (docs/architecture/frontend.md)

**Acceptance Criteria:**
1. La documentación atribuye el filtro de aprobación al scope backend y mantiene el payload frontend sin `status`.
2. La descripción del componente de anuncios conserva las reglas actuales de toast/dialog y descarte.

**writeScope:**
- docs/architecture/frontend.md

**validateCommand:** `rtk git diff --check -- docs/architecture/frontend.md`

**Review:** codex

## Failure Modes

- Un modal de revisión queda abierto mientras alguien cambia el estado o el último envío: comparar estado y review ID bajo bloqueo; fallar sin historial, auditoría ni notificación parcial (TASK-008, TASK-016).
- Se edita la imagen sin cambios en columnas del modelo: observar la imagen tras afterSave() y probar alta, reemplazo, eliminación y no-op (TASK-011).
- Un reviewer tiene permiso review pero no puede llegar al recurso: asignar y probar viewAny/view junto con review (TASK-005, TASK-006, TASK-009).
- Filament o un cliente intenta mostrar un anuncio sin aprobar mediante su ID: availableTo/visibleTo filtran approved antes del endpoint de descarte y del payload compartido (TASK-004).
- El usuario elimina al autor o actor: las FKs dejan actor null y el historial muestra Deleted user; no se intenta notificar al autor eliminado (TASK-002, TASK-013, TASK-019).
- SOC-67 escribe simultáneamente las guías compartidas: mantener la edición de traducciones de SOC-64 en un test propio y esperar SOC-67 TASK-055/056 antes de documentación (TASK-020, TASK-024, TASK-025).
- Comentario contiene datos sensibles: guardarlo únicamente en announcement_reviews y en notificación de decisión, nunca en properties de activity log (TASK-008, TASK-013, TASK-023).

## Ship Cut

No habilitar acciones de aprobación antes de que migración, modelo, servicio, policy y permisos estén integrados. La experiencia revisable requiere TASK-001..021; seeders y auditoría de ejemplo se completan en TASK-022..023. Las guías se publican después de SOC-67 TASK-055/056 y TASK-024..025.

## Test Coverage Map

- **Enums, migración y relaciones:** TASK-001, TASK-002, TASK-003
- **Visibilidad, payload compartido y descarte:** TASK-004
- **RBAC y policy:** TASK-005, TASK-006, TASK-007, TASK-009
- **Transiciones, concurrencia, comentarios y auditoría:** TASK-008, TASK-010
- **Cambios de imagen y Filament edit:** TASK-011
- **Notificaciones de base de datos:** TASK-012, TASK-013
- **Tabla, queue tab, acciones, detalle e historial:** TASK-014, TASK-015, TASK-016, TASK-017, TASK-018, TASK-019
- **Traducciones de panel y Activity Log:** TASK-020, TASK-021
- **Seeders:** TASK-022, TASK-023
- **Documentación:** TASK-024, TASK-025

## Execution Summary

- Tareas: 25.
- Camino crítico (10 tareas): TASK-001 → TASK-003 → TASK-008 → TASK-010 → TASK-013 → TASK-016 → TASK-018 → TASK-020 → TASK-021 → TASK-024.
- Primera capa paralela: TASK-001, TASK-002, TASK-005, TASK-012.
- SOC-67 plan inspeccionado: no comparte los archivos de dominio, Filament ni anuncios. Coincide en AdminTranslationKeysTest.php, CLAUDE.md, AGENTS.md y docs/architecture/{frontend,backend}.md; SOC-64 evita la colisión de test y aplaza la documentación hasta SOC-67 TASK-055/056.

## Task Dependencies

- **TASK-001** ← ninguna
- **TASK-002** ← ninguna
- **TASK-003** ← TASK-001, TASK-002
- **TASK-004** ← TASK-003
- **TASK-005** ← ninguna
- **TASK-006** ← TASK-005
- **TASK-007** ← TASK-006
- **TASK-008** ← TASK-003, TASK-005
- **TASK-009** ← TASK-006, TASK-008
- **TASK-010** ← TASK-008
- **TASK-011** ← TASK-010
- **TASK-012** ← ninguna
- **TASK-013** ← TASK-008, TASK-010, TASK-012
- **TASK-014** ← TASK-003
- **TASK-015** ← TASK-009, TASK-014
- **TASK-016** ← TASK-009, TASK-013, TASK-014
- **TASK-017** ← TASK-016
- **TASK-018** ← TASK-010, TASK-016
- **TASK-019** ← TASK-003
- **TASK-020** ← TASK-013, TASK-016, TASK-018, TASK-019
- **TASK-021** ← TASK-008, TASK-020
- **TASK-022** ← TASK-003, TASK-007, TASK-008
- **TASK-023** ← TASK-008, TASK-022
- **TASK-024** ← TASK-007, TASK-013, TASK-019, TASK-021, TASK-023; externa: SOC-67 TASK-055 (CLAUDE.md y AGENTS.md), SOC-67 TASK-056 (docs/architecture/backend.md)
- **TASK-025** ← TASK-004; externa: SOC-67 TASK-056 (docs/architecture/frontend.md)
