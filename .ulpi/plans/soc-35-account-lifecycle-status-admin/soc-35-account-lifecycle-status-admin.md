# Plan: SOC-35: Gestionar el estado de las cuentas (activa/suspendida) desde el panel admin

## Overview

Persistir el estado de cada cuenta (`users.status`) con un historial obligatorio de motivos (`account_status_changes`), permitir que un rol nuevo suspenda y reactive cuentas desde el detalle del usuario en Filament, bloquear toda vía de acceso de una cuenta suspendida (contraseña, reto 2FA, dispositivo confiable, panel admin y sesiones abiertas) y mostrar el estado de solo lectura en Configuración de cuenta.

**Linear:** [SOC-35](https://linear.app/social-hub-ang/issue/SOC-35/feature-manage-account-lifecycle-status-from-the-admin-panel) · **Modo:** EXPANSION · **Revisión predeterminada:** Claude

## Scope Challenge

EXPANSION con revisión de Claude. Decisiones del usuario (2026-10-23): los permisos y el rol se agregan en esta tarea; todos los puntos de bloqueo están en alcance; el motivo es obligatorio al suspender y al reactivar y vive en `account_status_changes`; al suspender se revocan los dispositivos confiables; el login muestra un mensaje específico de suspensión solo después de validar la contraseña. Extras elegidos: pestaña de historial en ViewUser, columna y filtro de estado en Users, y usuarios suspendidos en los seeders. Hallazgos: el reto 2FA es de Fortify (`TwoFactorLoginRequest` con `login.id` en sesión), así que se cubre con una subclase enlazada en el contenedor; `ActivityLogSeeder` no hace nada si `activity_log` tiene filas, por eso `AccountStatusSeeder` no puede usar el servicio.

## Prerequisites

- Panel Filament con guard `admin` y autorización en policies de modelo (SOC-44, SOC-57).
- `ActivityLogger` y el catálogo `ActivityLogEvent::ENUMS` (SOC-54); `ActivityLogAdminEvent` ya está registrado.
- `TrustedDeviceService::revokeAll(User, Request)` existe y registra `RevokedAll`.
- `UserServiceProvider` carga las migraciones de `app/User/Database/Migrations/`.

## Non-Goals

- Estados distintos de activa y suspendida (p. ej. pendiente de revisión).
- Suspensiones temporales con fecha de fin automática.
- Notificar por correo a la cuenta suspendida.
- Restaurar los dispositivos confiables al reactivar (se vuelven a registrar con 2FA).
- Gestionar roles o permisos desde una pantalla (sigue siendo por comandos).
- Cambiar el flujo de eliminación de cuenta.

## Contracts

- `users.status` es la única fuente de verdad del estado; no se deriva de `email_verified_at` ni de `last_login_at`.
- `status` no es asignable masivamente: solo `AccountStatusService` lo cambia, siempre junto con una fila de `account_status_changes` en la misma transacción.
- El motivo vive solo en `account_status_changes`; el activity log guarda ids (`target_user_id`) y nunca el texto.
- La autorización es de `UserPolicy` (`suspend`/`reactivate`); el servicio no autoriza y Filament aplica la policy en el servidor.
- Un administrador no puede suspender su propia cuenta.
- El mensaje de suspensión solo aparece con credenciales correctas; con contraseña incorrecta se mantiene `auth.failed`.
- Las cuentas activas no notan ningún cambio en login, 2FA, dispositivos confiables ni panel.

## Existing Code Leverage

- `TrustedDeviceEvent::record()` + `TrustedDeviceService`: patrón de fila append-only escrita en la misma transacción que el cambio de estado.
- `TrustedDeviceService::revokeAll()`: revocación de dispositivos al suspender.
- `ActivityLogger::record()` y el registro directo de los comandos Admin y `AdminInvitationService`.
- `UserPolicy` + `#[UsePolicy]` y la consulta de policies de Filament (SOC-57).
- `ActivityLogsRelationManager` como modelo de pestaña de solo lectura en `ViewUser`.
- `AdminAuthorizationSeeder`: crea roles nuevos con su paquete de permisos la primera vez.
- `TwoFactorStatus` / `AccountSettingsResource`: patrón de enum de estado expuesto a Configuración.

## Tasks

### TASK-001: Crear el enum AccountStatus y registrar la suite de pruebas del submódulo

Crear `App\User\Enums\AccountStatus` (string backed): `Active = 'active'`, `Suspended = 'suspended'`, `label()` en inglés para el panel ('Active', 'Suspended') y la constante `SUSPENDED_LOGIN_MESSAGE` con el texto en español que verán el login, el reto 2FA y el middleware ('Tu cuenta está suspendida. Contacta al equipo de soporte.'). Crear el submódulo `app/User/Modules/AccountStatus/` registrando su carpeta `Tests` como suite `AccountStatus` en `phpunit.xml` y en el `->in(...)` de `tests/Pest.php` (TestCase + RefreshDatabase).

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. `AccountStatus::cases()` devuelve exactamente `active` y `suspended`, y `AccountStatus::tryFrom('pending')` es `null`.
2. `phpunit.xml` y `tests/Pest.php` incluyen `app/User/Modules/AccountStatus/Tests`; un test colocado ahí recibe `RefreshDatabase`.

**writeScope:**

- `app/User/Enums/AccountStatus.php` (nuevo)
- `phpunit.xml`
- `tests/Pest.php`

**validateCommand:** `rtk vendor/bin/phpstan analyse app/User/Enums/AccountStatus.php`

**Review:** claude

### TASK-002: Migración: users.status y tabla account_status_changes

Una migración nueva en `app/User/Database/Migrations/` (la carga `UserServiceProvider`): agrega `users.status` (string, not null, default `active`, con índice) — el default deja como activas todas las filas existentes, que es el backfill — y crea `account_status_changes` (append-only): `id`, `user_id` (FK a `users`, `cascadeOnDelete`, la historia se va con la cuenta eliminada), `status` (string), `reason` (text, not null), `changed_by` (FK nullable a `users`, `nullOnDelete`), `created_at` (sin `updated_at`) e índice `(user_id, created_at)`. `down()` borra la tabla y la columna. Prueba en `Tests/Database/`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**

1. Un usuario insertado sin `status` queda con `active`; tras `rollback` + `migrate` las filas existentes quedan en `active`.
2. Borrar al administrador de `changed_by` deja la fila con `changed_by = null`; borrar la cuenta afectada borra sus filas.
3. Insertar una fila sin `reason` falla con una violación de NOT NULL.

**writeScope:**

- `app/User/Database/Migrations/2026_10_23_204636_add_account_status_to_users_table.php` (nuevo)
- `app/User/Modules/AccountStatus/Tests/Database/AccountStatusMigrationTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/User/Modules/AccountStatus/Tests/Database/AccountStatusMigrationTest.php`

**Review:** claude

### TASK-003: Modelo AccountStatusChange y su factory

Crear `App\User\Models\AccountStatusChange` con `MORPH_NAME = 'account_status_change'`, `UPDATED_AT = null`, `REASON_MAX_LENGTH = 1000`, cast de `status` a `AccountStatus` y `created_at` inmutable, relaciones `user()` y `changedBy()` (BelongsTo a `User`) y `#[UseFactory]`. `AccountStatusChangeFactory` completa (regla de factories): `user_id`, `status`, `reason`, `changed_by`, con estados `suspended()`, `reactivated()` y `changedBy(User)`. El registro en la morph map lo hace TASK-007.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002

**Acceptance Criteria:**

1. La factory crea una fila válida con `status` casteado a `AccountStatus` y `changedBy` resuelto.
2. Una fila cuyo administrador fue borrado devuelve `changedBy = null` sin error.
3. El modelo no escribe `updated_at`.

**writeScope:**

- `app/User/Models/AccountStatusChange.php` (nuevo)
- `app/User/Database/Factories/AccountStatusChangeFactory.php` (nuevo)
- `app/User/Modules/AccountStatus/Tests/Models/AccountStatusChangeTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/User/Modules/AccountStatus/Tests/Models/AccountStatusChangeTest.php`

**Review:** claude

### TASK-004: Estado de cuenta en el modelo User y su factory

En `User`: cast `status` → `AccountStatus`, `isSuspended()`, relación `accountStatusChanges()` (HasMany, más reciente primero). `status` NO entra en `#[Fillable]`: solo el servicio lo cambia con `forceFill`. En `UserFactory`: `status => Active` en la definición y estado `suspended()`. Prueba en `Tests/Models/UserAccountStatusTest.php`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001, TASK-003

**Acceptance Criteria:**

1. `createUser()` queda `active` y `isSuspended()` es `false`; con `suspended()` es `true`.
2. `$user->update(['status' => 'suspended'])` no cambia el estado (no es asignable masivamente).
3. `accountStatusChanges()` devuelve solo las filas de ese usuario, la más reciente primero.

**writeScope:**

- `app/User/Modules/AccountStatus/Tests/Models/UserAccountStatusTest.php` (nuevo)
- `app/User/Models/User.php`
- `app/User/Database/Factories/UserFactory.php`

**validateCommand:** `rtk php artisan test --compact app/User/Modules/AccountStatus/Tests/Models/UserAccountStatusTest.php`

**Review:** claude

### TASK-005: Permisos y rol de moderación de cuentas en el catálogo Admin

Agregar a `AdminPermission` `AdminUsersSuspend = 'admin.users.suspend'` y `AdminUsersReactivate = 'admin.users.reactivate'`; a `AdminRole` `AdminUsersModerator = 'admin-users-moderator'`; y en `AdminAuthorizationSeeder` el paquete del rol: `admin.users.viewAny`, `admin.users.view`, `admin.users.suspend`, `admin.users.reactivate`. Los comandos `admin:user-role` y `admin:role-permissions` aceptan los casos nuevos porque validan contra los enums. El rol no se asigna automáticamente (como `admin-users-provisioner`): `AdminUserSeeder::readerRoles()` y `admin:bootstrap-admin` no cambian. En una base existente basta volver a correr el seeder: crea el rol con sus permisos la primera vez.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** Ninguna

**Acceptance Criteria:**

1. Tras el seeder existen el rol `admin-users-moderator` y los dos permisos bajo el guard `admin`.
2. Las pruebas existentes del seeder y de los comandos Admin siguen pasando sin cambios en los roles previos.
3. `admin:user-role grant <email> admin-users-moderator --actor=<admin>` asigna el rol; un nombre inexistente sigue respondiendo error.

**writeScope:**

- `app/Modules/Admin/Enums/AdminPermission.php`
- `app/Modules/Admin/Enums/AdminRole.php`
- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php app/Modules/Admin/Tests/Commands`

**Review:** claude

### TASK-006: UserPolicy: habilidades suspend y reactivate

Agregar a `UserPolicy` `suspend(User $actor, User $account)` (permiso `admin.users.suspend`, la cuenta está activa y no es la del propio administrador) y `reactivate(User $actor, User $account)` (permiso `admin.users.reactivate` y la cuenta está suspendida). `update`, `delete` y el resto siguen en `false`. Ampliar `UserPolicyTest` y fijar en `AdminAuthorizationSeederTest` el paquete del rol nuevo.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004, TASK-005

**Acceptance Criteria:**

1. Con el permiso, `suspend` se permite sobre una cuenta activa ajena y `reactivate` sobre una suspendida.
2. Se niega sin el permiso, sobre la propia cuenta, al suspender una ya suspendida y al reactivar una activa.
3. `update`/`delete` siguen negados aun para `admin-users-moderator`.

**writeScope:**

- `app/User/Policies/UserPolicy.php`
- `app/Modules/Admin/Tests/Policies/UserPolicyTest.php`
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Policies/UserPolicyTest.php app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**Review:** claude

### TASK-007: Eventos del activity log y morph map

Agregar a `ActivityLogAdminEvent` `AccountSuspended = 'admin.account_suspended'` y `AccountReactivated = 'admin.account_reactivated'` (el enum ya está en `ActivityLogEvent::ENUMS`). Registrar `AccountStatusChange::MORPH_NAME` en `AppServiceProvider::registerMorphMap()`. `ActivityLogPrivacyTest` exige que cada caso se registre en algún lugar de `app/`; eso lo cumple TASK-008, por eso no está en este validate.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**

1. `ActivityLogEventCatalog::label('admin.account_suspended')` devuelve 'Account Suspended'.
2. `Relation::getMorphedModel('account_status_change')` devuelve `AccountStatusChange`; la morph map sigue rechazando clases no registradas.

**writeScope:**

- `app/Modules/ActivityLog/Enums/Events/ActivityLogAdminEvent.php`
- `app/Providers/AppServiceProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Contracts/ActivityLogEventTest.php`

**Review:** claude

### TASK-008: AccountStatusService: suspender y reactivar

Crear `App\User\Modules\AccountStatus\Services\AccountStatusService` con `suspend(User $account, User $actor, string $reason): AccountStatusChange` y `reactivate(...)`. Cada uno: recorta el motivo y lanza `InvalidArgumentException` si queda vacío o pasa `REASON_MAX_LENGTH`; abre `DB::transaction`, relee la cuenta con `lockForUpdate` y lanza `LogicException` si ya está en el estado destino (sin escribir nada); crea la fila de `account_status_changes`, actualiza `users.status` con `forceFill`, y registra con `ActivityLogger::record()` el evento con la fila como sujeto, el administrador como causer y solo `target_user_id` en propiedades (nunca el motivo). Al suspender llama a `TrustedDeviceService::revokeAll($account, request())` dentro de la misma transacción. No autoriza: la autorización es de la policy (TASK-006) y la aplica quien llama.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004, TASK-007

**Acceptance Criteria:**

1. Suspender deja `status = suspended`, una fila con motivo y `changed_by`, los dispositivos confiables revocados y un registro `admin.account_suspended` sin el texto del motivo.
2. Reactivar deja `active`, conserva publicaciones, comentarios y la fila de suspensión, y no restaura los dispositivos revocados.
3. Un motivo vacío o un estado repetido lanzan la excepción y no escriben filas, actividad ni cambios en dispositivos; `ActivityLogPrivacyTest` pasa.

**writeScope:**

- `app/User/Modules/AccountStatus/Services/AccountStatusService.php` (nuevo)
- `app/User/Modules/AccountStatus/Tests/Services/AccountStatusServiceTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/User/Modules/AccountStatus/Tests/Services/AccountStatusServiceTest.php app/Modules/ActivityLog/Tests/Security/ActivityLogPrivacyTest.php`

**Review:** claude

### TASK-009: Bloquear el login con contraseña (incluido el salto por dispositivo confiable)

En `LoginRequest::validateCredentials()`, después de validar las credenciales, si la cuenta está suspendida lanzar `ValidationException` en `email` con `AccountStatus::SUSPENDED_LOGIN_MESSAGE` (sin disparar `Failed` ni sumar intentos al rate limiter). Como `LoginSessionController::store()` consulta `TrustedDevice::validateAndTouch()` después, la cookie de dispositivo confiable tampoco salta el bloqueo y no se guarda `login.id` para el reto 2FA.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001, TASK-004

**Acceptance Criteria:**

1. Una cuenta suspendida con la contraseña correcta queda como invitada y recibe el mensaje de suspensión.
2. Con 2FA confirmado y una cookie de dispositivo confiable válida tampoco entra, y la sesión no contiene `login.id`.
3. Con contraseña incorrecta recibe `auth.failed`, no el mensaje de suspensión (no revela el estado).

**writeScope:**

- `app/Auth/Modules/Login/Tests/SuspendedAccountLoginTest.php` (nuevo)
- `app/Auth/Modules/Login/Requests/LoginRequest.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/Login/Tests/SuspendedAccountLoginTest.php app/Auth/Modules/Login/Tests/AuthenticationTest.php app/Auth/Modules/Login/Tests/TrustedDeviceOtpBypassTest.php`

**Review:** claude

### TASK-010: Bloquear el reto 2FA de Fortify

Cubrir la cuenta que pasó la contraseña antes de ser suspendida y tiene `login.id` en sesión. Crear `App\Auth\Modules\Login\Requests\TwoFactorLoginRequest` que extiende el de Fortify y sobrescribe `hasChallengedUser()` y `challengedUser()`: si la cuenta está suspendida olvida `login.id`/`login.remember` y lanza `HttpResponseException` con redirección a `login` y el error en `email`. Enlazarla en `FortifyServiceProvider::register()` (`$this->app->bind(FortifyTwoFactorLoginRequest::class, TwoFactorLoginRequest::class)`).

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001, TASK-004

**Acceptance Criteria:**

1. GET y POST de `two-factor.login` con una cuenta suspendida redirigen al login con el mensaje y la dejan como invitada.
2. Un código de recuperación válido enviado por una cuenta suspendida no se consume.
3. Una cuenta activa completa el reto como antes (las pruebas de 2FA existentes pasan).

**writeScope:**

- `app/Auth/Modules/Login/Requests/TwoFactorLoginRequest.php` (nuevo)
- `app/Auth/Modules/Login/Tests/SuspendedAccountTwoFactorChallengeTest.php` (nuevo)
- `app/Providers/FortifyServiceProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/Login/Tests/SuspendedAccountTwoFactorChallengeTest.php app/Auth/Modules/Login/Tests/AuthenticationTest.php app/Auth/Modules/TrustedDevice/Tests/Listeners`

**Review:** claude

### TASK-011: Middleware EnsureAccountIsActive en web y admin

Crear `App\Http\Middleware\EnsureAccountIsActive` con parámetro de guard (`EnsureAccountIsActive:admin`): si el usuario autenticado del guard está suspendido cierra ese guard, invalida la sesión y regenera el token; responde 403 JSON con el mensaje cuando la petición espera JSON (el toggle de likes con `useHttp`) y si no redirige al login correspondiente (`login` para web, la URL de login del panel para admin) con el error. Invitados y cuentas activas pasan sin consultas extra. Agregarlo al grupo `web` en `bootstrap/app.php` y a `authMiddleware` de `AdminPanelProvider` después de `Authenticate`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**

1. `AuthenticationTest` y `AdminPanelAccessTest` existentes siguen pasando.
2. El middleware queda registrado en ambos grupos y no rompe las páginas de invitado.

**writeScope:**

- `app/Http/Middleware/EnsureAccountIsActive.php` (nuevo)
- `bootstrap/app.php`
- `app/Modules/Admin/Providers/AdminPanelProvider.php`

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/Login/Tests/AuthenticationTest.php app/Modules/Admin/Tests/Filament/AdminPanelAccessTest.php`

**Review:** claude

### TASK-012: Pruebas de sesiones existentes

Cubrir que las sesiones abiertas dejan de servir tras la suspensión: sesión web, cookie remember-me, petición JSON y sesión del panel admin.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-011, TASK-013

**Acceptance Criteria:**

1. Una sesión web abierta antes de suspender redirige al login en la siguiente petición y queda como invitada; con remember-me tampoco vuelve a entrar.
2. `POST posts/{post}/like` de una cuenta suspendida responde 403 JSON y no crea el like.
3. Una sesión del panel `admin` abierta deja de acceder tras suspender a ese administrador; una cuenta activa no se ve afectada.

**writeScope:**

- `app/User/Modules/AccountStatus/Tests/Middleware/EnsureAccountIsActiveTest.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/SuspendedAdminSessionTest.php` (nuevo)

**validateCommand:** `rtk php artisan test --compact app/User/Modules/AccountStatus/Tests/Middleware/EnsureAccountIsActiveTest.php app/Modules/Admin/Tests/Filament/SuspendedAdminSessionTest.php`

**Review:** claude

### TASK-013: canAccessPanel rechaza cuentas suspendidas

Agregar `&& ! $this->isSuspended()` a `User::canAccessPanel()`. El login de Filament ya consulta `canAccessPanel()` después de autenticar, así que un administrador suspendido no entra al panel aunque tenga `admin.access` y MFA.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**

1. Un administrador verificado con `admin.access` y suspendido no puede acceder al panel `admin`.
2. Al reactivarlo vuelve a poder acceder; los demás casos de `UserPanelAccessTest` no cambian.

**writeScope:**

- `app/User/Models/User.php`
- `app/Modules/Admin/Tests/Policies/UserPanelAccessTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Policies/UserPanelAccessTest.php`

**Review:** claude

### TASK-014: Acciones Suspend y Reactivate en el detalle del usuario

En `ViewUser::getHeaderActions()` agregar 'Suspend account' y 'Reactivate account': confirmación, `Textarea` 'Reason' obligatoria con `maxLength(AccountStatusChange::REASON_MAX_LENGTH)`, `->authorize('suspend'|'reactivate')` contra la policy (oculta y rechazada en el servidor si no se cumple), llamada a `AccountStatusService` con el administrador autenticado y notificación de éxito; una `LogicException` (estado cambiado por otra pestaña) se muestra como notificación de error.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-006, TASK-008

**Acceptance Criteria:**

1. Un `admin-users-moderator` suspende con motivo desde la página y la cuenta queda suspendida con su fila e historial de actividad.
2. Sin motivo la acción no se ejecuta y muestra el error de validación; sin el permiso la acción no se ve y ejecutarla directamente no cambia nada.
3. La acción de suspender no aparece en la propia cuenta del administrador ni en una ya suspendida.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserAccountStatusActionsTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Users/Pages/ViewUser.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserAccountStatusActionsTest.php app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ViewUserTest.php`

**Review:** claude

### TASK-015: Columna, filtro y entrada de estado en Users

En `UsersTable` agregar la columna 'Status' (badge con `AccountStatus::label()`, verde/rojo) y un `SelectFilter` por estado; en `UserInfolist` agregar la entrada 'Status'. Sin consultas extra: la columna está en `users`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-004

**Acceptance Criteria:**

1. La tabla muestra 'Active'/'Suspended' y el filtro 'Suspended' lista solo las cuentas suspendidas.
2. El detalle muestra el estado y la tabla mantiene el número de consultas con 20 usuarios.

**writeScope:**

- `app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersAccountStatusTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Users/Tables/UsersTable.php`
- `app/Modules/Admin/Filament/Resources/Users/Schemas/UserInfolist.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersAccountStatusTest.php app/Modules/Admin/Tests/Filament/Resources/Users/Pages/ListUsersTest.php`

**Review:** claude

### TASK-016: Pestaña de historial de estado en el detalle del usuario

Crear `AccountStatusChangesRelationManager` (relación `accountStatusChanges`, título 'Status history'): columnas fecha, estado (badge), motivo (envuelto) y 'Changed by' ('Deleted user' si es `null`), con `changedBy` precargado; solo lectura (sin create/edit/delete) y visible cuando el administrador puede `view` al usuario. Registrarlo en `UserResource::getRelations()`.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-004

**Acceptance Criteria:**

1. Muestra las filas del usuario, la más reciente primero, con motivo y administrador; no muestra filas de otros usuarios.
2. Un administrador sin `admin.users.view` no ve la pestaña; no hay acciones de escritura.
3. Una fila con `changed_by` nulo muestra 'Deleted user' sin error.

**writeScope:**

- `app/Modules/Admin/Filament/Resources/Users/RelationManagers/AccountStatusChangesRelationManager.php` (nuevo)
- `app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/AccountStatusChangesRelationManagerTest.php` (nuevo)
- `app/Modules/Admin/Filament/Resources/Users/UserResource.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/Admin/Tests/Filament/Resources/Users/RelationManagers/AccountStatusChangesRelationManagerTest.php app/Modules/Admin/Tests/Filament/Resources/Users/UserResourceTest.php`

**Review:** claude

### TASK-017: Exponer el estado en AccountSettingsResource

Agregar `status` (valor de `AccountStatus`) al array y al shape PHPDoc de `AccountSettingsResource`. El controlador solo serializa al usuario autenticado, así que no se expone el estado de otras cuentas; no se agrega ningún campo de estado a `AccountSettingsUpdateRequest`.

**Type:** feature · **Effort:** XS · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-004

**Acceptance Criteria:**

1. La página de configuración recibe `accountSettings.status = 'active'` para el usuario autenticado.
2. Enviar `status` en el PUT de configuración no cambia el estado de la cuenta.

**writeScope:**

- `app/User/Modules/AccountSettings/Resources/AccountSettingsResource.php`
- `app/User/Modules/AccountSettings/Tests/Crud/AccountSettingsSummaryTest.php`

**validateCommand:** `rtk php artisan test --compact app/User/Modules/AccountSettings/Tests`

**Review:** claude

### TASK-018: Mostrar el estado en la tarjeta de resumen de Configuración

Agregar `status: "active" | "suspended"` a `AccountSettings` y mostrarlo de solo lectura en `AccountSettingsSummaryCard` con el `Badge` de shadcn ('Activa'/'Suspendida'), con tokens del tema y sin controles para cambiarlo.

**Type:** feature · **Effort:** S · **Agent:** react-vite-tailwind-engineer · **Priority:** P2

**Depends on:** TASK-017

**Acceptance Criteria:**

1. La tarjeta muestra 'Activa' para una cuenta activa, en modo claro y oscuro.
2. No hay ningún control que envíe el estado; `npm run types`, ESLint y Prettier pasan.

**writeScope:**

- `resources/js/modules/setting/modules/accountSettings/types/accountSettings.ts`
- `resources/js/modules/setting/modules/accountSettings/components/AccountSettingsSummaryCard.tsx`

**validateCommand:** `rtk npm run types && rtk npx eslint resources/js/modules/setting/modules/accountSettings && rtk npx prettier --check resources/js/modules/setting/modules/accountSettings`

**Review:** claude

### TASK-019: AccountStatusSeeder con cuentas suspendidas y reactivadas

Crear `App\User\Database\Seeders\AccountStatusSeeder` y registrarlo en `DatabaseSeeder` después de `TrustedDeviceSeeder` y antes de `PostSeeder`. Entre las cuentas que no son administradoras ni Angel: ~3% suspendidas (fila `suspended` con motivo de ejemplo y un administrador sembrado al azar como `changed_by`) y ~3% con historia suspendida→reactivada (dos filas, la segunda posterior). No usa `AccountStatusService` ni escribe `activity_log` (si no, `ActivityLogSeeder` se saltaría todo); a las suspendidas les revoca los dispositivos activos con soft delete y un `TrustedDeviceEvent` `RevokedAll` creado con la factory, sin `record()`. No hace nada si `account_status_changes` ya tiene filas.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-003, TASK-004

**Acceptance Criteria:**

1. Tras sembrar hay cuentas suspendidas y reactivadas con filas coherentes con `users.status`, y Angel y los administradores siguen activos.
2. Ninguna cuenta suspendida conserva dispositivos confiables activos; `activity_log` sigue vacío al terminar este seeder.
3. Correrlo dos veces no duplica filas.

**writeScope:**

- `app/User/Database/Seeders/AccountStatusSeeder.php` (nuevo)
- `app/User/Modules/AccountStatus/Tests/Seeders/AccountStatusSeederTest.php` (nuevo)
- `database/seeders/DatabaseSeeder.php`

**validateCommand:** `rtk php artisan test --compact app/User/Modules/AccountStatus/Tests/Seeders/AccountStatusSeederTest.php`

**Review:** claude

### TASK-020: ActivityLogSeeder refleja los cambios de estado sembrados

Agregar `seedAccountStatusActivity()` a `ActivityLogSeeder`: por cada `AccountStatusChange`, `admin.account_suspended` o `admin.account_reactivated` con la fila como sujeto, `changed_by` como causer, `target_user_id` como única propiedad y la fecha real de la fila. Nunca incluye el motivo.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-007, TASK-019

**Acceptance Criteria:**

1. Hay un registro de actividad por cada fila de `account_status_changes`, con su evento según el estado.
2. Ningún registro contiene la clave `reason` ni el texto del motivo; las pruebas existentes del seeder siguen pasando.

**writeScope:**

- `app/Modules/ActivityLog/Database/Seeders/ActivityLogSeeder.php`
- `app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php`

**validateCommand:** `rtk php artisan test --compact app/Modules/ActivityLog/Tests/Seeders/ActivityLogSeederTest.php`

**Review:** claude

### TASK-021: Actualizar CLAUDE.md y cerrar los gates

Documentar en `CLAUDE.md`: el submódulo `User/Modules/AccountStatus` (enum, tabla, servicio, bloqueo en login/2FA/dispositivos/panel, middleware, revocación de dispositivos), el rol `admin-users-moderator` y sus permisos en la tabla de roles y en la lista de los comandos, los eventos nuevos del activity log, la clave `account_status_change` de la morph map y `AccountStatusSeeder` en el orden de seeders. Correr Pint, Rector (clear-cache → dry → aplicar), PHPStan y la suite completa.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P3

**Depends on:** TASK-008, TASK-009, TASK-010, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-018, TASK-020

**Acceptance Criteria:**

1. CLAUDE.md describe el estado de cuenta, el rol nuevo, los eventos y el seeder, sin contradecir SOC-44/SOC-54 (que decían que el estado quedaba para SOC-35).
2. Pint, Rector dry-run, PHPStan y `composer test` pasan sin errores.

**writeScope:**

- `CLAUDE.md`

**validateCommand:** `rtk vendor/bin/pint --dirty --format agent && rtk composer rector-clear-cache && rtk composer rector-dry && rtk composer phpstan && rtk composer test`

**Review:** claude

## Failure Modes

- Suspensión entre el paso de contraseña y el reto 2FA: lo cubre TASK-010 con la subclase de `TwoFactorLoginRequest`.
- Sesión remember-me o abierta en otra pestaña: lo cubre el middleware de TASK-011 en `web` y `admin` (TASK-012 lo prueba).
- Dos administradores cambian el estado a la vez: `lockForUpdate` y la relectura del estado en TASK-008; el segundo recibe `LogicException`, que TASK-014 muestra como error.
- Falla a mitad de la suspensión (p. ej. al revocar dispositivos): la transacción revierte la fila, el estado y la actividad.
- Base existente sin el rol nuevo: hay que volver a correr `AdminAuthorizationSeeder` en el deploy (documentado en TASK-021).
- El seeder escribe actividad antes de `ActivityLogSeeder` y este se salta todo: TASK-019 lo prohíbe explícitamente.

## Ship Cut

TASK-001..013 entregan el núcleo (persistencia, permisos, servicio y todos los bloqueos) aunque todavía no haya UI. TASK-014 es el mínimo para usarlo desde el panel. TASK-015..020 son mejoras independientes entre sí. TASK-021 cierra.

## Test Coverage Map

| Área | Tareas |
| --- | --- |
| Migración/backfill | TASK-002 |
| Modelo e invariantes | TASK-003, TASK-004 |
| Cambios autorizados y denegados | TASK-006, TASK-014 |
| Servicio, motivo y activity log | TASK-008, TASK-020 |
| Login con contraseña y dispositivo confiable | TASK-009 |
| Reto 2FA | TASK-010 |
| Sesiones existentes (web, remember-me, JSON, admin) | TASK-012 |
| Acceso al panel | TASK-013 |
| Visualización en panel y en Configuración | TASK-015, TASK-016, TASK-017, TASK-018 |
| Seeders | TASK-019, TASK-020 |

## Execution Summary

- Tareas: 21
- Ruta crítica: TASK-001 → TASK-002 → TASK-003 → TASK-004 → TASK-011 → TASK-012 → TASK-021
- Capas paralelas:

  1. TASK-001, TASK-005
  2. TASK-002
  3. TASK-003
  4. TASK-004, TASK-007
  5. TASK-006, TASK-008, TASK-009, TASK-010, TASK-011, TASK-013, TASK-015, TASK-016, TASK-017, TASK-019
  6. TASK-012, TASK-014, TASK-018, TASK-020
  7. TASK-021

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-002 |
| TASK-004 | TASK-001, TASK-003 |
| TASK-005 | — |
| TASK-006 | TASK-004, TASK-005 |
| TASK-007 | TASK-003 |
| TASK-008 | TASK-004, TASK-007 |
| TASK-009 | TASK-001, TASK-004 |
| TASK-010 | TASK-001, TASK-004 |
| TASK-011 | TASK-004 |
| TASK-012 | TASK-011, TASK-013 |
| TASK-013 | TASK-004 |
| TASK-014 | TASK-006, TASK-008 |
| TASK-015 | TASK-004 |
| TASK-016 | TASK-004 |
| TASK-017 | TASK-004 |
| TASK-018 | TASK-017 |
| TASK-019 | TASK-003, TASK-004 |
| TASK-020 | TASK-007, TASK-019 |
| TASK-021 | TASK-008, TASK-009, TASK-010, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-018, TASK-020 |
