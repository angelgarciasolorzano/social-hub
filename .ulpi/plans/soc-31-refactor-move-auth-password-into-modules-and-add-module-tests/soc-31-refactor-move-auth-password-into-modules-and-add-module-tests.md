# Plan: SOC-31 — Mover Password a Modules y añadir pruebas del módulo

**Issue:** SOC-31. **Modo:** EXPANSION. **Revisión predeterminada:** codex.

## Overview

Reubicar el backend de Password bajo app/Auth/Modules/Password, mantener sus rutas y comportamiento, co-ubicar las pruebas de cambio y reset, registrar la suite Password, regenerar Wayfinder y actualizar sus consumidores. La expansión añade una prueba del soft delete de dispositivos confiables que ya ocurre al cambiar la contraseña; no cambia el flujo de reset.

## Scope Challenge

Linear no tiene un plan previo para SOC-31. El código actual ya contiene los tres controladores, tres Form Requests, rutas y páginas indicados; TrustedDevice aporta el patrón local de módulo y pruebas. La exploración adicional encontró cuatro consumidores Wayfinder, incluido SettingSidebar. Las pruebas heredadas de Password importan App\\Models\\User aunque el modelo actual está en App\\User\\Models\\User; PasswordUpdateTest también usa password.edit/update, pero route:list confirma setting.password.edit/update. El plan corrige esas referencias al moverlas. El modo EXPANSION y la revisión predeterminada codex quedaron confirmados; la expansión se limita a probar el soft delete actual en el cambio de contraseña.

## Prerequisites

- SOC-31 permanece en In Progress y no declara blockers ni relaciones con otras tareas.
- El backend actual está en app/Auth/Password/{Controllers,Requests}; el destino indicado por el ticket es app/Auth/Modules/Password/.
- AuthRouteServiceProvider carga app/Auth/routes/routes.php bajo web; routes.php incluye passwordGuest.php dentro de guest y password.php dentro de auth.
- phpunit.xml registra Unit, Feature y TrustedDevice. tests/Pest.php aplica TestCase y RefreshDatabase a Feature y TrustedDevice.
- resources/js/shared/wayfinder es generado e ignorado por Git; vite.config.ts fija path resources/js/shared/wayfinder y formVariants true.
- El modelo actual es App\\User\\Models\\User y expone trustedDevices(); la relación usa SoftDeletes.
- Route list actual: password.request/email/reset/store y setting.password.edit/update.

## Non-Goals

- Cambiar la lógica de cambio o recuperación de contraseña, tokens, eventos de reset o configuración de Fortify.
- Añadir revocación de dispositivos al flujo de reset, ni cambiar auditoría o invalidación de caché; la expansión solo fija con pruebas el soft delete que ya hace el cambio autenticado.
- Cambiar URI, nombres de ruta, middleware, nombres de componentes Inertia o mover las páginas React.
- Mover ni ampliar las pruebas de confirmación de contraseña de Fortify; tests/Feature/Auth/PasswordConfirmationTest.php permanece en su ubicación.
- Añadir dependencias o alterar archivos de documentación.

## Contracts

- Los seis controladores y Form Requests terminan bajo app/Auth/Modules/Password con namespaces PSR-4 App\\Auth\\Modules\\Password; no quedan imports activos al namespace App\\Auth\\Password.
- Las URI, nombres de ruta, middleware y componentes Inertia permanecen estables: password.request/email/reset/store y setting.password.edit/update.
- El cambio autenticado sigue validando current_password y password_confirmation, actualiza la contraseña y hace soft delete de los dispositivos confiables existentes.
- El reset conserva token válido/inválido, notificación, evento PasswordReset, remember_token y redirección existentes.
- Las pruebas de cambio, solicitud de enlace y reset viven en app/Auth/Modules/Password/Tests; la suite dedicada se llama Password y Feature sigue apuntando a tests/Feature.
- Los helpers Wayfinder se regeneran con la configuración del repo y nunca se editan manualmente.
- Se mantienen los no-goals de SOC-31, con la única expansión confirmada: una prueba Pest del efecto actual sobre TrustedDevice.

## Existing Code Leverage

