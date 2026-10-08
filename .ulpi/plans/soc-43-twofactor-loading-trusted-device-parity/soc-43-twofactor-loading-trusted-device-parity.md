# Plan: SOC-43: Optimizar carga de TwoFactor y alinear gestión de dispositivos de confianza

## Overview

Plan de SOC-43 para medir y optimizar la carga de TwoFactor y, por expansión confirmada, alinear la experiencia de alta y administración de dispositivos de confianza. Incluye la carga correcta de estados ya registrados, revocados y disponibles; las seis acciones con las validaciones ya existentes; y dos rediseños que esperan las referencias visuales que compartirá el usuario al llegar a cada tarea.

## Scope Challenge

EXPANSION confirmada. La auditoría inicial de Network y props Inertia se conserva como base. Se agregan cambios puntuales en TwoFactor para recuperar dos solicitudes redundantes identificadas en el código, acceder a acciones de registros revocados y reusar los flujos de TrustedDevice. Los rediseños no se inventan en esta planificación: sus tareas quedan bloqueadas hasta recibir la referencia del usuario. TrustedDevice sigue siendo la referencia conductual; su tabla y reglas de seguridad no se rediseñan.

## Prerequisites

- SOC-43 permanece In Progress hasta que se completen las optimizaciones y la verificación final.
- Las referencias visuales de Fecha de activación y Agregar dispositivo de confianza se recibirán antes de ejecutar TASK-008 y TASK-009; no implementar esos cambios con diseños supuestos.
- El rediseño de TrustedDeviceAddDialog puede afectar ambas vistas porque el componente es compartido. Al ejecutar TASK-009 se debe fijar el alcance del diseño y mantener el aspecto actual de TrustedDevice si la referencia corresponde solo a TwoFactor.
- Las rutas y validaciones de dispositivos ya existen; se deben reutilizar los diálogos y endpoints actuales.
- No existe un runner de pruebas frontend en package.json; los slices usan Prettier/ESLint por archivo, tipos y QA manual de Network/UI.
- La optimización debe apoyarse en el baseline de Network de TASK-001, considerando React StrictMode en desarrollo.

## Non-Goals

- Cambiar rutas, autorización, reglas de seguridad 2FA/TOTP, endpoints, persistencia o payloads de formularios existentes.
- Rediseñar la tabla o el módulo TrustedDevice; solo cambiar su diálogo compartido si la referencia recibida lo incluye explícitamente.
- Expandir la lista TwoFactor más allá de los tres dispositivos recientes que ya presenta.
- Cambiar la semántica del conteo de dispositivos no revocados.
- Agregar un runner JavaScript, dependencias, documentación adicional, instrumentación permanente o cambios de configuración.
- Optimizar solicitudes que el baseline no confirme como redundantes.

## Contracts

- Conservar las rutas y props públicas de TwoFactor; trustedDevicesCount sigue siendo un entero de dispositivos no revocados.
- La lista TwoFactor conserva el límite de tres recientes e incluye registros soft-deleted para que las acciones de revocación sean accesibles en las filas mostradas.
- En el alta, undefined significa que la coincidencia aún carga, null significa que no hay coincidencia y un registro se clasifica como activo, revocado o expirado usando los mismos estados y diálogos de TrustedDevice.
- El estado activo habilita Ver/Renombrar/Renovar/Revocar; el revocado habilita Ver/Reactivar/Eliminar definitivamente; el resto de las opciones permanece deshabilitado como en la tabla.
- Reactivar conserva OTP/TOTP o código de recuperación; Revocar y Eliminar definitivamente conservan la confirmación de contraseña y aceptación de consecuencias.
- Solo el diálogo de activación y la carga del alta cambian visualmente según las referencias entregadas; no se cambia la semántica de acciones ni de errores.
- Los props opcionales se solicitan al abrir la interacción que los necesita; las cargas fallidas muestran recuperación explícita y no se confunden con datos ausentes válidos.
- La consulta de coincidencia actual no retorna normalmente dispositivos expirados no revocados; no ampliar esa semántica sin una decisión respaldada por la prueba de servicio y el comportamiento de TrustedDevice.

## Existing Code Leverage

