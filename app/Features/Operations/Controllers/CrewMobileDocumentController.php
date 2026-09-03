<?php

namespace App\Features\Operations\Controllers;

use App\Features\Operations\Models\OperationalResource;
use App\Features\Operations\Requests\ListCrewMobileDocumentsRequest;
use App\Features\Operations\Services\CrewMobileDocuments;
use App\Features\Operations\Services\OperationsFileStorage;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrewMobileDocumentController extends Controller
{
    public function index(ListCrewMobileDocumentsRequest $request, CrewMobileDocuments $documents): JsonResponse
    {
        return ApiResponse::success('Documents returned.', $documents->list($request->validated('updated_since')));
    }

    public function download(Request $request, OperationalResource $document, CrewMobileDocuments $documents): JsonResponse
    {
        $document = $documents->authorised($document);
        $expiresAt = now()->addMinutes(5);

        return ApiResponse::success('Temporary download issued.', [
            'url' => URL::temporarySignedRoute('api.v1.documents.content', $expiresAt, ['document' => $document]),
            'expires_at' => $expiresAt->toIso8601String(),
            'checksum' => $documents->metadata($document)['checksum'],
        ]);
    }

    public function content(Request $request, OperationalResource $document, CrewMobileDocuments $documents, OperationsFileStorage $files): StreamedResponse
    {
        $document = $documents->authorised($document);
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        $filename = $document->title.($extension !== '' ? '.'.$extension : '');

        return $files->disk()->response($document->file_path, $filename, ['Cache-Control' => 'private, no-store'], 'attachment');
    }
}
