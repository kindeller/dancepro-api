<?php

namespace App\Features\Timesheets\Actions;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Timesheets\Models\CrewInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignCrewInvoiceNumber
{
    public function execute(CrewInvoice $invoice, ?int $startingNumber): CrewInvoice
    {
        return DB::transaction(function () use ($invoice, $startingNumber): CrewInvoice {
            /** @var CrewProfile $crew */
            $crew = CrewProfile::query()->lockForUpdate()->findOrFail($invoice->crew_profile_id);
            $requiredDetails = [
                'legal_name' => 'legal name', 'address_line_1' => 'street address', 'suburb' => 'suburb',
                'state' => 'state', 'postcode' => 'postcode', 'phone' => 'phone number', 'abn' => 'ABN',
                'bank_account_name' => 'bank account name', 'bank_name' => 'bank', 'bank_bsb' => 'BSB',
                'bank_account_number' => 'bank account number',
            ];
            $missing = collect($requiredDetails)->filter(fn ($label, $field) => blank($crew->{$field}))->values();
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(['invoice_details' => 'Complete these Payment Details in My Profile before submitting: '.$missing->join(', ').'.']);
            }
            $number = $crew->next_invoice_number ?? $startingNumber;
            if (! $number || $number < 1) {
                throw ValidationException::withMessages(['starting_invoice_number' => 'Enter the next unused number from your existing invoice sequence.']);
            }
            if (CrewInvoice::query()->where('crew_profile_id', $crew->id)->where('invoice_number', (string) $number)->whereKeyNot($invoice->id)->exists()) {
                throw ValidationException::withMessages(['starting_invoice_number' => 'That invoice number has already been used. Choose your next unused number.']);
            }
            $invoice->update(['invoice_number' => (string) $number]);
            $crew->update(['next_invoice_number' => $number + 1]);

            return $invoice;
        });
    }
}
