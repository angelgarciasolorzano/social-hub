---
paths:
  - 'app/**/*.php'
---

# App

## No single-letter variable names in PHP or TypeScript closures
Variables, parameters, and closure-captured values must be full descriptive words (e.g. $candidate, $deviceFilter). Single-letter names like $v, $i, $e are forbidden. Only acceptable exception: numeric for-loop counter (for ($i = 0; ...)).
