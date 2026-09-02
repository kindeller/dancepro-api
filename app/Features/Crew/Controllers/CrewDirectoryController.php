<?php

namespace App\Features\Crew\Controllers;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrewDirectoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $directory = in_array($request->string('view')->toString(), ['crew', 'competitions', 'studios'], true)
            ? $request->string('view')->toString()
            : 'crew';

        return view('crew.directory.index', [
            'directory' => $directory,
            'crew' => CrewProfile::query()->where('user_id', '!=', $request->user()->id)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->with('user')->orderBy('preferred_name')->get(),
            'competitions' => CompetitionContact::query()->where('is_active', true)
                ->with('staff')->orderBy('name')->get(),
            'studios' => Studio::query()->where('status', StudioStatus::Active)
                ->with('contacts')->orderBy('name')->get(),
        ]);
    }
}
