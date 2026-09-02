<?php

namespace App\Features\Chat\Controllers;

use App\Features\Chat\Actions\PostDirectChatMessage;
use App\Features\Chat\Actions\StartDirectChat;
use App\Features\Chat\Models\DirectChatConversation;
use App\Features\Chat\Requests\StartDirectChatRequest;
use App\Features\Chat\Requests\StoreChatMessageRequest;
use App\Features\Chat\Services\CrewChatInbox;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Operations\Actions\PostEventMessage;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $event->load(['messages.author', 'shifts']);

        return $this->view($request, $inbox, ['kind' => 'event', 'model' => $event]);
    }

    public function direct(Request $request, DirectChatConversation $conversation, CrewChatInbox $inbox): View
    {
        $conversation = $inbox->directConversation($request->user(), $conversation);
        $inbox->markDirectRead($request->user(), $conversation);
        $conversation->load(['messages.author', 'participants.crewProfile']);

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
        $post->execute($event, $request->user(), 'discussion', $request->string('body')->toString(), null);

        return redirect()->route('crew.chat.event', $event);
    }

    public function postDirect(StoreChatMessageRequest $request, DirectChatConversation $conversation, CrewChatInbox $inbox, PostDirectChatMessage $post): RedirectResponse
    {
        $conversation = $inbox->directConversation($request->user(), $conversation);
        $post->execute($conversation, $request->user(), $request->string('body')->toString());

        return redirect()->route('crew.chat.direct', $conversation);
    }

    private function view(Request $request, CrewChatInbox $inbox, ?array $selectedChat = null): View
    {
        $filter = in_array($request->string('filter')->toString(), ['all', 'unread', 'upcoming', 'events', 'direct'], true)
            ? $request->string('filter')->toString()
            : 'all';

        return view('crew.chat.index', [
            'chats' => $inbox->conversations($request->user(), $filter),
            'crew' => $inbox->availableCrew($request->user()),
            'filter' => $filter,
            'selectedChat' => $selectedChat,
        ]);
    }
}
