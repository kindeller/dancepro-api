<?php

namespace App\Features\Publicity\Requests;

use App\Features\Media\Requests\AdminTopPicsRequest;

class PairScreenRequest extends AdminTopPicsRequest
{
    public function rules(): array
    {
        return ['code' => ['required', 'regex:/^[0-9]{6}$/'], 'name' => ['required', 'string', 'max:100']];
    }
}
