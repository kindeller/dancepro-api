<?php

namespace App\Features\Customers\Observers;

use App\Features\Customers\Services\CustomerInboxSources;
use Illuminate\Database\Eloquent\Model;

class CustomerInboxSourceObserver
{
    public function saved(Model $source): void
    {
        app(CustomerInboxSources::class)->sync($source);
    }
}
