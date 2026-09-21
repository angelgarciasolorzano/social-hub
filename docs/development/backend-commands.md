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

# 3. Preview obligatorio de Rector
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

Limpia la config cacheada y ejecuta la suite completa de PHPUnit.

- ⚠️ **No** usar para tests rápidos — limpia caché antes de correr
- ✅ Útil antes de abrir PR o en CI

### `composer doctor`

```bash
composer doctor
```

Ejecuta los diagnósticos de Laravel Doctor en formato legible para desarrollo local.

- 🔎 Revisa configuración, entorno, Composer, base de datos, cache, queue, sesiones y storage
- 🛡️ No aplica reparaciones automáticamente porque usa `--no-interaction` y no incluye `--fix`
- ⚠️ Puede fallar si los servicios configurados localmente no están disponibles

**Cuándo:** al validar la salud del entorno local o investigar problemas de configuración.

### `composer doctor-ci`

```bash
composer doctor-ci
```

Ejecuta Laravel Doctor con formato de anotaciones de GitHub para el workflow de CI.

- ❌ Falla cuando Doctor encuentra diagnósticos con estado `fail` o `error`
- 📋 Produce anotaciones que GitHub Actions puede mostrar directamente en el job
- 🚫 No aplica reparaciones automáticas

**Cuándo:** como gate obligatorio del workflow de calidad del backend.

### `composer phpcpd`

```bash
composer phpcpd
```

Ejecuta PHPCPD Next con el preset de Laravel para detectar código duplicado.

- 🧪 Es una herramienta experimental y actualmente no bloquea CI
- ❗ Devuelve código `1` cuando encuentra clones, aunque el análisis sea válido
- 🔍 Los hallazgos deben revisarse manualmente porque tests paralelos y configuraciones repetitivas pueden ser intencionales
- 📊 En la primera ejecución detectó 8 clones, 119 líneas duplicadas y 1.14% de duplicación

**Cuándo:** durante la evaluación de duplicación antes de decidir si se convierte en un gate obligatorio.

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

## 4. Resumen rápido

| Comando                        | Para qué                       | Cuándo                          |
| ------------------------------ | ------------------------------ | ------------------------------- |
| `composer pint-dirty`          | Formatear archivos modificados | Antes de commit                 |
| `composer phpstan`             | Análisis estático              | Antes de commit                 |
| `composer rector-dry`          | Preview Rector                 | Después de cualquier cambio PHP |
| `composer rector`              | Aplicar Rector                 | Después de `rector-dry`         |
| `composer test`                | Suite completa PHPUnit         | Antes de PR                     |
| `composer doctor`              | Diagnósticos locales Laravel   | Validar entorno local           |
| `composer doctor-ci`           | Doctor con anotaciones GitHub  | Gate obligatorio en CI          |
| `composer phpcpd`              | Detección experimental clones  | Evaluar duplicación             |
| `php artisan test --compact`   | Tests rápidos con filtro       | Después de cada test modificado |
| `composer ide-helper`          | Regenerar helpers IDE          | Después de cambiar modelos      |
| `composer setup`               | Setup inicial                  | Primera vez                     |
| `composer dev`                 | Entorno dev completo           | Sesión de desarrollo            |
| `composer phpstan-clear-cache` | Limpiar caché PHPStan          | Si hay errores fantasma         |
