<?php

namespace Tests\Feature\Deployment;

use App\Features\Deployment\Services\DatabaseBackup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    private string $testDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDirectory = storage_path('framework/testing/database-backup-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->testDirectory);

        config([
            'backups.database.enabled' => true,
            'backups.database.directory' => str_replace(storage_path('app/private/').'/', '', $this->testDirectory),
            'backups.database.retention_days' => 30,
            'backups.database.timeout_seconds' => 10,
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql',
                'host' => 'database.example',
                'port' => '3306',
                'database' => 'dancepro',
                'username' => 'dancepro',
                'password' => 'not-a-real-secret',
                'unix_socket' => '',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testDirectory);

        parent::tearDown();
    }

    public function test_backup_is_created_compressed_verified_and_manifested(): void
    {
        config(['backups.database.dump_binary' => $this->fakeDumpExecutable(success: true)]);

        $result = app(DatabaseBackup::class)->create();

        $this->assertFileExists($result['path']);
        $this->assertFileExists($result['manifest']);
        $this->assertGreaterThan(0, $result['bytes']);
        $this->assertSame(hash_file('sha256', $result['path']), $result['sha256']);
        $this->assertStringContainsString('CREATE TABLE', (string) file_get_contents('compress.zlib://'.$result['path']));
        $this->assertStringContainsString($result['sha256'], (string) File::get($result['manifest']));
    }

    public function test_failed_dump_leaves_no_backup_or_manifest(): void
    {
        config(['backups.database.dump_binary' => $this->fakeDumpExecutable(success: false)]);

        try {
            app(DatabaseBackup::class)->create();
            $this->fail('Expected the failed database dump to stop the backup.');
        } catch (ProcessFailedException) {
            $this->assertSame([], File::glob($this->testDirectory.'/dancepro-*'));
        }
    }

    public function test_pruning_only_removes_expired_managed_backups(): void
    {
        config(['backups.database.dump_binary' => $this->fakeDumpExecutable(success: true)]);
        $expired = $this->testDirectory.'/dancepro-2000-01-01_00-00-00-expired.sql.gz';
        $unmanaged = $this->testDirectory.'/keep-me.sql.gz';
        File::put($expired, 'expired');
        File::put($expired.'.json', '{}');
        File::put($unmanaged, 'unmanaged');
        touch($expired, now()->subDays(31)->getTimestamp());

        $result = app(DatabaseBackup::class)->create(prune: true);

        $this->assertSame(1, $result['removed']);
        $this->assertFileDoesNotExist($expired);
        $this->assertFileDoesNotExist($expired.'.json');
        $this->assertFileExists($unmanaged);
    }

    public function test_daily_backup_is_scheduled_with_overlap_and_single_server_protection(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command ?? '', 'database:backup --prune'));

        $this->assertNotNull($event);
        $this->assertSame('0 2 * * *', $event->expression);
        $this->assertSame(config('app.timezone'), $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    private function fakeDumpExecutable(bool $success): string
    {
        $path = $this->testDirectory.'/'.($success ? 'successful-dump' : 'failed-dump');
        $body = $success
            ? <<<'SH'
#!/bin/sh
for argument in "$@"; do
    case "$argument" in
        --result-file=*) destination="${argument#--result-file=}" ;;
    esac
done
printf '%s\n' '-- MySQL dump' 'CREATE TABLE test (id bigint);' > "$destination"
SH
            : <<<'SH'
#!/bin/sh
exit 1
SH;
        File::put($path, $body.PHP_EOL);
        chmod($path, 0700);

        return $path;
    }
}
