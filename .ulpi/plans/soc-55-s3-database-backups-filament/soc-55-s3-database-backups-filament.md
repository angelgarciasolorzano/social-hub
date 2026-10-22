# Plan: SOC-55: Backups cifrados de base de datos en S3 con página Filament propia

## Overview

Implementar backups MySQL cifrados en un disco S3 dedicado y privado, con ejecución automática y manual en cola, monitoreo, una página Filament propia protegida por RBAC y auditoría, documentación y un simulacro de restauración aislada.

## Scope Challenge

Se conserva el alcance de SOC-55 (HOLD) y se confirma la decisión del usuario: no usar el plugin Filament. El panel ya descubre páginas desde app/Modules/Admin/Filament/Pages; los permisos y roles viven en enums/seeders del módulo Admin; ActivityLogger requiere causer explícito. Laravel ya abstrae discos, pero su driver S3 requiere league/flysystem-aws-s3-v3. MediaLibrary está instalado para archivos asociados a modelos; no se usará para el ZIP de backup salvo que durante implementación aparezca una necesidad concreta.

**Mode:** HOLD · **Default review:** codex

## Prerequisites

- SOC-53 y SOC-54 están cerradas, como requiere el issue.
- Composer debe validar Spatie Backup v10 y league/flysystem-aws-s3-v3 con PHP 8.5 y Laravel 13.35.
- El entorno de ejecución debe tener MySQL y mysqldump con soporte de transacción única; los tests existentes usan MySQL en testing.
- Operaciones debe proporcionar un bucket S3 privado con versionado, credenciales dedicadas, IAM restringido, una regla lifecycle para versiones no actuales y delete markers, mail de notificaciones, scheduler y worker de cola.
- Para aceptar la restauración hace falta un entorno MySQL scratch independiente y acceso de staging al objeto S3 cifrado.

## Non-Goals

- No instalar ni integrar shuvroroy/filament-spatie-laravel-backup.
- No integrar MediaLibrary con archivos ZIP; confirmar durante la implementación que el uso de Laravel Storage cubre el flujo y detenerse si aparece una necesidad diferente.
- No respaldar imágenes ni archivos de storage.
- No añadir restauración ni borrado manual al panel.
- No provisionar bucket, IAM o políticas AWS mediante IaC: no se encontró una estructura Terraform/CDK/CloudFormation en el repo; el alcance documenta los requisitos para Operaciones.

## Contracts

- El bucket permanece privado y distinto del almacenamiento de archivos de usuario. Laravel usa un disco dedicado de backups y el adaptador Flysystem S3 requerido.
- El IAM de la aplicación permite listar el bucket y leer, escribir y borrar objetos limitados al prefijo de backups. Con versionado, DeleteObject crea un delete marker; S3 Lifecycle purga versiones no actuales y marcadores vencidos después de 30 días, por lo que la app no necesita DeleteObjectVersion.
- La política Spatie conserva 7 backups diarios, 4 semanales y 6 mensuales; la tarea de configuración debe verificar la semántica real de DefaultStrategy v10.
- La salud que se presenta es disponibilidad por objeto y salud del destino según antigüedad máxima y almacenamiento total; no se inventa una señal de salud por archivo que el monitor no proporciona.
- La vista, las acciones y el servicio comprueban permisos en servidor. El lector solo ve; la descarga requiere permiso, contraseña actual del guard admin y URL S3 firmada durante cinco minutos.
- El job transporta el id del actor hasta el worker, registra creación solo tras completar escritura, y registra para auditoría filename y origen; nunca contraseña, clave, token o URL.
- La ejecución automática usa el mismo job que la acción manual, con origen programado y causer anónimo. El worker debe usar una conexión no sync; el ejemplo del repo ya usa database.
- La restauración se documenta y ensaya en MySQL aislado; el panel no restaura.

## Existing Code Leverage

- config/filesystems.php ya contiene un disco S3 genérico; se reutiliza el driver y se separa el disk/credential set de backups.
- AdminPanelProvider ya descubre app/Modules/Admin/Filament/Pages y AdminServiceProvider ya está registrado.
- AdminPermission, AdminRole, AdminAuthorizationSeeder, AdminBootstrapCommand y AdminUserSeeder son los puntos existentes de RBAC.
- AdminUserRoleCommand y AdminRolePermissionsCommand convierten argumentos a partir de los enums; las nuevas capacidades se validan con pruebas del comando.
- AuthServiceProvider y ActivityLogServiceProvider son el patrón vigente para registrar Schedule en un provider del módulo.
- ActivityLogAdminEvent, ActivityLogEventCatalog y ActivityLogger cubren eventos de Admin; ActivityLogger recibe el causer explícitamente y filtra propiedades sensibles.
- Los tests de Admin viven en app/Modules/Admin/Tests y ya disponen de AdminFilamentTestContext; el suite de ActivityLog vive en su módulo.
- README.md, .env.example, CLAUDE.md y AGENTS.md ya existen; docs/development es el directorio vigente para guías operativas.

