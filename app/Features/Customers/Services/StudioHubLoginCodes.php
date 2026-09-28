<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Studios\Models\Studio;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class StudioHubLoginCodes
{
    public function photo(Studio $studio): ?array
    {
        return $this->code($studio, 'gallery_code_hash', 'gallery_code_encrypted', 'Photo gallery login code');
    }

    public function video(?Concert $concert, Studio $studio): ?array
    {
        if (! $concert || $concert->studio_id !== $studio->id) {
            return null;
        }
        if ($concert->concert_code_group_id) {
            $group = $concert->codeGroup;

            return $group && $group->studio_id === $studio->id
                ? $this->code($group, 'code_hash', 'code_encrypted', 'Concert video login code') : null;
        }

        return $this->code($concert, 'access_password_hash', 'access_password_encrypted', 'Concert video login code');
    }

    private function code(Model $model, string $hashField, string $encryptedField, string $label): ?array
    {
        if (! $model->{$hashField}) {
            return null;
        }
        try {
            $code = $model->{$encryptedField};
        } catch (DecryptException) {
            $code = null;
        }

        return ['label' => $label, 'value' => filled($code) && Hash::check($code, $model->{$hashField}) ? $code : null];
    }
}
