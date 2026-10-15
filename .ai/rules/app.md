---
paths:
  - 'app/**/*.php'
  - 'resources/js/**/*.tsx'
  - 'resources/js/**/*.ts'
---

# App

## No single-letter variable names in PHP or TypeScript closures
Variables, parameters, and closure-captured values must be full descriptive words (e.g. $candidate, $deviceFilter). Single-letter names like $v, $i, $e are forbidden. Only acceptable exception: numeric for-loop counter (for ($i = 0; ...)).

## Reuse utility functions instead of repeating code
Whenever possible, reuse existing utility functions, helpers and shared components instead of repeating code. Extract a shared helper when the same logic appears in more than one place, for example test helpers in tests/Pest.php or shared React components.
