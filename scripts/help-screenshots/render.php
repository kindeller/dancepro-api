<?php

// Run through Sail Tinker; renders real views with fictional data, then rolls back.
use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Services\CustomerMediaProfile;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

abort_unless(app()->environment('local', 'testing'), 403, 'Help previews are local only.');
File::ensureDirectoryExists(base_path('tmp/help-screenshots'));
DB::beginTransaction();
try {
    Mail::fake();
    Notification::fake();
    view()->share('errors', new ViewErrorBag);
    $user = User::factory()->customer()->create(['name' => 'Example Customer', 'email' => 'customer@example.test']);
    Auth::setUser($user);
    $studio = new Studio(['uuid' => (string) Str::uuid(), 'name' => 'Example Dance Studio', 'status' => 'active']);
    $studio->id = -1;
    $event = new SchedulingEvent(['uuid' => (string) Str::uuid(), 'name' => 'Example Dance Competition', 'event_date' => '2026-11-01']);
    $dancer = new CustomerDancer(['uuid' => (string) Str::uuid(), 'name' => 'Example Dancer', 'relationship' => 'self', 'programme_names' => []]);
    $render = function (string $name, string $view, array $data, string $routeName) use ($user): void {
        $request = Request::create('http://localhost/');
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => (new Route('GET', '/', fn () => null))->name($routeName)->bind($request));
        app()->instance('request', $request);
        file_put_contents(base_path('tmp/help-screenshots/'.$name.'.html'), view($view, $data)->render());
    };
    $media = ['notifications' => collect(), 'selectionCounts' => ['favourites' => 0, 'cart' => 0], 'creditBalance' => 0,
        'dancers' => collect([$dancer]), 'studios' => collect([$studio]), 'availableEvents' => collect([$event]),
        'videoYears' => collect([2026]), 'recommendations' => ['dancers' => [], 'studios' => []], 'mediaLibrary' => ['photos' => [], 'videos' => []], 'events' => []];
    $render('media', 'customers.media.index', $media + ['mediaTab' => 'events'], 'customer.media.index');
    $render('purchases', 'customers.media.index', $media + ['mediaTab' => 'purchases'], 'customer.media.index');
    $profile = app(CustomerMediaProfile::class)->editor($user);
    $profile['availableStudios'] = collect([$studio]);
    foreach (['studios', 'dancers', 'preferences'] as $tab) {
        $render('profile-'.$tab, 'customers.profile.edit', $profile + ['profileTab' => $tab], 'customer.profile.edit');
    }
    $render('message', 'customers.messages.index', ['messageTab' => 'new', 'inboxCounts' => ['notifications' => 0, 'conversations' => 0], 'events' => collect(), 'orders' => collect()], 'customer.messages.index');

    $concert = new Concert(['uuid' => (string) Str::uuid(), 'name' => 'Example Studio Concert', 'event_date' => '2026-11-01']);
    $concert->setRelation('studio', $studio);
    $assets = collect(['Opening', 'Finale'])->map(fn ($name) => new MediaAsset(['uuid' => (string) Str::uuid(), 'media_type' => 'video', 'original_filename' => $name.'.mp4', 'display_name' => $name]));
    $collection = new MediaCollection(['name' => 'Concert videos']);
    $collection->setRelation('assets', $assets);
    $concert->setRelation('mediaCollections', collect([$collection]));
    $render('family', 'concert-family.manage', ['concert' => $concert, 'managing' => false, 'canShare' => true, 'shares' => collect(), 'assets' => $assets], 'customer.concert-family.index');
    // The download manager is captured, not an artificial video playback state.
    Auth::forgetUser();
    $render('concert', 'public.concerts.show', ['concert' => $concert, 'canStream' => true, 'downloadUrls' => $assets->mapWithKeys(fn ($asset) => [$asset->uuid => '#example'])->all()], 'concerts.show');
    Auth::setUser($user);
    $event->setRelation('studio', $studio);
    $render('review', 'customers.studios.concert-review', ['event' => $event, 'released' => false, 'uploads' => ['ready' => true, 'files' => collect(), 'batch' => (object) ['uuid' => (string) Str::uuid()]], 'review' => (object) ['revision' => 1, 'feedback' => []], 'canRelease' => true], 'customer.concert-review.show');
    echo "Rendered fictional Help previews. No records retained or messages sent.\n";
} finally {
    DB::rollBack();
    Auth::forgetUser();
}
