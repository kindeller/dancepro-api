<?php

namespace App\Features\Customers\Observers;

use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Services\CustomerConversations;

class CustomerSupportRequestObserver
{
    public function saved(CustomerSupportRequest $request): void
    {
        app(CustomerConversations::class)->syncHistory($request);
    }
}
