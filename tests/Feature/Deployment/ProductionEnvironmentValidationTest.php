<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

class ProductionEnvironmentValidationTest extends TestCase
{
    public function test_unsafe_production_configuration_is_rejected(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => true,
            'app.key' => null,
            'app.url' => 'http://localhost',
            'security.two_factor.enabled' => false,
            'security.two_factor.enforced' => false,
            'sanctum.expiration' => null,
            'session.secure' => false,
            'session.encrypt' => false,
            'session.driver' => 'array',
            'database.default' => 'sqlite',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'mail.default' => 'log',
            'mail.from.address' => 'hello@example.com',
            'logging.default' => 'single',
            'logging.channels.single.level' => 'debug',
        ]);

        $this->artisan('production:validate')
            ->expectsOutputToContain('APP_DEBUG must be false.')
            ->expectsOutputToContain('APP_URL must be the public HTTPS application URL.')
            ->expectsOutputToContain('TWO_FACTOR_ENFORCED must be true.')
            ->expectsOutputToContain('SANCTUM_EXPIRATION must be between 1 and 43200 minutes.')
            ->expectsOutputToContain('MAIL_MAILER must use a real outbound mail transport.')
            ->assertFailed();
    }

    public function test_secure_complete_production_configuration_passes(): void
    {
        $this->setSecureProductionConfiguration();

        $this->artisan('production:validate')
            ->expectsOutput('Production environment validation passed.')
            ->assertSuccessful();
    }

    public function test_stale_vite_hot_file_is_rejected(): void
    {
        $this->setSecureProductionConfiguration();
        $hotFile = tempnam(sys_get_temp_dir(), 'dancepro-vite-hot-');
        $this->assertNotFalse($hotFile);
        config()->set('deployment.vite_hot_file', $hotFile);

        try {
            $this->artisan('production:validate')
                ->expectsOutputToContain('The Vite hot file must not exist in production.')
                ->assertFailed();
        } finally {
            unlink($hotFile);
        }
    }

    public function test_partially_configured_download_signer_is_rejected(): void
    {
        $this->setSecureProductionConfiguration();
        config([
            'downloads.cloudfront.domain' => 'downloads.example.com',
            'downloads.cloudfront.key_pair_id' => null,
            'downloads.cloudfront.private_key' => null,
            'downloads.cloudfront.private_key_path' => null,
        ]);

        $this->artisan('production:validate')
            ->expectsOutputToContain('DOWNLOAD_CLOUDFRONT_KEY_PAIR_ID is required')
            ->assertFailed();
    }

    public function test_local_upload_disks_are_rejected_for_production(): void
    {
        $this->setSecureProductionConfiguration();
        config([
            'operations.filesystem_disk' => 'local',
            'uploads.public_disk' => 'public',
        ]);

        $this->artisan('production:validate')
            ->expectsOutputToContain('OPERATIONS_FILESYSTEM_DISK must use durable shared storage.')
            ->expectsOutputToContain('PUBLIC_UPLOAD_DISK must use durable shared storage.')
            ->assertFailed();
    }

    private function setSecureProductionConfiguration(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false,
            'app.key' => 'base64:test-key',
            'app.url' => 'https://dancepro.example',
            'deployment.healthcheck_url' => 'https://dancepro.example/up',
            'deployment.vite_hot_file' => sys_get_temp_dir().'/dancepro-no-vite-hot-file',
            'security.two_factor.enabled' => true,
            'security.two_factor.enforced' => true,
            'sanctum.expiration' => 10080,
            'session.secure' => true,
            'session.http_only' => true,
            'session.encrypt' => true,
            'session.driver' => 'database',
            'database.default' => 'mysql',
            'backups.database.enabled' => true,
            'backups.database.retention_days' => 30,
            'cache.default' => 'database',
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.from.address' => 'noreply@dancepro.example',
            'logging.default' => 'daily',
            'logging.channels.daily.level' => 'warning',
            'operations.filesystem_disk' => 's3',
            'uploads.public_disk' => 's3_public_uploads',
            'downloads.cloudfront.domain' => null,
            'downloads.cloudfront.key_pair_id' => null,
            'downloads.cloudfront.private_key' => null,
            'downloads.cloudfront.private_key_path' => null,
        ]);
    }
}
