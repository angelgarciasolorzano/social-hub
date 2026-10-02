# Plan: SOC-33: Mover User TwoFactor a Modules y ampliar su cobertura

## Overview

SOC-33 reorganiza la gestión 2FA bajo User y completa su cobertura sin alterar contratos. La expansión confirmada añade la corrección de referencias obsoletas en `PasswordConfirmationTest.php`, su conversión a Pest, el registro de Pest para TwoFactor y la sincronización documental.

Issue: [SOC-33](https://linear.app/social-hub-ang/issue/SOC-33/refactor-move-user-twofactor-into-modules-and-add-module-coverage) — Backlog, prioridad Medium; relacionada con SOC-32 (Done).

Modo confirmado: **EXPANSION**. Revisión por defecto: **codex**. La revisión de cobertura añadió trasladar el registro de RecoveryCodesGenerated al proveedor compartido del dominio User; el listener permanece en TwoFactor.

## Scope Challenge

- El usuario confirmó EXPANSION e incluyó PasswordConfirmationTest en SOC-33; después amplió TASK-001 para corregir el componente Inertia esperado y convertir la clase PHPUnit a Pest.
- El test cubre la pantalla Fortify `password.confirm` y la redirección intended hacia una ruta de User/TwoFactor; permanece en tests/Feature/Auth como integración entre módulos, no en Password.
- Se incluyen el registro de Pest y la sincronización documental requeridos por la organización modular.
- La ejecución sigue el plan aprobado, una tarea atómica por paso, con revisión antes de continuar.

## Prerequisites

- SOC-32 está Done; app/Auth/Modules/TrustedDevice/ y app/Auth/Modules/Password/ proveen patrones locales.
- Las rutas actuales son setting.security.two-factor-authentication.index, .destroy y .store-recovery-codes; route:list confirma que two-factor.show no existe.
- vite.config.ts configura Wayfinder en resources/js/shared/wayfinder con formVariants activado.
- tests/Pest.php registra directorios modulares; la nueva carpeta debe añadirse junto con la suite en phpunit.xml.
- El árbol de trabajo estaba limpio durante el análisis.
- Al ejecutar el plan, completar una tarea atómica y pausar para revisión antes de continuar, según .ai/rules/linear.md.

## Non-Goals

- Cambiar challenge de login, endpoints de Fortify o el flujo de activación/configuración 2FA.
- Cambiar URI, nombres de ruta, middleware, redirecciones, props, componentes Inertia o comportamiento.
- Mover/editar componentes frontend o código del módulo TrustedDevice.
- Mover PasswordConfirmationTest a Password o TwoFactor; permanece como prueba transversal en tests/Feature/Auth.
- Cambiar estado o publicar comentarios en Linear.
- Ejecutar la implementación durante esta etapa de planificación.

## Contracts

### Rutas

| Método | URI | Nombre | Middleware |
|---|---|---|---|
| GET | `/setting/two-factor-authentication` | `setting.security.two-factor-authentication.index` | auth, verified, password.confirm cuando confirmPassword está activo |
| DELETE | `/setting/two-factor-authentication` | `setting.security.two-factor-authentication.destroy` | auth, verified |
| POST | `/setting/two-factor-authentication/regenerate-recovery-codes` | `setting.security.two-factor-authentication.store-recovery-codes` | auth, verified |

### Inertia: `setting/modules/twoFactor/TwoFactor`

Props que deben conservarse:
- `canManageTwoFactor`
- `twoFactorEnabled`
- `requiresConfirmation`
- `twoFactorConfirmedAt`
- `recoveryCodesRegeneratedAt`
- `trustedDevicesCount`
- `trustedDevices (optional)`
- `firstTrustedDevice (optional)`
- `props expuestas por TrustedDeviceCurrentProps`

### Eventos y comportamiento

- RecoveryCodesGenerated -> TrackRecoveryCodesRegeneration; UserEventServiceProvider lo registra y persiste recovery_codes_regenerated_at.
- TwoFactorAuthenticationDisabled -> TrustedDeviceInvalidate; conserva la invalidación de dispositivos confiables.
- ValidTwoFactorAuthenticationCodeProvided -> TrustedDeviceRemember; permanece intacto.
- Regenerar recovery codes requiere la contraseña actual; el evento actualiza recovery_codes_regenerated_at.
- Desactivar 2FA requiere contraseña actual y TOTP válido; entradas inválidas no desactivan 2FA.
- La suite TrustedDevice existente ya verifica revocación e invalidación de caché al desactivar 2FA.

Wayfinder usa `resources/js/shared/wayfinder`. Helpers nombrados que deben seguir disponibles: `index`, `destroy`, `storeRecoveryCodes`.

## Existing Code Leverage

- app/User/TwoFactor/ contiene un controlador, tres Form Requests y TrackRecoveryCodesRegeneration que pasarían a app/User/Modules/TwoFactor/.
- app/User/routes/security.php ya declara las rutas estables; solo requiere actualizar el import del controlador.
- app/Auth/Providers/AuthEventServiceProvider.php registra TrustedDevice y actualmente también el listener de User; TASK-010 trasladará este último a app/User/Providers/UserEventServiceProvider.php.
- app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php cubre el efecto de desactivar 2FA.
- tests/Feature/Settings/TwoFactorAuthenticationTest.php es cobertura parcial, pero usa two-factor.show y settings/two-factor, ambos obsoletos.
- tests/Feature/Auth/PasswordConfirmationTest.php prueba el flujo genérico Fortify password.confirm; el destino y el componente Inertia esperado están desactualizados, y la clase aún usa sintaxis PHPUnit.
- docs/architecture/backend.md aún menciona app/User/TwoFactorAuthentication.

## Tasks

### TASK-001: Corregir y convertir PasswordConfirmationTest a Pest

Convertir tests/Feature/Auth/PasswordConfirmationTest.php a declaraciones Pest, corregir el componente esperado a auth/password/ConfirmPassword y usar setting.security.two-factor-authentication.index como destino intended vigente. Mantenerlo en tests/Feature/Auth: el archivo prueba el endpoint genérico Fortify password.confirm y su integración con la ruta de configuración User/TwoFactor; no es una prueba exclusiva de las acciones internas del módulo Password.

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** Ninguna  
**Review:** codex

**Acceptance Criteria:**
- Los tres casos usan sintaxis Pest consistente y conservan render, autenticación requerida y redirección intended.
- Las expectativas Inertia y de ruta corresponden a auth/password/ConfirmPassword y setting.security.two-factor-authentication.index; no quedan referencias a auth/confirm-password ni two-factor.show.
- El archivo permanece en tests/Feature/Auth como integración Auth -> User/TwoFactor, y la prueba enfocada pasa.

**Files to modify:**
- `tests/Feature/Auth/PasswordConfirmationTest.php`

**writeScope:**
- `tests/Feature/Auth/PasswordConfirmationTest.php`

**validateCommand:**
```bash
rtk php artisan test --compact tests/Feature/Auth/PasswordConfirmationTest.php
```

### TASK-002: Mover dos Form Requests y actualizar los imports del controlador

Mover TwoFactorRequest y TwoFactorRegenerateRecoveryCodesRequest a app/User/Modules/TwoFactor/Requests/ y actualizar sus imports en el controlador, que permanece temporalmente en su ubicación actual.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** Ninguna  
**Review:** codex

**Acceptance Criteria:**
- Ambas clases quedan bajo App\User\Modules\TwoFactor\Requests y el controlador importa esos tipos.
- TwoFactorRequest conserva InteractsWithTwoFactorState y su comportamiento cuando Fortify no permite administrar 2FA.
- Regenerar códigos sigue requiriendo contraseña actual; no cambian reglas, mensajes ni respuesta.

**Moves:**
- `app/User/TwoFactor/Requests/TwoFactorRequest.php` → `app/User/Modules/TwoFactor/Requests/TwoFactorRequest.php`
- `app/User/TwoFactor/Requests/TwoFactorRegenerateRecoveryCodesRequest.php` → `app/User/Modules/TwoFactor/Requests/TwoFactorRegenerateRecoveryCodesRequest.php`

**Files to modify:**
- `app/User/TwoFactor/Controllers/TwoFactorController.php`

**writeScope:**
- `move app/User/TwoFactor/Requests/TwoFactorRequest.php -> app/User/Modules/TwoFactor/Requests/TwoFactorRequest.php`
- `move app/User/TwoFactor/Requests/TwoFactorRegenerateRecoveryCodesRequest.php -> app/User/Modules/TwoFactor/Requests/TwoFactorRegenerateRecoveryCodesRequest.php`
- `app/User/TwoFactor/Controllers/TwoFactorController.php`

**validateCommand:**
```bash
rtk php -l app/User/Modules/TwoFactor/Requests/TwoFactorRequest.php && rtk php -l app/User/Modules/TwoFactor/Requests/TwoFactorRegenerateRecoveryCodesRequest.php && rtk php -l app/User/TwoFactor/Controllers/TwoFactorController.php
```

### TASK-003: Mover TwoFactorDisableRequest y actualizar el import

Mover TwoFactorDisableRequest al submódulo y cambiar su referencia en TwoFactorController, manteniendo autorización, contraseña y verificación TOTP.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-002  
**Review:** codex

**Acceptance Criteria:**
- TwoFactorDisableRequest queda bajo App\User\Modules\TwoFactor\Requests y el controlador importa esa clase.
- La petición sigue prohibiendo la operación si el usuario no tiene 2FA habilitado.
- Contraseña incorrecta y TOTP inválido siguen rechazándose sin desactivar 2FA.

**Moves:**
- `app/User/TwoFactor/Requests/TwoFactorDisableRequest.php` → `app/User/Modules/TwoFactor/Requests/TwoFactorDisableRequest.php`

**Files to modify:**
- `app/User/TwoFactor/Controllers/TwoFactorController.php`

**writeScope:**
- `move app/User/TwoFactor/Requests/TwoFactorDisableRequest.php -> app/User/Modules/TwoFactor/Requests/TwoFactorDisableRequest.php`
- `app/User/TwoFactor/Controllers/TwoFactorController.php`

**validateCommand:**
```bash
rtk php -l app/User/Modules/TwoFactor/Requests/TwoFactorDisableRequest.php && rtk php -l app/User/TwoFactor/Controllers/TwoFactorController.php
```

### TASK-004: Mover TwoFactorController y reconectar las rutas

Mover el controlador a app/User/Modules/TwoFactor/Controllers/ y actualizar su import en security.php. Conservar rutas, middleware, respuestas y contrato Inertia.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-003  
**Review:** codex

**Acceptance Criteria:**
- route:list conserva métodos, URI y nombres actuales; auth/verified siguen en las rutas y password.confirm sigue condicionado en GET.
- El componente Inertia y todas sus props, incluidas las opcionales de dispositivos confiables, permanecen iguales.
- Las rutas Fortify de activación, confirmación y challenge no se trasladan a este controlador.

**Moves:**
- `app/User/TwoFactor/Controllers/TwoFactorController.php` → `app/User/Modules/TwoFactor/Controllers/TwoFactorController.php`

**Files to modify:**
- `app/User/routes/security.php`

**writeScope:**
- `move app/User/TwoFactor/Controllers/TwoFactorController.php -> app/User/Modules/TwoFactor/Controllers/TwoFactorController.php`
- `app/User/routes/security.php`

**validateCommand:**
```bash
rtk php artisan route:list --name=setting.security.two-factor-authentication --except-vendor -vv
```

### TASK-005: Mover TrackRecoveryCodesRegeneration y actualizar su registro

Mover el listener a app/User/Modules/TwoFactor/Listeners/ y actualizar su import en AuthEventServiceProvider. Los listeners propiedad de TrustedDevice permanecen intactos.

**Type:** refactor  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** Ninguna  
**Review:** codex

**Acceptance Criteria:**
- RecoveryCodesGenerated sigue conectado a TrackRecoveryCodesRegeneration y actualiza recovery_codes_regenerated_at.
- TrustedDeviceInvalidate y TrustedDeviceRemember conservan sus registros y namespaces.
- El provider deja de importar el namespace anterior y el listener nuevo pasa lint de PHP.

**Moves:**
- `app/User/TwoFactor/Listeners/TrackRecoveryCodesRegeneration.php` → `app/User/Modules/TwoFactor/Listeners/TrackRecoveryCodesRegeneration.php`

**Files to modify:**
- `app/Auth/Providers/AuthEventServiceProvider.php`

**writeScope:**
- `move app/User/TwoFactor/Listeners/TrackRecoveryCodesRegeneration.php -> app/User/Modules/TwoFactor/Listeners/TrackRecoveryCodesRegeneration.php`
- `app/Auth/Providers/AuthEventServiceProvider.php`

**validateCommand:**
```bash
rtk php -l app/User/Modules/TwoFactor/Listeners/TrackRecoveryCodesRegeneration.php && rtk rg -n 'TrackRecoveryCodesRegeneration|TrustedDeviceInvalidate|TrustedDeviceRemember' app/Auth/Providers/AuthEventServiceProvider.php
```

### TASK-006: Convertir a Pest y co-ubicar la prueba de configuración TwoFactor

Mover tests/Feature/Settings/TwoFactorAuthenticationTest.php a app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php, convertirla a declaraciones Pest, actualizar las expectativas al contrato actual y registrar el directorio Pest y la suite TwoFactor.

**Type:** test  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-004  
**Review:** codex

**Acceptance Criteria:**
- La prueba de pantalla usa la ruta y el componente vigentes, no two-factor.show ni settings/two-factor.
- El archivo usa declaraciones Pest con nombres que describen los comportamientos de configuración de TwoFactor.
- Se conservan render, password.confirm habilitado/deshabilitado y el estado `canManageTwoFactor=false` cuando Fortify deshabilita 2FA.
- `tests/Pest.php` aplica TestCase y RefreshDatabase al módulo, y Pest con `--testsuite=TwoFactor` descubre y ejecuta el test co-ubicado.

**Moves:**
- `tests/Feature/Settings/TwoFactorAuthenticationTest.php` → `app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php`

**Files to modify:**
- `tests/Pest.php`
- `phpunit.xml`

**writeScope:**
- `move tests/Feature/Settings/TwoFactorAuthenticationTest.php -> app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php`
- `tests/Pest.php`
- `phpunit.xml`

**validateCommand:**
```bash
rtk vendor/bin/pest --configuration=phpunit.xml --testsuite=TwoFactor --compact
```

### TASK-007: Cubrir regeneración, listener y desactivación con Pest

Crear pruebas HTTP Pest co-ubicadas para regeneración y desactivación. La prueba de regeneración valida el enlace RecoveryCodesGenerated -> listener -> timestamp. El directorio Pest se registra en TASK-006.

**Type:** test  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P1  
**Depends on:** TASK-005, TASK-006  
**Review:** codex

**Acceptance Criteria:**
- TwoFactorRecoveryCodesTest cubre contraseña válida/inválida; el éxito reemplaza códigos y actualiza recovery_codes_regenerated_at, y el fallo conserva ambos.
- TwoFactorDisableTest cubre TOTP válido, contraseña incorrecta y código inválido; los rechazos conservan 2FA habilitado.
- Ambos archivos Pest pasan individualmente con el TestCase y RefreshDatabase registrados para TwoFactor.

**Files to create:**
- `app/User/Modules/TwoFactor/Tests/TwoFactorRecoveryCodesTest.php`
- `app/User/Modules/TwoFactor/Tests/TwoFactorDisableTest.php`

**writeScope:**
- `app/User/Modules/TwoFactor/Tests/TwoFactorRecoveryCodesTest.php`
- `app/User/Modules/TwoFactor/Tests/TwoFactorDisableTest.php`

**validateCommand:**
```bash
rtk php artisan test --compact app/User/Modules/TwoFactor/Tests/TwoFactorRecoveryCodesTest.php && rtk php artisan test --compact app/User/Modules/TwoFactor/Tests/TwoFactorDisableTest.php
```

### TASK-008: Regenerar Wayfinder y comprobar los helpers nombrados

Ejecutar el generador con la ruta configurada en vite.config.ts. Revisar su salida y confirmar que index, destroy y storeRecoveryCodes resuelven sin tocar las páginas.

**Type:** chore  
**Effort:** S  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-004  
**Review:** codex

**Acceptance Criteria:**
- Wayfinder se genera con --with-form y --path=resources/js/shared/wayfinder.
- Los helpers nombrados siguen correspondiendo a las rutas de seguridad actuales y los imports frontend existentes resuelven.
- No se editan helpers generados a mano ni se modifican componentes frontend.

**Files to modify:**
- `resources/js/shared/wayfinder/ (salida generada)`

**writeScope:**
- `resources/js/shared/wayfinder/ (salida generada por Wayfinder; no editar manualmente)`

**validateCommand:**
```bash
rtk php artisan wayfinder:generate --with-form --path=resources/js/shared/wayfinder --no-interaction
```

### TASK-010: Trasladar el registro del listener al proveedor de eventos de User

Crear el proveedor compartido UserEventServiceProvider y registrarlo desde UserServiceProvider. Mover ahí el enlace RecoveryCodesGenerated -> TrackRecoveryCodesRegeneration; AuthEventServiceProvider conserva los listeners de TrustedDevice. Los proveedores permanecen en el padre del dominio, no dentro de Modules/TwoFactor.

**Type:** refactor
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P1
**Depends on:** TASK-005, TASK-007
**Review:** codex

**Acceptance Criteria:**
- UserServiceProvider registra UserEventServiceProvider y mantiene el registro de UserRouteServiceProvider.
- RecoveryCodesGenerated se escucha una sola vez desde app/User/Providers/UserEventServiceProvider; AuthEventServiceProvider conserva solo los listeners de TrustedDevice y no importa código de User.
- La prueba de regeneración confirma que el timestamp sigue actualizándose y que una contraseña incorrecta conserva códigos/timestamp; la prueba TrustedDevice sigue pasando.

**Files to create:**
- app/User/Providers/UserEventServiceProvider.php

**Files to modify:**
- app/User/Providers/UserServiceProvider.php
- app/Auth/Providers/AuthEventServiceProvider.php

**writeScope:**
- app/User/Providers/UserEventServiceProvider.php
- app/User/Providers/UserServiceProvider.php
- app/Auth/Providers/AuthEventServiceProvider.php

**validateCommand:** rtk vendor/bin/pest --configuration=phpunit.xml --compact app/User/Modules/TwoFactor/Tests/TwoFactorRecoveryCodesTest.php && rtk vendor/bin/pest --configuration=phpunit.xml --compact app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php && rtk php -l app/User/Providers/UserEventServiceProvider.php && rtk php -l app/User/Providers/UserServiceProvider.php && rtk php -l app/Auth/Providers/AuthEventServiceProvider.php

### TASK-009: Sincronizar documentación y ejecutar los gates finales

Actualizar la ubicación de User TwoFactor en CLAUDE.md y docs/architecture/backend.md. Tras integrar los pasos anteriores, completar los gates de SOC-33 y la revisión manual en navegador.

**Type:** docs  
**Effort:** M  
**Agent:** laravel-senior-engineer  
**Priority:** P2  
**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-010
**Review:** codex

**Acceptance Criteria:**
- La documentación ubica TwoFactor en app/User/Modules/TwoFactor y describe UserEventServiceProvider como dueño del registro RecoveryCodesGenerated; Auth conserva sus listeners TrustedDevice.
- Pasan TwoFactor, TrustedDevice, PasswordConfirmationTest, suite completa, Pint, PHPStan, Rector dry-run seguido de Rector, format, lint, TypeScript, build y SSR.
- La revisión manual confirma carga, password.confirm cuando aplica, regeneración y desactivación; composer doctor se ejecuta al final.

**Files to modify:**
- `CLAUDE.md`
- `docs/architecture/backend.md`

**writeScope:**
- `CLAUDE.md`
- `docs/architecture/backend.md`

**validateCommand:**
```bash
rtk grep -n 'app/User/Modules/TwoFactor' CLAUDE.md docs/architecture/backend.md && rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk composer rector-dry && rtk composer rector && rtk vendor/bin/pest --configuration=phpunit.xml --testsuite=TwoFactor --compact && rtk php artisan test --compact TrustedDevice && rtk php artisan test --compact tests/Feature/Auth/PasswordConfirmationTest.php && rtk php artisan test --compact && rtk npm run format:check && rtk npm run lint:check && rtk npm run types && rtk npm run build && rtk npm run build:ssr && rtk composer doctor
```

## Failure Modes

- **Fortify está deshabilitado o el usuario no confirmó contraseña.** Se conserva el rechazo por estado inválido y el redirect a password.confirm cuando confirmPassword está activo.
- **La contraseña para regeneración es incorrecta.** Hay error de validación y recovery codes/timestamp no cambian.
- **La contraseña de desactivación o el TOTP es inválido.** La petición vuelve con error, 2FA sigue activo y no ocurre la invalidación de dispositivos.
- **Se pierde el registro del listener al moverlo.** La prueba HTTP detecta que recovery_codes_regenerated_at no se actualiza; la revisión de proveedores detecta si queda registrado en Auth o duplicado.
- **Un namespace, ruta o componente conserva una referencia vieja.** route:list, la suite TwoFactor y el typecheck/build detectan el contrato roto.

## Ship Cut

No marcar SOC-33 terminado hasta que los imports activos no apunten a app/User/TwoFactor, las rutas y props mantengan su contrato, TwoFactor/TrustedDevice/PasswordConfirmationTest y la suite completa pasen, Wayfinder esté regenerado, RecoveryCodesGenerated quede registrado una sola vez desde UserEventServiceProvider, la documentación sincronizada, la verificación manual hecha y composer doctor haya sido el último gate.

## Test Coverage Map

| Área | Prueba | Casos principales |
|---|---|---|
| TwoFactor settings | `app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php` | pantalla y componente Inertia vigentes; password.confirm condicionado por configuración; Fortify deshabilitado deja la administración indisponible |
| Recovery codes + listener | `app/User/Modules/TwoFactor/Tests/TwoFactorRecoveryCodesTest.php` | regeneración válida; timestamp actualizado por RecoveryCodesGenerated; contraseña inválida conserva códigos y timestamp |
| Disable 2FA | `app/User/Modules/TwoFactor/Tests/TwoFactorDisableTest.php` | contraseña/TOTP válidos; contraseña incorrecta no desactiva; TOTP inválido no desactiva |
| TrustedDevice integration | `app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php` | dispositivos/cachés del usuario se invalidan; datos de otros usuarios permanecen intactos |
| Password confirmation integration | `tests/Feature/Auth/PasswordConfirmationTest.php` | Pest verifica el componente vigente, el rechazo sin autenticación y la redirección intended a la ruta actual de TwoFactor |
| Excluded login challenge | `tests/Feature/Auth/TwoFactorChallengeTest.php` | permanece fuera del módulo y de la suite TwoFactor de configuración |

## Execution Summary

- Tareas: **10**.
- Iniciales independientes: `TASK-001`, `TASK-002`, `TASK-005`.
- Camino crítico (7 tareas): TASK-002 -> TASK-003 -> TASK-004 -> TASK-006 -> TASK-007 -> TASK-010 -> TASK-009.
- Paralelismo: PasswordConfirmationTest y el listener son independientes de la cadena Requests/controlador. El controlador desbloquea Wayfinder; las pruebas de módulo esperan al cableado. La transferencia del registro espera la cobertura de regeneración; la documentación final espera ese nuevo proveedor.
- Regla de ejecución: Aunque el DAG identifica tareas independientes, ejecutar una tarea atómica y pausar para revisión explícita antes de continuar.

## Task Dependencies

- `TASK-001` ← sin dependencias
- `TASK-002` ← sin dependencias
- `TASK-003` ← `TASK-002`
- `TASK-004` ← `TASK-003`
- `TASK-005` ← sin dependencias
- `TASK-006` ← `TASK-004`
- `TASK-007` ← `TASK-005`, `TASK-006`
- `TASK-008` ← `TASK-004`
- TASK-009 <- TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-010
- TASK-010 <- TASK-005, TASK-007

## Test Methodology

- Framework: TASK-001 convierte PasswordConfirmationTest a Pest; los nuevos tests modulares también se escriben en Pest.
- Organización: tests/Pest.php registra TestCase y RefreshDatabase para TwoFactor; phpunit.xml crea la suite independiente.
- Aserciones: Pruebas HTTP revisan respuesta, errores, estado persistido y efectos del listener; no duplicar la regresión propiedad de TrustedDevice.
- Validación: Cada tarea valida su writeScope; los gates completos van en la integración final.
