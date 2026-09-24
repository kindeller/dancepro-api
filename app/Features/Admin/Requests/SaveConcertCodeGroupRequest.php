<?php

namespace App\Features\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveConcertCodeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageConcerts') ?? false;
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'admin.concert-code-groups.store' => [
                'name' => ['required', 'string', 'max:120'],
                'year' => ['required', 'integer', 'between:1900,2200'],
                'code' => ['required', 'string', 'regex:/\A[A-Za-z0-9-]{6,64}\z/'],
                'concert_uuids' => ['required', 'array', 'min:1'],
                'concert_uuids.*' => ['required', 'uuid', 'distinct'],
            ],
            'admin.concert-code-groups.concerts' => [
                'version' => ['required', 'integer', 'min:1'],
                'concert_uuids' => ['required', 'array', 'min:1'],
                'concert_uuids.*' => ['required', 'uuid', 'distinct'],
            ],
            'admin.concert-code-groups.code' => [
                'version' => ['required', 'integer', 'min:1'],
                'code' => ['required', 'string', 'regex:/\A[A-Za-z0-9-]{6,64}\z/'],
            ],
            default => ['version' => ['required', 'integer', 'min:1']],
        };
    }
}
