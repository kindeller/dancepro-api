<?php

namespace App\Features\Customers\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLoginEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['successful' => 'boolean', 'risk_reasons' => 'array', 'occurred_at' => 'datetime'];
    }
}
