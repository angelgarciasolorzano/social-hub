---
paths:
  - 'app/**/*.php'
  - 'resources/js/**/*.tsx'
  - 'resources/js/**/*.ts'
---

# App

## No single-letter variable names in PHP or TypeScript closures
Variables, parameters, and closure-captured values must be full descriptive words (e.g. $candidate, $deviceFilter). Single-letter names like $v, $i, $e are forbidden. Only acceptable exception: numeric for-loop counter (for ($i = 0; ...)).

## Dialogs never mutate the URL for their own filter state; use committedFilters for empty-state copy
A dialog's internal filters/pagination are ephemeral UI state, not shareable page state — use `router.reload({ data, only: [...], preserveUrl: true, replace: true })`, never `router.get()`, so the browser URL stays untouched. Full-page toolbars are the opposite: their filters SHOULD live in the URL for shareability/bookmarking, so they legitimately use `router.get()` (see `useTrustedDeviceFilters.ts` vs. the dialog-scoped `useActivityFilters.ts`).

Both filter hooks also expose a `committedFilters` snapshot (updated only inside `triggerReload`, i.e. the instant a request is actually sent) separate from the live `filters` state (updated on every keystroke). Any UI that must stay in sync with the currently-rendered data — e.g. an empty-state message driven by "are filters active?" — must read `committedFilters`, not `filters`. Reading the live `filters` causes a visible bug: clearing a debounced search field flips the local state to empty immediately, but the actual (still-filtered, still-empty) dataset doesn't arrive for ~500ms+, so the UI briefly (or confusingly) shows the wrong empty-state copy.
