<?php

namespace App\Features\Customers\Requests;

class SearchProfileStudiosRequest extends CustomerCompetitionsRequest
{
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:255']];
    }
}
