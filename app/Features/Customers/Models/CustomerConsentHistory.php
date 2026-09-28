<?php

namespace App\Features\Customers\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerConsentHistory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'snapshot' => 'array'];
    }
}
