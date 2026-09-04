---
paths:
  - 'app/**/*.php'
  - '**/*'
---

# App

## No single-letter variable names in PHP or TypeScript closures
Variables, parameters, and closure-captured values must be full descriptive words (e.g. $candidate, $deviceFilter). Single-letter names like $v, $i, $e are forbidden. Only acceptable exception: numeric for-loop counter (for ($i = 0; ...)).

## Method docblock prose stays short
Keep the prose summary inside a method docblock to **3 lines max**. Multi-line `@return`, `@param`, array-shape generics, and similar PHPDoc annotations are fine — the constraint applies only to the descriptive prose above them, not to the type annotations.

## Linear issue titles in English, body in Spanish
**Title:** must be in English using a conventional commit prefix (`Feature:`, `Bug:`, `Chore:`, `Refactor:`, `Documentation:`). The title is the universal summary shown in lists, search results, and PR links — keeping it in English makes the backlog scannable for everyone.

**Body / description:** in Spanish (matches the project's working language and how the user thinks through specs).

The `gitBranchName` field follows the same convention: English kebab-case derived from the title.

Examples (from the project backlog):

- ✅ `Feature: Implement soft delete, re-trust flow, and automated purge for TrustedDevice` (SOC-21)
- ✅ `Chore: Improve social-hub-conventions skill (commands, conventional commits, mirror)` (SOC-19)
- ❌ `Feature: 3 dialogs de detalle para /setting/trusted-devices` — Spanish title (the bug SOC-22 had before being fixed)
