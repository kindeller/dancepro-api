<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Requests\CustomerCompetitionsRequest;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Models\StudioConversationAttachment;
use App\Features\Tracking\Requests\SendStudioConversationMessageRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerStudioCommunicationController extends Controller
{
    public function __invoke(CustomerCompetitionsRequest $request, Studio $studio, StudioCommunicationHistory $history): Response
    {
        return response()->view('customers.studios.communications', $history->forUser($request->user(), $studio))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(SendStudioConversationMessageRequest $request, Studio $studio, StudioCommunicationHistory $history): RedirectResponse
    {
        $history->reply($request->user(), $studio, $request->validated());

        return back()->with('status', 'Your reply has been added to the conversation.');
    }

    public function attachment(CustomerCompetitionsRequest $request, Studio $studio, StudioConversationAttachment $attachment, StudioCommunicationHistory $history): StreamedResponse
    {
        abort_unless($attachment->message?->studio_id === $studio->id && $history->canView($request->user(), $studio), 403);

        return Storage::disk($attachment->disk)->download($attachment->storage_key, $attachment->original_name, ['Cache-Control' => 'private, no-store']);
    }
}
