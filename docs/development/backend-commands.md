# Comandos del Backend

Referencia de los comandos Composer más usados para mantener consistencia y calidad en el código del backend.

> ⚠️ **Mantenimiento de la doc**: cuando agregues, modifiques o elimines un comando, script, alias de Composer o workflow del backend, actualizá este documento en el mismo PR/commit. La doc es la fuente de verdad de los comandos y no debe quedar desincronizada con `composer.json` o los scripts de CI.

## 1. Comandos esenciales (antes de cada commit)

Estos cuatro son los que mantienen la consistencia del proyecto. **Los agentes de IA deben ejecutarlos en orden** antes de finalizar una tarea backend o hacer commit.

### `composer pint-dirty`

```bash
composer pint-dirty
```

Formatea **solo los archivos PHP modificados** (no commiteados) con Laravel Pint siguiendo las reglas del proyecto.

- ⚡ Más rápido que `pint-fix` porque ignora archivos sin cambios
- ✅ Corrige estilo automáticamente
- 🚫 **No falla** el commit si hay issues, solo los arregla

**Cuándo:** después de modificar PHP, antes de commit.

### `composer phpstan`

```bash
composer phpstan
```

Análisis estático a `level: max` con cobertura de tipos al 100%.

- 🔍 Detecta errores de tipo, argumentos incorrectos, returns inconsistentes
- 📊 Cobertura 100% requerida en return, param, property, constant, declare
- ❌ **Falla** con exit code != 0 si encuentra issues

**Cuándo:** después de modificar PHP, antes de commit. Si falla, corrige antes de commitear.

### `composer rector-clear-cache`

```bash
composer rector-clear-cache
```

Borra la caché de Rector (`storage/rector`, ignorada por git).

- 🧹 Obliga a que la siguiente ejecución analice todos los archivos
- ⚠️ Rector guarda por archivo que "no hay cambios" y no invalida ese resultado cuando cambian los tipos de **otros** archivos (p. ej. los helpers de `tests/Pest.php` o un modelo). Reglas como `RenameVariableToMatchMethodCallReturnTypeRector` dependen de esos tipos: en SOC-41 `composer rector-dry` salía limpio en local y el CI (`backend-rector`, sin esa caché) marcaba 5 archivos de tests.

**Cuándo:** siempre **antes** de `composer rector-dry`, al inicio del gate de Rector. Ejecuta después Rector sobre todo el proyecto (sin pasar rutas): con una ruta parcial puede no detectar lo que el CI sí marca.

### `composer rector-dry`

```bash
composer rector-dry
```

Muestra qué cambios aplicaría Rector **sin tocar archivos**.

- 👀 Preview de cambios (PHP 8.5 syntax, Laravel-idiomatic patterns)
- 🛡️ Modo seguro — no modifica nada

**Cuándo:** después de cualquier modificación PHP. Es obligatorio para agentes de IA, aunque el cambio no sea un refactor grande.

### `composer rector`

```bash
composer rector
```

Aplica las transformaciones de Rector.

- ✏️ Reescribe archivos
- ⚠️ Revisa el diff antes de commitear (`git diff`)

**Cuándo:** después de revisar `rector-dry`, siempre como parte del gate obligatorio para tareas que modifiquen PHP.

## 2. Orden recomendado antes de commit

```bash
# 1. Formatear archivos modificados
composer pint-dirty

# 2. Verificar tipos
composer phpstan

# 3. Limpiar la caché de Rector y hacer el preview obligatorio (todo el proyecto, sin rutas)
composer rector-clear-cache
composer rector-dry
# Aplicar el gate obligatorio:
composer rector
# Después de aplicar Rector, revalidar tipos
composer phpstan   # Rector puede introducir issues de tipos, revalida
```

## 3. Otros comandos útiles

### `composer test`

```bash
composer test
```

Limpia la config cacheada y ejecuta la suite completa de Pest **en paralelo** (SOC-59): `php -d memory_limit=2G artisan test --parallel --passthru-php="'-d' 'memory_limit=1G'"`.

- ⚡ Laravel reparte los archivos entre un proceso por núcleo y crea una base MySQL por proceso (`social-hub-testing_test_1`, `_test_2`, …) que reutiliza en las siguientes ejecuciones; el usuario de la base debe poder crear bases (en local y en el servicio MySQL del CI lo hace `root`). Para recrearlas tras cambiar migraciones añade `--recreate-databases`
- 🧠 Cada worker de paratest es un proceso PHP nuevo que no hereda el `-d` del proceso padre, por eso el límite de 1G se le pasa con `--passthru-php`. `artisan test -d …` no sirve con `--parallel`: paratest no acepta esa opción
- 🐞 Para depurar un fallo, córrelo en serie, donde el orden de los tests es estable: `php -d memory_limit=2G vendor/bin/pest --compact <ruta>` (la suite completa en serie necesita el límite de 2G)
- ⚠️ Un archivo de test sin `namespace` no puede tener `use` de una clase global (`use LogicException;`): PHP lo ignora con un warning, que en los workers de paratest Laravel convierte en `ErrorException` y aborta la ejecución. Escribe la clase sin `use`
- ⏱️ Referencia de SOC-59 en un equipo de 14 núcleos: 1211 tests en 13–25 s en paralelo (según la carga de la máquina) frente a ~56 s en serie
- ⚠️ **No** usar para tests rápidos — limpia caché antes de correr
- 🚫 No acepta argumentos (`composer test -- <ruta>` falla porque Composer los reenvía también a `config:clear`); para filtrar usa `php artisan test --compact <ruta>`
- ✅ Útil antes de abrir PR; es el comando que ejecuta el workflow de CI

