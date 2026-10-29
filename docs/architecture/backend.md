# Arquitectura del Backend

Documento de referencia para crear y mantener módulos del backend en este proyecto Laravel.

> ⚠️ **Mantenimiento de la doc**: cuando agregues, modifiques o elimines una convención (o cualquier otro cambio de arquitectura del backend), actualizá este documento en el mismo PR/commit. La doc es la fuente de verdad de las convenciones del proyecto y no debe quedar desincronizada con el código.

## 0. Ubicación de los módulos: `app/Modules/` (SOC-38)

Los módulos de dominio viven dentro de `app/Modules/<Module>/` (namespace `App\Modules\<Module>\…`). Todo lo que quede **fuera** de `app/Modules/` es global a la aplicación:

| Dentro de `app/Modules/`                                                                | Fuera de `app/Modules/` (global)                                                                                    |
| --------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Controllers, models, requests, resources, rutas y tests del dominio                     | `app/Providers/`, `app/Http/` (middleware, controller base), `app/Events/`, `app/Console/Commands/`, `app/Support/` |
| Providers del propio módulo (`<Module>ServiceProvider`, `<Module>RouteServiceProvider`) | Listeners, policies y servicios compartidos por varios módulos                                                      |

**Estado de la migración:** la arquitectura es incremental. **Post**, **Comment** y **Like** viven en `app/Modules/Post/`, `app/Modules/Comment/` y `app/Modules/Like/`, y **ActivityLog** y **Announcement** nacieron ahí (SOC-54 y SOC-61); **Admin** vive en `app/Modules/Admin/` como módulo del sistema, no como un dominio CRUD estándar. Los demás dominios (`Auth`, `Friendship`, `Home`, `User`) siguen en `app/<Domain>/` —la _arquitectura vieja_— hasta que su propia tarea los mueva; por ejemplo, `Auth` pasará a `app/Modules/Auth/`. Los submódulos descritos en la sección 3 (`app/<Domain>/Modules/<Feature>/`) también pertenecen a esa arquitectura vieja: no los confundas con la carpeta superior `app/Modules/`.

**Código global (SOC-42):** no todo lo que vive hoy en la raíz de `app/` es un dominio pendiente de migrar. Va fuera de `app/Modules/` lo que sirve a varios dominios o al framework y no tiene modelos, rutas ni lógica de negocio propia: comandos de Artisan en `app/Console/Commands/` (se descubren solos; no necesitan provider), providers y middleware globales, y clases compartidas sin dominio en `app/Support/`. `MediaLibrary` es el ejemplo: es una integración con `spatie/laravel-medialibrary` que usan Post y User, así que su path generator vive en `app/Support/MediaLibrary/`, su comando en `app/Console/Commands/MediaLibraryCleanFoldersCommand.php` y sus tests en `tests/Feature/MediaLibrary/` (código global, no en `app/Modules/`). `app/Support/` es una carpeta base aprobada explícitamente en SOC-42; crear otra carpeta base requiere aprobación, como indica `AGENTS.md`. Los comandos destructivos se protegen con `Prohibitable` y se prohíben en producción desde `AppServiceProvider::boot()`.

**Frontend y Wayfinder:** el namespace del controller determina la ruta de las acciones generadas. Un módulo migrado se importa desde `@/shared/wayfinder/actions/App/Modules/<Module>/Controllers/<Controller>`; los de la arquitectura vieja, desde `@/shared/wayfinder/actions/App/<Domain>/…`. El directorio está en `.gitignore`: tras mover un módulo, regenera con `php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder` y actualiza los imports.

### Panel administrativo: Filament y Spatie Permission (SOC-44)

`app/Modules/Admin/` contiene la integración administrativa. Es una excepción al patrón de dos providers y rutas manuales: `AdminPanelProvider` se registra en `bootstrap/providers.php`, configura el panel Filament con id `admin`, URL `/admin` y guard de sesión `admin`, y descubre sus recursos dentro de `app/Modules/Admin/Filament/`. El guard de sesión `admin` usa el provider `users` existente; `web` permanece como guard predeterminado para la autenticación regular de la aplicación. Spatie usa también `admin` como `guard_name` del catálogo de roles y permisos. Son conceptos distintos: el guard de sesión autentica las solicitudes del panel y el guard name de Spatie delimita los roles y permisos compatibles con `User`. `AdminServiceProvider` registra los comandos Artisan del módulo; Filament genera y registra las rutas del panel.

