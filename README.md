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
