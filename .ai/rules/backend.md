---
paths:
  - 'app/**/*.php'
---

# Backend

## PHP closures follow the no-single-letter rule
PHP closures, arrow functions, and `array_*` callbacks inherit the **no single-letter variable names** rule from `.ai/rules/app.md`. Variables in closures (`$candidate`, `$deviceFilter`, `$index`, etc.) must be full descriptive words — never `$v`, `$i`, `$e`, `$x`. The numeric `for` loop counter (`for ($i = 0; ...)`) is the only acceptable exception, and `foreach` is preferred over indexed `for` whenever possible.

## Method docblock prose stays short
Keep the prose summary inside a method docblock to **3 lines max**. Multi-line `@return`, `@param`, array-shape generics, and similar PHPDoc annotations are fine — the constraint applies only to the descriptive prose above them, not to the type annotations.

## Use Data for typed non-model structures
Use spatie/laravel-data for typed request filters, input objects, and internal non-model data structures. When an input flow uses a FormRequest, keep request authorization and FluentRule validation in that FormRequest, then build the Data object from its validated data. Data classes may own type-safe input-to-model mapping and normalization, but must not define or return FluentRule rules.

## Keep model factories complete with persisted model fields
When adding or changing a model factory, compare its default state with the model and backing migration/schema. Define realistic values or explicit nulls for every persisted field that should be represented by the factory; omit only fields Eloquent manages automatically (such as the primary key and timestamps). Update the factory whenever the model or schema changes.

## Use Resources for model serialization
Use Laravel JsonResource classes to serialize Eloquent models for Inertia or API output. Do not duplicate the same model transformation in a laravel-data class.
