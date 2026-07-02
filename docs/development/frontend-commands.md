# Comandos del Frontend

Referencia de los comandos npm más usados para mantener consistencia y calidad en el código del frontend.

## 1. Comandos esenciales (antes de cada commit)

Tres comandos — equivalentes a `pint-dirty`/`phpstan`/`rector-dry` del backend. **Ejecuta los tres en orden** antes de hacer commit.

### `npm run format:check`

```bash
npm run format:check
```

Verifica que el formato Prettier esté aplicado en `resources/`.

- :lupa: **Solo lee**, no modifica archivos
- :x: Falla con exit code != 0 si hay archivos mal formateados

**Cuándo:** después de modificar TS/TSX/CSS/JSON, antes de commit.

### `npm run lint:check`

```bash
npm run lint:check
```

Ejecuta ESLint sin auto-fix. Revisa reglas de:

- React hooks
- TypeScript
- Imports
- Estilo (Prettier como regla ESLint vía `eslint-plugin-prettier`)
- Sorting (perfectionist, trivago)

- :lupa: **Solo lee**, no modifica archivos
- :x: Falla con exit code != 0 si hay violations

**Cuándo:** después de modificar TS/TSX, antes de commit.

### `npm run types`

```bash
npm run types
```

Ejecuta `tsc --noEmit` — verifica tipos TypeScript sin generar output.

- :lupa: Detecta errores de tipo, firmas incorrectas, propiedades faltantes
- :x: Falla con exit code != 0 si encuentra issues

**Cuándo:** después de modificar TS/TSX, antes de commit. Especialmente importante si trabajas con tipos de Laravel (resources, wayfinder).

## 2. Orden recomendado antes de commit

```bash
# 1. Verificar formato
npm run format:check

# 2. Verificar linting
npm run lint:check

# 3. Verificar tipos
npm run types
```

Si alguno falla:

```bash
# Auto-fix formato
npm run format

# Auto-fix linting
npm run lint

# Para errores de tipo, hay que corregirlos manualmente
# (TypeScript no auto-arregla)
```

## 3. Otros comandos útiles

### `npm run dev`

```bash
npm run dev
```

Levanta Vite con HMR (Hot Module Replacement). Servidor de desarrollo que recompila al guardar.

**Cuándo:** sesión de desarrollo frontend normal. En este proyecto se ejecuta en paralelo con `composer dev`.

### `npm run build`

```bash
npm run build
```

Compila el bundle de producción con Vite. Genera assets optimizados en `public/build/`.

**Cuándo:**

- Antes de deploy
- Antes de commitear cambios grandes en configuración de Vite/Tailwind
- En CI para validar que el build no rompe

### `npm run build:ssr`

```bash
npm run build:ssr
```

Compila el bundle para Server-Side Rendering. Útil si activas SSR en `config/inertia.php`.

**Cuándo:** solo si usas SSR. Si no, ignora este comando.

### `npm run format`

```bash
npm run format
```

Aplica Prettier a **todo** `resources/` (no solo modificado).

- :advertencia: Modifica muchos archivos — útil para reformatear después de cambios en `.prettierrc`
- :alto_voltaje: Para cambios locales, mejor usar formato on-save en VSCode

### `npm run lint`

```bash
npm run lint
```

Ejecuta ESLint con `--fix`. Corrige automáticamente:

- Problemas de estilo (Prettier)
- Imports duplicados
- Sorting (si perfectionist lo tiene configurado)
- Algunas reglas React

**Cuándo:** después de que `lint:check` falle y quieras auto-fix.

### `shadcn` (no es script npm)

```bash
npx shadcn@latest add <componente>
```

Añade componentes de shadcn/ui al proyecto. El CLI los copia a `resources/js/shared/components/shadcn/ui/`.

**Cuándo:** cuando necesites un nuevo componente UI del set de shadcn (Button, Dialog, Select, etc.).

## 4. Comparación con comandos del backend

| Backend               | Frontend               | Qué hace             |
| --------------------- | ---------------------- | -------------------- |
| `composer pint-dirty` | `npm run format:check` | Verifica formato     |
| `composer phpstan`    | `npm run types`        | Verifica tipos       |
| `composer rector-dry` | `npm run lint:check`   | Verifica reglas/lint |
| `composer pint`       | `npm run format`       | Aplica formato       |
| `composer rector`     | `npm run lint`         | Aplica fixes         |
| `composer ide-helper` | (no aplica)            | Genera helpers IDE   |

> :bombilla: **Diferencia clave:** ESLint con `--fix` aplica automáticamente reglas de formato (porque Prettier corre dentro de ESLint vía `eslint-plugin-prettier`). Backend y frontend tienen filosofías similares pero herramientas distintas.

## 5. Resumen rápido

| Comando                   | Para qué                 | Cuándo                            |
| ------------------------- | ------------------------ | --------------------------------- |
| `npm run format:check`    | Verificar formato        | Antes de commit                   |
| `npm run lint:check`      | Verificar linting        | Antes de commit                   |
| `npm run types`           | Verificar tipos TS       | Antes de commit                   |
| `npm run format`          | Aplicar formato          | Después de fallos de format:check |
| `npm run lint`            | Auto-fix linting         | Después de fallos de lint:check   |
| `npm run dev`             | Servidor dev con HMR     | Sesión de desarrollo              |
| `npm run build`           | Build producción         | Antes de deploy                   |
| `npm run build:ssr`       | Build con SSR            | Solo si usas SSR                  |
| `npx shadcn@latest add X` | Añadir componente shadcn | Cuando necesites UI nuevo         |

## 6. Configuración on-save (VSCode)

Con la config de `.vscode/settings.json`, todo esto corre **automáticamente al guardar**:

- Prettier formatea el archivo
- ESLint aplica auto-fix

Pero las versiones `*:check` siguen siendo necesarias en CI y pre-commit porque validan sin modificar.
