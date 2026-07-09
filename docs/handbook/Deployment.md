# Deployment

Phase 0 assumes Docker/Sail locally and a production environment capable of running PHP 8.3 or newer with the Laravel 13 runtime requirements.

Local Laravel and Composer commands should be run through Sail. The commands
below describe production-style deployment checks and may be run inside the
deployment environment rather than from the host machine.

Initial deployment checks should include:

```text
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan test
php artisan route:list
```

Sanctum requires the `personal_access_tokens` table migration to be run before the authentication endpoints can issue tokens.

## Related Documentation

- [Development Environment](Development-Environment.md)
- [Security](Security.md)
- [Testing](Testing.md)