### `composer pint-ci`

```bash
composer pint-ci
```

Ejecuta `vendor/bin/pint --test` sobre **todo el repositorio** en modo solo lectura, para CI.

- ❌ Falla (código `1`) si algún archivo no cumple el estilo del proyecto y lista cuáles
- 🚫 No modifica archivos; para corregirlos usa `composer pint-fix` o `composer pint-dirty`
- 🔎 Antes de SOC-48 usaba `--dirty`, que en un checkout limpio de CI no revisa ningún archivo; ahora revisa todo el código

**Cuándo:** lo ejecuta el workflow `Backend Code Quality Pint`; localmente solo hace falta si quieres reproducir el gate.

### `composer doctor`

```bash
composer doctor
```

Ejecuta los diagnósticos de Laravel Doctor en formato legible para desarrollo local.

- 🔎 Revisa configuración, entorno, Composer, base de datos, cache, queue, sesiones y storage
- 🛡️ No aplica reparaciones automáticamente porque usa `--no-interaction` y no incluye `--fix`
- ⚠️ Puede fallar si los servicios configurados localmente no están disponibles

**Cuándo:** como último gate local de una tarea backend, después de completar implementación, tests, formatters, análisis estático, Rector y las demás verificaciones aplicables. No ejecutes otros gates después de `composer doctor`.

### `composer doctor-ci`

```bash
composer doctor-ci
```

Ejecuta Laravel Doctor con formato de anotaciones de GitHub para el workflow de CI.

- ❌ Falla cuando Doctor encuentra diagnósticos con estado `fail` o `error`
- 📋 Produce anotaciones que GitHub Actions puede mostrar directamente en el job
- 🚫 No aplica reparaciones automáticas

**Cuándo:** el workflow `Backend Code Quality Laravel Doctor` lo ejecuta con anotaciones para GitHub Actions. Su ejecución ocurre en CI al llegar a este paso; no sustituye el gate local final ni es un chequeo intermedio durante la implementación.

### `composer phpcpd`

```bash
composer phpcpd
```

Ejecuta PHPCPD Next con el preset de Laravel para detectar código duplicado.

- 🧪 Es una herramienta experimental y actualmente no bloquea CI
- ❗ Devuelve código `1` cuando encuentra clones, aunque el análisis sea válido
- 🔍 Los hallazgos deben revisarse manualmente porque tests paralelos y configuraciones repetitivas pueden ser intencionales
- 📊 En SOC-48 detectó 53 clones, 889 líneas duplicadas y 4.14% de duplicación; tras la revisión y limpieza quedan 28 clones, 440 líneas y 2.09%. Los que permanecen son repetición intencional de escenarios de prueba o estructura propia de clases y se justifican en SOC-48

**Cuándo:** durante la evaluación de duplicación antes de decidir si se convierte en un gate obligatorio.

### `composer sloppy`

```bash
composer sloppy
```

