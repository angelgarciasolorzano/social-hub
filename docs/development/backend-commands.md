# Backend Commands

Reference for the most used Composer commands that keep the backend code consistent and of good quality.

> ⚠️ **Keeping this document current**: when you add, change or remove a backend command, script, Composer alias or workflow, update this document in the same PR/commit. The docs are the source of truth for the commands and must not drift from `composer.json` or the CI scripts.

## 1. Essential commands (before every commit)

These keep the project consistent. **AI agents must run them in order** before finishing a backend task or committing.

### `composer pint-dirty`

```bash
composer pint-dirty
```

Formats **only the modified PHP files** (uncommitted) with Laravel Pint following the project's rules.

- ⚡ Faster than `pint-fix` because it skips unchanged files
- ✅ Fixes style automatically
- 🚫 **Does not fail** the commit when there are issues, it only fixes them

**When:** after changing PHP, before committing.

### `composer phpstan`

```bash
composer phpstan
```

Static analysis at `level: max` with 100% type coverage.

- 🔍 Finds type errors, wrong arguments and inconsistent returns
- 📊 100% coverage required on return, param, property, constant and declare
- ❌ **Fails** with a non-zero exit code when it finds issues

**When:** after changing PHP, before committing. If it fails, fix it before committing.

### `composer rector-clear-cache`

```bash
composer rector-clear-cache
```

Deletes Rector's cache (`storage/rector`, ignored by git).

- 🧹 Forces the next run to analyze every file
- ⚠️ Rector stores "no changes" per file and does not invalidate it when the types of **other** files change (e.g. the helpers in `tests/Pest.php` or a model). Rules such as `RenameVariableToMatchMethodCallReturnTypeRector` depend on those types: in SOC-41 `composer rector-dry` was clean locally while CI (`backend-rector`, without that cache) flagged 5 test files.

**When:** always **before** `composer rector-dry`, at the start of the Rector gate. Then run Rector over the whole project (no paths): a partial path can miss what CI flags.

### `composer rector-dry`

```bash
composer rector-dry
```

Shows what Rector would change **without touching files**.

- 👀 Preview of the changes (PHP 8.5 syntax, Laravel-idiomatic patterns)
- 🛡️ Safe mode: changes nothing

**When:** after any PHP change. Mandatory for AI agents, even when the change is not a large refactor.

### `composer rector`

```bash
composer rector
```

Applies Rector's transformations.

- ✏️ Rewrites files
- ⚠️ Review the diff before committing (`git diff`)

**When:** after reviewing `rector-dry`, always as part of the mandatory gate for tasks that change PHP.

## 2. Recommended order before committing

```bash
# 1. Format the modified files
composer pint-dirty

# 2. Check types
composer phpstan

# 3. Clear Rector's cache and run the mandatory preview (whole project, no paths)
composer rector-clear-cache
composer rector-dry
# Apply the mandatory gate:
composer rector
# After applying Rector, check types again
composer phpstan   # Rector can introduce type issues
```

## 3. Other useful commands

### `composer test`

```bash
composer test
```

Clears the cached config and runs the whole Pest suite **in parallel** (SOC-59): `php -d memory_limit=2G artisan test --parallel --passthru-php="'-d' 'memory_limit=1G'"`.

- ⚡ Laravel spreads the files over one process per core and creates one MySQL database per process (`social-hub-testing_test_1`, `_test_2`, …) that it reuses on later runs; the database user must be able to create databases (locally and in the CI MySQL service `root` does). To recreate them after changing migrations, add `--recreate-databases`
- 🧠 Each paratest worker is a new PHP process that does not inherit the parent's `-d`, so the 1G limit is passed with `--passthru-php`. `artisan test -d …` does not work with `--parallel`: paratest does not accept that option
- 🐞 To debug a failure, run it serially, where test order is stable: `php -d memory_limit=2G vendor/bin/pest --compact <path>` (the whole suite run serially needs the 2G limit)
- ⚠️ A test file without a `namespace` cannot `use` a global class (`use LogicException;`): PHP ignores it with a warning, which Laravel turns into an `ErrorException` in paratest workers, aborting the run. Write the class without `use`
- ⏱️ SOC-59 reference on a 14-core machine: 1211 tests in 13–25 s in parallel (depending on machine load) versus ~56 s serially
- ⚠️ **Not** for quick tests: it clears the cache before running
- 🚫 Takes no arguments (`composer test -- <path>` fails because Composer also forwards them to `config:clear`); to filter use `php artisan test --compact <path>`
- ✅ Useful before opening a PR; it is the command the CI workflow runs

### `composer pint-ci`

```bash
composer pint-ci
```

Runs `vendor/bin/pint --test` over the **whole repository** in read-only mode, for CI.

- ❌ Fails (code `1`) when any file does not follow the project's style, and lists them
- 🚫 Changes no files; to fix them use `composer pint-fix` or `composer pint-dirty`
- 🔎 Before SOC-48 it used `--dirty`, which checks no file in a clean CI checkout; now it checks all the code

