# Plan: SOC-66: Traducir el recurso independiente de Comments en el panel Admin

## Overview

Ampliar SOC-66 con la localización al español e inglés del recurso independiente de Comments que quedó fuera del alcance inicial. El recurso no tiene listado ni aparece en navegación: esta expansión cubre su página de detalle, la tabla/modal de respuestas y el widget de actividad, además de guardrails y sincronización de documentación.

**Linear:** [SOC-66](https://linear.app/social-hub-ang/issue/SOC-66/feature-translate-activity-log-announcements-and-posts-into-spanish) · **Modo:** EXPANSION · **Revisión predeterminada:** codex

## Scope Challenge

EXPANSION: el usuario limitó esta ampliación a Comments independiente dentro de SOC-66. El alcance real es la página de detalle existente y sus componentes asociados; no se agregará un listado ni navegación nueva. El gestor de comentarios anidado en Posts ya está traducido y no se vuelve a trabajar.

## Prerequisites

- La traducción base de SOC-66 ya cubre Activity Log, Announcements, Posts y Comments anidado en Posts; queda pendiente el recurso independiente `app/Modules/Admin/Filament/Resources/Comments/`.
- `CommentResource` tiene navegación oculta y registra solo la ruta `view`; no existe página de listado. La lectura se autoriza con `CommentPolicy` y `admin.posts.view`.
- Las superficies existentes son `CommentInfolist`, `RepliesRelationManager` y `CommentActivityOverview`, con pruebas dedicadas en `app/Modules/Admin/Tests/Filament/Resources/Comments/`.
- `AdminTranslationKeysTest` detecta automáticamente los catálogos bajo `lang/en/admin/`; `AdminHardcodedTextTest` todavía excluye el recurso independiente de Comments.
- No se requieren dependencias, rutas, permisos, cambios de esquema ni nuevas capacidades de escritura.

## Non-Goals

- Agregar un index/listado de Comments o mostrar el recurso en la navegación del panel.
- Traducir o modificar mensajes, nombres, contenido de respuestas u otros datos escritos por usuarios.
- Volver a traducir el gestor de Comments anidado dentro de Posts o el relation manager de Posts dentro de Users.
- Cambiar políticas, permisos, auditoría, filtrado, paginación, relaciones o comportamiento de solo lectura.
- Traducir la interfaz React, los correos de invitación u otras áreas pendientes fuera del recurso independiente de Comments.

## Contracts

- Todo texto fijo del recurso usa claves nombradas del nuevo catálogo `lang/{en,es}/admin/comments.php`; en PHP se usa `TranslatedText::of('admin/comments.<clave>')`.
- Los catálogos inglés y español tienen las mismas claves, valores no vacíos y placeholders sincronizados.
- El recurso permanece oculto de navegación y conserva únicamente su página directa de detalle, con autorización y mutaciones denegadas como ahora.
- El texto del comentario y de sus respuestas, los nombres de autores y los datos de contexto permanecen como datos originales; solo se localiza copy de interfaz.
- Las respuestas siguen restringidas al comentario seleccionado, con los filtros, la paginación y la visibilidad condicional existentes.

## Existing Code Leverage

- `CommentResource` ya oculta la navegación y registra solamente `ViewComment`; `CommentInfolist` concentra las secciones del detalle.
- `RepliesRelationManager` contiene las columnas, filtros, acciones y modal de solo lectura para replies.
- `CommentActivityOverview` calcula replies y likes para el comentario seleccionado y conserva su autorización existente.
- `ViewCommentTest`, `RepliesRelationManagerTest` y `CommentActivityOverviewTest` ya cubren contenido, permisos, filtros, paginación y estados opcionales.
- `AdminTranslationKeysTest` autodetecta el catálogo nuevo; `AdminHardcodedTextTest` puede incluir el directorio Comments sin nueva infraestructura.

## Tasks

### TASK-020: Crear el catálogo de traducción de Comments

Crear el catálogo `admin/comments` en inglés y español con claves para los textos fijos del recurso de detalle, las respuestas y el widget de actividad. Reutilizar claves comunes ya existentes cuando representen exactamente el mismo texto, y no crear claves para contenido escrito por usuarios.

**Type:** feature · **Priority:** P0 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** Ninguna

**Acceptance Criteria:**
1. Los dos catálogos son PHP válido y contienen los mismos nombres de clave con valores no vacíos.
2. Los placeholders están sincronizados entre inglés y español; no se incluyen mensajes ni nombres de autores como textos traducibles.
3. `AdminTranslationKeysTest` detecta el catálogo nuevo y pasa la comprobación de paridad.

**Files to create:**
- `lang/en/admin/comments.php`
- `lang/es/admin/comments.php`

**Files to modify:**
- Ninguno

**writeScope:** `lang/en/admin/comments.php`, `lang/es/admin/comments.php`

**validateCommand:** `rtk php -l lang/en/admin/comments.php && rtk php -l lang/es/admin/comments.php && rtk php artisan test --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php`

### TASK-021: Traducir la identidad y el detalle del recurso Comments

Localizar las etiquetas singular/plural que usa el título de la página y los textos fijos de `CommentInfolist`. Ampliar la prueba del detalle para español e inglés sin modificar el contenido guardado.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-020

**Acceptance Criteria:**
1. El título de detalle y las secciones, descripciones y campos de Comment aparecen en el locale activo.
2. El mensaje y el nombre del autor permanecen como datos originales; la fecha representa el mismo instante y se formatea según el locale.
3. La prueba conserva el acceso autorizado, el rechazo sin `admin.posts.view`, la navegación oculta y el comportamiento de solo lectura.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Comments/CommentResource.php`
- `app/Modules/Admin/Filament/Resources/Comments/Schemas/CommentInfolist.php`
- `app/Modules/Admin/Tests/Filament/Resources/Comments/Pages/ViewCommentTest.php`

**writeScope:** `app/Modules/Admin/Filament/Resources/Comments/CommentResource.php`, `app/Modules/Admin/Filament/Resources/Comments/Schemas/CommentInfolist.php`, `app/Modules/Admin/Tests/Filament/Resources/Comments/Pages/ViewCommentTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Comments/Pages/ViewCommentTest.php`

### TASK-022: Traducir la tabla y el modal de respuestas

Localizar columnas, filtros, acción, títulos y descripciones del modal y su botón de cierre en `RepliesRelationManager`. Añadir casos pequeños para ambos idiomas y mantener la relación de respuestas acotada al comentario seleccionado.

**Type:** feature · **Priority:** P1 · **Effort:** M · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-020

**Acceptance Criteria:**
1. Columnas, filtros de fecha/autor y textos de la acción/modal aparecen en el locale activo.
2. El filtrado, la paginación y la consulta siguen mostrando solo respuestas del comentario seleccionado, incluso si se presenta el ID de una respuesta ajena.
3. El modal permanece de solo lectura y los campos opcionales de edición o mención siguen ocultos cuando sus datos faltan o el usuario mencionado fue eliminado.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Comments/RelationManagers/RepliesRelationManager.php`
- `app/Modules/Admin/Tests/Filament/Resources/Comments/RelationManagers/RepliesRelationManagerTest.php`

**writeScope:** `app/Modules/Admin/Filament/Resources/Comments/RelationManagers/RepliesRelationManager.php`, `app/Modules/Admin/Tests/Filament/Resources/Comments/RelationManagers/RepliesRelationManagerTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Comments/RelationManagers/RepliesRelationManagerTest.php`

### TASK-023: Traducir las métricas del detalle de Comments

Localizar las etiquetas y descripciones de replies y likes en `CommentActivityOverview`. Ampliar sus pruebas por locale manteniendo conteos, autorización y el caso de comentario no disponible.

**Type:** feature · **Priority:** P1 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-020

**Acceptance Criteria:**
1. Las etiquetas y descripciones de replies y likes cambian según el locale activo.
2. Los conteos siguen acotados al comentario seleccionado y muestran cero cuando no hay actividad.
3. Un administrador sin permiso de lectura no puede ver el widget ni sus métricas.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Filament/Resources/Comments/Widgets/CommentActivityOverview.php`
- `app/Modules/Admin/Tests/Filament/Resources/Comments/Widgets/CommentActivityOverviewTest.php`

**writeScope:** `app/Modules/Admin/Filament/Resources/Comments/Widgets/CommentActivityOverview.php`, `app/Modules/Admin/Tests/Filament/Resources/Comments/Widgets/CommentActivityOverviewTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Comments/Widgets/CommentActivityOverviewTest.php`

### TASK-024: Incluir Comments en el detector de textos fijos

Extender `AdminHardcodedTextTest` para escanear el recurso independiente de Comments y reemplazar la expectativa que actualmente exige excluirlo. Mantener fuera del detector el contenido authored que llega desde modelos.

**Type:** test · **Priority:** P2 · **Effort:** S · **Agent:** laravel-senior-engineer · **Review:** codex

**Depends on:** TASK-021, TASK-022, TASK-023

**Acceptance Criteria:**
1. `AdminHardcodedTextTest` incluye `Resources/Comments` y falla ante un literal de interfaz pasado a métodos Filament o a componentes de texto.
2. El detector pasa para las superficies traducidas y conserva pruebas que distinguen copy de interfaz del contenido de comentarios/respuestas.
3. `AdminTranslationKeysTest` sigue verificando automáticamente paridad y placeholders del catálogo nuevo.

**Files to create:**
- Ninguno

**Files to modify:**
- `app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php`

**writeScope:** `app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Translations/AdminTranslationKeysTest.php app/Modules/Admin/Tests/Translations/AdminHardcodedTextTest.php`

### TASK-025: Sincronizar la documentación del alcance Comments

Actualizar la documentación existente para reflejar que SOC-66 también traduce el recurso independiente de Comments, aclarando que permanece oculto de navegación, no tiene listado y conserva el contenido authored en su idioma original.

**Type:** docs · **Priority:** P2 · **Effort:** S · **Agent:** general-purpose · **Review:** codex

**Depends on:** TASK-024

**Acceptance Criteria:**
1. `AGENTS.md`, `CLAUDE.md` y `docs/architecture/backend.md` describen de forma consistente la cobertura de Comments independiente.
2. La documentación mantiene explícitos el acceso de solo lectura, la autorización existente y la exclusión del contenido authored.
3. No queda una afirmación de que el recurso independiente de Comments permanece fuera del alcance de SOC-66.

**Files to create:**
- Ninguno

**Files to modify:**
- `AGENTS.md`
- `CLAUDE.md`
- `docs/architecture/backend.md`

**writeScope:** `AGENTS.md`, `CLAUDE.md`, `docs/architecture/backend.md`

**validateCommand:** `rtk git diff --check -- AGENTS.md CLAUDE.md docs/architecture/backend.md`

## Failure Modes

- Un catálogo deja fuera una clave o marcador: `AdminTranslationKeysTest` debe fallar por paridad/placeholders antes de continuar con la UI.
- Se traduce el mensaje del comentario o una respuesta como si fuera copy fijo: las pruebas de detalle/modal exigen que el contenido authored permanezca igual.
- La traducción altera el alcance de replies, filtros o accesos por ID: `RepliesRelationManagerTest` debe conservar el caso que rechaza una reply perteneciente a otro comentario.
- El detector sigue excluyendo Comments o empieza a inspeccionar texto authored: TASK-024 exige que escanee los archivos de interfaz y mantenga fuera los valores dinámicos.

## Ship Cut

El catálogo se establece primero. Después, el detalle, el gestor de respuestas y las métricas pueden avanzar como slices independientes. SOC-66 queda completamente traducida para Comments independiente cuando los tres slices, el detector de literales y la sincronización de documentación pasan.

## Test Coverage Map

- **Catálogo Comments: paridad, valores y placeholders:** TASK-020, TASK-024
- **Identidad y detalle de Comment: idiomas, contenido original, permisos y modo de solo lectura:** TASK-021
- **Replies: copy localizado, alcance de registros, filtros, paginación y modal opcional:** TASK-022
- **Widget: etiquetas, conteos cero/positivos y autorización:** TASK-023
- **Detector de textos fijos y documentación de SOC-66:** TASK-024, TASK-025

## Execution Summary

- **Task count:** 6
- **Layers:** [TASK-020] → [TASK-021, TASK-022, TASK-023] → [TASK-024] → [TASK-025]
- **Critical path:** TASK-020 → TASK-021 → TASK-024 → TASK-025
- Después del catálogo, detalle, replies y widget son tres slices independientes. El guardrail se amplía tras integrarlos y la documentación se actualiza al final.

## Task Dependencies

```json
{
  "TASK-020": [],
  "TASK-021": [
    "TASK-020"
  ],
  "TASK-022": [
    "TASK-020"
  ],
  "TASK-023": [
    "TASK-020"
  ],
  "TASK-024": [
    "TASK-021",
    "TASK-022",
    "TASK-023"
  ],
  "TASK-025": [
    "TASK-024"
  ]
}
```

