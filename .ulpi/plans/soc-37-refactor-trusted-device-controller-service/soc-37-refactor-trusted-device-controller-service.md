# Plan: SOC-37: Mover la lógica de negocio de TrustedDeviceController al TrustedDeviceService

## Overview

TrustedDeviceController (359 líneas) mezcla HTTP con lógica de negocio: cada método repite guard de ownership, transacción, TrustedDeviceEvent::record e invalidación del TrustedDeviceDashboardCache. El plan amplía TrustedDeviceService con un método por caso de uso, unifica revokeAll con PasswordTrustedDeviceRevoker, devuelve un enum de resultado en store y, como paso final opcional, mueve la ownership a una Policy. El controlador queda solo con responsabilidades HTTP (~120 líneas). Es un refactor de legibilidad: rutas, mensajes flash, eventos de auditoría e invalidación de caché se mantienen idénticos.

**Issue:** [SOC-37](https://linear.app/social-hub-ang/issue/SOC-37/refactor-move-trusteddevicecontroller-business-logic-into) — Refactor: Move TrustedDeviceController business logic into TrustedDeviceService
**Modo:** HOLD · **Estilo:** Paso a paso: se implementa una tarea y se pausa para revisión (regla .ai/rules/linear.md). · **Review por defecto:** claude,codex

## Scope Challenge

Modo HOLD: se mantienen los 5 pasos acordados (Service, revokeAll unificado, reactivate, store con enum, Policy) sin recortar ni ampliar. Ya existen TrustedDeviceService (solo create), TrustedDeviceDashboardCache y PasswordTrustedDeviceRevoker, y hay tests por flujo que sirven de red de seguridad. Se descartó un Observer de modelo (Renamed/Renewed son ambos updated y ->delete() masivo no dispara eventos). La integración de todo el issue es opcional; el corte de envío está tras TASK-006.

## Prerequisites

- Los tests actuales de app/Auth/Modules/TrustedDevice/Tests y app/Auth/Modules/Password/Tests están en verde antes de empezar.
- Se leyeron .ai/rules/{app,backend,linear}.md; aplican: sin variables de una letra, docblocks con máximo 3 líneas de prosa, Pint/Rector/PHPStan como gates.
- phpunit.xml registra el directorio completo app/Auth/Modules/TrustedDevice/Tests y tests/Pest.php lo enlaza con TestCase; las subcarpetas nuevas de tests (Services/, Policies/) quedan cubiertas sin registro adicional.

## Non-Goals

- Observer de modelo sobre TrustedDevice.
- Cambios de frontend/UI, rutas o mensajes flash.
- Tocar PasswordTrustedDeviceRevoker más allá de delegar en el Service (TASK-003).
- TrustedDeviceTokenIssuer y que TrustedDeviceEvent::record() reciba ip/userAgent en vez del Request (ítems de EXPANSION, fuera de HOLD).
- Corregir el comportamiento de la ruta de carrera en store (ver Failure Modes); se preserva y se deja como follow-up.

## Contracts

- Rutas, nombres de ruta, FormRequests y mensajes flash de TrustedDeviceController permanecen idénticos (routes en app/Auth/routes/trustedDevice.php).
- Cada método del Service que cambia estado: ejecuta la transacción, graba exactamente el mismo TrustedDeviceEvent (Renamed, Renewed, Revoked, RevokedAll, Reactivated, Created) y programa la invalidación del TrustedDeviceDashboardCache con DB::afterCommit.
- La invalidación del caché solo ocurre cuando hubo cambio real (store: solo si wasRecentlyCreated; revokeAll: solo si había dispositivos), igual que hoy.
- Service::reactivate devuelve el token crudo; el controlador es el único que encola la cookie (queueTrustedDeviceCookie) tras la transacción.
- La lectura de config module.auth.trusted_devices.cookie_lifetime_minutes para renew/reactivate vive solo en el Service.
- Los guards 403 (ownership), 404 (reactivate/forceDestroy sobre dispositivo no revocado) y 401 se conservan con el mismo código HTTP; la Policy (TASK-007) solo reemplaza el 403.
- Capability provider de persistencia/eventos/caché: TrustedDevice (Eloquent), TrustedDeviceEvent::record() y TrustedDeviceDashboardCache::invalidate(), todos existentes.
- Wiring: el enum TrustedDeviceRegistrationResult (TASK-005) y la Policy (TASK-007) se autocargan por PSR-4; la Policy se registra con Gate::policy en AuthServiceProvider::boot (TASK-007).

## Existing Code Leverage

- app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php: solo tiene create(); se amplía y se reutiliza InfersDeviceMetadata.
- app/Auth/Modules/TrustedDevice/Services/TrustedDeviceDashboardCache.php: invalidate(User) ya existe; se inyecta en el Service.
- app/Auth/Modules/Password/Services/PasswordTrustedDeviceRevoker.php: contiene la implementación correcta de revokeAll (lockForUpdate + afterCommit) que se traslada al Service.
- app/Auth/Modules/TrustedDevice/Concerns/MintsTrustedDeviceToken.php: mintToken()/queueTrustedDeviceCookie() se siguen usando (el Service usa el trait para reactivate).
- app/Auth/Models/TrustedDevice.php: findActiveMatch, findAnyMatchForFingerprint y pruneOlder se reutilizan sin cambios.
- app/Auth/Modules/TrustedDevice/Tests/{Crud,Lifecycle,Queries}: red de seguridad existente para update, destroy, forceDestroy, destroyAll, store, reactivate, renew y caché; tests/Pest.php aporta createUser() y createTrustedDevice().

## Tasks

### TASK-001: Mover rename/renew/revoke/forceDelete al Service

Inyectar TrustedDeviceDashboardCache en TrustedDeviceService y agregar rename(), renew(), revoke() y forceDelete(). Cada método encapsula la transacción, el cambio de estado, el TrustedDeviceEvent y la invalidación vía DB::afterCommit mediante un único método privado. La lectura de cookie_lifetime_minutes para renew queda en el Service. update, renew, destroy y forceDestroy del controlador pasan a: guard, llamada al Service, flash. Se crea TrustedDeviceServiceTest con cobertura de estos cuatro métodos.

**Type:** refactor
**Effort:** M
**Agent:** laravel-senior-engineer
**Priority:** P0
**Review:** claude,codex
**Depends on:** ninguna

**Acceptance Criteria:**
- [ ] update, renew, destroy y forceDestroy del controlador no contienen DB::transaction, TrustedDeviceEvent::record ni invalidate(); los tests Crud/Update, Crud/Destroy, Crud/ForceDestroy y Lifecycle/Renew pasan sin modificar sus aserciones.
- [ ] Cada método del Service graba el evento correcto (Renamed, Renewed, Revoked, Revoked) y deja invalidado el caché de dashboard del usuario (verificado en TrustedDeviceServiceTest).
- [ ] Caso de falla: si la operación lanza dentro de la transacción no se persiste el evento ni se invalida el caché; un dispositivo de otro usuario sigue devolviendo 403 desde el controlador.

**writeScope:**
- `app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php`
- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php`
- `app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php`

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceUpdateTest.php app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceDestroyTest.php app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceForceDestroyTest.php app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceRenewTest.php app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php && vendor/bin/pint --dirty --format agent`

**Notas de integración:** serviceTest es un archivo nuevo: no requiere registro en phpunit.xml ni Pest.php (directorio padre ya registrado/enlazado). Los roles: service = lógica, controller = delegación HTTP, serviceTest = cobertura del Service.

### TASK-002: Agregar Service::revokeAll y usarlo en destroyAll

Agregar revokeAll(User, Request): int al Service con la implementación del PasswordTrustedDeviceRevoker (lockForUpdate, un RevokedAll por dispositivo, delete masivo, invalidación con DB::afterCommit solo si había dispositivos). destroyAll del controlador delega y conserva el flash de éxito incluso sin dispositivos. Se añaden casos de borde al test existente de destroyAll.

**Type:** refactor
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** claude,codex
**Depends on:** TASK-001

**Acceptance Criteria:**
- [ ] destroyAll del controlador solo valida, llama a revokeAll y devuelve el flash; Crud/TrustedDeviceDestroyAllTest pasa con sus aserciones originales.
- [ ] revokeAll devuelve el número de dispositivos revocados y graba un evento RevokedAll por cada uno.
- [ ] Caso límite: usuario sin dispositivos no graba eventos, no invalida el caché y el flash sigue siendo de éxito; los dispositivos de otro usuario no se tocan.

**writeScope:**
- `app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php`
- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php`
- `app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceDestroyAllTest.php`

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceDestroyAllTest.php app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php && vendor/bin/pint --dirty --format agent`

**Notas de integración:** Dependencia por solapamiento de archivos con TASK-001 (service y controller). El revoker de Password no se toca aquí.

### TASK-003: PasswordTrustedDeviceRevoker delega en el Service

Reemplazar el cuerpo de PasswordTrustedDeviceRevoker::revokeAll por una delegación a TrustedDeviceService::revokeAll, cambiando su dependencia del caché por el Service. Firma pública (User, PasswordRequest|PasswordNewRequest): int sin cambios, de modo que PasswordController y PasswordNewController no se modifican.

**Type:** refactor
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** claude,codex
**Depends on:** TASK-002

**Acceptance Criteria:**
- [ ] PasswordTrustedDeviceRevoker no contiene DB::transaction, TrustedDeviceEvent ni referencia al caché; delega en el Service y retorna el mismo int.
- [ ] Password/Tests/PasswordTrustedDeviceRevocationTest, PasswordUpdateTest y PasswordResetTest pasan sin modificar sus aserciones.
- [ ] Caso de falla: sin dispositivos retorna 0 y no invalida el caché (mismo comportamiento previo).

**writeScope:**
- `app/Auth/Modules/Password/Services/PasswordTrustedDeviceRevoker.php`

**validateCommand:** `vendor/bin/pest app/Auth/Modules/Password/Tests/PasswordTrustedDeviceRevocationTest.php app/Auth/Modules/Password/Tests/PasswordUpdateTest.php app/Auth/Modules/Password/Tests/PasswordResetTest.php && vendor/bin/pint --dirty --format agent`

**Notas de integración:** Paralelo a TASK-004: archivos disjuntos. Cumple el no-goal "no tocar el revoker más allá de delegar".

### TASK-004: Mover reactivate (con dropDuplicate y token) al Service

Agregar reactivate(User, TrustedDevice, Request): string al Service (usa MintsTrustedDeviceToken para mintToken). Incluye dropDuplicateActiveDevice como método privado, forceFill de token_hash/expires_at/last_used_at, restore(), evento Reactivated e invalidación; devuelve el token crudo. El controlador conserva los guards 401/403/404, llama al Service, encola la cookie con queueTrustedDeviceCookie y devuelve el flash. Se eliminan config() y dropDuplicateActiveDevice del controlador.

**Type:** refactor
**Effort:** M
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** claude,codex
**Depends on:** TASK-002

**Acceptance Criteria:**
- [ ] reactivate del controlador no contiene DB::transaction, config(), mintToken ni dropDuplicateActiveDevice; Lifecycle/TrustedDeviceReactivateTest pasa con sus aserciones originales.
- [ ] El token_hash persistido corresponde al token devuelto por el Service y la cookie encolada contiene ese mismo token.
- [ ] Caso límite: si existe un dispositivo activo hermano con el mismo fingerprint se hace forceDelete con evento Revoked antes de restaurar; dispositivo no revocado sigue devolviendo 404 y de otro usuario 403.

**writeScope:**
- `app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php`
- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php`
- `app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceReactivateTest.php`

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Lifecycle/TrustedDeviceReactivateTest.php app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php && vendor/bin/pint --dirty --format agent`

**Notas de integración:** Tanto el Service como el controlador usan el trait MintsTrustedDeviceToken (el controlador sigue necesitando queueTrustedDeviceCookie). Extraerlo a TrustedDeviceTokenIssuer queda fuera de HOLD.

### TASK-005: Mover store al Service con enum de resultado

Crear el enum TrustedDeviceRegistrationResult (Created, AlreadyActive, AlreadyRevoked, ConcurrentExisting) y agregar register(User, DeviceDetector, TrustedDeviceStoreRequest, string $tokenHash, ?string $name) al Service, preservando el doble chequeo de fingerprint (previo a la transacción y con lockForUpdate dentro), pruneOlder, el evento Created solo si wasRecentlyCreated y la invalidación solo cuando se creó. El controlador mintea el token, resuelve DeviceDetector, mapea el enum a flash/cookie y deja de usar InfersDeviceMetadata. ConcurrentExisting conserva el flash de éxito y la cookie actuales.

**Type:** refactor
**Effort:** L
**Agent:** laravel-senior-engineer
**Priority:** P1
**Review:** claude,codex
**Depends on:** TASK-004

**Acceptance Criteria:**
- [ ] store del controlador solo valida, mintea el token, llama a register y mapea el enum; ya no usa InfersDeviceMetadata ni TrustedDevice::findActiveMatch/pruneOlder; Crud/TrustedDeviceStoreTest pasa con sus aserciones originales.
- [ ] Mensajes flash idénticos: AlreadyActive y AlreadyRevoked devuelven sus errores actuales sin crear dispositivo ni encolar cookie; Created devuelve éxito y encola cookie.
- [ ] Caso límite (carrera): ConcurrentExisting no crea evento ni invalida el caché y mantiene el flash de éxito y la cookie actuales; con ip o userAgent nulos se omiten los chequeos de fingerprint como hoy.

**writeScope:**
- `app/Auth/Modules/TrustedDevice/Services/TrustedDeviceService.php`
- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php`
- `app/Auth/Modules/TrustedDevice/Enums/TrustedDeviceRegistrationResult.php`

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Crud/TrustedDeviceStoreTest.php app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php && vendor/bin/pint --dirty --format agent`

**Notas de integración:** Wiring del enum nuevo: autoload PSR-4, sin registro. Es la tarea más delicada por concurrencia; revisar con claude y codex.

### TASK-006: Cobertura del Service para revokeAll, reactivate y register

Ampliar TrustedDeviceServiceTest con casos unitarios/feature de revokeAll, reactivate y register: evento grabado, invalidación del caché solo con cambio real, resultado del enum por cada rama y token devuelto por reactivate. Solo agrega tests; no modifica código de producción.

**Type:** test
**Effort:** M
**Agent:** laravel-senior-engineer
**Priority:** P2
**Review:** claude,codex
**Depends on:** TASK-004, TASK-005

**Acceptance Criteria:**
- [ ] register devuelve Created, AlreadyActive y AlreadyRevoked según el estado previo del fingerprint, y solo Created crea evento e invalida el caché.
- [ ] reactivate devuelve un token cuyo hash coincide con el token_hash persistido y deja el dispositivo restaurado con evento Reactivated.
- [ ] Caso de falla: revokeAll sin dispositivos devuelve 0 sin eventos; revokeAll de un usuario no afecta a otro.

**writeScope:**
- `app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php`

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Services/TrustedDeviceServiceTest.php && vendor/bin/pint --dirty --format agent`

**Notas de integración:** Test-only para mantener cada tarea de código ≤3 archivos. Corte de envío: tras esta tarea y TASK-003 se cumple el objetivo de clean code.

### TASK-007: Policy de ownership para TrustedDevice (opcional)

Crear TrustedDevicePolicy con update, delete, restore y forceDelete (ownership por user_id), registrarla con Gate::policy en AuthServiceProvider::boot y reemplazar los abort_unless(... user_id ...) del controlador por Gate::authorize (el Controller base no usa AuthorizesRequests). Los abort_if(deleted_at === null, 404) y el estrechamiento de tipo del usuario se conservan en el controlador. Es un patrón nuevo en el proyecto (no existen Policies).

**Type:** refactor
**Effort:** M
**Agent:** laravel-senior-engineer
**Priority:** P3
**Review:** claude,codex
**Depends on:** TASK-005

**Acceptance Criteria:**
- [ ] El controlador no contiene comparaciones user_id === getKey(); los accesos de dueño siguen funcionando y los tests Crud y Lifecycle existentes pasan sin modificar sus aserciones.
- [ ] Un dispositivo de otro usuario devuelve 403 en update, renew, destroy, reactivate y forceDestroy.
- [ ] Caso límite: reactivate/forceDestroy sobre un dispositivo no revocado siguen devolviendo 404 (no 403) y el binding withTrashed de las rutas no cambia.

**writeScope:**
- `app/Auth/Providers/AuthServiceProvider.php`
- `app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php`
- `app/Auth/Modules/TrustedDevice/Policies/TrustedDevicePolicy.php`

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Crud app/Auth/Modules/TrustedDevice/Tests/Lifecycle && vendor/bin/pint --dirty --format agent`

**Notas de integración:** Registro ownership: AuthServiceProvider::boot. Si se decide no introducir Policies, esta tarea y TASK-008 se descartan sin afectar al resto.

### TASK-008: Tests de TrustedDevicePolicy

Crear TrustedDevicePolicyTest con casos por cada habilidad (update, delete, restore, forceDelete) para dueño y no dueño, incluyendo dispositivos con soft delete.

**Type:** test
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P3
**Review:** claude,codex
**Depends on:** TASK-007

**Acceptance Criteria:**
- [ ] Cada habilidad devuelve true para el dueño y false para otro usuario.
- [ ] Caso límite: un dispositivo con soft delete sigue autorizando restore y forceDelete solo a su dueño.

**writeScope:**
- `app/Auth/Modules/TrustedDevice/Tests/Policies/TrustedDevicePolicyTest.php`

**validateCommand:** `vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests/Policies/TrustedDevicePolicyTest.php && vendor/bin/pint --dirty --format agent`

**Notas de integración:** Carpeta nueva de tests cubierta por el directorio ya registrado en phpunit.xml y tests/Pest.php.

### TASK-009: Actualizar CLAUDE.md con la nueva estructura

Actualizar CLAUDE.md (módulo Auth/TrustedDevice) para reflejar el Service ampliado, la unificación de revokeAll con el revoker de Password, el enum TrustedDeviceRegistrationResult y, si se implementó, la Policy. La regla del proyecto exige sincronizar la doc en el mismo cambio.

**Type:** docs
**Effort:** S
**Agent:** general-purpose
**Priority:** P3
**Review:** claude,codex
**Depends on:** TASK-003, TASK-006, TASK-008

**Acceptance Criteria:**
- [ ] CLAUDE.md describe los métodos del Service, el enum de resultado y que PasswordTrustedDeviceRevoker delega en el Service.
- [ ] Caso límite: si TASK-007/008 se descartan, el documento no menciona la Policy y no queda ninguna referencia a abort_unless de ownership.

**writeScope:**
- `CLAUDE.md`

**validateCommand:** `grep -n 'TrustedDeviceService' CLAUDE.md`

**Notas de integración:** Ajustar dependsOn si TASK-007/008 se descartan.

### TASK-010: Gate final de calidad backend

Ejecutar los gates de calidad sobre todo el cambio: Pint, PHPStan (level max), Rector (dry-run y aplicar, obligatorio) y los suites completos TrustedDevice y Password. Corregir solo lo que reporten las herramientas sobre los archivos de este plan.

**Type:** chore
**Effort:** S
**Agent:** laravel-senior-engineer
**Priority:** P2
**Review:** claude,codex
**Depends on:** TASK-009

**Acceptance Criteria:**
- [ ] composer phpstan y vendor/bin/pint --dirty --format agent terminan sin errores.
- [ ] composer rector-dry no propone cambios o se aplican con composer rector y se vuelven a pasar los tests.
- [ ] Caso de falla: los suites TrustedDevice y Password completos pasan; cualquier regresión bloquea el cierre de SOC-37.

**writeScope:**
- (vacío)

**validateCommand:** `composer phpstan && composer rector-dry && vendor/bin/pest app/Auth/Modules/TrustedDevice/Tests app/Auth/Modules/Password/Tests && vendor/bin/pint --dirty --format agent`

**Notas de integración:** writeScope vacío: solo se corrigen hallazgos de herramientas en archivos ya modificados por tareas previas. Tras pasar, pedir al usuario el suite completo (php artisan test --compact) según la regla de Pest.

## Failure Modes

- Condición de carrera en store: hoy, si el chequeo con lockForUpdate encuentra un dispositivo activo, se retorna el existente pero el controlador igual encola una cookie con un token que no quedó persistido y muestra éxito. El plan lo preserva (caso ConcurrentExisting) para no cambiar comportamiento; se recomienda un follow-up aparte.
- DB::afterCommit dentro del Service reemplaza la invalidación posterior a la transacción del controlador; es equivalente externamente y ya la usa PasswordTrustedDeviceRevoker con tests, pero conviene confirmarlo en TASK-001.
- destroyAll gana lockForUpdate al unificarse con el revoker; reduce condiciones de carrera y no cambia el resultado observable.
- La Policy es un patrón nuevo y el Controller base no usa AuthorizesRequests; mitigado usando Gate::authorize y manteniendo 404 en el controlador.
- Los tests del Service dependen de createUser()/createTrustedDevice() de tests/Pest.php; si cambian, los tests nuevos deben adaptarse.

## Ship Cut

Si la ejecución se detiene a mitad de camino: con TASK-001 a TASK-006 (más TASK-003 en paralelo) el controlador ya queda con solo responsabilidades HTTP y el objetivo de clean code está cumplido. TASK-007 y TASK-008 (Policy) son opcionales y pueden descartarse; TASK-009 y TASK-010 cierran documentación y calidad.

## Test Coverage Map

| Comportamiento | Pruebas | Tareas |
| --- | --- | --- |
| rename / update | Tests/Crud/TrustedDeviceUpdateTest.php + Tests/Services/TrustedDeviceServiceTest.php | TASK-001 |
| renew | Tests/Lifecycle/TrustedDeviceRenewTest.php + ServiceTest | TASK-001 |
| revoke / forceDelete | Tests/Crud/TrustedDeviceDestroyTest.php, TrustedDeviceForceDestroyTest.php + ServiceTest | TASK-001 |
| revokeAll (controlador) | Tests/Crud/TrustedDeviceDestroyAllTest.php | TASK-002 |
| revokeAll (Password) | Password/Tests/PasswordTrustedDeviceRevocationTest.php, PasswordUpdateTest.php, PasswordResetTest.php | TASK-003 |
| reactivate | Tests/Lifecycle/TrustedDeviceReactivateTest.php | TASK-004 |
| register / store | Tests/Crud/TrustedDeviceStoreTest.php + ServiceTest | TASK-005, TASK-006 |
| invalidación de caché | Tests/Queries/TrustedDeviceDashboardCacheTest.php + ServiceTest | TASK-001, TASK-005 |
| Policy | Tests/Policies/TrustedDevicePolicyTest.php + Tests/Crud y Tests/Lifecycle (403/404) | TASK-007, TASK-008 |

## Execution Summary

- Tareas: 10
- Capa 0: TASK-001
- Capa 1: TASK-002
- Capa 2: TASK-003, TASK-004
- Capa 3: TASK-005
- Capa 4: TASK-006, TASK-007
- Capa 5: TASK-008
- Capa 6: TASK-009
- Capa 7: TASK-010
- Ruta crítica: TASK-001 → TASK-002 → TASK-004 → TASK-005 → TASK-007 → TASK-008 → TASK-009 → TASK-010
- Paralelismo real (archivos disjuntos): TASK-003 con TASK-004, y TASK-006 con TASK-007. El resto es secuencial por solapamiento de service/controller.
- Regla del proyecto: tras cada tarea, resumir cambios y checks y detenerse para revisión antes de continuar.

## Task Dependencies

```text
TASK-001 <- (none)
TASK-002 <- TASK-001
TASK-003 <- TASK-002
TASK-004 <- TASK-002
TASK-005 <- TASK-004
TASK-006 <- TASK-004, TASK-005
TASK-007 <- TASK-005
TASK-008 <- TASK-007
TASK-009 <- TASK-003, TASK-006, TASK-008
TASK-010 <- TASK-009
```
