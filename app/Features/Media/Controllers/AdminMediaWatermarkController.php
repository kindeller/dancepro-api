<?php

namespace App\Features\Media\Controllers;

use App\Features\Media\Models\MediaWatermark;
use App\Features\Media\Requests\AdminMediaWatermarkRequest;
use App\Features\Media\Requests\StoreMediaWatermarkRequest;
use App\Features\Media\Requests\UpdateMediaWatermarkRequest;
use App\Features\Media\Services\MediaWatermarkRenderer;
use App\Features\Media\Services\MediaWatermarks;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminMediaWatermarkController extends Controller
{
    public function store(StoreMediaWatermarkRequest $request, MediaWatermarks $watermarks): RedirectResponse
    {
        $watermarks->create($request->user(), $request->validated(), $request->file('artwork'));

        return back()->with('status', 'Watermark added to the library.');
    }

    public function update(UpdateMediaWatermarkRequest $request, MediaWatermark $watermark, MediaWatermarks $watermarks): RedirectResponse
    {
        $watermarks->update($watermark, $request->validated('action'));

        return back()->with('status', $request->validated('action') === 'default' ? 'Default watermark updated.' : 'Watermark archived.');
    }

    public function artwork(AdminMediaWatermarkRequest $request, MediaWatermark $watermark): StreamedResponse
    {
        abort_unless($watermark->mode === 'image' && $watermark->storage_disk && $watermark->storage_key, 404);
        $disk = Storage::disk($watermark->storage_disk);
        abort_unless($disk->exists($watermark->storage_key), 404);

        return $disk->response($watermark->storage_key, $watermark->name.'.png', ['Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store'], 'inline');
    }

    public function preview(AdminMediaWatermarkRequest $request, MediaWatermark $watermark, MediaWatermarkRenderer $renderer): Response
    {
        $image = imagecreatetruecolor(900, 560);
        for ($y = 0; $y < 560; $y++) {
            $colour = imagecolorallocate($image, 20 + (int) ($y / 14), 42 + (int) ($y / 8), 68 + (int) ($y / 10));
            imageline($image, 0, $y, 899, $y, $colour);
        }
        abort_unless($watermark->mode === 'text' || ($watermark->storage_disk && $watermark->storage_key
            && Storage::disk($watermark->storage_disk)->exists($watermark->storage_key)), 404);
        $descriptor = $watermark->mode === 'image'
            ? ['mode' => 'image', 'path' => Storage::disk($watermark->storage_disk)->path($watermark->storage_key), 'text' => null]
            : ['mode' => 'text', 'path' => null, 'text' => $watermark->text];
        $renderer->apply($image, $descriptor);
        ob_start();
        imagejpeg($image, null, 88);
        $bytes = ob_get_clean();
        unset($image);

        return response($bytes, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }
}
