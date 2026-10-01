<?php

namespace Tests\Feature\Families;

use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Services\CustomerPurchasedMedia;
use App\Features\Downloads\Services\IssuePurchasedMediaDownload;
use App\Features\Downloads\Services\PurchasedDownloadAccess;
use App\Features\Families\Models\CustomerFamilyMember;
use App\Features\Families\Notifications\FamilyInvitation;
use App\Features\Families\Services\FamilyAccess;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Orders\Actions\RecordPurchasedMediaDownload;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Orders\Models\OrderMediaEntitlement;
use App\Features\Orders\Services\AuthorizePurchasedMediaDownload;
use App\Features\Payments\Services\ValidatePayableOrder;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomerFamilyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_existing_dancers_are_preserved_and_manager_permissions_are_locked(): void
    {
        $manager = User::factory()->customer()->create(['first_name' => 'Alex', 'last_name' => 'Parent']);
        $dancer = CustomerDancer::create(['user_id' => $manager->id, 'name' => 'Sam Parent', 'programme_names' => ['S Parent']]);
        $this->actingAs($manager)->get(route('customer.profile.edit', ['tab' => 'family']))->assertOk()
            ->assertSee('My Family')->assertSee('Alex Parent')->assertSee('Sam Parent')->assertSee('Dancer only');
        $member = CustomerFamilyMember::where('customer_dancer_id', $dancer->id)->firstOrFail();
        $this->assertNull($member->email);
        $this->assertNull($member->user_id);
        $this->assertSame(['S Parent'], $member->dancer->programme_names);
        $this->post(route('customer.family.store'), ['action' => 'manager', 'is_dancer' => 1,
            'can_view_media' => 0, 'can_purchase' => 0])->assertSessionHasNoErrors();
        $owner = CustomerFamilyMember::where('user_id', $manager->id)->firstOrFail();
        $this->assertTrue($owner->can_view_media && $owner->can_purchase && $owner->is_dancer);
        $this->post(route('customer.family.store'), ['action' => 'remove', 'member_uuid' => $owner->uuid])->assertForbidden();
        $this->get(route('customer.profile.edit', ['tab' => 'dancers']))->assertOk()->assertSee('My Family');
    }

    public function test_saving_account_permissions_sends_invitation_and_preserves_dancer_identity(): void
    {
        Notification::fake();
        $manager = User::factory()->customer()->create();
        $payload = ['action' => 'save', 'first_name' => 'Sam', 'last_name' => 'Dancer', 'is_dancer' => 1,
            'programme_names' => "S Dancer\nSam D", 'can_view_media' => 0, 'can_purchase' => 0];
        $this->actingAs($manager)->post(route('customer.family.store'), $payload)->assertSessionHasNoErrors();
        $member = CustomerFamilyMember::where('first_name', 'Sam')->firstOrFail();
        $dancerUuid = $member->dancer->uuid;
        $this->assertSame('dancer', $member->status);
        Notification::assertNothingSent();
        $this->post(route('customer.family.store'), ['action' => 'invite', 'member_uuid' => $member->uuid])->assertSessionHasErrors('family');
        $payload = array_replace($payload, ['member_uuid' => $member->uuid, 'email' => 'sam@example.test', 'can_view_media' => 1]);
        $this->post(route('customer.family.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame($dancerUuid, $member->fresh()->dancer->uuid);
        $this->assertSame(['S Dancer', 'Sam D'], $member->fresh()->dancer->programme_names);
        Notification::assertSentOnDemand(FamilyInvitation::class, fn ($mail, $channels, $recipient) => $recipient->routeNotificationFor('mail') === 'sam@example.test');
        $this->assertSame('pending', $member->fresh()->status);
    }

    public function test_email_is_required_for_account_permissions_and_must_be_unique_in_family(): void
    {
        $manager = User::factory()->customer()->create(['email' => 'manager@example.test']);
        $data = ['action' => 'save', 'first_name' => 'Sam', 'last_name' => 'Dancer', 'can_view_media' => 1];
        $this->actingAs($manager)->post(route('customer.family.store'), $data)->assertSessionHasErrors('email');
        $this->post(route('customer.family.store'), $data + ['email' => ' MANAGER@example.test '])->assertSessionHasErrors('email');
        $this->post(route('customer.family.store'), $data + ['email' => 'sam@example.test'])->assertSessionHasNoErrors();
        $this->post(route('customer.family.store'), array_replace($data, ['first_name' => 'Another', 'email' => 'SAM@example.test']))->assertSessionHasErrors('email');
    }

    public function test_saving_unchanged_pending_and_accepted_members_does_not_send_duplicate_invitations(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        Notification::fake();
        $payload = ['action' => 'save', 'member_uuid' => $member->uuid,
            'first_name' => $member->first_name, 'last_name' => $member->last_name,
            'email' => $member->email, 'can_view_media' => $member->can_view_media,
            'can_purchase' => $member->can_purchase, 'is_dancer' => $member->is_dancer];
        $this->actingAs($manager)->post(route('customer.family.store'), $payload)->assertSessionHasNoErrors();
        Notification::assertNothingSent();
        $this->actingAs($recipient)->post($url)->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('customer.family.store'), array_replace($payload, ['can_purchase' => true]))->assertSessionHasNoErrors();
        Notification::assertNothingSent();
        $this->assertSame('active', $member->fresh()->status);
        $this->assertTrue($member->fresh()->can_purchase);
    }

    public function test_only_matching_verified_recipient_can_accept_and_other_members_cannot_manage(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        $other = User::factory()->customer()->create();
        $this->actingAs($other)->post($url)->assertSessionHasErrors('family');
        $this->assertNull($member->fresh()->user_id);
        $this->actingAs($recipient)->get($url)->assertOk()->assertSee('Accept &amp; join family', false);
        $this->post($url)->assertSessionHasNoErrors();
        $this->assertSame($recipient->id, $member->fresh()->user_id);
        $this->assertNull($member->fresh()->invitation_hash);
        $this->post($url)->assertGone();
        $this->post(route('customer.family.store'), ['action' => 'manager', 'is_dancer' => 1])->assertForbidden();
        $this->post(route('customer.family.store'), ['action' => 'save', 'first_name' => 'New', 'last_name' => 'Member', 'is_dancer' => 1])->assertForbidden();
        $this->get(route('customer.profile.edit', ['tab' => 'family']))->assertOk()->assertSee('Leave family')->assertDontSee('Send invite');
    }

    public function test_expired_cancelled_and_replaced_invites_cannot_be_accepted(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        $this->travel(2)->minutes();
        $this->actingAs($manager)->post(route('customer.family.store'), ['action' => 'invite', 'member_uuid' => $member->uuid])->assertSessionHasNoErrors();
        $this->actingAs($recipient)->post($url)->assertGone();
        $member->update(['expires_at' => now()->subMinute()]);
        $this->get($url)->assertGone();
        $this->actingAs($manager)->post(route('customer.family.store'), ['action' => 'cancel', 'member_uuid' => $member->uuid])->assertSessionHasNoErrors();
        $this->assertNull($member->fresh()->invitation_hash);
    }

    public function test_purchases_are_shared_in_both_directions_but_unrelated_accounts_and_removed_members_cannot_download(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        [$order, $entitlement, $asset, $event] = $this->purchase($manager);
        $this->assertDenied($recipient, $entitlement, $asset, 404);
        $this->actingAs($recipient)->post($url)->assertSessionHasNoErrors();
        $this->assertSame($asset->id, app(AuthorizePurchasedMediaDownload::class)->execute($recipient, $entitlement, $asset)->id);
        $this->assertContains($manager->id, app(FamilyAccess::class)->purchaseOwnerIds($recipient));
        $this->get(route('customer.media.library.show', ['kind' => 'competition', 'event' => $event->uuid]))->assertOk();
        $this->get(route('customer.media.library.thumbnail', ['kind' => 'competition', 'event' => $event->uuid, 'asset' => $asset->uuid]))->assertOk();
        [$ownOrder, $ownEntitlement, $ownAsset] = $this->purchase($recipient);
        $this->assertSame($ownAsset->id, app(AuthorizePurchasedMediaDownload::class)->execute($manager, $ownEntitlement, $ownAsset)->id);
        $this->assertDenied(User::factory()->customer()->create(), $entitlement, $asset, 404);
        $this->actingAs($manager)->post(route('customer.family.store'), ['action' => 'remove', 'member_uuid' => $member->uuid])->assertSessionHasNoErrors();
        $this->assertDenied($recipient, $entitlement, $asset, 404);
        $this->assertDenied($manager, $ownEntitlement, $ownAsset, 404);
        $this->assertSame($ownAsset->id, app(AuthorizePurchasedMediaDownload::class)->execute($recipient, $ownEntitlement, $ownAsset)->id);
        $this->assertSame($manager->id, $order->fresh()->user_id);
    }

    public function test_family_sharing_preserves_expiry_revocation_and_asset_boundaries(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        $this->actingAs($recipient)->post($url)->assertSessionHasNoErrors();
        [$order, $entitlement, $asset] = $this->purchase($manager);
        $otherAsset = MediaAsset::factory()->create();
        $this->assertDenied($recipient, $entitlement, $otherAsset, 404);
        $entitlement->update(['expires_at' => now()->subSecond()]);
        $this->assertDenied($recipient, $entitlement, $asset, 410);
        $entitlement->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->assertDenied($recipient, $entitlement, $asset, 404);
        $this->assertEmpty(app(CustomerPurchasedMedia::class)->forUser($recipient)['photos']);
        $entitlement->update(['revoked_at' => null]);
        $order->update(['status' => 'cancelled']);
        $this->assertDenied($recipient, $entitlement, $asset, 404);
    }

    public function test_view_and_purchase_permissions_are_checked_independently_and_again_when_paying(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        $this->actingAs($recipient)->post($url)->assertSessionHasNoErrors();
        [$order, $entitlement, $asset] = $this->purchase($manager);
        $member->update(['can_view_media' => false, 'can_purchase' => true]);
        $this->assertDenied($recipient, $entitlement, $asset, 404);
        app(FamilyAccess::class)->requirePurchasing($recipient);
        $member->update(['can_view_media' => true, 'can_purchase' => false]);
        $this->assertSame($asset->id, app(AuthorizePurchasedMediaDownload::class)->execute($recipient, $entitlement, $asset)->id);
        $draft = Order::factory()->create(['user_id' => $recipient->id]);
        $this->expectException(ValidationException::class);
        app(ValidatePayableOrder::class)->execute($draft);
    }

    public function test_download_history_is_per_viewer_and_member_can_leave(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        $this->actingAs($recipient)->post($url)->assertSessionHasNoErrors();
        [, $entitlement, $asset] = $this->purchase($manager);
        $record = app(RecordPurchasedMediaDownload::class);
        $record->execute($manager, $entitlement, $asset, Request::create('/'));
        $record->execute($recipient, $entitlement, $asset, Request::create('/'));
        $record->execute($recipient, $entitlement, $asset, Request::create('/'));
        $this->assertDatabaseHas('order_media_asset_downloads', ['user_id' => $manager->id, 'download_count' => 1]);
        $this->assertDatabaseHas('order_media_asset_downloads', ['user_id' => $recipient->id, 'download_count' => 2]);
        $this->post(route('customer.family.store'), ['action' => 'leave'])->assertSessionHasNoErrors();
        $this->assertDenied($recipient, $entitlement, $asset, 404);
    }

    public function test_unverified_recipient_cannot_accept_and_expiry_is_enforced(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        $recipient->update(['email_verified_at' => null]);
        $this->actingAs($recipient)->post($url)->assertRedirect(route('customer.verification.notice'));
        $this->assertNull($member->fresh()->user_id);
        $recipient->update(['email_verified_at' => now()]);
        $this->travel(15)->days();
        $this->post($url)->assertGone();
        $this->assertNull($member->fresh()->user_id);
    }

    public function test_another_manager_cannot_edit_or_remove_a_foreign_family_member(): void
    {
        [$manager, $member] = $this->invitation();
        $other = User::factory()->customer()->create();
        $this->actingAs($other)->post(route('customer.family.store'), ['action' => 'remove', 'member_uuid' => $member->uuid])->assertNotFound();
        $this->post(route('customer.family.store'), ['action' => 'save', 'member_uuid' => $member->uuid,
            'first_name' => 'Changed', 'last_name' => 'Name', 'is_dancer' => 1])->assertNotFound();
        $this->assertSame('Sam', $member->fresh()->first_name);
    }

    public function test_download_handoff_is_revoked_when_member_is_removed(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        $this->actingAs($recipient)->post($url)->assertSessionHasNoErrors();
        [, $entitlement, $asset] = $this->purchase($manager);
        $handoff = app(IssuePurchasedMediaDownload::class)->execute($recipient, $entitlement, $asset);
        $check = app(PurchasedDownloadAccess::class);
        $this->assertTrue($check->allows($handoff['download_link']));
        $this->actingAs($manager)->post(route('customer.family.store'), ['action' => 'remove', 'member_uuid' => $member->uuid])->assertSessionHasNoErrors();
        $this->assertFalse($check->allows($handoff['download_link']->fresh()));
        $this->get(route('downloads.public.show', $handoff['token']))->assertGone();
    }

    public function test_zip_cannot_bypass_expired_family_entitlement(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        $this->actingAs($recipient)->post($url)->assertSessionHasNoErrors();
        [, $entitlement, $asset, $event] = $this->purchase($manager);
        $second = MediaAsset::factory()->create(['media_collection_id' => $asset->media_collection_id]);
        $entitlement->assets()->attach($second);
        $entitlement->update(['expires_at' => now()->subDay()]);
        $this->get(route('customer.media.library.download-all', ['kind' => 'competition', 'event' => $event->uuid]))->assertGone();
    }

    public function test_invitation_email_uses_branding_and_explains_consent_and_permissions(): void
    {
        $mail = new FamilyInvitation('Alex Parent', 'Sam', 'https://example.test/invitation', true, false);
        $rendered = $mail->toMail(new \stdClass)->render();
        $this->assertStringContainsString('Hi Sam', $rendered);
        $this->assertStringContainsString('Review family invitation', $rendered);
        $this->assertStringContainsString('14 days', $rendered);
        $this->assertStringContainsString('existing and future purchases', $rendered);
        $this->assertStringContainsString('no purchase permission', $rendered);
    }

    public function test_legacy_dancer_edits_stay_in_sync_and_members_cannot_use_legacy_endpoint_to_manage_family(): void
    {
        [$manager, $member, $recipient, $url] = $this->invitation();
        $this->actingAs($manager)->post(route('customer.profile.store'), ['action' => 'dancer', 'name' => 'Jamie Dancer'])->assertSessionHasNoErrors();
        $dancer = CustomerDancer::where('name', 'Jamie Dancer')->firstOrFail();
        $this->assertDatabaseHas('customer_family_members', ['customer_dancer_id' => $dancer->id, 'is_dancer' => true]);
        $this->post(route('customer.profile.store'), ['action' => 'dancer_update', 'dancer_uuid' => $dancer->uuid, 'name' => 'Jamie Updated'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_family_members', ['customer_dancer_id' => $dancer->id, 'last_name' => 'Updated']);
        $this->actingAs($recipient)->post($url)->assertSessionHasNoErrors();
        $this->post(route('customer.profile.store'), ['action' => 'dancer', 'name' => 'Another Dancer'])->assertForbidden();
    }

    private function invitation(): array
    {
        Notification::fake();
        $manager = User::factory()->customer()->create();
        $recipient = User::factory()->customer()->create(['email' => 'member@example.test']);
        $this->actingAs($manager)->post(route('customer.family.store'), ['action' => 'save', 'first_name' => 'Sam', 'last_name' => 'Member',
            'email' => $recipient->email, 'can_view_media' => 1, 'can_purchase' => 1])->assertSessionHasNoErrors();
        $member = CustomerFamilyMember::where('email', $recipient->email)->firstOrFail();
        $url = null;
        Notification::assertSentOnDemand(FamilyInvitation::class, function ($mail) use (&$url) {
            $url = $mail->invitationUrl;

            return true;
        });

        return [$manager, $member, $recipient, $url];
    }

    private function purchase(User $buyer): array
    {
        Storage::fake('local');
        $event = SchedulingEvent::create(['name' => 'Family competition', 'event_type' => 'competition', 'event_date' => now()]);
        $collection = MediaCollection::factory()->forCompetition($event->id)->create(['media_type' => 'photo']);
        $asset = MediaAsset::factory()->create(['media_collection_id' => $collection->id, 'storage_disk' => 'local', 'storage_key' => 'photos/'.$collection->uuid.'.jpg',
            'thumbnail_storage_disk' => 'local', 'thumbnail_storage_key' => 'thumb.jpg']);
        Storage::disk('local')->put('thumb.jpg', 'thumbnail');
        Storage::disk('local')->put($asset->storage_key, 'photo');
        $order = Order::factory()->paid()->create(['user_id' => $buyer->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => $collection->id, 'media_asset_id' => $asset->id]);
        $entitlement = OrderMediaEntitlement::create(['order_item_id' => $item->id, 'media_type' => 'photo', 'ready_at' => now(), 'expires_at' => now()->addYear()]);
        $entitlement->assets()->attach($asset);

        return [$order, $entitlement, $asset, $event];
    }

    private function assertDenied(User $user, OrderMediaEntitlement $entitlement, MediaAsset $asset, int $status): void
    {
        try {
            app(AuthorizePurchasedMediaDownload::class)->execute($user, $entitlement, $asset);
            $this->fail('Expected media access to be denied.');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }
}
