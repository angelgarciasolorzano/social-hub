---
paths:
  - 'resources/js/**/*.tsx'
  - 'resources/js/**/*.ts'
---

# Frontend

## Child component props must be semantically named
When a child component receives a list of items, the prop name must describe **what the items are**, not a generic placeholder. A child that renders device-type rows accepts `deviceTypeRows: DeviceTypeRow[]`, not a generic `rows: ...`.

This applies to any prop that carries domain meaning. Generic names (`rows`, `items`, `list`, `data`) are forbidden unless the child is truly generic (rare). Specific names (`deviceTypeRows`, `recommendations`, `activityLog`, etc.) communicate intent and make the call site self-documenting.

Rule of thumb: if you cannot tell what the prop contains from its name alone, the name is wrong.

## TypeScript closures follow the no-single-letter rule
TypeScript closures (callbacks, `useState` selectors, `.map` / `.filter` / `.find` callbacks, etc.) inherit the **no single-letter variable names** rule from `.ai/rules/app.md`. Variables in closures (`value`, `index`, `candidate`, etc.) must be full descriptive words — never `v`, `i`, `e`, `x`. The numeric `for` loop counter (`for (let i = 0; ...)`) is the only exception, and `forEach`/`map` are preferred over indexed `for` whenever possible.
