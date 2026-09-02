<?php

namespace App\Features\Scheduling\Requests;

use App\Features\Scheduling\Support\PaymentRateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'rate_key' => ['required', Rule::in(array_keys(PaymentRateCatalog::all()))],
            'crew_profile_id' => ['nullable', 'integer', 'exists:crew_profiles,id'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'effective_from' => ['required', 'date'],
            'is_superable' => ['nullable', 'boolean'],
        ];
    }
}
