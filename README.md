# social-hub

Una red social construida **solo como proyecto de práctica**: el objetivo es aprender y explorar todo lo posible de **Laravel**, **Inertia** y **React**. No es un producto comercial ni está pensado para producción.

**Stack:** PHP 8.5, Laravel 13 (Fortify, Reverb, Wayfinder), Filament 5, Inertia v3, React 19, TypeScript, Tailwind CSS 4 y shadcn/ui. Calidad con Pest, PHPStan, Pint, Rector, ESLint y Prettier.

## Requisitos

- PHP 8.5 con las extensiones habituales de Laravel.
- [Composer](https://getcomposer.org) 2.
- Node.js 22 (la versión del CI) y npm.
- **MySQL** corriendo en local. La aplicación y los tests utilizan MySQL, y `composer run setup` crea ambas bases de datos.

## Arrancar el proyecto

```bash
git clone <url-del-repositorio> social-hub
cd social-hub
composer run setup
```

`composer run setup` instala las dependencias de PHP y JavaScript, crea `.env` y `.env.testing` con su `APP_KEY`, crea las bases de datos de la aplicación y de pruebas en MySQL, corre las migraciones y compila el frontend.

Carga datos de ejemplo (opcional):

```bash
php artisan migrate:fresh --seed
```

La cuenta de prueba es `angel@gmail.com` con la contraseña `password`.

Levanta el entorno de desarrollo:

```bash
composer run dev
```

Arranca el servidor de Laravel, Reverb, la cola, los logs y Vite. La aplicación queda en <http://localhost:8000> y el panel de administración en <http://localhost:8000/admin>.

Comprueba que todo funciona:

```bash
composer test
```

## Problemas frecuentes

- **`composer run setup` falla al crear una base:** MySQL no está corriendo o las credenciales no tienen permiso para crear bases. Inícialo o revisa `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` en `.env` y `.env.testing`.
- **`composer run dev` falla al arrancar Reverb:** el puerto `8080` está ocupado (cambia `REVERB_PORT` en `.env`) o a tu `.env` le faltan las variables `REVERB_*` y `VITE_REVERB_*`; cópialas de `.env.example`.

## Documentación

La documentación técnica está en inglés:

- [CLAUDE.md](CLAUDE.md): mapa del proyecto, comandos, convenciones e invariantes.
- [docs/development/](docs/development): [variables de entorno y servicios](docs/development/environment.md) (S3, backups, Reverb), [comandos del backend](docs/development/backend-commands.md) y [del frontend](docs/development/frontend-commands.md), y [restaurar un backup](docs/development/database-backup-restore.md).
- [docs/architecture/](docs/architecture): cómo crear un módulo ([backend](docs/architecture/backend.md), [frontend](docs/architecture/frontend.md)) y un documento por módulo en [modules/](docs/architecture/modules).
- [AGENTS.md](AGENTS.md) y [.ai/rules/](.ai/rules): reglas para agentes de IA.

Los commits siguen [Conventional Commits](https://www.conventionalcommits.org) y los PR usan la [plantilla del repositorio](.github/PULL_REQUEST_TEMPLATE/pull_request_template.md). Si cambias la estructura de módulos o las convenciones, actualiza `CLAUDE.md` y el documento del módulo en el mismo PR.
