<?php

namespace App\Features\Chat\Controllers;

use App\Features\Chat\Actions\PostDirectChatMessage;
use App\Features\Chat\Requests\MarkChatReadRequest;
use App\Features\Chat\Requests\StoreChatMessageRequest;
use App\Features\Chat\Services\CrewMobileChat;
use App\Features\Operations\Actions\PostEventMessage;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileChatController extends Controller
{
    public function index(Request $request, CrewMobileChat $chat): JsonResponse
    {
        $filter = in_array($request->string('filter')->toString(), ['all', 'unread', 'upcoming', 'events', 'direct'], true)
            ? $request->string('filter')->toString() : 'all';
        $limit = min(max($request->integer('limit', 25), 1), 100);
        $result = $chat->conversations($request->user(), $filter, $limit, $request->query('cursor'));

        return ApiResponse::success('Chats returned.', $result['items'], meta: [
            'next_cursor' => $result['next_cursor'],
            'has_more' => $result['has_more'],
        ]);
    }

    public function messages(Request $request, string $chatId, CrewMobileChat $chat): JsonResponse
    {
        $limit = min(max($request->integer('limit', 50), 1), 100);
        $result = $chat->messages($request->user(), $chatId, $limit);
        $page = $result['page'];

        return ApiResponse::success('Messages returned.', collect($page->items())->map($chat->messageResource(...)), meta: [
            'next_cursor' => $page->nextCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function store(StoreChatMessageRequest $request, string $chatId, CrewMobileChat $chat, PostEventMessage $postEvent, PostDirectChatMessage $postDirect): JsonResponse
    {
        [$kind, $conversation] = $chat->resolve($request->user(), $chatId);
        $message = $kind === 'event'
            ? $postEvent->execute($conversation, $request->user(), 'discussion', $request->string('body')->toString(), null)
            : $postDirect->execute($conversation, $request->user(), $request->string('body')->toString());
        $message->load('author.crewProfile');

        return ApiResponse::success('Message created.', $chat->messageResource($message), 201);
    }

    public function read(MarkChatReadRequest $request, string $chatId, CrewMobileChat $chat): JsonResponse
    {
        $chat->markRead($request->user(), $chatId, $request->validated('through_message'));

        return ApiResponse::success('Chat marked as read.');
    }
}
