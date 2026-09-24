<?php

namespace App\Features\Programmes\Requests;

use App\Features\Programmes\Policies\ProgrammePolicy;
use Illuminate\Validation\Rule;

class SyncProgrammeRequest extends SaveProgrammeDraftRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(ProgrammePolicy::class)->upload($this->user(), $this->route('schedulingEvent'));
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['version']);

        return array_replace($rules, [
            'revision_uuid' => ['required', 'uuid'],
            'base_plan_uuid' => ['nullable', 'uuid'],
            'nodes' => ['required', 'array', 'list', 'min:1', 'max:10000'],
            'nodes.*' => ['array:uuid,parent_uuid,kind,number,title,date,class_name,item_kind,folder_number,folder_title,merged_into_uuid'],
            'nodes.*.title' => ['required', 'string', 'max:500'],
            'nodes.*.item_kind' => ['nullable', Rule::in(['dance', 'routine', 'slideshow', 'presentation', 'finale', 'break', 'other'])],
            'nodes.*.folder_number' => ['nullable', 'string', 'max:12'],
            'nodes.*.folder_title' => ['nullable', 'string', 'max:500'],
            'nodes.*.merged_into_uuid' => ['nullable', 'uuid'],
        ]);
    }
}
