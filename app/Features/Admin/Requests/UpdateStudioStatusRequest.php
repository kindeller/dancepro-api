<?php

namespace App\Features\Admin\Requests;

use App\Features\Studios\Support\StudioStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudioStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageStudios') ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(StudioStatus::class)]];
    }
}
