<?php

namespace App\Features\Programmes\Requests;

use Illuminate\Validation\Rule;

class SaveProgrammeDraftRequest extends ProgrammeVersionRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'nodes' => ['present', 'array', 'list', 'max:10000'],
            'nodes.*' => ['array:uuid,parent_uuid,kind,number,title,date,session,class_name,item_kind,source_page'],
            'nodes.*.uuid' => ['required', 'uuid', 'distinct:ignore_case'],
            'nodes.*.parent_uuid' => ['nullable', 'uuid'],
            'nodes.*.kind' => ['required', Rule::in(['day', 'section', 'group', 'item'])],
            'nodes.*.number' => ['nullable', 'string', 'max:12', 'regex:/^[0-9]+[a-zA-Z]?$/'],
            'nodes.*.title' => ['nullable', 'string', 'max:500'],
            'nodes.*.date' => ['nullable', 'date_format:Y-m-d'],
            'nodes.*.session' => ['nullable', 'string', 'max:150'],
            'nodes.*.class_name' => ['nullable', 'string', 'max:500'],
            'nodes.*.item_kind' => ['nullable', Rule::in(['dance', 'slideshow', 'presentation', 'finale', 'break', 'other'])],
            'nodes.*.source_page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
