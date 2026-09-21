<?php

namespace App\Shared\Support;

use RuntimeException;

class LocalDevelopmentSafety
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', 'mysql', 'mariadb', 'postgres', 'postgresql', 'db', 'redis', 'mailpit', 'host.docker.internal'];

    public function assertSafe(): void
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! $this->isLocalHost($appHost) && ! $this->isPrivateAddress($appHost)
            && (! is_string($appHost) || ! preg_match('/\.(?:test|localhost|local)$/i', $appHost))) {
            throw new RuntimeException('Local development APP_URL must use a local or private host.');
        }

        foreach (['s3', 's3_public_uploads', 's3_competitions', 's3_concerts', 's3_concerts_legacy'] as $disk) {
            if (config("filesystems.disks.{$disk}.driver") !== 'local') {
                throw new RuntimeException('Local development cannot use an AWS storage driver. Clear cached configuration and check APP_ENV.');
            }
        }

        if (config('media.upload_disk') !== 's3_concerts'
            || config('media.legacy_disk') !== 's3_concerts_legacy') {
            throw new RuntimeException('Local media disks must use the isolated concert and legacy disk names.');
        }

        $connection = (string) config('database.default');
        if (! in_array($connection, ['sqlite', 'mysql', 'mariadb', 'pgsql'], true)) {
            throw new RuntimeException('Local development requires a local database connection.');
        }
        if ($connection !== 'sqlite') {
            $database = config("database.connections.{$connection}", []);
            $host = parse_url((string) ($database['url'] ?? ''), PHP_URL_HOST) ?: ($database['host'] ?? '');
            if (! $this->isLocalHost($host)) {
                throw new RuntimeException('Local development cannot connect to a remote database host.');
            }
        }

        $mailer = (string) config('mail.default');
        $smtp = config('mail.mailers.smtp', []);
        $smtpHost = parse_url((string) ($smtp['url'] ?? ''), PHP_URL_HOST) ?: ($smtp['host'] ?? '');
        if (! in_array($mailer, ['log', 'array', 'smtp'], true)
            || ($mailer === 'smtp' && ! $this->isLocalHost($smtpHost))) {
            throw new RuntimeException('Local development cannot use a remote mail service.');
        }

        if (! in_array(config('queue.default'), ['sync', 'database', 'redis', 'null'], true)) {
            throw new RuntimeException('Local development cannot use a remote queue service.');
        }

        foreach (['default', 'cache'] as $connection) {
            $redis = config("database.redis.{$connection}", []);
            $host = parse_url((string) ($redis['url'] ?? ''), PHP_URL_HOST) ?: ($redis['host'] ?? '');
            if (! $this->isLocalHost($host)) {
                throw new RuntimeException('Local development cannot connect to a remote Redis host.');
            }
        }
    }

    private function isLocalHost(mixed $host): bool
    {
        return is_string($host) && in_array(strtolower(trim($host, '[]')), self::LOCAL_HOSTS, true);
    }

    private function isPrivateAddress(mixed $host): bool
    {
        $address = is_string($host) ? trim($host, '[]') : '';

        return filter_var($address, FILTER_VALIDATE_IP) !== false
            && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