- `app/User/Modules/TwoFactor/Controllers/TwoFactorController.php` — Carga de trustedDevicesCount y props opcionales/current-device.
- `app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php` — Pruebas de render y props Inertia de TwoFactor.
- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog.tsx` — Diálogo Fecha de activación y carga de firstTrustedDevice.
- `resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx` — Lista, alta, menú de acciones y wiring de diálogos en TwoFactor.
- `resources/js/modules/setting/modules/twoFactor/data/twoFactorEnable.ts` — Configuración actual de acciones de TwoFactor.
- `resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceOverview.ts` — Acciones de fila compartidas y condiciones por estado.
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAddDialog.tsx` — Diálogo compartido de alta; conservar su variante visual de TrustedDevice.
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAlreadyRegisteredDialog.tsx` — Diálogo existente para la coincidencia activa.
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceRevokedDialog.tsx` — Diálogo existente para un dispositivo revocado.
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceExpiredDialog.tsx` — Diálogo existente para un dispositivo expirado.
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceReactivationDialog.tsx` — Acción compartida con validación OTP.
- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceForceDestroyDialog.tsx` — Acción compartida con confirmación de contraseña/impacto.

## Tasks

### TASK-001: Medir la carga actual de TwoFactor

Capturar en navegador los recorridos de TwoFactor activo e inactivo antes de optimizar. Clasificar cada prop Inertia como inicial, opcional o diferida (incluyendo props que no deban diferirse), con la interacción que la necesita y evidencia de uso. Registrar solicitudes, iniciadores, recargas parciales y cantidad de GET/POST para abrir Fecha de activación, ver dispositivos, activar 2FA y cargar códigos de recuperación. Distinguir el modo dev con React StrictMode de una sesión de producción. El resultado se entrega como evidencia de ejecución de SOC-43/PR, sin añadir documentación permanente al repositorio.

**Phase:** Baseline de red y alcance
**Type:** chore
**Effort:** S
**Agent:** general-purpose
**Priority:** P0
**Acceptance Criteria:**

- La captura clasifica las props como iniciales, opcionales o diferidas y vincula cada prop opcional/diferida a la interacción que la necesita.
- La captura registra por interacción si se repiten el POST de configuración, la carga de códigos o las recargas de trustedDevices/currentDevice, incluidos sus iniciadores.
- Cada optimización posterior se vincula a una solicitud o consulta redundante observada; las interacciones fallidas o canceladas no deben disparar operaciones adicionales.

**writeScope:**

- Sin archivos de implementación.

**validateCommand:**

```sh
Manual QA: DevTools > Network en 2FA activo e inactivo; guardar el resumen de solicitudes en el reporte de ejecución de SOC-43.
```

### TASK-002: Evitar conteos innecesarios y exponer dispositivos revocados

Ajustar el payload de TwoFactor para no consultar el conteo de dispositivos cuando 2FA está desactivado, conservando el valor público numérico, y hacer que la lista de hasta tres dispositivos recientes incluya registros soft-deleted. Mantener trustedDevicesCount como conteo de dispositivos no revocados; esto habilita las acciones de gestión de los registros revocados sin cambiar el resumen actual.

**Phase:** Optimización y datos de dispositivos
**Type:** feature
**Effort:** M
**Agent:** laravel-senior-engineer
**Priority:** P1
**Depends on:** TASK-001
**Acceptance Criteria:**

- Con 2FA desactivado, el payload conserva trustedDevicesCount con valor 0 y no ejecuta la consulta COUNT de dispositivos.
- Con 2FA activo, trustedDevicesCount sigue contando solo dispositivos no revocados y la lista devuelve hasta tres registros recientes incluyendo soft-deleted con deletedAt serializado.
- La prueba cubre una lista vacía y una combinación de dispositivos activos y revocados sin alterar el contrato de props existente.

**writeScope:**

- `app/User/Modules/TwoFactor/Controllers/TwoFactorController.php` — Condicionar el conteo por estado de 2FA e incluir soft-deleted solo en la lista limitada.
- `app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php` — Probar el payload, el conteo y los casos vacío/activo/revocado.

**validateCommand:**

```sh
rtk composer rector-dry && rtk composer rector && rtk vendor/bin/pint --dirty --format agent && rtk php artisan test --compact app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php
```

### TASK-003: Cargar los detalles de activación solo al abrir su diálogo

Mover la recarga del prop opcional firstTrustedDevice desde el montaje del diálogo cerrado hasta su apertura. Evitar solicitudes repetidas al cerrar y volver a abrir después de haber cargado los datos, y mantener un estado de carga/reintento si la petición falla.

**Phase:** Optimización y datos de dispositivos
**Type:** refactor
**Effort:** S
**Agent:** react-vite-tailwind-engineer
**Priority:** P1
**Depends on:** TASK-001
**Acceptance Criteria:**

- Visitar TwoFactor no solicita firstTrustedDevice mientras el diálogo Fecha de activación permanece cerrado.
- Abrir el diálogo solicita el prop una sola vez y reabrirlo con datos cargados no genera otra GET.
- Si la carga falla, el diálogo no presenta datos vacíos como si fueran válidos y ofrece una ruta de reintento.
- Los hooks de React usados por este diálogo declaran explícitamente el tipo de sus valores, por ejemplo `useState<boolean>` y `useRef<boolean>`.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog.tsx` — Controlar carga opcional según apertura y representar carga/error/reintento.

