<?php

namespace App\Features\Programmes\Requests;

use App\Features\Programmes\Policies\ProgrammePolicy;
use Illuminate\Foundation\Http\FormRequest;

class ProgrammeVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(ProgrammePolicy::class)->update($this->user(), $this->route('schedulingEvent'));
    }

    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1']];
    }
}
