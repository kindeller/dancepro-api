<?php

namespace Tests\Unit;

use App\Features\Notifications\Services\MobilePushDelivery;
use App\Shared\Support\LocalDevelopmentSafety;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LocalDevelopmentSafetyTest extends TestCase
{
    public function test_local_mode_never_sends_mobile_push_even_with_a_gateway_configured(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('mobile.push.gateway_url', 'https://live-push.example.test/send');
        Http::fake();

        app(MobilePushDelivery::class)->deliver(1);

        Http::assertNothingSent();
    }

    public function test_local_configuration_rejects_remote_database_and_aws_disks(): void
    {
        foreach (['s3', 's3_public_uploads', 's3_competitions', 's3_concerts', 's3_concerts_legacy'] as $disk) {
            config()->set("filesystems.disks.{$disk}.driver", 'local');
        }
        config()->set('database.default', 'sqlite');
        config()->set('mail.default', 'log');
        config()->set('queue.default', 'sync');
        $safety = app(LocalDevelopmentSafety::class);
        $safety->assertSafe();

        config()->set('app.url', 'https://production.example.com');
        try {
            $safety->assertSafe();
            $this->fail('A public APP_URL should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('APP_URL', $exception->getMessage());
        }
        config()->set('app.url', 'http://localhost');

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.url', 'mysql://user:password@production.example.test/app');
        try {
            $safety->assertSafe();
            $this->fail('A remote database should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('remote database', $exception->getMessage());
        }

        config()->set('database.default', 'sqlite');
        config()->set('filesystems.disks.s3_concerts.driver', 's3');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AWS storage driver');
        $safety->assertSafe();
    }
}
