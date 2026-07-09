<?php

namespace App\Features\Downloads\Controllers;

use App\Features\Downloads\Actions\ResolveDownloadLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicDownloadController extends Controller
{
    public function show(string $token, Request $request, ResolveDownloadLink $resolveDownloadLink): RedirectResponse|JsonResponse
    {
        return $resolveDownloadLink->handle($token, $request);
    }
}
