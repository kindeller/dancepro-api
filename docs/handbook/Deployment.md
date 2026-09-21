# Deployment

This document describes the deployment process for the DancePro API.

The project is developed locally using Docker/Sail and deployed to a Linux
production server running PHP 8.3+ with Laravel 13. These are minimum PHP and
framework requirements, not a claim that local Sail and production run the same
PHP patch version. Verify the actual versions with `php -v` and
`php artisan --version` on each environment before deploying.

The production server retains its own environment configuration. Sensitive values such as application keys, database credentials and AWS credentials are **never committed to the repository**.

---

# Deployment Workflow

The intended deployment workflow is:

1. Develop and test locally using Docker/Sail.
2. Commit changes to a feature branch.
3. Push to GitHub.
4. Open and review a Pull Request; run the required Sail checks locally.
5. Merge into `master`.
6. Create and verify a production database backup on EC2.
7. Run `dancepro-deploy --dry-run` and review the proposed changes.
8. Run `dancepro-deploy` on EC2.
9. Perform a production smoke test.

The checked-in [deployment script](../../scripts/dancepro-deploy.sh) requires a
clean `master` checkout and deploys `origin/master`. The Git branch is independent
of PHP/Laravel versions. If the repository later adopts `main`, update and test
the script and this handbook together before changing the production branch.

---

# Production Environment

The production server should provide:

- PHP 8.3+
- Composer
- Node.js 20.19+ or 22.12+
- npm 10+
- Apache
- Git
- MySQL or MariaDB dump client
- AWS CLI
- Required PHP extensions
- Laravel writable directories
- Production `.env` configuration

The production `.env` file is maintained only on the server.

It must never be committed to Git.

The production `APP_KEY` must also have a separately controlled recovery copy,
and database restoration must be tested with that matching key. Follow
[Sensitive Data Retention and Key Recovery](Sensitive-Data-Retention-and-Key-Recovery.md)
before generating, recovering or rotating an encryption key.

---

# Pre-Deployment Database Backup

While the production MySQL/MariaDB database is hosted on the EC2 application
server, create a compressed logical backup before each deployment. The deployment
script does **not** create or upload this backup; its confirmation prompt only
reminds the operator that a verified backup should already exist. This is a
manual step until a separately tested backup automation is added.

Use the existing `dance-pro-db-backup` bucket, after confirming that its access
and encryption settings meet the production backup policy. The bucket name is
an identifier, not a credential. The EC2 instance needs permission to write a
new object there and read its metadata. Do not place AWS keys in these commands.
Uploads incur S3 storage and request charges; retain and restore-test backups
under the bucket's established retention policy.

## Check the database and tools

From `/var/www/dancepro-api` on the production EC2 instance:

```bash
php artisan about --only=environment
grep -E '^DB_(CONNECTION|HOST|PORT|DATABASE|USERNAME)=' .env
command -v mariadb-dump || command -v mysqldump
aws --version
```

Confirm `DB_CONNECTION` is `mysql` or `mariadb`, and that the dump client can
connect to the **same** database as Laravel. Do not display or paste
`DB_PASSWORD`. The commands below request the password interactively; obtain it
through the server's approved access method. If the server uses a socket or a
configured client option file instead of password authentication, adapt the
connection flags to match and verify the selected database before dumping.

## Create and check the local dump

Set the non-secret values from the production configuration. Keep the dump out
of the Git checkout and restrict its permissions:

```bash
set -o pipefail
umask 077
BACKUP_TIMESTAMP=$(date -u +'%Y-%m-%d_%H-%M-%S-%N')
BACKUP_FILE="/tmp/dancepro-db-${BACKUP_TIMESTAMP}.sql.gz"
test ! -e "$BACKUP_FILE" || { echo 'Backup file already exists; stop' >&2; exit 1; }
DB_HOST_VALUE='<DB_HOST>'
DB_PORT_VALUE='<DB_PORT>'
DB_USER_VALUE='<DB_USERNAME>'
DB_NAME_VALUE='<DB_DATABASE>'
```

Replace the four placeholders with the values shown above. If only `mysqldump`
is installed, substitute that command for `mariadb-dump`. Then run:

```bash
if mariadb-dump \
    --host="$DB_HOST_VALUE" \
    --port="$DB_PORT_VALUE" \
    --user="$DB_USER_VALUE" \
    --password \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    "$DB_NAME_VALUE" | gzip > "$BACKUP_FILE"; then
    echo 'Database dump completed'
else
    echo 'Database dump failed; stop the deployment' >&2
    exit 1
fi

test -s "$BACKUP_FILE" && gzip -t "$BACKUP_FILE"
stat -c 'Local backup size: %s bytes' "$BACKUP_FILE"
```

