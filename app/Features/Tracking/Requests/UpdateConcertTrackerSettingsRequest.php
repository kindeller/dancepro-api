<?php

namespace App\Features\Tracking\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConcertTrackerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'rules' => ['required', 'array'],
            'rules.*.mode' => ['required', Rule::in(['disabled', 'review', 'automatic'])],
            'rules.*.offset_days' => ['required', 'integer', 'between:-365,365'],
            'rules.*.send_time' => ['required', 'date_format:H:i'],
            'unassigned_alert_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_admin', true)->where('is_active', true)],
            'escalation_after_days' => ['required', 'integer', 'between:1,30'],
            'editing_target_days' => ['required', 'integer', 'between:1,90'],
            'approval_target_days' => ['required', 'integer', 'between:1,90'],
            'email_business_name' => ['required', 'string', 'max:255'],
            'email_logo_url' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/'],
            'email_phone' => ['nullable', 'string', 'max:80'],
            'email_address' => ['nullable', 'string', 'max:255'],
            'email_website' => ['required', 'url:http,https', 'max:2048'],
            'email_reply_address' => ['nullable', 'email', 'max:255'],
            'email_legal_footer' => ['nullable', 'string', 'max:1000'],
            'signature_display_name' => ['required', 'string', 'max:255'],
            'signature_position' => ['nullable', 'string', 'max:120'],
            'signature_phone' => ['nullable', 'string', 'max:80'],
            'signature_sign_off' => ['nullable', 'string', 'max:255'],
            'signature_is_enabled' => ['nullable', 'boolean'],
        ];
    }
}
