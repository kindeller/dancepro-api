<?php

namespace App\Features\Scheduling\Requests;

use App\Features\Scheduling\Support\PaymentRateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssignmentAllowancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'allowances' => ['nullable', 'array'],
            'allowances.*' => ['string', 'distinct', Rule::in(array_keys(PaymentRateCatalog::allowances()))],
        ];
    }
}