`set -o pipefail` makes a dump failure fail the pipeline even if `gzip` exits
successfully. `--single-transaction` gives a consistent snapshot for
transactional tables such as InnoDB; avoid schema changes during the dump and
review any nontransactional tables separately. The application can stay online
for this snapshot, but writes after the snapshot will not exist in a restore.
Stop if the dump, size check or gzip check fails. A small dump compared with
previous backups needs investigation before proceeding.

## Upload and verify the exact object

```bash
BACKUP_KEY=$(basename "$BACKUP_FILE")
aws s3 cp "$BACKUP_FILE" "s3://dance-pro-db-backup/$BACKUP_KEY" --no-progress
LOCAL_BYTES=$(stat -c %s "$BACKUP_FILE")
if S3_BYTES=$(aws s3api head-object \
    --bucket dance-pro-db-backup \
    --key "$BACKUP_KEY" \
    --query ContentLength \
    --output text) && \
    test "$LOCAL_BYTES" -gt 0 && test "$LOCAL_BYTES" = "$S3_BYTES"; then
    printf 'Verified backup: s3://dance-pro-db-backup/%s (%s bytes)\n' "$BACKUP_KEY" "$S3_BYTES"
else
    echo 'S3 backup verification failed; keep the local file and stop deployment' >&2
    exit 1
fi
```

`head-object` checks the exact key without requiring permission to list the
whole bucket. Record the key and `git rev-parse HEAD` in the deployment record.
The size check detects an incomplete upload; it does not prove that SQL will
restore successfully. Retain the local file if upload or verification fails and
stop the deployment. After successful verification, remove the temporary EC2
copy using `rm -f "$BACKUP_FILE"` and confirm it is gone with
`test ! -e "$BACKUP_FILE"`.

Run `dancepro-deploy --dry-run`, review the commits and migration diff, then run
`dancepro-deploy`. The dry run fetches remote Git metadata and writes a
deployment log; it leaves the application, database, checked-out code and
services unchanged. The live script creates a rollback tag, enters maintenance
mode, updates code, and prompts before running migrations. It leaves the site
in maintenance mode if deployment fails after that point.

Periodically restore a downloaded backup into an isolated nonproduction database
to test recoverability. A production restore is a separate recovery operation:
review the backup timestamp, application commit, migration state, and customer
data written since the dump before replacing any live database.

---

# Deployment Steps

For normal production updates, use `dancepro-deploy` after the verified backup
and dry run above. Its underlying operations include:

```bash
composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

npm ci --include=dev --no-audit --no-fund
rm -f public/hot
npm run build
npm prune --omit=dev --no-audit --no-fund
npm ls --omit=dev --depth=0

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
concert-player entry point. Composer always reconciles the production `vendor`
directory against `composer.lock`, even when the Composer files did not change,
so a missing or incomplete dependency directory cannot silently survive a
deployment. Frontend development dependencies are installed only for the Vite
build and pruned after the manifest is verified; runtime dependencies remain in
`node_modules`.

If queue workers are used:

```bash
php artisan queue:restart
```

The script does not currently restart queue workers, so handle this separately
when production workers exist. It also does not build Vite assets; confirm the
matching frontend build is present before exposing new concert-player changes.

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
PUBLIC_UPLOAD_DISK
AWS_PUBLIC_UPLOADS_BUCKET
AWS_PUBLIC_UPLOADS_URL
```

In production, `OPERATIONS_FILESYSTEM_DISK` must use durable private shared
storage and `PUBLIC_UPLOAD_DISK` must use durable public shared storage. The
included `s3` and `s3_public_uploads` disks satisfy those application checks;
local development continues to use `local` and `public`.
`AWS_PUBLIC_UPLOADS_URL` should be the public CDN/CloudFront base URL. The S3
bucket itself can remain private behind CloudFront origin access; uploads do
not request public object ACLs.

Before changing `PUBLIC_UPLOAD_DISK`, copy existing tracked logos and venue
reference images without deleting their source copies:

```bash
php artisan uploads:migrate-public --from=public
php artisan uploads:migrate-public --from=public --apply
```

Private crew profile photos also use `OPERATIONS_FILESYSTEM_DISK`. The
deployment command first inventories existing operational files,
then moves verified copies of venue maps, run sheets, crew resources and event
attachments out of `storage/app/public`. Event logos, venue reference images
and concert media are not changed by this command.

Database backups do not contain uploaded objects. Enable bucket versioning and
a separately managed backup or replication policy for both the public-upload
and private-operations buckets. Test restoring database and object storage to
the same point in time. Do not expire object versions until the documented
retention period has elapsed.

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

