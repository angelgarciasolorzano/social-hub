# Auditoría de la suite Admin (TASK-005)

Alcance: los 45 archivos de `app/Modules/Admin/Tests` (43 de tests y 2 de soporte), 304 casos tras TASK-001. Duración en serie en la última corrida completa: 16,7 s.

Criterio, según SOC-59 y `.ai/rules/backend.md` ("Keep backend tests focused"): se propone eliminar un caso solo cuando otro test ya detecta exactamente el mismo fallo, y se cita ese test. No se fusionan comportamientos distintos en un test más grande, aunque compartan setup: la regla del proyecto prefiere varios tests pequeños. Autorización, seguridad, validación y efectos persistidos que pueden fallar por separado se conservan.

## Resumen

| Tipo | Hallazgos | Casos afectados |
| --- | --- | --- |
| Redundante (se elimina) | R1–R12 | 18 casos |
| Setup innecesario o duplicado (se simplifica, sin quitar casos) | S1–S4 | 13 archivos |
| Ubicación incorrecta (se mueve) | U1 | 1 caso |
| Revisado y conservado | C1–C10 | — |

Con R1–R12 la suite Admin pasaría de 304 a 286 casos. El ahorro de tiempo es pequeño (cada caso cuesta unos 55 ms, casi todo en `AdminAuthorizationSeeder` y la transacción), así que el beneficio principal es tener menos tests que mantener.

## Redundantes

| Id | Test a eliminar | Lo cubre | Motivo |
| --- | --- | --- | --- |
| R1 | `Database/AdminUserSeederTest.php` · "grants the complete admin permission set to Angel" | Mismo archivo · "assigns Angel backup operations, user moderation and all reader roles while preserving existing assignments" | El primero comprueba con `hasAllPermissions` un subconjunto de la lista **exacta** de permisos que ya fija el segundo. |
| R2 | `Database/AdminUserSeederTest.php` · "does not assign the user provisioner role to Angel automatically" | El mismo test de R1 | La lista exacta de roles de Angel no incluye `admin-users-provisioner`; si el seeder lo agregara, ese test fallaría. |
| R3 | `Database/AdminUserSeederRandomAdminsTest.php` · "gives Angel the base role, backup operator role, user moderator role, and every reader role" | El mismo test de R1 | Comprueba la misma lista exacta de roles de Angel; el de R1 además prueba la idempotencia y que se conservan roles ajenos. |
| R4 | `Filament/SuspendedAdminSessionTest.php` · "keeps the panel session of an active administrator" | `Filament/AdminPanelAccessTest.php` · "allows authorized administrators to enter the admin panel" | Mismo setup (rol `admin` + secreto MFA) y misma comprobación: `GET /admin` responde 200. `signInPanelAdministrator()` queda con un solo uso (ver S4). |
| R5 | `Policies/UserPanelAccessTest.php` · "allows the administrator back into the panel once reactivated" | Mismo archivo · "allows verified users with admin.access into the admin panel" y "denies a suspended administrator even with admin.access" | `canAccessPanel()` lee `status` del modelo; "reactivado" es `status = active`, el mismo camino del primer test. La reactivación real (servicio, historial) se prueba en AccountStatus. |
| R6 | `Commands/AdminBootstrapCommandTest.php` · "accepts the promoted user as their own actor" | Mismo archivo · "promotes a verified user to the base and all reader roles" (usa al promovido como actor y exige `SUCCESS`) y "records the bootstrap with the actor and the assigned roles" (fija `causer_id`) | Entre los dos ya cubren que el promovido es un actor válido y que el actor queda como causer. |
| R7 | `Commands/AdminRolePermissionsCommandTest.php` · "grants each backups permission to a role in the admin guard" (dataset de 3) | Mismo archivo · "grants a cataloged permission to a role and updates permission checks" y "uses the admin guard when matching role and permission names exist in web" | El comando resuelve cualquier permiso con `AdminPermission::tryFrom()` por el mismo camino; repetirlo con los tres permisos de backups no ejercita nada nuevo, y el guard ya lo fija el segundo test. |
| R8 | `Commands/AdminUserRoleCommandTest.php` · "grants Posts and Dashboard reader roles without panel entry" (dataset de 2), "grants the backups reader role from the admin guard" y "grants all backup operations through the backup operator role" | Mismo archivo · "grants a catalog role and exposes its permissions" y "grants panel entry through the admin role"; `Database/AdminAuthorizationSeederTest.php` · "seeds each role with only its intended permissions" | El comando es genérico (busca el rol en el enum y llama a `assignRole`). Los dos tests que quedan cubren el caso sin acceso al panel y el que sí lo da; qué permisos trae cada rol ya lo fija el test del seeder con listas exactas. 4 casos. |
| R9 | `Commands/AdminUserRoleCommandTest.php` · "revokes Posts and Dashboard reader roles without changing panel access" (dataset de 2) | Mismo archivo · "revokes only the selected role and its permissions" | Mismo camino de revocación con otro rol lector; el test que queda ya comprueba que se conserva `admin.access`. |
| R10 | `Filament/Resources/Posts/Pages/ViewPostTest.php` · "denies every post mutation in the panel even to a posts reader who wrote the post" | `app/Modules/Post/Tests/Policies/PostPolicyTest.php` · "denies :dataset to a posts reader who wrote the post" | `PostResource` no sobrescribe ningún `can*()` (SOC-57) y Filament delega en `PostPolicy`; los dos tests comprueban las mismas habilidades sobre la misma política. Distinto de `CommentResource` (ver C3). |
| R11 | `Filament/Resources/Users/RelationManagers/TrustedDevicesRelationManagerTest.php` · "uses ten rows as the trusted device pagination default" | Mismo archivo · "paginates only the selected users trusted devices" | Ese test crea 11 dispositivos y comprueba que la página 1 deja fuera uno y la página 2 tiene uno solo, lo que ya fija el tamaño de 10. |
| R12 | `Models/UserRoleTest.php` · "assigns admin guard roles and resolves their permissions" | `Database/AdminAuthorizationSeederTest.php` · "seeds each role with only its intended permissions" (roles y permisos ausentes en `web`) y "gives the activity reader only the activity log permissions" (permiso resuelto a través del rol en el guard `admin`) | Comprueba lo mismo con otro rol lector. |

