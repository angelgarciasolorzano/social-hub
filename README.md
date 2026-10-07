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
