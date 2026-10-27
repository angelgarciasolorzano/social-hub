# Plan: SOC-60 Filament Admin Insights and Read-Only View Improvements

**Issue:** [SOC-60](https://linear.app/social-hub-ang/issue/SOC-60/feature-expand-filament-dashboard-insights-and-improve-admin-views)  
**Mode:** EXPANSION  
**Default review:** codex  
**Scope cuts:** None

## Overview

Este plan convierte SOC-60 en tareas de implementación independientes y paralelizables. Conserva los cinco frentes del issue y agrega una reconciliación puntual del catálogo RBAC en la documentación backend, que está desactualizada frente al código actual. No se implementa ningún cambio como parte de este plan.

## Scope Challenge

SOC-60 reúne dashboard, activity log, backups, detalle/listado de usuarios, consulta de respuestas y rendimiento de dos tablas con MediaLibrary. El código ya contiene páginas, policies, servicios y pruebas para todos esos flujos. El dashboard auto-descubre widgets; el detalle de backups ya cachea su listado durante la instancia de página; las dos tablas de publicaciones resuelven MediaLibrary por registro y no eager-load media.

La expansión elegida se limita a TASK-011: completar en `docs/architecture/backend.md` la lista de roles que ya existen en `AdminRole` y `AdminAuthorizationSeeder`. No se amplían permisos ni acciones.

## Prerequisites

- Filament 5.10.1 and the existing Admin module structure remain the implementation baseline.
- No schema change or new dependency is required; use existing User, AccountStatusChange, ActivityLog, BackupListingData, and MediaLibrary models/services.
- For TASK-007 and TASK-010, Debugbar storage must be enabled and an authorized local Admin session must be able to render both post-image tables with representative media-backed rows.

## Non-Goals

- Do not add CRUD or moderation actions for users, posts, comments, replies, or backups; preserve only the existing account lifecycle and backup operations.
- Do not add roles, permissions, migrations, or third-party packages.
- Do not replace or redesign the existing registration summary and registration chart.
- Do not add a general-purpose analytics framework or an absolute response-time performance gate.

## Contracts

- AdminPanelProvider discovers widgets from app/Modules/Admin/Filament/Widgets; preserve the registration widgets and the app's existing per-resource hierarchy.
- Dashboard user metrics require admin.users.viewAny, activity metrics require admin.activity.viewAny, and backup metrics require admin.backups.view. Filter each card and its link independently before querying its source.
- Activity metrics use a fixed seven-day window and event labels from ActivityLogEventCatalog; never expose activity properties or reconstructed content.
- The backup page widget receives the same cached listing from BackupManagement::getBackupListing() through Filament Page::getWidgetData(); dashboard backup summaries cache aggregate-only data for five minutes after authorization.
- User detail continues to rely on UserPolicy. Its lifecycle addition is a 30-day status-change count so it does not repeat the current status infolist or disclose reasons.
- The user avatar uses UserImageType::PROFILE_PICTURE, User::DEFAULT_PROFILE_PICTURE_PATH, meaningful alt text, and eager-loaded media.
- The reply action is read-only and resolves edited_at and repliedToUser as optional context while keeping the reply scoped to its selected parent.
- Posts media optimization preserves the media collection, supported page sizes, fallback, alt text, selected relation keys, and the shared Post detail route.

## Existing Code Leverage

- app/Modules/Admin/Providers/AdminPanelProvider.php already discovers Admin widgets automatically.
- app/Modules/Admin/Filament/Widgets/RegistrationSummaryWidget.php and RegistrationsChart.php provide the dashboard baseline.
- app/Modules/Admin/Services/BackupManagementService.php returns BackupListingData and enforces backup permissions; BackupManagement caches its listing per page instance.
- ActivityLogResource, ActivityLogsTable, UserActivityOverview, AccountStatusChangesRelationManager, UsersTable, RepliesRelationManager, PostsTable, and PostsRelationManager are existing extension points.
- Relevant focused tests already exist under app/Modules/Admin/Tests/Filament/ for dashboard, activity, backups, users, posts, and replies.

## Tasks

### TASK-001: Add permission-aware operational metrics to the Admin dashboard

Create one compact operational widget discovered from the existing Admin widget directory. Include account status and recent status changes, recent activity, and backup health. Keep each metric and its destination link behind the permission for its source. Cache only the dashboard backup summary for five minutes; do not cache or expose backup filenames.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** codex

**Acceptance Criteria:**
- The existing registration summary and chart remain available; the new widget adds at most three compact cards: active/suspended accounts with seven-day status changes, seven-day activity count, and latest backup age with available/total count.
- The account card/link requires admin.users.viewAny, activity requires admin.activity.viewAny, and backups requires admin.backups.view; without a source permission its query/service call and rendered values/link are absent.
- Zero activity or no backups renders explicit zero/unavailable states, and a failure in one source does not prevent the other permitted cards or dashboard from rendering.

**Write scope:**
- `app/Modules/Admin/Filament/Widgets/OperationalSummaryWidget.php` — Render per-source permission-gated operational metrics and five-minute backup summary cache.
- `app/Modules/Admin/Tests/Filament/RegistrationDashboardTest.php` — Cover the preserved registration widgets, metric values, permission combinations, and missing-source states.

**Validate command:**
```bash
rtk php artisan test --compact app/Modules/Admin/Tests/Filament/RegistrationDashboardTest.php
```

### TASK-002: Add an aggregate summary to the activity log page

Add a small read-only stats widget to the activity log listing. Use a fixed seven-day window and the existing event catalog for the distribution; aggregate at the database instead of loading event properties or content.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** codex

**Acceptance Criteria:**
- The listing shows the seven-day event total and an event-type distribution using the existing readable event labels.
- The widget is available only on the already-authorized activity page and does not render activity properties, comment text, or post content.
- An empty log renders zero counts and an empty state without errors; existing table filters, pagination, and read-only behavior remain.

**Write scope:**
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Widgets/ActivityLogSummaryWidget.php` — Compute and render aggregate event statistics.
- `app/Modules/Admin/Filament/Resources/ActivityLogs/Pages/ListActivityLogs.php` — Attach the summary widget to the existing list page.
- `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php` — Verify counts, labels, empty data, and preservation of the authorized read-only page.

**Validate command:**
```bash
rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php
```

### TASK-003: Add a summary widget to backup management

Add a read-only backup summary using the listing already loaded by BackupManagement. Pass the page's cached listing through Filament's getWidgetData() so rendering the widget does not repeat the remote storage scan.

**Type:** feature  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** codex

**Acceptance Criteria:**
- The page summary shows available and missing counts, the latest available backup time/age, and total size when every size is available.
- The widget consumes the page's cached BackupListingData through getWidgetData(); the table, infolist, and widget share one listing per Livewire page instance.
- An empty or unhealthy destination renders an explicit state, and a user without admin.backups.view cannot open the page or see summary data; create/download actions keep their current permissions.

**Write scope:**
- `app/Modules/Admin/Filament/Pages/Backups/Widgets/BackupSummaryWidget.php` — Render summary cards from page-provided backup data.
- `app/Modules/Admin/Filament/Pages/Backups/BackupManagement.php` — Attach the widget and pass the existing cached listing via getWidgetData().
- `app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php` — Cover summary values, empty/unhealthy storage, access control, and single-listing reuse.

**Validate command:**
```bash
rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php
```

### TASK-004: Add a non-duplicative account lifecycle indicator to user details

Extend UserActivityOverview with one aggregate: account status changes during the last 30 days. Keep current status in the existing infolist and detailed change records in their existing relation manager; do not repeat reasons or add mutation actions.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** codex

**Acceptance Criteria:**
- The user detail shows the selected user's 30-day status-change count alongside the existing activity metrics.
- Counts are scoped to the selected user and the current user-view authorization; no reason text or unrelated account data appears.
- A user with no status changes displays zero, while existing suspend/reactivate actions and the status infolist remain unchanged.

**Write scope:**
- `app/Modules/Admin/Filament/Resources/Users/Widgets/UserActivityOverview.php` — Add the status-change aggregate to the existing widget.
- `app/Modules/Admin/Tests/Filament/Resources/Users/Widgets/UserActivityOverviewTest.php` — Verify user scoping, 30-day boundaries, zero state, and authorization.

**Validate command:**
```bash
rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Users/Widgets/UserActivityOverviewTest.php
```

### TASK-005: Show accessible profile avatars in the users table

Add the user's profile picture to UsersTable from the existing MediaLibrary profile collection. Reuse its configured fallback and eager-load media so the new column does not add one query per row.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** codex

**Acceptance Criteria:**
- A user with a profile picture renders a small avatar with descriptive alt text; a user without one renders the configured default profile image with an accessible name.
- The users table continues to paginate and filter correctly, and profile media is loaded without query growth per displayed user.
- The column does not expose or change any user fields or actions beyond the requested picture.

**Write scope:**
- `app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php` — Add the profile image column, fallback, alt text, and media eager loading.
- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php` — Verify populated and empty media cases, accessibility label, permissions, and bounded queries.

**Validate command:**
```bash
rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php
```

### TASK-006: Open full reply context in a read-only modal

Add a row-level read action to RepliesRelationManager. The modal shows full reply text, author, creation time, optional edited time, and optional replied-to user; it must not provide form fields or mutation actions.

**Type:** feature  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** codex

**Acceptance Criteria:**
- The modal displays the complete selected reply, author, and creation time; edited_at and repliedToUser are shown only when present.
- A reply with no edit or mention renders without placeholder errors, and a missing replied-to user does not break the modal.
- The selected reply remains scoped to the current comment and the table remains read-only apart from opening this modal.

**Write scope:**
- `app/Modules/Admin/Filament/Resources/Comments/RelationManagers/RepliesRelationManager.php` — Add the authorized read-only row action and modal content.
- `app/Modules/Admin/Tests/Filament/Resources/Comments/RelationManagers/RepliesRelationManagerTest.php` — Verify complete content, optional context, record scoping, and no mutation actions.

**Validate command:**
```bash
rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Comments/RelationManagers/RepliesRelationManagerTest.php
```

### TASK-007: Capture the pre-optimization image-table baseline

Create a performance evidence note and measure both existing post-image tables before changing them. Use the same local dataset for both captures: the Posts list and a user's Posts relation with representative media-backed rows. Record row count, request ID, response duration, total query count, and repeated media-query shapes from Debugbar; omit user names and post content.

**Type:** docs  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** codex

**Acceptance Criteria:**
- The new note records the environment, row count, request ID, duration, total queries, and repeated media-query groups for both pages.
- The note contains no personal data, post content, credentials, or backup information; no baseline values are invented when a request was not captured.
- If either route has no representative captured request, capture it with an authorized local Admin session before TASK-008 or TASK-009 begins.

**Write scope:**
- `docs/development/admin-post-image-performance.md` — Store reproducible before/after measurements for both image tables.

**Validate command:**
```bash
rtk php artisan debugbar:find --max=50 --json
```

### TASK-008: Remove per-row media loading from the Posts list

After TASK-007 establishes the baseline, optimize the MediaLibrary-backed ImageColumn in PostsTable. Eager-load the media relation while preserving the current image collection, pagination, 64-pixel display, fallback, alt text, and list behavior.

**Type:** refactor  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** codex
**Depends on:** TASK-007

**Acceptance Criteria:**
- The Posts list resolves media without a query per row at both the default and larger supported page sizes.
- Posts with and without media preserve their current image and fallback behavior, and filters, author data, sorting, and pagination remain intact.
- The regression test verifies bounded media-query counts as row count increases and fails if a per-record media query returns.

**Write scope:**
- `app/Modules/Admin/Filament/Resources/Posts/Tables/PostsTable.php` — Eager-load post media for the list's image column without changing display behavior.
- `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ListPostsTest.php` — Add media-query regression coverage across row counts and preserve image fallback assertions.

**Validate command:**
```bash
rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ListPostsTest.php
```

### TASK-009: Remove per-row media loading from user post details

After TASK-007 establishes the baseline, optimize the PostsRelationManager image column. Preserve the required selected post keys, relation scoping, pagination, existing MediaLibrary collection, 64-pixel display, fallback, and alt text.

**Type:** refactor  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** codex
**Depends on:** TASK-007

**Acceptance Criteria:**
- The user detail loads post media with a bounded query count rather than one media query for each displayed post.
- The selected post key and user_id remain available to the relation query, and posts still link to the shared Post detail page.
- The regression test covers media present and missing and detects query growth when the relation page size increases.

**Write scope:**
- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/PostsRelationManager.php` — Eager-load media while preserving the owner-scoped select and table behavior.
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php` — Add query-growth regression coverage and verify existing image and navigation behavior.

**Validate command:**
```bash
rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php
```

### TASK-010: Record post-image performance after optimization

After TASK-008 and TASK-009, capture the same pages, row counts, environment, and Debugbar collectors as TASK-007. Update the evidence note with before/after query counts and durations, and explain any timing variance without imposing an absolute millisecond threshold.

**Type:** docs  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Review:** codex
**Depends on:** TASK-008, TASK-009

**Acceptance Criteria:**
- The note contains comparable before/after measurements for both tables, including row counts, query totals, media-query shapes, and request durations.
- The final captures show no per-row media-query group; if one remains, the task reports it and the issue is not ready to close.
- The note identifies environment and timing variance and contains no user or post content.

**Write scope:**
- `docs/development/admin-post-image-performance.md` — Append comparable post-optimization measurements and conclusion.

**Validate command:**
```bash
rtk php artisan debugbar:queries latest --json
```

### TASK-011: Reconcile the Admin RBAC catalog in backend architecture docs

As the adjacent EXPANSION item, update the existing backend architecture role table to match the current AdminRole enum and AdminAuthorizationSeeder. The document currently omits the backups reader and operator roles even though CLAUDE.md and the code catalog include them.

**Type:** docs  
**Effort:** S  
**Agent:** general-purpose  
**Priority:** P2  
**Review:** codex

**Acceptance Criteria:**
- The role table lists every current AdminRole and describes the backups reader as view-only and the backups operator as view/create/download.
- The documented role-permission bundles match AdminRole, AdminPermission, AdminAuthorizationSeeder, and CLAUDE.md without changing RBAC code.
- The existing read-only/resource authorization explanation remains accurate after the table update.

**Write scope:**
- `docs/architecture/backend.md` — Reconcile the existing RBAC reference table with the current code catalog.

**Validate command:**
```bash
rtk rg -n 'admin-posts-reader|admin-backups-reader|admin-backups-operator' docs/architecture/backend.md
```


## Failure Modes

- A user sees an operational metric or deep link without permission for its source; the widget must omit the card before the query/service call.
- An empty activity log or backup destination produces a missing/undefined statistic instead of an explicit empty value.
- A remote backup listing failure breaks the entire dashboard or creates a second storage scan on the backup page.
- A reply without edited_at or repliedToUser breaks the modal, or a row action resolves a reply outside its owner comment.
- Missing profile/post media renders a broken image or inaccessible link instead of the configured fallback and alt text.
- Lazy MediaLibrary lookups add one query per table row; query-growth tests and before/after Debugbar evidence must detect this.

## Ship Cut

The feature slices can be reviewed independently, but SOC-60 remains open until tasks TASK-001 through TASK-010 pass. Do not merge either media optimization before its pre-change baseline exists, and do not close the issue until the final evidence note compares both tables. TASK-011 is the adjacent documentation reconciliation and does not block the UI slices.

## Test Coverage Map

- **TASK-001** — `app/Modules/Admin/Tests/Filament/RegistrationDashboardTest.php`: Preserved registration widgets, source-specific dashboard data/visibility, empty sources, and missing backup summary.
- **TASK-002** — `app/Modules/Admin/Tests/Filament/Resources/ActivityLogs/Pages/ListActivityLogsTest.php`: Seven-day event aggregates, readable distribution, empty state, and read-only authorized listing.
- **TASK-003** — `app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php`: Backup counts/latest age/size, empty or unhealthy state, permission boundary, and cached listing reuse.
- **TASK-004** — `app/Modules/Admin/Tests/Filament/Resources/Users/Widgets/UserActivityOverviewTest.php`: Selected-user lifecycle count, 30-day boundary, zero count, and visibility.
- **TASK-005** — `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php`: Profile image, configured fallback, alt text, and bounded media query count.
- **TASK-006** — `app/Modules/Admin/Tests/Filament/Resources/Comments/RelationManagers/RepliesRelationManagerTest.php`: Full modal context, absent optional fields, parent scoping, and read-only action set.
- **TASK-008** — `app/Modules/Admin/Tests/Filament/Resources/Posts/Pages/ListPostsTest.php`: Posts table image/fallback behavior and query count across row counts.
- **TASK-009** — `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/PostsRelationManagerTest.php`: User-post image/fallback behavior, relation scope, and query count across row counts.

## Execution Summary

- Tareas: 11
- Capas paralelas: [TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-011] → [TASK-008, TASK-009] → [TASK-010]
- Ruta crítica: TASK-007 → TASK-008 → TASK-010 (3 tareas)

## Task Dependencies

- **TASK-001** no tiene dependencias
- **TASK-002** no tiene dependencias
- **TASK-003** no tiene dependencias
- **TASK-004** no tiene dependencias
- **TASK-005** no tiene dependencias
- **TASK-006** no tiene dependencias
- **TASK-007** no tiene dependencias
- **TASK-008** depende de TASK-007
- **TASK-009** depende de TASK-007
- **TASK-010** depende de TASK-008, TASK-009
- **TASK-011** no tiene dependencias
