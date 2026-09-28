<?php

namespace App\Features\Customers\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Actions\AddCustomerConcertVideo;
use App\Features\Customers\Actions\RegisterCustomerCompetition;
use App\Features\Customers\Actions\SaveCustomerMediaProfile;
use App\Features\Customers\Requests\AddConcertVideoRequest;
use App\Features\Customers\Requests\CustomerCompetitionGalleryRequest;
use App\Features\Customers\Requests\RegisterCustomerCompetitionRequest;
use App\Features\Customers\Requests\SaveCustomerMediaProfileRequest;
use App\Features\Customers\Services\CustomerConcertAccess;
use App\Features\Customers\Services\CustomerMobileCatalogue;
use App\Features\Customers\Services\CustomerRecommendedMedia;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerMobileMediaController extends Controller
{
    public function home(Request $request, CustomerMobileCatalogue $catalogue): JsonResponse
    {
        return ApiResponse::success('Customer media returned.', $catalogue->forUser($request->user()));
    }

    public function profile(SaveCustomerMediaProfileRequest $request, SaveCustomerMediaProfile $save, CustomerMobileCatalogue $catalogue): JsonResponse
    {
        $save->execute($request->user(), $request->validated());

        return ApiResponse::success('Profile updated.', $catalogue->forUser($request->user()));
    }

    public function competition(RegisterCustomerCompetitionRequest $request, RegisterCustomerCompetition $register): JsonResponse
    {
        $event = $register->execute($request->user(), $request->validated('event_uuid'), $request->validated('login_code'));

        return ApiResponse::success('Competition added.', ['event_uuid' => $event->uuid]);
    }

    public function concertCode(AddConcertVideoRequest $request, AddCustomerConcertVideo $add): JsonResponse
    {
        $count = $add->execute($request->user(), $request->validated('concert_code'), (int) $request->validated('year'));

        return ApiResponse::success('Concert video access added.', ['concerts_added' => $count]);
    }

    public function gallery(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, CompetitionCustomerPreview $preview): JsonResponse
    {
        $data = $preview->browse($schedulingEvent, $request->validated('folder'), 'api.v1.customer.competitions.image');
        $photos = $data['photos'];

        return ApiResponse::success('Competition gallery returned.', [
            'event' => ['uuid' => $schedulingEvent->uuid, 'title' => $data['title'], 'code' => $data['eventCode']],
            'selected' => $this->node($data['selected']),
            'breadcrumbs' => collect($data['breadcrumbs'])->map($this->node(...))->all(),
            'children' => collect($data['children'])->map($this->node(...))->all(),
            'photos' => $photos ? collect($photos->items())->map(fn ($photo) => ['uuid' => $photo->uuid,
                'thumbnail_url' => route('api.v1.customer.competitions.image', ['schedulingEvent' => $schedulingEvent,
                    'photo' => $photo->uuid, 'size' => 'thumbnail']),
                'preview_url' => route('api.v1.customer.competitions.image', ['schedulingEvent' => $schedulingEvent,
                    'photo' => $photo->uuid, 'size' => 'preview'])])->all() : [],
            'page' => $photos?->currentPage(), 'last_page' => $photos?->lastPage(),
            'cover_url' => $data['coverUrl'],
        ]);
    }

    public function image(CustomerCompetitionGalleryRequest $request, SchedulingEvent $schedulingEvent, string $photo, CompetitionCustomerPreview $preview): StreamedResponse
    {
        return $preview->image($schedulingEvent, $photo, $request->validated('size', 'preview'));
    }

    public function recommendation(Request $request, SchedulingEvent $schedulingEvent, string $item, string $photo, CustomerRecommendedMedia $recommended, CompetitionCustomerPreview $preview): StreamedResponse
    {
        abort_unless($recommended->allowsThumbnail($request->user(), $schedulingEvent, $item, $photo), 404);

        return $preview->image($schedulingEvent, $photo, 'thumbnail');
    }

    public function concert(Request $request, Concert $concert, CustomerConcertAccess $access): JsonResponse
    {
        abort_unless($access->allows($request->user(), $concert), 403);
        $concert->load(['mediaCollections' => fn ($query) => $query->where('status', 'published'),
            'mediaCollections.assets' => fn ($query) => $query->where('status', 'available')->where('is_visible', true)]);
        $canStream = $access->allowsStreaming($request->user(), $concert);

        return ApiResponse::success('Concert media returned.', [
            'event' => ['uuid' => $concert->uuid, 'name' => $concert->name, 'date' => $concert->event_date?->toDateString()],
            'can_stream' => $canStream, 'can_download' => true,
            'download_behaviour' => [
                'primary_destination' => 'encrypted_app_storage',
                'offline_playback' => true,
                'native_library_export' => true,
                'native_library_export_requires_user_action' => true,
                'repeat_download_requires_confirmation' => true,
            ],
            'videos' => $concert->mediaCollections->flatMap->assets->filter(fn ($asset) => $asset->media_type->value === 'video')
                ->map(fn ($asset) => ['uuid' => $asset->uuid, 'title' => $asset->display_name ?? $asset->original_filename,
                    'size_bytes' => $asset->size_bytes, 'offline_status' => 'not_downloaded',
                    'can_save_to_native_library' => true,
                    'playback_url' => $canStream ? route('api.v1.customer.concerts.playback', [$concert, $asset]) : null,
                    'download_url' => route('api.v1.customer.concerts.download', [$concert, $asset])])->values()->all(),
        ]);
    }

    private function node(?array $node): ?array
    {
        return $node ? ['uuid' => $node['uuid'], 'kind' => $node['kind'], 'label' => $node['label']] : null;
    }
}
