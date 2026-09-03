<?php

namespace App\Features\Scheduling\Controllers;

use App\Features\Scheduling\Actions\MarkCrewNotificationRead;
use App\Features\Scheduling\Models\CrewNotification;
use App\Features\Scheduling\Requests\MarkCrewNotificationReadRequest;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobileNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 25), 1), 100);
        $page = CrewNotification::query()->where('user_id', $request->user()->id)
            ->latest('id')->cursorPaginate($limit);
        $items = collect($page->items())->map(fn (CrewNotification $notification): array => [
            'id' => $notification->uuid,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'deep_link' => null,
            'created_at' => $notification->created_at->toIso8601String(),
            'read' => $notification->read_at !== null,
        ]);

        return ApiResponse::success('Notifications returned.', $items, meta: [
            'next_cursor' => $page->nextCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function read(MarkCrewNotificationReadRequest $request, CrewNotification $notification, MarkCrewNotificationRead $markRead): JsonResponse
    {
        $markRead->execute($request->user(), $notification);

        return ApiResponse::success('Notification marked as read.');
    }
}
