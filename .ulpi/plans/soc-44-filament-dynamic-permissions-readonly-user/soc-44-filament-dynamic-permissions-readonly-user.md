# Plan: SOC-44: Panel administrativo Filament con permisos dinámicos y consulta de usuarios

## Overview

Instalar Filament 5 y Spatie Laravel Permission v8, integrar roles y permisos con el User personalizado del proyecto, provisionar y administrar asignaciones desde Artisan, proteger un panel Filament y añadir un único recurso User de solo lectura. El rol admin concede entrada al panel; permisos separados controlan cada recurso. La primera cuenta administrativa se promociona desde consola y no existe flujo web de autoasignación.

**Linear:** [SOC-44](https://linear.app/social-hub-ang/issue/SOC-44/feature-implement-filament-admin-panel-roles-and-user-management) · Backlog · Medium

**Modo:** EXPANSION · **Estilo:** Por fases; implementar un TASK por vez y detenerse para revisión después de cada tarea. · **Revisión predeterminada:** codex

## Scope Challenge

EXPANSION confirmada por el usuario: incluir Spatie desde el inicio, permisos granulares y bootstrap/operación por consola. No se añadirá una pantalla de administración de roles/permisos. Las operaciones de roles y permisos serán comandos exclusivos de Artisan. El recurso User seguirá siendo de consulta. La lógica e integración propias se ubicarán bajo app/Modules/Admin para respetar la base modular existente; las migraciones publicadas de paquetes externos estarán en database/migrations. Filament tendrá su propio PanelProvider y su ciclo de rutas, sin crear un app/Filament nuevo ni proveedores de rutas vacíos.

## Prerequisites

- SOC-44 permanece en Backlog hasta que el usuario autorice comenzar la implementación; este plan no cambia el issue de Linear.
- Stack inspeccionado: PHP ^8.5, Laravel ^13.32, Tailwind CSS ^4.3; la documentación de Filament 5 exige PHP 8.2+, Laravel 11.28+ y Tailwind 4.1+.
- El modelo autenticable real es App\User\Models\User y el único guard actual es web.
- Antes de implementar el recurso, confirmar en la revisión del plan la allowlist propuesta: name, email, email_verified_at, created_at y last_login_at; no mostrar phone, biography, imágenes ni filas relacionadas salvo justificación explícita.
- La lógica propia de Admin vivirá en app/Modules/Admin; las migraciones publicadas de paquetes externos vivirán en la carpeta global database/migrations.
- Las pruebas de Admin se ubicarán en app/Modules/Admin/Tests y se registrarán en phpunit.xml y tests/Pest.php.

## Non-Goals

- Implementar suspensión/reactivación o cualquier estado persistido de cuenta: permanece en SOC-35.
- Añadir otros recursos administrativos, ni edición, creación o eliminación de usuarios.
- Crear una UI Filament para gestionar roles, permisos o asignaciones; esas operaciones quedan solo en comandos Artisan ejecutables por operadores con acceso al servidor.
- Añadir una regla de Gate::before que otorgue permisos universales al rol admin; los permisos se evalúan por capacidad.
- Hacer obligatorio 2FA para el personal administrativo; el alcance es no exponer secretos y comprobar los controles existentes.
- Modificar pantallas React/Inertia o generar rutas Wayfinder para Filament.

## Contracts

- El panel usa el guard web y solo acepta el panel de id admin; FilamentUser::canAccessPanel verifica admin.access además de la política de cuenta acordada.
- El catálogo inicial contiene las capacidades admin.access, users.viewAny y users.view. El rol admin recibe solo admin.access; users-reader recibe users.viewAny y users.view. Consultar Usuarios requiere ambos roles.
- Los permisos se comprueban desde Laravel Policies/Gates y páginas Filament en el servidor; ocultar navegación no cuenta como autorización.
- El UserPolicy permite viewAny/view según permisos y deniega create/update/delete/deleteAny y equivalentes; el recurso registra únicamente index y view.
- La allowlist por defecto del panel es name, email, email_verified_at, created_at y last_login_at. Los secretos two_factor_secret y two_factor_recovery_codes se mantienen fuera de las respuestas de Livewire y de cualquier vista.
- AdminAuthorizationSeeder crea roles/permisos de forma repetible sin restablecer asignaciones dinámicas que un operador haya cambiado.
- admin:bootstrap-admin solo promociona una cuenta existente y verificada, solo cuando aún no existe una cuenta con admin.access, y asigna admin más users-reader. Los comandos de grant/revoke validan nombres contra el catálogo.
- Los comandos de operación no se publican como rutas ni acciones web; las pruebas verifican también el rechazo de cambios que dejarían el panel sin administrador.
- Cada cambio PHP se valida con la prueba del slice y, por las reglas del repositorio, ejecuta composer rector-dry seguido de composer rector. Las pruebas/gates completos y composer doctor quedan al cierre.

## Existing Code Leverage

- `app/User/Models/User.php` — Modelo autenticable custom; agregar HasRoles, FilamentUser y ocultar secretos 2FA.
- `config/auth.php` — Define el guard web y el modelo App\User\Models\User.
- `app/Auth/Modules/TrustedDevice/Policies/TrustedDevicePolicy.php` — Referencia de policy modular basada en permisos/propiedad.
- `app/Auth/Models/TrustedDevice.php` — Referencia para asociar una policy mediante #[UsePolicy].
- `app/Modules/Comment/Providers/CommentServiceProvider.php` — Patrón real de provider de módulo.
- `app/Auth/Providers/AuthServiceProvider.php` — Patrón real para cargar migraciones modulares y registrar comandos Artisan.
- `phpunit.xml` — Registra suites de pruebas por módulo.
- `tests/Pest.php` — Registra directorios modulares con RefreshDatabase y TestCase.
- `app/User/Resources/UserResource.php` — Ejemplo local de serialización mediante allowlist.
- `app/User/Modules/AccountSettings/Resources/AccountSettingsResource.php` — Inventario de atributos personales disponibles; no debe reutilizarse como allowlist administrativa sin aprobación.
- `resources/css/app.css` — Estilos actuales de Inertia/Tailwind que deben mantenerse aislados del tema Filament.
- `vite.config.ts` — Entrada Vite actual; registrar el tema Filament sin reemplazar la entrada existente.
- `bootstrap/app.php` — Añade middleware Inertia al grupo web compartido; verificar que no interfiera con el panel.

## Tasks

### TASK-001: Instalar Filament 5 y Spatie Permission v8

Agregar las dependencias compatibles de Filament y Spatie Permission, conservar las restricciones de PHP/Laravel existentes y publicar config/permission.php. Spatie resuelve el guard mediante auth.defaults.guard, que en este proyecto es web; mantener teams desactivado. Livewire llega como dependencia de Filament; no instalar ni modificar paquetes adicionales fuera de este conjunto.

**Type:** infra · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

- composer.json y composer.lock resuelven Filament 5 y Spatie Laravel Permission 8 con PHP 8.5 y Laravel 13.
- Filament, Livewire y Spatie Permission se resuelven desde Composer con las versiones instaladas, sin cambiar las restricciones actuales de Laravel o PHP.
- Laravel auth.defaults.guard se mantiene en web y config/permission.php mantiene teams desactivado, que es el comportamiento compatible con la configuración de Spatie Permission v8.

**writeScope:**

- `composer.json`
- `composer.lock`
- `config/permission.php`

**validateCommand:** `rtk composer validate && rtk composer show filament/filament && rtk composer show spatie/laravel-permission && rtk composer show livewire/livewire`

### TASK-002: Crear la migración global de permisos

Generar la migración de Spatie Permission en database/migrations, compatible con el id unsigned big integer de users, el guard web y teams desactivado. El proveedor Admin se añadirá junto con los primeros comandos del módulo, cuando tenga una integración concreta que registrar.

**Type:** infra · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

- La migración global crea roles, permissions y tablas pivote de Spatie con tipos de clave compatibles con users.id.
- El comando migrate --pretend limitado a database/migrations descubre la migración sin aplicarla; una configuración DB inválida no se oculta como éxito.

**writeScope:**

- `database/migrations/2026_10_10_150651_create_permission_tables.php`

**validateCommand:** `rtk php artisan migrate --pretend --path=database/migrations`

### TASK-003: Registrar la suite Pest de Admin

Añadir la suite Admin a phpunit.xml y registrar app/Modules/Admin/Tests en tests/Pest.php para heredar TestCase y RefreshDatabase igual que los otros módulos.

**Type:** infra · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-002

**Acceptance Criteria:**

- phpunit.xml ejecuta app/Modules/Admin/Tests bajo una suite Admin identificable.
- Pest aplica TestCase y RefreshDatabase a los tests del módulo.
- Las suites existentes Comment, Post, Auth y User conservan sus rutas y configuración.

**writeScope:**

- `phpunit.xml`
- `tests/Pest.php`

**validateCommand:** `rtk git diff --check -- phpunit.xml tests/Pest.php`

### TASK-004: Definir roles, permisos y seeder idempotente

Crear enums para los nombres estables de roles/permisos y un seeder AdminAuthorizationSeeder. Crear el rol admin con admin.access y users-reader con users.viewAny/users.view. Al crear por primera vez, asignar los permisos base; al volver a ejecutar el seeder, conservar cambios operativos existentes en los pivotes.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002

**Acceptance Criteria:**

- Los nombres de roles y permisos se centralizan en enums respaldados y se usan con sus valores string en Spatie.
- El rol admin solo recibe admin.access; users-reader recibe users.viewAny y users.view.
- Reejecutar el seeder no duplica filas ni vuelve a conceder un permiso que un operador hubiera revocado.

**writeScope:**

- `app/Modules/Admin/Enums/AdminRole.php`
- `app/Modules/Admin/Enums/AdminPermission.php`
- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`

**validateCommand:** `rtk php -l app/Modules/Admin/Enums/AdminRole.php && rtk php -l app/Modules/Admin/Enums/AdminPermission.php && rtk php -l app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`

### TASK-005: Probar catálogo e idempotencia de permisos

Añadir pruebas Pest del seeder en SQLite de testing. Verificar la asignación inicial del catálogo y que una modificación operativa a un pivote se conserva tras una segunda ejecución.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003, TASK-004

**Acceptance Criteria:**

- El seeder crea una sola copia de cada rol y permiso y establece las capacidades iniciales previstas.
- Una segunda ejecución conserva un permiso que el test revocó entre ejecuciones.
- El test falla si admin recibe users.view sin estar asignado users-reader.

**writeScope:**

- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

### TASK-006: Integrar roles en User y ocultar secretos 2FA

Añadir HasRoles al modelo App\User\Models\User y sumar two_factor_secret y two_factor_recovery_codes a sus atributos ocultos. No exponer roles/permisos como fillable ni aceptar su asignación mediante formularios.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001, TASK-003, TASK-004

**Acceptance Criteria:**

- User puede recibir roles y resolver permisos usando el guard web.
- toArray/JSON omite password, remember_token, two_factor_secret y two_factor_recovery_codes.
- La asignación masiva de campos role/roles/permissions no concede ni cambia acceso.

**writeScope:**

- `app/User/Models/User.php`
- `app/Modules/Admin/Tests/Models/UserRoleTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Models/UserRoleTest.php`

### TASK-007: Aplicar UserPolicy a lectura granular de usuarios

Crear UserPolicy en el dominio User y asociarla explícitamente al modelo. viewAny/view exigen sus permisos granulares; todas las capacidades de escritura permanecen denegadas.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-006

**Acceptance Criteria:**

- Un usuario con users.viewAny puede listar y solo users.view permite abrir un registro.
- Un usuario sin permiso recibe denegación para ambas operaciones.
- create/update/delete/deleteAny/restore/forceDelete están denegados aun cuando se invoquen mediante Gate directamente.

**writeScope:**

- `app/User/Models/User.php`
- `app/User/Policies/UserPolicy.php`
- `app/Modules/Admin/Tests/Policies/UserPolicyTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Policies/UserPolicyTest.php`

### TASK-008: Restringir el acceso al panel por permiso

Implementar FilamentUser::canAccessPanel(Panel $panel) en User. Permitir únicamente el panel admin a usuarios verificados con admin.access; los demás paneles quedan denegados.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-007

**Acceptance Criteria:**

- Una cuenta verificada con admin.access pasa la autorización del panel admin.
- Una cuenta regular, sin verificación o sin admin.access es rechazada.
- Un panel con id distinto de admin no se habilita por accidente.

**writeScope:**

- `app/User/Models/User.php`
- `app/Modules/Admin/Tests/Policies/UserPanelAccessTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Policies/UserPanelAccessTest.php`

### TASK-009: Crear el comando de bootstrap del primer administrador

Crear admin:bootstrap-admin {email}. El comando solo promueve una cuenta existente y verificada cuando todavía no hay ningún usuario con admin.access; asigna admin y users-reader dentro de una transacción. Crear y registrar AdminServiceProvider junto con el comando para alojar la integración de consola del módulo.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-005, TASK-006

**Acceptance Criteria:**

- El primer usuario verificado recibe exactamente los roles admin y users-reader sin crear credenciales ni una ruta web.
- Una cuenta inexistente o sin email verificado no cambia roles.
- El comando falla sin modificar datos si ya existe cualquier usuario con admin.access.

**writeScope:**

- `bootstrap/providers.php`
- `app/Modules/Admin/Providers/AdminServiceProvider.php`
- `app/Modules/Admin/Console/Commands/AdminBootstrapCommand.php`
- `app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`

### TASK-010: Gestionar asignaciones de roles desde Artisan

Crear un comando admin:user-role para conceder o revocar roles del catálogo a una cuenta identificada por email. No permitirá crear nombres arbitrarios, usar asignaciones masivas desde el modelo ni retirar admin.access al último administrador.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-009

**Acceptance Criteria:**

- Un operador puede conceder y revocar roles predefinidos y los cambios se reflejan en las comprobaciones de permiso.
- Un role desconocido o una cuenta inexistente se rechaza sin mutación.
- Revocar el último rol que concede admin.access se rechaza para evitar dejar el panel sin operador.

**writeScope:**

- `app/Modules/Admin/Providers/AdminServiceProvider.php`
- `app/Modules/Admin/Console/Commands/AdminUserRoleCommand.php`
- `app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php`

### TASK-011: Gestionar permisos de roles desde Artisan

Crear admin:role-permissions para conceder/revocar capacidades catalogadas en roles existentes. Las operaciones de consola son el único mecanismo operativo en SOC-44; no se crea UI de roles. Proteger el último acceso al panel y mantener el catálogo de nombres en AdminPermission.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-010

**Acceptance Criteria:**

- Un operador puede ajustar permisos de un rol usando solo nombres del enum AdminPermission.
- Un permiso o rol desconocido se rechaza sin cambiar pivotes.
- La revocación que dejaría sin admin.access a todas las cuentas se rechaza.

**writeScope:**

- `app/Modules/Admin/Providers/AdminServiceProvider.php`
- `app/Modules/Admin/Console/Commands/AdminRolePermissionsCommand.php`
- `app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`

### TASK-012: Registrar el panel Filament protegido

Crear AdminPanelProvider con id admin, path admin, guard web, login y descubrimiento de recursos dentro de app/Modules/Admin/Filament/Resources. Registrar el provider en bootstrap/providers.php y no habilitar registro público ni recursos de administración de roles.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-008, TASK-002

**Acceptance Criteria:**

- GET /admin redirige a autenticación para visitas anónimas y una cuenta autorizada puede entrar después de autenticarse.
- Una cuenta autenticada sin admin.access recibe una denegación del servidor aun entrando directamente a /admin.
- El panel no registra signup, recursos de roles ni acciones de escritura de usuarios.

**writeScope:**

- `bootstrap/providers.php`
- `app/Modules/Admin/Providers/AdminPanelProvider.php`
- `app/Modules/Admin/Tests/Filament/AdminPanelAccessTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/AdminPanelAccessTest.php`

### TASK-013: Compilar un tema Filament aislado

Crear la hoja del tema Filament, incorporarla como entrada Vite independiente y conectar viteTheme() en AdminPanelProvider. Mantener resources/css/app.css y la entrada React/Inertia intactas.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-012

**Acceptance Criteria:**

- El tema importa los estilos requeridos de Filament 5 y escanea las vistas/componentes Filament del módulo Admin.
- Vite compila el tema Filament como entrada sin sustituir app.css ni app.tsx.
- La build de producción incluye el CSS usado por /admin y mantiene operativa la build SSR.

**writeScope:**

- `vite.config.ts`
- `app/Modules/Admin/Providers/AdminPanelProvider.php`
- `resources/css/filament/admin/theme.css`

**validateCommand:** `rtk npm run build && rtk npm run build:ssr`

### TASK-014: Crear Users Resource de consulta con lista y detalle

Crear UserResource, ListUsers y ViewUser bajo app/Modules/Admin/Filament/Resources. Implementar búsqueda por name/email, paginación y detalle con infolist de campos allowlisted. No generar páginas Create/Edit ni acciones de escritura.

**Type:** feature · **Effort:** L · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-012, TASK-007

**Acceptance Criteria:**

- La lista permite buscar por name/email y navegar resultados paginados.
- El detalle muestra solo la allowlist confirmada y no carga relaciones completas; cualquier conteo adicional requiere autorización del usuario en la revisión.
- El registro de páginas contiene únicamente index y view, sin rutas Create/Edit/Delete ni acciones masivas de mutación.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/UserResource.php`
- `app/Modules/Admin/Filament/Resources/UserResource/Pages/ListUsers.php`
- `app/Modules/Admin/Filament/Resources/UserResource/Pages/ViewUser.php`

**validateCommand:** `rtk php artisan route:list --path=admin`

### TASK-015: Probar acceso, búsqueda, detalle y datos secretos del recurso

Añadir pruebas Livewire/Filament del recurso de usuarios para permisos viewAny/view, búsqueda, detalle allowlisted, denegación de páginas de escritura y ausencia de valores TOTP/recovery codes en el HTML/respuestas serializadas.

**Type:** test · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-014, TASK-006, TASK-008

**Acceptance Criteria:**

- Un usuario con admin y users-reader ve lista, busca y abre el detalle; un usuario sin users.viewAny o users.view no puede completar la operación correspondiente.
- Las URLs directas de create/edit/delete se rechazan y no mutan User.
- Una fixture con valores centinela en two_factor_secret y recovery codes no aparece en HTML ni en estado serializado devuelto al navegador.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/UserResourceTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/UserResourceTest.php`

### TASK-016: Documentar la integración Admin, Filament y Spatie

Actualizar la arquitectura backend y las referencias del proyecto para reflejar el módulo Admin, la excepción de rutas provistas por Filament, los nombres de permisos, los comandos de bootstrap/operación, los datos visibles, la ubicación global de migraciones publicadas de paquetes y la ubicación modular de la implementación y las pruebas.

**Type:** docs · **Effort:** M · **Agent:** general-purpose · **Priority:** P2

**Depends on:** TASK-011, TASK-013, TASK-015

**Acceptance Criteria:**

- docs/architecture/backend.md refleja panel, provider, policies, datos y pruebas observados en el código.
- CLAUDE.md documenta la ubicación modular Filament y los comandos Artisan de operación.
- AGENTS.md registra las dependencias y reglas específicas de Filament/Spatie sin duplicar ni contradecir las reglas existentes.

**writeScope:**

- `docs/architecture/backend.md`
- `CLAUDE.md`
- `AGENTS.md`

**validateCommand:** `rtk git diff --check -- docs/architecture/backend.md CLAUDE.md AGENTS.md`

### TASK-017: Ejecutar las pruebas y gates finales de SOC-44

Con todos los slices integrados, correr la suite completa, verificaciones backend/frontend requeridas y Doctor al final. Corregir fallos dentro de tareas nuevas con revisión del usuario antes de cerrar SOC-44; no mover SOC-35 a In Progress.

**Type:** test · **Effort:** L · **Agent:** general-purpose · **Priority:** P1

**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016

**Acceptance Criteria:**

- Pint, PHPStan, Rector dry-run y Rector apply completan en el orden requerido; las pruebas Pest completas pasan.
- format:check, lint:check, types, build y build:ssr pasan; la matriz de autorización y la ausencia de secretos se conserva.
- composer doctor es el último gate local y queda verde antes de solicitar cierre de SOC-44.

**writeScope:**

- Sin archivos.
**validateCommand:** `rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk composer rector-dry && rtk composer rector && rtk php artisan test --compact && rtk npm run format:check && rtk npm run lint:check && rtk npm run types && rtk npm run build && rtk npm run build:ssr && rtk composer doctor`

## Failure Modes

- Una cuenta regular o sin admin.access no puede abrir /admin aunque conozca la URL.
- Una cuenta con admin.access pero sin users.viewAny/users.view no ve ni abre UserResource.
- El comando de bootstrap rechaza email inexistente/no verificado y cualquier intento después de inicializar el primer administrador.
- Los comandos rechazan roles/permisos desconocidos y no permiten retirar el acceso al último administrador.
- Las rutas de creación/edición/eliminación de User no existen o responden con denegación; no se confía en botones ocultos.
- Las vistas y datos serializados no contienen password, remember_token, two_factor_secret ni two_factor_recovery_codes.
- La repetición del seeder no duplica registros ni revierte permisos personalizados.
- El tema Filament compila sin eliminar ni alterar la hoja de estilos de Inertia.

## Ship Cut

El único corte liberable es después de TASK-017 con pruebas y gates verdes. No desplegar un estado parcial que exponga /admin antes de probar acceso al panel, policies y páginas User. Si el trabajo se pausa antes de TASK-012, el panel aún no se registra en bootstrap; si se pausa después, la suite de autorización del panel debe estar integrada antes de publicar el cambio.

## Test Coverage Map

- **TASK-001:** validación: `rtk composer validate && rtk composer show filament/filament && rtk composer show spatie/laravel-permission && rtk composer show livewire/livewire`
- **TASK-002:** validación: `rtk php artisan migrate --pretend --path=database/migrations`
- **TASK-003:** `tests/Pest.php`
- **TASK-004:** validación: `rtk php -l app/Modules/Admin/Enums/AdminRole.php && rtk php -l app/Modules/Admin/Enums/AdminPermission.php && rtk php -l app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`
- **TASK-005:** `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`
- **TASK-006:** `app/Modules/Admin/Tests/Models/UserRoleTest.php`
- **TASK-007:** `app/Modules/Admin/Tests/Policies/UserPolicyTest.php`
- **TASK-008:** `app/Modules/Admin/Tests/Policies/UserPanelAccessTest.php`
- **TASK-009:** `app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`
- **TASK-010:** `app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php`
- **TASK-011:** `app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`
- **TASK-012:** `app/Modules/Admin/Tests/Filament/AdminPanelAccessTest.php`
- **TASK-013:** validación: `rtk npm run build && rtk npm run build:ssr`
- **TASK-014:** validación: `rtk php artisan route:list --path=admin`
- **TASK-015:** `app/Modules/Admin/Tests/Filament/UserResourceTest.php`
- **TASK-016:** validación: `rtk git diff --check -- docs/architecture/backend.md CLAUDE.md AGENTS.md`
- **TASK-017:** validación: `rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk composer rector-dry && rtk composer rector && rtk php artisan test --compact && rtk npm run format:check && rtk npm run lint:check && rtk npm run types && rtk npm run build && rtk npm run build:ssr && rtk composer doctor`

## Execution Summary

- Tareas: 17
- Fases: 5
- Camino crítico: TASK-001 → TASK-002 → TASK-003 → TASK-006 → TASK-007 → TASK-008 → TASK-012 → TASK-014 → TASK-015 → TASK-016 → TASK-017 (11 tareas)
- Revisión predeterminada: codex

## Task Dependencies

- **TASK-001** ← ninguna
- **TASK-002** ← TASK-001
- **TASK-003** ← TASK-002
- **TASK-004** ← TASK-002
- **TASK-005** ← TASK-003, TASK-004
- **TASK-006** ← TASK-001, TASK-003, TASK-004
- **TASK-007** ← TASK-006
- **TASK-008** ← TASK-007
- **TASK-009** ← TASK-005, TASK-006
- **TASK-010** ← TASK-009
- **TASK-011** ← TASK-010
- **TASK-012** ← TASK-008, TASK-002
- **TASK-013** ← TASK-012
- **TASK-014** ← TASK-012, TASK-007
- **TASK-015** ← TASK-014, TASK-006, TASK-008
- **TASK-016** ← TASK-011, TASK-013, TASK-015
- **TASK-017** ← TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016

