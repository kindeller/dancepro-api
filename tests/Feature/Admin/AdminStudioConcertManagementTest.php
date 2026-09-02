<?php

namespace Tests\Feature\Admin;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Support\ConcertStatus;
use App\Features\Customers\Support\UserType;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminStudioConcertManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_browse_create_and_edit_studios(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/admin/studios')
            ->assertOk()
            ->assertSee('Add studio');

        $this->actingAs($staff)->post('/admin/studios', [
            'name' => 'Fictional Coast Dance',
            'code' => 'fcd',
            'status' => 'active',
            'brand_color' => '#126A8A',
            'contacts' => [
                ['name' => 'Morgan Director', 'role' => 'Studio owner', 'emails' => 'studio@example.test, accounts@example.test', 'phone' => '0400 111 222'],
                ['name' => 'Taylor Admin', 'role' => 'Administrator', 'emails' => 'admin@example.test'],
            ],
            'description' => 'A fictional studio used by this feature test.',
            'logo' => UploadedFile::fake()->image('studio-logo.jpg', 3508, 2480),
        ])->assertRedirect();

        $studio = Studio::query()->where('name', 'Fictional Coast Dance')->firstOrFail();
        $this->assertNotNull($studio->uuid);
        $this->assertSame('FCD', $studio->code);
        $this->assertStringStartsWith('fictional-coast-dance-', $studio->slug);
        $this->assertCount(2, $studio->contacts);
        $this->assertSame(['studio@example.test', 'accounts@example.test'], $studio->contacts->first()->emailAddresses());
        $this->assertSame(['studio@example.test', 'accounts@example.test', 'admin@example.test'], $studio->contactEmailAddresses());
        $this->assertSame('studio@example.test', $studio->contact_email);
        Storage::disk('public')->assertExists($studio->logo_path);

        $this->actingAs($staff)->put('/admin/studios/'.$studio->uuid, [
            'name' => 'Fictional Coast Performing Arts',
            'code' => 'fcpa',
            'slug' => $studio->slug,
            'status' => 'inactive',
            'brand_color' => '#126A8A',
        ])->assertRedirect();

        $this->assertDatabaseHas('studios', [
            'uuid' => $studio->uuid,
            'name' => 'Fictional Coast Performing Arts',
            'code' => 'FCPA',
            'status' => 'inactive',
        ]);
        $this->actingAs($staff)->get(route('admin.studios.edit', $studio))->assertOk()->assertSee('Studio code')->assertSee($studio->logoUrl(), false);

        $this->actingAs($staff)->get(route('admin.studios.index', ['search' => 'FCPA']))
            ->assertOk()
            ->assertSee('Fictional Coast Performing Arts')
            ->assertSee('studio-thumbnail', false)
            ->assertSee($studio->logoUrl(), false);
    }

    public function test_each_studio_staff_member_may_have_multiple_comma_separated_email_addresses(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post('/admin/studios', [
            'name' => 'Multiple Contact Studio',
            'status' => 'active',
            'contacts' => [[
                'name' => 'Casey Manager',
                'emails' => ' CASEY@example.test, bookings@example.test, casey@example.test ',
            ]],
        ])->assertRedirect();

        $studio = Studio::query()->where('name', 'Multiple Contact Studio')->firstOrFail();

        $this->assertSame(['casey@example.test', 'bookings@example.test'], $studio->contacts->first()->emailAddresses());

        $this->actingAs($staff)->post('/admin/studios', [
            'name' => 'Invalid Contact Studio',
            'status' => 'active',
            'contacts' => [['name' => 'Casey Manager', 'emails' => 'valid@example.test, not-an-email']],
        ])->assertSessionHasErrors('contacts.0.emails');
    }

    public function test_studio_contacts_are_grouped_and_status_can_be_changed_inline(): void
    {
        $staff = User::factory()->staff()->create();
        $active = Studio::factory()->create(['name' => 'Active Studio', 'code' => 'ACT', 'status' => 'active']);
        $inactive = Studio::factory()->create(['name' => 'Inactive Studio', 'code' => 'INA', 'status' => 'inactive']);

        $response = $this->actingAs($staff)->get(route('admin.studios.index'));

        $response->assertOk()
            ->assertSeeInOrder(['<h2 class="studio-group-heading">Active (1)</h2>', 'Active Studio', '<h2 class="studio-group-heading">Inactive (1)</h2>', 'Inactive Studio'], false)
            ->assertSee('data-href="'.route('admin.studios.edit', $active).'"', false)
            ->assertDontSee('Active studio contacts')
            ->assertSee('status-'.$active->uuid, false)
            ->assertDontSee('>Studio status<', false)
            ->assertDontSee($active->slug);

        $this->actingAs($staff)->patch(route('admin.studios.status.update', $active), [
            'status' => 'inactive',
        ])->assertRedirect();

        $this->assertDatabaseHas('studios', ['id' => $active->id, 'status' => 'inactive']);

        $this->actingAs($staff)->patch(route('admin.studios.status.update', $inactive), [
            'status' => 'not-a-status',
        ])->assertSessionHasErrors('status');
    }

    public function test_staff_can_create_and_approve_a_password_protected_concert(): void
    {
        $staff = User::factory()->staff()->create();
        $studio = Studio::factory()->create();

        $this->actingAs($staff)->post('/admin/concerts', [
            'studio_id' => $studio->id,
            'name' => 'Fictional Winter Showcase',
            'status' => ConcertStatus::Published->value,
            'event_date' => now()->subDay()->toDateString(),
            'is_enabled' => '1',
            'requires_approval' => '1',
            'is_approved' => '0',
            'access_password' => 'concert-password',
            'available_from' => now()->subHour()->format('Y-m-d\TH:i'),
            'available_until' => now()->addWeek()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $concert = Concert::query()->where('name', 'Fictional Winter Showcase')->firstOrFail();
        $this->assertFalse($concert->isPubliclyAvailable());
        $this->assertTrue($concert->passwordMatches('concert-password'));
        $this->assertSame($concert->uuid.'/', $concert->storage_prefix);

        $this->actingAs($staff)->put('/admin/concerts/'.$concert->uuid, [
            'studio_id' => $studio->id,
            'name' => $concert->name,
            'slug' => $concert->slug,
            'status' => ConcertStatus::Published->value,
            'event_date' => $concert->event_date->toDateString(),
            'is_enabled' => '1',
            'requires_approval' => '1',
            'is_approved' => '1',
            'available_from' => now()->subHour()->format('Y-m-d\TH:i'),
            'available_until' => now()->addWeek()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $concert->refresh();
        $this->assertTrue($concert->isPubliclyAvailable());
        $this->assertTrue($concert->approvedBy->is($staff));
        $this->assertTrue($concert->passwordMatches('concert-password'));
    }

    public function test_customer_cannot_manage_studios_or_concerts(): void
    {
        $customer = User::factory()->customer()->create(['type' => UserType::Customer->value]);

        $this->actingAs($customer)->get('/admin/studios')->assertForbidden();
        $this->actingAs($customer)->get('/admin/concerts')->assertForbidden();
    }

    public function test_admin_navigation_contains_studio_and_concert_links(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee(route('admin.studios.index'), false)
            ->assertSee(route('admin.concerts.index'), false);
    }

    public function test_studio_edit_page_lists_associated_concerts_and_links_to_their_admin_pages(): void
    {
        $staff = User::factory()->staff()->create();
        $studio = Studio::factory()->create();
        $concert = Concert::factory()->published()->for($studio)->create([
            'name' => 'Fictional Linked Concert',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.studios.edit', $studio))
            ->assertOk()
            ->assertSee('Associated Concerts')
            ->assertSee('Fictional Linked Concert')
            ->assertSee(route('admin.concerts.edit', $concert), false)
            ->assertSee(route('concerts.show', $concert), false)
            ->assertSee(route('admin.concerts.create', ['studio_id' => $studio->id]), false);
    }

    public function test_add_concert_link_preselects_the_studio(): void
    {
        $staff = User::factory()->staff()->create();
        $studio = Studio::factory()->create();

        $this->actingAs($staff)
            ->get(route('admin.concerts.create', ['studio_id' => $studio->id]))
            ->assertOk()
            ->assertSee('value="'.$studio->id.'" selected', false);
    }
}
