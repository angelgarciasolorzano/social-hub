# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- Place new module-specific tests under the owning module's `Tests/` directory (`app/Modules/{Domain}/Tests/` for modules migrated to the new architecture; `app/{Domain}/Tests/` or `app/{Domain}/Modules/{Feature}/Tests/` in the old architecture); reserve root `tests/` for cross-module, infrastructure, and existing tests.
- Register new module test directories in `phpunit.xml` and configure their Pest base case/shared traits in `tests/Pest.php` as needed.
- For browser test placement, this project's module-owned `Tests/Browser/` paths and the locations configured in `tests/Pest.php` take precedence over the generic `tests/Browser` example in Laravel Boost's `testing-best-practices` skill. Put browser tests in the owning module's `Tests/Browser/` subdirectory so Pest assigns them to the `browser` group and applies the shared Vite setup; run them with `composer test:browser`.
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.