## Tasks

### TASK-001: Agregar Spatie Backup y el adaptador S3 de Flysystem

Instalar `spatie/laravel-backup` v10 y `league/flysystem-aws-s3-v3` como dependencias directas aprobadas por SOC-55. Validar la resolución con PHP 8.5 y Laravel 13.35. No instalar el plugin Filament. Durante la implementación, confirmar que el archivo ZIP se maneja como objeto de un disco Laravel; no integrar MediaLibrary salvo que aparezca una necesidad concreta y se solicite revisar ese cambio.

**Type:** chore · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** ninguna

**Acceptance Criteria:**
- Composer resuelve las dos dependencias con el runtime PHP 8.5 y Laravel 13.35 del proyecto, y el paquete registra `backup:run`, `backup:clean` y `backup:monitor`.
- El adaptador S3 queda declarado directamente porque Laravel requiere el paquete Flysystem S3 para usar un disco `s3`; el plugin Filament no aparece en Composer.
- Si la resolución resulta incompatible, la tarea falla sin sustituir versiones por un plugin o paquete no aprobado.

**Write scope:**
- `composer.json` — Declarar Spatie Backup y el adaptador Flysystem S3; no agregar el plugin ni MediaLibrary.
- `composer.lock` — Fijar el árbol resuelto y compatible de dependencias.

**Validate command:** `rtk composer validate --no-check-publish && rtk composer show spatie/laravel-backup && rtk composer show league/flysystem-aws-s3-v3`

**Review:** codex

### TASK-002: Configurar el disco S3 privado y los backups de base de datos

Agregar un disco S3 dedicado, separado del disco S3 genérico y de los archivos de usuario, y publicar/configurar `config/backup.php`. El backup debe incluir solo MySQL, usar cifrado obligatorio y verificación del ZIP, y fijar una retención explícita. Usar variables de entorno propias del backup en lugar de reutilizar inadvertidamente las credenciales genéricas. La opción de transacción única para mysqldump se separa en TASK-018 porque Spatie la obtiene de `config/database.php`.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-001

**Acceptance Criteria:**
- El disco dedicado usa `driver=s3`, credenciales y bucket de backup propios, y no configura visibilidad pública ni cambia `FILESYSTEM_DISK`.
- La configuración Spatie apunta al disco dedicado, respalda únicamente la conexión MySQL (no la conexión SQLite local por defecto), excluye archivos, cifra y verifica el ZIP.
- La política de limpieza define 7 copias diarias, 4 semanales y 6 mensuales; la prueba cubre que el disco público/local no quede como destino de backup.

**Write scope:**
- `config/filesystems.php` — Declarar el disco privado dedicado con variables `BACKUP_S3_*`.
- `config/backup.php` — Configurar base de datos, cifrado, verificación, retención, limpieza y salud según Spatie Backup v10.
- `app/Modules/Admin/Tests/Backups/BackupConfigurationTest.php` — Probar el destino privado, la configuración de base de datos y los límites de retención.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Backups/BackupConfigurationTest.php`

**Review:** codex

### TASK-003: Agregar los permisos y el rol de lectura al catálogo Admin

Añadir a los enums `AdminPermission` y `AdminRole` los valores de SOC-55. Agregar una prueba pequeña de catálogo que pinne los valores públicos y evite que una edición posterior cambie accidentalmente los nombres usados por RBAC.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** ninguna

**Acceptance Criteria:**
- El catálogo contiene exactamente `admin.backups.view`, `admin.backups.create`, `admin.backups.download` y el rol `admin-backups-reader`.
- La prueba comprueba los valores de enum esperados, incluida la distinción entre permisos de lectura, creación y descarga.
- Un valor no catalogado sigue sin convertirse en un permiso o rol válido.

**Write scope:**
- `app/Modules/Admin/Enums/AdminPermission.php` — Declarar los tres permisos granulares del área de backups.
- `app/Modules/Admin/Enums/AdminRole.php` — Declarar el rol `admin-backups-reader`.
- `app/Modules/Admin/Tests/Enums/AdminBackupAuthorizationEnumsTest.php` — Fijar los identificadores del catálogo y sus valores.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Enums/AdminBackupAuthorizationEnumsTest.php`

**Review:** codex

### TASK-004: Sembrar el rol lector con permiso exclusivo de consulta

