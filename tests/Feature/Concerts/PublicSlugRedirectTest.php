<?php

namespace Tests\Feature\Concerts;

use App\Features\Concerts\Models\Concert;
use App\Features\Studios\Models\Studio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSlugRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_slug_redirects_to_the_canonical_uuid_route(): void
    {
        $studio = Studio::factory()->create(['slug' => 'coastal-dance']);

        $this->get(route('studios.resolve-slug', ['slug' => $studio->slug]))
            ->assertRedirect(route('studios.show', $studio));
    }

    public function test_concert_slug_redirects_to_the_canonical_uuid_route(): void
    {
        $concert = Concert::factory()->create(['slug' => 'winter-showcase']);

        $this->get(route('concerts.resolve-slug', ['slug' => $concert->slug]))
            ->assertRedirect(route('concerts.show', $concert));
    }

    public function test_concert_slug_redirect_preserves_canonical_access_checks(): void
    {
        $protectedConcert = Concert::factory()->published()->passwordProtected()->create([
            'slug' => 'protected-showcase',
        ]);
        $unavailableConcert = Concert::factory()->published()->create([
            'slug' => 'future-showcase',
            'available_from' => now()->addDay(),
        ]);

        $this->followingRedirects()
            ->get(route('concerts.resolve-slug', ['slug' => $protectedConcert->slug]))
            ->assertOk()
            ->assertSee('Unlock concert');

        $this->followingRedirects()
            ->get(route('concerts.resolve-slug', ['slug' => $unavailableConcert->slug]))
            ->assertNotFound();
    }

    public function test_unknown_and_ambiguous_slugs_do_not_resolve(): void
    {
        Studio::factory()->count(2)->create(['slug' => 'shared-studio']);
        Concert::factory()->count(2)->create(['slug' => 'shared-concert']);

        $this->get(route('studios.resolve-slug', ['slug' => 'missing-studio']))->assertNotFound();
        $this->get(route('concerts.resolve-slug', ['slug' => 'missing-concert']))->assertNotFound();
        $this->get(route('studios.resolve-slug', ['slug' => 'shared-studio']))->assertNotFound();
        $this->get(route('concerts.resolve-slug', ['slug' => 'shared-concert']))->assertNotFound();
    }

    public function test_soft_deleted_records_do_not_resolve(): void
    {
        $studio = Studio::factory()->create(['slug' => 'former-studio']);
        $concert = Concert::factory()->create(['slug' => 'former-concert']);
        $studio->delete();
        $concert->delete();

        $this->get(route('studios.resolve-slug', ['slug' => 'former-studio']))->assertNotFound();
        $this->get(route('concerts.resolve-slug', ['slug' => 'former-concert']))->assertNotFound();
    }
}
