<?php

namespace App\Features\Notifications\Controllers;

use App\Features\Notifications\Actions\RegisterMobilePushDevice;
use App\Features\Notifications\Models\MobilePushDevice;
use App\Features\Notifications\Requests\RegisterMobilePushDeviceRequest;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrewMobilePushDeviceController extends Controller
{
    public function store(RegisterMobilePushDeviceRequest $request, RegisterMobilePushDevice $register): JsonResponse
    {
        return ApiResponse::success('Push device registered.', $this->resource(
            $register->execute($request->user(), $request->validated()),
        ));
    }

    public function destroy(Request $request, MobilePushDevice $device): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);
        $device->delete();

        return ApiResponse::success('Push device removed.');
    }

    private function resource(MobilePushDevice $device): array
    {
        return [
            'id' => $device->uuid,
            'installation_id' => $device->installation_id,
            'platform' => $device->platform,
            'environment' => $device->environment,
            'app_version' => $device->app_version,
            'device_name' => $device->device_name,
            'registered_at' => $device->last_registered_at->toIso8601String(),
        ];
    }
}