Extender `AdminAuthorizationSeeder` para crear el rol nuevo y asignarle únicamente `admin.backups.view`. Actualizar la prueba del catálogo para cubrir exactamente sus permisos y la idempotencia del seeder, conservando el comportamiento existente que no restablece permisos revocados en roles ya creados.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-003

**Acceptance Criteria:**
- `admin-backups-reader` existe con el guard `admin` y recibe solo `admin.backups.view`.
- El rol lector no recibe `admin.access`, `admin.backups.create` ni `admin.backups.download`.
- Sembrar dos veces crea un único rol/permisos y no restaura permisos manualmente revocados en otros roles.

**Write scope:**
- `app/Modules/Admin/Database/Seeders/AdminAuthorizationSeeder.php` — Mapear el nuevo rol a su único permiso de lectura.
- `app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php` — Comprobar guard, permisos exactos, idempotencia y ausencia de privilegios adicionales.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Database/AdminAuthorizationSeederTest.php`

**Review:** codex

### TASK-005: Incluir el rol lector en el bootstrap del primer administrador

Actualizar la lista explícita de roles de `AdminBootstrapCommand` para que el primer administrador reciba el nuevo rol lector, siguiendo el contrato existente de asignar todas las secciones de lectura al bootstrap. Ajustar la prueba del comando, incluida la comprobación de que un catálogo incompleto no produce una asignación parcial.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**
- Un bootstrap válido asigna `admin-backups-reader` y permite consultar backups.
- Si falta el rol requerido en el guard `admin`, el comando falla antes de asignar roles al usuario.
- Los administradores existentes no reciben este rol automáticamente; su asignación posterior se hace por `admin:user-role`.

**Write scope:**
- `app/Modules/Admin/Console/Commands/AdminBootstrapCommand.php` — Añadir el rol lector a la lista de bootstrap.
- `app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php` — Verificar el rol asignado y el fallo sin cambios cuando falta un rol requerido.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Commands/AdminBootstrapCommandTest.php`

**Review:** codex

### TASK-006: Extender los roles lectores de los seeders locales y de pruebas

Añadir el nuevo lector a `AdminUserSeeder` y actualizar sus pruebas. Angel conserva todos los roles lectores; los administradores aleatorios conservan el rango establecido por AGENTS.md de uno a cuatro roles lectores, elegidos del catálogo ampliado, y los roles existentes se preservan.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**
- En `local` y `testing`, Angel recibe el rol lector de backups y su permiso de consulta.
- Los usuarios aleatorios siguen recibiendo de uno a cuatro roles lectores, seleccionados del catálogo actual de cinco; no se amplía el máximo de cuatro.
- El seeder sigue sin ejecutarse fuera de `local`/`testing`, no duplica administradores al repetirse y preserva asignaciones existentes.

**Write scope:**
- `app/Modules/Admin/Database/Seeders/AdminUserSeeder.php` — Incluir el rol lector y conservar el límite de 1–4 roles aleatorios.
- `app/Modules/Admin/Tests/Database/AdminUserSeederTest.php` — Probar la asignación completa a Angel y la preservación de roles.
- `app/Modules/Admin/Tests/Database/AdminUserSeederRandomAdminsTest.php` — Probar el rango 1–4, la selección del catálogo y la idempotencia.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Database/AdminUserSeederTest.php app/Modules/Admin/Tests/Database/AdminUserSeederRandomAdminsTest.php`

**Review:** codex

### TASK-007: Probar los comandos genéricos de asignación para el catálogo nuevo

`AdminUserRoleCommand` y `AdminRolePermissionsCommand` ya convierten sus argumentos mediante `AdminRole` y `AdminPermission`; verificar con sus pruebas que admiten los nuevos casos del catálogo sin introducir listas paralelas ni cambios innecesarios en los comandos.

**Type:** test · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004

**Acceptance Criteria:**
- `admin:user-role grant` acepta `admin-backups-reader` y registra la asignación bajo el guard `admin`.
- `admin:role-permissions grant` acepta cada permiso nuevo para un rol autorizado por el operador.
- Los argumentos desconocidos siguen fallando sin crear roles/permisos ni mutar asignaciones.

**Write scope:**
- `app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php` — Probar la asignación del nuevo rol y rechazo de roles desconocidos.
- `app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php` — Probar el uso de permisos de backup y el rechazo de permisos desconocidos.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Commands/AdminUserRoleCommandTest.php app/Modules/Admin/Tests/Commands/AdminRolePermissionsCommandTest.php`

**Review:** codex

### TASK-008: Registrar los eventos de auditoría de backups

Agregar eventos de creación y descarga a `ActivityLogAdminEvent`. El catálogo de actividad ya enumera automáticamente los casos de ese enum; actualizar la prueba existente para fijar que ambos eventos se encuentran y se presentan con etiquetas legibles.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** ninguna

