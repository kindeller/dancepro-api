<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerSupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['general', 'download_reactivation'])],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'order_media_entitlement_id' => ['nullable', 'integer', 'required_if:type,download_reactivation', 'exists:order_media_entitlements,id'],
            'event_uuid' => ['nullable', 'uuid'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,txt', 'max:10240'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
