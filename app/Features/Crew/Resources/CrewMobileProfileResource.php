<?php

namespace App\Features\Crew\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrewMobileProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'preferred_name' => $this->preferred_name,
            'legal_name' => $this->legal_name,
            'email' => $this->user->email,
            'phone' => $this->phone,
            'address' => [
                'line_1' => $this->address_line_1,
                'line_2' => $this->address_line_2,
                'suburb' => $this->suburb,
                'state' => $this->state,
                'postcode' => $this->postcode,
            ],
            'emergency_contact' => [
                'name' => $this->emergency_contact_name,
                'relationship' => $this->emergency_contact_relationship,
                'phone' => $this->emergency_contact_phone,
            ],
            'payment_details' => [
                'account_name' => $this->bank_account_name,
                'bank_name' => $this->bank_name,
                'bsb_last_four' => $this->lastFour($this->bank_bsb),
                'account_number_last_four' => $this->lastFour($this->bank_account_number),
                'complete' => collect([$this->bank_account_name, $this->bank_bsb, $this->bank_account_number])->every(fn ($value) => filled($value)),
            ],
            'compliance' => [
                'working_with_children_number' => $this->working_with_children_number,
                'working_with_children_expiry' => $this->working_with_children_expiry?->toDateString(),
                'first_aid_expiry' => $this->first_aid_expiry?->toDateString(),
            ],
            'vehicles' => $this->vehicles->map(fn ($vehicle): array => [
                'id' => $vehicle->uuid,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'registration' => $vehicle->registration,
                'colour' => $vehicle->colour,
                'notes' => $vehicle->notes,
            ])->values(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    private function lastFour(?string $value): ?string
    {
        $normalised = preg_replace('/\s|-/', '', (string) $value);

        return filled($normalised) ? substr($normalised, -4) : null;
    }
}
