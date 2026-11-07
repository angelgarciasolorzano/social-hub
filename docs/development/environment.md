# Environment and services

Environment variables and the services behind them. Everything is defined in `.env`, created from `.env.example` by `composer run setup`; tests use `.env.testing` (from `.env.testing.example`).

> ⚠️ **Keeping this document current**: when you add, rename or change the default of an environment variable, or change how a service is configured, update this document and `.env.example` in the same PR/commit.

## Database, session, queue and cache

- Development uses MySQL (`DB_CONNECTION=mysql`) and the `database` driver for `SESSION_DRIVER`, `QUEUE_CONNECTION` and `CACHE_STORE`. MySQL must be running locally to use the app.
- `composer run setup` creates the application database configured by `.env` (default `laravel`) and the testing database configured by `.env.testing`; the MySQL account needs permission to create databases.
- Tests use MySQL (see `.env.testing`: `root` without password on `127.0.0.1:3306`, database `social-hub-testing`). `composer test` runs in parallel and creates one database per worker (`social-hub-testing_test_N`), so the MySQL user must be able to create databases.
- Sample data: `php artisan migrate:fresh --seed`. The local test account is `angel@gmail.com` with the password `password` (no 2FA, so the login asks for no OTP).

## Broadcasting (Laravel Reverb)

`.env.example` sets `BROADCAST_CONNECTION=reverb` and `QUEUE_CONNECTION=database`, and `composer run dev` starts Reverb and the queue listener.

