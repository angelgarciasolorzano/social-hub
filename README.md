# social-hub

Una red social construida **solo como proyecto de práctica**: el objetivo del proyecto es aprender y explorar todo lo que se pueda de **Laravel**, **Inertia** y **React**. No es un producto comercial ni está pensado para producción.

## Stack

- **Backend:** PHP 8.5, Laravel 13, Laravel Fortify (autenticación y 2FA), Laravel Reverb (tiempo real), Laravel Wayfinder (rutas tipadas para el frontend), Spatie Media Library y Spatie Data.
- **Frontend:** Inertia v3, React 19, TypeScript, Tailwind CSS 4, shadcn/ui y Vite.
- **Calidad:** Pest, PHPStan (nivel máximo), Laravel Pint, Rector, ESLint y Prettier.

## Arquitectura

El dominio está dividido en módulos autocontenidos. Cada módulo tiene sus propios controllers, models, providers, requests, resources y rutas (y, cuando aplica, factories, enums y seeders):

- **Auth:** login, registro, verificación de email, recuperación de contraseña, 2FA y dispositivos de confianza.
- **User:** perfil y ajustes de cuenta.
- **Home:** páginas de inicio.
- **Post:** publicaciones con imágenes.
- **Comment:** comentarios (en publicaciones y en otros comentarios).
- **Like:** "me gusta" en publicaciones y comentarios.
- **Friendship:** solicitudes y relaciones de amistad.
- **MediaLibrary:** personalizaciones de archivos multimedia.

Los módulos se están migrando de forma incremental a `app/Modules/<Dominio>/`. Por ahora solo **Post** vive ahí; el resto sigue en `app/<Dominio>/`.

En el frontend, `resources/js/modules/` refleja los dominios del backend y `resources/js/shared/` contiene el código reutilizable.

Para más detalle consulta [CLAUDE.md](CLAUDE.md), [AGENTS.md](AGENTS.md) y la carpeta [docs/](docs).

## Requisitos previos

