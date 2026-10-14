# Plan: SOC-45: Endurecer el alta de dispositivos confiables y el bypass de OTP

## Overview

El plan cierra el bypass de OTP en capas: (1) un verificador único de 2FA/TOTP/códigos de recuperación; (2) el login solo omite OTP con 2FA confirmado; (3) al confirmar 2FA se revocan los dispositivos existentes (decisión A); (4) alta y renovación exigen un TOTP vigente (decisión B) y 2FA confirmado, verificado en el request y de nuevo dentro de la transacción con el usuario bloqueado; (5) la reactivación consume los códigos de recuperación una sola vez bajo bloqueo; (6) la UI explica cómo activar 2FA, pide el OTP y aclara que recordar el navegador es una comodidad temporal.

**Issue:** [SOC-45](https://linear.app/social-hub-ang/issue/SOC-45/feature-harden-trusted-device-enrollment-and-otp-bypass) — Feature: Harden trusted-device enrollment and OTP bypass
**Modo:** HOLD · **Estilo:** Paso a paso: se implementa una tarea y se pausa para revisión (regla .ai/rules/linear.md). · **Review por defecto:** claude

## Scope Challenge

Modo HOLD: se mantiene el alcance de la tarea y las decisiones A (revocar al confirmar) y B (TOTP en el mismo request) sin ampliar. Se descartó una migración de datos para registros históricos porque aún no hay datos en producción: los históricos quedan cubiertos por TASK-002 y TASK-003 (revisar antes de desplegar con datos reales). Se descartó vincular dispositivos a una 'generación' de 2FA (requiere columna, backfill y es más frágil) y también un chequeo created_at < two_factor_confirmed_at en el login, porque rompería la reactivación legítima de dispositivos revocados. Ya existen el Service, el listener de desactivación, la validación TOTP en TrustedDeviceReactivateRequest y los tests por flujo, que se reutilizan.

## Prerequisites

- Los tests actuales de app/Auth/Modules/TrustedDevice/Tests, Login/Tests y User/Modules/TwoFactor/Tests están en verde antes de empezar.
- Se leyeron .ai/rules/{app,backend,frontend,linear}.md: sin variables de una letra, docblocks con máximo 3 líneas de prosa, FluentRule en FormRequests, un comportamiento por test, pausa tras cada tarea.
- config/fortify.php mantiene confirm=true: hasEnabledTwoFactorAuthentication() exige secreto y two_factor_confirmed_at.
- phpunit.xml ya registra app/Auth/Modules/TrustedDevice/Tests, Login/Tests y la suite TwoFactor; las subcarpetas nuevas quedan cubiertas sin registro adicional.

## Non-Goals

- Rediseñar por completo las interfaces de TwoFactor o TrustedDevice.
- Cambiar los factores de Fortify o sustituir el sistema TOTP.
- Rate limiting nuevo para los OTP de alta/renovación/reactivación.
- Migrar la cookie a otro mecanismo o usar IP/User-Agent como credencial (siguen siendo solo metadatos).
- Migración de datos para registros históricos (descartada: no hay datos en producción; TASK-004 eliminada y su número no se reutiliza).

## Contracts

- 2FA activo = User::hasEnabledTwoFactorAuthentication() con confirm=true: two_factor_secret no nulo y two_factor_confirmed_at no nulo. Un secreto pendiente nunca cuenta.
- Alta y renovación: TOTP vigente en otp_code; sin códigos de recuperación. Reactivación: TOTP o código de recuperación, este último consumido una sola vez dentro de la transacción con el usuario bloqueado.
- Las reglas se aplican dos veces: en el FormRequest (rechazo temprano, error two_factor/otp_code) y en el Service dentro de la transacción con lockForUpdate sobre el usuario (cierre de la carrera con la desactivación de 2FA). Ningún camino crea filas, emite cookies, extiende expiraciones ni invalida cachés antes de pasar ambas.
- TrustedDeviceOperationResult::TwoFactorRequired es no exitoso: el controlador no encola cookie con resultados no exitosos (lógica existente en store).
- Los errores de validación nunca incluyen el código enviado, el secreto, los códigos de recuperación ni tokens; los logs del verificador tampoco.
- Capability providers: persistencia y bloqueo vía Eloquent/DB::transaction (existentes); TOTP vía Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider (existente); consumo de códigos vía User::replaceRecoveryCode de Fortify (existente); eventos TwoFactorAuthenticationConfirmed/Disabled de Fortify.
- Wiring: TwoFactorCodeVerifier, TrustedDeviceRenewRequest, TwoFactorStatus se autocargan por PSR-4; el único registro nuevo es el listener de TwoFactorAuthenticationConfirmed en AuthEventServiceProvider (TASK-003).
- Las rutas y nombres de ruta de user.trusted-devices.* no cambian; cambia el contrato de sus payloads (store y renew ahora requieren otp_code).

## Existing Code Leverage

- app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php: register, renew y reactivate ya tienen transacción, eventos y invalidación de caché; se les agrega la verificación.
- app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceReactivateRequest.php: ya valida TOTP y recuperación; su lógica se traslada a TwoFactorCodeVerifier.
- app/Auth/Modules/TrustedDevice/Listeners/TrustedDeviceInvalidate.php: revoca todos los dispositivos al desactivar 2FA; se reutiliza para la confirmación.
- app/Auth/Models/TrustedDevice.php: validateAndTouch ya compara hash SHA-256, deleted_at y expires_at.
- app/Auth/Modules/TrustedDevice/Concerns/MintsTrustedDeviceToken.php: token aleatorio de 64 caracteres, hash y cookie httpOnly/secure/sameSite lax; no se cambia.
- app/Auth/Modules/TrustedDevice/Enums/TrustedDeviceOperationResult.php: se agrega el caso TwoFactorRequired.
- tests/Pest.php: helpers createUserWithTwoFactor, validOtpFor y createTrustedDevice.
- Fortify: User::replaceRecoveryCode y RecoveryCode::generate (formato xxxxxxxxxx-xxxxxxxxxx).
- Frontend: TrustedDeviceAddDialog, TrustedDeviceRenewTrustDialog y TrustedDeviceReactivationDialog en components/dialog/actions; TwoFactorDevices.tsx reutiliza el diálogo de alta.

## Tasks

### TASK-001: Crear TwoFactorCodeVerifier (TOTP y código de recuperación de un solo uso)

Crear un servicio final readonly en el módulo TrustedDevice con tres operaciones: hasConfirmedTwoFactor(User) (delega en hasEnabledTwoFactorAuthentication(), que con confirm=true exige secreto y two_factor_confirmed_at), verifyTotp(User, string) (descifra el secreto y usa TwoFactorAuthenticationProvider; registra con Log::warning sin incluir el código ni el secreto si el descifrado falla) y consumeRecoveryCode(User, string) (debe llamarse dentro de una DB::transaction del llamador: relee al usuario con lockForUpdate, compara con hash_equals contra los códigos descifrados y consume el aceptado con User::replaceRecoveryCode (que lo elimina), de modo que el mismo código no sirva dos veces). Es la única fuente de verdad de verificación para alta, renovación y reactivación.

**Type:** feature
**Priority:** P0
**Effort:** M
**Agent:** laravel-senior-engineer
**Review:** claude

**Acceptance Criteria:**

- [ ] verifyTotp devuelve true con el TOTP vigente de createUserWithTwoFactor() y false con un código inválido o con un usuario sin secreto; hasConfirmedTwoFactor devuelve false con secreto pendiente (two_factor_confirmed_at nulo) y con 2FA desactivado.
- [ ] consumeRecoveryCode devuelve true la primera vez y false la segunda con el mismo código (el código se elimina de two_factor_recovery_codes, porque User::replaceRecoveryCode del proyecto lo quita); un código de recuperación de otro usuario devuelve false.
- [ ] Caso de falla: ni los valores devueltos ni los logs contienen el código, el secreto ni los códigos de recuperación (Log spy), y un secreto cifrado corrupto devuelve false en lugar de lanzar.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Services/TwoFactorCodeVerifier.php` (crear)
- `app/Auth/Modules/TrustedDevice/Tests/Services/TwoFactorCodeVerifierTest.php` (crear)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Services/TwoFactorCodeVerifierTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** Archivos nuevos autocargados por PSR-4; el servicio se resuelve por inyección sin registro. app/Auth/Modules/TrustedDevice/Tests ya está registrado en phpunit.xml (suite TrustedDevice).

### TASK-002: Endurecer TrustedDevice::validateAndTouch con 2FA confirmado

validateAndTouch pasa a devolver false (sin tocar last_used_at) cuando el usuario no cumple hasEnabledTwoFactorAuthentication(), como defensa en profundidad del chequeo que ya hace LoginSessionController::store. La cookie sigue siendo la única credencial: se compara el hash SHA-256 y se exigen fila no eliminada y expires_at futuro; IP y User-Agent no intervienen. Se agrega un test de login de extremo a extremo para el bypass.

**Type:** bugfix
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude

**Acceptance Criteria:**

- [ ] Con 2FA confirmado, una cookie válida, no revocada y no expirada omite el OTP en POST /login (redirige a home y actualiza last_used_at).
- [ ] Caso de falla: con secreto pendiente o con 2FA desactivado validateAndTouch devuelve false y no modifica last_used_at; una cookie expirada, soft-deleted o de otro usuario tampoco omite el OTP (el login redirige a two-factor.login).
- [ ] Cambiar el User-Agent o la IP de la solicitud no invalida una cookie válida ni valida una inválida (no son credencial).

**writeScope:**

- `app/Auth/Models/TrustedDevice.php` (modificar)
- `app/Auth/Modules/Login/Tests/TrustedDeviceOtpBypassTest.php` (crear)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/Login/Tests/TrustedDeviceOtpBypassTest.php app/Auth/Modules/Login/Tests/AuthenticationTest.php app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceRememberTest.php && vendor/bin/pint --dirty --format agent`

### TASK-003: Revocar dispositivos confiables al confirmar 2FA (decisión A)

Generalizar el listener TrustedDeviceInvalidate para que acepte el evento base TwoFactorAuthenticationEvent de Fortify y registrarlo también para TwoFactorAuthenticationConfirmed en AuthEventServiceProvider. Al confirmar 2FA se revocan (soft delete, evento RevokedAll por dispositivo) todos los dispositivos del usuario y se invalida el caché del dashboard, de modo que ninguna cookie emitida antes de la confirmación pueda omitir el OTP. La revocación al desactivar 2FA se mantiene.

**Type:** feature
**Priority:** P0
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude

**Acceptance Criteria:**

- [ ] Al confirmar 2FA (evento TwoFactorAuthenticationConfirmed) todos los dispositivos activos del usuario quedan con deleted_at, con un TrustedDeviceEvent RevokedAll por dispositivo, y el caché del dashboard queda invalidado.
- [ ] Caso de falla: tras confirmar 2FA, una cookie de un dispositivo creado antes de la confirmación ya no omite el OTP en POST /login; los dispositivos de otros usuarios no se tocan.
- [ ] Desactivar 2FA (TwoFactorAuthenticationDisabled) sigue revocando todos los dispositivos como antes (tests existentes sin cambios en sus aserciones).

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Listeners/TrustedDeviceInvalidate.php` (modificar)
- `app/Auth/Providers/AuthEventServiceProvider.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php` (modificar)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** El registro del listener vive en AuthEventServiceProvider (ya cargado por AuthServiceProvider); no hay archivos nuevos.

### TASK-005: Exigir 2FA confirmado dentro de register() y renew() del Service

Agregar el caso TwoFactorRequired a TrustedDeviceOperationResult (no exitoso, mensaje comprensible que indica activar y confirmar 2FA). TrustedDeviceService::register y ::renew comprueban primero (fuera de la transacción, sin efectos) y de nuevo dentro de la transacción, releyendo al usuario con lockForUpdate, que 2FA siga confirmado; si no, retornan TwoFactorRequired antes de crear filas, extender expires_at, registrar eventos o invalidar el caché. renew pasa de void a devolver TrustedDeviceOperationResult (Renewed|TwoFactorRequired). Cierra la carrera con la desactivación de 2FA.

**Type:** feature
**Priority:** P1
**Effort:** M
**Agent:** laravel-senior-engineer
**Review:** claude
**Depends on:** TASK-001

**Acceptance Criteria:**

- [ ] register con 2FA desactivado o con secreto pendiente devuelve TwoFactorRequired sin crear fila, evento ni invalidar el caché; con 2FA confirmado devuelve Created como antes.
- [ ] renew con 2FA confirmado devuelve Renewed y extiende expires_at; con 2FA no confirmado devuelve TwoFactorRequired y deja expires_at y los eventos intactos.
- [ ] Caso de carrera: si two_factor_confirmed_at/secreto se anulan en la base entre la carga del modelo y el bloqueo (modelo obsoleto en memoria), register y renew igualmente rechazan y no dejan un dispositivo activo.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Enums/TrustedDeviceOperationResult.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php` (modificar)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** El controlador actual ignora el valor de renew(), por lo que el cambio de tipo es compatible; TASK-006 y TASK-007 consumen el nuevo resultado.

### TASK-006: Alta manual: exigir OTP vigente y rechazar sin 2FA confirmado en el request

TrustedDeviceStoreRequest agrega otp_code (6 dígitos) y en withValidator: si el usuario no tiene 2FA confirmado agrega el error two_factor con el mensaje de activar y confirmar 2FA (sin evaluar el OTP); si lo tiene, verifica el TOTP con TwoFactorCodeVerifier y agrega el error otp_code ('El código de verificación es incorrecto.') si falla. Solo TOTP: el alta no acepta códigos de recuperación. El controlador sigue encolando la cookie únicamente si el resultado es exitoso, por lo que TwoFactorRequired nunca emite cookie.

**Type:** feature
**Priority:** P1
**Effort:** M
**Agent:** laravel-senior-engineer
**Review:** claude
**Depends on:** TASK-001, TASK-005

**Acceptance Criteria:**

- [ ] Con 2FA desactivado o con secreto pendiente, POST user/trusted-devices responde con error de validación two_factor, no crea el dispositivo ni el evento y la respuesta no contiene la cookie trusted_device.
- [ ] Con 2FA confirmado y un TOTP vigente se crea el dispositivo, se registra el evento Created y se emite la cookie; sin otp_code, con TOTP inválido o con un código de recuperación se rechaza sin crear nada.
- [ ] Los mensajes de error no contienen el código enviado, el secreto ni tokens.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceStoreRequest.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceStoreTest.php` (modificar)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceStoreTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** Cambio de contrato: la UI actual no envía otp_code; TASK-012 debe entregarse junto con esta tarea (ver Ship Cut).

### TASK-007: Renovación: OTP vigente con TrustedDeviceRenewRequest y trait compartido

Crear TrustedDeviceRenewRequest (HasFluentRules) y el trait Concerns/RequiresTwoFactorOtp, que concentra la regla otp_code y la verificación (error two_factor sin 2FA confirmado, error otp_code con TOTP inválido); TrustedDeviceStoreRequest pasa a usar el mismo trait en lugar de duplicar la lógica. TrustedDeviceController::renew usa la nueva request y devuelve el payload del resultado del Service. La autorización por #[Authorize('update','trustedDevice')] sigue ejecutándose antes de validar, así que un dispositivo ajeno responde 403 sin evaluar el OTP. Se mueve el helper allowOtpReuse a tests/Pest.php y se corrigen las pruebas de TrustedDeviceDashboardCacheTest que hacían alta y renovación sin OTP. Alcance ampliado a 8 archivos a petición del usuario (arreglo de DashboardCacheTest) y por la extracción del trait y el helper compartidos.

**Type:** feature
**Priority:** P1
**Effort:** L
**Agent:** laravel-senior-engineer
**Review:** claude
**Depends on:** TASK-005, TASK-006

**Acceptance Criteria:**

- [ ] Con 2FA confirmado y TOTP vigente la renovación extiende expires_at y registra el evento Renewed.
- [ ] Caso de falla: sin otp_code, con TOTP inválido o con código en formato de recuperación responde con error otp_code y no modifica expires_at ni registra eventos; con 2FA desactivado/pendiente se rechaza con el error two_factor.
- [ ] Un dispositivo de otro usuario responde 403 antes de validar el OTP; el alta (TrustedDeviceStoreTest) sigue pasando con el trait compartido y las pruebas de TrustedDeviceDashboardCacheTest pasan enviando OTP.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceStoreRequest.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceRenewTest.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceStoreTest.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php` (modificar)
- `tests/Pest.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceRenewRequest.php` (crear)
- `app/Auth/Modules/TrustedDevice/Concerns/RequiresTwoFactorOtp.php` (crear)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceRenewTest.php app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceStoreTest.php app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** Los archivos nuevos se autocargan por PSR-4; la ruta user.trusted-devices.renew no cambia. Excepción deliberada a la regla de 3 archivos por tarea. allowOtpReuse vive en tests/Pest.php porque Fortify rechaza reutilizar un TOTP dentro de su ventana y varias pruebas lo necesitan.

### TASK-008: Reactivación: verificar y consumir el código dentro del Service

TrustedDeviceService::reactivate verifica dentro de su DB::transaction (con el usuario bloqueado) que 2FA esté confirmado y valida el otp_code leído del Request: seis dígitos se tratan como TOTP y el formato de Fortify (10 caracteres, guion, 10 caracteres) como código de recuperación, que se consume con TwoFactorCodeVerifier::consumeRecoveryCode. Si falla lanza ValidationException (otp_code con el mensaje existente, o two_factor sin 2FA confirmado) sin modificar nada. La firma pasa a reactivate(User, TrustedDevice, string $otpCode, Request) y el controlador le pasa el código; el helper createUserWithRecoveryCodes se mueve a tests/Pest.php. TrustedDeviceReactivateRequest queda solo con reglas de formato (acepta ambos formatos) y sin verificación propia.

**Type:** bugfix
**Priority:** P1
**Effort:** M
**Agent:** laravel-senior-engineer
**Review:** claude
**Depends on:** TASK-001, TASK-005

**Acceptance Criteria:**

- [ ] Un TOTP vigente reactiva el dispositivo (deleted_at nulo, token nuevo, evento Reactivated); un código de recuperación con el formato real de Fortify también, y queda consumido en two_factor_recovery_codes.
- [ ] Caso de falla: reutilizar el mismo código de recuperación para reactivar otro dispositivo falla y deja ese dispositivo revocado; un código de otro usuario, un OTP inválido y un usuario con 2FA desactivado no reactivan nada.
- [ ] El test existente que usaba '111111' como código de recuperación se actualiza al formato real y los errores no revelan códigos ni secretos.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Requests/TrustedDeviceReactivateRequest.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceReactivateTest.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php` (modificar)
- `tests/Pest.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Services/TwoFactorCodeVerifierTest.php` (modificar)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceReactivateTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** Se evita verificar dos veces el mismo TOTP: Fortify impide reutilizar un código ya aceptado, por eso la verificación vive solo en el Service. Alcance ampliado a 7 archivos: el Service ya no lee el código del Request, lo que obliga a tocar el controlador y la prueba existente del Service.

### TASK-009: Pruebas de concurrencia y reuso en el Service

Ampliar TrustedDeviceServiceTest con los escenarios de carrera y reuso de reactivate: doble uso secuencial del mismo código de recuperación con modelos obsoletos en memoria, desactivación de 2FA justo antes de reactivar y fallo atómico (ningún cambio parcial ante un código inválido).

**Type:** test
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude
**Depends on:** TASK-008

**Acceptance Criteria:**

- [ ] Dos reactivate consecutivos con el mismo código de recuperación y dos dispositivos revocados: el primero tiene éxito y el segundo lanza ValidationException sin modificar el segundo dispositivo.
- [ ] Caso de falla: con two_factor_secret anulado en la base y el modelo del usuario obsoleto en memoria, reactivate rechaza y no restaura el dispositivo.
- [ ] Un código inválido no consume ningún código de recuperación ni cambia token_hash/expires_at.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php` (modificar)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** La concurrencia real no se puede ejecutar en Pest; se simula con modelos obsoletos y llamadas secuenciales sobre el mismo estado bloqueado.

### TASK-010: Enum TwoFactorStatus y prop twoFactorStatus en TrustedDeviceIndexController

Crear el enum TwoFactorStatus (disabled, pending, confirmed) con fromUser(User) en el módulo User/TwoFactor (disabled sin secreto, pending con secreto y sin two_factor_confirmed_at, confirmed con ambos) y exponerlo como prop twoFactorStatus en la vista /setting/trusted-devices.

**Type:** feature
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude

**Acceptance Criteria:**

- [ ] La prop twoFactorStatus vale 'disabled', 'pending' o 'confirmed' según el usuario (secreto nulo; secreto con two_factor_confirmed_at nulo; ambos presentes).
- [ ] Caso de falla/edge: un usuario con two_factor_confirmed_at pero secreto nulo se reporta como 'disabled' y nunca como 'confirmed'.
- [ ] Las demás props de la vista (trustedDevices, stats, filtros) no cambian.

**writeScope:**

- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceIndexController.php` (modificar)
- `app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceIndexTest.php` (modificar)
- `app/User/Modules/TwoFactor/Enums/TwoFactorStatus.php` (crear)

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceIndexTest.php && vendor/bin/pint --dirty --format agent`

**Notas:** El enum se autocarga por PSR-4; sigue las convenciones de enums del proyecto (backed string).

### TASK-011: Exponer twoFactorStatus en la vista de TwoFactor

TwoFactorController::index agrega la prop twoFactorStatus (mismo enum) a las props existentes, porque TwoFactorDevices también monta el diálogo de alta.

**Type:** feature
**Priority:** P2
**Effort:** S
**Agent:** laravel-senior-engineer
**Review:** claude
**Depends on:** TASK-010

**Acceptance Criteria:**

- [ ] GET de la vista de TwoFactor incluye twoFactorStatus con los tres valores correctos.
- [ ] Caso de falla/edge: con canManageTwoFactor desactivado la prop no se envía y la página no falla.

**writeScope:**

- `app/User/Modules/TwoFactor/Controllers/TwoFactorController.php` (modificar)
- `app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php` (modificar)

**validateCommand:** `vendor/bin/pest app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php && vendor/bin/pint --dirty --format agent`

### TASK-012: Diálogo de alta: aviso de 2FA requerido, campo OTP y copy de comodidad

TrustedDeviceAddDialog lee twoFactorStatus de las props de la página (la usan tanto /setting/trusted-devices como TwoFactorDevices). Si no es 'confirmed' muestra un Alert distinto para desactivado y pendiente con un botón al flujo de TwoFactor (ruta de TwoFactorController::index vía Wayfinder) y no permite enviar; si es 'confirmed' agrega el campo otp_code y muestra errors.otp_code/errors.two_factor. Incluye el texto: recordar el navegador omite el OTP por un periodo limitado y no es un segundo factor independiente. Agrega el tipo TwoFactorStatus al archivo de tipos.

**Type:** feature
**Priority:** P2
**Effort:** M
**Agent:** react-vite-tailwind-engineer
**Review:** claude
**Depends on:** TASK-010

**Acceptance Criteria:**

- [ ] Con twoFactorStatus 'disabled' o 'pending' el diálogo muestra el aviso correspondiente con enlace a TwoFactor y el botón de enviar no está disponible; con 'confirmed' aparece el campo OTP y se envía otp_code.
- [ ] Caso de falla: un error del servidor en otp_code/two_factor se muestra en el campo sin cerrar el diálogo; el texto de comodidad no sugiere que sea un segundo factor.
- [ ] npm run types, lint y format pasan sin variables de una letra ni imports fuera de las rutas @/shared/wayfinder.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAddDialog.tsx` (modificar)
- `resources/js/modules/setting/modules/trustedDevice/types/trustedDevice.ts` (modificar)

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

**Notas:** No requiere cambios en TrustedDevice.tsx ni en TwoFactorDevices.tsx porque el diálogo lee la prop vía usePage. Cumple .ai/rules/frontend.md (hooks tipados explícitamente, nombres semánticos).

### TASK-013: Diálogo de renovación: campo OTP y errores

TrustedDeviceRenewTrustDialog agrega el campo otp_code (TOTP de 6 dígitos), envía el valor con useForm, muestra errors.otp_code/errors.two_factor y el texto de comodidad temporal.

**Type:** feature
**Priority:** P2
**Effort:** S
**Agent:** react-vite-tailwind-engineer
**Review:** claude
**Depends on:** TASK-007

**Acceptance Criteria:**

- [ ] El formulario envía otp_code a la ruta renew y no cierra el diálogo si el servidor responde con error.
- [ ] Caso de falla: sin código o con 2FA no confirmado se muestran los errores del servidor junto al campo.
- [ ] npm run types, lint y format pasan.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRenewTrustDialog.tsx` (modificar)

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-014: Diálogo de reactivación: aceptar código de recuperación y aclarar el copy

TrustedDeviceReactivationDialog acepta TOTP (6 dígitos) o código de recuperación (formato de Fortify), ajusta validación/maxLength/ayuda del campo y avisa que cada código de recuperación solo se puede usar una vez; mantiene el texto de comodidad temporal.

**Type:** feature
**Priority:** P2
**Effort:** S
**Agent:** react-vite-tailwind-engineer
**Review:** claude
**Depends on:** TASK-008

**Acceptance Criteria:**

- [ ] El campo acepta un TOTP de 6 dígitos y un código de recuperación de 21 caracteres con guion, y los envía como otp_code.
- [ ] Caso de falla: el error del servidor se muestra sin cerrar el diálogo.
- [ ] npm run types, lint y format pasan.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceReactivationDialog.tsx` (modificar)

**validateCommand:** `npm run types && npm run lint:check && npm run format:check`

### TASK-015: Actualizar CLAUDE.md con el endurecimiento de dispositivos confiables

Documentar en la sección Auth/TrustedDevice y en la de AccountSettings/TwoFactor de CLAUDE.md: definición de 2FA activo, revocación al confirmar, OTP en alta/renovación, código de recuperación de un solo uso, TwoFactorCodeVerifier, TwoFactorStatus y el nuevo caso TwoFactorRequired. Regla del proyecto: la doc se actualiza en el mismo cambio.

**Type:** docs
**Priority:** P3
**Effort:** S
**Agent:** general-purpose
**Review:** claude
**Depends on:** TASK-003, TASK-006, TASK-007, TASK-008, TASK-011, TASK-012

**Acceptance Criteria:**

- [ ] CLAUDE.md describe las nuevas reglas (2FA confirmado, reautenticación con TOTP, consumo único, revocación al confirmar) con los nombres reales de clases y archivos.
- [ ] Ninguna afirmación de CLAUDE.md contradice el código (rutas, enums y clases mencionadas existen).

**writeScope:**

- `CLAUDE.md` (modificar)

**validateCommand:** `grep -n 'TwoFactorCodeVerifier\|TwoFactorStatus\|TwoFactorRequired' CLAUDE.md`

### TASK-016: Gates de calidad, verificación en navegador y comentario en Linear

Ejecutar los gates completos: composer rector-clear-cache, rector-dry, rector, Pint, PHPStan, las suites TrustedDevice, Login, TwoFactor y Password, y types/lint/format del frontend. Verificar en el navegador los estados desactivado, pendiente y confirmado en el alta, la renovación y la reactivación. Dejar un comentario resumen en SOC-45 (Definition of Done). Sin cambios de código salvo correcciones que surjan de los gates.

**Type:** verification
**Priority:** P3
**Effort:** M
**Agent:** general-purpose
**Review:** claude
**Depends on:** TASK-002, TASK-009, TASK-013, TASK-014, TASK-015

**Acceptance Criteria:**

- [ ] composer rector-clear-cache, rector-dry, phpstan y vendor/bin/pint --dirty --format agent terminan sin hallazgos; las suites TrustedDevice, Login, TwoFactor y Password pasan.
- [ ] En el navegador: con 2FA desactivado y con secreto pendiente el alta muestra el aviso con enlace a TwoFactor; confirmado, el alta y la renovación piden OTP y la reactivación acepta TOTP o código de recuperación.
- [ ] Caso de falla: un código de recuperación usado una vez es rechazado al intentar reactivar otro dispositivo.

**writeScope:**

- (sin archivos: tarea de verificación)

**validateCommand:** `composer rector-clear-cache && composer rector-dry && composer phpstan && php artisan test --compact app/Auth app/User && npm run types && npm run lint:check && npm run format:check`

**Notas:** No tiene writeScope: es una tarea de verificación. Pendiente del usuario la ejecución de la suite completa (regla pest/core).

## Failure Modes

- Entregar TASK-006/TASK-007 sin TASK-012/TASK-013 rompe el alta y la renovación en la UI (no enviarían otp_code): se deben desplegar juntas.
- Un TOTP aceptado no se puede verificar dos veces (Fortify evita su reuso): la reactivación verifica solo en el Service, no también en el request (TASK-008).
- El consumo de códigos de recuperación con User::replaceRecoveryCode reemplaza el código en el JSON cifrado; si el JSON fuera inválido, el verificador devuelve false y registra sin datos sensibles.
- La concurrencia real no se puede probar en Pest; se simula con modelos obsoletos y se confía en lockForUpdate sobre el usuario (TASK-009).
- Sin migración de datos: si se despliega con registros previos a 2FA confirmado en la base, esos dispositivos solo se revocan cuando el usuario confirme 2FA de nuevo; revisar antes de desplegar con datos reales.
- Los usuarios con dispositivos confiables tendrán que volver a registrarlos tras confirmar 2FA; es el comportamiento buscado.

## Ship Cut

Si la ejecución se detiene a mitad de camino: TASK-001, TASK-002, TASK-003, TASK-005 y TASK-008 cierran el bypass de OTP y el reuso de códigos sin cambiar la UI (la reactivación sigue aceptando el mismo campo). Las rutas de alta y renovación (TASK-006, TASK-007) cambian su contrato, así que solo se entregan junto con TASK-010 a TASK-013. TASK-009, TASK-014 (copy), TASK-015 y TASK-016 pueden ir después, pero TASK-016 es necesaria para cerrar la tarea.

## Test Coverage Map

| Comportamiento | Prueba | Tarea |
| --- | --- | --- |
| Verificador (TOTP, estado 2FA, un solo uso) | app/Auth/Modules/TrustedDevice/Tests/Services/TwoFactorCodeVerifierTest.php | TASK-001 |
| Login: cookie válida/expirada/revocada/ajena/2FA pendiente | app/Auth/Modules/Login/Tests/TrustedDeviceOtpBypassTest.php | TASK-002 |
| Revocación al confirmar y al desactivar 2FA | app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php | TASK-003 |
| register/renew con 2FA no confirmado y carrera | app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php | TASK-005 |
| Alta HTTP: sin 2FA, OTP inválido, éxito | app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceStoreTest.php | TASK-006 |
| Renovación HTTP: reautenticación | app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceRenewTest.php | TASK-007 |
| Reactivación HTTP: TOTP, recuperación, otro usuario | app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceReactivateTest.php | TASK-008 |
| Reuso/concurrencia de códigos de recuperación | app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php | TASK-009 |
| Prop twoFactorStatus | app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceIndexTest.php + app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php | TASK-010, TASK-011 |
| UI (types/lint/format + navegador) | npm run types / lint:check / format:check + verificación manual | TASK-012 a TASK-014, TASK-016 |

## Execution Summary

- Tareas: 15
- Ruta crítica: TASK-001 → TASK-005 → TASK-006 → TASK-007 → TASK-013 → TASK-016
- Capa 0: TASK-001, TASK-002, TASK-003, TASK-010
- Capa 1: TASK-005, TASK-011, TASK-012
- Capa 2: TASK-006, TASK-008
- Capa 3: TASK-007, TASK-009, TASK-014
- Capa 4: TASK-013, TASK-015
- Capa 5: TASK-016

## Task Dependencies

| Tarea | Depende de |
| --- | --- |
| TASK-001 | — |
| TASK-002 | — |
| TASK-003 | — |
| TASK-005 | TASK-001 |
| TASK-006 | TASK-001, TASK-005 |
| TASK-007 | TASK-005, TASK-006 |
| TASK-008 | TASK-001, TASK-005 |
| TASK-009 | TASK-008 |
| TASK-010 | — |
| TASK-011 | TASK-010 |
| TASK-012 | TASK-010 |
| TASK-013 | TASK-007 |
| TASK-014 | TASK-008 |
| TASK-015 | TASK-003, TASK-006, TASK-007, TASK-008, TASK-011, TASK-012 |
| TASK-016 | TASK-002, TASK-009, TASK-013, TASK-014, TASK-015 |
