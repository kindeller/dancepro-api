<?php

namespace App\Features\Media\Services;

use GdImage;

class MediaWatermarkRenderer
{
    /** Crop before branding so the centred logo remains visible in every 3:2 cover. */
    public function coverCanvas(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $cropWidth = min($width, (int) floor($height * 1.5));
        $cropHeight = min($height, (int) floor($width / 1.5));
        $cover = imagecrop($image, ['x' => (int) (($width - $cropWidth) / 2),
            'y' => (int) (($height - $cropHeight) / 2), 'width' => max(1, $cropWidth), 'height' => max(1, $cropHeight)]);
        abort_unless($cover instanceof GdImage, 503, 'Gallery cover cannot be prepared.');

        return $cover;
    }

    public function apply(GdImage $image, array $watermark): void
    {
        if ($watermark['mode'] === 'image') {
            $mark = @imagecreatefrompng($watermark['path']);
            abort_unless($mark instanceof GdImage, 503, 'Watermark artwork cannot be decoded.');
            $scale = isset($watermark['width_ratio'])
                ? min(imagesx($image) * $watermark['width_ratio'] / imagesx($mark), imagesy($image) * 0.8 / imagesy($mark))
                : min(imagesx($image), imagesy($image)) / max(imagesx($mark), imagesy($mark));
            $width = max(1, (int) round(imagesx($mark) * $scale));
            $height = max(1, (int) round(imagesy($mark) * $scale));
            imagealphablending($image, true);
            imagecopyresampled($image, $mark, (int) ((imagesx($image) - $width) / 2),
                (int) ((imagesy($image) - $height) / 2), 0, 0, $width, $height, imagesx($mark), imagesy($mark));
            unset($mark);

            return;
        }
        $background = imagecolorallocatealpha($image, 255, 255, 255, 36);
        $foreground = imagecolorallocatealpha($image, 13, 31, 45, 24);
        for ($y = 18; $y < imagesy($image); $y += 100) {
            for ($x = 8; $x < imagesx($image); $x += 240) {
                imagestring($image, 5, $x + 1, $y + 1, $watermark['text'], $background);
                imagestring($image, 5, $x, $y, $watermark['text'], $foreground);
            }
        }
    }
}
