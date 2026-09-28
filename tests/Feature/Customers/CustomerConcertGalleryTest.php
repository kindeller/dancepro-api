<?php

namespace Tests\Feature\Customers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\CustomerConcertPhotoCart;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaPriceList;
use App\Features\Media\Support\MediaType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerConcertGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_studio_customer_sees_only_released_albums_and_watermarked_previews(): void
    {
        Storage::fake('local');
        $concert = Concert::factory()->published()->create(['name' => 'Summer concert', 'external_gallery_url' => null]);
        $customer = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $concert->studio_id, 'access_verified_at' => now()]);
        $ready = MediaCollection::factory()->create(['concert_id' => $concert->id, 'competition_id' => null,
            'name' => '01 Dance', 'media_type' => MediaType::Photo, 'status' => 'published',
            'visibility' => 'customer', 'published_at' => now()]);
        MediaCollection::factory()->create(['concert_id' => $concert->id, 'competition_id' => null,
            'name' => '02 Unreleased', 'media_type' => MediaType::Photo, 'status' => 'draft', 'visibility' => 'private']);
        $image = imagecreatetruecolor(320, 240);
        imagefill($image, 0, 0, imagecolorallocate($image, 80, 100, 120));
        ob_start();
        imagejpeg($image);
        $original = ob_get_clean();
        Storage::disk('local')->put('concerts/summer/photo.jpg', $original);
        $asset = MediaAsset::factory()->create(['media_collection_id' => $ready->id, 'storage_disk' => 'local',
            'storage_key' => 'concerts/summer/photo.jpg', 'mime_type' => 'image/jpeg', 'media_type' => MediaType::Photo,
            'status' => 'available', 'is_visible' => true]);

        $root = route('customer.concerts.photos.show', $concert);
        $album = route('customer.concerts.photos.show', ['concert' => $concert, 'folder' => $ready->uuid]);
        $preview = route('customer.concerts.photos.image', ['concert' => $concert, 'asset' => $asset->uuid]);
        $this->actingAs($customer)->get($root)->assertOk()->assertSee('01 Dance')->assertDontSee('02 Unreleased')
            ->assertSee(route('customer.media.favourites', ['focus_kind' => 'concert', 'focus_event' => $concert->uuid]))
            ->assertSee(route('customer.media.cart', ['focus_kind' => 'concert', 'focus_event' => $concert->uuid]));
        $this->get($album)->assertOk()->assertSee($preview, false)->assertDontSee('photo.jpg')
            ->assertSee('Bulk photo discounts')->assertSee('Spend $100')->assertSee('Save 5%');
        $response = $this->get($preview)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertNotSame($original, $response->streamedContent());
        $this->actingAs(User::factory()->customer()->create())->get($preview)->assertForbidden();
        $ready->update(['status' => 'draft']);
        $this->actingAs($customer)->get($preview)->assertNotFound();
    }

    public function test_concert_photo_cart_accepts_media_from_multiple_concerts_and_studios(): void
    {
        $first = Concert::factory()->published()->create(['name' => 'Junior concert', 'event_date' => '2026-01-10']);
        $second = Concert::factory()->published()->create(['name' => 'Senior concert', 'event_date' => '2026-09-10', 'studio_id' => $first->studio_id]);
        $foreign = Concert::factory()->published()->create(['name' => 'Other studio concert']);
        $customer = User::factory()->customer()->create();
        foreach ([$first, $foreign] as $concert) {
            CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $concert->studio_id, 'access_verified_at' => now()]);
        }
        $createAsset = function (Concert $concert): MediaAsset {
            $collection = MediaCollection::factory()->create(['concert_id' => $concert->id, 'competition_id' => null,
                'media_type' => MediaType::Photo, 'status' => 'published', 'visibility' => 'customer', 'published_at' => now()]);

            return MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'media_type' => MediaType::Photo,
                'status' => 'available', 'is_visible' => true]);
        };
        $firstAsset = $createAsset($first);
        $secondAsset = $createAsset($second);
        $foreignAsset = $createAsset($foreign);
        $this->actingAs($customer);
        $this->post(route('customer.concerts.photos.selections.store', $first), ['asset_uuid' => $firstAsset->uuid, 'action' => 'add'])->assertRedirect();
        $this->post(route('customer.concerts.photos.selections.store', $first), ['asset_uuid' => $firstAsset->uuid, 'action' => 'favourite'])->assertRedirect();
        $this->post(route('customer.concerts.photos.selections.store', $second), ['asset_uuid' => $secondAsset->uuid, 'action' => 'add'])->assertRedirect();
        $this->get(route('customer.media.favourites'))->assertOk()
            ->assertSee('data-favourite-preview', false)
            ->assertSee(route('customer.concerts.photos.image', ['concert' => $first, 'asset' => $firstAsset]), false);
        $this->get(route('customer.media.cart', ['focus_kind' => 'concert', 'focus_event' => $first->uuid]))->assertOk()
            ->assertSeeInOrder(['Junior concert', 'Senior concert'])->assertSee('$20.00');
        $this->get(route('customer.media.cart'))->assertOk()
            ->assertSeeInOrder(['Senior concert', 'Junior concert']);
        $this->get(route('customer.concerts.photos.cart', $first->studio))
            ->assertRedirect(route('customer.media.cart'));
        $this->post(route('customer.concerts.photos.selections.store', $foreign), ['asset_uuid' => $foreignAsset->uuid, 'action' => 'add'])
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->post(route('customer.concerts.photos.selections.store', $first), ['asset_uuid' => $foreignAsset->uuid, 'action' => 'add'])->assertNotFound();
        foreach (range(1, 9) as $index) {
            $asset = $createAsset($first);
            $this->post(route('customer.concerts.photos.selections.store', $first), ['asset_uuid' => $asset->uuid, 'action' => 'add'])->assertRedirect();
        }
        $this->get(route('customer.media.cart', ['focus_kind' => 'concert', 'focus_event' => $first->uuid]))->assertOk()
            ->assertSee('Event bulk discount (5%)')->assertSee('$95.00');
        $this->assertDatabaseCount('customer_concert_photo_selections', 12);
    }

    public function test_authorised_studio_principal_photos_are_one_dollar_and_never_qualify_for_bulk_discount(): void
    {
        $customer = User::factory()->customer()->create(['email' => 'principal@example.test']);
        $concert = Concert::factory()->published()->create();
        $concert->studio->update(['contact_email' => $customer->email]);
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $concert->studio_id, 'access_verified_at' => now()]);
        $collection = MediaCollection::factory()->create(['concert_id' => $concert->id, 'competition_id' => null,
            'media_type' => MediaType::Photo, 'status' => 'published', 'visibility' => 'customer', 'published_at' => now()]);

        $this->actingAs($customer);
        foreach (range(1, 12) as $index) {
            $asset = MediaAsset::factory()->create(['media_collection_id' => $collection->id,
                'media_type' => MediaType::Photo, 'status' => 'available', 'is_visible' => true]);
            $this->post(route('customer.concerts.photos.selections.store', $concert), [
                'asset_uuid' => $asset->uuid, 'action' => 'add',
            ])->assertRedirect();
        }

        $this->get(route('customer.media.cart', ['focus_kind' => 'concert', 'focus_event' => $concert->uuid]))->assertOk()
            ->assertDontSee('Event bulk discount')->assertSee('$12.00');
    }

    public function test_concert_cart_and_favourite_survive_an_album_price_list_change(): void
    {
        $concert = Concert::factory()->published()->create();
        $customer = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $concert->studio_id, 'access_verified_at' => now()]);
        $album = MediaCollection::factory()->create(['concert_id' => $concert->id, 'competition_id' => null,
            'media_type' => MediaType::Photo, 'status' => 'published', 'visibility' => 'customer', 'published_at' => now()]);
        $asset = MediaAsset::factory()->create(['media_collection_id' => $album->id,
            'media_type' => MediaType::Photo, 'status' => 'available', 'is_visible' => true]);
        $this->actingAs($customer);
        foreach (['favourite', 'add'] as $action) {
            $this->post(route('customer.concerts.photos.selections.store', $concert), [
                'asset_uuid' => $asset->uuid, 'action' => $action,
            ])->assertRedirect();
        }
        $special = MediaPriceList::create([
            'name' => 'Repriced concert album', 'gallery_type' => 'concert', 'is_active' => true,
            'prices' => ['concert_photo' => 1500],
        ]);

        $album->update(['media_price_list_id' => $special->id]);
        $quote = app(CustomerConcertPhotoCart::class)->forStudio($customer, $concert->studio);
        $selection = $quote['selections']->firstOrFail();

        $this->assertSame(1500, $quote['subtotal']);
        $this->assertTrue($selection->in_cart);
        $this->assertTrue($selection->is_favourite);
    }
}
