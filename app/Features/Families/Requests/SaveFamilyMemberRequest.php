<?php

namespace App\Features\Families\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFamilyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomer() && $this->user()->hasVerifiedEmail();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email'))),
            'first_name' => trim((string) $this->input('first_name')), 'last_name' => trim((string) $this->input('last_name'))]);
        if (is_string($this->input('programme_names'))) {
            $this->merge(['programme_names' => array_values(array_filter(array_map('trim', preg_split('/\R/', $this->input('programme_names')))))]);
        }
    }

    public function rules(): array
    {
        $editing = $this->input('action') === 'save';

        return [
            'action' => ['required', Rule::in(['save', 'manager', 'invite', 'cancel', 'remove', 'leave'])],
            'member_uuid' => ['nullable', 'uuid'],
            'first_name' => [Rule::excludeIf(! $editing), 'required', 'string', 'max:255'],
            'last_name' => [Rule::excludeIf(! $editing), 'required', 'string', 'max:255'],
            'email' => [Rule::excludeIf(! $editing), Rule::requiredIf($editing && ($this->boolean('can_view_media') || $this->boolean('can_purchase'))), 'nullable', 'email:rfc', 'max:255'],
            'can_view_media' => ['sometimes', 'boolean'], 'can_purchase' => ['sometimes', 'boolean'], 'is_dancer' => ['sometimes', 'boolean'],
            'programme_names' => [Rule::excludeIf(! in_array($this->input('action'), ['save', 'manager'], true)), 'nullable', 'array', 'max:10'],
            'programme_names.*' => ['string', 'max:255'],
        ];
    }
}
