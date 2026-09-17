<?php

namespace App\Features\Media\Controllers;

use App\Features\Media\Actions\ManageMediaUploads;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaUpload;
use App\Features\Media\Requests\AbortMultipartUploadRequest;
use App\Features\Media\Requests\CompleteMultipartUploadRequest;
use App\Features\Media\Requests\CompleteUploadBatchRequest;
use App\Features\Media\Requests\CreateMultipartPartsRequest;
use App\Features\Media\Requests\CreateUploadUrlsRequest;
use App\Features\Media\Requests\FinalizeMediaAssetRequest;
use App\Features\Media\Requests\StartMultipartUploadRequest;
use App\Features\Media\Resources\MediaAssetResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MediaUploadController extends Controller
{
    public function show(MediaAsset $asset, MediaUpload $upload): JsonResponse
    {
        Gate::authorize('view', $asset);
        abort_unless($upload->media_asset_id === $asset->id, 404);

        return ApiResponse::success('Media upload returned.', [
            'upload_uuid' => $upload->uuid,
            'kind' => $upload->kind,
            'status' => $upload->status,
            'relative_path' => $upload->relative_path,
            'expires_at' => $upload->expires_at,
            'completed_at' => $upload->completed_at,
            'aborted_at' => $upload->aborted_at,
        ]);
    }

    public function uploadUrls(CreateUploadUrlsRequest $request, MediaAsset $asset, ManageMediaUploads $uploads): JsonResponse
    {
        Gate::authorize('upload', $asset);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success('Upload requests created.', $uploads->createUploadBatch(
            $asset,
            $user,
            $request->array('files'),
            (string) $request->header('Idempotency-Key'),
        ), 201);
    }

    public function completeUploadBatch(
        CompleteUploadBatchRequest $request,
        MediaAsset $asset,
        MediaUpload $upload,
        ManageMediaUploads $uploads,
    ): JsonResponse {
        Gate::authorize('upload', $asset);
        /** @var User $user */
        $user = $request->user();
        $upload = $uploads->completeUploadBatch($asset, $upload, $user);

        return ApiResponse::success('Upload batch completed.', [
            'upload_batch_uuid' => $upload->uuid,
            'status' => $upload->status,
            'completed_at' => $upload->completed_at,
        ]);
    }

    public function startMultipart(StartMultipartUploadRequest $request, MediaAsset $asset, ManageMediaUploads $uploads): JsonResponse
    {
        Gate::authorize('upload', $asset);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success('Multipart upload started.', $uploads->startMultipart(
            $asset,
            $user,
            $request->validated(),
            (string) $request->header('Idempotency-Key'),
        ), 201);
    }

    public function multipartParts(
        CreateMultipartPartsRequest $request,
        MediaAsset $asset,
        MediaUpload $upload,
        ManageMediaUploads $uploads,
    ): JsonResponse {
        Gate::authorize('upload', $asset);

        return ApiResponse::success('Multipart part requests created.', $uploads->createPartRequests($asset, $upload, $request->array('parts')));
    }

    public function completeMultipart(
        CompleteMultipartUploadRequest $request,
        MediaAsset $asset,
        MediaUpload $upload,
        ManageMediaUploads $uploads,
    ): JsonResponse {
        Gate::authorize('upload', $asset);
        /** @var User $user */
        $user = $request->user();
        $upload = $uploads->completeMultipart(
            $asset,
            $upload,
            $user,
            $request->array('parts'),
            $request->string('checksum_crc64nvme')->toString(),
        );

        return ApiResponse::success('Multipart upload completed.', [
            'upload_uuid' => $upload->uuid,
            'relative_path' => $upload->relative_path,
            'status' => $upload->status,
            'completed_at' => $upload->completed_at,
        ]);
    }

    public function abortMultipart(
        AbortMultipartUploadRequest $request,
        MediaAsset $asset,
        MediaUpload $upload,
        ManageMediaUploads $uploads,
    ): JsonResponse {
        Gate::authorize('upload', $asset);
        /** @var User $user */
        $user = $request->user();
        $upload = $uploads->abortMultipart($asset, $upload, $user);

        return ApiResponse::success('Multipart upload aborted.', [
            'upload_uuid' => $upload->uuid,
            'status' => $upload->status,
            'aborted_at' => $upload->aborted_at,
        ]);
    }

    public function finalize(FinalizeMediaAssetRequest $request, MediaAsset $asset, ManageMediaUploads $uploads): JsonResponse
    {
        Gate::authorize('upload', $asset);
        /** @var User $user */
        $user = $request->user();
        $asset = $uploads->finalize($asset, $user, $request->array('expected_outputs'));

        return ApiResponse::success('Media asset finalised.', new MediaAssetResource($asset->load('collection')));
    }
}
