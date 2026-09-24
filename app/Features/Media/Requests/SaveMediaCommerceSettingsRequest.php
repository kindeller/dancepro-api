<?php

namespace App\Features\Media\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMediaCommerceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        $rules = ['section' => ['required', 'in:prices,discounts,watermarks,notifications,help']];
        foreach (['competition_photo', 'competition_routine_photos', 'competition_routine_video', 'competition_routine_package', 'concert_photo'] as $key) {
            $rules[$key] = ['required_if:section,prices', 'numeric', 'min:1', 'max:10000', 'decimal:0,2'];
        }
        for ($tier = 1; $tier <= 4; $tier++) {
            $rules["bulk_{$tier}_threshold"] = ['required_if:section,discounts', 'numeric', 'min:1', 'max:100000', 'decimal:0,2'];
            $rules["bulk_{$tier}_percent"] = ['required_if:section,discounts', 'integer', 'min:0', 'max:90'];
        }

        return $rules + [
            'upsell_enabled' => ['sometimes', 'boolean'],
            'watermark_text' => ['required_if:section,watermarks', 'string', 'max:100'],
            'competition_watermark' => ['nullable', 'file', 'mimes:png', 'max:10240', 'dimensions:max_width=6000,max_height=6000'],
            'notification_template' => ['required_if:section,notifications', 'in:receipt,ready,expiry'],
            'receipt_email_subject' => ['required_if:notification_template,receipt', 'string', 'max:160'],
            'receipt_email_body' => ['required_if:notification_template,receipt', 'string', 'max:3000'],
            'ready_email_subject' => ['required_if:notification_template,ready', 'string', 'max:160'],
            'ready_email_body' => ['required_if:notification_template,ready', 'string', 'max:3000'],
            'expiry_email_subject' => ['required_if:notification_template,expiry', 'string', 'max:160'],
            'expiry_email_body' => ['required_if:notification_template,expiry', 'string', 'max:3000'],
            'help_content' => ['required_if:section,help', 'string', 'max:10000'],
        ];
    }
}