## Setup innecesario o duplicado

- **S1 — Parches contra las imágenes aleatorias de las factories (quedan muertos tras TASK-002/003).** `clearMediaCollection()` en `Posts/Pages/ListPostsTest.php` (líneas 222 y 238), `Posts/Pages/ViewPostTest.php` (104 y 130), `Users/RelationManagers/PostsRelationManagerTest.php` (69 y 86) y `Users/Pages/ViewUserTest.php` (105 y 106). En los tests del fallback sin imagen, el `Storage::fake()` que acompañaba esas llamadas también sobra, porque ya no se escribe ningún archivo. 4 archivos.
- **S2 — Helpers duplicados de backups.** `grantBackupManagementPermission()` (`Backups/BackupManagementServiceTest.php`) y `grantPermissionForBackupPage()` (`Filament/Pages/Backups/BackupManagementTest.php`) crean un rol de prueba con un único permiso; `storeBackupManagementArchive()` y `storeFilamentBackupArchive()` guardan un archivo en el disco `backups`. Unificarlos en `Tests/Support/` (o en `tests/Pest.php`, como pide `.ai/rules/app.md`). El patrón "rol de prueba con un permiso" se repite además en `Policies/UserPolicyTest.php` con `Role::findOrCreate`; el mismo helper serviría ahí. 3 archivos.
- **S3 — Helpers duplicados de invitaciones.** `createAdminInvitationForAcceptance()` (`Invitations/AcceptAdminInvitationTest.php`) y `createAdminInvitationForRoute()` (`Invitations/AdminInvitationRouteTest.php`) generan el mismo par invitación + token en claro; solo difieren en cómo reciben el email. `createAdminInvitation()` (`Models/AdminInvitationTest.php`) es la versión sin token. Unificar en un helper con email y usuario opcionales. 2 archivos.
- **S4 — `signInPanelAdministrator()`** (`Filament/SuspendedAdminSessionTest.php`) repite `AdminFilamentTestContext::actingAsAdminWithRoles()` más el secreto MFA. Tras R4 queda con un solo uso; puede usar el contexto compartido. 1 archivo (va junto con R4).

## Ubicación incorrecta

- **U1** — `Models/UserRoleTest.php` · "omits authentication secrets from array and json serialization" prueba el `$hidden` del modelo `User`, no nada de Admin, y es el único test de esa garantía en el proyecto. Se conserva y se mueve a `app/User/Tests/Models/`. Con R12 y U1, `UserRoleTest.php` queda solo con "does not assign roles or permissions through mass assignment", que sí es de Admin.

## Revisado y conservado

