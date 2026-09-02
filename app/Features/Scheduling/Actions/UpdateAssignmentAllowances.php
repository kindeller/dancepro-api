<?php

namespace App\Features\Scheduling\Actions;

use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Support\PaymentRateCatalog;

class UpdateAssignmentAllowances
{
    public function execute(SchedulingShiftAssignment $assignment, array $allowanceKeys): void
    {
        $assignment->loadMissing('shift.schedulingEvent');
        $allowedKeys = array_keys(PaymentRateCatalog::allowancesForEvent($assignment->shift->schedulingEvent->event_type->value));
        $allowanceKeys = array_values(array_intersect($allowanceKeys, $allowedKeys));

        $assignment->allowances()->whereNotIn('allowance_key', $allowanceKeys)->delete();
        foreach ($allowanceKeys as $key) {
            $assignment->allowances()->updateOrCreate(['allowance_key' => $key], ['quantity' => 1]);
        }
    }
}
