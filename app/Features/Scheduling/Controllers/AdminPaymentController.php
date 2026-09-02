<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Scheduling\Actions\SavePayRateVersion;
use App\Features\Scheduling\Actions\UpdateAssignmentAllowances;
use App\Features\Scheduling\Models\PayRate;
use App\Features\Scheduling\Models\SchedulingShiftAssignment;
use App\Features\Scheduling\Requests\SaveCrewPayRateMatrixRequest;
use App\Features\Scheduling\Requests\StorePayRateRequest;
use App\Features\Scheduling\Requests\UpdateAssignmentAllowancesRequest;
use App\Features\Scheduling\Support\PaymentRateCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminPaymentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageScheduling');
        $crew = CrewProfile::query()->with('user')->whereHas('user', fn ($query) => $query->where('is_active', true))->orderBy('preferred_name')->get();
        $rates = PayRate::query()->whereIn('crew_profile_id', $crew->pluck('id'))
            ->whereDate('effective_from', '<=', today())
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))
            ->orderByDesc('effective_from')->get()
            ->unique(fn (PayRate $rate): string => $rate->crew_profile_id.'|'.$rate->rate_key)
            ->keyBy(fn (PayRate $rate): string => $rate->crew_profile_id.'|'.$rate->rate_key);

        return view('admin.payments.index', ['catalog' => PaymentRateCatalog::matrix(), 'crew' => $crew, 'rates' => $rates]);
    }

    public function storeMatrix(SaveCrewPayRateMatrixRequest $request, SavePayRateVersion $saveRate): RedirectResponse
    {
        $catalog = PaymentRateCatalog::matrix();
        $validatedRates = $request->validated('rates');
        $crewIds = CrewProfile::query()->whereIn('id', array_keys($validatedRates))->pluck('id')->all();

        foreach ($validatedRates as $crewProfileId => $rates) {
            if (! in_array((int) $crewProfileId, $crewIds, true)) {
                continue;
            }
            foreach ($rates as $matrixKey => $amount) {
                if ($amount === null || $amount === '' || ! isset($catalog[$matrixKey])) {
                    continue;
                }
                foreach ($catalog[$matrixKey][2] as $rateKey) {
                    $saveRate->execute([
                        'crew_profile_id' => (int) $crewProfileId,
                        'rate_key' => $rateKey,
                        'amount' => $amount,
                        'effective_from' => $request->validated('effective_from'),
                        'is_superable' => PaymentRateCatalog::all()[$rateKey][2],
                    ]);
                }
            }
        }

        return back()->with('status', 'Crew rates saved.');
    }

    public function storeRate(StorePayRateRequest $request, SavePayRateVersion $saveRate): RedirectResponse
    {
        $rate = $saveRate->execute($request->validated());

        return back()->with('status', $rate->name.' saved.');
    }

    public function updateAllowances(UpdateAssignmentAllowancesRequest $request, SchedulingShiftAssignment $assignment, UpdateAssignmentAllowances $updateAllowances): RedirectResponse
    {
        $updateAllowances->execute($assignment, $request->validated('allowances', []));

        return back()->with('status', 'Allowances updated.');
    }
}
