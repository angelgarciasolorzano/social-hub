## Resumen de SOC-59

### Contexto

`AdminAuthorizationSeeder` mantenía dos rutinas que renombraban permisos y un rol de SOC-44 (`users.viewAny`, `users.view` y `users-reader`). Además, la tarea pedía auditar todas las suites para quitar tests redundantes u obsoletos sin perder cobertura y hacer la suite más eficiente. Se trabajó en modo EXPANSION: además de la consolidación, se optimizó la infraestructura de tests.

### Decisiones

| Tema | Decisión | Evidencia o motivo |
| --- | --- | --- |
| Compatibilidad heredada del seeder | Eliminada junto con sus 2 tests | Los nombres nacieron en `0021e7f6` (SOC-44) y el commit siguiente, `abb4e6cc` (SOC-47), los renombró. No hay tags, releases ni configuración de despliegue en el repo; ninguna migración los crea; la base local no los tiene; y confirmaste que esa versión no llegó a staging ni a producción |
| Imágenes de las factories | Pasan a ser opcionales: `UserFactory::withImages()` y `PostFactory::withImage(int $chancePercent = 100)` | `createUser()` adjuntaba imágenes reales al 50 % de los usuarios y `createPost()` al 60 % de los posts, en el disco público real. Eso hacía intermitente `UserImageUpdateTest` y encarecía cada test. Los seeders mantienen la misma proporción |
| Ejecución de la suite | `composer test` corre en paralelo | Una base MySQL por proceso. Hubo que quitar 22 `use` de clases globales en 18 archivos sin namespace, que PHP ignoraba con un aviso y paratest convierte en error |
| Criterio de consolidación | Solo se elimina un caso cuando otro detecta exactamente el mismo fallo, y se cita cuál. No se fusionan comportamientos distintos | Regla "Keep backend tests focused" de `.ai/rules/backend.md`. Las capas de defensa de SOC-45 y los límites de seguridad se conservan |
| Código OTP "equivocado" | `invalidOtpFor($user)` en lugar de `'000000'` fijo | `'000000'` podía ser el código válido de ese momento (1 en un millón por test). El helper evita toda la ventana de verificación de Fortify |

### Implementación

- **Auditoría:** se revisaron las 16 suites registradas en `phpunit.xml`. Hay cuatro informes en `.ulpi/plans/soc-59-…/` (Admin, contenido, Auth/User y ActivityLog/globales); aprobaste todos los hallazgos.
- **Consolidación:** 59 casos eliminados, cada uno con el test que conserva su cobertura citado en el informe. Además, 4 casos movidos a su módulo: la serialización de `User` a `app/User/Tests/Models/` y `PasswordConfirmationTest` a `app/Auth/Modules/Password/Tests/`.
- **Helpers compartidos en `tests/Pest.php`:**
  - `withTemporarySeedingImages()`
  - `grantAdminPermissionThroughTestRole()`
  - `storeBackupArchive()`
  - `createAdminInvitationWithToken()`
  - `invalidOtpFor()`

  Reemplazan 7 copias locales.
- **Arreglos de cobertura que salieron de la auditoría:**
  - El test del `status` por defecto ahora prueba el `default('active')` de la columna. Antes usaba la factory, que ya da el valor; se comprobó que falla sin el default.
  - Se quitaron las llamadas a `clearMediaCollection()` que ya no hacían nada.
  - Los tests que corren `UserSeeder` y `PostSeeder` usan `Storage::fake('public')`, así que ya no copian imágenes al disco real.
  - Ningún test envía ya un código fijo en nombre de un usuario con secreto TOTP.
- **Documentación:**
  - `CLAUDE.md`: ejecución en paralelo, helpers, imágenes opcionales de las factories y `invalidOtpFor()`.
  - `docs/development/backend-commands.md`: el comando `composer test` y cómo depurar.
  - `README.md`.

### Duración

| Medición | Antes | Después |
| --- | --- | --- |
| Tests | 1209 (1 fallo intermitente) | 1152 (0 fallos) |
| Suite en serie, mismo comando | 74,0 s | 52,5–53,1 s (−29 %) |
| `composer test` (paralelo, 14 núcleos) | 74,0 s (en serie) | 21,0–21,3 s (−71 %) |

| Suite | Tests antes → después | Segundos antes → después (serie) |
| --- | --- | --- |
| Admin | 306 → 285 | 21,0 → 15,5 |
| Comment | 220 → 201 | 16,7 → 7,8 |
| TrustedDevice | 220 → 208 | 14,1 → 11,4 |
| ActivityLog | 145 → 144 | 5,2 → 4,4 |
| Post | 69 → 68 | 4,5 → 3,6 |
| Like | 39 → 38 | 3,6 → 2,1 |
| AccountStatus | 39 → 37 | 3,3 → 2,5 |

La mayor parte de la mejora en serie viene de quitar las imágenes reales de las factories. Eliminar los 59 casos ahorra unos 3–4 s.

### Criterios cumplidos

- [x] La decisión sobre los nombres heredados está respaldada por evidencia de su origen y de los entornos soportados.
- [x] El seeder ya no contiene compatibilidad innecesaria.
- [x] La auditoría cubre las 16 suites registradas, no solo Admin.
- [x] Cada test eliminado o consolidado cita el test que conserva su cobertura.
- [x] Se conservaron los límites de seguridad y los comportamientos que pueden fallar por separado.
- [x] Pasan las suites afectadas y la suite completa (1152/1152, en serie y en paralelo). Pint, PHPStan, Rector, Prettier, ESLint (sin errores), `tsc` y `composer doctor` están en verde.

### Pendiente

- PR: _(enlace cuando se abra)_.
- El primer run de CI confirmará que el servicio MySQL crea las bases por proceso de la ejecución en paralelo.
- `storage/app/public` ocupa 30 GB en local, por las imágenes que los tests copiaban antes de este cambio. No se borró nada.
