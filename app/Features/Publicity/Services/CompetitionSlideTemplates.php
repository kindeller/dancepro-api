<?php

namespace App\Features\Publicity\Services;

use App\Features\Scheduling\Models\SchedulingEvent;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

/** Approved artwork with fixed QR placements on a 1920 × 1080 canvas. */
class CompetitionSlideTemplates
{
    // Cover the guide outline, including its antialiased edge. QR includes a four-module quiet zone.
    private const BOXES = [
        1 => [124, 104, 663],
        2 => [1142, 121, 663],
        3 => [1141, 120, 664],
        4 => [1141, 120, 664],
        5 => [1141, 120, 664],
        6 => [1141, 120, 664],
    ];

    public function __construct(private readonly PublicityEventAccess $access) {}

    public function promotions(SchedulingEvent $event): array
    {
        $url = $this->access->link($event);
        if ($event->event_type->value !== 'competition' || ! $url) {
            return [];
        }

        $slides = [];
        foreach (self::BOXES as $number => $box) {
            $slides[] = ['id' => 'template-'.$event->uuid.'-'.$number, 'kind' => 'image', 'title' => '', 'body' => null,
                'version' => $this->version($number, $url), 'seconds' => 8];
        }

        return $slides;
    }

    public function image(SchedulingEvent $event, string $id): string
    {
        $slide = collect($this->promotions($event))->firstWhere('id', $id);
        abort_unless($slide, 404);
        $key = 'publicity/generated/'.$slide['version'].'.png';
        $disk = Storage::disk('local');
        if ($disk->exists($key)) {
            return $key;
        }

        $number = (int) substr($id, -1);
        [$x, $y, $size] = self::BOXES[$number];
        $canvas = imagecreatefrompng($this->path($number));
        $qr = imagecreatefromstring((new Writer(new GDLibRenderer($size, 4)))->writeString($this->access->link($event)));
        imagecopy($canvas, $qr, $x, $y, 0, 0, $size, $size);
        ob_start();
        imagepng($canvas, null, 6);
        $disk->put($key, ob_get_clean());

        return $key;
    }

    private function version(int $number, string $url): string
    {
        return hash('sha256', json_encode(['competition-qr-v1', hash_file('sha256', $this->path($number)), self::BOXES[$number], $url]));
    }

    private function path(int $number): string
    {
        return resource_path('images/publicity/competition-templates/qr-'.$number.'.png');
    }
}