**validateCommand:**

```sh
rtk npm exec -- prettier --check resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog.tsx && rtk npm exec -- eslint resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog.tsx && rtk npm run types
```

### TASK-004: Unificar la carga de la lista y resolver correctamente el alta

Cargar trustedDevices, currentDevicePreview y currentDeviceMatch en una sola recarga parcial al abrir la lista. Tratar undefined como carga pendiente, null como ausencia de coincidencia y resolver los estados con el mismo comportamiento de TrustedDevice: alta normal, ya registrado, revocado o expirado cuando el payload represente ese estado. La búsqueda actual no devuelve coincidencias expiradas no revocadas; conservar ese comportamiento de backend y no ampliar la búsqueda sin evidencia y regresión específica.

**Phase:** Optimización y datos de dispositivos
**Type:** feature
**Effort:** M
**Agent:** react-vite-tailwind-engineer
**Priority:** P1
**Depends on:** TASK-001
**Acceptance Criteria:**

- Abrir la lista produce una sola recarga parcial que incluye trustedDevices, currentDevicePreview y currentDeviceMatch.
- Mientras currentDeviceMatch sea undefined no se abre por error el diálogo de dispositivo ya registrado; null permite el diálogo de alta.
- Una coincidencia activa abre AlreadyRegistered, una revocada abre el diálogo Revoked y una coincidencia inactiva recibida abre Expired; cancelar o fallar la carga no registra el dispositivo.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/views/TwoFactorEnable.tsx` — Solicitar en una sola recarga los props que necesita la lista.
- `resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx` — Representar la carga pendiente y resolver los cuatro estados de alta con los diálogos compartidos.

**validateCommand:**

```sh
rtk npm exec -- prettier --check resources/js/modules/setting/modules/twoFactor/views/TwoFactorEnable.tsx resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx && rtk npm exec -- eslint resources/js/modules/setting/modules/twoFactor/views/TwoFactorEnable.tsx resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx
```

### TASK-005: Evitar la doble carga de códigos de recuperación

Dejar un único responsable de solicitar los códigos al completar la activación. El paso de éxito debe consumir el resultado ya cargado y distinguir de forma explícita entre lista vacía y carga fallida para que el estado vacío no inicie una segunda petición concurrente.

**Phase:** Optimización y datos de dispositivos
**Type:** refactor
**Effort:** S
**Agent:** react-vite-tailwind-engineer
**Priority:** P1
**Depends on:** TASK-001
**Acceptance Criteria:**

- Completar activación dispara una sola GET de códigos de recuperación.
- Una respuesta válida con lista vacía no dispara una segunda GET automática.
- Ante una respuesta fallida el paso de éxito comunica el fallo y permite reintentar sin duplicar una solicitud ya en curso.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorActivationFlow.ts` — Mantener el único punto de carga y exponer resultado/error.
- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/TwoFactorSetupDialog.tsx` — Pasar el estado de carga y la función centralizada al paso de éxito.
- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/TwoFactorSuccessStep.tsx` — Consumir el resultado sin iniciar un fetch duplicado y conservar reintento explícito.

**validateCommand:**

