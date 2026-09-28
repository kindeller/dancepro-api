<?php

namespace App\Features\Publicity\Controllers;

use App\Features\Publicity\Requests\PublicityJoinRequest;
use App\Features\Publicity\Services\PublicityEventAccess;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicityJoinController extends Controller
{
    public function scan(PublicityJoinRequest $request, SchedulingEvent $schedulingEvent, PublicityEventAccess $access): RedirectResponse
    {
        $access->validate($schedulingEvent, $request->validated('version'));
        $request->session()->put('publicity_join', ['event' => $schedulingEvent->uuid, 'name' => $schedulingEvent->name, 'version' => $request->validated('version')]);
        $request->session()->put('url.intended', route('publicity.join.complete'));

        return to_route($request->user() ? 'publicity.join.complete' : 'customer.login')
            ->with('status', 'Sign in to add '.$schedulingEvent->name.' to your media.');
    }

    public function studio(PublicityJoinRequest $request, Studio $studio, PublicityEventAccess $access): RedirectResponse
    {
        $access->validateStudio($studio, $request->validated('version'));
        $request->session()->put('publicity_join', ['studio' => $studio->uuid, 'name' => $studio->name, 'version' => $request->validated('version')]);
        $request->session()->put('url.intended', route('publicity.join.complete'));

        return to_route($request->user() ? 'publicity.join.complete' : 'customer.login')
            ->with('status', 'Sign in or create an account to add '.$studio->name.' photo galleries to your media.');
    }

    public function complete(Request $request, PublicityEventAccess $access): RedirectResponse
    {
        $pending = $request->session()->get('publicity_join');
        if (! $pending) {
            return to_route('customer.media.index');
        }
        $name = $access->add($request->user(), $pending);
        $request->session()->forget(['publicity_join', 'url.intended']);

        return to_route('customer.media.index')->with('status', $name.' added. Released media will appear here when available.');
    }
}
