<?php

namespace App\Features\Chat\Controllers;

use App\Features\Chat\Requests\UpdateMessageReactionRequest;
use App\Features\Chat\Services\CrewMobileChat;
use App\Features\Chat\Services\MessageReactions;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class MessageReactionController extends Controller
{
    public function update(UpdateMessageReactionRequest $request, string $chatId, string $message, MessageReactions $reactions, CrewMobileChat $chats): JsonResponse
    {
        return ApiResponse::success('Reaction saved.', ['reactions' => $reactions->update($request->user(), $chatId, $message, $request->validated('reaction'), $chats)]);
    }
}
