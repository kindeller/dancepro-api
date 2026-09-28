<?php

namespace App\Features\StudioBilling\Services;

use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\Studios\Models\Studio;
use App\Models\User;

class StudioInvoiceAccess
{
    public function __construct(private readonly StudioCommunicationHistory $access) {}

    public function studio(User $user, Studio $studio): void
    {
        abort_unless($this->access->canView($user, $studio), 403);
    }

    public function invoice(User $user, Studio $studio, StudioInvoice $invoice): void
    {
        $this->studio($user, $studio);
        abort_unless($invoice->studio_id === $studio->id && $invoice->status !== 'draft', 404);
    }
}
