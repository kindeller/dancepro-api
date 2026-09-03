<?php

namespace App\Features\Timesheets\Support;

use App\Features\Crew\Models\CrewProfile;

final class InvoiceIssuerSnapshot
{
    /**
     * @return array<string, string|null>
     */
    public static function fromCrewProfile(CrewProfile $profile): array
    {
        return [
            'legal_name' => $profile->legal_name,
            'address_line_1' => $profile->address_line_1,
            'address_line_2' => $profile->address_line_2,
            'suburb' => $profile->suburb,
            'state' => $profile->state,
            'postcode' => $profile->postcode,
            'phone' => $profile->phone,
            'abn' => $profile->abn,
            'bank_account_name' => $profile->bank_account_name,
            'bank_name' => $profile->bank_name,
            'bank_bsb' => $profile->bank_bsb,
            'bank_account_number' => $profile->bank_account_number,
        ];
    }
}
