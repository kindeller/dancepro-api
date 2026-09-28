<?php

namespace App\Features\Orders\Requests;

use App\Features\Orders\Support\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['orders', 'refunds', 'issues'])],
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'source' => ['nullable', Rule::in(['customer_cart', 'complimentary_media_grant'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