- PHP ^8.5 con las extensiones habituales de Laravel.
- [Composer](https://getcomposer.org) 2.
- Node.js 22 (la versión que usa el CI) y npm.
- **MySQL** corriendo en local. El entorno de desarrollo usa SQLite por defecto, pero `composer run setup` crea la base de datos de pruebas en MySQL (`db:create-testing`) y la suite de tests la necesita. Sin MySQL ese paso falla.

## Instalación

1. Clona el repositorio y entra en la carpeta:

   ```bash
   git clone <url-del-repositorio> social-hub
   cd social-hub
   ```

2. Ejecuta el script de instalación:

   ```bash
   composer run setup
   ```

   El script hace, en orden:

   - instala las dependencias de PHP (`composer install`);
   - copia `.env.example` a `.env` y `.env.testing.example` a `.env.testing` si no existen;
   - genera la `APP_KEY` de ambos entornos;
   - crea la base de datos de pruebas en MySQL (`php artisan --env=testing db:create-testing`);
   - corre las migraciones (`php artisan migrate --force`);
   - instala las dependencias de JavaScript y compila el frontend (`npm install` y `npm run build`).

3. Si prefieres hacerlo a mano, estos son los mismos pasos por separado:

   ```bash
   composer install
   cp .env.example .env
   cp .env.testing.example .env.testing
   php artisan key:generate
   php artisan key:generate --env=testing
   php artisan --env=testing db:create-testing
   php artisan migrate --force
   npm install
   npm run build
   ```

4. Levanta el entorno de desarrollo:

   ```bash
   composer run dev
   ```

   Esto arranca en paralelo el servidor de Laravel (`php artisan serve`), el servidor de Reverb, el worker de la cola, los logs (`pail`) y Vite. La aplicación queda disponible en <http://localhost:8000>.

5. Verifica la instalación:

   ```bash
   composer test
   composer doctor
   ```

### Problemas frecuentes

- **`composer run setup` falla en `db:create-testing`:** MySQL no está corriendo o no es accesible con los datos de `.env.testing` (por defecto `root` sin contraseña en `127.0.0.1:3306`, base `social-hub-testing`). Inicia MySQL o ajusta `DB_USERNAME` y `DB_PASSWORD` en `.env.testing` y vuelve a ejecutar `php artisan --env=testing db:create-testing`.
- **`composer run dev` falla al arrancar Reverb:** revisa que el puerto `8080` esté libre o cambia `REVERB_PORT` en `.env`, y que las variables `REVERB_*` y `VITE_REVERB_*` existan (vienen en `.env.example`). Si tu `.env` es anterior a ese cambio, cópialas desde `.env.example`.

## Variables de entorno

Todas se definen en `.env`, que se crea a partir de `.env.example`. Las más relevantes:

- **Base de datos, sesión, cola y caché:** por defecto usan SQLite (`DB_CONNECTION=sqlite`) y el driver `database` para `SESSION_DRIVER`, `QUEUE_CONNECTION` y `CACHE_STORE`, así que no necesitas servicios extra para desarrollo. Los tests usan MySQL (ver `.env.testing`).
- **Broadcasting:** `BROADCAST_CONNECTION=log` por defecto, por lo que los eventos en tiempo real solo se registran en el log. Para transmitirlos por Reverb cambia a `BROADCAST_CONNECTION=reverb`; las variables `REVERB_*` (servidor) y `VITE_REVERB_*` (cliente) de `.env.example` ya apuntan a `localhost:8080`. Los valores de `REVERB_APP_ID`, `REVERB_APP_KEY` y `REVERB_APP_SECRET` son de ejemplo para desarrollo local: cámbialos si expones el servidor fuera de tu máquina.
- **Dispositivos de confianza (opcionales):** `SOCIALHUB_AUTH_TRUSTED_DEVICE_COOKIE_LIFETIME_MINUTES` define cuánto dura la cookie de un dispositivo de confianza (por defecto `43200`, es decir 30 días) y `SOCIALHUB_AUTH_TRUSTED_DEVICE_PURGE_AFTER_DAYS` cuántos días se conservan los dispositivos revocados antes de borrarse definitivamente (por defecto `90`). Están vacías en `.env.example`, así que se aplican esos valores por defecto.

## Backups de base de datos

La aplicación crea backups cifrados de la base MySQL en el disco privado `backups`, separado del disco S3 general. Configura las nueve variables `BACKUP_*` de `.env.example` en el entorno del servidor. `BACKUP_S3_ACCESS_KEY_ID` y `BACKUP_S3_SECRET_ACCESS_KEY` deben pertenecer a una identidad IAM dedicada; `BACKUP_ARCHIVE_PASSWORD` es obligatorio para ejecutar el job y debe guardarse como secreto de despliegue. Configura `BACKUP_NOTIFICATION_EMAIL` con una dirección válida para recibir los avisos. No guardes credenciales ni passphrases en el repositorio ni las expongas con variables `VITE_*`.

El host que procesa los jobs necesita el cliente `mysqldump` compatible con la versión de MySQL configurada. Los backups se encolan y requieren un worker con una conexión de cola asíncrona, por ejemplo `php artisan queue:work`; no uses `QUEUE_CONNECTION=sync` en producción. También requiere el scheduler de Laravel ejecutándose cada minuto mediante cron (`php artisan schedule:run`) o un proceso persistente (`php artisan schedule:work`). La aplicación programa el backup diario a la 01:00 y el monitoreo a las 04:30, según la zona horaria configurada. La limpieza se encola después de cada backup exitoso.

El bucket debe ser privado y tener versionado habilitado. Limita la política IAM a un bucket dedicado y al prefijo de backups: `s3:ListBucket` sobre el ARN del bucket con una condición `s3:prefix`; `s3:PutObject`, `s3:GetObject` y `s3:DeleteObject` sobre los objetos bajo ese prefijo. `GetObject` permite descargar los archivos; `DeleteObject` permite que la limpieza retire backups vencidos.

En un bucket con versionado, `DeleteObject` agrega un marcador de borrado; no elimina de inmediato la versión anterior. Configura S3 Lifecycle para eliminar versiones no actuales después de 30 días y retirar marcadores de borrado vencidos cuando ya no tengan versiones asociadas. La eliminación de versiones queda a cargo de Lifecycle, por lo que la identidad de la aplicación no necesita `s3:DeleteObjectVersion`. Consulta [la guía de Lifecycle para buckets versionados](https://docs.aws.amazon.com/AmazonS3/latest/userguide/lifecycle-configuration-examples.html) y [el ejemplo de política IAM limitada por prefijo](https://docs.aws.amazon.com/AmazonS3/latest/userguide/walkthrough1.html).

El monitoreo integrado comprueba la antigüedad y el almacenamiento de los backups en el destino. Como corre dentro de la misma aplicación, no detecta por sí solo una caída total del despliegue; para eso se necesita monitoreo externo.

## Calidad de código

Antes de abrir un PR, ejecuta los mismos checks que corre el CI. El backend tiene un workflow por gate en `.github/workflows/` (`backend-code-quality-test.yml`, `-pint.yml`, `-rector.yml`, `-phpstan.yml` y `-doctor.yml`). El frontend también separa sus gates en [Format](.github/workflows/frontend-code-quality-format.yml), [Lint](.github/workflows/frontend-code-quality-lint.yml) y [Types](.github/workflows/frontend-code-quality-types.yml). El detalle de los comandos está en [docs/development/frontend-commands.md](docs/development/frontend-commands.md).

**Backend**

```bash
vendor/bin/pint --dirty --format agent   # formatea solo los archivos PHP modificados
composer rector-dry                      # previsualiza las transformaciones de Rector
composer phpstan                         # análisis estático (nivel máximo)
composer test                            # suite de Pest (límite de memoria de 2G)
```

Si `composer rector-dry` propone cambios, aplícalos con `composer rector`.

**Frontend**

```bash
npm run lint:check     # ESLint (usa `npm run lint` para autocorregir)
npm run format:check   # Prettier (usa `npm run format` para formatear)
npm run types          # TypeScript sin emitir archivos
```

En CI, los tres gates generan Wayfinder antes de correr. Aunque ESLint excluye los archivos generados, Lint los necesita para resolver los imports del código fuente.

En [docs/development/](docs/development) hay una referencia más completa de comandos para backend y frontend.

## Estructura del proyecto

```text
app/
├── Modules/          # módulos migrados a la nueva arquitectura (hoy solo Post)
├── Auth/ User/ ...   # dominios que siguen en la arquitectura anterior
├── Providers/        # providers globales de la aplicación
└── Http/ Events/ ... # archivos globales (middleware, eventos, comandos)
resources/js/
├── modules/          # páginas y componentes por dominio (auth, home, post, setting, ...)
└── shared/           # componentes, hooks y utilidades reutilizables
docs/
├── architecture/     # guías de arquitectura de backend y frontend
├── development/      # referencia de comandos de desarrollo
└── github/           # plantillas de PR y de revisión
```

## Convenciones

- **Tests por módulo:** las pruebas específicas de un módulo viven en su carpeta `Tests/` (por ejemplo `app/Modules/Post/Tests/`); `tests/` se reserva para pruebas transversales.
- **Commits:** siguen [Conventional Commits](https://www.conventionalcommits.org) (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`).
- **Pull requests:** usan la plantilla de [.github/PULL_REQUEST_TEMPLATE/](.github/PULL_REQUEST_TEMPLATE/pull_request_template.md).
- **Documentación:** si cambias la estructura de módulos o las convenciones, actualiza `CLAUDE.md` en el mismo PR.
