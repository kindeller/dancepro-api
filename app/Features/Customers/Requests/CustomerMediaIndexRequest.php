<?php

namespace App\Features\Customers\Requests;

use Illuminate\Validation\Rule;

class CustomerMediaIndexRequest extends CustomerCompetitionsRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'tab' => ['nullable', Rule::in(['events', 'purchases'])],
        ]);
    }
}
