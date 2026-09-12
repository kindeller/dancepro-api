<?php

namespace App\Features\Crew\Actions;

use App\Features\Crew\Models\CrewProfile;

class RevealCrewPaymentDetails
{
    /** @return array{account_name: ?string, bank_name: ?string, bsb_last_four: ?string, account_number_last_four: ?string, complete: bool} */
    public function execute(CrewProfile $profile): array
    {
        return [
            'account_name' => $profile->bank_account_name,
            'bank_name' => $profile->bank_name,
            'bsb_last_four' => $this->lastFour($profile->bank_bsb),
            'account_number_last_four' => $this->lastFour($profile->bank_account_number),
            'complete' => collect([$profile->bank_account_name, $profile->bank_bsb, $profile->bank_account_number])
                ->every(fn ($value): bool => filled($value)),
        ];
    }

    private function lastFour(?string $value): ?string
    {
        $normalised = preg_replace('/\s|-/', '', (string) $value);

        return filled($normalised) ? substr($normalised, -4) : null;
    }
}