Validation also refuses to proceed while `public/hot` exists. That file tells
Laravel to load assets from a Vite development server and must be removed before
every production build. The standard deployment script already performs this
cleanup; manual deployments must do the same.

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
name; the default is 30 days. The application also schedules
`database:backup --prune` daily at `DATABASE_BACKUP_SCHEDULE_TIME` (02:00 by
default, in `APP_TIMEZONE`). It uses overlap and single-server locks, so the
production scheduler must run once per minute and the shared cache must support
atomic locks.

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
construction, and queue backend query. It also verifies list/read access to the
competition, current concert and legacy concert media disks, then parses the
configured concert CloudFront private key. Download CloudFront signing is
checked when configured. Storage media is not changed, and no email or job is
sent. A failed check leaves the application in maintenance mode.

After reopening, `curl` is restricted to HTTPS, does not follow redirects, and
must receive status 200 with the exact JSON body `{"status":"up"}` from `/up`.
A login page, redirect, wrong virtual host, or generic successful page cannot
pass this check.

Before DNS cutover, complete every activation and test step in
[Production Monitoring](Production-Monitoring.md). Monitoring remains an
external deployment responsibility until the hosting and alerting providers are
selected; a successful application deployment does not by itself prove alerts
will reach the owner.

The final production go/no-go decision must also complete the provider-neutral
[Production Launch Checklist](Production-Launch-Checklist.md). Keep provider
names, account references, alert destinations and test evidence in the private
deployment record rather than committing them to this repository.

Also confirm `public/build/manifest.json` exists and that the public concert
player loads without a missing-manifest or missing-asset error.

The concert player lazy-loads the HLS.js light build only when the browser does
not provide native HLS playback. Keep the generated HLS chunk below Vite's
500 kB warning threshold and retain the progressive MP4 fallback when changing
player dependencies.

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
Before publishing concert video, verify the CloudFront behavior requires signed
requests and that the S3 origin cannot be read directly. Test an unsigned MP4
CloudFront URL and its direct S3 URL; both must be denied. Test a signed MP4 URL
and byte-range seeking from an authorised concert session. Assets on the legacy
disk are not routed through the new concert distribution.

When the Flutter media API is deployed, use a synthetic asset to verify that an
active staff media token can reserve an asset and obtain prefix-constrained
upload requests, while customer and unrelated tokens receive `403`. Confirm
that uploaded checksums are validated, incomplete multipart upload can resume,
finalisation is idempotent and no bearer token or presigned query string appears
in logs.
For the staff web uploader, verify that a session-authenticated staff member
can reserve a test asset, upload both MP4 versions directly to S3, finalise it,
make it visible and publish its collection. Confirm a customer cannot access
the admin page or media actions. S3 CORS must permit the admin origin's `PUT`
requests and expose `ETag`; the browser should not send video bytes to PHP.

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
- A synthetic fallback-only MP4 can be uploaded, assigned and played without an
  HLS manifest.
- A synthetic 720p/480p HLS package uploads its master manifest last and plays
  through CloudFront.
- The packaged macOS Flutter client stores its bearer token in Keychain and
  contains no embedded AWS access key.
- Disabled, unavailable or unapproved concerts remain inaccessible.
- A studio cover, concert cover and PDF program can be uploaded, viewed,
  replaced and cleared against the configured concert bucket. Check the
  production PHP and web-server upload limits before testing a 10 MB file.

---

# Restore and Future Database Hosting

An object in S3 is only a usable recovery point after a successful restore
rehearsal. Periodically download a backup into an isolated, nonproduction
environment, run `gzip -t`, restore it into a fresh database, and check expected
tables, row counts and application behaviour. Never rehearse against production.

Before a production restore, identify the backup key and UTC timestamp, the Git
commit deployed when it was made, all migrations applied since then, and any
customer data written afterward. Restoring an older dump replaces that later
database activity. Reverting Git alone does not revert migrations or S3 objects.

If production moves to Amazon RDS, replace this EC2 dump procedure with a tested
RDS backup and recovery plan, including retention, point-in-time recovery,
encryption, monitoring and periodic restores. A deployment backup remains a
separate decision from routine database protection.

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
Merge to master
        │
        ▼
Deploy to EC2
        │
        ▼
Production Smoke Tests
```

GitHub Actions should eventually perform the deployment automatically by connecting to the EC2 instance and executing the production deployment script.

That automation is a future plan, not a current deployment check. It must also
create and verify a database backup before running migrations while MySQL or
MariaDB remains local to EC2. After a move to RDS, replace this logical-dump
procedure with a tested RDS backup and recovery plan.

The production `.env` file should remain on the EC2 instance and should not be recreated during deployment.

---

## Related Documentation

- [Development Environment](Development-Environment.md)
- [Git Workflow](Git-Workflow.md)
- [Security](Security.md)
- [Testing](Testing.md)
- [AWS](AWS.md)