Ejecuta [Sloppy](https://github.com/Heyosseus/sloppy) (`heyosseus/sloppy`), un análisis estático experimental de deuda típica del código generado por agentes de IA.

- 🧪 Es experimental y **no está en CI** (igual que PHPCPD), mientras se evalúa
- 📖 Es de solo lectura: lista los hallazgos por riesgo y una puntuación sobre 100; no modifica archivos ni requiere configuración
- ❗ Devuelve código `1` cuando encuentra hallazgos, aunque el análisis sea válido
- 🚫 No uses `sloppy fix` ni `sloppy agents install` sin revisarlo: el primero aplica Rector y Pint sobre los archivos y el segundo escribe hooks y reglas en los archivos de agentes
- 📊 Primera ejecución en SOC-48: puntuación 96/100 con 3 hallazgos (`TrustedDeviceIndexController::__invoke`, `AdminUserRoleCommand::handle` y `User` con una notificación en el modelo), sin acción por ahora

**Cuándo:** durante la evaluación, para decidir si se convierte en un gate.

### `php artisan test --compact`

```bash
php artisan test --compact
php artisan test --compact tests/Feature/PostTest.php
php artisan test --compact --filter=testName
```

Suite de tests en formato compacto. Acepta filtros por archivo o por nombre.

### `composer ide-helper`

```bash
composer ide-helper
```

Regenera los archivos de ayuda para IDE:

- `_ide_helper.php` — anotaciones para clases de Laravel
- `_ide_helper_models.php` — PHPDoc de modelos Eloquent (incluye `@property`, `@method`)

**Cuándo:** después de:

- Agregar un modelo nuevo
- Cambiar relaciones o propiedades fillable de un modelo
- Actualizar dependencias que afecten tipos

Después de regenerar, **hacé commit** del archivo actualizado para que PHPStan y tu IDE lo lean.

### `composer setup`

```bash
composer setup
```

Configuración inicial del proyecto en una instalación nueva:

1. `composer install`
2. Copia `.env.example` → `.env`
3. Genera APP_KEY
4. Ejecuta migraciones
5. `npm install`
6. `npm run build`

**Cuándo:** primera vez que clonas el repo, o después de un reset total.

### `composer dev`

```bash
composer dev
```

Levanta el entorno de desarrollo completo en paralelo (con colores por proceso):

- 🟦 `php artisan serve` (servidor HTTP)
- 🟪 `php artisan queue:listen` (cola)
- 🟥 `php artisan pail` (logs en tiempo real)
- 🟧 `npm run dev` (Vite con HMR)

**Cuándo:** sesión de desarrollo normal. Reemplaza el `php artisan serve` manual.

### `composer phpstan-clear-cache`

```bash
composer phpstan-clear-cache
```

Limpia la caché de resultados de PHPStan.

**Cuándo:** si PHPStan reporta errores "raros" que parecen cacheados, o después de cambios grandes en `phpstan.neon`.

## 4. Workflows de CI del backend

Cada gate corre en su propio workflow de GitHub Actions, en paralelo, con los mismos triggers (`push` y `pull_request` sobre `main` y `develop`) y PHP 8.5:

| Workflow (`name`)                      | Archivo                                   | Comando               | Preparación específica                                    |
| -------------------------------------- | ----------------------------------------- | --------------------- | --------------------------------------------------------- |
| Backend Code Quality Test              | `backend-code-quality-test.yml`           | `composer test`       | Servicio MySQL 8.4 y `.env.testing` desde `.env.testing.example` |
| Backend Code Quality Pint              | `backend-code-quality-pint.yml`           | `composer pint-ci`    | Ninguna                                                   |
| Backend Code Quality Rector            | `backend-code-quality-rector.yml`         | `composer rector-dry` | Caché de `storage/rector`                                 |
| Backend Code Quality PHPStan           | `backend-code-quality-phpstan.yml`        | `composer phpstan`    | `.env.ci` + SQLite + migraciones + `composer ide-helper`, y caché de `storage/phpstan` |
| Backend Code Quality Laravel Doctor    | `backend-code-quality-doctor.yml`         | `composer doctor-ci`  | `.env.ci` + SQLite + migraciones                          |

- PHPStan **necesita** `composer ide-helper` antes de analizar: `phpstan.neon` escanea `_ide_helper_models.php`, que se genera y no está en git.
- PHPCPD no está en CI (experimental).
- Si cambias un workflow o el nombre de un check, actualiza también los checks requeridos de la rama protegida en GitHub.

## 5. Resumen rápido

| Comando                        | Para qué                       | Cuándo                          |
| ------------------------------ | ------------------------------ | ------------------------------- |
| `composer pint-dirty`          | Formatear archivos modificados | Antes de commit                 |
| `composer phpstan`             | Análisis estático              | Antes de commit                 |
| `composer rector-clear-cache`  | Borrar la caché de Rector      | Antes de `rector-dry`           |
| `composer rector-dry`          | Preview Rector                 | Después de cualquier cambio PHP |
| `composer rector`              | Aplicar Rector                 | Después de `rector-dry`         |
| `composer test`                | Suite completa Pest (paralelo) | Antes de PR                     |
| `composer pint-ci`             | Pint de solo lectura, todo el repo | Gate del workflow de CI     |
| `composer doctor`              | Diagnósticos locales Laravel   | Último gate local de tarea backend |
| `composer doctor-ci`           | Doctor con anotaciones GitHub  | Gate del workflow de CI         |
| `composer phpcpd`              | Detección experimental clones  | Evaluar duplicación             |
| `composer sloppy`              | Análisis experimental de deuda | Evaluar (no está en CI)         |
| `php artisan test --compact`   | Tests rápidos con filtro       | Después de cada test modificado |
| `composer ide-helper`          | Regenerar helpers IDE          | Después de cambiar modelos      |
| `composer setup`               | Setup inicial                  | Primera vez                     |
| `composer dev`                 | Entorno dev completo           | Sesión de desarrollo            |
| `composer phpstan-clear-cache` | Limpiar caché PHPStan          | Si hay errores fantasma         |
