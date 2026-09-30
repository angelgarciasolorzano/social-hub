# Plan: SOC-30 — Rendimiento y accesibilidad de TrustedDevice

## Overview

**Modo:** EXPANSION. **Revisión predeterminada:** codex.

El plan cubre los tres frentes de SOC-30 y suma dos entregables acordados: una ayuda dedicada para los atajos y una medición repetible del ahorro de consultas. Conserva los contratos actuales de Inertia y filtros.

## Scope Challenge

- Ya existen lecturas diferidas de estadísticas y actividad reciente; buildStats() hace ocho consultas agregadas y la actividad reciente carga tres eventos. La tabla filtrada y el diálogo de actividad tienen filtros y paginación propios, por lo que quedan fuera del caché.
- Durante un reload, TrustedDevicesTableSection devuelve el skeleton y desmonta la barra que contiene el popover. Además, el diálogo de actividad reciente vive dentro de su tarjeta diferida; para abrirlo desde un atajo estable, su estado debe vivir en la página.
- El módulo no tiene uso actual de caché ni una dependencia de Hotkeys. package.json tampoco declara un runner de pruebas frontend. Las consultas del módulo se basan en el usuario autenticado y no encontramos una dimensión de tenant en sus modelos.

## Prerequisites

- Usar el cache store configurado mediante la API compatible del framework, con claves explícitas por usuario y sin depender de cache tags.
- Añadir @tanstack/react-hotkeys a dependencias de frontend. El listado actual de npm muestra 0.10.0 y marca la biblioteca como alpha; usar inicialmente ^0.10.0 y conservar la resolución exacta en package-lock.json.
- La documentación React vigente usa useHotkeySequence para secuencias y formatForDisplay para las etiquetas; verificar las opciones de inputs contra esa versión antes de integrarla.

