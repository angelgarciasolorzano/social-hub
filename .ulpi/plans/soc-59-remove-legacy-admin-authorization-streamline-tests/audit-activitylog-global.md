# Auditoría de ActivityLog y de las suites globales (TASK-008)

Alcance: los 15 archivos de `app/Modules/ActivityLog/Tests` (unos 95 casos), los 2 de `tests/Unit` (21) y los 5 de `tests/Feature` (23), más los helpers de `tests/Pest.php`. Mismo criterio que en las auditorías anteriores.

## Resumen

| Tipo | Hallazgos | Casos afectados |
| --- | --- | --- |
| Redundante (se elimina) | R1–R3 | 3 casos |
| Ubicación incorrecta (se mueve) | U1 | 3 casos |
| Helpers de `tests/Pest.php` sin uso | — | ninguno |
| Revisado y conservado | C1–C6 | — |

Esta parte de la suite está bastante limpia: ActivityLog se escribió en SOC-54/SOC-56 con un test por regla y un control de privacidad transversal, y las suites globales son pocas y específicas.

## Redundantes

| Id | Test a eliminar | Lo cubre | Motivo |
| --- | --- | --- | --- |
| R1 | `app/Modules/ActivityLog/Tests/Listeners/AuthActivitySubscriberTest.php` · "keeps the existing login listeners working" | `app/User/Modules/AccountSettings/Tests/Listeners/TrackLastLoginTest.php` · "updates the last login timestamp after valid credentials authenticate the user" | Los dos inician sesión por `login.store` y comprueban que `last_login_at` se actualizó; el de AccountSettings además comprueba que el valor avanzó respecto del anterior. Se escribió en SOC-54 para asegurar que el nuevo suscriptor no rompía los listeners existentes, y el test del dueño del listener ya lo garantiza. |
| R2 | `tests/Feature/MediaLibrary/MediaStoragePathTest.php` · "keeps post and user files in different folders" | Mismo archivo · "stores post images under posts/{media id}/" y "stores user pictures under {media id}/ without a model folder" | Si las imágenes de posts van a `posts/{id}/` y las de usuarios a `{id}/` sin carpeta de modelo, ya están en carpetas distintas. |
| R3 | `tests/Feature/MediaLibrary/MediaStoragePathTest.php` · "uses the custom path generator from App\Support" | Mismo archivo · "stores post images under posts/{media id}/" | El generador por defecto de MediaLibrary guarda en `{id}/`; la ruta `posts/{id}/` solo aparece si `config('media-library.path_generator')` apunta al generador propio, así que el test de almacenamiento ya falla si la configuración cambia. |

## Ubicación incorrecta

- **U1** — `tests/Feature/Auth/PasswordConfirmationTest.php` (3 casos: pantalla de confirmación, invitado redirigido y volver a la página prevista) prueba la confirmación de contraseña de Fortify, que pertenece al dominio Auth. Según CLAUDE.md, `tests/` queda para pruebas transversales o de infraestructura, y las nuevas pruebas de un módulo van en su carpeta `Tests/`. Mover a `app/Auth/Modules/Password/Tests/PasswordConfirmationTest.php` (suite `Password`, ya registrada en `phpunit.xml` y `tests/Pest.php`) y borrar la carpeta `tests/Feature/Auth/`, que queda vacía. No cambia ningún caso.

## Helpers de `tests/Pest.php`

Los 24 helpers tienen al menos un archivo que los usa (entre 2 y 116). No hay ninguno que borrar. Las otras auditorías proponen dos agregados: `invalidOtpFor()` (S1 de `audit-auth-user.md`) y el helper de "rol de prueba con un permiso" (S2 de `audit-admin.md`).

## Revisado y conservado

- **C1 — Control de privacidad transversal** (`ActivityLogPrivacyTest` · "never stores content, passwords or attempted emails across the recorded flows"): recorre varios flujos reales y busca valores centinela en toda la tabla. Se superpone con los tests de cada suscriptor, pero es el único que detecta una fuga nueva en un flujo que nadie revisó.
- **C2 — "records every cataloged event from some listener, command or service"** y **`ActivityLogEventTest`**: fijan que cada evento del catálogo se registra en algún sitio y que cada enum está registrado; son las salvaguardas que CLAUDE.md describe para agregar dominios.
- **C3 — `ActivityLoggerTest` "rejects a forbidden property key…"** (guarda del logger) y los tests de privacidad de cada suscriptor (lo que cada flujo pasa) son dos capas distintas.
- **C4 — `TrustedDeviceActivityMirrorTest`**: el dataset de todas las acciones prueba el espejo directo, "mirrors a trusted device action performed through the application" prueba el camino real y el de rollback prueba la transacción compartida.
- **C5 — `tests/Unit/Support/Seeding`**: cada caso fija una propiedad distinta de `SkewedRandom` y `WeightedRandom`. "repeats the same sequence with the same seed" fija que usan `mt_rand`, de lo que depende `PostSeederTest` con `mt_srand()`.
- **C6 — `MediaLibraryCustomPathGeneratorTest`, `MediaLibraryCleanFoldersCommandTest` y `CreateTestingDatabaseTest`**: infraestructura global, bien ubicada, sin duplicados.

## Propuesta de tareas para TASK-009

1. R1 — `AuthActivitySubscriberTest.php`.
2. R2, R3 — `MediaStoragePathTest.php`.
3. U1 — crear `app/Auth/Modules/Password/Tests/PasswordConfirmationTest.php` y borrar `tests/Feature/Auth/PasswordConfirmationTest.php`.

## Total de las cuatro auditorías

| Auditoría | Casos a eliminar | Ajustes de setup | Movimientos |
| --- | --- | --- | --- |
| Admin (`audit-admin.md`) | 18 | 4 | 1 caso |
| Contenido (`audit-content.md`) | 23 | 2 | — |
| Auth y User (`audit-auth-user.md`) | 15 | 3 | — |
| ActivityLog y globales (este informe) | 3 | — | 3 casos |
| **Total** | **59 de 1211 (≈5 %)** | **9** | **4 casos** |

El ahorro de tiempo de quitar 59 casos es pequeño, unos 3–4 s en serie, porque los tests cuestan entre 50 y 60 ms casi todos. Las mejoras grandes de duración ya vinieron de TASK-002/003 (sin imágenes reales: de 74,2 s a 56,4 s) y de TASK-004 (en paralelo: 13–25 s). El valor de la consolidación está en mantener menos tests y en los arreglos de cobertura que encontró la auditoría:

- el código `'000000'`, que puede ser el válido;
- el test del valor por defecto de `status`, que no lo probaba;
- los parches de `clearMediaCollection()`, que ya no hacen nada.
