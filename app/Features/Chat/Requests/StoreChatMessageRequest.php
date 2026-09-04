<?php

namespace App\Features\Chat\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCrew() ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:10000', 'required_without:attachment'],
            'attachment' => [
                'nullable', 'file', 'max:10240', 'required_without:body',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,txt,csv',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,text/csv',
            ],
        ];
    }
}