- app/Auth/Modules/TrustedDevice/ aporta la convención de submódulo.
- app/Auth/Password/Controllers y Requests ya contienen la implementación que se mueve.
- app/Auth/routes/password.php y passwordGuest.php son los puntos de binding existentes.
- tests/Feature/Settings/PasswordUpdateTest.php y tests/Feature/Auth/PasswordResetTest.php cubren flujos útiles que se trasladan.
- app/Auth/Modules/TrustedDevice/Tests y tests/Pest.php aportan convenciones y helpers para una regresión Pest.
- phpunit.xml separa Unit, Feature y TrustedDevice; tests/Pest.php configura el contexto de los módulos.
- EditPassword.tsx, SettingSidebar.tsx, ForgotPassword.tsx y ResetPassword.tsx son los cuatro consumidores Wayfinder encontrados.
- vite.config.ts y el comando Wayfinder del ticket determinan el destino generado.

## Tasks

### TASK-001: Mover PasswordRequest al módulo

**Description:** Mover PasswordRequest a app/Auth/Modules/Password/Requests y actualizar el import del controlador autenticado antes de reubicarlo.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- `app/Auth/Password/Requests/PasswordRequest.php`
- `app/Auth/Modules/Password/Requests/PasswordRequest.php`
- `app/Auth/Password/Controllers/PasswordController.php`

**Acceptance Criteria:**

- PasswordRequest vive en el destino y declara App\\Auth\\Modules\\Password\\Requests.
- PasswordController importa el request nuevo; sus reglas current_password, defaults y confirmed no cambian.
- La ruta de fallo por contraseña actual incorrecta y confirmación distinta sigue cubierta por las pruebas del flujo.

**validateCommand:** `rtk php -l app/Auth/Modules/Password/Requests/PasswordRequest.php && rtk rg -n 'Modules\\\\Password\\\\Requests\\\\PasswordRequest' app/Auth/Password/Controllers/PasswordController.php`

### TASK-002: Mover PasswordController y conservar la ruta autenticada

**Description:** Mover PasswordController a Modules/Password y cambiar solo el import del controlador en password.php.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** TASK-001

**writeScope:**

- `app/Auth/Password/Controllers/PasswordController.php`
- `app/Auth/Modules/Password/Controllers/PasswordController.php`
- `app/Auth/routes/password.php`

**Acceptance Criteria:**

- PasswordController declara el namespace del módulo y password.php lo resuelve desde el destino.
- GET/PUT setting/password mantienen setting.password.edit/update, auth, verified y throttle:6,1.
- El componente setting/modules/password/EditPassword y la lógica de hash y revocación permanecen sin cambios.

**validateCommand:** `rtk php -l app/Auth/Modules/Password/Controllers/PasswordController.php && rtk php artisan route:list --name=setting.password`

### TASK-003: Mover PasswordNewRequest al módulo

**Description:** Mover PasswordNewRequest y actualizar el import en PasswordNewController, sin modificar la validación de token, correo ni contraseña.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- `app/Auth/Password/Requests/PasswordNewRequest.php`
- `app/Auth/Modules/Password/Requests/PasswordNewRequest.php`
- `app/Auth/Password/Controllers/PasswordNewController.php`

**Acceptance Criteria:**

- PasswordNewRequest vive en Modules/Password/Requests con namespace coincidente.
- PasswordNewController importa la clase movida y mantiene token/email requeridos y password confirmado.
- La entrada con token inválido o confirmation distinta sigue terminando en error de validación.

**validateCommand:** `rtk php -l app/Auth/Modules/Password/Requests/PasswordNewRequest.php && rtk rg -n 'Modules\\\\Password\\\\Requests\\\\PasswordNewRequest' app/Auth/Password/Controllers/PasswordNewController.php`

### TASK-004: Mover PasswordNewController y preservar rutas de reset

**Description:** Mover PasswordNewController y actualizar su binding en passwordGuest.php después de migrar su Form Request.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** TASK-003

**writeScope:**

- `app/Auth/Password/Controllers/PasswordNewController.php`
- `app/Auth/Modules/Password/Controllers/PasswordNewController.php`
- `app/Auth/routes/passwordGuest.php`

**Acceptance Criteria:**

- El controlador de reset vive en Modules/Password/Controllers y el guest route file lo importa desde allí.
- GET reset-password/{token} y POST reset-password conservan password.reset/store y middleware guest.
- Token válido sigue redirigiendo a login y token inválido sigue rechazándose sin alterar el flujo PasswordReset.

**validateCommand:** `rtk php -l app/Auth/Modules/Password/Controllers/PasswordNewController.php && rtk php artisan route:list --name=password.reset`

### TASK-005: Mover PasswordResetLinkRequest al módulo

**Description:** Mover PasswordResetLinkRequest y actualizar el import de PasswordResetLinkController manteniendo la validación actual de email.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- `app/Auth/Password/Requests/PasswordResetLinkRequest.php`
- `app/Auth/Modules/Password/Requests/PasswordResetLinkRequest.php`
- `app/Auth/Password/Controllers/PasswordResetLinkController.php`

