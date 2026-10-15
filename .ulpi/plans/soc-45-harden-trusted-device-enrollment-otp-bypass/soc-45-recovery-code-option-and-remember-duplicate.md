# Plan: SOC-45 (extra): Código de recuperación al desactivar 2FA y recordar navegador sin duplicados

## Overview

Dos cambios pedidos tras probar SOC-45 en el navegador. (1) El diálogo de reactivar ya acepta un código de recuperación, pero desactivar 2FA pide solo el código de seis dígitos: se unifica la regla en TwoFactorCodeVerifier::verifyCode (TOTP o recuperación de un solo uso), reactivar y desactivar 2FA la usan, y se comparte un componente de interfaz con el toggle. Alta y renovación siguen solo con TOTP. (2) Bug: al revocar un dispositivo, volver a iniciar sesión con OTP y marcar recordar este dispositivo crea una fila nueva y deja la revocada (dos dispositivos del mismo navegador, confirmado en la base y en la pantalla del usuario: ids 557 revocado y 558 activo con la misma huella); el login pasa a reutilizar la fila de la misma huella.

**Issue:** [SOC-45](https://linear.app/social-hub-ang/issue/SOC-45/feature-harden-trusted-device-enrollment-and-otp-bypass) — Feature: Harden trusted-device enrollment and OTP bypass
**Modo:** HOLD · **Estilo:** Paso a paso: se implementa una tarea y se pausa para revisión (regla .ai/rules/linear.md). · **Review por defecto:** claude

## Scope Challenge

Modo HOLD. Decisiones del usuario: arreglar el duplicado reactivando la fila revocada (una sola fila, historial en los eventos) y ofrecer el código de recuperación solo al desactivar 2FA y al reactivar. Se evaluó ofrecerlo también en alta y renovación y se descartó: en esos momentos el usuario ya tiene sesión y su app a la mano, quien no la tiene ya puede iniciar sesión con un código de recuperación y marcar recordar el dispositivo, y cada uso gasta uno de los 8 códigos, que son el respaldo ante la pérdida del teléfono (se mantiene la decisión B de SOC-45). Se descartó crear una fila nueva y borrar la revocada (pierde la fila de historial) y dejar ambas (es lo que ocurre hoy). Tampoco se cambia el diálogo de confirmar 2FA al activarlo, que no puede usar un código de recuperación.

## Prerequisites

- Las suites de app y tests pasan antes de empezar (con memory_limit de 512M).
- Se leyeron .ai/rules/{app,backend,frontend,linear}.md: pruebas pequeñas de un comportamiento, Eloquent en vez de DB::table en pruebas, reutilizar utilitarios, pausa tras cada tarea.
- TwoFactorCodeVerifier (SOC-45) existe con consumeRecoveryCode dentro de la transacción del llamador.

## Non-Goals

- Aceptar códigos de recuperación en el alta y la renovación de dispositivos (decisión del usuario: solo TOTP, para no gastar los 8 códigos).
- Cambiar el diálogo de confirmar 2FA al activarlo (VerifyOtpStep): confirma un secreto nuevo y no admite códigos de recuperación.
- Cambiar el reto de login de Fortify, que ya acepta códigos de recuperación.
- Migrar o limpiar filas duplicadas ya existentes en bases de desarrollo (se corrigen volviendo a sembrar).
- Rediseñar las interfaces de TwoFactor o TrustedDevice.

## Contracts

- TwoFactorCodeVerifier::verifyCode(User, string): seis dígitos son un TOTP; cualquier otro formato es un código de recuperación que se consume. Debe llamarse dentro de una transacción. Lo usan reactivar (en el Service) y desactivar 2FA (en su request).
- Alta y renovación de dispositivos no cambian: solo TOTP, verificado en la request (RequiresTwoFactorOtp).
- Recordar el navegador en el login: una sola fila por huella (usuario, user agent, sistema, IP). Si existe (revocada, expirada o activa) se reutiliza con token nuevo y evento Reactivated (si estaba revocada) o Renewed; si no existe se crea con evento Created. La alta manual sigue devolviendo AlreadyActive o AlreadyRevoked sin cambios.
- Mensajes de error fijos, sin incluir el código, el secreto ni los códigos de recuperación.
- Capability providers: Fortify (TwoFactorAuthenticationProvider, User::replaceRecoveryCode), TrustedDeviceService (transacciones, eventos, caché del dashboard) y DeviceDetector para inferir los metadatos del dispositivo.
- Wiring: TwoFactorCodeField es un componente compartido nuevo en modules/setting/shared/components; el único cambio de registro en backend es la llamada del listener al Service (TASK-007).

## Existing Code Leverage

- app/Auth/Modules/TrustedDevice/Services/TwoFactorCodeVerifier.php: verifyTotp y consumeRecoveryCode ya existen; verifyCode los combina.
- app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php: assertReactivationCodeIsValid ya valida y consume dentro de la transacción; pasa a usar verifyCode.
- resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceReactivationDialog.tsx: ya contiene el toggle OTP/recuperación y RecoveryCodeField que pasan al componente compartido.
- app/Auth/Modules/TrustedDevice/Listeners/TrustedDeviceRemember.php y app/Auth/Models/TrustedDevice.php: findActiveMatch, findAnyMatchForFingerprint y pruneOlder se reutilizan; el Service ya tiene create(), recordEvent() e invalidateDashboardAfterCommit().
- tests/Pest.php: createUserWithTwoFactor(), createUserWithRecoveryCodes(), allowOtpReuse() y createTrustedDevice().

## Tasks

### TASK-001: TwoFactorCodeVerifier::verifyCode (TOTP o código de recuperación)

Agregar a TwoFactorCodeVerifier un método verifyCode(User, string) que decide por el formato: seis dígitos se verifican como TOTP (verifyTotp) y cualquier otra cosa se trata como código de recuperación y se consume (consumeRecoveryCode, que exige estar dentro de la transacción del llamador). Es la única regla de 'qué código es válido' donde se acepta un código de recuperación (reactivar y desactivar 2FA).

**Type:** feature
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude

**Acceptance Criteria:**

- [ ] Un TOTP vigente devuelve true; un código de recuperación válido devuelve true la primera vez y false la segunda (queda consumido).
- [ ] Caso de falla/edge: un código de seis dígitos nunca se busca entre los códigos de recuperación, un código con formato de recuperación nunca pasa por TOTP, y el código de recuperación de otro usuario o un texto vacío devuelven false sin consumir nada.
- [ ] Los logs y valores devueltos no incluyen el código ni el secreto (se conserva la prueba existente de logs).

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Services/TwoFactorCodeVerifier.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Services/TwoFactorCodeVerifierTest.php` (modificar)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Services/TwoFactorCodeVerifierTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** Extiende la clase creada en SOC-45; no requiere archivos nuevos.

### TASK-002: Reactivar usa verifyCode en lugar de su lógica propia

TrustedDeviceService::assertReactivationCodeIsValid deja de decidir el formato y de llamar a verifyTotp o consumeRecoveryCode por su cuenta y usa TwoFactorCodeVerifier::verifyCode (regla de no repetir código). El comportamiento no cambia: 2FA confirmado obligatorio, mensajes fijos, consumo de un solo uso dentro de la transacción.

**Type:** refactor
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude
**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] Las pruebas existentes de reactivación (TOTP, código de recuperación de un solo uso, código de otro usuario, 2FA desactivado, reversión ante un fallo posterior) pasan sin cambiar sus aserciones.
- [ ] Caso de falla/edge: un código inválido sigue lanzando ValidationException con el mensaje fijo sin consumir ni modificar nada.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php` (modificar)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceReactivateTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** Refactor sin cambios de contrato; las pruebas existentes son la red de seguridad, por eso no hay archivos de prueba en el alcance.

### TASK-003: Desactivar 2FA: aceptar código de recuperación

TwoFactorDisableRequest acepta además del TOTP un código de recuperación (formato de Fortify) y lo verifica con TwoFactorCodeVerifier::verifyCode. Desactivar 2FA borra los códigos, por lo que consumir el usado no deja estado inconsistente; se conserva la confirmación con contraseña y el mensaje de error fijo.

**Type:** feature
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude
**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] Desactivar 2FA con contraseña correcta y un código de recuperación válido desactiva 2FA y revoca los dispositivos (listener existente).
- [ ] Caso de falla/edge: un código de recuperación inválido o de otro usuario, o con contraseña incorrecta, no desactiva 2FA y devuelve el error de siempre sin revelar el código.
- [ ] Desactivar con TOTP sigue funcionando sin cambios.

