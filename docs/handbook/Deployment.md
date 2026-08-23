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

php artisan migrate --force

php artisan optimize:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

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
uses `s3_concerts`. Production credentials should be scoped to the required
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

DOWNLOAD_ALLOWED_DISKS
DOWNLOAD_DEFAULT_DISK
DOWNLOAD_SIGNED_URL_TTL_MINUTES
```

When CloudFront signing is enabled, production also requires the configured
distribution domain, key-pair ID and private key or readable private-key path
described in [AWS](AWS.md). Private-key contents must remain outside source
control.

The configured region should always resolve to:

```text
ap-southeast-2
```

A missing region will prevent Laravel from constructing the AWS S3 client.

---

# Deployment Validation

Following deployment, validate:

```bash
php artisan about
```

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

Storage::disk('s3_competitions')->directories('');
Storage::disk('s3_concerts')->directories('');
```

The regions should resolve correctly and both configured buckets should be
accessible with their production credentials.

When CloudFront concert delivery is implemented, also verify that Laravel can
generate a short-lived playback URL and a short-lived attachment URL without
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
