<?php

namespace App\Features\Deployment\Services;

final class ProductionEnvironmentValidator
{
    /**
     * @return list<string>
     */
    public function errors(): array
    {
        $errors = [];

        $this->require(app()->environment('production'), 'APP_ENV must be production.', $errors);
        $this->require(! config('app.debug'), 'APP_DEBUG must be false.', $errors);
        $this->require(filled(config('app.key')), 'APP_KEY must be set.', $errors);
        $this->require(
            ! is_file((string) config('deployment.vite_hot_file')),
            'The Vite hot file must not exist in production. Remove public/hot and rebuild frontend assets.',
            $errors,
        );

        $appUrl = (string) config('app.url');
        $appHost = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        $this->require(
            parse_url($appUrl, PHP_URL_SCHEME) === 'https'
                && $appHost !== ''
                && ! in_array($appHost, ['localhost', '127.0.0.1', '::1'], true),
            'APP_URL must be the public HTTPS application URL.',
            $errors,
        );

        $this->require(config('session.secure') === true, 'SESSION_SECURE_COOKIE must be true.', $errors);
        $this->require(config('session.http_only') === true, 'SESSION_HTTP_ONLY must be true.', $errors);
        $this->require(config('session.encrypt') === true, 'SESSION_ENCRYPT must be true.', $errors);
        $this->require(
            in_array(config('session.driver'), ['database', 'redis', 'memcached', 'dynamodb'], true),
            'SESSION_DRIVER must use a persistent shared store.',
            $errors,
        );

        $this->require(config('security.two_factor.enabled') === true, 'TWO_FACTOR_ENABLED must be true.', $errors);
        $this->require(config('security.two_factor.enforced') === true, 'TWO_FACTOR_ENFORCED must be true.', $errors);
        $tokenExpiration = (int) config('sanctum.expiration');
        $this->require(
            $tokenExpiration > 0 && $tokenExpiration <= 43200,
            'SANCTUM_EXPIRATION must be between 1 and 43200 minutes.',
            $errors,
        );
        $mobileTokenExpiration = (int) config('security.mobile_token_expiration');
        $this->require(
            $mobileTokenExpiration > 0 && $mobileTokenExpiration <= 43200,
            'MOBILE_TOKEN_EXPIRATION must be between 1 and 43200 minutes.',
            $errors,
        );

        $this->require(config('database.default') !== 'sqlite', 'DB_CONNECTION must use the production database.', $errors);
        $this->require(config('backups.database.enabled') === true, 'DATABASE_BACKUP_ENABLED must be true.', $errors);
        $this->require(
            (int) config('backups.database.retention_days') > 0,
            'DATABASE_BACKUP_RETENTION_DAYS must be greater than zero.',
            $errors,
        );
        $this->require(
            preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) config('backups.database.schedule_time')) === 1,
            'DATABASE_BACKUP_SCHEDULE_TIME must use 24-hour HH:MM format.',
            $errors,
        );
        $healthcheckUrl = (string) config('deployment.healthcheck_url');
        $this->require(
            parse_url($healthcheckUrl, PHP_URL_SCHEME) === 'https'
                && parse_url($healthcheckUrl, PHP_URL_HOST) === parse_url($appUrl, PHP_URL_HOST)
                && parse_url($healthcheckUrl, PHP_URL_PATH) === '/up',
            'DEPLOY_HEALTHCHECK_URL must be the production HTTPS /up URL on the APP_URL host.',
            $errors,
        );
        $this->require(
            ! in_array(config('cache.default'), ['array', 'null'], true),
            'CACHE_STORE must use a persistent store.',
            $errors,
        );
        $this->require(
            ! in_array(config('queue.default'), ['sync', 'null'], true),
            'QUEUE_CONNECTION must use an asynchronous persistent connection.',
            $errors,
        );

        $mailDriver = config('mail.default');
        $this->require(
            filled($mailDriver) && ! in_array($mailDriver, ['log', 'array'], true),
            'MAIL_MAILER must use a real outbound mail transport.',
            $errors,
        );
        $this->require(
            filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
                && strtolower((string) config('mail.from.address')) !== 'hello@example.com',
            'MAIL_FROM_ADDRESS must be a real sender address.',
            $errors,
        );

        $this->validateLogging($errors);
        $this->validateFilesystem('operations.filesystem_disk', 'OPERATIONS_FILESYSTEM_DISK', $errors, requirePrivate: true, requirePersistent: true);
        $this->validateFilesystem('uploads.public_disk', 'PUBLIC_UPLOAD_DISK', $errors, requirePublic: true, requirePersistent: true);
        $this->validateDownloadSigner($errors);

        return $errors;
    }

    /**
     * @param  list<string>  $errors
     */
    private function validateLogging(array &$errors): void
    {
        $channel = config('logging.default');
        $this->require(filled($channel) && $channel !== 'null', 'LOG_CHANNEL must write production logs.', $errors);

        $channels = config("logging.channels.{$channel}.driver") === 'stack'
            ? config("logging.channels.{$channel}.channels", [])
            : [$channel];

        foreach ($channels as $stackedChannel) {
            $this->require(
                config("logging.channels.{$stackedChannel}.level") !== 'debug',
                "Logging channel [{$stackedChannel}] must not use debug level.",
                $errors,
            );
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function validateFilesystem(
        string $configKey,
        string $environmentName,
        array &$errors,
        bool $requirePrivate = false,
        bool $requirePublic = false,
        bool $requirePersistent = false,
    ): void {
        $disk = config($configKey);
        $configuration = config("filesystems.disks.{$disk}");
        $this->require(
            filled($disk) && is_array($configuration),
            "{$environmentName} must name a configured filesystem disk.",
            $errors,
        );

        if ($requirePrivate && is_array($configuration)) {
            $this->require(
                ($configuration['visibility'] ?? null) !== 'public',
                "{$environmentName} must use private storage.",
                $errors,
            );
        }

        if ($requirePublic && is_array($configuration)) {
            $this->require(
                ($configuration['browser_accessible'] ?? false) === true,
                "{$environmentName} must provide browser-accessible URLs.",
                $errors,
            );
        }

        if ($requirePersistent && is_array($configuration)) {
            $this->require(
                ($configuration['persistent'] ?? false) === true,
                "{$environmentName} must use durable shared storage.",
                $errors,
            );
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function validateDownloadSigner(array &$errors): void
    {
        $domain = config('downloads.cloudfront.domain');
        $keyPairId = config('downloads.cloudfront.key_pair_id');
        $privateKey = config('downloads.cloudfront.private_key');
        $privateKeyPath = config('downloads.cloudfront.private_key_path');

        if (! collect([$domain, $keyPairId, $privateKey, $privateKeyPath])->contains(fn ($value) => filled($value))) {
            return;
        }

        $this->require(filled($domain), 'DOWNLOAD_CLOUDFRONT_DOMAIN is required when download signing is configured.', $errors);
        $this->require(filled($keyPairId), 'DOWNLOAD_CLOUDFRONT_KEY_PAIR_ID is required when download signing is configured.', $errors);
        $this->require(
            filled($privateKey) || filled($privateKeyPath),
            'A download CloudFront private key or private-key path is required when signing is configured.',
            $errors,
        );
    }

    /**
     * @param  list<string>  $errors
     */
    private function require(bool $condition, string $message, array &$errors): void
    {
        if (! $condition) {
            $errors[] = $message;
        }
    }
}
