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

## Plan every new implementation task
Before starting implementation work in this project, use `.agents/skills/plan-to-task-list-with-dag/SKILL.md`, even for small tasks (a single-task DAG is fine). Continue an existing approved plan instead of duplicating it. Store each Markdown/JSON pair in `.ulpi/plans/<plan-slug>/<plan-slug>.md` and `.json`; start the shared slug with the task key (lowercase, e.g. `soc-30`) plus a clear description. This project rule applies even when the skill's general guidance excludes trivial direct work.
