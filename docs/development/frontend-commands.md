# Frontend Commands

Reference for the most used npm commands that keep the frontend code consistent and of good quality.

> ⚠️ **Keeping this document current**: when you add, change or remove a frontend command, npm script, alias or workflow, update this document in the same PR/commit. The docs are the source of truth for the commands and must not drift from `package.json` or the CI scripts.

## Reproducible install (CI)

### `npm run install:ci`

```bash
npm run install:ci
```

Wrapper around `npm ci --no-audit --no-fund`. Installs dependencies from `package-lock.json` **reproducibly and quickly**.

- 🔒 Fails when `package-lock.json` is out of sync with `package.json`
- ⏩ Faster than `npm install` (it resolves no dependencies)
- 🔇 No audit or funding output (clean CI logs)

**When:**

- In CI, in the [Format](../../.github/workflows/frontend-code-quality-format.yml), [Lint](../../.github/workflows/frontend-code-quality-lint.yml) and [Types](../../.github/workflows/frontend-code-quality-types.yml) workflows
- Locally, before pushing, to check that your `package-lock.json` is in sync

**Not for normal development**: use `npm install` when you add new dependencies.

## CI workflows

The three workflows run on `push` and `pull_request` to `main` and `develop`, and each reports its own gate:

- [Frontend Code Quality Format](../../.github/workflows/frontend-code-quality-format.yml) runs `npm run format:check`. It sets up PHP and Laravel and generates Wayfinder, because Prettier checks all of `resources/`, generated files included.
- [Frontend Code Quality Lint](../../.github/workflows/frontend-code-quality-lint.yml) runs `npm run lint:check`. It sets up PHP and Laravel, installs the npm dependencies and generates Wayfinder before the gate. ESLint excludes the generated files from the analysis, but it needs those modules to resolve the source code's imports.
- [Frontend Code Quality Types](../../.github/workflows/frontend-code-quality-types.yml) runs `npm run types`. It sets up PHP and Laravel and generates Wayfinder, which TypeScript needs to resolve those types.

## 1. Essential commands (before every commit)

Three commands, the counterparts of the backend's `pint-dirty` / `phpstan` / `rector-dry`. **Run all three in order** before committing.

### `npm run format:check`

```bash
npm run format:check
```

Checks that Prettier formatting is applied in `resources/`.

- 🔍 **Read-only**, changes no files
- ❌ Fails with a non-zero exit code when files are badly formatted

**When:** after changing TS/TSX/CSS/JSON, before committing.

### `npm run lint:check`

```bash
npm run lint:check
```

Runs ESLint without auto-fix. It checks rules for:

- React hooks
- TypeScript
- Imports
- Style (Prettier as an ESLint rule through `eslint-plugin-prettier`)
- Sorting (perfectionist, trivago)

- 🔍 **Read-only**, changes no files
- ❌ Fails with a non-zero exit code on violations

**When:** after changing TS/TSX, before committing.

### `npm run types`

```bash
npm run types
```

Runs `tsc --noEmit`: checks TypeScript types without emitting output.

- 🔍 Finds type errors, wrong signatures and missing properties
- ❌ Fails with a non-zero exit code when it finds issues

**When:** after changing TS/TSX, before committing. Especially important when working with types that come from Laravel (resources, Wayfinder).

## 2. Recommended order before committing

```bash
# 1. Check formatting
npm run format:check

# 2. Check linting
npm run lint:check

# 3. Check types
npm run types
```

If one fails:

```bash
# Auto-fix formatting
npm run format

# Auto-fix linting
npm run lint

# Type errors must be fixed by hand
# (TypeScript does not auto-fix)
```

## 3. Other useful commands

### `npm run dev`

```bash
npm run dev
```

Starts Vite with HMR (Hot Module Replacement): a development server that recompiles on save.

**When:** only when the PHP processes are already running. `composer run dev` already starts Vite together with the server, queue, logs and Reverb, so do not run both.

### `npm run build`

```bash
npm run build
```

Builds the production bundle with Vite. It writes optimized assets to `public/build/`.

**When:**

- Before opening a PR (it must compile)
- Before deploying
- Before committing large changes to the Vite/Tailwind configuration

### `npm run build:ssr`

```bash
npm run build:ssr
```

Builds the client bundle and the Server-Side Rendering bundle.

**When:** before opening a PR, together with `npm run build`: both must compile.

### `npm run format`

```bash
npm run format
```

Applies Prettier to **all** of `resources/` (not only modified files).

- ⚠️ Touches many files: useful to reformat after changing `.prettierrc`
- ⚡ For local changes, format-on-save in VS Code is more convenient

### `npm run lint`

```bash
npm run lint
```

Runs ESLint with `--fix`. It automatically fixes:

- Style problems (Prettier)
- Duplicated imports
- Sorting (where perfectionist is configured)
- Some React rules

**When:** after `lint:check` fails and you want the auto-fix.

### `shadcn` (not an npm script)

```bash
npx shadcn@latest add <component>
```

Adds shadcn/ui components to the project. The CLI copies them to `resources/js/shared/components/shadcn/ui/`.

**When:** when you need a new UI component from the shadcn set (Button, Dialog, Select, etc.). Never copy them by hand from the docs.

## 4. Comparison with the backend commands

| Backend               | Frontend               | What it does         |
| --------------------- | ---------------------- | -------------------- |
| `composer pint-dirty` | `npm run format:check` | Checks formatting    |
| `composer phpstan`    | `npm run types`        | Checks types         |
| `composer rector-dry` | `npm run lint:check`   | Checks rules / lint  |
| `composer pint-fix`   | `npm run format`       | Applies formatting   |
| `composer rector`     | `npm run lint`         | Applies fixes        |
| `composer ide-helper` | (not applicable)       | Generates IDE helpers |

> 💡 **Key difference:** ESLint with `--fix` also applies formatting rules (Prettier runs inside ESLint through `eslint-plugin-prettier`). Backend and frontend share a similar philosophy with different tools.

## 5. Quick summary

| Command                   | What for                  | When                              |
| ------------------------- | ------------------------- | --------------------------------- |
| `npm run install:ci`      | Reproducible install      | In CI or before pushing           |
| `npm run format:check`    | Check formatting          | Before committing                 |
| `npm run lint:check`      | Check linting             | Before committing                 |
| `npm run types`           | Check TS types            | Before committing                 |
| `npm run format`          | Apply formatting          | After `format:check` fails        |
| `npm run lint`            | Auto-fix linting          | After `lint:check` fails          |
| `npm run dev`             | Dev server with HMR       | Only without `composer run dev`   |
| `npm run build`           | Production build          | Before a PR and before deploying  |
| `npm run build:ssr`       | Build with SSR            | Before a PR                       |
| `npx shadcn@latest add X` | Add a shadcn component    | When you need new UI              |

## 6. On-save configuration (VS Code)

With the `.vscode/settings.json` configuration, this runs **automatically on save**:

- Prettier formats the file
- ESLint applies its auto-fix

The `*:check` versions are still needed in CI and before committing, because they validate without changing anything.