**Acceptance Criteria:**

- PasswordResetLinkRequest vive bajo Modules/Password/Requests con namespace PSR-4.
- PasswordResetLinkController resuelve el request nuevo.
- Email ausente o mal formado continúa rechazándose antes de intentar enviar la notificación.

**validateCommand:** `rtk php -l app/Auth/Modules/Password/Requests/PasswordResetLinkRequest.php && rtk rg -n 'Modules\\\\Password\\\\Requests\\\\PasswordResetLinkRequest' app/Auth/Password/Controllers/PasswordResetLinkController.php`

### TASK-006: Mover PasswordResetLinkController y conservar rutas guest

**Description:** Mover PasswordResetLinkController al módulo y actualizar passwordGuest.php. La modificación de ese archivo se serializa con TASK-004.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** TASK-004, TASK-005

**writeScope:**

- `app/Auth/Password/Controllers/PasswordResetLinkController.php`
- `app/Auth/Modules/Password/Controllers/PasswordResetLinkController.php`
- `app/Auth/routes/passwordGuest.php`

**Acceptance Criteria:**

- El controlador de solicitud de enlace vive en Modules/Password/Controllers y su ruta usa el namespace nuevo.
- GET/POST forgot-password conservan password.request/email y middleware guest.
- El componente Inertia auth/password/ForgotPassword y el mensaje broker permanecen iguales.

**validateCommand:** `rtk php -l app/Auth/Modules/Password/Controllers/PasswordResetLinkController.php && rtk php artisan route:list --name=password.request`

### TASK-007: Trasladar y reparar la prueba de cambio autenticado

**Description:** Mover PasswordUpdateTest al directorio del módulo, actualizar el modelo al namespace vigente y usar los route names setting.password.* que ya expone la aplicación.

**Type:** test  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- `tests/Feature/Settings/PasswordUpdateTest.php`
- `app/Auth/Modules/Password/Tests/PasswordUpdateTest.php`

**Acceptance Criteria:**

- La prueba vive en app/Auth/Modules/Password/Tests y conserva los casos de pantalla, cambio válido y contraseña actual incorrecta.
- Las llamadas usan App\\User\\Models\\User y setting.password.edit/update; no se cambian las rutas de producción para acomodar al test.
- El caso de contraseña actual incorrecta permanece como fallo de validación y redirige al formulario.

**validateCommand:** `rtk php -l app/Auth/Modules/Password/Tests/PasswordUpdateTest.php && rtk rg -n 'App\\\\User\\\\Models\\\\User|setting\\.password\\.(edit|update)' app/Auth/Modules/Password/Tests/PasswordUpdateTest.php`

### TASK-008: Trasladar las pruebas de solicitud y reset

**Description:** Mover PasswordResetTest al módulo y ajustar el import del modelo a App\\User\\Models\\User, conservando los escenarios existentes.

**Type:** test  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** —

**writeScope:**

- `tests/Feature/Auth/PasswordResetTest.php`
- `app/Auth/Modules/Password/Tests/PasswordResetTest.php`

**Acceptance Criteria:**

- La prueba vive en app/Auth/Modules/Password/Tests y conserva envío/render del enlace, render del reset y token válido.
- El caso de token inválido continúa reportando error en email.
- Las rutas password.email/reset/store y su middleware guest no se renombran para acomodar al test.

**validateCommand:** `rtk php -l app/Auth/Modules/Password/Tests/PasswordResetTest.php && rtk rg -n 'App\\\\User\\\\Models\\\\User|password\\.(email|reset|store)' app/Auth/Modules/Password/Tests/PasswordResetTest.php`

### TASK-009: Registrar la suite Password y su contexto Pest

**Description:** Añadir la suite Password en phpunit.xml y configurar tests/Pest.php para aplicar TestCase y RefreshDatabase al nuevo directorio del módulo.

**Type:** chore  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** TASK-007, TASK-008

**writeScope:**

- `phpunit.xml`
- `tests/Pest.php`

**Acceptance Criteria:**

- phpunit.xml registra Password apuntando a app/Auth/Modules/Password/Tests y Feature sigue limitado a tests/Feature.
- tests/Pest.php incluye el directorio Password para que los tests Pest reciban TestCase y RefreshDatabase.
- php artisan test --list-suites muestra Password sin mover PasswordConfirmationTest de Fortify.