**writeScope:**

- `app/User/Modules/TwoFactor/Requests/TwoFactorDisableRequest.php` (modificar)
- `app/User/Modules/TwoFactor/Tests/TwoFactorDisableTest.php` (modificar)

**validateCommand:** `vendor/bin/pest app/User/Modules/TwoFactor/Tests/TwoFactorDisableTest.php app/User --compact && vendor/bin/pint --dirty --format agent`

**Notas:** Reutiliza el verificador de TrustedDevice desde el módulo User (la dependencia User → Auth ya existe en TwoFactorController).

### TASK-004: Componente TwoFactorCodeField (OTP o código de recuperación) y diálogo de reactivación

Crear en modules/setting/shared/components el componente TwoFactorCodeField que muestra el campo de seis dígitos o el campo de código de recuperación (21 caracteres) con el enlace para alternar, limpia el valor y los errores al alternar y avisa que cada código de recuperación es de un solo uso. Es configurable por nombre y errores para que lo usen diálogos con campo 'otp_code' o 'code'. TrustedDeviceReactivationDialog pasa a usarlo y se elimina su toggle y su RecoveryCodeField propios (regla de no repetir código).

**Type:** feature
**Priority:** P2
**Effort:** M
**Agent:** react-vite-tailwind-engineer
**Review:** claude

