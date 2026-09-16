---
paths:
  - 'app/**/*.php'
---

# Backend

## PHP closures follow the no-single-letter rule
PHP closures, arrow functions, and `array_*` callbacks inherit the **no single-letter variable names** rule from `.ai/rules/app.md`. Variables in closures (`$candidate`, `$deviceFilter`, `$index`, etc.) must be full descriptive words — never `$v`, `$i`, `$e`, `$x`. The numeric `for` loop counter (`for ($i = 0; ...)`) is the only acceptable exception, and `foreach` is preferred over indexed `for` whenever possible.

## Method docblock prose stays short
Keep the prose summary inside a method docblock to **3 lines max**. Multi-line `@return`, `@param`, array-shape generics, and similar PHPDoc annotations are fine — the constraint applies only to the descriptive prose above them, not to the type annotations.
