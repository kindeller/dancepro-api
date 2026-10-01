<?php

namespace App\Features\Messaging\Controllers;

use App\Features\Chat\Services\CrewMobileChat;
use App\Features\Messaging\Requests\MessageCentreRequest;
use App\Features\Messaging\Requests\SendCentreMessageRequest;
use App\Features\Messaging\Services\MessageCentre;
use App\Features\Messaging\Services\SendCentreMessage;
use App\Features\Operations\Services\OperationsFileStorage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminMessageCentreController extends Controller
{
    public function index(MessageCentreRequest $request, MessageCentre $centre)
    {
        return response()->view('admin.messages.index', $centre->page($request->user(), $request->validated()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function send(SendCentreMessageRequest $request, SendCentreMessage $messages)
    {
        $destination = $messages->send($request->user(), $request->validated());

        return redirect()->route('admin.messages.index', $destination)->with('status', 'Message added to the conversation.');
    }

    public function attachment(Request $request, string $chat, string $message, CrewMobileChat $chats, OperationsFileStorage $files)
    {
        [, $conversation] = $chats->resolve($request->user(), $chat);
        $message = $conversation->messages()->where('uuid', $message)->firstOrFail();
        abort_unless($message->attachment_path, 404);
        abort_unless($files->disk()->exists($message->attachment_path), 404);

        return $files->disk()->download($message->attachment_path, $message->attachment_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
