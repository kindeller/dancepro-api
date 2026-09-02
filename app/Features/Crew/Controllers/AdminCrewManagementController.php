<?php

namespace App\Features\Crew\Controllers;

use App\Features\Crew\Actions\AwardCrewRecognition;
use App\Features\Crew\Actions\SaveRecognitionType;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRecognition;
use App\Features\Crew\Models\RecognitionType;
use App\Features\Crew\Requests\AwardCrewRecognitionRequest;
use App\Features\Crew\Requests\SaveRecognitionTypeRequest;
use App\Features\Crew\Support\RecognitionBadgeDesigns;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminCrewManagementController extends Controller
{
    public function recognitionsRewards(): View
    {
        Gate::authorize('manageCrew');

        return view('admin.crew-management.recognitions-rewards', [
            'types' => RecognitionType::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'crewProfiles' => CrewProfile::query()->with('user')->whereHas('user')->get()->sortBy(fn (CrewProfile $profile) => strtolower($profile->preferred_name ?: $profile->legal_name ?: $profile->user->name)),
            'events' => SchedulingEvent::query()->orderByDesc('event_date')->limit(100)->get(),
            'recognitions' => CrewRecognition::query()->with(['crewProfile.user', 'schedulingEvent', 'awardedBy'])->latest('awarded_on')->latest()->limit(100)->get(),
            'designs' => RecognitionBadgeDesigns::options(),
        ]);
    }

    public function storeRecognitionType(SaveRecognitionTypeRequest $request, SaveRecognitionType $save): RedirectResponse
    {
        $save->execute($request->validated());

        return back()->with('status', 'Recognition type added.');
    }

    public function updateRecognitionType(SaveRecognitionTypeRequest $request, RecognitionType $recognitionType, SaveRecognitionType $save): RedirectResponse
    {
        $save->execute($request->validated(), $recognitionType);

        return back()->with('status', 'Recognition type updated.');
    }

    public function awardRecognition(AwardCrewRecognitionRequest $request, AwardCrewRecognition $award): RedirectResponse
    {
        $recognitions = $award->execute($request->validated(), $request->user()->id);

        return back()->with('status', $recognitions->count().' '.str('recognition')->plural($recognitions->count()).' awarded.');
    }

    public function training(): View
    {
        Gate::authorize('manageCrew');

        return view('admin.crew-management.training');
    }
}
