<?php

namespace App\Features\Media\Controllers;

use App\Features\Media\Actions\AcceptLocalMediaUpload;
use App\Features\Media\Requests\LocalMediaUploadRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class LocalMediaUploadController extends Controller
{
    public function object(LocalMediaUploadRequest $request, AcceptLocalMediaUpload $uploads): Response
    {
        $uploads->object($request->string('key')->toString(), $request->string('checksum')->toString(), $request->getContent(true));

        return response('', 200, ['Cache-Control' => 'no-store']);
    }

    public function part(LocalMediaUploadRequest $request, string $uploadId, int $partNumber, AcceptLocalMediaUpload $uploads): Response
    {
        $etag = $uploads->part($request->string('key')->toString(), $uploadId, $partNumber, $request->string('checksum')->toString(), $request->getContent(true));

        return response('', 200, ['ETag' => $etag, 'Cache-Control' => 'no-store']);
    }
}
