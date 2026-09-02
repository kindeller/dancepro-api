<?php

namespace App\Features\Contacts\Actions;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Studios\Models\Studio;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ExportContactDirectory
{
    /** @return array{studios:int,studio_contacts:int,competitions:int,competition_staff:int,logos:int,path:string} */
    public function execute(string $destination): array
    {
        $destination = $this->absolutePath($destination);
        File::ensureDirectoryExists(dirname($destination));

        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create directory archive: {$destination}");
        }

        $logoCount = 0;

        try {
            $studios = Studio::query()->with('contacts')->orderBy('name')->get()
                ->map(function (Studio $studio) use ($zip, &$logoCount): array {
                    $logo = $this->addLogo($zip, 'studios', $studio->uuid, $studio->logo_path);
                    $logoCount += $logo === null ? 0 : 1;

                    return [
                        'uuid' => $studio->uuid,
                        'name' => $studio->name,
                        'code' => $studio->code,
                        'slug' => $studio->slug,
                        'status' => $studio->status->value,
                        'description' => $studio->description,
                        'cover_image_url' => $studio->cover_image_url,
                        'brand_color' => $studio->brand_color,
                        'contact_name' => $studio->contact_name,
                        'contact_email' => $studio->contact_email,
                        'contact_phone' => $studio->contact_phone,
                        'legacy_id' => $studio->legacy_id,
                        'notes' => $studio->notes,
                        'logo' => $logo,
                        'contacts' => $studio->contacts->map(fn ($contact): array => [
                            'name' => $contact->name,
                            'role' => $contact->role,
                            'emails' => $contact->emailAddresses(),
                            'phone' => $contact->phone,
                            'position' => $contact->position,
                        ])->values()->all(),
                    ];
                })->values();

            $competitions = CompetitionContact::query()->with('staff')->orderBy('name')->get()
                ->map(function (CompetitionContact $competition) use ($zip, &$logoCount): array {
                    $logo = $this->addLogo($zip, 'competitions', $competition->uuid, $competition->logo_path);
                    $logoCount += $logo === null ? 0 : 1;

                    return [
                        'uuid' => $competition->uuid,
                        'name' => $competition->name,
                        'code' => $competition->code,
                        'organiser_name' => $competition->organiser_name,
                        'organiser_email' => $competition->organiser_email,
                        'organiser_phone' => $competition->organiser_phone,
                        'is_active' => $competition->is_active,
                        'notes' => $competition->notes,
                        'logo' => $logo,
                        'staff' => $competition->staff->map(fn ($staff): array => [
                            'name' => $staff->name,
                            'role' => $staff->role,
                            'emails' => $staff->emailAddresses(),
                            'phone' => $staff->phone,
                            'position' => $staff->position,
                        ])->values()->all(),
                    ];
                })->values();

            $manifest = [
                'format' => 'dancepro-contact-directory',
                'version' => 1,
                'exported_at' => now()->toIso8601String(),
                'studios' => $studios->all(),
                'competitions' => $competitions->all(),
            ];

            $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (! $zip->addFromString('manifest.json', $encoded)) {
                throw new RuntimeException('Unable to add the directory manifest to the archive.');
            }
        } catch (\Throwable $exception) {
            $zip->close();
            File::delete($destination);

            throw $exception;
        }

        if (! $zip->close()) {
            File::delete($destination);
            throw new RuntimeException('Unable to finish the directory archive.');
        }

        return [
            'studios' => $studios->count(),
            'studio_contacts' => $studios->sum(fn (array $studio): int => count($studio['contacts'])),
            'competitions' => $competitions->count(),
            'competition_staff' => $competitions->sum(fn (array $competition): int => count($competition['staff'])),
            'logos' => $logoCount,
            'path' => $destination,
        ];
    }

    /** @return array{path:string,extension:string,sha256:string}|null */
    private function addLogo(ZipArchive $zip, string $type, string $uuid, ?string $storagePath): ?array
    {
        if (! $storagePath) {
            return null;
        }

        $disk = Storage::disk(config('uploads.public_disk'));
        if (! $disk->exists($storagePath)) {
            throw new RuntimeException("Directory logo is missing from public storage: {$storagePath}");
        }

        $contents = $disk->get($storagePath);
        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
        $archivePath = "logos/{$type}/{$uuid}.{$extension}";

        if (! $zip->addFromString($archivePath, $contents)) {
            throw new RuntimeException("Unable to add directory logo to archive: {$storagePath}");
        }

        return ['path' => $archivePath, 'extension' => $extension, 'sha256' => hash('sha256', $contents)];
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return storage_path('app/private/'.ltrim($path, DIRECTORY_SEPARATOR));
    }
}