```sh
rtk npm exec -- prettier --check resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorActivationFlow.ts resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/TwoFactorSetupDialog.tsx resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/TwoFactorSuccessStep.tsx && rtk npm exec -- eslint resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorActivationFlow.ts resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/TwoFactorSetupDialog.tsx resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorDisabled/steps/TwoFactorSuccessStep.tsx
```

### TASK-006: Eliminar la solicitud duplicada al iniciar la configuración 2FA

Conservar una única llamada al endpoint Fortify de configuración por envío de activación. Coordinar el submit del formulario y la carga de setup data para que un clic no invoque dos veces la misma acción; el fallo del primer envío debe permitir un reintento del usuario sin cambiar las verificaciones de OTP.

**Phase:** Optimización y datos de dispositivos
**Type:** refactor
**Effort:** M
**Agent:** react-vite-tailwind-engineer
**Priority:** P1
**Depends on:** TASK-001
**Acceptance Criteria:**

- Un envío válido genera una sola POST a /user/two-factor-authentication y obtiene los datos de configuración necesarios.
- Una respuesta de error no genera una segunda POST automática y deja disponible un reintento explícito.
- La verificación OTP, los mensajes de error y la navegación por los pasos existentes se conservan.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/views/TwoFactorDisabled.tsx` — Iniciar el flujo de configuración con un único submit.
- `resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorAuth.ts` — Mantener la petición y el estado de respuesta en un único responsable.

**validateCommand:**

```sh
rtk npm exec -- prettier --check resources/js/modules/setting/modules/twoFactor/views/TwoFactorDisabled.tsx resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorAuth.ts && rtk npm exec -- eslint resources/js/modules/setting/modules/twoFactor/views/TwoFactorDisabled.tsx resources/js/modules/setting/modules/twoFactor/hooks/useTwoFactorAuth.ts
```

### TASK-007: Alinear las acciones de TwoFactor con TrustedDevice

Reutilizar la configuración de acciones y sus condiciones de TrustedDevice en las filas de TwoFactor. Añadir Reactivar y Eliminar definitivamente usando los diálogos compartidos existentes. Mantener visibles las seis opciones como en la tabla, deshabilitando las incompatibles con el estado; las acciones sensibles deben pasar por los mismos formularios y validaciones de OTP, contraseña y aceptación de consecuencias.

**Phase:** Paridad de acciones
**Type:** feature
**Effort:** M
**Agent:** react-vite-tailwind-engineer
**Priority:** P1
**Depends on:** TASK-002, TASK-004
**Acceptance Criteria:**

- Cada fila ofrece Ver, Renombrar, Renovar, Revocar, Reactivar y Eliminar definitivamente; el estado activo habilita las primeras cuatro y el revocado habilita Ver, Reactivar y Eliminar definitivamente.
- Las acciones incompatibles se muestran deshabilitadas y no abren diálogos ni ejecutan endpoints.
- Reactivar reutiliza la validación OTP existente; Revocar y Eliminar definitivamente conservan contraseña y aceptación de consecuencias; fallos de autorización/validación permanecen visibles.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/data/twoFactorEnable.ts` — Usar la configuración compartida de las seis acciones y condiciones por estado.
- `resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx` — Renderizar las opciones y conectar Reactivar/Eliminar definitivamente con los diálogos existentes.

**validateCommand:**

```sh
rtk npm exec -- prettier --check resources/js/modules/setting/modules/twoFactor/data/twoFactorEnable.ts resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx && rtk npm exec -- eslint resources/js/modules/setting/modules/twoFactor/data/twoFactorEnable.ts resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx
```

### TASK-008: Rediseñar el diálogo Fecha de activación con la referencia del usuario

Esperar a que el usuario comparta el diseño al llegar a este paso. Implementar la referencia en el diálogo existente sin inventar la composición visual antes de recibirla. Preservar el contrato de datos, la carga bajo demanda, el cierre accesible y el comportamiento de error/reintento. La flecha del alert navega a dispositivos de confianza con Wayfinder y comunica el destino mediante Tooltip.

**Phase:** Rediseños pendientes de referencia
**Type:** feature
**Effort:** M
**Agent:** react-vite-tailwind-engineer
**Priority:** P2
**Depends on:** TASK-003
**Acceptance Criteria:**

