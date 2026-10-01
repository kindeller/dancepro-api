<?php

namespace Tests\Feature\Customers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudioGalleryCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_admin_code_action_updates_the_shared_studio_code_with_safe_history(): void
    {
        $studio = Studio::factory()->create(['code' => 'MEDIA-STUDIO']);
        $admin = User::factory()->staff()->create();
        $url = route('admin.studios.gallery-code.update', $studio);
        $this->actingAs($admin)->get(route('admin.studios.edit', $studio))->assertOk()->assertSee('Studio code')->assertDontSee('Customer gallery code');
        $this->post($url, ['gallery_code' => 'gallery-test', 'version' => 0])->assertRedirect();
        $studio->refresh();
        $this->assertSame('GALLERY-TEST', $studio->code);
        $this->assertTrue(Hash::check('GALLERY-TEST', $studio->gallery_code_hash));
        $this->assertSame(1, $studio->gallery_code_version);
        $this->assertArrayNotHasKey('gallery_code_hash', $studio->toArray());
        $this->assertArrayNotHasKey('gallery_code_encrypted', $studio->toArray());
        $this->assertSame('GALLERY-TEST', $studio->gallery_code_encrypted);
        $this->assertStringNotContainsString('GALLERY-TEST', $studio->getRawOriginal('gallery_code_encrypted'));
        $this->assertDatabaseHas('studio_gallery_code_changes', ['studio_id' => $studio->id, 'actor_user_id' => $admin->id, 'version' => 1]);
        $this->assertSame(1, DB::table('studio_gallery_code_changes')->count());
        $this->get(route('admin.studios.edit', $studio))->assertOk()->assertSee('GALLERY-TEST')->assertDontSee('Customer gallery code')->assertDontSee($studio->gallery_code_hash);
        $this->post($url, ['gallery_code' => 'new-gallery', 'version' => 0])->assertConflict();
        $this->post($url, ['gallery_code' => 'bad!', 'version' => 1])->assertSessionHasErrors('gallery_code')->assertSessionMissing('_old_input.gallery_code');
        $this->actingAs(User::factory()->customer()->create())->post($url, ['gallery_code' => 'another-test', 'version' => 1])->assertForbidden();
    }

    public function test_three_letter_gallery_code_can_be_saved_and_used_by_a_customer(): void
    {
        $studio = Studio::factory()->create();
        $admin = User::factory()->staff()->create();
        $url = route('admin.studios.gallery-code.update', $studio);
        $this->actingAs($admin)->post($url, ['gallery_code' => 'abc', 'version' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('ABC', $studio->fresh()->gallery_code_encrypted);
        $this->post($url, ['gallery_code' => 'AB', 'version' => 1])->assertSessionHasErrors('gallery_code');
        $this->actingAs(User::factory()->customer()->create())->post(route('customer.profile.store'), [
            'action' => 'studio_gallery', 'studio_uuid' => $studio->uuid, 'studio_gallery_code' => 'abc',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull(CustomerStudio::firstOrFail()->access_verified_at);
    }

    public function test_customer_must_verify_studio_code_before_profile_media_access(): void
    {
        $studio = Studio::factory()->create(['code' => 'MEDIA-STUDIO']);
        Concert::factory()->published()->create(['studio_id' => $studio->id, 'event_date' => '2026-06-01', 'external_gallery_url' => 'https://photos.example.test/studio']);
        $user = User::factory()->customer()->create();
        $this->actingAs($user);
        $input = ['action' => 'studio_gallery', 'studio_uuid' => $studio->uuid, 'studio_gallery_code' => 'GALLERY-TEST'];
        $this->post(route('customer.profile.store'), $input)->assertSessionHasErrors('studio_gallery_code')->assertSessionMissing('_old_input.studio_gallery_code');
        $this->assertDatabaseCount('customer_studios', 0);
        $studio->forceFill(['gallery_code_hash' => Hash::make('GALLERY-TEST')])->save();
        $this->post(route('customer.profile.store'), $input)->assertSessionHasErrors('studio_gallery_code');
        $this->post(route('customer.profile.store'), array_replace($input, ['studio_gallery_code' => ' media-studio ']))->assertRedirect();
        $this->assertDatabaseCount('customer_studios', 1);
        $link = CustomerStudio::firstOrFail();
        $this->assertNotNull($link->access_verified_at);
        $this->get(route('customer.media.index'))->assertOk()->assertSee(route('customer.profile.studio', $studio), false);
        $this->get(route('customer.profile.studio', $studio))->assertOk()->assertSee('https://photos.example.test/studio', false);
        $studio->forceFill(['gallery_code_hash' => Hash::make('ROTATED-CODE')])->save();
        $this->get(route('customer.profile.studio', $studio))->assertOk();
        $this->actingAs(User::factory()->customer()->create())->get(route('customer.profile.studio', $studio))->assertForbidden();
    }

    public function test_previous_unverified_profile_link_requires_current_gallery_code(): void
    {
        $studio = Studio::factory()->create(['code' => null]);
        $studio->forceFill(['gallery_code_hash' => Hash::make('GALLERY-TEST')])->save();
        $user = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $user->id, 'studio_id' => $studio->id]);
        $this->actingAs($user)->get(route('customer.profile.edit', ['tab' => 'studios']))->assertOk()->assertSee('Confirm your studio code under Add/change studio');
        $this->get(route('customer.profile.studio', $studio))->assertForbidden();
        $this->get(route('customer.media.index'))->assertOk()->assertSee('Add your studio in My Profile');
        $this->post(route('customer.profile.store'), ['action' => 'studio_gallery', 'studio_uuid' => $studio->uuid, 'studio_gallery_code' => 'GALLERY-TEST'])->assertRedirect();
        $this->assertDatabaseCount('customer_studios', 1);
        $this->assertNotNull(CustomerStudio::first()->access_verified_at);
    }
}
