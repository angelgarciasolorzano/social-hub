# Laravel Wayfinder

Before generating route helpers, inspect `wayfinder({ path: ... })` in `vite.config.ts` and use that configured output path. In this repository the generated files live under `resources/js/shared/wayfinder/`; import controller actions from `@/shared/wayfinder/actions/` and named routes from `@/shared/wayfinder/routes/`.

After changing Laravel routes or controllers, regenerate helpers with the configured path and form variants:

```bash
php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder --no-interaction
```

Review the generated diff under `resources/js/shared/wayfinder/`, including the route URI and exported alias. Never edit generated files by hand; change the Laravel route or controller and regenerate instead.
