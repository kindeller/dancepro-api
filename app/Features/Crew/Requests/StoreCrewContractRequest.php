<?php

namespace App\Features\Crew\Requests;

use App\Features\Crew\Support\CrewContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrewContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCrew') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:100', Rule::unique('crew_contracts')->where('name', $this->string('name')->toString())],
            'status' => ['required', Rule::enum(CrewContractStatus::class)],
            'effective_from' => ['nullable', 'date'],
            'content' => ['required', 'string', 'max:500000'],
        ];
    }
}