**Acceptance Criteria:**
- El catálogo incluye `admin.backup_created` y `admin.backup_downloaded` dentro del log `admin`.
- La prueba confirma las etiquetas y opciones de filtro generadas automáticamente.
- Los eventos no requieren ni admiten contraseñas, claves, tokens o URLs firmadas en sus propiedades.

**Write scope:**
- `app/Modules/ActivityLog/Enums/Events/ActivityLogAdminEvent.php` — Declarar los eventos de creación y descarga.
- `app/Modules/ActivityLog/Tests/Support/ActivityLogEventCatalogTest.php` — Verificar la integración de los eventos en el catálogo y sus etiquetas.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/ActivityLog/Tests/Support/ActivityLogEventCatalogTest.php`

**Review:** codex

### TASK-009: Ejecutar y auditar backups mediante un job en cola

Crear un job propio del módulo Admin que ejecute el backup de base de datos aprobado y reciba el id del actor que lo solicitó. El mismo job acepta un origen programado sin actor. Registrar creación, nombre de archivo y origen solo cuando el paquete haya terminado de copiar el archivo al disco; ante error, propagar la excepción para que el job falle y no registrar una creación exitosa.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-002, TASK-008

**Acceptance Criteria:**
- El job ejecuta el backup únicamente con `--only-db`, usa el disco S3 configurado y puede despacharse sin bloquear una petición web.
- Al completar, registra el nombre del archivo y el actor manual; un job programado registra el origen del sistema sin atribuirlo a otro usuario.
- Si falla el dump o la escritura S3, o falta la passphrase, el job falla cerrado y no crea un evento `admin.backup_created` exitoso.

**Write scope:**
- `app/Modules/Admin/Jobs/RunDatabaseBackup.php` — Implementar la ejecución asíncrona, propagación del actor y auditoría de éxito.
- `app/Modules/Admin/Tests/Backups/RunDatabaseBackupTest.php` — Probar la ejecución DB-only, causer/origen y ausencia de auditoría exitosa en fallos.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Backups/RunDatabaseBackupTest.php`

**Review:** codex

### TASK-010: Implementar el servicio de consulta, cola y descarga temporal

Crear un servicio de administración que liste los objetos del destino de backups en orden descendente, exponga metadatos y disponibilidad, despache el job y emita URLs temporales S3. El servicio recibe al usuario admin explícitamente, vuelve a verificar los permisos correspondientes y valida que el archivo pertenezca al disco configurado. Para descargar exige la contraseña actual del usuario admin, valida el objeto y genera una URL firmada de cinco minutos; audita usuario y nombre del archivo, nunca la URL ni la contraseña.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-002, TASK-008, TASK-009

**Acceptance Criteria:**
- El listado devuelve fecha, tamaño, disco y disponibilidad del objeto, ordenado del más reciente al más antiguo; la salud global refleja los checks configurados de antigüedad y almacenamiento.
- Crear backup solo encola `RunDatabaseBackup`; descargar requiere `admin.backups.download`, una contraseña válida y un archivo existente del disco dedicado.
- Una contraseña incorrecta, una clave fuera del destino o un objeto inexistente no genera URL ni evento de descarga; una descarga válida produce URL temporal de cinco minutos y auditoría sin secretos.

**Write scope:**
- `app/Modules/Admin/Services/BackupManagementService.php` — Centralizar consulta de destinos, despacho y descarga/autorización/auditoría.
- `app/Modules/Admin/Tests/Backups/BackupManagementServiceTest.php` — Cubrir listado, cola, URL temporal, permisos, contraseña y fallos.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Backups/BackupManagementServiceTest.php`

**Review:** codex

### TASK-011: Programar backup, limpieza y monitoreo

Usar el `AdminServiceProvider` ya registrado y el patrón de scheduling de `AuthServiceProvider`/`ActivityLogServiceProvider`. Programar el job de backup diario y un monitor independiente fuera de horas de uso. Solo después de que el backup finalice correctamente, encolar un job separado para ejecutar `backup:clean`; no programar la limpieza a una hora fija ni volver a ejecutar el backup si falla la limpieza. El monitor envía las alertas configuradas por correo cuando la copia es antigua o excede el límite de almacenamiento.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-009

**Acceptance Criteria:**
- El scheduler encola diariamente el job de backup y programa el monitoreo independiente.
- La limpieza se ejecuta como job encadenado solo después del éxito del backup; un fallo de limpieza no vuelve a ejecutar el dump.
- El scheduler conserva el backup diario y el monitor independiente, sin programar una limpieza por reloj fijo.
- Las pruebas verifican el horario del backup y monitor, la cadena de limpieza y la propagación de fallos del backup y de limpieza.
- La configuración del monitor y del correo es explícita; la alerta cubre backup fuera de antigüedad o almacenamiento saludable.

**Write scope:**
- `app/Modules/Admin/Providers/AdminServiceProvider.php` — Registrar el backup diario y el monitor independiente.
- `app/Modules/Admin/Jobs/RunDatabaseBackup.php` — Encadenar la limpieza para que corra solo después del éxito.
- `app/Modules/Admin/Jobs/RunBackupCleanup.php` — Ejecutar `backup:clean` como job independiente.
- `app/Modules/Admin/Tests/Backups/AdminBackupScheduleTest.php` — Verificar el horario del backup y el monitor independiente.
- `app/Modules/Admin/Tests/Backups/RunDatabaseBackupTest.php` — Verificar que la limpieza queda encadenada al job y que los fallos del backup se propagan.
- `app/Modules/Admin/Tests/Backups/RunBackupCleanupTest.php` — Verificar el comando de limpieza y la propagación de fallos.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Backups/AdminBackupScheduleTest.php app/Modules/Admin/Tests/Backups/RunDatabaseBackupTest.php app/Modules/Admin/Tests/Backups/RunBackupCleanupTest.php`

