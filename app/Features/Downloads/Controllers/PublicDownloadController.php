<?php

namespace App\Features\Downloads\Controllers;

use App\Features\Downloads\Actions\ResolveDownloadLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicDownloadController extends Controller
{
    public function show(string $token, Request $request, ResolveDownloadLink $resolveDownloadLink): RedirectResponse|Response
    {
        return $resolveDownloadLink->handle($token, $request);
    }
}
