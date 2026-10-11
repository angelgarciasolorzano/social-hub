# Plan: SOC-47: Ampliar administración Filament de usuarios e indicadores del dashboard

## Overview

Implementar el alcance completo de SOC-47: filtros y fallback localizado en la tabla de usuarios, métricas y relaciones de solo lectura en el detalle de User, y un gráfico de nuevas cuentas más indicadores en el dashboard.

**Linear:** [SOC-47](https://linear.app/social-hub-ang/issue/SOC-47/feature-expand-filament-user-administration-and-dashboard-insights) · Backlog · Medium · asignada a Angel Garcia

**Modo:** HOLD · **Revisión predeterminada:** Codex

## Scope Challenge

HOLD confirmado por el usuario: ejecutar el alcance completo de SOC-47, explicitar el cumplimiento de la arquitectura Filament ya establecida y conservar los no-objetivos de SOC-47. No se añaden gestión de roles, plugins ni cambios de estado de cuenta.

## Prerequisites

- SOC-44 está completada; AdminPanelProvider, UserResource, UserPolicy y las suites Admin existen.
- El panel Filament y el catálogo Spatie usan el guard `admin`; `web` permanece como guard predeterminado de la aplicación. AGENTS.md, CLAUDE.md y docs/architecture/backend.md ya reflejan este contrato.
- Las suites Pest de Admin están registradas en phpunit.xml y tests/Pest.php.
- No se requiere paquete ni plugin adicional; el widget puede usar la configuración de descubrimiento existente.

## Non-Goals

- Suspender o reactivar cuentas (SOC-35).
- Administrar, renovar o revocar dispositivos confiables desde el panel.
- Crear, editar o eliminar usuarios, publicaciones o comentarios.
- Crear una interfaz de Filament para administrar roles o permisos.
- Añadir plugins, páginas React/Inertia o rutas de panel duplicadas.

## Contracts

- Obligatorio seguir `.ai/rules/backend.md`: los recursos Filament permanecen dentro de `app/Modules/Admin/Filament/Resources/<PluralModel>/`; sus Pages, Tables, Schemas y RelationManagers se organizan bajo el recurso dueño. Los widgets de dashboard van en `app/Modules/Admin/Filament/Widgets/`.
- Usar componentes y flujos nativos de Filament 5.10.1. Para el detalle de User, usar relation managers paginados y ViewAction de lectura; no añadir un recurso CRUD de Post separado.
- Los datos relacionados solo se muestran bajo el detalle autorizado de User y el permiso existente `users.view`; no se amplía el catálogo de permisos ni se confía en ocultar botones como autorización.
- Las vistas de TrustedDevice usan allowlist de campos y el estado se deriva como revocado, expirado o activo; quedan excluidos token_hash, IP, user_agent crudo y credenciales.
- El chart valida el filtro contra valores permitidos antes de consultar y agrupa por granularidad apropiada al rango. El resumen usa agregados; los listados cargan relaciones necesarias sin N+1.
- AdminPanelProvider ya descubre recursos y widgets desde el módulo. No agregar rutas manuales duplicadas ni cambiar guard/provider salvo que una prueba descubra un defecto.
- Crear archivos Filament con generadores de Artisan específicos tras revisar `--help`, pasando `--no-interaction`, y preservar la jerarquía generada conforme a `.ai/rules/backend.md`.
- Después de cada tarea, entregar un breve resumen y detenerse para revisión antes de implementar la siguiente; la revisión predeterminada del plan es Codex.

## Existing Code Leverage

- `app/Modules/Admin/Providers/AdminPanelProvider.php` — Admin panel, resource/widget discovery, auth guard and middleware.
- `app/Modules/Admin/Filament/Resources/Users/UserResource.php` — Existing read-only resource and owner for related-record managers.
- `app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php` — Current columns, name/email search, and sorting.
- `app/Modules/Admin/Filament/Resources/Users/Pages/ViewUser.php` — Current user detail page; attach the compact activity widget here.
- `app/User/Models/User.php` — Typed posts(), comments(), and trustedDevices() relationships.
- `app/Auth/Models/TrustedDevice.php` — Device fields, expires_at, deleted_at soft-delete state, and isActive().
- `app/Modules/Post/Models/Post.php` — Post ownership and comments relationship.
- `app/Modules/Comment/Models/Comment.php` — Comment author, polymorphic parent, and child-comment/reply relationship.
- `app/User/Policies/UserPolicy.php` — Existing users.viewAny/users.view authorization and denied write abilities.
- `app/Modules/Admin/Tests/Filament/UserResourceTest.php` — Existing Livewire tests for search, allowlisted detail data, permissions, and denied writes.
- `resources/lang/en/messages.php` — Existing English application message catalog.
- `resources/lang/es/messages.php` — Existing Spanish application message catalog.
- `phpunit.xml` — Admin suite already registered.
- `tests/Pest.php` — Admin module tests already receive project Pest setup.

## External References

- Filament 5 resource directory and viewing-record patterns: https://filamentphp.com/docs/5.x/resources/overview and https://filamentphp.com/docs/5.x/resources/viewing-records
- Filament 5 relationship managers and read-only mode: https://filamentphp.com/docs/5.x/resources/managing-relationships
- Filament 5 chart filters and empty state: https://filamentphp.com/docs/5.x/widgets/charts

## Tasks

### TASK-001: Add the localized last-login fallback

Show a localized “Never” / “Nunca” placeholder when last_login_at is null. Keep the user table within its current resource hierarchy and use the existing English and Spanish message catalogs.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**
- A user with a null last_login_at sees the fallback in the active application locale; a real timestamp remains formatted as a date.
- The English and Spanish catalogs resolve the fallback to “Never” and “Nunca” respectively without changing the application's locale policy.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php`
- `resources/lang/en/messages.php`
- `resources/lang/es/messages.php`

**validateCommand:** `rtk php -l app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php && rtk php -l resources/lang/en/messages.php && rtk php -l resources/lang/es/messages.php && rtk git diff --check -- app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php resources/lang/en/messages.php resources/lang/es/messages.php`

**Review:** Codex

### TASK-002: Add and test user-table filters

Extend the existing Filament UsersTable with email-verification, registration-date, and has/does-not-have-posts filters. Preserve name/email search, useful sorting, and Filament pagination. Verify filter combinations and the localized fallback through the existing Livewire resource test.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001

**Acceptance Criteria:**
- The verification, registration-date, and posts-presence filters return the expected users when used alone and in combination, including an empty-result edge case.
- Name/email search, sorting, and pagination continue to work with filters applied.
- The feature test verifies both locale fallbacks and confirms that the user resource remains read-only.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php`
- `app/Modules/Admin/Tests/Filament/UserResourceTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/UserResourceTest.php`

**Review:** Codex

### TASK-003: Show compact user activity metrics

Add a page-attached native Filament stats widget to the User detail page. Display post count, authored comment count including replies, and active trusted-device count; keep the widget under the Users resource hierarchy.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**
- The User detail page shows correct post, comment/reply, and active-device counts for the selected user.
- A user with no related records sees zero-valued metrics, and the widget does not render device tokens, raw user agents, IP addresses, or authentication secrets.
- A user without users.view cannot access the detail metrics, and the page exposes no mutation action.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Users/Pages/ViewUser.php`
- `app/Modules/Admin/Filament/Resources/Users/Widgets/UserActivityOverview.php`
- `app/Modules/Admin/Tests/Filament/UserActivityOverviewTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/UserActivityOverviewTest.php`

**Review:** Codex

### TASK-004: Add a read-only trusted-devices relation manager

Create a paginated TrustedDevice relation manager for the User detail and register it on UserResource. Include soft-deleted devices so revoked devices can be represented; display only the approved device fields and derive revoked/expired/active status without reusing the broader API resource.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**
- The relation is paginated and scoped to the selected user; its allowlist contains name, browser/OS summary, last-used time, expiration, and status.
- Status precedence is revoked when deleted_at is present, expired when expires_at has passed, and active otherwise, including boundary cases.
- Sentinel token_hash, raw user_agent, and IP values never appear in rendered/serialized output; unauthorized users cannot view the relation and no create/edit/delete/revoke action is available.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Users/UserResource.php`
- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/TrustedDevicesRelationManager.php`
- `app/Modules/Admin/Tests/Filament/TrustedDevicesRelationManagerTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/TrustedDevicesRelationManagerTest.php`

**Review:** Codex

### TASK-005: Add read-only posts and comment context to User detail

Create a paginated Posts relation manager under the Users resource and register it on UserResource. Use Filament's native read-only ViewAction to show a post with its comments and replies in context; eager-load the relationships required by the view.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**
- The User detail lists only that user's posts with Filament pagination; opening a post shows its allowlisted detail and its comments/replies with author/context.
- The post detail query eagerly loads the required user/comment/reply relations so query count does not grow per row or reply.
- The view is read-only, direct access remains constrained by the authorized User record, and no create/edit/delete action is registered.

**writeScope:**
- `app/Modules/Admin/Filament/Resources/Users/UserResource.php`
- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/PostsRelationManager.php`
- `app/Modules/Admin/Tests/Filament/PostsRelationManagerTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/PostsRelationManagerTest.php`

**Review:** Codex

### TASK-006: Add registration chart and dashboard summary widgets

Add native Filament dashboard widgets under the already discovered Admin/Filament/Widgets directory. The chart offers 7-day, 30-day, 90-day, and 12-month ranges; use daily buckets for 7/30 days, weekly for 90 days, and monthly for 12 months. Add one or two useful summary metrics and an empty state.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** Ninguna

**Acceptance Criteria:**
- Each supported range includes the correct date boundaries and grouping granularity; an unknown crafted filter value safely falls back instead of being interpolated into a query.
- The no-registration case renders Filament's empty state, and the dashboard summary shows accurate total/verified account metrics.
- The widgets are available through existing AdminPanelProvider widget discovery, use aggregate queries without N+1 loading, and add no plugin or duplicate route.

**writeScope:**
- `app/Modules/Admin/Filament/Widgets/RegistrationsChart.php`
- `app/Modules/Admin/Filament/Widgets/RegistrationSummaryWidget.php`
- `app/Modules/Admin/Tests/Filament/RegistrationDashboardTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/RegistrationDashboardTest.php`

**Review:** Codex

### TASK-007: Document the expanded Admin read surfaces

Update the backend architecture reference after the feature slices are integrated. Record the User relation-manager layout, allowlisted device data, read-only post/comment context, and dashboard widget discovery without duplicating the existing Filament hierarchy rule.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P2

**Depends on:** TASK-002, TASK-003, TASK-005, TASK-006

**Acceptance Criteria:**
- The architecture reference describes only paths and behaviors present in the integrated code, including the existing Admin resource/widget discovery points.
- Documentation preserves the `admin` authorization guard contract and points to `.ai/rules/backend.md` for the per-resource Filament directory convention.

**writeScope:**
- `docs/architecture/backend.md`

**validateCommand:** `rtk git diff --check -- docs/architecture/backend.md`

**Review:** Codex

### TASK-008: Run integrated backend gates

After all implementation and documentation slices are reviewed and integrated, run the affected Filament tests, the complete Pest suite, and the required backend gates. Keep Laravel Doctor as the final local command.

**Type:** test · **Effort:** L · **Agent:** general-purpose · **Priority:** P1

**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007

**Acceptance Criteria:**
- All Admin Filament feature tests and the complete Pest suite pass.
- Pint, PHPStan, Rector dry-run, Rector apply, and Composer Doctor pass in the required order, with Doctor run last.

**writeScope:**

**validateCommand:** `rtk git diff --check && rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk composer rector-dry && rtk composer rector && rtk php artisan test --compact app/Modules/Admin/Tests/Filament && rtk php artisan test --compact && rtk composer doctor`

**Review:** Codex

## Failure Modes

- Un filtro combinado puede excluir un usuario que sí cumple sus criterios individuales; las pruebas verifican combinaciones y conjunto vacío.
- La relación de dispositivos podría serializar token_hash, IP o user_agent sin procesar; los tests usan valores centinela y exigen que estén ausentes.
- Un dispositivo con soft delete podría aparecer como activo; el estado da precedencia a “revocado”.
- Una ruta o componente Livewire podría revelar una relación sin permiso de lectura o permitir una mutación por request directo; las pruebas ejercitan autorización del servidor.
- La vista de publicaciones podría consultar usuarios/comentarios por fila; las pruebas de relación controlan carga anticipada y consultas.
- Un filtro de chart manipulado podría alterar la consulta o producir agrupaciones incorrectas; solo se acepta la lista de rangos permitidos.
- Un rango sin registros podría dejar el chart en error o sin estado vacío; la prueba cubre ese caso.

## Ship Cut

El corte liberable es después de TASK-008, con todos los slices integrados, tests Filament y Pest completos, y gates backend verdes. Las reglas del proyecto requieren implementar una tarea atómica por vez y detenerse para revisión después de cada tarea. Este plan no cambia el estado de Linear.

## Test Coverage Map

- **TASK-001:** `rtk php -l app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php && rtk php -l resources/lang/en/messages.php && rtk php -l resources/lang/es/messages.php && rtk git diff --check -- app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php resources/lang/en/messages.php resources/lang/es/messages.php`
- **TASK-002:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/UserResourceTest.php`
- **TASK-003:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/UserActivityOverviewTest.php`
- **TASK-004:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/TrustedDevicesRelationManagerTest.php`
- **TASK-005:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/PostsRelationManagerTest.php`
- **TASK-006:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/RegistrationDashboardTest.php`
- **TASK-007:** `rtk git diff --check -- docs/architecture/backend.md`
- **TASK-008:** `rtk git diff --check && rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk composer rector-dry && rtk composer rector && rtk php artisan test --compact app/Modules/Admin/Tests/Filament && rtk php artisan test --compact && rtk composer doctor`

## Execution Summary

- **Tareas:** 8
- **Fases lógicas:** 5
- **Caminos críticos:** TASK-001 → TASK-002 → TASK-007 → TASK-008; TASK-004 → TASK-005 → TASK-007 → TASK-008
- **Revisión predeterminada:** Codex
- **Salida:** SOC-47 se considera listo únicamente después de integrar todos los slices, ejecutar las pruebas/gates finales y completar el resumen de documentación. No se cambia el estado de Linear dentro de este plan.

## Task Dependencies

- **TASK-001** ← ninguna
- **TASK-002** ← TASK-001
- **TASK-003** ← ninguna
- **TASK-004** ← ninguna
- **TASK-005** ← TASK-004
- **TASK-006** ← ninguna
- **TASK-007** ← TASK-002, TASK-003, TASK-005, TASK-006
- **TASK-008** ← TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007

