<?php

// CLI-only checks during maintenance. No HTTP bypass or public diagnostic route.
use App\Features\Concerts\Models\Concert;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Studios\Models\Studio;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;

require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$kernel->setGlobalMiddleware(array_values(array_filter(
    $kernel->getGlobalMiddleware(),
    fn ($middleware) => $middleware !== PreventRequestsDuringMaintenance::class,
)));
foreach (['/', '/login', '/api/studios'] as $path) {
    $response = $kernel->handle(Request::create(rtrim(config('app.url'), '/').$path));
    $body = $response->getContent();
    $valid = match ($path) {
        '/' => str_contains($body, 'Find your dance studio.'),
        '/login' => str_contains($body, '<title>DancePro Login</title>'),
        default => ($json = json_decode($body, true)) && ($json['success'] ?? false) === true && is_array($json['data'] ?? null),
    };
    if ($response->getStatusCode() !== 200 || ! $valid) {
        throw new RuntimeException('Pre-open application check failed: '.$path);
    }
}
$concert = new Concert(['uuid' => '00000000-0000-4000-8000-000000000001', 'name' => 'Deployment check']);
$concert->setRelation('studio', new Studio(['name' => 'Deployment check']));
$asset = new MediaAsset(['uuid' => '00000000-0000-4000-8000-000000000002', 'media_type' => 'video', 'display_name' => 'Deployment check']);
$collection = new MediaCollection(['name' => 'Deployment check']);
$collection->setRelation('assets', collect([$asset]));
$concert->setRelation('mediaCollections', collect([$collection]));
$html = view('public.concerts.show', ['concert' => $concert, 'downloadUrls' => [$asset->uuid => '#']])->render();
if (! str_contains($html, '/build/') || ! str_contains($html, 'data-playback=') || ! str_contains($html, 'type="module"')) {
    throw new RuntimeException('Concert view did not render the Vite player.');
}
echo "Application routes and concert Vite view passed before reopening.\n";
