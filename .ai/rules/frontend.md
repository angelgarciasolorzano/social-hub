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

## Explicitly type React hooks
In TypeScript, explicitly declare the state, ref, value, and callback types of React hooks whenever the API allows it. For example, use `useState<SelectedContent>(...)` and `useRef<HTMLInputElement | null>(null)`; apply the same standard to `useMemo`, `useCallback`, and other hooks that support explicit typing.

## TypeScript closures follow the no-single-letter rule
TypeScript closures (callbacks, `useState` selectors, `.map` / `.filter` / `.find` callbacks, etc.) inherit the **no single-letter variable names** rule from `.ai/rules/app.md`. Variables in closures (`value`, `index`, `candidate`, etc.) must be full descriptive words — never `v`, `i`, `e`, `x`. The numeric `for` loop counter (`for (let i = 0; ...)`) is the only exception, and `forEach`/`map` are preferred over indexed `for` whenever possible.

## Dialogs never mutate the URL for their own filter state; use committedFilters for empty-state copy
A dialog's internal filters/pagination are ephemeral UI state, not shareable page state — use `router.reload({ data, only: [...], preserveUrl: true, replace: true })`, never `router.get()`, so the browser URL stays untouched. Full-page toolbars are the opposite: their filters SHOULD live in the URL for shareability/bookmarking, so they legitimately use `router.get()` (see `useTrustedDeviceFilters.ts` vs. the dialog-scoped `useActivityFilters.ts`).

Both filter hooks also expose a `committedFilters` snapshot (updated only inside `triggerReload`, i.e. the instant a request is actually sent) separate from the live `filters` state (updated on every keystroke). Any UI that must stay in sync with the currently-rendered data — e.g. an empty-state message driven by "are filters active?" — must read `committedFilters`, not `filters`. Reading the live `filters` causes a visible bug: clearing a debounced search field flips the local state to empty immediately, but the actual (still-filtered, still-empty) dataset doesn't arrive for ~500ms+, so the UI briefly (or confusingly) shows the wrong empty-state copy.

## Order components top-down within a file, each with its own props interface
Declare the components of a file in reading order: the entry component or dispatcher first, then the main case, then variants or special cases, then small shared helpers, and `export default` last.

Each component's props interface or type goes immediately before that component, in the order: interface, function, interface of the next function, function, and so on. Never group all the interfaces at the top of the file; only a type shared by several components (and its type guard) stays where it is first needed.

Keep helpers in the same file while they are short and used only there; extract them to their own file when the file grows too large or they are reused.
