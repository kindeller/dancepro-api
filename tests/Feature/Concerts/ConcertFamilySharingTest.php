<?php

namespace Tests\Feature\Concerts;

use App\Features\Concerts\Actions\PruneConcertFamilyContactData;
use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Concerts\Models\ConcertCodeGroup;
use App\Features\Concerts\Models\ConcertFamilyShare;
use App\Features\Concerts\Notifications\ConcertFamilyVerification;
use App\Features\Concerts\Services\ConcertFamilyAccess;
use App\Features\Customers\Services\CustomerPrivacyData;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcertFamilySharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_creates_selected_invitation_and_email_verified_family_can_only_open_selected_videos(): void
    {
        [$owner, $concert, $asset, $other] = $this->fixture();
        $share = $this->invite($owner, $concert, $asset);
        $this->get(route('customer.concert-family.index', $concert))->assertOk()->assertSee('three download requests')->assertSee('family@example.test');
        $this->get(route('concert-family.show', $share))->assertOk()->assertSee('Verify your email')->assertDontSee($concert->name);
        $url = route('concert-family.playback', [$share, $asset, 'player' => (string) Str::uuid()]);
        $this->getJson($url)->assertForbidden();
        $this->verify($share);
        $this->get(route('concert-family.show', $share))->assertOk()->assertSee($asset->original_filename)->assertDontSee($other->original_filename);
        $media = $this->getJson($url)->assertOk()->json('data.url');
        $this->get($media)->assertOk();
        $this->getJson(route('concert-family.playback', [$share, $other, 'player' => (string) Str::uuid()]))->assertNotFound();
        $this->actingAs(User::factory()->customer()->create())->get(route('concerts.media.download', [$concert, $asset]))->assertForbidden();
        $this->assertSame(1, $share->videos()->first()->playback_starts);
    }

    public function test_invitation_expiry_acceptance_expiry_and_original_concert_windows_are_enforced(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $share = $this->invite($owner, $concert, $asset);
        $this->travel(15)->days();
        $this->post(route('concert-family.code', $share))->assertGone();
        $this->post(route('customer.concert-family.renew', [$concert, $share]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $share->fresh()->version);
        $this->verify($share->fresh());
        $group = ConcertCodeGroup::create(['studio_id' => $concert->studio_id, 'name' => 'Videos', 'year' => now()->year,
            'code_hash' => Hash::make('FAMILY'), 'released_at' => now()->subMonths(7)]);
        $concert->forceFill(['concert_code_group_id' => $group->id])->save();
        ConcertAccessGrant::where('concert_id', $concert->id)->update(['concert_code_group_id' => $group->id]);
        $this->getJson(route('concert-family.playback', [$share, $asset, 'player' => (string) Str::uuid()]))->assertForbidden();
        $this->postJson(route('concert-family.download.request', [$share, $asset]), ['request_uuid' => (string) Str::uuid()])->assertOk();
        $this->travel(31)->days();
        $this->get(route('concert-family.show', $share))->assertOk()->assertSee('no longer available');
        $this->postJson(route('concert-family.download.request', [$share, $asset]), ['request_uuid' => (string) Str::uuid()])->assertGone();
    }

    public function test_download_allowance_is_three_per_video_idempotent_and_survives_renewal(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $share = $this->invite($owner, $concert, $asset);
        $this->verify($share);
        $route = route('concert-family.download.request', [$share, $asset]);
        $request = ['request_uuid' => (string) Str::uuid()];
        $url = $this->postJson($route, $request)->assertOk()->assertJsonPath('remaining', 2)->json('url');
        $this->postJson($route, $request)->assertOk()->assertJsonPath('remaining', 2);
        $this->get($url)->assertDownload('Opening.mp4');
        $this->get($url)->assertDownload();
        foreach ([1, 0] as $remaining) {
            $this->postJson($route, ['request_uuid' => (string) Str::uuid()])->assertOk()->assertJsonPath('remaining', $remaining);
        }
        $this->postJson($route, ['request_uuid' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('download');
        $this->post(route('customer.concert-family.renew', [$concert, $share]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $share->fresh()->version);
        $this->get($url)->assertForbidden();
        $this->travel(2)->minutes();
        $this->verify($share->fresh());
        $this->postJson($route, ['request_uuid' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(3, $share->videos()->first()->download_requests);
        $this->assertSame(2, $share->fresh()->link_count);
    }

    public function test_three_distinct_recipients_include_revoked_and_expired_links_and_cross_account_changes_are_denied(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $first = $this->invite($owner, $concert, $asset);
        $first->update(['revoked_at' => now()]);
        $this->invite($owner, $concert, $asset, 'second@example.test');
        $this->invite($owner, $concert, $asset, 'third@example.test');
        $this->post(route('customer.concert-family.store', $concert), $this->payload($asset, 'fourth@example.test'))->assertSessionHasErrors('recipient_email');
        $this->post(route('customer.concert-family.store', $concert), $this->payload($asset))->assertSessionHasErrors('recipient_email');
        $this->actingAs(User::factory()->customer()->create())->post(route('customer.concert-family.revoke', [$concert, $first]))->assertForbidden();
        $this->get(route('customer.concert-family.index', $concert))->assertForbidden();
        $this->get(route('customer.concert-family.settings', $concert))->assertForbidden();
    }

    public function test_invalid_assets_and_missing_entitlement_cannot_create_invites(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $this->actingAs($owner)->post(route('customer.concert-family.store', $concert), $this->payload($asset) + [])->assertRedirect();
        $asset->update(['is_visible' => false]);
        $this->post(route('customer.concert-family.store', $concert), $this->payload($asset, 'other@example.test'))->assertSessionHasErrors('assets');
        $this->actingAs(User::factory()->customer()->create())->post(route('customer.concert-family.store', $concert), $this->payload($asset))->assertForbidden();
    }

    public function test_revoke_parent_access_hidden_video_and_studio_policy_block_existing_links(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $share = $this->invite($owner, $concert, $asset);
        $this->verify($share);
        $url = $this->postJson(route('concert-family.download.request', [$share, $asset]), ['request_uuid' => (string) Str::uuid()])->assertOk()->json('url');
        $asset->update(['is_visible' => false]);
        $this->get($url)->assertNotFound();
        $asset->update(['is_visible' => true]);
        $concert->studio->forceFill(['family_downloads_enabled' => false])->save();
        $this->get($url)->assertForbidden();
        $concert->studio->forceFill(['family_downloads_enabled' => true])->save();
        ConcertAccessGrant::where('user_id', $owner->id)->update(['revoked_at' => now()]);
        $this->get($url)->assertGone();
        ConcertAccessGrant::where('user_id', $owner->id)->update(['revoked_at' => null]);
        $this->post(route('customer.concert-family.revoke', [$concert, $share]))->assertRedirect();
        $this->get($url)->assertGone();
    }

    public function test_owner_and_admin_manage_studio_policy_and_disabling_does_not_silently_restore_invites(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $share = $this->invite($owner, $concert, $asset);
        $director = User::factory()->customer()->create();
        StudioAccountAccess::create(['studio_id' => $concert->studio_id, 'user_id' => $director->id, 'email' => $director->email, 'role' => 'owner', 'status' => 'active']);
        $this->actingAs($director)->get(route('customer.concert-family.settings', $concert))->assertOk()->assertSee('family@example.test');
        $this->patch(route('customer.concert-family.policy', $concert), ['family_sharing_enabled' => 0, 'family_downloads_enabled' => 0])->assertSessionHasNoErrors();
        $this->assertNotNull($share->fresh()->revoked_at);
        $this->actingAs(User::factory()->admin()->create())->patch(route('customer.concert-family.policy', $concert), ['family_sharing_enabled' => 1, 'family_downloads_enabled' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull($share->fresh()->revoked_at);
    }

    public function test_email_code_attempt_limit_cooldown_expiry_and_session_binding(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $share = $this->invite($owner, $concert, $asset);
        Notification::fake();
        $this->post(route('concert-family.code', $share))->assertSessionHasNoErrors();
        $this->post(route('concert-family.code', $share))->assertSessionHasErrors('code');
        $code = Notification::sent(new AnonymousNotifiable, ConcertFamilyVerification::class)->first()->code;
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('concert-family.verify', $share), ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post(route('concert-family.verify', $share), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull($share->fresh()->accepted_at);
        $this->travel(2)->minutes();
        Notification::fake();
        $this->post(route('concert-family.code', $share))->assertSessionHasNoErrors();
        $code = Notification::sent(new AnonymousNotifiable, ConcertFamilyVerification::class)->first()->code;
        $this->travel(11)->minutes();
        $this->post(route('concert-family.verify', $share), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_activity_and_one_playback_window_lease(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $share = $this->invite($owner, $concert, $asset);
        $this->verify($share);
        $player = (string) Str::uuid();
        $other = (string) Str::uuid();
        $this->getJson(route('concert-family.playback', [$share, $asset, 'player' => $player]))->assertOk();
        $this->getJson(route('concert-family.playback', [$share, $asset, 'player' => $other]))->assertForbidden();
        $this->postJson(route('concert-family.activity', [$share, $asset]), ['player' => $player, 'position' => 90, 'ended' => true])->assertNoContent();
        $this->getJson(route('concert-family.playback', [$share, $asset, 'player' => $other]))->assertOk();
        $this->assertNotNull($share->videos()->first()->playback_completed_at);
        $this->assertSame(90, $share->videos()->first()->last_position_seconds);
    }

    public function test_contact_retention_and_privacy_export_hide_verification_secrets(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $share = $this->invite($owner, $concert, $asset);
        $this->verify($share);
        $data = app(CustomerPrivacyData::class)->export($owner)['concert_family_shares'];
        $this->assertArrayNotHasKey('code_hash', $data[0]);
        $this->assertArrayNotHasKey('verified_session_hash', $data[0]);
        $share->update(['expires_at' => now()->subDays(181)]);
        $this->assertSame(1, app(PruneConcertFamilyContactData::class)->execute());
        $this->assertSame('Removed recipient', $share->fresh()->recipient_name);
        $this->assertNotNull($share->fresh()->revoked_at);
    }

    public function test_forwarded_link_and_verification_code_do_not_authorise_another_browser(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $share = $this->invite($owner, $concert, $asset);
        Notification::fake();
        $this->post(route('concert-family.code', $share))->assertSessionHasNoErrors();
        $code = Notification::sent(new AnonymousNotifiable, ConcertFamilyVerification::class)->first()->code;
        $originalBrowser = session('concert_family_browser');
        $this->withSession(['concert_family_browser' => Str::random(64)])
            ->post(route('concert-family.verify', $share), ['code' => $code])->assertSessionHasErrors('code');
        $this->withSession(['concert_family_browser' => $originalBrowser])
            ->post(route('concert-family.verify', $share), ['code' => $code])->assertRedirect();
        $url = route('concert-family.download.request', [$share, $asset]);
        $this->withSession(['concert_family_browser' => Str::random(64)])
            ->postJson($url, ['request_uuid' => (string) Str::uuid()])->assertForbidden();
        $this->assertSame(0, $share->videos()->first()->download_requests);
    }

    public function test_missing_file_does_not_use_download_allowance_and_parent_expiry_caps_invitation(): void
    {
        [$owner, $concert, $asset] = $this->fixture();
        $share = $this->invite($owner, $concert, $asset);
        $this->verify($share);
        // Local fake storage only; no live objects are touched.
        Storage::disk('s3_concerts_legacy')->delete($asset->storage_key);
        $this->postJson(route('concert-family.download.request', [$share, $asset]), ['request_uuid' => (string) Str::uuid()])->assertNotFound();
        $this->assertSame(0, $share->videos()->first()->download_requests);
        $concert->update(['available_until' => now()->addDay()]);
        $end = app(ConcertFamilyAccess::class)->end($share->fresh());
        $this->assertTrue($end->equalTo($concert->fresh()->available_until));
        $this->travel(2)->days();
        $this->post(route('concert-family.code', $share))->assertGone();
    }

    private function payload(MediaAsset $asset, string $email = 'family@example.test'): array
    {
        return ['recipient_name' => 'Family overseas', 'recipient_email' => $email, 'assets' => [$asset->uuid], 'terms' => 1];
    }

    private function invite(User $owner, Concert $concert, MediaAsset $asset, string $email = 'family@example.test'): ConcertFamilyShare
    {
        $this->actingAs($owner)->post(route('customer.concert-family.store', $concert), $this->payload($asset, $email))->assertSessionHasNoErrors();

        return ConcertFamilyShare::where('recipient_email', $email)->firstOrFail();
    }

    private function verify(ConcertFamilyShare $share): void
    {
        Notification::fake();
        $this->post(route('concert-family.code', $share))->assertSessionHasNoErrors();
        $code = Notification::sent(new AnonymousNotifiable, ConcertFamilyVerification::class)->first()->code;
        $this->post(route('concert-family.verify', $share), ['code' => $code])->assertSessionHasNoErrors()->assertRedirect(route('concert-family.show', $share));
    }

    private function fixture(): array
    {
        Storage::fake('s3_concerts_legacy');
        config()->set('filesystems.disks.s3_concerts_legacy.driver', 'local');
        $owner = User::factory()->customer()->create();
        $concert = Concert::factory()->published()->create(['access_password_hash' => 'concert-code']);
        ConcertAccessGrant::factory()->create(['user_id' => $owner->id, 'concert_id' => $concert->id, 'source' => 'password', 'expires_at' => null,
            'metadata' => ['code_fingerprint' => hash('sha256', $concert->access_password_hash)]]);
        $collection = MediaCollection::factory()->for($concert)->create(['media_type' => 'video', 'status' => 'published']);
        $assets = collect(['Opening.mp4', 'Not shared.mp4'])->map(function ($name) use ($collection) {
            $asset = MediaAsset::factory()->for($collection, 'collection')->create(['media_type' => 'video', 'storage_disk' => 's3_concerts_legacy', 'storage_key' => 'existing/'.$name, 'original_filename' => $name, 'display_name' => $name]);
            Storage::disk('s3_concerts_legacy')->put($asset->storage_key, 'video bytes');

            return $asset;
        });

        return [$owner, $concert, ...$assets];
    }
}