- La referencia visual del usuario está disponible antes de cambiar el JSX y la implementación corresponde a sus elementos y estados indicados.
- La fecha y los datos mostrados siguen correspondiendo al dispositivo de activación, con estados de carga/error y cierre por teclado operativos.
- El rediseño no añade una petición al montar la pantalla ni cambia props/endpoints; la flecha navega a dispositivos de confianza y el Tooltip informa su destino al pasar el cursor o enfocar con teclado.

**writeScope:**

- `resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog.tsx` — Aplicar el diseño recibido sobre el diálogo TwoFactor existente.

**validateCommand:**

```sh
rtk npm exec -- prettier --check resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog.tsx && rtk npm exec -- eslint resources/js/modules/setting/modules/twoFactor/components/dialog/twoFactorEnable/TwoFactorActivationDetailsDialog.tsx
```

### TASK-009: Rediseñar Agregar dispositivo de confianza con la referencia del usuario

Esperar a que el usuario comparta el diseño al llegar a este paso. El diálogo TrustedDeviceAddDialog es compartido: aplicar el diseño a TwoFactor y preservar la apariencia actual de TrustedDevice mediante una variante contextual si la referencia es específica de TwoFactor. Si la referencia es expresamente común, actualizar el componente compartido y revisar ambos call sites.

**Phase:** Rediseños pendientes de referencia
**Type:** feature
**Effort:** M
**Agent:** react-vite-tailwind-engineer
**Priority:** P2
**Depends on:** TASK-004, TASK-007
**Acceptance Criteria:**

- El diseño del usuario está disponible antes de cambiar la interfaz y su alcance (TwoFactor o ambas pantallas) queda explícito en el cambio.
- El alta conserva el flujo de nombre, validación y envío existentes; si el rediseño es exclusivo de TwoFactor, TrustedDevice mantiene su apariencia actual.
- Errores y cancelación no registran un dispositivo y el diálogo funciona con teclado y viewport móvil.

**writeScope:**

- `resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAddDialog.tsx` — Implementar el diseño recibido y mantener el aspecto predeterminado actual si requiere variante.
- `resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx` — Seleccionar la variante TwoFactor y conservar el wiring de estados de alta.

**validateCommand:**

```sh
rtk npm exec -- prettier --check resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAddDialog.tsx resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx && rtk npm exec -- eslint resources/js/modules/setting/modules/trustedDevice/components/dialog/actions/TrustedDeviceAddDialog.tsx resources/js/modules/setting/modules/twoFactor/components/ui/twoFactorEnable/TwoFactorDevices.tsx
```

### TASK-010: Verificar rendimiento, paridad de acciones y los dos rediseños

Repetir Network QA en activo/inactivo y flujos de activación, códigos y dispositivos; comprobar estados y acciones en TwoFactor, validaciones sensibles y las dos referencias de diseño. Ejecutar los gates globales de frontend y el gate final de backend después de completar sus pruebas, Rector y Pint.

**Phase:** Verificación final
**Type:** test
**Effort:** M
**Agent:** general-purpose
**Priority:** P1
**Depends on:** TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009
**Acceptance Criteria:**

- La matriz final compara el baseline con el resultado y confirma que cada optimización eliminó la solicitud/consulta redundante identificada sin retrasar datos necesarios.
- La matriz de estados verifica dispositivo nuevo, activo, revocado, expirado recibido, lista vacía y carga fallida; los formularios conservan errores de OTP/contraseña/aceptación.
- Los dos diálogos coinciden con las referencias recibidas en desktop/móvil y funcionan con teclado; TrustedDevice conserva el alcance visual acordado.

**writeScope:**

- Sin archivos de implementación.

**validateCommand:**

```sh
rtk npm run format:check && rtk npm run lint:check && rtk npm run types && rtk npm run build && rtk composer doctor
```

### TASK-011: Agregar la regla de tipado explícito para hooks de React

Al cerrar SOC-43, registrar en las reglas frontend del proyecto que los hooks de React en TypeScript deben declarar explícitamente sus tipos cuando la API lo permita. Incluir estado y referencias (`useState<T>`, `useRef<T>`) y los tipos de valor o callback de otros hooks (`useMemo`, `useCallback`, etc.); no depender de inferencia para esos contratos.