**When:** the `Backend Code Quality Pint` workflow runs it; locally you only need it to reproduce the gate.

### `composer pint-fix`

```bash
composer pint-fix
```

Formats **every** PHP file of the repository with Pint. Prefer `pint-dirty` during a task.

### `composer doctor`

```bash
composer doctor
```

Runs Laravel Doctor's diagnostics in a readable format for local development.

- 🔎 Checks configuration, environment, Composer, database, cache, queue, sessions and storage
- 🛡️ Applies no repairs automatically: it uses `--no-interaction` and has no `--fix`
- ⚠️ Can fail when the locally configured services are not available

**When:** as the last local gate of a backend task, after implementation, tests, formatters, static analysis, Rector and the other applicable checks. Do not run other gates after `composer doctor`.

### `composer doctor-ci`

```bash
composer doctor-ci
```

Runs Laravel Doctor with GitHub annotation output for the CI workflow.

- ❌ Fails when Doctor finds diagnostics with status `fail` or `error`
- 📋 Produces annotations that GitHub Actions shows directly in the job
- 🚫 Applies no automatic repairs

**When:** the `Backend Code Quality Laravel Doctor` workflow runs it with GitHub Actions annotations. It runs in CI at that step; it does not replace the final local gate and is not an intermediate check during implementation.

### `composer phpcpd`

```bash
composer phpcpd
```

Runs PHPCPD Next with the Laravel preset to find duplicated code.

- 🧪 Experimental; it does not block CI today
- ❗ Returns code `1` when it finds clones, even though the analysis is valid
- 🔍 Findings need a manual review, because parallel tests and repetitive configuration can be intentional
- 📊 In SOC-48 it found 53 clones, 889 duplicated lines and 4.14% duplication; after the review and cleanup 28 clones, 440 lines and 2.09% remain. The rest are intentional repetition of test scenarios or the classes' own structure, justified in SOC-48

**When:** while evaluating duplication, before deciding whether it becomes a mandatory gate.

### `composer sloppy`

```bash
composer sloppy
```

