<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Notifications\CrewInvitation;
use App\Features\Customers\Support\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteCrewMember
{
    public function execute(array $data): CrewProfile
    {
        $crewProfile = DB::transaction(function () use ($data): CrewProfile {
            $user = User::query()->create([
                'name' => $data['preferred_name'],
                'email' => $data['email'],
                'password' => Str::random(48),
                'type' => UserType::Crew->value,
                'is_active' => true,
                'is_admin' => false,
            ]);

            return CrewProfile::query()->create([
                'user_id' => $user->id,
                'preferred_name' => $data['preferred_name'],
            ]);
        });

        if ($data['send_invitation']) {
            $this->send($crewProfile);
        }

        return $crewProfile->refresh();
    }

    public function send(CrewProfile $crewProfile): void
    {
        $user = $crewProfile->user;
        $user->notify(new CrewInvitation(Password::broker()->createToken($user)));
        $user->forceFill(['invitation_sent_at' => now()])->save();
    }
}