La convención de directorios para las clases Filament del proyecto está documentada en [`.ai/rules/backend.md`](../../.ai/rules/backend.md); consulta también la [documentación de recursos de Filament 5](https://filamentphp.com/docs/5.x/resources/overview) para las APIs del framework.

La autorización de panel y recursos usa Spatie Permission con el guard `admin`, configurado en el modelo `User`. `User::canAccessPanel()` exige el panel `admin`, correo verificado, una cuenta no suspendida (SOC-35) y `admin.access`; además, `AdminPanelProvider` requiere MFA mediante la aplicación. `AdminRole` y `AdminPermission` en `app/Modules/Admin/Enums/` son el catálogo canónico. Sus bundles iniciales son:

| Rol | Permisos | Capacidad |
| --- | --- | --- |
| `admin` | `admin.access` | Entrar al panel, sin acceso implícito a secciones |
| `admin-dashboard-reader` | `admin.dashboard.view` | Ver el dashboard |
| `admin-users-reader` | `admin.users.viewAny`, `admin.users.view` | Consultar usuarios y sus detalles |
| `admin-posts-reader` | `admin.posts.viewAny`, `admin.posts.view` | Consultar publicaciones y sus detalles |
| `admin-users-provisioner` | `admin.users.viewAny`, `admin.users.create` | Listar usuarios y enviar invitaciones de administrador (SOC-53) |
| `admin-users-moderator` | `admin.users.viewAny`, `admin.users.view`, `admin.users.suspend`, `admin.users.reactivate` | Suspender y reactivar cuentas desde el detalle del usuario (SOC-35) |
| `admin-activity-reader` | `admin.activity.viewAny`, `admin.activity.view` | Consultar el registro de actividad y sus detalles (SOC-56) |
| `admin-backups-reader` | `admin.backups.view` | Consultar metadatos de respaldos y estado del destino (SOC-55), solo lectura |
| `admin-backups-operator` | `admin.backups.view`, `admin.backups.create`, `admin.backups.download` | Consultar metadatos, encolar respaldos y descargarlos tras confirmar la contraseña (SOC-55) |
| `admin-announcements-manager` | `admin.announcements.viewAny`, `admin.announcements.view`, `admin.announcements.create`, `admin.announcements.update`, `admin.announcements.delete` | Crear, editar, duplicar, despublicar y borrar anuncios del sistema (SOC-61) |

Las secciones y acciones comprueban sus permisos; por eso `admin.access` solo permite entrar al panel. Los roles lectores conceden acceso de lectura específico y los roles de provisión, moderación y operación de respaldos conceden únicamente las acciones indicadas en la tabla. El lector de respaldos no puede encolar ni descargar, y la página no tiene acciones manuales para borrar o restaurar respaldos. `AdminAuthorizationSeeder` crea todos los permisos de `AdminPermission` y todos los roles de `AdminRole` bajo el guard `admin`, asignando los bundles predeterminados al crear cada rol y preservando cambios operativos en ejecuciones posteriores. `App\User\Policies\UserPolicy`, asociada al modelo User con `#[UsePolicy]`, autoriza la lectura con `admin.users.viewAny` y `admin.users.view`, y solo permite como escritura `suspend` (`admin.users.suspend`, sobre una cuenta activa que no sea la propia) y `reactivate` (`admin.users.reactivate`, sobre una cuenta suspendida); el resto de las operaciones de escritura del recurso está denegado. `App\Modules\ActivityLog\Policies\ActivityLogPolicy` hace lo mismo con el registro de actividad (`admin.activity.viewAny` y `admin.activity.view`) y `App\Modules\Post\Policies\PostPolicy` con las publicaciones (`admin.posts.viewAny` y `admin.posts.view`; `view` también exige que la publicación conserve su autor). `App\Modules\Announcement\Policies\AnnouncementPolicy` (SOC-61) es la única que permite escrituras completas: cada habilidad del panel con su permiso de `admin.announcements.*`, `replicate` con `create`, y niega el borrado masivo y la restauración; además define `dismiss`, que usa la aplicación.

Desde SOC-57 la autorización de los recursos vive en la policy del modelo: ningún recurso sobrescribe `can*()` para leer, Filament consulta la policy y los relation managers y widgets usan la misma policy con `Gate::forUser($user)->allows('viewAny', Modelo::class)` o `allows('view', ...)` en lugar de comprobar `AdminPermission`. Solo la acción de invitar de `ListUsers` y las piezas del dashboard y de Backups, que no dependen de un modelo, siguen comprobando permisos. Filament **permite** cualquier habilidad cuyo método no exista en la policy, así que una policy de solo lectura debe negar explícitamente cada habilidad de escritura (`create`, `update`, `delete`, `deleteAny`, `restore`, `restoreAny`, `forceDelete`, `forceDeleteAny`). `CommentPolicy` agrega `viewAny` y `view` del panel (ambas con `admin.posts.view`, porque los comentarios no tienen permisos propios) junto a `update`/`delete` de la aplicación, que permiten al autor cambiar su comentario; por eso `CommentResource` conserva todas las habilidades de escritura en `false` (`canCreate`, `canEdit`, `canDelete`, `canDeleteAny`, `canRestore(Any)`, `canForceDelete(Any)`), documentadas en la clase: son los únicos overrides de autorización que quedan en los recursos de Admin.

`UserResource` muestra `name`, `email`, `email_verified_at`, `created_at` y `last_login_at`; no presenta contraseñas ni secretos/códigos de 2FA. Su página `ViewUser` agrega `UserActivityOverview`, con conteos de publicaciones, comentarios y dispositivos confiables activos, además de tendencias diarias de publicaciones y comentarios de los últimos siete días.

La página de usuario también muestra tres relaciones de solo lectura, limitadas al usuario autorizado mediante `UserPolicy`:

- `PostsRelationManager` permite buscar y ordenar publicaciones, filtrar por presencia de comentarios y fecha, y abrir el detalle compartido del recurso Posts.
- `ActivityLogsRelationManager` (pestaña "Activity", SOC-56) lista la actividad donde el usuario es causante o sujeto con la misma tabla del recurso de actividad; además exige `admin.activity.viewAny`.
- `TrustedDevicesRelationManager` muestra nombre del dispositivo, resumen de navegador/sistema operativo, último uso, vencimiento y estado derivado (activo, expirado o revocado). La consulta selecciona una lista permitida de campos; no expone `token_hash`, IP ni el `user_agent` sin procesar.

`PostResource` es el recurso de solo lectura para consultar publicaciones. Su detalle presenta autor, fechas y contenido, y carga `CommentsRelationManager` con comentarios paginados. La acción de ver un comentario carga sus replies al abrir el detalle; estos datos también son de lectura y no se agregan acciones de mutación.

`AdminPanelProvider` descubre los widgets del dashboard en `app/Modules/Admin/Filament/Widgets/`; por eso esa carpeta y `Console/Commands/` solo contienen clases que se registran o descubren, y el comportamiento compartido vive en traits de `Concerns/` (`Console/Concerns/InteractsWithAdminCatalog.php` para los comandos `admin:user-role`, `admin:role-permissions` y `admin:bootstrap-admin`, incluida la validación de `--actor`, y `Filament/Concerns/BuildsDailyActivityCharts.php` para los widgets de actividad de Posts y Users, y `Filament/Concerns/DescribesActivityLogParties.php` para describir causante y sujeto de una actividad). `RegistrationSummaryWidget` presenta los totales de cuentas y cuentas verificadas, con tendencias de siete días; `RegistrationsChart` muestra altas de usuarios con rangos de 7, 30 y 90 días o 12 meses, y cambia la agrupación entre días, semanas y meses. Cuando el rango no contiene altas, el chart presenta su estado vacío.

Las pruebas del módulo viven en `app/Modules/Admin/Tests/`; las pruebas de recursos Filament siguen la estructura de recurso, páginas y gestores de relaciones, y las pruebas del dashboard se agrupan bajo `Tests/Filament/`.

La migración publicada por Spatie para sus tablas permanece en la carpeta global `database/migrations/`. Las migraciones publicadas por paquetes externos se mantienen allí; la implementación propia de Admin, sus seeders y pruebas viven en `app/Modules/Admin/`.

`DatabaseSeeder` ejecuta `AdminAuthorizationSeeder` y `AdminUserSeeder`. El segundo seeder solo modifica usuarios en `local` y `testing`: asigna al administrador local predeterminado el rol `admin`, los cinco roles lectores (dashboard, usuarios, publicaciones, actividad y respaldos), `admin-users-moderator`, `admin-backups-operator` y `admin-announcements-manager` (SOC-61); promueve usuarios al azar hasta que al menos el 20% (redondeado hacia arriba) tenga `admin`, asignando a cada nuevo administrador de 1 a 4 roles lectores aleatorios y conservando asignaciones previas. En producción, inicializa el catálogo y ejecuta el bootstrap con una cuenta verificada:

```bash
php artisan db:seed --class='App\Modules\Admin\Database\Seeders\AdminAuthorizationSeeder'
php artisan admin:bootstrap-admin admin@example.com --actor=admin@example.com
```

`admin:bootstrap-admin <email> --actor=<email>` solo permite el primer bootstrap: exige el catálogo inicializado, una cuenta existente con correo verificado y que todavía no haya un usuario con `admin.access`; asigna `admin` y los cinco roles lectores (actividad y respaldos incluidos desde SOC-56 y SOC-55). Después, `admin:user-role <grant|revoke> <email> <role> --actor=<admin-email>` modifica una asignación de rol y `admin:role-permissions <grant|revoke> <role> <permission> --actor=<admin-email>` modifica un bundle. Los tres comandos exigen `--actor` (SOC-54): la persona que ejecuta el cambio, que queda como causante en el log `admin` del registro de actividad. Debe ser una cuenta verificada y, salvo en el bootstrap (donde puede ser el mismo usuario promovido), tener `admin.access`. Solo se registran los cambios reales; un rechazo no cambia nada ni deja registro. El primer comando acepta cualquier caso de `AdminRole` y el segundo cualquier caso de `AdminPermission` (ver la tabla de arriba). Ambos rechazan valores fuera de los enums y protegen el último permiso `admin.access`. La gestión sigue siendo por consola; SOC-44 no agrega una pantalla administrativa para ello.

### Idiomas del panel (SOC-65)

El panel de administración está en español e inglés. Las traducciones viven en `lang/{en,es}/` en la raíz del proyecto, la convención de Laravel; `resources/lang/` se movió ahí y ya no existe. Cada sección del panel tiene su catálogo: `lang/{en,es}/admin/{common,dashboard,users,backups,activity-log}.php`. `common` guarda solo los textos que usan dos o más secciones.

- **Claves con nombre**, nunca el texto en inglés como clave: `admin/users.table.status`. Los plurales van con `trans_choice` (`{1} :count día|[0,*] :count días`) y los valores con marcadores (`:name`, `:count`). `:Type` pone el valor con la primera letra en mayúscula.
- **En PHP** todo texto pasa por `App\Support\Translation\TranslatedText::of($clave, $reemplazos)`. Es `__()` tipado como `string`: PHPStan no acepta el `array|string` de `__()` donde Filament espera un texto, y una clave que apunta a un grupo lanza `LogicException`. **En Blade** se usa `__()`.
- **Propiedades de texto**: `$title`, `$heading`, `$navigationLabel`, `$navigationGroup` y el `$title` de un relation manager no pueden llamar a una función. Por eso la clase sobrescribe el getter correspondiente: `getTitle()`, `getHeading()`, `getNavigationLabel()`, `getNavigationGroup()`, `getModelLabel()`, `getPluralModelLabel()` o el estático `RelationManager::getTitle()`.
- **Colores e íconos de badges**: nunca comparan la etiqueta traducida. Dependen del registro (`hasVerifiedEmail()`, el booleano de salud de los respaldos) o de una clave: `TrustedDevicesRelationManager` guarda `active`/`expired`/`revoked` como estado y solo traduce la etiqueta.
- **Fechas**: se formatean con `translatedFormat()` y un formato del catálogo. En español salen como "5 oct.". Los textos propios de Filament (login, MFA, perfil, botones, paginación, filtros) ya vienen traducidos.
- **Selector de idioma**: es el plugin `bezhansalleh/filament-language-switch` (5.x), configurado en `AdminServiceProvider::configureLanguageSwitch()`.
  - Ofrece `es` y `en` (`SWITCH_LOCALES`, casos de `PreferredLocale`) dentro del panel y en el login, la configuración obligatoria de MFA y el perfil. No aparece en la página de aceptar invitación.
  - El idioma se resuelve en este orden: sesión, `?locale=`, `users.preferred_locale` del administrador (leído con `Filament::auth()`), `Accept-Language`, cookie y `app.locale`.
  - Al elegir un idioma, `StoreAdminPreferredLocale` lo guarda en `preferred_locale` sin auditarlo. Es la misma columna que edita la configuración de cuenta de la aplicación.
  - El middleware del plugin se registra también como middleware persistente de Livewire. Sin eso, cada actualización de un componente (filtrar, paginar, abrir un modal) respondería en `app.locale`.
- **Qué está traducido**:
  - Dashboard.
  - Users, con `AccountStatus::label()`.
  - Backups, con los errores de descarga de `BackupManagementService`.
  - Del registro de actividad: su tabla, `DescribesActivityLogParties` y los nombres de eventos y logs. `ActivityLogEventCatalog` lee `admin/activity-log.events.<valor>` y, si falta la clave, usa el nombre en inglés.
- **Qué sigue en inglés**:
  - Announcements, Posts y Comments.
  - El resto del registro de actividad.
  - El correo y la página de invitación.
  - Los nombres de las comprobaciones fallidas de Spatie Backup.
- **Pruebas que lo protegen** (`app/Modules/Admin/Tests/Translations/`):
  - `AdminTranslationKeysTest` exige las mismas claves, textos no vacíos y los mismos marcadores en `en` y `es`, además de un nombre para cada evento, log y tipo del morph map.
  - `AdminHardcodedTextTest` falla si un archivo ya traducido pasa un literal a un método de texto de Filament o escribe texto suelto en Blade.

### Registro de actividad (ActivityLog)

`app/Modules/ActivityLog/` (SOC-54) registra las acciones de los usuarios y la auditoría administrativa en una sola tabla `activity_log` de `spatie/laravel-activitylog`, separada por `log_name` (`auth`, `content`, `likes`, `trusted_devices`, `account`, `admin`), cada uno con su retención y una purga diaria (`activity-log:purge`). Ningún otro módulo usa el paquete: los módulos despachan eventos de dominio que escuchan los subscribers de `Listeners/`, y la auditoría Admin llama a `Services/ActivityLogger` dentro de su transacción. Nunca se guarda contenido, contraseñas, códigos, tokens ni correos. La decisión de diseño, el catálogo de eventos (`Contracts/ActivityLogEvent` con un enum por dominio en `Enums/Events/`) y la política de privacidad están documentados en CLAUDE.md.

Desde SOC-56 el panel Admin tiene un recurso de solo lectura, `ActivityLogs/ActivityLogResource` (`/admin/activity-logs`), sobre el modelo propio `App\Modules\ActivityLog\Models\ActivityLog` (al que apunta `activitylog.activity_model`) y autorizado por `ActivityLogPolicy`: listado con filtros por log, evento, causante y fechas, y un detalle con las propiedades guardadas, la IP y el user agent. Nunca muestra el contenido de publicaciones o comentarios.

Para tener datos de prueba, `ActivityLogSeeder` (`app/Modules/ActivityLog/Database/Seeders/`) corre al final de `DatabaseSeeder` y genera la actividad a partir de lo ya sembrado: publicaciones, comentarios (creados, respuestas, editados y tombstones), likes, eventos de dispositivos de confianza, logins y auditoría Admin. Reparte las fechas en los últimos 60 días, nunca guarda contenido y no hace nada si la tabla ya tiene registros; el detalle está en CLAUDE.md.

### Anuncios del sistema (Announcement)

`app/Modules/Announcement/` (SOC-61) permite publicar avisos desde el panel Admin que la aplicación muestra a sus usuarios en su siguiente visita (sin tiempo real). Cada anuncio tiene tipo (`info`, `warning`, `critical`), audiencia (`all`, `authenticated`, `without_two_factor`), fechas de inicio y de fin opcional, un botón opcional, una imagen opcional en MediaLibrary, una presentación (`display`: toast o dialog, que el administrador elige en el formulario desde SOC-63) y puede o no cerrarse; los descartes de cada usuario se guardan en `announcement_dismissals`.

- **Una sola regla de visibilidad.** Los scopes `scheduled`, `running` y `ended` del modelo definen las fechas; `availableTo(?User)` agrega la audiencia y `visibleTo(?User)` quita lo que el usuario descartó, ordena por gravedad y limita a tres. Las pestañas del panel y la prop de la aplicación usan esos mismos scopes. `dialogs()` y `dialogsOverlapping()` alimentan la pestaña 'Active dialogs' y el aviso del formulario cuando otro dialog se cruza en las mismas fechas.
- **Un solo escritor auditado.** `AnnouncementService` crea, edita, despublica, duplica y borra dentro de una transacción y registra `admin.announcement_*` en el log `admin`, sin el título ni el cuerpo. El panel nunca escribe el modelo directamente; `AnnouncementData` convierte el estado del formulario.
- **HTML siempre saneado.** El cuerpo se guarda tal como lo escribe el editor y se muestra solo a través de `Announcement::safeBodyHtml()` (el `RichContentRenderer` de Filament), en el detalle del panel, en la vista previa y en la aplicación.
- **Aplicación.** `HandleInertiaRequests` comparte la prop `announcements` (lazy, serializada por `AnnouncementBannerResource`, sin autor, audiencia ni fechas). `AnnouncementDismissalController` tiene dos acciones: `dismiss` (`POST announcements/{announcement}/dismiss`) guarda un descarte y `sync` (`POST announcements/dismissals`) guarda los que un invitado cerró en su navegador antes de iniciar sesión, solo los que la policy permite; ambas usan `insertOrIgnore` y tienen throttle propio.
- **Todo anuncio se puede cerrar (SOC-63).** Con sesión y descartable, el cierre va al servidor y no vuelve en ningún dispositivo; un invitado lo guarda en `localStorage` y se sincroniza al iniciar sesión; uno no descartable se guarda en `sessionStorage` y vuelve en la siguiente sesión del navegador.
- **Panel.** `Filament/Resources/Announcements/` tiene listado con pestañas, creación, detalle y edición, más las acciones de vista previa (con una maqueta de dialog para los dialogs), duplicar y despublicar, y el campo 'Show as', que pasa solo a Dialog al elegir el tipo Critical mientras el administrador no haya elegido; la imagen usa el plugin oficial `filament/spatie-laravel-media-library-plugin`.

`AnnouncementSeeder` siembra con la factory unos 23 anuncios con historia en los últimos días: cinco fijos que cubren cada caso (el de 2FA como dialog), quince de ejemplo (los críticos como dialog) con un estado al azar (vigente, programado, vencido o despublicado), algunos editados, y tres duplicados. No pasa por el servicio para no escribir en `activity_log` antes de tiempo: `ActivityLogSeeder` deriva después la auditoría (`created`, `duplicated`, `updated` y `unpublished`) de las fechas de cada fila, como hace con las suspensiones. El detalle completo está en CLAUDE.md.

## 1. Convención de nomenclatura

Todos los archivos y clases usan **PascalCase** y empiezan con el nombre del módulo.

| Tipo                   | Patrón                         | Ejemplo                                 |
| ---------------------- | ------------------------------ | --------------------------------------- |
| Controller             | `{Module}Controller`           | `PostController`                        |
| Model                  | `{Module}`                     | `Post`                                  |
| Factory                | `{Module}Factory`              | `PostFactory`                           |
| Request                | `{Module}{Action}Request`      | `PostStoreRequest`, `PostUpdateRequest` |
| Resource               | `{Module}Resource`             | `PostResource`                          |
| Data                   | `{Module}{Concept}Data`        | `TrustedDeviceFiltersData`              |
| Seeder                 | `{Module}Seeder`               | `PostSeeder`                            |
| Enum                   | `{Module}{Concept}`            | `CommentType`, `FriendshipStatus`       |
| Service Provider       | `{Module}ServiceProvider`      | `PostServiceProvider`                   |
| Route Service Provider | `{Module}RouteServiceProvider` | `PostRouteServiceProvider`              |
| Policy                 | `{Module}Policy`               | `PostPolicy`                            |
| Service                | `{Module}Service`              | `PostService`                           |
| Console Command        | `{Module}{Purpose}Command`     | `MediaLibraryCleanFoldersCommand`       |

**Excepción:** `routes/routes.php` no lleva prefijo (Laravel lo busca así).

## 2. Estructura de un módulo

```
app/Modules/<Module>/
├── Controllers/
│   └── {Module}Controller.php
├── Models/
│   └── {Module}.php
├── Requests/
│   ├── {Module}StoreRequest.php
│   └── {Module}UpdateRequest.php
├── Resources/
│   └── {Module}Resource.php
├── Data/
│   └── {Module}{Concept}Data.php      ← input, filtros y estructuras no modeladas
├── Database/
│   ├── Factories/
│   │   └── {Module}Factory.php
│   ├── Migrations/
│   └── Seeders/
│       └── {Module}Seeder.php        ← solo si hay datos iniciales
├── Tests/                             ← solo si el módulo tiene pruebas propias
├── Providers/
│   ├── {Module}ServiceProvider.php
│   └── {Module}RouteServiceProvider.php
└── routes/
    └── routes.php
```

**No todo módulo necesita todo.** Solo crea las carpetas que vayas a usar. El núcleo obligatorio:

- `Models/`
- `Providers/` (ambos providers)
- `routes/`

## 3. Submódulos (arquitectura vieja)

> Esta sección describe el layout `app/<Domain>/Modules/<Feature>/` de la arquitectura vieja (Auth y User). Cuando esos dominios se migren, vivirán en `app/Modules/<Domain>/` (p. ej. `app/Modules/Auth/`).

Cuando un módulo tiene **varias áreas independientes** que ameritan su propio espacio, se subdivide. Ejemplos del proyecto:

- `app/Auth/Modules/` → `Email/`, `Login/`, `Password/`, `Register/`, `TrustedDevice/`
- `app/User/` → `Profile/`, `Preferences/`, `Modules/AccountSettings/`, `Modules/TwoFactor/`
- `app/Auth/Modules/TrustedDevice/` → cookie-backed TOTP bypass (introducido en SOC-14)

La configuración de 2FA de User vive en `app/User/Modules/TwoFactor/`: ahí se agrupan su controller, Form Requests, listener de regeneración de códigos y pruebas. Las rutas siguen en `app/User/routes/security.php`. `UserServiceProvider` registra `app/User/Providers/UserEventServiceProvider.php`, que conecta el evento Fortify de regeneración con el listener de User; `AuthEventServiceProvider` conserva los listeners de TrustedDevice. El challenge de 2FA durante el login continúa siendo responsabilidad de Auth.

La configuración privada de la cuenta vive en `app/User/Modules/AccountSettings/`: agrupa el controller, Form Request, Data object construido con los datos validados, `AccountSettingsResource`, listener de último acceso y pruebas del módulo. El controller resuelve siempre al usuario autenticado. `UserResource` sigue reservado al perfil público; la información privada de configuración no se agrega a esa serialización. El perfil público continúa en `app/User/Profile/` y las preferencias existentes siguen en `app/User/Preferences/`.

El cambio de contraseña conserva su responsabilidad de backend en `app/Auth/Modules/Password/`, incluida su validación y la revocación de dispositivos confiables. AccountSettings lo presenta en un diálogo y envía la actualización al endpoint `PUT /setting/password`; no existe un GET ni una vista independiente para cambiar la contraseña.

La infraestructura compartida de User se organiza en `app/User/Database/{Migrations,Factories,Seeders}/`. `UserServiceProvider` carga las migraciones modulares; la migración de AccountSettings se encuentra allí. Las migraciones iniciales de Laravel permanecen en la carpeta raíz `database/migrations/` y no se mueven a User.

El estado de cuenta (SOC-35) se guarda en `users.status` (enum `AccountStatus`: `active` / `suspended`) y cada cambio agrega una fila a `account_status_changes` con su motivo obligatorio y el administrador que lo hizo. Solo `app/User/Modules/AccountStatus/Services/AccountStatusService` lo cambia: al suspender revoca los dispositivos confiables y registra la actividad sin el motivo. Una cuenta suspendida no puede entrar por contraseña, reto 2FA, dispositivo confiable ni panel admin, y el middleware global `EnsureAccountIsActive` cierra las sesiones abiertas. AccountSettings muestra el estado guardado de solo lectura y no lo infiere de la verificación del correo, el último acceso ni la sesión activa; el detalle está en CLAUDE.md.

### Cuándo subdividir

| ✅ Sí                                                           | ❌ No                            |
| --------------------------------------------------------------- | -------------------------------- |
| Hay flujos independientes (login vs registro)                   | Es un módulo pequeño y cohesivo  |
| Cada área tiene sus propias Requests con validaciones distintas | Los archivos están muy acoplados |
| Las áreas evolucionarán por separado                            | Solo tienes 2-3 archivos         |

### Estructura de un submódulo

```
app/<Module>/<Submodule>/
├── Controllers/
│   └── <Submodule>Controller.php
├── Services/                         ← lógica de negocio compartida de la feature
│   └── <Submodule>Service.php
└── Requests/                          ← solo si hay validaciones específicas
    └── <Submodule>Request.php
```

**Importante:** los submódulos NO tienen providers propios. Las rutas se centralizan en `app/<Module>/routes/` con un archivo por submódulo:

```
app/Auth/routes/
├── routes.php        ← carga los includes
├── email.php
├── login.php
├── password.php
└── register.php
```

### Convención `Modules/<Feature>/` (introducida en SOC-14)

Cuando una feature **dentro de un módulo crece lo suficiente como para merecer su propio namespace**, se extrae bajo `app/<Module>/Modules/<Feature>/`. Ejemplo canónico: `app/Auth/Modules/TrustedDevice/`.

**Lo que el submodule SÍ tiene** (espeja el lado de features del módulo padre):

```
app/<Module>/Modules/<Feature>/
├── Concerns/        ← traits / helpers locales (ej. HasPasswordConfirmation.php)
├── Controllers/     ← controladores específicos de la feature
├── Listeners/       ← listeners de Fortify / eventos del dominio scoped a la feature
├── Data/            ← filtros y estructuras tipadas que no representan modelos
├── Requests/        ← FormRequests de la feature
├── Resources/       ← Eloquent API Resources de los modelos de la feature
└── Services/        ← lógica de negocio compartida de la feature
```

**Lo que se queda en el módulo padre** (nunca se duplica dentro del submodule):

- `Providers/` — el `<Module>ServiceProvider` y `<Module>RouteServiceProvider` ya cubren la feature.
- `Models/` — el modelo Eloquent vive en el padre para que las relaciones (`User::trustedDevices()`) queden junto al resto del dominio.
- `routes/` — se registran a través del `AuthRouteServiceProvider` central con un archivo por concern (ej. `routes/trustedDevice.php`).
- `Database/Migrations/`, `Database/Factories/` y `Database/Seeders/` — migraciones y factories se registran vía `<Module>ServiceProvider::loadMigrationsFrom`; los seeders se invocan desde `DatabaseSeeder`.
- `config/` — la configuración del módulo vive en el padre.

**Regla práctica para extraer un submodule**: la feature tiene sus propios listeners de eventos **o** ≥3 controllers/requests propios. Por debajo de eso, mantenla en el concern correspondiente del módulo padre. SOC-32 define una excepción explícita para Auth: Email, Login y Register viven en `app/Auth/Modules/{Email,Login,Register}/` y sus pruebas se co-ubican allí; Login y Register quedan por debajo del umbral general, pero siguen la estructura acordada para esa tarea.

## 4. Responsabilidad de cada archivo

| Tipo                       | Qué hace                                                                    |
| -------------------------- | --------------------------------------------------------------------------- |
| **Controller**             | Orquesta HTTP: recibe Request → llama al modelo → retorna Response          |
| **Model**                  | Entidad del dominio + relaciones Eloquent                                   |
| **Request**                | Valida input del usuario (FormRequest con FluentRule)                       |
| **Resource**               | Transforma un modelo al JSON que verá el cliente                            |
| **Data**                   | Representa y normaliza input, filtros o estructuras no modeladas            |
| **Factory**                | Genera instancias falsas para tests/seeders                                 |
| **Seeder**                 | Puebla la BD con datos iniciales                                            |
| **Policy**                 | Reglas de autorización (quién puede hacer qué)                              |
| **Service**                | Lógica de negocio compleja que no encaja en un controller                   |
| **Console Command**        | Operaciones Artisan: batch, mantenimiento (global: `app/Console/Commands/`) |
| **Service Provider**       | Registra bindings en el contenedor de Laravel                               |
| **Route Service Provider** | Carga las rutas del módulo bajo middleware `web`                            |

> 💡 Si una acción no cabe en un controller (muchos casos, reglas complejas, transacciones múltiples) → crea un **Service**. Si una autorización es reutilizable → crea una **Policy**.

### Data y Resources

- Usa `spatie/laravel-data` para filtros, input y estructuras internas tipadas que no representan un modelo Eloquent.
- Usa `JsonResource` para serializar modelos Eloquent hacia Inertia o APIs.
- No dupliques en un `Data` la transformación de un modelo que ya pertenece a un `Resource`.

### Organización de pruebas

Las pruebas nuevas se guardan junto al módulo dueño de la funcionalidad:

- Módulo migrado a la nueva arquitectura: `app/Modules/<Domain>/Tests/` (Post, Comment y Like), además del módulo del sistema `app/Modules/Admin/Tests/`.
- Módulo de dominio en la arquitectura vieja: `app/<Domain>/Tests/`.
- Funcionalidad de un submódulo (arquitectura vieja): `app/<Domain>/Modules/<Feature>/Tests/`.
- Reserva `tests/` para pruebas transversales/de infraestructura y las pruebas existentes que aún no se hayan migrado; no agregues allí nuevas pruebas específicas de un módulo.

Dentro de cada `Tests/`, agrupa por responsabilidad:

- `Crud/` para crear, actualizar y eliminar recursos.
- `Database/` para verificar factories y seeders cuando corresponda.
- `Models/` para relaciones, morph map y colecciones de media del modelo.
- `Lifecycle/` para renovaciones, reactivaciones y otros cambios de ciclo de vida.
- `Listeners/` para comportamiento disparado por listeners.
- `Queries/` para índices, listados y filtros.
- `Requests/` para reglas y mensajes de validación de FormRequests.
- `Resources/` para la serialización de los Resources.
- `Security/` para regresiones de seguridad y robustez: rate limiting, normalización de entrada, límites de anidación y conteo de consultas.

Si aparece una nueva responsabilidad —por ejemplo `Services/`— se crea su carpeta únicamente cuando exista código de esa categoría que probar.
Al agregar una nueva carpeta modular de pruebas, regístrala en `phpunit.xml` y configura en `tests/Pest.php` el caso base y los traits compartidos que correspondan; actualmente ya están registrados `app/Modules/Post/Tests/`, `app/Modules/Comment/Tests/`, `app/Modules/Like/Tests/` y los de Auth/User (`app/Auth/Modules/*/Tests/`, `app/User/Modules/*/Tests/`).

## 5. Cómo crear un módulo nuevo

Sigue esta checklist en orden:

1. **Crear la estructura de carpetas:**

   ```bash
   mkdir -p app/Modules/{Module}/{Models,Providers,routes,Controllers,Requests,Resources,Database/{Migrations,Factories,Seeders}}
   ```

2. **Crear el modelo** en `app/Modules/{Module}/Models/{Module}.php`

3. **Crear la factory** en `app/Modules/{Module}/Database/Factories/{Module}Factory.php`. Su estado predeterminado debe cubrir los campos persistidos del modelo y su migración con valores realistas o `null` explícito; deja que Eloquent gestione la clave primaria y los timestamps.

4. **Crear los dos providers:**
   - `app/Modules/{Module}/Providers/{Module}ServiceProvider.php` — su `boot()` registra el `RouteServiceProvider`
   - `app/Modules/{Module}/Providers/{Module}RouteServiceProvider.php` — extiende `Illuminate\Foundation\Support\Providers\RouteServiceProvider`, carga `app/Modules/{Module}/routes/routes.php` con middleware `web`

5. **Registrar el `{Module}ServiceProvider`** en `bootstrap/providers.php`

6. **Crear el controller** en `app/Modules/{Module}/Controllers/{Module}Controller.php`

7. **Crear las Requests** según las acciones (Store, Update, u otras específicas)

8. **Crear la Resource** (los listados paginados se serializan con `->through()` sobre la Resource; no se crea una Collection)

9. **Definir las rutas** en `app/Modules/{Module}/routes/routes.php`

10. **Crear las pruebas junto al código que cubren:** `app/Modules/{Module}/Tests/` para pruebas del módulo (en la arquitectura vieja: `app/{Module}/Tests/` o `app/{Module}/Modules/{Feature}/Tests/` para un submódulo). Registra la carpeta en `phpunit.xml` y `tests/Pest.php` para que Pest la descubra y aplique la configuración correcta.

## 6. Convenciones adicionales

- Todo archivo PHP empieza con `declare(strict_types=1);`
- Namespaces PSR-4: `App\Modules\{Module}\{Type}` (arquitectura vieja: `App\{Module}\{Type}`)
- Al cambiar un modelo o su esquema, revisa y actualiza su factory para cubrir sus campos persistidos con datos realistas o `null` explícito; omite solo los campos que Eloquent administra automáticamente, como la clave primaria y los timestamps.
- Relaciones Eloquent declaran genéricos en PHPDoc: `@return HasMany<Post, $this>`
- Models con `HasFactory` declaran el genérico: `/** @use HasFactory<{Module}Factory> */`
- Constantes del modelo (MORPH_NAME, MORPH_COLUMN) llevan docblock descriptivo
- Si el módulo es polimórfico, registra el morph en `App\Providers\AppServiceProvider::boot()` (`Relation::enforceMorphMap`)
- En una tabla polimórfica propia del dominio, las columnas y la relación comparten el sufijo `{adjetivo}able` de la convención de Laravel: `morphs('commentable')` genera `commentable_type` y `commentable_id`, y el modelo expone `commentable()` (igual que `likeable` en Like). `model_type` y `model_id` es la convención de las tablas genéricas de paquetes como Spatie (`media`, permisos), no de las tablas de dominio.
- El nombre del placeholder de una ruta debe coincidir con el parámetro tipado del controller cuando se usa binding implícito de enum o modelo (por ejemplo `{post}` ↔ `Post $post`); si no coinciden, Laravel resuelve la clase desde el contenedor y la ruta responde 500. Rector renombra los parámetros según su tipo, así que corrige la ruta y no el parámetro.
- Las rutas que escriben datos llevan `throttle`. Cuando un módulo tiene varias rutas con throttle, usa el tercer argumento como prefijo (`throttle:6,1,comments.store`): Laravel firma los throttles sin nombre solo por usuario, y sin prefijo todas las rutas compartirían el mismo cupo.
- Normaliza la entrada de texto libre en `prepareForValidation()` del FormRequest (por ejemplo, quitar caracteres de control conservando tabulaciones y saltos de línea, y recortar) para que las reglas se evalúen sobre el valor que realmente se guarda.
- Los listados paginados por cursor se serializan con `cursorPaginate()->through(fn (Model $model): array => new {Module}Resource($model)->resolve($request))`; no se envuelven en una Collection manual que reconstruya `data`, `meta` y `links`. Se devuelve el paginador tal cual. Solo se envuelve en `Inertia::scroll(...)` cuando la página usa el componente `<InfiniteScroll>` de Inertia (como `posts` en Home y Profile): `Inertia::scroll` agrega a la respuesta instrucciones de merge (`mergeProps`) y metadata de scroll (`scrollProps`) que, sin ese componente, nadie consume (la lista de comentarios la maneja un hook que recarga con `reset` y conserva su propio estado).
- Los datos de un diálogo o lista que solo se necesitan al abrirlos salen como props `Inertia::optional` de una clase `ProvidesInertiaProperties` en `Props/` (ver `app/Modules/Comment/Props/CommentThreadProps.php`), que el controlador de la página anfitriona incluye en `Inertia::render`. No llevan ruta propia: el cliente hace un partial reload con `only`. El límite de tasa de esa lectura se aplica con `RateLimiter` dentro de la clase, ya que no hay middleware de ruta.
- Un controlador con una sola acción es invocable (`__invoke`), por ejemplo los controladores de Auth/TrustedDevice; cuando un módulo tiene varias acciones sobre el mismo recurso se agrupan en un solo controlador con un método por acción, por ejemplo `CommentController` (`store`, `update` y `destroy`) y `LikeController` (`toggleComment` y `togglePost`, que comparten un método privado).
- Los hilos de comentarios tienen un solo nivel con mención (SOC-41): responder a una respuesta se acepta y se aplana. `CommentController::store()` cuelga el comentario nuevo del comentario padre del hilo y guarda en `replied_to_user_id` (FK nullable a `users`, `nullOnDelete`) al autor de la respuesta respondida; el usuario lo deriva el servidor, nunca el cliente. `CommentResource` expone `repliedTo: {id, name}` o `null` solo cuando `repliedToUser` está cargado (`whenLoaded`), y `CommentThreadProps` lo precarga con `repliedToUser:id,name` para mantener el número de consultas constante. Responder a un post o a un comentario raíz no guarda mención.
- Editar y eliminar comentarios propios (SOC-52): las rutas `comments.update` (PATCH) y `comments.destroy` (DELETE) viven en `CommentController` junto a `store`, con `throttle:10,1,comments.update` y `throttle:10,1,comments.destroy`. La autorización la hace `CommentPolicy` (`app/Modules/Comment/Policies/`, registrada con `#[UsePolicy]` en el modelo, porque el descubrimiento por nombre no la encuentra bajo `Modules/`) y se aplica con `#[Authorize('update'|'delete', 'comment')]` en el método del controlador: corre tras el route model binding y antes del FormRequest, así que un usuario ajeno recibe 403 aunque envíe datos inválidos. Solo el autor puede editar o eliminar y nunca un comentario ya eliminado.
- Borrado mixto con tombstone (SOC-52): `comments` usa `SoftDeletes` y `edited_at` (migración nueva, las originales no se editan). Un comentario con respuestas se elimina de forma lógica y se muestra como "Comentario eliminado" para no romper el hilo; uno sin respuestas se elimina de forma física junto con sus likes (la tabla `likes` no tiene FK, así que se borran de forma explícita) dentro de la misma `DB::transaction`, y si era la última respuesta de un padre ya eliminado, el padre también se elimina de forma física para no dejar tombstones vacíos. Un tombstone nunca expone contenido ni autor (`content` y `user` en `null`). Como `SoftDeletes` cambia las consultas por defecto, `Post::comments()`, `User::comments()` y el panel Admin dejan de ver los tombstones; los listados de hilos usan `withTrashed()` donde deben mostrarlos, y publicar sobre un tombstone responde 404.
- Contadores reales en listados (SOC-50): `Post::withEngagementFor(User)` es un `#[Scope]` que añade en la misma consulta `root_comments_count` (comentarios raíz vivos), `replies_count` (respuestas, incluidas las de un tombstone, con subconsulta correlacionada y `withTrashed()`), `likes_count` y `liked_by_me`; `Post::commentsTotal()` suma los dos contadores y `PostResource` expone `commentsCount` y `likesInfo`, así el listado de publicaciones mantiene un número constante de consultas. Las respuestas de un comentario se listan de la más antigua a la más nueva (id ascendente) y los comentarios de una publicación de la más nueva a la más antigua. El máximo del contenido de un comentario vive en `Comment::CONTENT_MAX_LENGTH` y lo usan `CommentStoreRequest` y `CommentUpdateRequest`; el frontend lo replica en `modules/comment/constants/commentContent.ts` y ambos deben cambiarse juntos.
- Un `JsonResource` con varios bloques los reparte en métodos privados con el mismo estilo (`author()`, `repliedTo()`, `repliesInfo()`, `likesInfo()`, `isMine()`), y los indicadores que dependen del usuario autenticado (`isMine`, `likedByMe`) se calculan comparando atributos ya cargados, sin Gate ni consultas adicionales.
- Los listados que serializan contadores de relaciones los precargan con `withCount()` en el query; el accesor del modelo reutiliza `{relation}_count` cuando existe y consulta solo si no está precargado, para que el costo de una página sea constante.

## Ejemplo canónico: módulo `Post`

```
app/Modules/Post/
├── Controllers/PostController.php
├── Database/
│   ├── Factories/PostFactory.php
│   └── Seeders/PostSeeder.php
├── Models/Post.php
├── Providers/
│   ├── PostServiceProvider.php
│   └── PostRouteServiceProvider.php
├── Requests/PostRequest.php
├── Resources/PostResource.php
├── Tests/
│   ├── Crud/
│   ├── Database/
│   ├── Models/
│   ├── Requests/
│   └── Resources/
└── routes/routes.php
```

Usa este módulo como referencia al crear nuevos. `app/Modules/Comment/` es el segundo ejemplo: agrega `Concerns/`, `Enums/`, `Policies/`, `Props/` y `Tests/Security/`. `app/Modules/Like/` es el tercero y muestra un módulo con `Database/Migrations/` movidas desde la carpeta global, un solo controlador con una acción por tipo de likeable (comentarios y publicaciones) y `routes/routes.php`.
