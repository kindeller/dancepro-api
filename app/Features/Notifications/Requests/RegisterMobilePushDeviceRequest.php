<?php

namespace App\Features\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMobilePushDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'installation_id' => ['required', 'uuid'],
            'platform' => ['required', Rule::in(['ios', 'android'])],
            'push_token' => ['required', 'string', 'max:4096'],
            'environment' => ['required', Rule::in(['development', 'production'])],
            'app_version' => ['required', 'string', 'max:50'],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }
}
