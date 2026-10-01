<?php

namespace App\Features\Website\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWebsiteHelpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['action' => ['required', 'in:draft,publish,hide'], 'category' => ['required', 'string', 'max:100'], 'title' => ['required', 'string', 'max:255'], 'summary' => ['required', 'string', 'max:1000'], 'body' => ['required', 'string', 'max:20000'], 'keywords' => ['nullable', 'string', 'max:1000'], 'sort_order' => ['required', 'integer', 'min:0', 'max:9999']];
    }
}
