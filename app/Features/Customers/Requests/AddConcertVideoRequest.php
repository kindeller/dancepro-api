<?php

namespace App\Features\Customers\Requests;

class AddConcertVideoRequest extends CustomerCompetitionsRequest
{
    public function rules(): array
    {
        return ['year' => ['required', 'integer', 'between:1900,2200'], 'concert_code' => ['required', 'string', 'max:255']];
    }
}
