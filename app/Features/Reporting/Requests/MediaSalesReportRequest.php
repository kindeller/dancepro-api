<?php

namespace App\Features\Reporting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaSalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['overview', 'customers', 'products', 'events', 'discounts', 'downloads', 'loyalty', 'orders'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'kind' => ['nullable', Rule::in(['competition', 'concert'])],
            'customer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
