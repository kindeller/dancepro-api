# Deployment

This document describes the deployment process for the DancePro API.

The project is developed locally using Docker/Sail and deployed to a Linux production server running PHP 8.3+ with Laravel 13.

The production server retains its own environment configuration. Sensitive values such as application keys, database credentials and AWS credentials are **never committed to the repository**.

---

# Deployment Workflow

The intended deployment workflow is:

1. Develop and test locally using Docker/Sail.
2. Commit changes to a feature branch.
3. Push to GitHub.
4. Open a Pull Request.
5. Automated tests are executed.
6. Merge into `main`.
7. Deploy the latest version to the production EC2 instance.
8. Perform a production smoke test.

Production deployments should always originate from the `main` branch.

---

# Production Environment

The production server should provide:

- PHP 8.3+
- Composer
- Node.js 20.19+ or 22.12+
- npm 10+
- Apache
- Git
- MySQL
- Required PHP extensions
- Laravel writable directories
- Production `.env` configuration

The production `.env` file is maintained only on the server.

It must never be committed to Git.

---

# Deployment Steps

After updating the application code:

```bash
composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

npm ci --include=dev --no-audit --no-fund
npm run build

php artisan migrate --force

php artisan operations:secure-documents
php artisan operations:secure-documents --apply --force

php artisan optimize:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The generated `public/build` directory is intentionally not committed. Every
deployment rebuilds it from `package-lock.json`, removes any stale Vite hot-file,
and stops before reopening the application unless the manifest contains the
concert-player entry point.

If queue workers are used:

```bash
php artisan queue:restart
```

---

# File Permissions

Apache must be able to write to:

```text
storage/
bootstrap/cache/
```

Typical ownership:

```bash
sudo chown -R ec2-user:apache storage bootstrap/cache
```

Typical permissions:

```bash
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

Never use `chmod -R 777`.

---

# Environment Changes

Whenever the production `.env` file changes:

```bash
php artisan optimize:clear
php artisan config:cache
```

Do not rely on `env()` values during runtime.

Laravel should access configuration using the `config()` helper.

---

# AWS Configuration

The production environment requires valid AWS credentials.

Competition storage uses the `s3_competitions` filesystem disk. Concert media
uses `s3_concerts`, while unmigrated V1 concert media may use
`s3_concerts_legacy`. Production credentials should be scoped to the required
bucket and operations for each domain.

The following values must be configured:

```text
AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY
AWS_DEFAULT_REGION

AWS_COMPETITIONS_ACCESS_KEY_ID
AWS_COMPETITIONS_SECRET_ACCESS_KEY
AWS_COMPETITIONS_DEFAULT_REGION
AWS_COMPETITIONS_BUCKET

AWS_CONCERT_ACCESS_KEY_ID
AWS_CONCERT_SECRET_ACCESS_KEY
AWS_CONCERT_DEFAULT_REGION
AWS_CONCERT_BUCKET

AWS_CONCERT_LEGACY_ACCESS_KEY_ID
AWS_CONCERT_LEGACY_SECRET_ACCESS_KEY
AWS_CONCERT_LEGACY_DEFAULT_REGION
AWS_CONCERT_LEGACY_BUCKET

CONCERT_PLAYBACK_SIGNED_URL_TTL_MINUTES
CLOUDFRONT_CONCERT_DOMAIN
CLOUDFRONT_CONCERT_KEY_PAIR_ID
CLOUDFRONT_CONCERT_PRIVATE_KEY_PATH
CLOUDFRONT_CONCERT_COOKIE_DOMAIN

DOWNLOAD_ALLOWED_DISKS
DOWNLOAD_DEFAULT_DISK
DOWNLOAD_SIGNED_URL_TTL_MINUTES
DOWNLOAD_CLOUDFRONT_DOMAIN
DOWNLOAD_CLOUDFRONT_KEY_PAIR_ID
DOWNLOAD_CLOUDFRONT_PRIVATE_KEY or DOWNLOAD_CLOUDFRONT_PRIVATE_KEY_PATH
OPERATIONS_FILESYSTEM_DISK
```

`OPERATIONS_FILESYSTEM_DISK` must use a private filesystem disk. The default
`local` disk stores files under `storage/app/private`; do not set it to
`public`. The deployment command first inventories existing operational files,
then moves verified copies of venue maps, run sheets, crew resources and event
attachments out of `storage/app/public`. Event logos, venue reference images
and concert media are not changed by this command.

When CloudFront signing is enabled, production also requires the configured
distribution domain, key-pair ID and private key or readable private-key path
described in [AWS](AWS.md). Private-key contents must remain outside source
control. The standard file-based configuration uses
`CLOUDFRONT_CONCERT_PRIVATE_KEY_PATH=app/private/keys/dancepro-concerts-private.pem`;
Laravel resolves this relative to `storage_path()`. The deployment process must
place the key at that runtime location without adding it to the deployment
artifact or Git repository.

The configured region should always resolve to:

```text
ap-southeast-2
```

A missing region will prevent Laravel from constructing the AWS S3 client.

---

# Deployment Validation

Before placing a production instance online, run:

```bash
php artisan production:validate
```

