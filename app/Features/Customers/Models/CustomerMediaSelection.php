<?php

namespace App\Features\Customers\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMediaSelection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_favourite' => 'boolean', 'in_cart' => 'boolean'];
    }
}
