---
paths:
  - 'app/**/*.php'
  - 'database/migrations/**'
---

# Backend

## Keep module UI view directories lowercase
Name every directory below a module's `UI/Views/` root in lowercase, and keep namespaced Blade view references consistent with the directory names. Do not rely on macOS case-insensitive filesystem behavior: Linux CI resolves these paths case-sensitively.

## PHP closures follow the no-single-letter rule
PHP closures, arrow functions, and `array_*` callbacks inherit the **no single-letter variable names** rule from `.ai/rules/app.md`. Variables in closures (`$candidate`, `$deviceFilter`, `$index`, etc.) must be full descriptive words — never `$v`, `$i`, `$e`, `$x`. The numeric `for` loop counter (`for ($i = 0; ...)`) is the only acceptable exception, and `foreach` is preferred over indexed `for` whenever possible.

## Method docblock prose stays short
Keep the prose summary inside a method docblock to **3 lines max**. Multi-line `@return`, `@param`, array-shape generics, and similar PHPDoc annotations are fine — the constraint applies only to the descriptive prose above them, not to the type annotations.

## Document service methods
Document every public and private method in service classes except constructors, whose purpose is self-evident. Keep summaries short and understandable, within the existing three-line maximum; retain type annotations such as `@return` where needed.

## Use Data for typed non-model structures
Use spatie/laravel-data for typed request filters, input objects, and internal non-model data structures. When an input flow uses a FormRequest, keep request authorization and FluentRule validation in that FormRequest, then build the Data object from its validated data. Data classes may own type-safe input-to-model mapping and normalization, but must not define or return FluentRule rules.

## Keep model factories complete with persisted model fields
When adding or changing a model factory, compare its default state with the model and backing migration/schema. Define realistic values or explicit nulls for every persisted field that should be represented by the factory; omit only fields Eloquent manages automatically (such as the primary key and timestamps). Update the factory whenever the model or schema changes.

## Use Resources for model serialization
Use Laravel JsonResource classes to serialize Eloquent models for Inertia or API output. Do not duplicate the same model transformation in a laravel-data class.

## Keep published package migrations global
When a third-party package migration must be published or copied into the application, keep it in the global database/migrations/ directory rather than inside a domain module. The package integration and feature code remain in their owning application module.

## Keep FluentRule definitions in FormRequests
When an input flow uses sandermuller/laravel-fluent-validation, define its FluentRule chains in the FormRequest rules() method with HasFluentRules. A Laravel Data class may provide typed properties and input-to-model mapping, but must not define or return FluentRule rules; it should be built from the already validated FormRequest data.

## Type backed enums in Data objects
For finite domain values represented by a backed enum, type Laravel Data properties as that enum instead of string/int. Build from validated request data and keep request validation in the FormRequest; map the enum to its backing scalar with ->value at persistence or serialization boundaries that expect a primitive.

## Preserve Filament resource directory conventions
For code under app/Modules/Admin/Filament, keep Filament's generated resource layout beneath the module root: place each resource in a directory named for its plural model (for example, Resources/Users/UserResource.php) and keep resource pages under that directory's Pages folder. When using Filament generators with custom namespaces, preserve this per-resource hierarchy.

## Keep Filament component declarations in native methods
Across all Filament classes, keep concise component declarations in the framework-provided method so Filament's native structure stays visible. For example, declare StatsOverviewWidget Stat::make() entries directly in getStats(). Extract substantial or reusable queries and calculations into private methods; avoid one wrapper method per individual component.

## Keep backend tests focused
Each backend test should verify one behavior or invariant. Whenever possible, write several small tests instead of one test that covers many cases, and split tests that cover unrelated responsibilities into smaller, clearly named cases so setup and assertions stay easy to understand. Use a dataset when the same behavior is checked with different inputs. Share only concise helpers that reduce duplication without hiding what each test verifies.

## Tests change model data through Eloquent, not DB::table
In tests, do not use DB::table(...)->update(...) to modify data that belongs to a model. Use the model instead, e.g. User::query()->findOrFail($user->id)->forceFill([...])->save(). Loading a second instance also keeps the original in-memory model stale, which is how tests simulate another request changing the row.

## Test helper functions go at the top of the test file
In backend test files, declare helper functions and constants at the top of the file, before the first test, not in the middle or at the end. This keeps the file readable: the reader meets the helpers before the tests that use them.
