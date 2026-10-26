## Resumen de SOC-62

### Contexto

Para enviar las imágenes de MediaLibrary a un S3 local (MiniStack) solo existía el disco general `s3`, cuyo bucket es `AWS_BUCKET`: cualquier otra función que usara ese disco habría guardado en el bucket de imágenes. El disco `backups` repetía además todas las credenciales con `BACKUP_S3_*`, aunque en local solo cambia el bucket.

### Decisiones

| Tema | Decisión | Motivo |
| --- | --- | --- |
| Imágenes | Disco `media` propio (S3, público) con `MEDIA_S3_BUCKET` y `MEDIA_S3_URL`; se activa con `MEDIA_DISK=media` | El bucket de imágenes deja de depender de `AWS_BUCKET` |
| Credenciales y conexión | `AWS_*` son los valores comunes; `media` y `backups` los sobrescriben con sus `MEDIA_S3_*` / `BACKUP_S3_*` si existen (`env('BACKUP_S3_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID'))`) | Un solo juego de credenciales en local; en producción los backups pueden usar una identidad IAM propia, y las imágenes otro proveedor (por ejemplo R2), sin cambiar código |
| `.env.example` | Las `MEDIA_S3_*` y `BACKUP_S3_*` de credenciales valen `"${AWS_...}"` | Propuesta tuya: se ve de dónde salen los valores y evita la trampa de dejarlas vacías, que anula el respaldo. Comprobado con `Dotenv::parse` |
| Respaldo en `config/` | Se mantiene aunque `.env.example` interpole | Las variables que vienen de la plataforma (Cloud, Forge, CI) no se interpolan |
| `BACKUP_S3_URL` | Eliminada | Las descargas son URLs firmadas (`temporaryUrl()`) que salen del endpoint; la `url` del disco no se leía en ningún sitio |

### Implementación

- `config/filesystems.php`: disco `media`, y respaldo a `AWS_*` en el disco `backups` (sigue siendo privado, con `throw` y `report`).
- Tests:
  - `tests/Feature/MediaLibrary/MediaDiskConfigurationTest.php`, con 6 casos.
  - `BackupConfigurationTest`, con 4 casos nuevos: respaldo, prioridad y bucket propio. Se comprobó que el test de respaldo falla sin el cambio.
  - Helpers `configWithEnvironment()` y `filesystemDiskWithEnvironment()` en `tests/Pest.php`.
- Documentación: `.env.example`, `README.md` (imágenes en S3 local con LocalStack o MiniStack, y backups) y `CLAUDE.md`.
- Hallazgo: dos reglas de rector-laravel reescriben las lecturas de `$_ENV[...]` y `$_SERVER[...]`, incluso dentro de `unset()`, y generan PHP inválido. Los helpers trabajan con las superglobales completas, y quedó documentado.

### Criterios cumplidos

- [x] Con `MEDIA_DISK=media`, las imágenes usan `MEDIA_S3_BUCKET` y `MEDIA_S3_URL`, sin depender de `AWS_BUCKET`.
- [x] Sin `BACKUP_S3_*` de credenciales, el disco `backups` usa `AWS_*`; si se definen, tienen prioridad.
- [x] Los tests siguen en el disco `public` (`.env.testing` no define `MEDIA_DISK`) y hay tests para el disco `media` y para el respaldo.
- [x] Documentación sincronizada: `.env.example`, `README.md` y `CLAUDE.md`.
- [x] Pint, PHPStan, Rector y `composer doctor` en verde; `composer test` pasa con 1174/1174 en paralelo.

### Para usarlo en local

```dotenv
AWS_ENDPOINT=http://localhost:4566
AWS_USE_PATH_STYLE_ENDPOINT=true
MEDIA_DISK=media
MEDIA_S3_BUCKET=social-hub-local-media
MEDIA_S3_URL=http://localhost:4566/social-hub-local-media
BACKUP_S3_BUCKET=social-hub-local-backups
```

Después, `php artisan migrate:fresh --seed` para que las imágenes se suban al bucket.

PR: _(enlace cuando se abra)_
