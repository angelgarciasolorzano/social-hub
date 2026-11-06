# Restoring a database backup

This procedure restores a SOC-55 backup into a temporary, isolated MySQL instance. Never restore the dump into production and never use production credentials to connect to the target.

## Requirements

- Access to the Admin panel and the `admin.backups.download` permission.
- The `BACKUP_ARCHIVE_PASSWORD` passphrase of the environment that produced the archive, available in the approved secrets manager.
- A `mysql` client compatible with the target server's version.
- A temporary scratch MySQL instance, separate from the source server and bound to loopback only, with an empty database.
- A ZIP extractor that supports ZIP AES-256, such as The Unarchiver or BetterZip. macOS's Archive Utility cannot extract these files, according to [Spatie Backup v10's encryption documentation](https://spatie.be/docs/laravel-backup/v10/advanced-usage/encrypt-backup-archives).

The current dump includes `SQL_LOG_BIN` and `GTID_PURGED` statements that need server privileges. To import it, use an administrator account of the disposable scratch instance; an account limited to a single database cannot run those statements. Never use credentials of the source server or of production. The temporary instance must be isolated and hold no other application databases.

The S3 disk is private. After the Admin user confirms their current password, the panel generates a signed download URL valid for five minutes.

## Download and decrypt

1. Open `/admin/backups`, find the backup by date and use **Download**. Confirm the Admin user's current password. Let the browser download the file; do not copy the signed URL.
2. Move the file to a private working directory on a machine with encrypted storage. Restrict read permissions to the operator who will run the restore.
3. Open the ZIP with an AES-256-capable extractor. When asked, type the `BACKUP_ARCHIVE_PASSWORD` passphrase taken from the secrets manager.
4. Confirm the extraction finished correctly. With the current configuration the backup holds the dump at `db-dumps/mysql-<database-name>.sql`: use the `.sql` file found in `db-dumps`.

Spatie Backup 10 encrypts each file while it builds the ZIP. This project backs up MySQL only and `database_dump_compressor` is disabled, so the current dump is uncompressed SQL inside the encrypted ZIP. The name follows `database_dump_filename_base` and `database_dump_file_extension`; check them again if they change before following this procedure. See [Spatie Backup v10's dump configuration](https://spatie.be/docs/laravel-backup/v10/installation-and-setup) if the name or the compression differ.

Never write the passphrase in a terminal argument, temporary file, ticket or log. Do not record or share the signed URL. Treat the ZIP and the extracted dump as sensitive data.

## Restore into the scratch MySQL instance

Before importing, confirm the scratch instance's host and port. It must be a dedicated instance with no connection to production, and the target database must be empty. The account is the scratch instance's administrator described in the requirements, which exists only there.

The example uses `127.0.0.1:3307` and the database `social_hub_restore`; replace them with the verified details of your scratch instance:

```bash
mysql \
  --host=127.0.0.1 \
  --port=3307 \
  --user=scratch_admin \
  --password \
  --database=social_hub_restore \
  < "/private/extracted/path/db-dumps/mysql-<database-name>.sql"
```

`mysql` prompts for the password because `--password` has no value. Do not type the password after the option: MySQL warns that putting it on the command line can expose it to other processes. The connection must point to the verified scratch server, and the input file must be the extracted dump, never a production path. The `scratch_admin` account must exist only in the temporary instance. See the [MySQL connection guide](https://dev.mysql.com/doc/refman/8.4/en/connecting.html).

If the client returns an error, stop the procedure and keep the original file to investigate. Do not import the dump partially into another database as a way to recover.

## Verify and clean up

With the same scratch host, port and user options, check the schema and query a known marker from the backup:

```bash
mysql \
  --host=127.0.0.1 \
  --port=3307 \
  --user=scratch_admin \
  --password \
  --database=social_hub_restore \
  --execute='SHOW TABLES;'
```

Also run a read-only query that checks the marker or known record agreed for that backup. In a drill, create the marker in the test environment before producing the backup; never change real data to add it.

When you finish, close the scratch session and delete the ZIP and the extracted dump following the team's data-handling policy. Never upload files, outputs with data or screenshots of the process to tickets or logs. The restore drill and its result are documented separately in TASK-016.

## Result of the TASK-016 drill

- **Date:** 2026-10-22.
- **Environment:** local, with an S3 emulator and a temporary, isolated scratch MySQL instance.
- **Result:** successful. Without the passphrase the ZIP could not be extracted; with the configured passphrase the dump was extracted and restored. The schema and a known marker migration matched the source database, which stayed unchanged. The scratch instance and the temporary files were removed.