**validateCommand:** `rtk php artisan test --list-suites && rtk rg -n 'testsuite name="Password"|Modules/Password/Tests' phpunit.xml tests/Pest.php`

### TASK-010: Fijar con regresión la revocación actual de dispositivos

**Description:** Añadir un test Pest en el módulo que documente el soft delete que PasswordController ya ejecuta al cambiar correctamente la contraseña.

**Type:** test  
**Priority:** P2  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** TASK-002, TASK-009

**writeScope:**

- `app/Auth/Modules/Password/Tests/PasswordTrustedDeviceRevocationTest.php`

**Acceptance Criteria:**

- Con cambio válido, el trusted device deja de aparecer en la relación activa y la fila sigue presente con deleted_at mediante withTrashed.
- Con current_password incorrecta, la petición falla y el trusted device permanece activo.
- La prueba no exige evento, invalidación de caché ni revocación en el reset; no altera producción.

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/Password/Tests/PasswordTrustedDeviceRevocationTest.php`

### TASK-011: Regenerar las acciones Wayfinder del nuevo namespace

**Description:** Ejecutar Wayfinder con la ruta de salida configurada en vite.config.ts. El generador es dueño de los helpers ignorados; no editar su salida a mano.

**Type:** chore  
**Priority:** P1  
**Effort:** S  
**Agent:** `laravel-senior-engineer`  
**Review:** `codex`  
**Depends on:** TASK-002, TASK-004, TASK-006

**writeScope:**

- Sin archivos versionados; valida integración/gates o genera helpers ignorados.

**Acceptance Criteria:**

- La generación usa --with-form y resources/js/shared/wayfinder, conforme a vite.config.ts.
- Se generan las acciones para los tres controladores bajo App/Auth/Modules/Password/Controllers y no se requiere editar archivos generados manualmente.
- Los consumidores TypeScript pueden importar la nueva ubicación después de la generación.

**validateCommand:** `rtk php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder --no-interaction && rtk rg --files --no-ignore resources/js/shared/wayfinder/actions/App/Auth/Modules/Password/Controllers`

### TASK-012: Actualizar los consumidores Wayfinder de Settings

**Description:** Cambiar las importaciones de PasswordController en EditPassword y SettingSidebar a las acciones generadas bajo Modules/Password.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** `react-vite-tailwind-engineer`  
**Review:** `codex`  
**Depends on:** TASK-011

**writeScope:**

- `resources/js/modules/setting/modules/password/EditPassword.tsx`
- `resources/js/modules/setting/shared/components/SettingSidebar.tsx`

**Acceptance Criteria:**

- EditPassword importa update desde la nueva ruta de PasswordController.
- SettingSidebar importa edit desde la misma ubicación nueva.
- Los formularios siguen llamando las acciones Wayfinder y no introducen URLs codificadas ni cambios visuales.

**validateCommand:** `rtk npx prettier --check resources/js/modules/setting/modules/password/EditPassword.tsx resources/js/modules/setting/shared/components/SettingSidebar.tsx && rtk npx eslint resources/js/modules/setting/modules/password/EditPassword.tsx resources/js/modules/setting/shared/components/SettingSidebar.tsx`

### TASK-013: Actualizar los consumidores Wayfinder de recuperación

**Description:** Cambiar ForgotPassword y ResetPassword para importar acciones generadas de PasswordResetLinkController y PasswordNewController desde Modules/Password.

**Type:** refactor  
**Priority:** P1  
**Effort:** S  
**Agent:** `react-vite-tailwind-engineer`  
**Review:** `codex`  
**Depends on:** TASK-011

**writeScope:**

- `resources/js/modules/auth/password/ForgotPassword.tsx`
- `resources/js/modules/auth/password/ResetPassword.tsx`

**Acceptance Criteria:**

- ForgotPassword importa su acción desde App/Auth/Modules/Password/Controllers/PasswordResetLinkController.
- ResetPassword importa su acción desde App/Auth/Modules/Password/Controllers/PasswordNewController.
- Los formularios mantienen los nombres de ruta, datos y componentes actuales; no quedan imports del namespace viejo.

**validateCommand:** `rtk npx prettier --check resources/js/modules/auth/password/ForgotPassword.tsx resources/js/modules/auth/password/ResetPassword.tsx && rtk npx eslint resources/js/modules/auth/password/ForgotPassword.tsx resources/js/modules/auth/password/ResetPassword.tsx`

### TASK-014: Validar suite, gates y flujos en navegador

**Description:** Ejecutar la suite aislada y completa, gates backend/frontend y revisión manual de cambio, recuperación y reset. Laravel Doctor queda como último gate local.

**Type:** test  
**Priority:** P1  
**Effort:** M  
**Agent:** `general-purpose`  
**Review:** `codex`  
**Depends on:** TASK-006, TASK-008, TASK-009, TASK-010, TASK-012, TASK-013

**writeScope:**

- Sin archivos versionados; valida integración/gates o genera helpers ignorados.

**Acceptance Criteria:**

- php artisan test --compact Password y php artisan test --compact pasan; quedan cubiertos cambio autenticado, envío de enlace, reset válido, token inválido y fallo de current_password.
- Pint, PHPStan, Rector dry-run seguido de Rector, format, lint, TypeScript, build normal y SSR pasan; si Rector modifica archivos se repiten los gates afectados antes de cerrar.
- En navegador se renderizan cambio, recuperación y reset y se completan los flujos esperados; composer doctor se ejecuta después de todos los gates como la última verificación backend.

**validateCommand:** `rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk composer rector-dry && rtk composer rector && rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk php artisan test --compact Password && rtk php artisan test --compact && rtk npm run format:check && rtk npm run lint:check && rtk npm run types && rtk npm run build && rtk npm run build:ssr && rtk composer doctor`

## Failure Modes

- Un namespace o import obsoleto deja una ruta sin controlador o un Form Request sin resolver.
- Un cambio accidental de URI, route name, middleware o componente Inertia altera una API pública que el ticket exige preservar.
- Omitir SettingSidebar deja una importación Wayfinder vieja aunque las tres páginas mencionadas compilen.
- Editar helpers Wayfinder generados a mano pierde el cambio al regenerar; el directorio además está ignorado por Git.
- No registrar app/Auth/Modules/Password/Tests en phpunit.xml impide ejecutar la suite Password; no añadir su contexto a tests/Pest.php afecta la nueva prueba Pest.
- Mantener App\\Models\\User o las route names obsoletas hace fallar las pruebas trasladadas antes de validar la regresión real.
- Una prueba que solo confirme ausencia en la relación activa puede ocultar un hard delete; debe afirmar que la fila sigue existiendo con deleted_at.
- Expandir la revocación al reset o agregar eventos/cache cambia comportamiento y cruza los no-goals acordados.

## Ship Cut

La refactorización se considera lista solo cuando los bindings backend resuelven los namespaces nuevos, la suite Password descubre y pasa todos los flujos, las cuatro referencias Wayfinder usan acciones generadas nuevas, y los checks manuales y gates backend/frontend terminan. No es un corte publicable entre el movimiento backend y la actualización de sus consumidores.

## Test Coverage Map

- PasswordUpdateTest conserva pantalla, cambio válido y rechazo de contraseña actual incorrecta usando los route names reales setting.password.edit/update.
- PasswordResetTest conserva solicitud/render del enlace, render del formulario, reset con token válido y rechazo de token inválido.
- La nueva prueba Pest demuestra que el cambio correcto marca deleted_at y conserva la fila consultable conTrashed; contraseña actual incorrecta deja el dispositivo activo.
- php artisan test --compact Password prueba la suite aislada; php artisan test --compact ejecuta la suite completa al cierre.
- php artisan route:list --name=password confirma que URI y nombres públicos permanecen iguales.
- La verificación manual en navegador cubre render y envío de cambio, solicitud de recuperación y reset con token.
- format, lint, TypeScript, build normal y SSR validan los cuatro consumidores React/Wayfinder.

## Execution Summary

Plan de ejecución preparado después de leer SOC-31, comentarios, reglas del repositorio y código real. No se modificó código de la aplicación ni se ejecutaron pruebas. Modo EXPANSION, revisión predeterminada codex, 14 tareas con writeScope máximo de tres paths; la expansión se limita a una regresión sobre el comportamiento existente.

## Task Dependencies

**Critical path:** TASK-003 → TASK-004 → TASK-006 → TASK-011 → TASK-013 → TASK-014.

- TASK-001: sin dependencias
- TASK-002: TASK-001
- TASK-003: sin dependencias
- TASK-004: TASK-003
- TASK-005: sin dependencias
- TASK-006: TASK-004, TASK-005
- TASK-007: sin dependencias
- TASK-008: sin dependencias
- TASK-009: TASK-007, TASK-008
- TASK-010: TASK-002, TASK-009
- TASK-011: TASK-002, TASK-004, TASK-006
- TASK-012: TASK-011
- TASK-013: TASK-011
- TASK-014: TASK-006, TASK-008, TASK-009, TASK-010, TASK-012, TASK-013