The command exits unsuccessfully when a critical production setting is unsafe
or missing. The deployment script runs it after installing the new code and
clearing stale caches, before migrations or reopening the site.

The production `.env` must provide all of the following:

- `APP_ENV=production`, `APP_DEBUG=false`, an application key and the public
  HTTPS `APP_URL`.
- Encrypted, HTTP-only, HTTPS-only sessions stored in a shared persistent
  backend such as the database or Redis.
- Enabled and enforced two-factor authentication for staff accounts.
- A production database, persistent cache and asynchronous queue connection.
- Automated database backups enabled with a positive retention period and a
  working `mysqldump`-compatible executable.
- `DEPLOY_HEALTHCHECK_URL` set to the production HTTPS `/up` URL on the same
  host as `APP_URL`.
- A real outbound mail transport and sender address.
- A non-debug production log channel.
- Valid configured disks for private operational files and directory logos.

Local filesystem disks are valid only when the server's `storage` directory is
on durable backed-up storage. Moving uploads to shared object storage is tracked
separately and should be completed before horizontal scaling.

Download CloudFront signer values are optional until that delivery path is
enabled. If any signer value is supplied, validation requires the distribution
domain, key-pair ID, and either the private key or its runtime path. The command
checks configuration only; it does not read, display or modify media or signing
keys.

## Database backups and restoration

Every live deployment runs `php artisan database:backup --prune` after entering
maintenance mode and before reviewing or running migrations. The command uses
the configured MySQL/MariaDB connection, passes its password to the dump process
through the child-process environment rather than command-line arguments, and
creates a private gzip archive plus SHA-256 manifest beneath
`storage/app/private/backups/database`. A failed dump, compression, or integrity
check stops deployment and removes the incomplete artifact.

Set `DATABASE_BACKUP_ENABLED=true` in production. The server must provide the
configured `DATABASE_BACKUP_DUMP_BINARY` (normally `mysqldump`), and the backup
directory must itself be included in encrypted off-server backups. Retention
only removes expired files matching the command's managed `dancepro-*.sql.gz`
name; the default is 30 days.

An operator can recheck an archive without exposing its contents:

```bash
php artisan database:backup-verify storage/app/private/backups/database/dancepro-YYYY-MM-DD_HH-MM-SS-ID.sql.gz
```

At least quarterly, restore the newest archive into an isolated non-production
MySQL instance, run migrations in status-only mode, and record the result and
duration. Never test restoration over the live database. A Git rollback does
not reverse migrations, so the verified pre-deployment archive is the database
recovery point.

Following deployment, also inspect:

```bash
php artisan about
```

Immediately before reopening the application, deployment runs
`production:check-dependencies`. This performs a database query, cache
write/read/delete cycle, private-storage write/read/delete probe, mail transport
construction, and queue backend query. No email or job is sent. A failed check
leaves the application in maintenance mode.

After reopening, `curl` is restricted to HTTPS, does not follow redirects, and
must receive status 200 with the exact JSON body `{"status":"up"}` from `/up`.
A login page, redirect, wrong virtual host, or generic successful page cannot
pass this check.

Also confirm `public/build/manifest.json` exists and that the public concert
player loads without a missing-manifest or missing-asset error.

Verify:

- Production environment
- Debug disabled
- Correct database connection
- Correct filesystem configuration

Check both storage disks:

```bash
php artisan tinker
```

```php
config('filesystems.disks.s3_competitions.region');
config('filesystems.disks.s3_concerts.region');
config('filesystems.disks.s3_concerts_legacy.region');

Storage::disk('s3_competitions')->directories('');
Storage::disk('s3_concerts')->directories('');
Storage::disk('s3_concerts_legacy')->directories('');
```

The regions should resolve correctly and both configured buckets should be
accessible with their production credentials.

When CloudFront concert delivery is configured, also verify that Laravel can
generate short-lived playback cookies and a short-lived attachment URL without
logging or displaying private signing material.

---

# Smoke Tests

Following deployment, verify:

- Application loads.
- Authentication succeeds.
- Competition objects are listed.
- Download links are generated.
- Downloads complete successfully.
- Public studios and available concerts are listed.
- Password-protected concert access succeeds and failed attempts are handled.
- A concert playlist loads and advances to the next video.
- Desktop and mobile playback can seek when using the production media path.
- The player handles an unavailable or expired media URL safely.
- Concert originals use tracking links and download as attachments.
- Disabled, unavailable or unapproved concerts remain inaccessible.

---

# Automated Deployment

The long-term deployment strategy is:

```
Developer
        │
        ▼
Feature Branch
        │
        ▼
Pull Request
        │
        ▼
GitHub Actions
        │
        ▼
Run Tests
        │
        ▼
Merge to main
        │
        ▼
Deploy to EC2
        │
        ▼
Production Smoke Tests
```

GitHub Actions should eventually perform the deployment automatically by connecting to the EC2 instance and executing the production deployment script.

The production `.env` file should remain on the EC2 instance and should not be recreated during deployment.

---

## Related Documentation

- [Development Environment](Development-Environment.md)
- [Git Workflow](Git-Workflow.md)
- [Security](Security.md)
- [Testing](Testing.md)
- [AWS](AWS.md)
