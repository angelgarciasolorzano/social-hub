# Restaurar un backup de base de datos

Este procedimiento restaura un backup de SOC-55 en una instancia MySQL temporal y aislada. No restaures el dump en producción ni uses credenciales de producción para conectarte al destino.

## Requisitos

- Acceso al panel Admin y permiso `admin.backups.download`.
- La passphrase `BACKUP_ARCHIVE_PASSWORD` del entorno que generó el archivo, disponible en el gestor de secretos aprobado.
- Un cliente `mysql` compatible con la versión del servidor de destino.
- Una instancia MySQL scratch temporal, separada del servidor fuente y ligada únicamente a loopback, con una base vacía.
- Un extractor ZIP que soporte ZIP AES-256, como The Unarchiver o BetterZip. La utilidad Archive de macOS no puede extraer estos archivos según la [documentación de cifrado de Spatie Backup v10](https://spatie.be/docs/laravel-backup/v10/advanced-usage/encrypt-backup-archives).

El dump actual incluye instrucciones `SQL_LOG_BIN` y `GTID_PURGED` que requieren privilegios de servidor. Para importarlo, usa una cuenta administradora de la instancia scratch desechable; una cuenta limitada a una sola base no puede ejecutar esas instrucciones. Nunca uses credenciales del servidor fuente ni producción. La instancia temporal debe estar aislada y no contener otras bases de datos de la aplicación.

El disco S3 es privado. El panel genera una URL firmada de descarga por cinco minutos después de confirmar la contraseña actual del usuario Admin.

## Descargar y descifrar

1. Abre `/admin/backups`, localiza el backup por fecha y usa **Download**. Confirma la contraseña actual del usuario Admin. Deja que el navegador descargue el archivo; no copies la URL firmada.
2. Mueve el archivo a un directorio de trabajo privado en un equipo con almacenamiento cifrado. Restringe los permisos de lectura al operador que ejecutará la restauración.
3. Abre el ZIP con un extractor compatible con AES-256. Cuando lo solicite, introduce manualmente la passphrase `BACKUP_ARCHIVE_PASSWORD` obtenida del gestor de secretos.
4. Confirma que la extracción terminó correctamente. Con la configuración actual, el backup contiene el dump en `db-dumps/mysql-<nombre-de-la-base>.sql`: usa el archivo `.sql` encontrado dentro de `db-dumps`.

Spatie Backup 10 cifra cada archivo mientras construye el ZIP. En este proyecto se respalda solo MySQL y `database_dump_compressor` está desactivado, así que el dump actual es SQL sin compresión dentro del ZIP cifrado. El nombre sigue la configuración de `database_dump_filename_base` y `database_dump_file_extension`; vuelve a revisarlas si cambian antes de seguir este procedimiento. Consulta [la configuración de dumps de Spatie Backup v10](https://spatie.be/docs/laravel-backup/v10/installation-and-setup) si el nombre o la compresión difieren.

No escribas la passphrase en un argumento de terminal, archivo temporal, ticket o log. No registres ni compartas la URL firmada. Trata el ZIP y el dump extraído como datos sensibles.

## Restaurar en MySQL scratch

Antes de importar, confirma el host y puerto de la instancia scratch. Debe ser una instancia dedicada, sin conexión a producción. La base debe estar vacía y el usuario debe tener permisos solo sobre esa base.

El ejemplo usa `127.0.0.1:3307` y la base `social_hub_restore`; reemplázalos por los datos verificados de tu instancia scratch:

```bash
mysql \
  --host=127.0.0.1 \
  --port=3307 \
  --user=scratch_admin \
  --password \
  --database=social_hub_restore \
  < "/ruta/privada/extraida/db-dumps/mysql-<nombre-de-la-base>.sql"
```

`mysql` solicita la contraseña porque `--password` no lleva un valor. No escribas la contraseña después de la opción: MySQL advierte que incluirla en la línea de comandos puede exponerla a otros procesos. La conexión debe apuntar al servidor scratch comprobado, y el archivo de entrada debe ser el dump extraído, nunca una ruta en producción. La cuenta `scratch_admin` debe existir solo en la instancia temporal. Consulta la [guía de conexión de MySQL](https://dev.mysql.com/doc/refman/8.4/en/connecting.html).

Si el cliente devuelve un error, detén el procedimiento y conserva el archivo original para investigar. No importes parcialmente el dump en otra base como forma de recuperación.

## Verificar y limpiar

Con las mismas opciones de host, puerto y usuario scratch, verifica el esquema y consulta un dato marcador conocido del respaldo:

```bash
mysql \
  --host=127.0.0.1 \
  --port=3307 \
  --user=scratch_admin \
  --password \
  --database=social_hub_restore \
  --execute='SHOW TABLES;'
```

Ejecuta además una consulta de solo lectura para comprobar el marcador o registro conocido acordado para ese backup. En un simulacro, crea el marcador en el entorno de prueba antes de generar el respaldo; no modifiques datos reales para añadirlo.

Al terminar, cierra la sesión scratch y elimina el ZIP y el dump extraído según la política de manejo de datos del equipo. No subas archivos, salidas con datos ni capturas del proceso a tickets o logs. El simulacro de restauración y su resultado se documentan por separado en TASK-016.

## Resultado del simulacro TASK-016

- **Fecha:** 2026-10-22.
- **Entorno:** local, con simulador S3 y una instancia MySQL scratch temporal aislada.
- **Resultado:** exitoso. Sin la passphrase, el ZIP no pudo extraerse; con la passphrase configurada, el dump se extrajo y restauró. El esquema y una migración marcador conocida coincidieron con la base fuente, que permaneció sin cambios. Se retiraron la instancia scratch y los archivos temporales.