- **C1 — Registro de componentes** (`ViewCommentTest` "registers only the hidden direct detail page", `PostsRelationManagerTest` y `TrustedDevicesRelationManagerTest` "registers … relation", `ViewActivityLogTest` "only registers the list and detail pages", `RegistrationDashboardTest` "discovers the registration widgets…", `CommentActivityOverviewTest` "registers the activity widget…", `RepliesRelationManagerTest` "renders the replies manager on comment details"). Los tests de renderizado montan el relation manager o el widget directamente con Livewire, así que no prueban que esté registrado en el recurso; además "only registers the list and detail pages" fija que no existen páginas de creación o edición.
- **C2 — Solo lectura de tablas y acciones** (`ListPostsTest` "keeps the Posts resource index read only", `CommentsRelationManagerTest`, `PostsRelationManagerTest`, `RepliesRelationManagerTest`, `AccountStatusChangesRelationManagerTest`, `TrustedDevicesRelationManagerTest` "defines trusted device columns without mutation actions"). Cada uno es una tabla distinta y puede ganar una acción por separado.
- **C3 — `ViewCommentTest` "denies every comment mutation in the panel…"**: `CommentResource` fija sus `can*()` en `false` mientras `CommentPolicy` permite `update` al autor (lo comprueba el propio test); es una decisión independiente de la política.
- **C4 — Seguridad y límites**: `UserResourceTest` (rutas directas de crear, editar y borrar), `ViewUserTest` "does not expose two factor secrets…" (revisa el HTML completo, que también incluye pestañas y widgets, no solo la lista blanca de campos de "limits the user detail to allowlisted fields"), `UserPolicyTest` (las dos pruebas de mutaciones denegadas usan roles distintos: lector y moderador), `UserPanelAccessTest` (verificado, otro panel, suspendido) y `AdminPanelAccessTest` (capa HTTP, distinta de `canAccessPanel()`).
- **C5 — Conteo de consultas** (`ListActivityLogsTest`, `ListUsersAccountStatusTest`, `CommentsRelationManagerTest` "does not load replies…", `PostsRelationManagerTest` "does not load comment threads…").
- **C6 — Comandos Admin**: validación del actor, catálogo sin sembrar, protección del último `admin.access` y registro de actividad son reglas distintas aunque compartan setup. "prevents revoking admin.access…" y "does not record a revocation blocked…" verifican dos efectos diferentes y se dejan separados, según la regla de tests pequeños.
- **C7 — Backups**: la configuración fijada en `BackupConfigurationTest` y `AdminBackupScheduleTest` protege decisiones de seguridad (disco S3 privado, cifrado, verificación y retención). Los permisos se prueban tanto en el servicio como en la página, que son dos puntos de entrada distintos.
- **C8 — Invitaciones en la página y en el servicio**: `AcceptAdminInvitationTest` repite parte de las comprobaciones de persistencia de `AdminInvitationServiceTest` (credenciales intactas y email verificado), pero prueba el cableado de la página (redirección, formulario y token que el cliente no puede cambiar). Se conservan; como mucho se podrían recortar assertions, sin quitar casos.
- **C9 — `AdminBackupAuthorizationEnumsTest`**: "unlisted backup RBAC values cannot be resolved" se apoya en `tryFrom()`, es decir, en el lenguaje, pero documenta la decisión de no tener restauración. Se conserva; es barato y no tiene base de datos.
- **C10 — `RegistrationDashboardTest`**: cada rango (7, 30 y 90 días, 12 meses) agrupa de forma distinta (día, semana o mes); no son repeticiones.

## Observación de infraestructura (no se propone cambio)

Casi todos los tests de Admin llaman a `$this->seed(AdminAuthorizationSeeder::class)`. Sembrar el catálogo una sola vez por proceso (con `$seeder` en `TestCase`, de modo que `RefreshDatabase` lo incluya en el `migrate:fresh` inicial) quitaría ese costo, pero afectaría a todos los tests del proceso, y en paralelo cada worker mezcla suites. Rompería los tests que exigen un catálogo vacío ("requires the Admin authorization catalog to be seeded first", los `assertDatabaseCount('roles', …)`) y el ahorro, unos 10–15 ms por test, es menor que el riesgo.

## Propuesta de tareas para TASK-009 (máximo 3 archivos cada una)

1. R1, R2, R3 — los dos archivos de seeders de Admin.
2. R4 + S4, R5 — `SuspendedAdminSessionTest.php` y `UserPanelAccessTest.php`.
3. R6, R7 — `AdminBootstrapCommandTest.php` y `AdminRolePermissionsCommandTest.php`.
4. R8, R9 — `AdminUserRoleCommandTest.php`.
5. R10, R11 — `ViewPostTest.php` y `TrustedDevicesRelationManagerTest.php`.
6. R12 + U1 — `UserRoleTest.php` y el nuevo `app/User/Tests/Models/UserSerializationTest.php`.
7. S1 — `ListPostsTest.php`, `ViewPostTest.php` (depende de 5), `PostsRelationManagerTest.php`; `ViewUserTest.php` en una tarea aparte por el límite de archivos.
8. S2 — los dos archivos de backups y el helper compartido.
9. S3 — los dos archivos de invitaciones (el helper queda en uno de ellos o en `tests/Pest.php`).
