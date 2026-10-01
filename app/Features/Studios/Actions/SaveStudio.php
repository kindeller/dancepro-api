<?php

namespace App\Features\Studios\Actions;

use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveStudio
{
    public function __construct(private readonly ManageStudioAccountAccess $accountAccess) {}

    public function execute(array $attributes, ?Studio $studio = null, ?User $actor = null): Studio
    {
        return DB::transaction(function () use ($attributes, $studio, $actor): Studio {
            $studio ??= new Studio;
            $uuid = $studio->uuid ?? (string) Str::uuid();
            $contacts = $attributes['contacts'] ?? [];
            $accounts = [];
            foreach ($contacts as $index => &$contact) {
                if (! empty($contact['account_uuid'])) {
                    $account = User::query()->where('uuid', $contact['account_uuid'])->where('type', 'customer')
                        ->where('is_active', true)->whereNull('merged_into_user_id')->first();
                    if (! $account) {
                        throw ValidationException::withMessages(["contacts.{$index}.account_uuid" => 'This customer account is no longer available. Search again.']);
                    }
                    $accounts[$index] = $account;
                    $contact['emails'] = implode(', ', array_unique([$account->email, ...$this->emails($contact['emails'] ?? null)]));
                }
                if (! empty($contact['access_role'])) {
                    abort_unless($actor?->can('manageStudios') && isset($accounts[$index]), 403);
                }
            }
            unset($contact);
            $primaryContact = $contacts[0] ?? [];

            if ($studio->exists && array_key_exists('code', $attributes) && $attributes['code'] !== $studio->code) {
                // Retire the old independent gallery code when the shared Studio code changes.
                $studio->forceFill(['gallery_code_hash' => null, 'gallery_code_encrypted' => null,
                    'gallery_code_version' => $studio->gallery_code_version + 1]);
            }
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
                    'user_id' => $accounts[$position]->id ?? null,
                    'role' => $contact['role'] ?? null,
                    'emails' => $this->emails($contact['emails'] ?? null),
                    'phone' => $contact['phone'] ?? null,
                    'position' => $position,
                ]);
                if (! empty($contact['access_role'])) {
                    $account = $accounts[$position];
                    $existing = $studio->accountAccesses()->where('email', $account->email)->first();
                    if (! $existing || $existing->role !== $contact['access_role'] || $existing->status === 'revoked'
                        || ($existing->status === 'pending' && $account->hasVerifiedEmail())) {
                        $this->accountAccess->invite($studio, $actor, ['email' => $account->email, 'role' => $contact['access_role']]);
                    }
                }
            }

            if (($attributes['logo'] ?? null) instanceof UploadedFile) {
                $studio->logo_path = $attributes['logo']->storeAs(
                    "logos/studios/{$studio->uuid}",
                    'logo.'.$attributes['logo']->extension(),
                    config('uploads.public_disk'),
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
