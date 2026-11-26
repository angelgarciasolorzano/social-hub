# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Module-specific tests belong in the owning module's `Tests/` directory; after using `make:test`, move the generated file from root `tests/` and register its module directory in `phpunit.xml`/`tests/Pest.php` when needed.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

## Pest Coverage with Herd

- Run coverage for the complete Pest suite with `herd coverage ./vendor/bin/pest --coverage`. Do not pass a module path: coverage should run across the project.
- PHPUnit's `<source>` configuration in `phpunit.xml` determines which application files are measured; it currently includes `app`.
- If `herd coverage` fails while resolving PHP before Pest starts, run Pest with Herd's PHP binary directly and ensure that binary loads Xdebug with `XDEBUG_MODE=coverage` (or Herd's Xdebug-enabled debug configuration). Treat a wrapper startup failure separately from a Pest test or coverage failure.

## Laravel Doctor

- Run `composer doctor` only after implementation and all applicable backend gates are complete; it is the final local gate for a backend task. Do not run further checks after it.
- `composer doctor` runs local diagnostics without applying fixes. `composer doctor-ci` is the backend CI workflow command and emits GitHub annotations; use it as a CI gate, not as an intermediate implementation check.
