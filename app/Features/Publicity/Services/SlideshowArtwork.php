<?php

namespace App\Features\Publicity\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use GdImage;
use Illuminate\Support\Facades\Storage;

/** Small offline-safe logos and a generated event advert, using existing artwork. */
class SlideshowArtwork
{
    public function __construct(private readonly PublicityEventAccess $access) {}

    public function overlays(SchedulingEvent $event): array
    {
        return ['event' => $this->eventLogo($event), 'dancepro' => $this->thumbnail(file_get_contents(resource_path('images/publicity/dancepro-overlay.png')), 400, 92)];
    }

    public function promotion(SchedulingEvent $event): array
    {
        $data = $this->data($event);

        return ['id' => 'auto-'.$event->uuid, 'kind' => 'image', 'title' => '', 'body' => null,
            'version' => hash('sha256', json_encode($data)), 'seconds' => 8];
    }

    public function image(SchedulingEvent $event): string
    {
        $data = $this->data($event);
        $key = 'publicity/generated/'.hash('sha256', json_encode($data)).'.jpg';
        $disk = Storage::disk('local');
        if ($disk->exists($key)) {
            return $key;
        }
        $canvas = imagecreatetruecolor(1920, 1080);
        $navy = imagecolorallocate($canvas, 4, 27, 62);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $blue = imagecolorallocate($canvas, 0, 163, 213);
        imagefill($canvas, 0, 0, $navy);
        imagefilledrectangle($canvas, 0, 0, 18, 1080, $blue);
        if ($data['logo']) {
            $logo = imagecreatefromstring(base64_decode(substr($data['logo'], strpos($data['logo'], ',') + 1)));
            $this->place($canvas, $logo, 110, 75, 520, 180);
        }
        $y = $this->text($canvas, $data['name'], 110, 335, 1050, 48, $white, true);
        $this->text($canvas, $data['date'], 110, $y + 20, 1020, 25, $blue);
        $this->text($canvas, 'Your moment. Captured.', 110, 650, 1050, 38, $white, true);
        $this->text($canvas, $data['url'] ? 'Scan to add this event and view your media.' : 'Your event photos are coming to DancePro.', 110, 725, 1030, 28, $white);
        $this->text($canvas, $data['url'] ? 'Sign in or create an account on your phone.' : 'Ask the crew for gallery access details.', 110, 830, 1030, 23, $white);
        if ($data['url']) {
            $qr = imagecreatefromstring((new Writer(new GDLibRenderer(560, 4)))->writeString($data['url']));
            $this->place($canvas, $qr, 1240, 250, 560, 560);
            $this->text($canvas, 'SCAN TO VIEW', 1290, 870, 560, 28, $white, true);
        }
        ob_start();
        imagejpeg($canvas, null, 94);
        $disk->put($key, ob_get_clean());

        return $key;
    }

    private function data(SchedulingEvent $event): array
    {
        return ['template' => 'event-welcome-v1', 'name' => $event->name, 'date' => $event->event_date?->format('j F Y') ?? '',
            'logo' => $this->eventLogo($event), 'url' => $this->access->link($event)];
    }

    private function eventLogo(SchedulingEvent $event): ?string
    {
        $path = ($event->event_type->value === 'competition' ? $event->competitionContact?->logo_path : $event->studio?->logo_path) ?? $event->logo_path;
        $disk = Storage::disk(config('uploads.public_disk'));
        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        return $this->thumbnail($disk->get($path), 520, 180);
    }

    private function thumbnail(string $bytes, int $width, int $height): ?string
    {
        $info = @getimagesizefromstring($bytes);
        if (! $info || $info[0] * $info[1] > 20000000) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return null;
        }
        $scale = min(1, $width / imagesx($image), $height / imagesy($image));
        $output = imagecreatetruecolor(max(1, (int) (imagesx($image) * $scale)), max(1, (int) (imagesy($image) * $scale)));
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), imagesx($image), imagesy($image));
        ob_start();
        imagepng($output);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    private function place(GdImage $canvas, GdImage $source, int $x, int $y, int $width, int $height): void
    {
        $scale = min($width / imagesx($source), $height / imagesy($source));
        imagecopyresampled($canvas, $source, $x, $y, 0, 0, (int) (imagesx($source) * $scale), (int) (imagesy($source) * $scale), imagesx($source), imagesy($source));
    }

    private function text(GdImage $canvas, string $text, int $x, int $y, int $width, int $size, int $color, bool $bold = false): int
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans'.($bold ? '-Bold' : '').'.ttf');
        $line = '';
        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            $candidate = trim($line.' '.$word);
            $box = imagettfbbox($size, 0, $font, $candidate);
            if ($line !== '' && $width < $box[2] - $box[0]) {
                imagettftext($canvas, $size, 0, $x, $y, $color, $font, $line);
                $y += (int) ($size * 1.5);
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        imagettftext($canvas, $size, 0, $x, $y, $color, $font, $line);

        return $y + (int) ($size * 1.5);
    }
}