**Review:** codex

### TASK-012: Crear la página Filament propia de backups

Añadir la página en `app/Modules/Admin/Filament/Pages/Backups/BackupManagement.php`, dentro de una carpeta de funcionalidad bajo el directorio que `AdminPanelProvider` descubre recursivamente. Ubicar la vista Blade en `app/Modules/Admin/UI/Views/filament/backups/backup-management.blade.php`, referenciada como `admin::filament.backups.backup-management`. Mostrar backups, tamaño, fecha, disco, disponibilidad y estado global del destino. Añadir acciones de crear y descargar con autorización del lado servidor; la descarga solicita confirmación de contraseña. No incluir borrado manual ni restauración desde el panel.

**Type:** feature · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-004, TASK-005, TASK-010

**Acceptance Criteria:**
- Un lector de backups ve la página y el menú; un usuario sin `admin.backups.view` recibe 403 al abrir la URL directa.
- Las acciones vuelven a autorizar en el servidor; llamadas Livewire directas sin create/download son rechazadas aunque la acción esté oculta.
- Crear encola el job; descargar exige contraseña confirmada y usa URL temporal; la página no presenta acciones de borrado o restauración.

**Write scope:**
- `app/Modules/Admin/Filament/Pages/Backups/BackupManagement.php` — Implementar la página, navegación y acciones autorizadas.
- `app/Modules/Admin/UI/Views/filament/backups/backup-management.blade.php` — Renderizar la tabla y estado del destino en el módulo Admin.
- `app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php` — Probar acceso URL, permisos directos, confirmación, cola y descargas.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php`

**Review:** codex

### TASK-013: Documentar variables y requisitos operativos para backups

Añadir a `.env.example` las variables de backup `BACKUP_S3_ACCESS_KEY_ID`, `BACKUP_S3_SECRET_ACCESS_KEY`, `BACKUP_S3_DEFAULT_REGION`, `BACKUP_S3_BUCKET`, `BACKUP_S3_URL`, `BACKUP_S3_ENDPOINT`, `BACKUP_S3_USE_PATH_STYLE_ENDPOINT`, `BACKUP_ARCHIVE_PASSWORD` y `BACKUP_NOTIFICATION_EMAIL`, con valores vacíos o seguros de ejemplo y sin secretos reales. Actualizar README con requisitos de MySQL y `mysqldump`, worker no-sync y scheduler, y el contrato de bucket/IAM: privado, versionado, `ListBucket`, `PutObject`, `GetObject` y `DeleteObject` limitado al prefijo. Explicar que en un bucket versionado el borrado crea marcadores; una regla de ciclo de vida elimina versiones no actuales y marcadores vencidos después de 30 días.

**Type:** docs · **Effort:** M · **Agent:** general-purpose · **Priority:** P2

**Depends on:** TASK-002, TASK-011, TASK-012

**Acceptance Criteria:**
- `.env.example` documenta las nueve variables `BACKUP_*` enumeradas en la tarea con valores vacíos o seguros, sin credenciales ni contraseñas reales; README indica la instalación de `mysqldump`, el worker y el scheduler.
- README especifica permisos de lectura/escritura/listado/borrado, bucket privado versionado y ciclo de vida de versiones anteriores; la política no requiere `DeleteObjectVersion` si la expiración queda a cargo de S3 Lifecycle.
- La documentación explica que la salud se mide con antigüedad y almacenamiento del destino, y que el monitor del mismo despliegue no detecta por sí solo una caída total de la aplicación.

**Write scope:**
- `.env.example` — Añadir las nueve variables `BACKUP_*` enumeradas en la tarea con valores vacíos o seguros de ejemplo.
- `README.md` — Documentar prerrequisitos, worker/scheduler, IAM, versionado y expiración de versiones no actuales.

**Validate command:** `rtk git diff --check -- .env.example README.md`

**Review:** codex

### TASK-014: Actualizar las decisiones de arquitectura y las instrucciones del proyecto

Actualizar la sección Admin de `CLAUDE.md` y las instrucciones de roles/seeders en `AGENTS.md`. Documentar que se usa una página Filament propia sobre Spatie Backup, por qué no se usa el plugin ni MediaLibrary, los tres permisos nuevos, el rol lector, la asignación del bootstrap y el límite de uno a cuatro roles lectores aleatorios.

**Type:** docs · **Effort:** S · **Agent:** general-purpose · **Priority:** P2

**Depends on:** TASK-005, TASK-006, TASK-007, TASK-012

**Acceptance Criteria:**
- CLAUDE.md y AGENTS.md muestran el catálogo actualizado y el comportamiento real del bootstrap y de `AdminUserSeeder`.
- La decisión de página propia queda documentada y no describe el plugin Filament ni MediaLibrary como dependencias de la solución.
- Las instrucciones dejan claro que los administradores existentes no reciben automáticamente el rol y que no hay borrado/restauración manual desde el panel.

**Write scope:**
- `CLAUDE.md` — Documentar arquitectura elegida, permisos y comportamiento operativo del módulo Admin.
- `AGENTS.md` — Mantener las instrucciones de roles y seeders coherentes con el catálogo nuevo.

**Validate command:** `rtk git diff --check -- CLAUDE.md AGENTS.md`

**Review:** codex

### TASK-015: Escribir el procedimiento de restauración fuera del panel

Crear un runbook en el directorio `docs/development` existente. Documentar cómo descargar un ZIP privado, identificar su dump, descifrarlo con la variable de entorno, restaurarlo a una base MySQL aislada y verificar tablas/datos. Incluir advertencia contra restaurar sobre producción, requisitos de cliente `mysql` y cómo evitar exponer contraseña, URL temporal o dump en logs. Confirmar el formato real de archivo del paquete v10 durante la implementación.

**Type:** docs · **Effort:** M · **Agent:** devops-aws-senior-engineer · **Priority:** P2

**Depends on:** TASK-002, TASK-010, TASK-012, TASK-013

**Acceptance Criteria:**
- El runbook permite restaurar un archivo cifrado en una base MySQL vacía y aislada, con verificación posterior del esquema y un registro marcador.
- El procedimiento no requiere endpoint de restauración en Filament y prohíbe como destino la conexión de producción.
- El documento no incluye credenciales, passphrase ni URL firmada real.

**Write scope:**
- `docs/development/database-backup-restore.md` — Documentar copia privada, descifrado, importación aislada y verificación segura.

**Validate command:** `rtk git diff --check -- docs/development/database-backup-restore.md`

**Review:** codex

### TASK-016: Ensayar una restauración completa en un entorno aislado

Ejecutar el procedimiento de TASK-015 con credenciales de staging, un backup cifrado almacenado en S3 y una base MySQL scratch independiente. Usar un registro marcador conocido, comprobar el esquema y el dato después de restaurar y registrar en el runbook solo fecha, entorno genérico y resultado; no tocar producción ni guardar secretos.

**Type:** infra · **Effort:** M · **Agent:** devops-aws-senior-engineer · **Priority:** P1

**Depends on:** TASK-015

**Acceptance Criteria:**
- El ZIP se descarga desde el bucket privado, falla su apertura sin passphrase y se restaura correctamente con ella en la base scratch.
- La comprobación encuentra el registro marcador y valida que la base fuente no fue modificada.
- El runbook registra el resultado de la prueba sin credenciales, contraseñas, URLs firmadas ni datos reales de usuarios.

**Write scope:**
- `docs/development/database-backup-restore.md` — Registrar evidencia anonimizada del simulacro de restauración completado.

**Validate command:** `Manual: seguir los comandos del runbook para descargar, verificar el cifrado e importar en una base MySQL aislada; validar el registro marcador y registrar el resultado en docs/development/database-backup-restore.md.`

**Review:** codex

### TASK-018: Configurar el dump MySQL con transacción única

Agregar la opción de transacción única a la configuración `dump` de la conexión MySQL. Spatie Backup v10 lee esta opción desde `config/database.php` y la convierte en la llamada `useSingleTransaction()` de su dumper; mantenerla limitada a MySQL.

**Type:** feature · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P0

**Depends on:** TASK-002

**Acceptance Criteria:**
- La conexión `mysql` declara `dump.useSingleTransaction` como `true`, que Spatie Backup v10 convierte en la opción de mysqldump de transacción única.
- La prueba de configuración confirma la opción para MySQL sin añadirla a conexiones distintas.
- El resto de opciones y conexiones de base de datos conservan su configuración existente.

**Write scope:**
- `config/database.php` — Activar `useSingleTransaction` solamente para la conexión MySQL que se respaldará.
- `app/Modules/Admin/Tests/Backups/BackupConfigurationTest.php` — Verificar que la opción de dump se aplica solo a MySQL.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Backups/BackupConfigurationTest.php`

