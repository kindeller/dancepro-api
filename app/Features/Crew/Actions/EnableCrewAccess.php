<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnableCrewAccess
{
    public function execute(string $email): CrewProfile
    {
        return DB::transaction(function () use ($email): CrewProfile {
            $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->lockForUpdate()->first();

            if (! $user || ! $user->is_active || $user->merged_into_user_id !== null) {
                throw ValidationException::withMessages(['existing_email' => 'Enter the email address of an existing active account.']);
            }

            if ($user->crewProfile()->exists()) {
                throw ValidationException::withMessages(['existing_email' => 'This account already has My Hub access. Edit their existing crew record instead.']);
            }

            return $user->crewProfile()->create(['preferred_name' => $user->name]);
        });
    }
}