- **Server:** `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET` are the Reverb application's credentials; `REVERB_HOST`, `REVERB_PORT` (`8080`) and `REVERB_SCHEME` tell Laravel where to publish. The example values are for local development: change them if the server is reachable from outside your machine, and never expose `REVERB_APP_SECRET` to the frontend.
- **Client:** `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT` and `VITE_REVERB_SCHEME` are derived from the `REVERB_*` values; Vite bakes them into the bundle, so restart Vite or rebuild after changing them.
- If `php artisan config:show broadcasting.default` prints `log`, events only go to the log and never reach Echo. Debugging steps are in [backend-commands.md](backend-commands.md#local-broadcasting-with-laravel-reverb).

## Images (MediaLibrary) and S3

Profile pictures, cover images, post images and announcement images go to the disk named by `MEDIA_DISK` (`public` by default, in `storage/app/public`).

- With `MEDIA_DISK=media` they go to their own S3 bucket: `MEDIA_S3_BUCKET` is the bucket and `MEDIA_S3_URL` the base of the URLs the browser opens.
- The credentials and connection (`MEDIA_S3_ACCESS_KEY_ID`, `MEDIA_S3_SECRET_ACCESS_KEY`, `MEDIA_S3_DEFAULT_REGION`, `MEDIA_S3_ENDPOINT`, `MEDIA_S3_USE_PATH_STYLE_ENDPOINT`) reuse the `AWS_*` ones: `.env.example` sets them to `"${AWS_...}"`, so change them only when images live with another provider or account.
- For a local S3 emulator (LocalStack, MiniStack) use `AWS_ENDPOINT=http://localhost:4566`, `AWS_USE_PATH_STYLE_ENDPOINT=true` and `MEDIA_S3_URL=http://localhost:4566/<bucket>`, create the bucket before seeding, and run `php artisan migrate:fresh --seed` again so the images are uploaded.
- `AWS_BUCKET` and `AWS_URL` only affect the general `s3` disk, which the application does not use. Tests are not affected: `.env.testing` defines no `MEDIA_DISK`.

How the disks are wired is described in [infrastructure.md](../architecture/infrastructure.md#media-uploads-and-storage-disks).

## Trusted devices

- `SOCIALHUB_AUTH_TRUSTED_DEVICE_COOKIE_LIFETIME_MINUTES` — how long a trusted device's cookie lasts (default `43200`, 30 days).
- `SOCIALHUB_AUTH_TRUSTED_DEVICE_PURGE_AFTER_DAYS` — how many days revoked devices are kept before `trusted-devices:purge` deletes them for good (default `90`).

## Activity log retention

`SOCIALHUB_ACTIVITY_LOG_<LOG>_RETENTION_DAYS` sets the days each log is kept before `activity-log:purge` deletes it (defaults: auth 180, content 365, likes 30, trusted_devices 180, account 365, admin 730). See [activity-log.md](../architecture/modules/activity-log.md).

## Database backups

The application creates encrypted backups of the MySQL database on the private `backups` disk, with its own bucket (`BACKUP_S3_BUCKET`). The panel side is described in [admin.md](../architecture/modules/admin.md#database-backups-soc-55) and the restore procedure in [database-backup-restore.md](database-backup-restore.md).

### Variables

- The credentials and connection (`BACKUP_S3_ACCESS_KEY_ID`, `BACKUP_S3_SECRET_ACCESS_KEY`, `BACKUP_S3_DEFAULT_REGION`, `BACKUP_S3_ENDPOINT`, `BACKUP_S3_USE_PATH_STYLE_ENDPOINT`) reuse the `AWS_*` ones: `.env.example` sets them to `"${AWS_...}"`, which phpdotenv resolves because the `AWS` block comes first, so locally you only need `BACKUP_S3_BUCKET`.
- In production, replace `BACKUP_S3_ACCESS_KEY_ID` and `BACKUP_S3_SECRET_ACCESS_KEY` with a dedicated IAM identity limited to that bucket, so the application's credential cannot read or delete the backups.
- When the variables come from the platform (Laravel Cloud, Forge, CI secrets) instead of a `.env` file there is no interpolation: undefined `BACKUP_S3_*` variables still fall back to `AWS_*` through `config/filesystems.php`. Never define them empty (`BACKUP_S3_ACCESS_KEY_ID=`): an empty value defeats that fallback.
- `BACKUP_ARCHIVE_PASSWORD` is required to run the job and must be stored as a deployment secret. Set `BACKUP_NOTIFICATION_EMAIL` to a valid address to receive alerts. Never keep credentials or passphrases in the repository or expose them through `VITE_*` variables.

### Runtime requirements

- The host that processes the jobs needs a `mysqldump` client compatible with the configured MySQL version.
- Backups are queued and need a worker on an asynchronous queue connection, for example `php artisan queue:work`; never use `QUEUE_CONNECTION=sync` in production.
- The Laravel scheduler must run every minute through cron (`php artisan schedule:run`) or a persistent process (`php artisan schedule:work`). The backup runs daily at 01:00 and the monitor at 04:30, in the configured timezone; the cleanup is queued after each successful backup.
- The built-in monitor checks the age and storage of the backups at the destination. It runs inside the same application, so it cannot detect a full outage of the deployment by itself: that needs external monitoring.

### Bucket and IAM policy

- The bucket must be private and versioned. Limit the IAM policy to a dedicated bucket and the backups prefix: `s3:ListBucket` on the bucket ARN with an `s3:prefix` condition; `s3:PutObject`, `s3:GetObject` and `s3:DeleteObject` on the objects under that prefix. `GetObject` allows downloads; `DeleteObject` lets the cleanup remove expired backups.
- In a versioned bucket `DeleteObject` adds a delete marker and does not remove the previous version right away. Configure S3 Lifecycle to delete noncurrent versions after 30 days and to remove expired delete markers once they have no versions left. Lifecycle owns version deletion, so the application's identity does not need `s3:DeleteObjectVersion`. See [the Lifecycle guide for versioned buckets](https://docs.aws.amazon.com/AmazonS3/latest/userguide/lifecycle-configuration-examples.html) and [the example IAM policy limited by prefix](https://docs.aws.amazon.com/AmazonS3/latest/userguide/walkthrough1.html).
