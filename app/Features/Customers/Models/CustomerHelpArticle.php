<?php

namespace App\Features\Customers\Models;

use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class CustomerHelpArticle extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['draft' => 'array', 'is_published' => 'boolean', 'sort_order' => 'integer'];
    }
}