**Acceptance Criteria:**

- [ ] El componente alterna entre el campo OTP y el campo de recuperación, limpia el valor al alternar y no envía el formulario sin un código.
- [ ] Caso de falla/edge: un error del servidor se muestra bajo el campo activo sin cerrar el diálogo y el campo se limpia tras el error; el diálogo de reactivación se comporta igual que antes.
- [ ] npm run types, lint y format pasan, sin variables de una letra y con hooks tipados explícitamente.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceReactivationDialog.tsx` (modificar)
- `resources/js/modules/setting/shared/components/TwoFactorCodeField.tsx` (crear)

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

**Notas:** Convención: los componentes compartidos entre sub-módulos de setting viven en modules/setting/shared/components.

### TASK-005: Diálogo de desactivar 2FA con la opción de código de recuperación

DisabledTwoFactorDialog reemplaza su bloque de OTP por TwoFactorCodeField (campo 'code') y muestra el error del servidor.

**Type:** feature
**Priority:** P2
**Effort:** S
**Agent:** react-vite-tailwind-engineer
**Review:** claude
**Depends on:** TASK-003, TASK-004

**Acceptance Criteria:**

- [ ] El diálogo permite alternar a un código de recuperación y lo envía como code junto con la contraseña.
- [ ] Caso de falla/edge: contraseña incorrecta o código inválido muestran el error del servidor sin cerrar el diálogo.
- [ ] npm run types, lint y format pasan.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/DisabledTwoFactorDialog.tsx` (modificar)

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-006: Recordar navegador en el login: reutilizar la fila de la misma huella

Bug: al iniciar sesión con OTP y marcar recordar este dispositivo tras haberlo revocado, TrustedDeviceRemember crea una fila nueva y deja la revocada, así que el usuario ve dos dispositivos del mismo navegador (la alta manual sí avisa AlreadyRevoked). Agregar TrustedDevice::findLatestForFingerprint (incluye revocadas y expiradas) y TrustedDeviceService::rememberAfterTwoFactorChallenge(User, DeviceDetector, Request): devuelve el token crudo; dentro de una transacción reutiliza la fila existente de esa huella (la restaura si estaba revocada, con token nuevo, expires_at y last_used_at nuevos, y evento Reactivated si estaba revocada o Renewed si estaba expirada o activa) o crea una nueva con evento Created; invalida el caché del dashboard al confirmar la transacción.

