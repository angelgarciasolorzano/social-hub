---
paths:
  - '**/*'
---

# Linear

## Linear issue titles in English, body in Spanish
**Title:** must be in English using a conventional commit prefix (`Feature:`, `Bug:`, `Chore:`, `Refactor:`, `Documentation:`). The title is the universal summary shown in lists, search results, and PR links — keeping it in English makes the backlog scannable for everyone.

**Body / description:** in Spanish (matches the project's working language and how the user thinks through specs).

The `gitBranchName` field follows the same convention: English kebab-case derived from the title.

Examples (from the project backlog):

- ✅ `Feature: Implement soft delete, re-trust flow, and automated purge for TrustedDevice` (SOC-21)
- ✅ `Chore: Improve social-hub-conventions skill (commands, conventional commits, mirror)` (SOC-19)
- ❌ `Feature: 3 dialogs de detalle para /setting/trusted-devices` — Spanish title (the bug SOC-22 had before being fixed)
