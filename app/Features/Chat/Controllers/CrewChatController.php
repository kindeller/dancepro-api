<?php

namespace App\Features\Chat\Controllers;

use App\Features\Chat\Actions\PostDirectChatMessage;
use App\Features\Chat\Actions\StartDirectChat;
use App\Features\Chat\Models\DirectChatConversation;
use App\Features\Chat\Models\DirectChatMessage;
use App\Features\Chat\Requests\StartDirectChatRequest;
use App\Features\Chat\Requests\StoreChatMessageRequest;
use App\Features\Chat\Services\CrewChatInbox;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Operations\Actions\PostEventMessage;
use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrewChatController extends Controller
{
    public function index(Request $request, CrewChatInbox $inbox): View
    {
        return $this->view($request, $inbox);
    }

    public function event(Request $request, SchedulingEvent $schedulingEvent, CrewChatInbox $inbox): View
    {
        $event = $inbox->accessibleEvent($request->user(), $schedulingEvent);
        $inbox->markEventRead($request->user(), $event);
        $event->load(['messages.author', 'messages.reactions', 'shifts', 'studio', 'competitionContact']);

        return $this->view($request, $inbox, ['kind' => 'event', 'model' => $event]);
    }

    public function direct(Request $request, DirectChatConversation $conversation, CrewChatInbox $inbox): View
    {
        $conversation = $inbox->directConversation($request->user(), $conversation);
        $inbox->markDirectRead($request->user(), $conversation);
        $conversation->load(['messages.author', 'messages.reactions', 'participants.crewProfile']);

        return $this->view($request, $inbox, ['kind' => 'direct', 'model' => $conversation]);
    }

    public function start(StartDirectChatRequest $request, StartDirectChat $start): RedirectResponse
    {
        $recipient = CrewProfile::query()
            ->where('uuid', $request->string('recipient_profile_uuid')->toString())
            ->with('user')->firstOrFail()->user;
        $conversation = $start->execute($request->user(), $recipient);

        return redirect()->route('crew.chat.direct', $conversation);
    }

    public function postEvent(StoreChatMessageRequest $request, SchedulingEvent $schedulingEvent, CrewChatInbox $inbox, PostEventMessage $post): RedirectResponse
    {
        $event = $inbox->accessibleEvent($request->user(), $schedulingEvent);
        abort_if($event->cancelled_at !== null, 422, 'This event has been cancelled. New messages are disabled.');
        $post->execute($event, $request->user(), 'discussion', $request->input('body'), $request->file('attachment'));

        return redirect()->route('crew.chat.event', $event);
    }

    public function postDirect(StoreChatMessageRequest $request, DirectChatConversation $conversation, CrewChatInbox $inbox, PostDirectChatMessage $post): RedirectResponse
    {
        $conversation = $inbox->directConversation($request->user(), $conversation);
        $post->execute($conversation, $request->user(), $request->input('body'), $request->file('attachment'));

        return redirect()->route('crew.chat.direct', $conversation);
    }

    public function directAttachment(Request $request, DirectChatConversation $conversation, DirectChatMessage $message, CrewChatInbox $inbox, OperationsFileStorage $files): StreamedResponse
    {
        $conversation = $inbox->directConversation($request->user(), $conversation);
        abort_unless($message->direct_chat_conversation_id === $conversation->id && filled($message->attachment_path), 404);
        abort_unless($files->disk()->exists($message->attachment_path), 404);

        return $files->disk()->response(
            $message->attachment_path,
            $message->attachment_name ?: basename($message->attachment_path),
            ['Cache-Control' => 'private, no-store'],
            'attachment',
        );
    }

    private function view(Request $request, CrewChatInbox $inbox, ?array $selectedChat = null): View
    {
        $filter = in_array($request->string('filter')->toString(), ['all', 'unread', 'upcoming', 'events', 'direct'], true)
            ? $request->string('filter')->toString()
            : 'all';

        $chats = $inbox->conversations($request->user(), $filter);
        (new Collection($chats->where('kind', 'event')->pluck('model')->all()))
            ->loadMissing(['studio', 'competitionContact']);
        $chats = $chats->map(function (array $chat): array {
            if ($chat['kind'] === 'event') {
                $chat['title'] = $chat['model']->crewChatTitle();
            }

            return $chat;
        });

        return view('crew.chat.index', [
            'chats' => $chats,
            'crew' => $inbox->availableCrew($request->user()),
            'filter' => $filter,
            'selectedChat' => $selectedChat,
        ]);
    }
}
