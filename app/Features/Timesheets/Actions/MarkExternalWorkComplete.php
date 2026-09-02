<?php

namespace App\Features\Timesheets\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Models\AssignmentTimeEntry;
use App\Features\Timesheets\Services\CrewInvoiceSelection;

class MarkExternalWorkComplete
{
    public function __construct(private readonly CrewInvoiceSelection $selection) {}

    public function execute(CrewProfile $crew, array $entryIds): int
    {
        $entries = $this->selection->resolve($crew, $entryIds)['entries'];

        AssignmentTimeEntry::query()->whereKey($entries->modelKeys())->update(['approval_status' => 'externally_invoiced']);

        return $entries->count();
    }
}
