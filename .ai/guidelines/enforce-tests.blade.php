# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

### Pest Browser

- Module-owned browser tests live in `Tests/Browser/` and use the shared `browser` Pest group and Vite setup from `tests/Pest.php`. After installing project dependencies, provision only Chromium with `npx playwright install chromium`, then run `composer test:browser`; it builds Vite assets once and runs the whole group. The tests use `.env.testing` with the existing `social-hub-testing` database. `composer test` excludes the `browser` group; no alternate database, other browsers, or CI workflow are configured.
