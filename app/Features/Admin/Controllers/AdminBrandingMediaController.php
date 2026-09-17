<?php

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Actions\ManageBrandingMedia;
use App\Features\Admin\Requests\ConfirmBrandingDeletionRequest;
use App\Features\Admin\Requests\UploadBrandingMediaRequest;
use App\Features\Concerts\Models\Concert;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminBrandingMediaController extends Controller
{
    public function uploadStudioCover(UploadBrandingMediaRequest $request, Studio $studio, ManageBrandingMedia $media): RedirectResponse
    {
        Gate::authorize('manageStudios');
        $media->upload($studio, 'cover', $request->file('file'));

        return back()->with('status', 'Studio cover uploaded.');
    }

    public function uploadConcertCover(UploadBrandingMediaRequest $request, Concert $concert, ManageBrandingMedia $media): RedirectResponse
    {
        Gate::authorize('manageConcerts');
        $media->upload($concert, 'cover', $request->file('file'));

        return back()->with('status', 'Concert cover uploaded.');
    }

    public function uploadProgram(UploadBrandingMediaRequest $request, Concert $concert, ManageBrandingMedia $media): RedirectResponse
    {
        Gate::authorize('manageConcerts');
        $media->upload($concert, 'program', $request->file('file'));

        return back()->with('status', 'Concert program uploaded.');
    }

    public function confirmStudioClear(Studio $studio, ManageBrandingMedia $media): View
    {
        Gate::authorize('manageStudios');

        return $this->confirmation($studio, 'cover', $media, route('admin.branding.studios.cover.destroy', $studio));
    }

    public function confirmConcertCoverClear(Concert $concert, ManageBrandingMedia $media): View
    {
        Gate::authorize('manageConcerts');

        return $this->confirmation($concert, 'cover', $media, route('admin.branding.concerts.cover.destroy', $concert));
    }

    public function confirmProgramClear(Concert $concert, ManageBrandingMedia $media): View
    {
        Gate::authorize('manageConcerts');

        return $this->confirmation($concert, 'program', $media, route('admin.branding.concerts.program.destroy', $concert));
    }

    public function clearStudioCover(ConfirmBrandingDeletionRequest $request, Studio $studio, ManageBrandingMedia $media): RedirectResponse
    {
        Gate::authorize('manageStudios');
        $media->clear($studio, 'cover', $request->validated('digest'));

        return redirect()->route('admin.studios.edit', $studio)->with('status', 'Studio cover cleared.');
    }

    public function clearConcertCover(ConfirmBrandingDeletionRequest $request, Concert $concert, ManageBrandingMedia $media): RedirectResponse
    {
        Gate::authorize('manageConcerts');
        $media->clear($concert, 'cover', $request->validated('digest'));

        return redirect()->route('admin.concerts.edit', $concert)->with('status', 'Concert cover cleared.');
    }

    public function clearProgram(ConfirmBrandingDeletionRequest $request, Concert $concert, ManageBrandingMedia $media): RedirectResponse
    {
        Gate::authorize('manageConcerts');
        $media->clear($concert, 'program', $request->validated('digest'));

        return redirect()->route('admin.concerts.edit', $concert)->with('status', 'Concert program cleared.');
    }

    private function confirmation(Studio|Concert $owner, string $kind, ManageBrandingMedia $media, string $action): View
    {
        [$urlField, $keyField] = $media->fields($kind);
        abort_unless($owner->{$urlField}, 404);

        return view('admin.branding.confirm-delete', [
            'owner' => $owner, 'kind' => $kind, 'disk' => $media->disk(),
            'key' => $owner->{$keyField}, 'digest' => $media->digest($owner, $kind),
            'action' => $action,
            'cancel' => route($owner instanceof Studio ? 'admin.studios.edit' : 'admin.concerts.edit', $owner),
        ]);
    }
}
