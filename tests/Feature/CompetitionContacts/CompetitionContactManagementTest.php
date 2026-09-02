<?php

namespace Tests\Feature\CompetitionContacts;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompetitionContactManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_manage_competition_contacts_from_contacts_tabs(): void
    {
        Storage::fake('public');
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.competition-contacts.index'))
            ->assertOk()->assertSee('Studios')->assertSee('Competitions')->assertSee('Crew')
            ->assertSee('Add competition contact');

        $this->actingAs($staff)->post(route('admin.competition-contacts.store'), [
            'name' => 'Perth Dance Challenge',
            'code' => 'pdc',
            'staff' => [
                ['name' => 'Jordan Smith', 'role' => 'Director', 'emails' => 'jordan@example.test, accounts@example.test', 'phone' => '0400 321 654'],
                ['name' => 'Casey Admin', 'role' => 'Administrator', 'emails' => 'casey@example.test'],
            ],
            'logo' => UploadedFile::fake()->image('competition-logo.jpg', 1200, 800),
            'is_active' => '1',
        ])->assertRedirect();

        $contact = CompetitionContact::query()->firstOrFail();
        $this->assertCount(2, $contact->staff);
        $this->assertSame(['jordan@example.test', 'accounts@example.test', 'casey@example.test'], $contact->contactEmailAddresses());
        $this->assertSame('jordan@example.test', $contact->organiser_email);
        $this->assertSame('PDC', $contact->code);
        Storage::disk('public')->assertExists($contact->logo_path);
        $this->actingAs($staff)->get(route('admin.competition-contacts.index'))
            ->assertOk()->assertSee('competition-thumbnail', false)->assertSee($contact->logoUrl(), false)
            ->assertSee('competition-code', false)->assertSee('PDC')
            ->assertSee('data-copy-emails', false);
        $this->actingAs($staff)->get(route('admin.scheduling-events.create', ['type' => 'competition']))
            ->assertOk()->assertSee('Saved competition')->assertSee($contact->name)
            ->assertSee($contact->organiser_email);
    }

    public function test_competition_staff_email_list_validates_every_address(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('admin.competition-contacts.store'), [
            'name' => 'Invalid Email Competition',
            'staff' => [['name' => 'Jordan Smith', 'emails' => 'valid@example.test, invalid-email']],
            'is_active' => '1',
        ])->assertSessionHasErrors('staff.0.emails');
    }

    public function test_competition_contacts_are_grouped_and_status_can_be_changed_inline(): void
    {
        $staff = User::factory()->staff()->create();
        $inactive = CompetitionContact::query()->create([
            'name' => 'Inactive Competition',
            'organiser_name' => 'Inactive Organiser',
            'organiser_email' => 'inactive@example.test',
            'organiser_phone' => '',
            'is_active' => false,
        ]);
        CompetitionContact::query()->create([
            'name' => 'Active Competition',
            'organiser_name' => 'Active Organiser',
            'organiser_email' => 'active@example.test',
            'organiser_phone' => '',
            'is_active' => true,
        ]);

        $this->actingAs($staff)->get(route('admin.competition-contacts.index'))
            ->assertOk()
            ->assertSeeInOrder(['>Active (1)</h2>', 'Active Competition', '>Inactive (1)</h2>', 'Inactive Competition'], false)
            ->assertDontSee('>Edit</a>', false);

        $this->actingAs($staff)->patch(route('admin.competition-contacts.status.update', $inactive), [
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertTrue($inactive->fresh()->is_active);
    }

    public function test_selected_competition_contact_is_linked_while_event_keeps_contact_snapshot(): void
    {
        $staff = User::factory()->staff()->create();
        $contact = CompetitionContact::query()->create([
            'name' => 'West Coast Competition', 'organiser_name' => 'Avery Jones',
            'organiser_email' => 'avery@example.test', 'organiser_phone' => '0400 555 777', 'is_active' => true,
        ]);

        $this->actingAs($staff)->post(route('admin.scheduling-events.store'), [
            'competition_contact_id' => $contact->id,
            'name' => $contact->name, 'organiser_name' => $contact->organiser_name,
            'organiser_email' => $contact->organiser_email, 'organiser_phone' => $contact->organiser_phone,
            'event_type' => 'competition', 'roles' => ['competition-videographer'],
            'days' => [['date' => now()->addMonth()->toDateString(), 'morning' => '1', 'afternoon' => '0']],
        ])->assertRedirect();

        $this->assertDatabaseHas('scheduling_events', [
            'competition_contact_id' => $contact->id, 'organiser_name' => 'Avery Jones',
            'organiser_email' => 'avery@example.test', 'organiser_phone' => '0400 555 777',
        ]);
    }
}