Runs [Sloppy](https://github.com/Heyosseus/sloppy) (`heyosseus/sloppy`), an experimental static analysis of the debt typical of code generated by AI agents.

- 🧪 Experimental and **not in CI** (like PHPCPD) while it is evaluated
- 📖 Read-only: lists findings by risk and a score out of 100; changes no files and needs no configuration
- ❗ Returns code `1` when it finds issues, even though the analysis is valid
- 🚫 Do not use `sloppy fix` or `sloppy agents install` without reviewing them: the first applies Rector and Pint to the files and the second writes hooks and rules into the agent files
- 📊 First run in SOC-48: score 96/100 with 3 findings (`TrustedDeviceIndexController::__invoke`, `AdminUserRoleCommand::handle` and `User` with a notification on the model), no action for now

**When:** during the evaluation, to decide whether it becomes a gate.

### `php artisan test --compact`

```bash
php artisan test --compact
php artisan test --compact tests/Feature/PostTest.php
php artisan test --compact --filter=testName
```

The test suite in compact format. Accepts filters by file or by name.

### `composer ide-helper`

```bash
composer ide-helper
```

Regenerates the IDE helper files:

- `_ide_helper.php` — annotations for Laravel classes
- `_ide_helper_models.php` — PHPDoc for the Eloquent models (including `@property` and `@method`)

**When:** after:

- Adding a new model
- Changing a model's relations or fillable properties
- Updating dependencies that affect types

Both files are gitignored: regenerate them locally so PHPStan (which scans `_ide_helper_models.php`) and your IDE read the current models. CI generates them before running PHPStan.

### `composer setup`

```bash
composer setup
```

Initial setup of a fresh install:

1. `composer install`
2. Copies `.env.example` → `.env`
3. Generates `APP_KEY`
4. Creates the application database with `php artisan db:create-application`
5. Creates the testing database with `php artisan db:create-testing`
6. Runs the migrations
7. `npm install`
8. `npm run build`

The configured MySQL account must be allowed to create databases. `db:create-application` is restricted to the local environment and creates the database named by `DB_DATABASE` (which defaults to `laravel` in `.env.example`).

**When:** the first time you clone the repo, or after a full reset.

### `composer run dev`

```bash
composer run dev
```

Runs `php artisan dev`, which starts the roster of `Illuminate\Foundation\DevCommands` in parallel (colored per process):

- 🟦 `php artisan serve` (HTTP server)
- 🟪 `php artisan queue:listen` (processes the queue configured in `QUEUE_CONNECTION`)
- 🟥 `php artisan pail` (real-time logs)
- 🟧 `npm run dev` (Vite with HMR)
- 🟩 `php artisan reverb:start` (local WebSocket server)

**When:** a normal development session. It replaces starting those processes one by one. `composer dev` is the short alias of the same script.

### Local broadcasting with Laravel Reverb

`.env.example` sets `BROADCAST_CONNECTION=reverb` and `QUEUE_CONNECTION=database`. The configuration Laravel actually uses comes from `.env` and the configuration cache, so check the resolved values before debugging a notification that does not arrive:

```bash
php artisan config:show broadcasting.default
php artisan config:show queue.default
```

The first command must show `reverb` for Laravel to send broadcasts to the Reverb server; if it shows `log`, broadcasts are written to the log and never reach Echo. The second shows the queue that `queue:listen` processes. If you edited `.env` while the configuration was cached, run `php artisan config:clear` and check both values again.

The variables have two uses:

- **Laravel/Reverb server:** `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET` are the Reverb application's credentials. `REVERB_HOST`, `REVERB_PORT` and `REVERB_SCHEME` tell Laravel where to publish events. Never expose `REVERB_APP_SECRET` to the frontend.
- **React/Vite client:** `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT` and `VITE_REVERB_SCHEME` hold the public key and the browser's connection data. The example derives them from their `REVERB_*` counterparts; Vite bakes them into the bundle, so restart Vite or rebuild after changing them.

`composer run dev` starts Reverb and `queue:listen` together with the HTTP server and Vite; do not start another Reverb instance in parallel. To restart the running Reverb server gracefully:

```bash
php artisan reverb:restart
```

After changing environment variables, clear the configuration cache if needed and restart `composer run dev` so Reverb, Laravel, the queue and Vite read the current configuration. Check `broadcasting.default` again and that the development roster shows the Reverb process.

### `composer phpstan-clear-cache`

```bash
composer phpstan-clear-cache
```

Clears PHPStan's result cache.

**When:** when PHPStan reports "odd" errors that look cached, or after large changes to `phpstan.neon`.

## 4. Backend CI workflows

Each gate runs in its own GitHub Actions workflow, in parallel, with the same triggers (`push` and `pull_request` on `main` and `develop`) and PHP 8.5:

| Workflow (`name`)                      | File                                      | Command               | Specific setup                                            |
| -------------------------------------- | ----------------------------------------- | --------------------- | --------------------------------------------------------- |
| Backend Code Quality Test              | `backend-code-quality-test.yml`           | `composer test`       | MySQL 8.4 service and `.env.testing` from `.env.testing.example` |
| Backend Code Quality Pint              | `backend-code-quality-pint.yml`           | `composer pint-ci`    | None                                                      |
| Backend Code Quality Rector            | `backend-code-quality-rector.yml`         | `composer rector-dry` | `storage/rector` cache                                    |
| Backend Code Quality PHPStan           | `backend-code-quality-phpstan.yml`        | `composer phpstan`    | MySQL 8.4 service + `.env.ci` + migrations + `composer ide-helper`, and `storage/phpstan` cache |
| Backend Code Quality Laravel Doctor    | `backend-code-quality-doctor.yml`         | `composer doctor-ci`  | MySQL 8.4 service + `.env.ci` + migrations                 |

- PHPStan **needs** `composer ide-helper` before analyzing: `phpstan.neon` scans `_ide_helper_models.php`, which is generated and not in git.
- PHPStan and Laravel Doctor use the MySQL connection values from `.env.ci` with their isolated MySQL 8.4 service. Other CI workflows that copy `.env.ci` do not connect to a database.
- PHPCPD and Sloppy are not in CI (experimental).
- If you change a workflow or the name of a check, also update the protected branch's required checks on GitHub.

## 5. Quick summary

| Command                        | What for                           | When                               |
| ------------------------------ | ---------------------------------- | ---------------------------------- |
| `composer pint-dirty`          | Format modified files              | Before committing                  |
| `composer phpstan`             | Static analysis                    | Before committing                  |
| `composer rector-clear-cache`  | Delete Rector's cache              | Before `rector-dry`                |
| `composer rector-dry`          | Rector preview                     | After any PHP change               |
| `composer rector`              | Apply Rector                       | After `rector-dry`                 |
| `composer test`                | Whole Pest suite (parallel)        | Before a PR                        |
| `composer pint-ci`             | Read-only Pint, whole repo         | CI workflow gate                   |
| `composer pint-fix`            | Format every PHP file              | When the whole repo needs it       |
| `composer doctor`              | Local Laravel diagnostics          | Last local gate of a backend task  |
| `composer doctor-ci`           | Doctor with GitHub annotations     | CI workflow gate                   |
| `composer phpcpd`              | Experimental clone detection       | Evaluating duplication             |
| `composer sloppy`              | Experimental debt analysis         | Evaluating (not in CI)             |
| `php artisan test --compact`   | Quick tests with a filter          | After every changed test           |
| `composer ide-helper`          | Regenerate IDE helpers             | After changing models              |
| `composer setup`               | Initial setup                      | First time                         |
| `composer dev`                 | Full dev environment               | Development session                |
| `composer phpstan-clear-cache` | Clear PHPStan's cache              | On phantom errors                  |
