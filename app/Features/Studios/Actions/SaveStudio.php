<?php

namespace App\Features\Studios\Actions;

use App\Features\Studios\Models\Studio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveStudio
{
    public function execute(array $attributes, ?Studio $studio = null): Studio
    {
        return DB::transaction(function () use ($attributes, $studio): Studio {
            $studio ??= new Studio;
            $uuid = $studio->uuid ?? (string) Str::uuid();
            $contacts = $attributes['contacts'] ?? [];
            $primaryContact = $contacts[0] ?? [];

            $studio->fill([
                ...collect($attributes)->except(['logo', 'contacts'])->all(),
                'uuid' => $uuid,
                'slug' => ($attributes['slug'] ?? null) ?: ($studio->slug ?: Str::slug($attributes['name']).'-'.Str::substr($uuid, 0, 8)),
                'contact_name' => $primaryContact['name'] ?? null,
                'contact_email' => $this->emails($primaryContact['emails'] ?? null)[0] ?? null,
                'contact_phone' => $primaryContact['phone'] ?? null,
            ])->save();

            $studio->contacts()->delete();
            foreach ($contacts as $position => $contact) {
                $studio->contacts()->create([
                    'name' => $contact['name'],
                    'role' => $contact['role'] ?? null,
                    'emails' => $this->emails($contact['emails'] ?? null),
                    'phone' => $contact['phone'] ?? null,
                    'position' => $position,
                ]);
            }

            if (($attributes['logo'] ?? null) instanceof UploadedFile) {
                $studio->logo_path = $attributes['logo']->storeAs(
                    "logos/studios/{$studio->uuid}",
                    'logo.'.$attributes['logo']->extension(),
                    'public',
                );
                $studio->save();
            }

            return $studio->load('contacts');
        });
    }

    /** @return list<string> */
    private function emails(?string $emails): array
    {
        return collect(explode(',', (string) $emails))
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
