<?php

namespace App\Features\Contacts\Actions;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ImportContactDirectory
{
    private const MAX_ARCHIVE_LOGO_BYTES = 15 * 1024 * 1024;

    /** @return array{studios:int,studio_contacts:int,competitions:int,competition_staff:int,logos:int,new_studios:int,updated_studios:int,new_competitions:int,updated_competitions:int,applied:bool} */
    public function execute(string $archivePath, bool $apply = false): array
    {
        $archivePath = $this->absolutePath($archivePath);
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException("Unable to open directory archive: {$archivePath}");
        }

        try {
            $manifest = $this->manifest($zip);
            $this->validateManifest($manifest, $zip);
            $summary = $this->summary($manifest, $apply);

            if ($apply) {
                DB::transaction(function () use ($manifest, $zip): void {
                    foreach ($manifest['studios'] as $definition) {
                        $this->importStudio($definition, $zip);
                    }
                    foreach ($manifest['competitions'] as $definition) {
                        $this->importCompetition($definition, $zip);
                    }
                });
            }

            return $summary;
        } finally {
            $zip->close();
        }
    }

    private function manifest(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('manifest.json');
        if ($contents === false || strlen($contents) > 10 * 1024 * 1024) {
            throw new RuntimeException('The directory archive has no valid manifest.');
        }

        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('The directory manifest contains invalid JSON.', previous: $exception);
        }

        if (! is_array($manifest)) {
            throw new RuntimeException('The directory manifest must be a JSON object.');
        }

        return $manifest;
    }

    private function validateManifest(array $manifest, ZipArchive $zip): void
    {
        if (($manifest['format'] ?? null) !== 'dancepro-contact-directory' || ($manifest['version'] ?? null) !== 1) {
            throw new RuntimeException('The directory archive format or version is unsupported.');
        }

        $studios = $manifest['studios'] ?? null;
        $competitions = $manifest['competitions'] ?? null;
        if (! is_array($studios) || ! is_array($competitions) || count($studios) > 2000 || count($competitions) > 500) {
            throw new RuntimeException('The directory manifest has invalid entity collections.');
        }

        $seenStudioUuids = [];
        $seenStudioCodes = [];
        foreach ($studios as $index => $studio) {
            $this->validateEntity($studio, "studio {$index}", $seenStudioUuids, $seenStudioCodes, true, $zip);
            $this->validatePeople($studio['contacts'] ?? null, "studio {$index} contacts");
        }

        $seenCompetitionUuids = [];
        $seenCompetitionCodes = [];
        foreach ($competitions as $index => $competition) {
            $this->validateEntity($competition, "competition {$index}", $seenCompetitionUuids, $seenCompetitionCodes, false, $zip);
            $this->validatePeople($competition['staff'] ?? null, "competition {$index} staff");
        }

        $this->assertNoDatabaseConflicts($studios, $competitions);
    }

    private function validateEntity(array $entity, string $label, array &$seenUuids, array &$seenCodes, bool $studio, ZipArchive $zip): void
    {
        $uuid = $entity['uuid'] ?? null;
        $name = $entity['name'] ?? null;
        if (! is_string($uuid) || ! Str::isUuid($uuid) || isset($seenUuids[$uuid])) {
            throw new RuntimeException("The {$label} UUID is invalid or duplicated.");
        }
        if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 255) {
            throw new RuntimeException("The {$label} name is invalid.");
        }
        $seenUuids[$uuid] = true;

        $code = $entity['code'] ?? null;
        if ($code !== null) {
            if (! is_string($code) || trim($code) === '' || mb_strlen($code) > 50) {
                throw new RuntimeException("The {$label} code is invalid.");
            }
            $normalisedCode = mb_strtolower(trim($code));
            if (isset($seenCodes[$normalisedCode])) {
                throw new RuntimeException("The {$label} code is duplicated.");
            }
            $seenCodes[$normalisedCode] = true;
        }

        if ($studio && ! in_array($entity['status'] ?? null, array_column(StudioStatus::cases(), 'value'), true)) {
            throw new RuntimeException("The {$label} status is invalid.");
        }
        if (! $studio && ! is_bool($entity['is_active'] ?? null)) {
            throw new RuntimeException("The {$label} active flag is invalid.");
        }

        $this->validateLogo($entity['logo'] ?? null, $label, $zip);
    }

    private function validatePeople(mixed $people, string $label): void
    {
        if (! is_array($people) || count($people) > 50) {
            throw new RuntimeException("The {$label} collection is invalid.");
        }

        foreach ($people as $person) {
            if (! is_array($person) || ! is_string($person['name'] ?? null) || trim($person['name']) === '') {
                throw new RuntimeException("A person in {$label} has no valid name.");
            }
            if (! is_array($person['emails'] ?? null)) {
                throw new RuntimeException("A person in {$label} has an invalid email collection.");
            }
            foreach ($person['emails'] as $email) {
                if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    throw new RuntimeException("A person in {$label} has an invalid email address.");
                }
            }
        }
    }

    private function validateLogo(mixed $logo, string $label, ZipArchive $zip): void
    {
        if ($logo === null) {
            return;
        }
        if (! is_array($logo) || ! is_string($logo['path'] ?? null) || ! is_string($logo['sha256'] ?? null)) {
            throw new RuntimeException("The {$label} logo definition is invalid.");
        }

        $path = $logo['path'];
        if (str_contains($path, '..') || str_starts_with($path, '/') || $zip->locateName($path, ZipArchive::FL_NOCASE) === false) {
            throw new RuntimeException("The {$label} logo is missing or has an unsafe path.");
        }

        $stat = $zip->statName($path);
        if ($stat === false || ($stat['size'] ?? 0) > self::MAX_ARCHIVE_LOGO_BYTES) {
            throw new RuntimeException("The {$label} logo is too large.");
        }

        $contents = $zip->getFromName($path);
        if ($contents === false || ! hash_equals($logo['sha256'], hash('sha256', $contents))) {
            throw new RuntimeException("The {$label} logo failed its integrity check.");
        }
        if (! in_array($this->imageMime($contents), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException("The {$label} logo is not a supported image.");
        }
    }

    private function assertNoDatabaseConflicts(array $studios, array $competitions): void
    {
        $existingStudios = Studio::withTrashed()->get(['uuid', 'name', 'code']);
        $existingCompetitions = CompetitionContact::withTrashed()->get(['uuid', 'name', 'code']);

        foreach ($studios as $studio) {
            $conflict = $existingStudios->first(fn (Studio $existing): bool => $existing->uuid !== $studio['uuid'] && (
                (($studio['code'] ?? null) && $existing->code && strcasecmp($existing->code, $studio['code']) === 0)
                || strcasecmp(trim($existing->name), trim($studio['name'])) === 0
            ));
            if ($conflict) {
                throw new RuntimeException("Studio identity conflict: {$studio['name']} does not match existing UUID {$conflict->uuid}.");
            }
        }

        foreach ($competitions as $competition) {
            $conflict = $existingCompetitions->first(fn (CompetitionContact $existing): bool => $existing->uuid !== $competition['uuid'] && (
                (($competition['code'] ?? null) && $existing->code && strcasecmp($existing->code, $competition['code']) === 0)
                || strcasecmp(trim($existing->name), trim($competition['name'])) === 0
            ));
            if ($conflict) {
                throw new RuntimeException("Competition identity conflict: {$competition['name']} does not match existing UUID {$conflict->uuid}.");
            }
        }
    }

    private function importStudio(array $definition, ZipArchive $zip): void
    {
        $studio = Studio::withTrashed()->firstOrNew(['uuid' => $definition['uuid']]);
        $studio->fill(collect($definition)->only([
            'name', 'code', 'slug', 'status', 'description', 'cover_image_url', 'brand_color', 'contact_name',
            'contact_email', 'contact_phone', 'legacy_id', 'notes',
        ])->all());
        $studio->logo_path = $this->storeLogo($definition['logo'], 'studios', $definition['uuid'], $zip);
        $studio->deleted_at = null;
        $studio->save();
        $studio->contacts()->delete();
        $studio->contacts()->createMany($definition['contacts']);
    }

    private function importCompetition(array $definition, ZipArchive $zip): void
    {
        $competition = CompetitionContact::withTrashed()->firstOrNew(['uuid' => $definition['uuid']]);
        $competition->fill(collect($definition)->only([
            'name', 'code', 'organiser_name', 'organiser_email', 'organiser_phone', 'is_active', 'notes',
        ])->all());
        $competition->logo_path = $this->storeLogo($definition['logo'], 'competition-contacts', $definition['uuid'], $zip);
        $competition->deleted_at = null;
        $competition->save();
        $competition->staff()->delete();
        $competition->staff()->createMany($definition['staff']);
    }

    private function storeLogo(?array $logo, string $type, string $uuid, ZipArchive $zip): ?string
    {
        if ($logo === null) {
            return null;
        }

        $contents = $zip->getFromName($logo['path']);
        $extension = match ($this->imageMime($contents)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };
        $path = "logos/{$type}/{$uuid}/import-".substr($logo['sha256'], 0, 16).".{$extension}";
        if (! Storage::disk(config('contact-directory.logo_disk'))->put($path, $contents)) {
            throw new RuntimeException("Unable to store imported logo: {$path}");
        }

        return $path;
    }

    private function imageMime(string $contents): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
    }

    private function summary(array $manifest, bool $applied): array
    {
        $studioUuids = collect($manifest['studios'])->pluck('uuid');
        $competitionUuids = collect($manifest['competitions'])->pluck('uuid');
        $existingStudios = Studio::withTrashed()->whereIn('uuid', $studioUuids)->count();
        $existingCompetitions = CompetitionContact::withTrashed()->whereIn('uuid', $competitionUuids)->count();

        return [
            'studios' => count($manifest['studios']),
            'studio_contacts' => collect($manifest['studios'])->sum(fn (array $studio): int => count($studio['contacts'])),
            'competitions' => count($manifest['competitions']),
            'competition_staff' => collect($manifest['competitions'])->sum(fn (array $competition): int => count($competition['staff'])),
            'logos' => collect($manifest['studios'])->whereNotNull('logo')->count() + collect($manifest['competitions'])->whereNotNull('logo')->count(),
            'new_studios' => count($manifest['studios']) - $existingStudios,
            'updated_studios' => $existingStudios,
            'new_competitions' => count($manifest['competitions']) - $existingCompetitions,
            'updated_competitions' => $existingCompetitions,
            'applied' => $applied,
        ];
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return storage_path('app/private/'.ltrim($path, DIRECTORY_SEPARATOR));
    }
}
