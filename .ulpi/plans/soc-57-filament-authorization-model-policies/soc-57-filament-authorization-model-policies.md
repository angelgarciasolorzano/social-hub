# Plan: SOC-57: Mover la autorización de los recursos de Filament a las policies de los modelos

## Overview

Que PostResource y CommentResource sigan el patrón de UserResource/ActivityLogResource: la regla vive en la policy del modelo y relation managers y widgets la consultan con Gate, sin cambios de comportamiento.

**Linear:** [SOC-57](https://linear.app/social-hub-ang/issue/SOC-57/refactor-move-filament-resource-authorization-into-model-policies) · **Modo:** HOLD · **Revisión predeterminada:** Claude

## Scope Challenge

HOLD con revisión de Claude. Decisiones del usuario: CommentResource conserva los overrides de mutación en false (opción 1 de la tarea) y se incluye UserActivityOverview, que la tarea no listaba. Hallazgo: Filament permite cualquier habilidad cuyo método no exista en la policy (`get_authorization_response`), así que se eligió cubrir todo: PostPolicy niega también restoreAny/forceDeleteAny y CommentResource agrega canRestore(Any)/canForceDelete(Any) en false.

## Prerequisites

- `UserPolicy` y `ActivityLogPolicy` asociadas con `#[UsePolicy]` son el patrón.
- Las pruebas de Filament de Posts, Comments y Users existen en `app/Modules/Admin/Tests/Filament/Resources/`.

## Non-Goals

- Permisos propios `admin.comments.*`.
- Cambiar qué ve cada rol.
- Mover la autorización de la acción de invitar, de los widgets del dashboard o de Backups.

## Contracts

- Los permisos efectivos de cada pieza del panel no cambian.
- `CommentPolicy::update`/`delete` no cambian.
- Desde el panel no se puede crear, editar, borrar, restaurar ni forzar el borrado de publicaciones o comentarios.

## Tasks

### TASK-001: Crear PostPolicy y asociarla al modelo Post

Crear `App\Modules\Post\Policies\PostPolicy` (final, patrón de `UserPolicy`/`ActivityLogPolicy`): `viewAny` con `admin.posts.viewAny`; `view(User, ?Post = null)` con `admin.posts.view` y, cuando recibe una publicación, exige que tenga autor (la regla actual de `PostResource::canView()`); el `null` permite que los widgets estáticos consulten `Gate::allows('view', Post::class)`. `create`, `update`, `delete`, `deleteAny`, `restore`, `restoreAny`, `forceDelete` y `forceDeleteAny` devuelven `false` (Filament permite cualquier habilidad cuyo método no exista en la policy). Asociarla con `#[UsePolicy]` en `Post`. Verificado: ningún flujo de la aplicación consulta la policy de `Post` (`PostRequest::authorize()` solo exige usuario).

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
- `Gate::getPolicyFor($post)` devuelve `PostPolicy`.
- `viewAny`/`view` solo se permiten con su permiso respectivo; `view` se niega para una publicación sin autor aun con el permiso.
- Todas las habilidades de mutación se niegan incluso a un administrador con `admin-posts-reader` y al autor de la publicación.

**writeScope:**
- `app/Modules/Post/Policies/PostPolicy.php`
- `app/Modules/Post/Tests/Policies/PostPolicyTest.php`
- `app/Modules/Post/Models/Post.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Post/Tests/Policies/PostPolicyTest.php`

**Review:** Claude

### TASK-002: Agregar viewAny y view del panel a CommentPolicy

Agregar a `CommentPolicy` `viewAny(User)` y `view(User)` con `admin.posts.view` (no hay permisos propios de comentarios), sin tocar `update`/`delete` que usa `CommentController`. No se agregan habilidades de mutación del panel a la policy: son reglas de la aplicación, y el panel se mantiene en solo lectura desde `CommentResource` (TASK-004). Ampliar `CommentPolicyTest`.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**
- `viewAny` y `view` se permiten con `admin.posts.view` y se niegan sin él (incluido `admin.posts.viewAny` solo).
- Las pruebas existentes de `update`/`delete` del autor siguen pasando sin cambios.

**writeScope:**
- `app/Modules/Comment/Policies/CommentPolicy.php`
- `app/Modules/Comment/Tests/Policies/CommentPolicyTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Comment/Tests/Policies/CommentPolicyTest.php`

**Review:** Claude

### TASK-003: Quitar los overrides de autorización de PostResource

Eliminar `canViewAny`, `canView`, `canCreate`, `canEdit`, `canDelete` y `canDeleteAny` de `PostResource` (y los imports que queden sin uso); Filament consulta `PostPolicy`.

**Type:** refactor · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001

**Acceptance Criteria:**
- `PostResource` no sobrescribe ningún `can*()`.
- Las pruebas de listado y detalle de Posts pasan sin cambios, incluida la denegación sin permiso.
- `ViewPostTest` prueba que ningún `can*()` de mutación de `PostResource` se permite, ni siquiera al lector que escribió la publicación.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Posts/PostResource.php`
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ViewPostTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/Pages`

**Review:** Claude

### TASK-004: Dejar CommentResource solo con los overrides de solo lectura

Eliminar `canViewAny` y `canView` de `CommentResource` (pasan a `CommentPolicy`). Conservar `canCreate`, `canEdit`, `canDelete` y `canDeleteAny` en `false` y agregar `canRestore`, `canRestoreAny`, `canForceDelete` y `canForceDeleteAny` en `false` (el modelo usa `SoftDeletes` y la policy no define esas habilidades), con un docblock que explique que `CommentPolicy` permite al autor editar y borrar en la aplicación y el panel debe seguir en solo lectura. Agregar a `ViewCommentTest` la prueba de que un administrador autor del comentario no puede editarlo ni borrarlo desde el panel.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-002

**Acceptance Criteria:**
- Ningún administrador, ni siquiera el autor, obtiene `true` en ningún `can*()` de mutación de `CommentResource`.
- La vista de comentario sigue permitida con `admin.posts.view` y denegada sin él.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Comments/CommentResource.php`
- `app/Modules/Admin/Tests/Filament/Resources/Comments/Pages/ViewCommentTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Comments/Pages/ViewCommentTest.php`

**Review:** Claude

### TASK-005: Usar la policy en el relation manager y los widgets de Posts

`CommentsRelationManager::canViewForRecord` usa `Gate::forUser($user)->allows('viewAny', Comment::class)`; `PostsOverview::canView` usa `allows('viewAny', Post::class)`; `PostActivityOverview::canView` usa `allows('view', Post::class)`. Ninguno importa `AdminPermission`.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001, TASK-002

**Acceptance Criteria:**
- Los tres conservan los mismos permisos efectivos (las pruebas existentes pasan sin cambios).
- Un usuario sin permisos de posts no ve el relation manager ni los widgets.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Posts/RelationManagers/CommentsRelationManager.php`
- `app/Modules/Admin/Filament/Resources/Posts/Widgets/PostsOverview.php`
- `app/Modules/Admin/Filament/Resources/Posts/Widgets/PostActivityOverview.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts app/Modules/Admin/Tests/Filament/Pages`

**Review:** Claude

### TASK-006: Usar la policy en el relation manager y el widget de Comments

`RepliesRelationManager::canViewForRecord` usa `allows('viewAny', Comment::class)` y `CommentActivityOverview::canView` usa `allows('view', Comment::class)`.

**Type:** refactor · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-002

**Acceptance Criteria:**
- Mismos permisos efectivos: las pruebas existentes pasan sin cambios.
- Sin `admin.posts.view` no se muestran ni las respuestas ni el widget.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Comments/RelationManagers/RepliesRelationManager.php`
- `app/Modules/Admin/Filament/Resources/Comments/Widgets/CommentActivityOverview.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Comments`

**Review:** Claude

### TASK-007: Usar la policy en el relation manager de posts y el widget de Users

`Users/RelationManagers/PostsRelationManager::canViewForRecord` reemplaza `->can(AdminPostsViewAny)` por `allows('viewAny', Post::class)`; `UserActivityOverview::canView` usa `allows('view', User::class)` (`UserPolicy::view` no recibe modelo).

**Type:** refactor · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001

**Acceptance Criteria:**
- Mismos permisos efectivos: las pruebas existentes pasan sin cambios.
- Sin `admin.posts.viewAny` la pestaña de posts del usuario no se muestra aunque se pueda ver el usuario.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/PostsRelationManager.php`
- `app/Modules/Admin/Filament/Resources/Users/Widgets/UserActivityOverview.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Users`

**Review:** Claude

### TASK-008: Documentar el patrón de autorización en la policy del modelo

Actualizar CLAUDE.md (sección Admin panel y modelos Post/Comment) y `docs/architecture/backend.md`: los recursos de Filament no sobrescriben `can*()`, la regla vive en la policy del modelo y relation managers/widgets la consultan con `Gate`; `PostPolicy`; las habilidades de panel de `CommentPolicy` y por qué `CommentResource` conserva los overrides de mutación en `false`; la trampa de Filament (un método ausente en la policy se permite).

**Type:** docs · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P3

**Depends on:** TASK-003, TASK-004, TASK-005, TASK-006, TASK-007

**Acceptance Criteria:**
- La documentación nombra `PostPolicy`, las habilidades nuevas de `CommentPolicy` y los overrides que quedan en `CommentResource`.
- No queda texto que diga que Posts/Comments autorizan con overrides en el recurso.

**writeScope:**
- `CLAUDE.md`
- `docs/architecture/backend.md`

**validateCommand:** `rtk grep -n 'PostPolicy' CLAUDE.md docs/architecture/backend.md`

**Review:** Claude

### TASK-009: Pasar los gates de calidad y la suite completa

Ejecutar `composer rector-clear-cache`, `composer rector-dry`, `composer rector`, `vendor/bin/pint --dirty --format agent`, `composer phpstan` y `composer test`; corregir lo que surja dentro de los archivos del plan.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-008

**Acceptance Criteria:**
- Rector, Pint y PHPStan terminan sin cambios ni errores pendientes.
- `composer test` pasa completo, incluidas las pruebas de `CommentController` (update/destroy del autor).

**writeScope:**
- (sin archivos propios)

**validateCommand:** `rtk composer test`

**Review:** Claude

## Execution Layers

1. TASK-001, TASK-002
2. TASK-003, TASK-004, TASK-005, TASK-006, TASK-007
3. TASK-008
4. TASK-009

## Ship Cut

TASK-001..004 dejan los recursos en la policy; TASK-005..007 son independientes entre sí.
