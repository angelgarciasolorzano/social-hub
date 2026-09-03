---
paths:
  - 'app/**/*.php'
---

# App

## No single-letter variable names in PHP or TypeScript closures
Variables, parameters, and closure-captured values must be full descriptive words (e.g. $candidate, $deviceFilter). Single-letter names like $v, $i, $e are forbidden. Only acceptable exception: numeric for-loop counter (for ($i = 0; ...)).

## Method docblock prose stays short
Keep the prose summary inside a method docblock to **3 lines max**. Multi-line `@return`, `@param`, array-shape generics, and similar PHPDoc annotations are fine — the constraint applies only to the descriptive prose above them, not to the type annotations.