Referencias consultadas: [Listado del paquete](https://www.npmjs.com/package/%40tanstack/react-hotkeys) · [TanStack React Quick Start](https://tanstack.com/hotkeys/latest/docs/framework/react/quick-start).

## Non-Goals

- Cambiar rutas, filtros, paginación, props de Inertia o semántica de acciones.
- Añadir atajos fuera de TrustedDevice o atajos para acciones destructivas.
- Añadir un runner frontend, instrumentación de caché en producción o migraciones de base de datos.

## Contracts

- Cachear solo stats y recentActivity, conservando sus estructuras actuales. TTL: 60 segundos para estadísticas y 30 segundos para actividad reciente. Claves: trusted-device:dashboard:{userId}:stats y trusted-device:dashboard:{userId}:recent-activity.
- Invalidar ambas claves después de una mutación exitosa y después de completar las escrituras de los listeners. No añadir dimensión de tenant: el módulo observado solo particiona estos datos por usuario.
- Atajos globales de la página: ? abre la ayuda; G → N abre el flujo existente de agregar dispositivo; G → A abre el diálogo de actividad. Se deshabilitan al escribir en campos editables o mientras hay un diálogo abierto. Las acciones de fila siguen disponibles con teclado por sus controles normales.
- La prueba de rendimiento compara lecturas de las tablas pertinentes: estadísticas 8 → 0 consultas en una lectura caliente; actividad reciente 1 → 0. No se exige un umbral de latencia.
- Sin runner frontend, la regresión visual y de atajos se documenta como QA manual en navegador, tal como se acordó.

## Existing Code Leverage

- [app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceIndexController.php](/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceIndexController.php): Lecturas diferidas actuales.
- [resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx](/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx): Sección de tabla, skeleton y estado de diálogos.
- [resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecentActivity.tsx](/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecentActivity.tsx): Botón existente para abrir el historial.
- [app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceIndexTest.php](/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceIndexTest.php): Prueba backend existente.
- [app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceRememberTest.php](/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceRememberTest.php): Prueba backend existente.
- [app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php](/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php): Prueba backend existente.

## Tasks

### TASK-001: Añadir caché por usuario a estadísticas y actividad reciente

**Description:** Extraer las dos lecturas cacheables a TrustedDeviceDashboardCache, inyectarlo en el controlador de índice y conservar la serialización de recursos como arrays. Añadir una prueba que mida lecturas frías y calientes.

**Type:** feature  **Priority:** P0  **Effort:** M

**Agent:** `laravel-senior-engineer`  **Review:** `codex`

**Depends on:** —

**writeScope:**
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceIndexController.php`
- **create:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Services/TrustedDeviceDashboardCache.php`
- **create:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php`

**Acceptance Criteria:**
- Las props stats y recentActivity conservan su forma y se cachean con los TTL y claves definidos; ningún resultado de otro usuario puede reutilizarse.
- La prueba demuestra 8→0 consultas de estadísticas y 1→0 de actividad en una lectura caliente; una lectura tras vencer el TTL vuelve a consultar.
- Las listas filtradas y activityDialog continúan sin caché; la prueba cubre aislamiento entre usuarios.

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php`

### TASK-002: Invalidar caché tras mutaciones HTTP

**Description:** Invalidar ambas claves después de completar con éxito las mutaciones del controlador y cubrir actualización de datos, errores y aislamiento.

**Type:** feature  **Priority:** P1  **Effort:** M

**Agent:** `laravel-senior-engineer`  **Review:** `codex`

**Depends on:** TASK-001

**writeScope:**
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Controllers/TrustedDeviceController.php`
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php`

**Acceptance Criteria:**
- Crear, renombrar, renovar, revocar uno o todos, reactivar y eliminar definitivamente invalidan después de completar sus escrituras.
- Una lectura posterior al cambio devuelve las estadísticas y actividad nuevas, incluida la limpieza de un dispositivo duplicado durante reactivación.
- Una petición rechazada o sin mutación no invalida datos de otro usuario ni expone datos entre cuentas.

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Queries/TrustedDeviceDashboardCacheTest.php`

### TASK-003: Invalidar caché al recordar un dispositivo

**Description:** Cubrir el flujo de segundo factor que registra un dispositivo y su evento.

**Type:** feature  **Priority:** P1  **Effort:** S

**Agent:** `laravel-senior-engineer`  **Review:** `codex`

**Depends on:** TASK-001

**writeScope:**
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Listeners/TrustedDeviceRemember.php`
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceRememberTest.php`

**Acceptance Criteria:**
- Con remember_device=true, estadísticas y actividad reflejan el dispositivo y evento recién creados.
- Con remember_device=false, el listener no crea el evento ni invalida caché.
- El cambio no invalida las claves de otro usuario.

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceRememberTest.php`

### TASK-004: Invalidar caché al desactivar 2FA

**Description:** Invalidar después de que el listener termine de registrar eventos y revocar dispositivos dentro de su transacción.

**Type:** bug  **Priority:** P1  **Effort:** S

**Agent:** `laravel-senior-engineer`  **Review:** `codex`

**Depends on:** TASK-001

**writeScope:**
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Listeners/TrustedDeviceInvalidate.php`
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php`

**Acceptance Criteria:**
- Después de desactivar 2FA, una lectura refleja dispositivos revocados y eventos nuevos.
- La invalidación ocurre tras la transacción; la caché de otro usuario se mantiene intacta.
- El test cubre el caso sin dispositivos y no produce errores ni datos compartidos.

**validateCommand:** `rtk php artisan test --compact app/Auth/Modules/TrustedDevice/Tests/Listeners/TrustedDeviceInvalidateTest.php`

### TASK-005: Mantener barra y popover durante la carga de filtros

**Description:** Mantener montada la barra de herramientas cuando ya existen datos y un reload reemplaza la tabla por skeleton.

**Type:** bug  **Priority:** P1  **Effort:** S

**Agent:** `react-vite-tailwind-engineer`  **Review:** `codex`

**Depends on:** —

**writeScope:**
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx`

**Acceptance Criteria:**
- Al aplicar cada filtro, la barra y el popover permanecen montados y abiertos mientras la tabla muestra skeleton y recibe resultados.
- Escape y clic fuera cierran el popover; restablecer filtros no lo cierra durante el reload.
- El skeleton inicial, los errores, los parámetros de URL y la semántica de filtros siguen iguales.

**validateCommand:** `rtk npm exec -- prettier --check resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx && rtk npm exec -- eslint resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx`

### TASK-006: Llevar el diálogo de actividad al estado estable de la página

**Description:** Hacer que la página controle el diálogo y que la tarjeta de actividad invoque un callback para abrirlo. Esto permite que el atajo funcione aunque la tarjeta diferida se desmonte.

**Type:** refactor  **Priority:** P1  **Effort:** S

**Agent:** `react-vite-tailwind-engineer`  **Review:** `codex`

**Depends on:** TASK-005

**writeScope:**
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx`
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecentActivity.tsx`

**Acceptance Criteria:**
- El botón existente abre el mismo diálogo mediante onOpenActivity; no quedan dos dueños del estado.
- El atajo puede abrir actividad aunque la tarjeta no esté montada; el diálogo solicita activityDialog como ahora.
- Al cerrar por Escape, el foco no se coloca en el botón «Ver toda la actividad».

**validateCommand:** `rtk npm exec -- prettier --check resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecentActivity.tsx && rtk npm exec -- eslint resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx resources/js/modules/setting/modules/trustedDevice/components/ui/TrustedDeviceRecentActivity.tsx`

### TASK-007: Añadir dependencia y hook de atajos

**Description:** Instalar @tanstack/react-hotkeys@^0.10.0 y crear un hook de TrustedDevice que exponga callbacks para ayuda, alta y actividad. Registrar el hook en el nivel de página, no dentro de la tabla diferida.

**Type:** feature  **Priority:** P1  **Effort:** M

**Agent:** `react-vite-tailwind-engineer`  **Review:** `codex`

**Depends on:** —

**writeScope:**
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/package.json`
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/package-lock.json`
- **create:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/resources/js/modules/setting/modules/trustedDevice/hooks/useTrustedDeviceShortcuts.ts`

**Acceptance Criteria:**
- El hook registra ?, G→N y G→A usando las APIs React documentadas; el lockfile resuelve la versión instalada.
- Los callbacks no se disparan al escribir en inputs, textareas o contenido editable, ni desde un diálogo abierto.
- El hook permite apagar los registros cuando la página no está activa y no intercepta combinaciones reservadas del navegador.

**validateCommand:** `rtk npm ls @tanstack/react-hotkeys && rtk npm exec -- prettier --check resources/js/modules/setting/modules/trustedDevice/hooks/useTrustedDeviceShortcuts.ts && rtk npm exec -- eslint resources/js/modules/setting/modules/trustedDevice/hooks/useTrustedDeviceShortcuts.ts`

### TASK-008: Conectar atajos y ayuda accesible

**Description:** Conectar callbacks en la página, añadir un botón visible de ayuda y un diálogo que liste las combinaciones con etiquetas de teclas adaptadas a la plataforma.

**Type:** feature  **Priority:** P1  **Effort:** M

**Agent:** `react-vite-tailwind-engineer`  **Review:** `codex`

**Depends on:** TASK-005, TASK-006, TASK-007

**writeScope:**
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx`
- **create:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceKeyboardShortcutsDialog.tsx`
- **modify:** `/Users/angelnoegarciasolorzano/Documents/Repositories/social-hub/resources/js/modules/setting/modules/trustedDevice/components/dialog/index.ts`

**Acceptance Criteria:**
- ? abre ayuda, G→N usa el flujo existente de alta y G→A abre actividad; ninguno ejecuta una mutación destructiva.
- La ayuda es alcanzable con teclado, lista todas las combinaciones y presenta correctamente sus teclas; el diálogo conserva foco, Escape y retorno de foco.
- Las acciones de fila siguen operables con teclado sin usar atajos.

**validateCommand:** `rtk npm exec -- prettier --check resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceKeyboardShortcutsDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/index.ts && rtk npm exec -- eslint resources/js/modules/setting/modules/trustedDevice/TrustedDevice.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/info/TrustedDeviceKeyboardShortcutsDialog.tsx resources/js/modules/setting/modules/trustedDevice/components/dialog/index.ts`

### TASK-009: Ejecutar integración, QA manual y preparar revisión

**Description:** Ejecutar gates integrados, completar la matriz manual de teclado y filtros, revisar Wayfinder generado y preparar el PR con resumen de resultados para SOC-30. No mover el issue a Done hasta cumplir los gates y criterios.

**Type:** test  **Priority:** P2  **Effort:** M

**Agent:** `general-purpose`  **Review:** `codex`

**Depends on:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008

**writeScope:**
- `[]` — solo validación y preparación de revisión.

**Acceptance Criteria:**
- Pint, PHPStan, Rector dry-run seguido de Rector, tests backend y gates frontend pasan; cualquier diff de Wayfinder se revisa.
- QA manual cubre skeleton inicial, cada filtro, popover abierto durante reload, Escape/clic fuera, ?, G→N, G→A, campos editables y que Escape no enfoque «Ver toda la actividad».
- El PR usa la plantilla del repositorio y el resumen de SOC-30 registra los resultados y limitaciones de QA manual.

**validateCommand:** `rtk vendor/bin/pint --dirty --format agent && rtk composer phpstan && rtk composer rector-dry && rtk composer rector && rtk php artisan test --compact && rtk npm run format:check && rtk npm run lint:check && rtk npm run types && rtk npm run build && rtk npm run build:ssr`

## Failure Modes

- El cache store efectivo puede diferir del database predeterminado. Las claves explícitas y operaciones remember/forget evitan depender de tags; verificar el store configurado en el entorno de prueba y el de ejecución.
- expiringSoon y recentlyAdded cambian con el tiempo sin mutaciones; el TTL de 60 s limita ese desfase. Las escrituras del módulo y listeners invalidan ambas entradas inmediatamente.
- La API de Hotkeys es alpha; el hook debe compilar y funcionar en el build normal y SSR con la versión bloqueada.
- La prueba de atajos será manual, no automatizada. Si se exige cumplir el criterio de regresión automatizada literalmente, este plan requerirá introducir un runner y cambiar el recorte aprobado.

## Ship Cut

No hay migraciones. El caché usa claves por usuario con TTL corto y se puede dejar vencer si se revierte la integración. No dar SOC-30 por completada si queda sin resolver el popover, falta una invalidación, los atajos se disparan dentro de campos/dialogs o falla un gate.

## Test Coverage Map

- **Pest:** cold/warm cache, conteo de consultas, TTL, aislamiento entre usuarios, mutaciones HTTP, remember-device y desactivación de 2FA.
- **Navegador manual:** reload de cada filtro sin cierre del popover; cierre normal; atajos en página, input y diálogo; foco inicial y que Escape no enfoque «Ver toda la actividad».
- **Integración:** suite backend completa, Pint, PHPStan, Rector, format, lint, TypeScript, build normal y SSR.

## Execution Summary

El trabajo puede empezar en paralelo con TASK-001, TASK-005, TASK-007.

**Ruta crítica:** TASK-005 → TASK-006 → TASK-008 → TASK-009.

TASK-002/003/004 esperan el contrato del caché; TASK-006 espera el arreglo del popover; TASK-008 une el hook con la página y diálogo de actividad. TASK-009 cierra gates y revisión.

## Task Dependencies

- TASK-001 → TASK-002, TASK-003, TASK-004, TASK-009.
- TASK-002 → TASK-009.
- TASK-003 → TASK-009.
- TASK-004 → TASK-009.
- TASK-005 → TASK-006, TASK-008, TASK-009.
- TASK-006 → TASK-008, TASK-009.
- TASK-007 → TASK-008, TASK-009.
- TASK-008 → TASK-009.
- TASK-009 → —.