**Review:** codex

### TASK-019: Extraer la tabla de backups a una clase dedicada

Mover la configuración de la tabla de `BackupManagement` a `app/Modules/Admin/Filament/Pages/Backups/Tables/BackupsTable.php`, siguiendo el patrón `configure(Table $table): Table` de las tablas generadas por Filament. Mantener la página como componente Livewire que delega la configuración y conservar las acciones, autorización, validación de contraseña y presentación actuales.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-012

**Acceptance Criteria:**
- `BackupManagement::table()` delega en `BackupsTable::configure()` y la clase dedicada contiene la definición de columnas y acciones.
- La prueba de la página sigue cubriendo las acciones de descarga/creación, y las llamadas directas sin permiso siguen siendo rechazadas.
- La tabla conserva su estado vacío y no expone acciones de borrado ni restauración.

**Write scope:**
- `app/Modules/Admin/Filament/Pages/Backups/BackupManagement.php` — Delegar la configuración de tabla y ofrecer el chequeo de permiso de solo lectura que utiliza el configurador.
- `app/Modules/Admin/Filament/Pages/Backups/Tables/BackupsTable.php` — Configurar columnas, estado vacío y acción de descarga.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php`

**Review:** codex

### TASK-020: Refine backup page method structure and download action

Organize `BackupManagement` with Filament framework methods first and remove descriptive method comments while preserving type annotations. Move the download action callback body into a private `handleDownload()` method in `BackupsTable.php`, and delegate to it from the action.

**Type:** refactor · **Effort:** S · **Agent:** laravel-senior-engineer · **Priority:** P2

**Depends on:** TASK-019

**Acceptance Criteria:**
- `canAccess()`, `getHeaderActions()` and `table()` appear before backup operations and helper methods; explanatory method prose is removed while required PHPDoc type annotations remain.
- The table download action delegates to a private method in `BackupsTable.php` and retains the existing permission and download flow.
- The existing page test confirms that a missing password is rejected and an authorized download still redirects to its temporary URL.

**Write scope:**
- `app/Modules/Admin/Filament/Pages/Backups/BackupManagement.php` — Place Filament-provided methods first and remove descriptive method comments without dropping type annotations.
- `app/Modules/Admin/Filament/Pages/Backups/Tables/BackupsTable.php` — Extract the download callback body into a private helper and preserve validation behavior.

**Validate command:** `rtk php -d memory_limit=512M artisan test --compact app/Modules/Admin/Tests/Filament/Pages/Backups/BackupManagementTest.php`

**Review:** codex

### TASK-017: Pasar los gates completos de calidad y pruebas

Ejecutar los gates obligatorios del proyecto después de integrar los slices. Limpiar la caché de Rector, revisar el dry-run y aplicar Rector; después Pint, PHPStan, la suite completa y Laravel Doctor como última comprobación. Rector solo puede modificar código dentro de los scopes aprobados por las tareas previas; cualquier otra propuesta se detiene para revisión.

**Type:** chore · **Effort:** M · **Agent:** laravel-senior-engineer · **Priority:** P1

**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-018, TASK-019, TASK-020

**Acceptance Criteria:**
- Rector clear-cache, dry-run y aplicación se ejecutan en ese orden; Rector no deja cambios fuera de los archivos ya aprobados.
- Pint `--dirty`, PHPStan y la suite completa de Pest pasan después de aplicar Rector.
- `composer doctor` corre al final, después de todos los gates, y no se ejecutan más verificaciones después.

**Write scope:**
- Ningún archivo; solo gates finales.

**Validate command:** `rtk composer rector-clear-cache && rtk composer rector-dry && rtk composer rector && rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk composer test && rtk composer doctor`

**Review:** codex

## Failure Modes

- Sin adaptador S3, credenciales o permisos IAM, la escritura/lectura falla; ningún job debe registrar creación exitosa y la notificación de fallo debe llegar.
- Sin passphrase, o con ella incorrecta, el proceso falla cerrado y no entrega un archivo sin cifrar ni una URL temporal.
- Sin mysqldump o sin conexión no-sync/worker, el backup manual no se completa; el monitor avisa por edad/capacidad cuando el scheduler sigue vivo.
- Una llamada directa a la URL o a una acción Livewire no autorizada recibe 403 aunque navegación/acción esté oculta.
- Una ruta manipulada a un objeto fuera del disk/prefix o inexistente no produce URL ni auditoría de descarga.
- El borrado en bucket versionado no elimina físicamente el contenido; la regla lifecycle de versiones anteriores es un prerequisito de despliegue.
- El monitor corre en la misma aplicación que genera backups y no detecta por sí solo una caída completa de esa aplicación; un monitor central queda fuera de HOLD.
- La importación accidental sobre producción se evita usando una base scratch y un registro marcador en el simulacro.

## Ship Cut

SOC-55 no se considera completo sin cifrado y destino privado, permisos y auditoría, worker/scheduler/alertas, documentación y el simulacro de restauración. Si no están disponibles el bucket/credenciales de staging o MySQL scratch, se puede cerrar el código, pero el criterio de restauración permanece pendiente y no se marca la tarea como terminada.

## Test Coverage Map

- Configuración: app/Modules/Admin/Tests/Backups/BackupConfigurationTest.php.
- Enums y catálogo RBAC: app/Modules/Admin/Tests/Enums/AdminBackupAuthorizationEnumsTest.php y AdminAuthorizationSeederTest.php.
- Bootstrap y seeders: AdminBootstrapCommandTest.php, AdminUserSeederTest.php y AdminUserSeederRandomAdminsTest.php.
- Comandos genéricos: AdminUserRoleCommandTest.php y AdminRolePermissionsCommandTest.php.
- Auditoría: ActivityLogEventCatalogTest.php, RunDatabaseBackupTest.php y BackupManagementServiceTest.php.
- Programación y panel: AdminBackupScheduleTest.php y BackupManagementTest.php.
- Entrega final: suite completa de Pest, Rector, Pint, PHPStan y Doctor.

## Execution Summary

- Tasks: 20.
- Parallel layers: L0: TASK-001, TASK-003, TASK-008; L1: TASK-002, TASK-004; L2: TASK-005, TASK-006, TASK-007, TASK-009, TASK-018; L3: TASK-010, TASK-011; L4: TASK-012; L5: TASK-013, TASK-014, TASK-019; L6: TASK-015, TASK-020; L7: TASK-016; L8: TASK-017.
- Critical path (9 tasks): TASK-001 → TASK-002 → TASK-009 → TASK-010 → TASK-012 → TASK-013 → TASK-015 → TASK-016 → TASK-017.
- Tras completar una tarea atómica, detener la implementación para revisión del usuario.

## Task Dependencies

- **TASK-001** ← sin dependencias
- **TASK-002** ← TASK-001
- **TASK-003** ← sin dependencias
- **TASK-004** ← TASK-003
- **TASK-005** ← TASK-004
- **TASK-006** ← TASK-004
- **TASK-007** ← TASK-004
- **TASK-008** ← sin dependencias
- **TASK-009** ← TASK-002, TASK-008
- **TASK-010** ← TASK-002, TASK-008, TASK-009
- **TASK-011** ← TASK-009
- **TASK-012** ← TASK-004, TASK-005, TASK-010
- **TASK-013** ← TASK-002, TASK-011, TASK-012
- **TASK-014** ← TASK-005, TASK-006, TASK-007, TASK-012
- **TASK-015** ← TASK-002, TASK-010, TASK-012, TASK-013
- **TASK-016** ← TASK-015
- **TASK-018** ← TASK-002
- **TASK-019** ← TASK-012
- **TASK-020** ← TASK-019
- **TASK-017** ← TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-014, TASK-015, TASK-016, TASK-018, TASK-019, TASK-020

## References

- Laravel 13 S3 driver requirements: https://laravel.com/framework/docs/13.x/filesystem#s3-driver-configuration
- Laravel 13 temporary filesystem URLs: https://laravel.com/framework/docs/13.x/filesystem#temporary-urls
- Laravel 13 scheduling queued jobs: https://laravel.com/framework/docs/13.x/scheduling#scheduling-queued-jobs
- Spatie Laravel Backup v10 setup/configuration: https://spatie.be/docs/laravel-backup/v10/installation-and-setup
- Spatie Laravel Backup v10 database-only runs and notifications: https://spatie.be/docs/laravel-backup/v10/taking-backups/overview
- Spatie Laravel Backup v10 health monitoring: https://spatie.be/docs/laravel-backup/v10/monitoring-the-health-of-all-backups/overview
- Amazon S3 deletion behavior for versioned objects: https://docs.aws.amazon.com/AmazonS3/latest/userguide/DeletingObjectVersions.html