**Type:** bugfix
**Priority:** P1
**Effort:** M
**Agent:** laravel-senior-engineer
**Review:** claude
**Depends on:** TASK-002

**Acceptance Criteria:**

- [ ] Con un dispositivo revocado de la misma huella, recordar el navegador deja una sola fila activa (la misma restaurada, con token nuevo) y registra un evento Reactivated; con uno expirado deja una sola fila y registra Renewed.
- [ ] Caso de falla/edge: sin fila previa se crea una nueva con evento Created; con IP o User-Agent nulos no se reutiliza ninguna fila; el token anterior deja de ser válido y el nuevo valida el login.
- [ ] Si el registro del evento falla, la restauración se revierte (el dispositivo sigue revocado) y la caché del dashboard no se invalida.

**writeScope:**

- `app/Auth/Models/TrustedDevice.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php` (modificar)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php app/Auth/Modules/TrustedDevice/Tests/Queries app/Auth --compact && vendor/bin/pint --dirty --format agent`

**Notas:** findAnyMatchForFingerprint (usado por la alta manual) no cambia: devolvería AlreadyActive para una fila expirada. La fila reutilizada conserva created_at; el historial queda en los eventos. Depende de TASK-002 por el archivo del Service.

### TASK-007: TrustedDeviceRemember delega en el Service

El listener TrustedDeviceRemember deja de crear el dispositivo, podar y registrar el evento por su cuenta: si remember_device está marcado llama a rememberAfterTwoFactorChallenge y encola la cookie con el token devuelto. Se actualizan las pruebas del listener con los casos de huella repetida.

**Type:** bugfix
**Priority:** P1
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude
**Depends on:** TASK-006

**Acceptance Criteria:**

- [ ] Recordar el navegador tras revocarlo deja una sola fila de ese navegador activa y en la lista, y la cookie emitida valida el siguiente login sin OTP.
- [ ] Caso de falla/edge: sin remember_device no se crea ni modifica nada y no se emite cookie; recordar dos veces seguidas el mismo navegador no crea una segunda fila.
- [ ] El evento registrado es Created, Reactivated o Renewed según el estado previo de la fila.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Listeners/TrustedDeviceRemember.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceRememberTest.php` (modificar)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceRememberTest.php app/Auth --compact && vendor/bin/pint --dirty --format agent`

**Notas:** Reduce el listener a una llamada; las dependencias que dejen de usarse (DeviceDetector, TrustedDeviceEvent) se retiran.

### TASK-008: Actualizar CLAUDE.md con el código de recuperación y el recordar sin duplicados

Documentar: verifyCode (TOTP o recuperación de un solo uso) usado por reactivar y desactivar 2FA, y que alta y renovación siguen aceptando solo el TOTP (decisión B de SOC-45, por no gastar los 8 códigos); el componente TwoFactorCodeField; y que recordar el navegador en el login reutiliza la fila de la misma huella (Reactivated, Renewed o Created).

**Type:** docs
**Priority:** P3
**Effort:** S
**Agent:** general-purpose
**Review:** claude
**Depends on:** TASK-003, TASK-005, TASK-007

**Acceptance Criteria:**

- [ ] CLAUDE.md describe las reglas con los nombres reales de clases y archivos y deja explícito dónde se acepta y dónde no un código de recuperación.
- [ ] Ninguna afirmación de CLAUDE.md contradice el código.

**writeScope:**

- `CLAUDE.md` (modificar)

**validateCommand:** `grep -n 'verifyCode\|TwoFactorCodeField\|rememberAfterTwoFactorChallenge' CLAUDE.md`

### TASK-009: Gates de calidad y verificación en el navegador

Ejecutar rector-clear-cache, rector-dry, rector, Pint, PHPStan y las suites de app y tests (memory_limit 512M). Verificar en el navegador con el usuario del desarrollador: revocar el dispositivo y volver a iniciar sesión recordándolo debe dejar una sola fila; reactivar y desactivar 2FA deben aceptar un código de recuperación (usando una cuenta de prueba y sin consumir los códigos reales del usuario sin su permiso).

**Type:** verification
**Priority:** P3
**Effort:** M
**Agent:** general-purpose
**Review:** claude
**Depends on:** TASK-008

**Acceptance Criteria:**

- [ ] composer rector-clear-cache, rector-dry, phpstan y vendor/bin/pint --dirty --format agent terminan sin hallazgos y pasan app y tests con memory_limit de 512M.
- [ ] En el navegador, recordar un navegador revocado deja un único dispositivo activo, y los diálogos de reactivar y desactivar 2FA muestran la opción de código de recuperación.
- [ ] Caso de falla: un código de recuperación usado una vez es rechazado la segunda vez, y alta y renovación siguen sin ofrecer la opción.

**writeScope:**

- (sin archivos: tarea de verificación)

**validateCommand:** `composer rector-clear-cache && composer rector-dry && composer phpstan && php -d memory_limit=512M vendor/bin/pest app tests --compact && npm run types && npm run lint:check && npm run format:check`

**Notas:** Sin writeScope: tarea de verificación. Las filas duplicadas que ya existan en una base de desarrollo no se migran; se corrigen volviendo a sembrar.

## Failure Modes

- Cada desactivación o reactivación con código de recuperación consume uno de los 8 códigos: quien los agote debe regenerarlos desde la pantalla de TwoFactor (existente).
- Reutilizar la fila por huella conserva created_at: el dispositivo restaurado aparece con su fecha original (el historial real está en los eventos).
- Dos navegadores con la misma IP y user agent comparten huella y, por tanto, fila (comportamiento existente de la alta manual).
- TASK-006 modifica el mismo archivo del Service que TASK-002, por eso depende de él.

## Ship Cut

Si la ejecución se detiene a mitad de camino: TASK-006 y TASK-007 (corrección del duplicado) son independientes del código de recuperación y pueden entregarse solas, aunque TASK-006 requiere TASK-002 por el archivo compartido. TASK-001 a TASK-005 entregan el código de recuperación al desactivar 2FA y la limpieza de reactivar; TASK-008 y TASK-009 cierran documentación y calidad.

## Test Coverage Map

| Comportamiento | Prueba | Tarea |
| --- | --- | --- |
| verifyCode: TOTP, recuperación de un solo uso, formatos | app/Auth/Modules/TrustedDevice/Tests/Services/TwoFactorCodeVerifierTest.php | TASK-001 |
| Reactivar con verifyCode (pruebas existentes) | app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php + app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceReactivateTest.php | TASK-002 |
| Desactivar 2FA con código de recuperación | app/User/Modules/TwoFactor/Tests/TwoFactorDisableTest.php | TASK-003 |
| Interfaz: componente compartido y los dos diálogos | npm run types / lint:check / format:check + navegador | TASK-004, TASK-005, TASK-009 |
| Reutilizar la fila de la huella al recordar el navegador | app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php + app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceRememberTest.php | TASK-006, TASK-007 |
| Documentación sin contradicciones | grep en CLAUDE.md | TASK-008 |
| Gates de calidad y navegador | composer + pest + navegador | TASK-009 |

## Execution Summary

- Tareas: 9
- Ruta crítica: TASK-001 → TASK-002 → TASK-006 → TASK-007 → TASK-008 → TASK-009
- Capa 0: TASK-001, TASK-004
- Capa 1: TASK-002, TASK-003
- Capa 2: TASK-005, TASK-006
- Capa 3: TASK-007
- Capa 4: TASK-008
- Capa 5: TASK-009

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | TASK-001 |
| TASK-003 | TASK-001 |
| TASK-004 | — |
| TASK-005 | TASK-003, TASK-004 |
| TASK-006 | TASK-002 |
| TASK-007 | TASK-006 |
| TASK-008 | TASK-003, TASK-005, TASK-007 |
| TASK-009 | TASK-008 |
