<?php

namespace Tests\Feature\Customers;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Concerts\Models\ConcertCodeGroup;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Services\CustomerConcertAccess;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConcertCodeGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_code_unlocks_selected_concerts_without_restarting_access_periods(): void
    {
        $studio = Studio::factory()->create();
        $first = Concert::factory()->published()->create(['studio_id' => $studio->id, 'event_date' => '2026-06-01']);
        $second = Concert::factory()->published()->create(['studio_id' => $studio->id, 'event_date' => '2026-07-01']);
        $admin = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.concert-code-groups.index', $studio))->assertOk()->assertSee('Create code group');
        $this->post(route('admin.concert-code-groups.store', $studio), [
            'name' => 'Junior concerts', 'year' => 2026, 'code' => 'JUNIOR-26', 'concert_uuids' => [$first->uuid],
        ])->assertRedirect();
        $group = ConcertCodeGroup::firstOrFail();
        $first->refresh();
        $this->assertTrue($first->requiresPassword());
        $this->assertTrue(Hash::check('JUNIOR-26', $group->code_hash));
        $this->assertNull($group->released_at);
        $this->actingAs($customer)->post(route('customer.media.concerts.store'), ['year' => 2026, 'concert_code' => 'JUNIOR-26'])->assertSessionHasErrors('concert_code');

        $this->actingAs($admin)->post(route('admin.concert-code-groups.release', [$studio, $group]), ['version' => 1])->assertRedirect();
        $group->refresh();
        $this->assertNotNull($group->released_at);
        $this->actingAs($customer)->post(route('customer.media.concerts.store'), ['year' => 2026, 'concert_code' => 'junior-26'])->assertRedirect();
        $grant = ConcertAccessGrant::where('user_id', $customer->id)->where('concert_id', $first->id)->firstOrFail();
        $this->assertSame($group->id, $grant->concert_code_group_id);
        $this->assertTrue($grant->expires_at->equalTo($group->downloadEndsAt()));
        $this->assertTrue(app(CustomerConcertAccess::class)->allowsStreaming($customer, $first));

        $this->travelTo($group->released_at->copy()->addMonths(7));
        $this->assertFalse(app(CustomerConcertAccess::class)->allowsStreaming($customer, $first));
        $this->assertTrue(app(CustomerConcertAccess::class)->allows($customer, $first));
        $this->actingAs($admin)->post(route('admin.concert-code-groups.concerts', [$studio, $group]), [
            'version' => $group->version, 'concert_uuids' => [$second->uuid],
        ])->assertRedirect();
        $second->refresh();
        $this->actingAs($customer)->post(route('customer.media.concerts.store'), ['year' => 2026, 'concert_code' => 'JUNIOR-26'])->assertRedirect();
        $laterGrant = ConcertAccessGrant::where('user_id', $customer->id)->where('concert_id', $second->id)->firstOrFail();
        $this->assertTrue($laterGrant->expires_at->equalTo($group->downloadEndsAt()));

        $this->travelTo($group->released_at->copy()->addMonths(13));
        $this->assertFalse(app(CustomerConcertAccess::class)->allows($customer, $first));
        $this->post(route('customer.media.concerts.store'), ['year' => 2026, 'concert_code' => 'JUNIOR-26'])->assertSessionHasErrors('concert_code');
    }

    public function test_group_rejects_wrong_studio_and_stale_edit_without_exposing_code(): void
    {
        $studio = Studio::factory()->create();
        $other = Studio::factory()->create();
        $concert = Concert::factory()->create(['studio_id' => $other->id, 'event_date' => '2026-06-01']);
        $admin = User::factory()->staff()->create();
        $this->actingAs($admin)->post(route('admin.concert-code-groups.store', $studio), [
            'name' => 'Wrong studio', 'year' => 2026, 'code' => 'JUNIOR-26', 'concert_uuids' => [$concert->uuid],
        ])->assertSessionHasErrors('concert_uuids');
        $this->assertDatabaseCount('concert_code_groups', 0);
    }

    public function test_group_cannot_start_a_download_clock_longer_than_concert_availability(): void
    {
        $studio = Studio::factory()->create();
        $concert = Concert::factory()->published()->create(['studio_id' => $studio->id, 'event_date' => '2026-06-01',
            'available_until' => now()->addMonth()]);
        $admin = User::factory()->staff()->create();
        $this->actingAs($admin)->post(route('admin.concert-code-groups.store', $studio), [
            'name' => 'Short release', 'year' => 2026, 'code' => 'SHORT-26', 'concert_uuids' => [$concert->uuid],
        ])->assertRedirect();
        $group = ConcertCodeGroup::firstOrFail();
        $this->post(route('admin.concert-code-groups.release', [$studio, $group]), ['version' => 1])->assertSessionHasErrors('version');
        $this->assertNull($group->fresh()->released_at);
    }

    public function test_code_rotation_is_audited_without_storing_the_code_or_revoking_grants(): void
    {
        $studio = Studio::factory()->create();
        $concert = Concert::factory()->published()->create(['studio_id' => $studio->id, 'event_date' => '2026-06-01']);
        $admin = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        CustomerStudio::create(['user_id' => $customer->id, 'studio_id' => $studio->id, 'access_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.concert-code-groups.store', $studio), [
            'name' => 'Evening', 'year' => 2026, 'code' => 'OLD-CODE', 'concert_uuids' => [$concert->uuid],
        ])->assertRedirect();
        $group = ConcertCodeGroup::firstOrFail();
        $this->post(route('admin.concert-code-groups.release', [$studio, $group]), ['version' => 1])->assertRedirect();
        $this->actingAs($customer)->post(route('customer.media.concerts.store'), ['year' => 2026, 'concert_code' => 'OLD-CODE'])->assertRedirect();
        $this->assertTrue(app(CustomerConcertAccess::class)->allows($customer, $concert->fresh()));
        $this->actingAs($admin)->post(route('admin.concert-code-groups.code', [$studio, $group]), [
            'version' => $group->fresh()->version, 'code' => 'NEW-CODE',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('NEW-CODE', $group->fresh()->code_hash));
        $this->assertTrue(app(CustomerConcertAccess::class)->allows($customer, $concert->fresh()));
        $this->assertSame(['created', 'released', 'code_changed'], DB::table('concert_code_group_changes')->orderBy('id')->pluck('action')->all());
        $this->assertStringNotContainsString('NEW-CODE', DB::table('concert_code_group_changes')->get()->toJson());
    }
}
