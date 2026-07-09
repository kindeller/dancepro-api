<?php

namespace App\Features\Downloads\Actions;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Services\StorageKeyValidator;
use App\Features\Downloads\Support\DownloadLinkStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateDownloadLinks
{
    public function __construct(
        private readonly StorageKeyValidator $storageKeyValidator,
    ) {}

    /**
     * @param  array<int, string>  $keys
     * @return Collection<int, array{download_link: DownloadLink, token: string}>
     */
    public function handle(User $user, array $keys, ?string $disk, ?int $days, ?string $purpose, ?string $notes): Collection
    {
        $disk = $disk ?: (string) config('downloads.default_disk', 's3_competitions');
        $this->validateDisk($disk);

        $expiresAt = now()->addDays($days ?: 60);

        return collect($keys)
            ->map(fn (string $key): string => $this->storageKeyValidator->validate($key))
            ->unique()
            ->values()
            ->map(function (string $key) use ($user, $disk, $expiresAt, $purpose, $notes): array {
                $token = Str::random(64);

                $downloadLink = DownloadLink::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'generated_by_user_id' => $user->id,
                    'storage_disk' => $disk,
                    'storage_key' => $key,
                    'original_filename' => basename($key),
                    'purpose' => $purpose,
                    'token_hash' => hash('sha256', $token),
                    'status' => DownloadLinkStatus::ACTIVE,
                    'expires_at' => $expiresAt,
                    'notes' => $notes,
                ]);

                return [
                    'download_link' => $downloadLink,
                    'token' => $token,
                ];
            });
    }

    private function validateDisk(string $disk): void
    {
        $allowedDisks = config('downloads.allowed_disks', []);

        if (! is_array($allowedDisks) || ! in_array($disk, $allowedDisks, true)) {
            throw ValidationException::withMessages([
                'disk' => ['The selected disk is not allowed for download links.'],
            ]);
        }
    }
}
