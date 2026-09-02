<?php

namespace App\Features\Deployment\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

final class DatabaseBackup
{
    /**
     * @return array{path:string, manifest:string, bytes:int, sha256:string, removed:int}
     */
    public function create(bool $prune = false): array
    {
        if (! config('backups.database.enabled')) {
            throw new RuntimeException('Database backups are disabled. Set DATABASE_BACKUP_ENABLED=true.');
        }

        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}");
        if (! is_array($connection) || ! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Automated deployment backups currently require MySQL or MariaDB.');
        }

        $configuredBinary = (string) config('backups.database.dump_binary');
        $binary = str_contains($configuredBinary, DIRECTORY_SEPARATOR)
            ? (is_executable($configuredBinary) ? $configuredBinary : null)
            : (new ExecutableFinder)->find($configuredBinary);
        if ($binary === null) {
            throw new RuntimeException('The configured database dump executable was not found.');
        }

        $configuredDirectory = (string) config('backups.database.directory');
        $directory = str_starts_with($configuredDirectory, DIRECTORY_SEPARATOR)
            ? $configuredDirectory
            : storage_path('app/private/'.trim($configuredDirectory, '/'));
        File::ensureDirectoryExists($directory, 0700, true);

        $identifier = now()->utc()->format('Y-m-d_H-i-s').'-'.bin2hex(random_bytes(4));
        $sqlPath = "{$directory}/dancepro-{$identifier}.sql";
        $archivePath = $sqlPath.'.gz';
        $manifestPath = $archivePath.'.json';

        try {
            $process = new Process(
                $this->command($binary, $connection, $sqlPath),
                env: ['MYSQL_PWD' => (string) ($connection['password'] ?? '')],
            );
            $process->setTimeout((int) config('backups.database.timeout_seconds'));
            $process->mustRun();

            $this->compress($sqlPath, $archivePath);
            $verification = $this->verify($archivePath);
            File::put($manifestPath, json_encode([
                'created_at' => now()->utc()->toIso8601String(),
                'connection' => $connectionName,
                'database' => (string) ($connection['database'] ?? ''),
                'archive' => basename($archivePath),
                ...$verification,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL, true);
            File::chmod($manifestPath, 0600);

            return [
                'path' => $archivePath,
                'manifest' => $manifestPath,
                ...$verification,
                'removed' => $prune ? $this->prune($directory) : 0,
            ];
        } catch (Throwable $exception) {
            File::delete([$sqlPath, $archivePath, $manifestPath]);

            throw $exception;
        } finally {
            File::delete($sqlPath);
        }
    }

    /**
     * @return array{bytes:int, sha256:string}
     */
    public function verify(string $archivePath): array
    {
        if (! File::isFile($archivePath) || File::size($archivePath) === 0) {
            throw new RuntimeException('The database backup archive is missing or empty.');
        }

        $stream = gzopen($archivePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The database backup archive is not readable gzip data.');
        }

        try {
            $sample = gzread($stream, 1);
            if ($sample === false || $sample === '') {
                throw new RuntimeException('The database backup archive failed gzip verification.');
            }
        } finally {
            gzclose($stream);
        }

        $checksum = hash_file('sha256', $archivePath);
        if ($checksum === false) {
            throw new RuntimeException('The database backup checksum could not be generated.');
        }

        return ['bytes' => File::size($archivePath), 'sha256' => $checksum];
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return list<string>
     */
    private function command(string $binary, array $connection, string $sqlPath): array
    {
        $command = [
            $binary,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--no-tablespaces',
            '--result-file='.$sqlPath,
            '--user='.(string) ($connection['username'] ?? ''),
        ];

        if (filled($connection['unix_socket'] ?? null)) {
            $command[] = '--socket='.(string) $connection['unix_socket'];
        } else {
            $command[] = '--host='.(string) ($connection['host'] ?? '127.0.0.1');
            $command[] = '--port='.(string) ($connection['port'] ?? '3306');
        }

        $command[] = '--databases';
        $command[] = (string) ($connection['database'] ?? '');

        return $command;
    }

    private function compress(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($destination, 'wb9');
        if ($input === false || $output === false) {
            throw new RuntimeException('The database dump could not be compressed.');
        }

        File::chmod($source, 0600);

        try {
            while (! feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false || gzwrite($output, $chunk) === false) {
                    throw new RuntimeException('The database dump could not be compressed.');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }

        File::chmod($destination, 0600);
    }

    private function prune(string $directory): int
    {
        $cutoff = now()->subDays(max(1, (int) config('backups.database.retention_days')))->getTimestamp();
        $removed = 0;

        foreach (File::glob("{$directory}/dancepro-*.sql.gz") ?: [] as $archive) {
            if (File::lastModified($archive) >= $cutoff) {
                continue;
            }

            if (File::delete([$archive, $archive.'.json'])) {
                $removed++;
            }
        }

        return $removed;
    }
}
