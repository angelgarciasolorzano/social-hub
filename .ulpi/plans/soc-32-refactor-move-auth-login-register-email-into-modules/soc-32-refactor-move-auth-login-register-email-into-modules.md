# Plan: SOC-32: Mover Auth Login, Register y Email a submódulos

## Overview

SOC-32 mueve Login, Register y Email bajo `app/Auth/Modules/`, amplía la cobertura de sus pruebas y las expresa con Pest. Conserva los contratos actuales y sigue el patrón completado en SOC-31.

Issue: [SOC-32](https://linear.app/social-hub-ang/issue/SOC-32/refactor-move-auth-login-register-and-email-into-modules-with-module) — estado observado: Backlog. Su dependencia SOC-31 está en Done.

Modo confirmado: **EXPANSION**. Revisión por defecto: **codex**.

## Scope Challenge

- El usuario amplió el alcance para cubrir fallos y casos límite de Login, Register y Email, y pidió que todas las pruebas de esos módulos estén escritas con Pest.
- La descripción actual de SOC-32 pide co-ubicar suites y enumera escenarios mínimos; no exige explícitamente ampliar la cobertura ni convertir los archivos PHPUnit a Pest.
- La expansión se limita a pruebas feature de los tres módulos; no cambia lógica, contratos ni componentes de producción.
- SOC-32 estaba en Backlog y señalaba SOC-31 como bloqueante; SOC-31 ya está Done.
- Se reutilizan los controladores/requests, el patrón Password y las páginas React existentes.

## Prerequisites

- SOC-31 figura Done y su estructura Password existe en el checkout como precedente.
- Pest v5.2.1 está instalado; las pruebas de Password usan it() y sirven como convención local.
- SOC-32 no exige explícitamente Pest ni la cobertura ampliada; esta extensión acotada fue solicitada por el usuario.
- El árbol de trabajo estaba limpio durante el análisis.
- Regenerar Wayfinder después de mover controladores y mantener sus imports alineados.
- Al iniciar implementación, trabajar en una sola tarea atómica y pausar para revisión al completarla, según .ai/rules/linear.md.

## Non-Goals

- Cambiar autenticación, registro, verificación de correo, rate limiting o configuración de Fortify.
- Cambiar URI, nombres de ruta, middleware, redirecciones, props o nombres de componentes Inertia.
- Mover páginas frontend.
- Mover las pruebas dedicadas a TwoFactorChallenge, PasswordConfirmation u otros submódulos.
- Cambiar el estado o publicar comentarios en Linear como parte de este plan.

## Contracts

| Método | URI | Nombre | Middleware |
|---|---|---|---|
| GET | `/login` | `login` | `web`, `guest` |
| POST | `/login` | `login.store` | `web`, `guest` |
| POST | `/logout` | `logout` | `web`, `auth` |
| GET | `/register` | `register` | `web`, `guest` |
| POST | `/register` | `register.store` | `web`, `guest` |
| GET | `/verify-email` | `verification.notice` | `web`, `auth` |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | `web`, `auth`, `signed`, `throttle:6,1` |
| POST | `/email/verification-notification` | `verification.send` | `web`, `auth`, `throttle:6,1` |

Componentes Inertia y props:

- `auth/login/Login`: `canResetPassword`, `status`
- `auth/register/Register`: sin props declaradas en el controlador
- `auth/VerifyEmail`: `status`

Redirecciones y respuestas que no deben cambiar:

- Login y registro conservan el destino intended a la ruta home.
- La verificación exitosa conserva home?verified=1; el aviso para usuario ya verificado conserva el destino home.
- Logout conserva la redirección a /.
- El reenvío de notificación conserva el retorno back con status verification-link-sent.

## Existing Code Leverage

- app/Auth/Modules/Password/ aporta el patrón reciente de Controllers, Requests, Services y Tests.
- app/Auth/Modules/TrustedDevice/ confirma que los submódulos comparten providers, modelos, rutas y configuración del dominio Auth.
- AuthRouteServiceProvider sigue cargando app/Auth/routes/routes.php; sus includes conservan los grupos guest/auth.
- vite.config.ts ya fija Wayfinder en resources/js/shared/wayfinder con formVariants activado; la carpeta generada está ignorada por Git.
- Las páginas afectadas son Login.tsx, Register.tsx y VerifyEmail.tsx; sus nombres Inertia y ubicación permanecen.
- Pest v5.2.1 está instalado y app/Auth/Modules/Password/Tests/ usa it(); tests/Pest.php aplica TestCase y RefreshDatabase a directorios de módulos.

## Tasks

### TASK-001: Mover LoginSessionController y reconectar sus rutas

Mover el controlador a Modules/Login y actualizar las referencias de las rutas login/store y logout. LoginRequest permanece temporalmente bajo su namespace actual para que este paso sea coherente y ejecutable.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** Ninguna  
**Review:** codex

**Acceptance Criteria:**
  - LoginSessionController queda bajo App\Auth\Modules\Login\Controllers y es el controlador usado por GET/POST /login y POST /logout.
  - route:list conserva login, login.store y logout con sus URI y middleware guest/auth.
  - El controlador sigue importando temporalmente la clase LoginRequest existente; no cambia su autenticación, limitación ni redirecciones.

**writeScope:**
  - `move app/Auth/Login/Controllers/LoginSessionController.php -> app/Auth/Modules/Login/Controllers/LoginSessionController.php`
  - `app/Auth/routes/login.php`
  - `app/Auth/routes/routes.php`

**validateCommand:**
`rtk php artisan route:list --name=login --except-vendor -vv && rtk php artisan route:list --name=logout --except-vendor -vv`

### TASK-002: Mover LoginRequest al submódulo Login

Mover LoginRequest y actualizar el import del controlador ya reubicado. Conservar reglas, rate limiting y validación actuales.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-001  
**Review:** codex

**Acceptance Criteria:**
  - LoginRequest queda bajo App\Auth\Modules\Login\Requests y el controlador importa ese namespace.
  - La ruta de login inicia y la validación conserva email/password requeridos.
  - Credenciales inválidas y bloqueo por rate limit siguen cubiertos por la prueba Login.

**writeScope:**
  - `move app/Auth/Login/Requests/LoginRequest.php -> app/Auth/Modules/Login/Requests/LoginRequest.php`
  - `app/Auth/Modules/Login/Controllers/LoginSessionController.php`

**validateCommand:**
`rtk php -l app/Auth/Modules/Login/Requests/LoginRequest.php && rtk php artisan route:list --name=login --except-vendor -vv`

### TASK-003: Mover RegisterUserController y reconectar la ruta

Mover el controlador a Modules/Register y actualizar las rutas guest de registro. RegisterRequest permanece temporalmente en su namespace actual.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** Ninguna  
**Review:** codex

**Acceptance Criteria:**
  - RegisterUserController queda bajo App\Auth\Modules\Register\Controllers y register.php apunta a esa clase.
  - GET/POST /register conservan los nombres register/register.store y el middleware guest.
  - El componente Inertia auth/register/Register y la redirección intended a home permanecen iguales.

**writeScope:**
  - `move app/Auth/Register/Controllers/RegisterUserController.php -> app/Auth/Modules/Register/Controllers/RegisterUserController.php`
  - `app/Auth/routes/register.php`

**validateCommand:**
`rtk php artisan route:list --name=register --except-vendor -vv`

### TASK-004: Mover RegisterRequest al submódulo Register

Mover RegisterRequest y actualizar el import en RegisterUserController sin modificar reglas ni comportamiento de registro.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-003  
**Review:** codex

**Acceptance Criteria:**
  - RegisterRequest queda bajo App\Auth\Modules\Register\Requests y el controlador importa ese namespace.
  - El endpoint register.store sigue visible bajo middleware guest y el formulario continúa enviándose.
  - El comportamiento de errores de validación existente permanece y el test de Register confirma el límite guest.

**writeScope:**
  - `move app/Auth/Register/Requests/RegisterRequest.php -> app/Auth/Modules/Register/Requests/RegisterRequest.php`
  - `app/Auth/Modules/Register/Controllers/RegisterUserController.php`

**validateCommand:**
`rtk php -l app/Auth/Modules/Register/Requests/RegisterRequest.php && rtk php artisan route:list --name=register --except-vendor -vv`

### TASK-005: Mover los controladores de aviso y verificación Email

Mover EmailVerificationPromptController y EmailVerifyController a Modules/Email y actualizar sus referencias en el fragmento de rutas, manteniendo notification controller en su ubicación hasta TASK-006.

**Type:** refactor  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** Ninguna  
**Review:** codex

**Acceptance Criteria:**
  - El aviso y la verificación usan controladores bajo App\Auth\Modules\Email\Controllers.
  - verification.notice y verification.verify conservan URI, nombres y middleware auth/signed/throttle.
  - Los nombres de página y props Inertia se mantienen; los casos de hash y usuario inválidos siguen en la suite Email.

**writeScope:**
  - `move app/Auth/Email/Controllers/EmailVerificationPromptController.php -> app/Auth/Modules/Email/Controllers/EmailVerificationPromptController.php`
  - `move app/Auth/Email/Controllers/EmailVerifyController.php -> app/Auth/Modules/Email/Controllers/EmailVerifyController.php`
  - `app/Auth/routes/email.php`

**validateCommand:**
`rtk php artisan route:list --name=verification --except-vendor -vv`

### TASK-006: Mover el controlador de reenvío de verificación

Mover EmailVerificationNotificationController y actualizar la última referencia antigua en routes/email.php.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-005  
**Review:** codex

**Acceptance Criteria:**
  - EmailVerificationNotificationController queda bajo App\Auth\Modules\Email\Controllers y verification.send apunta al nuevo namespace.
  - POST /email/verification-notification conserva auth y throttle:6,1.
  - La respuesta status verification-link-sent y el caso de usuario ya verificado sin reenvío permanecen.

**writeScope:**
  - `move app/Auth/Email/Controllers/EmailVerificationNotificationController.php -> app/Auth/Modules/Email/Controllers/EmailVerificationNotificationController.php`
  - `app/Auth/routes/email.php`

**validateCommand:**
`rtk php artisan route:list --name=verification.send --except-vendor -vv`

### TASK-007: Convertir y ampliar pruebas Login con Pest

Mover AuthenticationTest a Login/Tests y reescribir sus pruebas PHPUnit como funciones Pest. Extender la cobertura del login sin tocar la suite dedicada de 2FA.

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002  
**Review:** codex

**Acceptance Criteria:**
  - AuthenticationTest queda en app/Auth/Modules/Login/Tests como Pest it() consistente, sin clase PHPUnit ni setup manual de TestCase/RefreshDatabase.
  - La suite cubre render Inertia/props, credenciales válidas, logout, el flujo integrado de redirección a 2FA y rate limiting.
  - Una dataset de credenciales inválidas cubre contraseña errónea y email inexistente; ambos casos dejan al usuario guest. Verificar bloqueo al superar el umbral y limpieza del contador tras login válido.

**writeScope:**
  - `move tests/Feature/Auth/AuthenticationTest.php -> app/Auth/Modules/Login/Tests/AuthenticationTest.php`

**validateCommand:**
`rtk php artisan test --compact app/Auth/Modules/Login/Tests/AuthenticationTest.php`

### TASK-008: Convertir y ampliar pruebas Register con Pest

Mover RegistrationTest a Register/Tests y reescribirla con Pest. Cubrir persistencia y efectos del registro válido, además de fallos de validación de RegisterRequest.

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-004  
**Review:** codex

**Acceptance Criteria:**
  - RegistrationTest queda en app/Auth/Modules/Register/Tests como Pest it() consistente, sin clase PHPUnit ni setup manual de TestCase/RefreshDatabase.
  - El registro válido persiste al usuario, guarda contraseña hasheada, dispara Registered, autentica y redirige a home.
  - Payload vacío, email inválido/duplicado y contraseña sin confirmación válida devuelven errores, no persisten usuarios ni autentican; usar datasets nombrados cuando compartan aserciones.

**writeScope:**
  - `move tests/Feature/Auth/RegistrationTest.php -> app/Auth/Modules/Register/Tests/RegistrationTest.php`

**validateCommand:**
`rtk php artisan test --compact app/Auth/Modules/Register/Tests/RegistrationTest.php`

### TASK-009: Convertir y ampliar pruebas Email con Pest

Mover EmailVerificationTest y VerificationNotificationTest a Email/Tests y reescribir ambas en Pest. Extender los límites de autenticación, firma, idempotencia y notificaciones.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-006  
**Review:** codex

**Acceptance Criteria:**
  - Ambos archivos usan Pest it() consistentemente, factories por prueba y fakes locales para eventos/notificaciones.
  - La suite comprueba acceso autenticado, aviso para usuario pendiente/verificado y reenvío de VerifyEmail con status, sin reenviar si ya está verificado.
  - La URL firmada válida verifica y dispara Verified una vez; hash, usuario, firma alterada o expirada inválidos no verifican. Las redirecciones/assertions stale se alinean con home.

**writeScope:**
  - `move tests/Feature/Auth/EmailVerificationTest.php -> app/Auth/Modules/Email/Tests/EmailVerificationTest.php`
  - `move tests/Feature/Auth/VerificationNotificationTest.php -> app/Auth/Modules/Email/Tests/VerificationNotificationTest.php`

**validateCommand:**
`rtk php artisan test --compact app/Auth/Modules/Email/Tests`

### TASK-010: Registrar suites Login, Register y Email

Agregar las tres carpetas de Tests a phpunit.xml y al conjunto Pest configurado en tests/Pest.php siguiendo el registro existente de Password.

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-007, TASK-008, TASK-009  
**Review:** codex

**Acceptance Criteria:**
  - phpunit.xml registra suites Login, Register y Email apuntando a app/Auth/Modules/{Login,Register,Email}/Tests.
  - tests/Pest.php aplica TestCase y RefreshDatabase a esas carpetas según el patrón Password; las pruebas módulo-específicas usan Pest.
  - Las tres suites se ejecutan por nombre; TwoFactorChallenge, PasswordConfirmation y TrustedDevice no se incorporan accidentalmente.

**writeScope:**
  - `phpunit.xml`
  - `tests/Pest.php`

**validateCommand:**
`rtk php artisan test --compact Login && rtk php artisan test --compact Register && rtk php artisan test --compact Email`

### TASK-011: Regenerar Wayfinder y actualizar imports de acciones

Regenerar la salida configurada tras mover los controladores y actualizar los tres imports de acción en Login, Register y VerifyEmail. La salida bajo shared/wayfinder está ignorada por Git.

**Type:** refactor  
**Effort:** S  
**Agent:** react-vite-tailwind-engineer  
**Priority:** P1  
**Depends on:** TASK-002, TASK-004, TASK-006  
**Review:** codex

**Acceptance Criteria:**
  - Login.tsx, Register.tsx y VerifyEmail.tsx importan acciones desde App/Auth/Modules/{Login,Register,Email}/Controllers.
  - No quedan imports de acciones Wayfinder a los namespaces antiguos Login/Register/Email.
  - Los helpers de rutas y nombres de componentes Inertia permanecen sin cambios y el lint de los tres archivos pasa.

**writeScope:**
  - `resources/js/modules/auth/login/Login.tsx`
  - `resources/js/modules/auth/register/Register.tsx`
  - `resources/js/modules/auth/VerifyEmail.tsx`

**validateCommand:**
`rtk php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder --no-interaction && rtk npm exec eslint -- resources/js/modules/auth/login/Login.tsx resources/js/modules/auth/register/Register.tsx resources/js/modules/auth/VerifyEmail.tsx`

### TASK-012: Verificar integración, contratos y gates completos

Comprobar rutas, suites modulares, flujos en navegador y gates backend/frontend en el orden del proyecto. composer doctor debe ser el último gate local.

**Type:** validation  
**Effort:** M  
**Agent:** general-purpose  
**Priority:** P1  
**Depends on:** TASK-010, TASK-011  
**Review:** codex

**Acceptance Criteria:**
  - route:list confirma los ocho contratos documentados y las páginas mantienen componentes, props y redirecciones.
  - Las suites Pest Login/Register/Email pasan independientemente y pasan la suite completa, Pint, PHPStan, Rector dry/apply, format, lint, types, build y build:ssr.
  - El smoke check manual confirma Login/Register/VerifyEmail y los flujos válidos e inválidos; composer doctor se ejecuta al final y no hay más gates después.

**writeScope:**
  - Sin archivos; verificación de integración.

**validateCommand:**
`Manual browser smoke with rtk composer run dev on /login, /register and /verify-email; then, in order: rtk vendor/bin/pint --dirty --format agent; rtk composer phpstan; rtk composer rector-dry; rtk composer rector; rtk php artisan test --compact Login; rtk php artisan test --compact Register; rtk php artisan test --compact Email; rtk php artisan test --compact; rtk php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder --no-interaction; rtk npm run format:check; rtk npm run lint:check; rtk npm run types; rtk npm run build; rtk npm run build:ssr; finish with rtk composer doctor.`


## Failure Modes

- AuthenticationTest, EmailVerificationTest y VerificationNotificationTest importan App\Models\User, pero el modelo actual está en App\User\Models\User.
- Las pruebas heredadas esperan route('dashboard'), mientras que el código actual usa route('home'); corregir las aserciones al trasladarlas, sin cambiar redirecciones de producción.
- AuthenticationTest incluye un caso del login que redirige al challenge 2FA. Mantenerlo con Login como prueba de integración de login; dejar intacta la suite dedicada TwoFactorChallengeTest.
- Wayfinder generado está ignorado por Git; validar los imports producidos y el typecheck/build para detectar referencias viejas.
- No se encontró un runner de navegador en los scripts del proyecto; la verificación visual de los flujos será manual.
- La suite completa puede revelar fallos preexistentes en pruebas fuera de alcance; separarlos del cambio SOC-32 en vez de ampliar el alcance silenciosamente.

## Ship Cut

No considerar la reorganización lista hasta que las tres suites estén registradas, Wayfinder apunte a los controladores nuevos, los contratos de rutas estén comparados y los gates más la revisión manual estén completos. Una integración parcial de namespaces/rutas no es un corte publicable.

## Test Coverage Map

| Área | Pruebas | Escenarios |
|---|---|---|
| Login | `AuthenticationTest.php` | página auth/login/Login y props canResetPassword/status; login válido establece sesión y redirige a home; contraseña errónea y email inexistente rechazan autenticación; rate limit bloquea sobre el umbral y el login válido limpia el contador; 2FA pendiente redirige al challenge sin autenticar; logout cierra sesión y redirige a / |
| Register | `RegistrationTest.php` | página auth/register/Register; registro válido persiste usuario, hashea contraseña, dispara Registered, autentica y redirige a home; payload vacío y email inválido muestran errores sin persistir; email duplicado y password_confirmation inválida se rechazan sin autenticar; register.store conserva el límite guest |
| Email | `EmailVerificationTest.php`, `VerificationNotificationTest.php` | notice protegido por auth y página para usuario pendiente; usuario verificado vuelve a home desde el notice; URL firmada válida marca email y dispara Verified una sola vez; hash/usuario inválidos y firmas manipuladas/expiradas no verifican; reenvío notifica al usuario pendiente y retorna status verification-link-sent; usuario verificado no recibe otra notificación |
| Excluidas | `TwoFactorChallengeTest.php`, `PasswordConfirmationTest.php`, `TrustedDevice tests` | permanecen en su ubicación y fuera de estos cambios |

Convenciones: **Pest v5.2.1**. Usar it() de forma consistente, siguiendo app/Auth/Modules/Password/Tests; los archivos trasladados no deben conservar clases PHPUnit. Pruebas feature HTTP con Arrange-Act-Assert; TestCase y RefreshDatabase vienen de tests/Pest.php, sin repetir traits por archivo. Crear datos con factories dentro de cada prueba; usar datasets nombrados para entradas equivalentes; aislar Event::fake y Notification::fake en las pruebas que los necesitan. En escrituras comprobar respuesta, estado persistido y efectos; en fallos comprobar errores, ausencia de persistencia y que la sesión siga guest.

## Execution Summary

- **Tareas:** 12.
- **Tareas iniciales paralelas:** TASK-001, TASK-003, TASK-005.
- **Camino crítico:** TASK-005 → TASK-006 → TASK-009 → TASK-010 → TASK-012.
- Email controllers/routes → tests Email → registro de suites → integración completa; hasta cinco tareas en secuencia.
- Mover inicialmente Login, Register y Email en paralelo; luego completar requests y tests de cada módulo; suites y frontend avanzan en paralelo cuando sus dependencias terminen.

## Task Dependencies

- **TASK-001** no tiene dependencias
- **TASK-002** depende de TASK-001
- **TASK-003** no tiene dependencias
- **TASK-004** depende de TASK-003
- **TASK-005** no tiene dependencias
- **TASK-006** depende de TASK-005
- **TASK-007** depende de TASK-002
- **TASK-008** depende de TASK-004
- **TASK-009** depende de TASK-006
- **TASK-010** depende de TASK-007, TASK-008, TASK-009
- **TASK-011** depende de TASK-002, TASK-004, TASK-006
- **TASK-012** depende de TASK-010, TASK-011

Capas ejecutables: L0: TASK-001, TASK-003, TASK-005; L1: TASK-002, TASK-004, TASK-006; L2: TASK-007, TASK-008, TASK-009, TASK-011; L3: TASK-010; L4: TASK-012.