**Phase:** Convenciones del proyecto
**Type:** chore
**Effort:** S
**Agent:** general-purpose
**Priority:** P2
**Depends on:** TASK-010
**Acceptance Criteria:**

- `.ai/rules/frontend.md` contiene una regla breve y aplicable sobre tipado explícito en hooks de React escritos en TypeScript.
- La regla incluye ejemplos de `useState<T>` y `useRef<T>` y aclara que aplica a los demás hooks con tipos genéricos o de callback disponibles.
- No se modifica ninguna lógica de producto al registrar la regla.

**writeScope:**

- `.ai/rules/frontend.md` — Agregar la convención autorizada de tipado explícito para hooks de React.

**validateCommand:**

```sh
rtk git diff --check -- .ai/rules/frontend.md
```

## Failure Modes

- Una carga opcional en estado undefined puede abrir el diálogo equivocado; el flujo espera a resolver la petición antes de clasificar.
- Los registros soft-deleted quedan fuera de la relación por defecto; el payload los incluye en el límite reciente antes de habilitar Reactivar/Eliminar definitivamente.
- El branch Expired existe en TrustedDevice, pero el matcher actual no devuelve expiraciones normales; la expansión de esa consulta queda fuera hasta contar con una prueba que confirme el contrato.
- React StrictMode puede repetir efectos en desarrollo; Network QA distingue el entorno y los efectos se vuelven idempotentes según el estado de carga.
- Un cambio visual al diálogo compartido puede alterar TrustedDevice; el alcance del diseño debe quedar decidido en TASK-009 y la variante actual debe conservarse.
- Reimplementar acciones en lugar de reutilizar sus diálogos podría saltar validaciones; los seis casos usan configuración y validadores existentes.

## Ship Cut

TASK-001 a TASK-007 forman el corte funcional de rendimiento y paridad. TASK-008 y TASK-009 requieren sus referencias antes de ejecución; SOC-43 no se considera terminado hasta que ambos rediseños, TASK-010 y la regla de proyecto de TASK-011 estén completos. No cerrar ni mover la tarea de Linear a Done antes del gate final.

## Test Coverage Map

- Backend: `app/User/Modules/TwoFactor/Tests/TwoFactorSettingsTest.php` cubre conteo condicional y serialización de registros soft-deleted.
- Frontend por slice: Prettier y ESLint acotados a los archivos editados; `npm run types` y checks globales en TASK-010.
- Network manual: primera carga activa/inactiva, apertura de Fecha de activación, lista de dispositivos, activación y recuperación; comparar conteos contra TASK-001.
- UX manual: activo, revocado, expirado recibido, no coincidencia, error y cancelación; probar las validaciones de OTP/contraseña y los dos diseños en móvil/desktop con teclado.
- No añadir runner frontend: `package.json` no contiene uno y la cobertura de esta expansión se cierra con QA de navegador más checks de lint/tipos.

## Execution Summary

- Modo: EXPANSION; revisión predeterminada: codex.
- Tareas: 11 en 6 fases.
- Ruta crítica (6): TASK-001 → TASK-004 → TASK-007 → TASK-009 → TASK-010 → TASK-011.
- Tras el baseline pueden avanzar en paralelo TASK-002 a TASK-006; los rediseños se desbloquean al recibir sus referencias.
- La regla de tipado explícito de hooks se agrega al final, después de la verificación global de SOC-43.
- Prerrequisitos principales: diseño del usuario en cada paso, endpoints/validaciones actuales y baseline Network.
- No-goals principales: seguridad/rutas, rediseño de TrustedDevice fuera del diálogo compartido acordado, instrumentación permanente y nuevo runner.

## Task Dependencies

- TASK-001 ← (sin dependencias)
- TASK-002 ← TASK-001
- TASK-003 ← TASK-001
- TASK-004 ← TASK-001
- TASK-005 ← TASK-001
- TASK-006 ← TASK-001
- TASK-007 ← TASK-002, TASK-004
- TASK-008 ← TASK-003
- TASK-009 ← TASK-004, TASK-007
- TASK-010 ← TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-009
- TASK-011 ← TASK-010

Ruta crítica: TASK-001 → TASK-004 → TASK-007 → TASK-009 → TASK-010 → TASK-011.
