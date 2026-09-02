<?php

namespace Tests\Feature\Crew;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Crew\Models\CrewRecognition;
use App\Features\Crew\Models\RecognitionType;
use App\Features\Customers\Support\UserType;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewRecognitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_recognition_badge_bank_exists_after_migration(): void
    {
        $this->assertDatabaseCount('recognition_types', 12);
        $this->assertDatabaseHas('recognition_types', ['name' => 'Above & Beyond', 'icon' => '🌟', 'design' => 'gold-star']);
        $this->assertDatabaseHas('recognition_types', ['name' => 'Event Hero', 'icon' => '🎬']);
    }

    public function test_admin_can_create_and_edit_a_recognition_type(): void
    {
        $admin = User::factory()->staff()->create();
        $this->actingAs($admin)->post(route('admin.recognition-types.store'), [
            'name' => 'Brilliant Communicator', 'icon' => '📣', 'design' => 'rainbow',
            'default_message' => 'For keeping everybody informed.', 'is_active' => '1',
        ])->assertRedirect()->assertSessionHas('status', 'Recognition type added.');

        $type = RecognitionType::query()->where('name', 'Brilliant Communicator')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.recognition-types.update', $type), [
            'name' => 'Clear Communicator', 'icon' => '💬', 'design' => 'dp-blue',
            'default_message' => 'Clear and timely communication.', 'is_active' => '0',
        ])->assertRedirect()->assertSessionHas('status', 'Recognition type updated.');

        $this->assertDatabaseHas('recognition_types', ['id' => $type->id, 'name' => 'Clear Communicator', 'is_active' => false]);
    }

    public function test_admin_can_customise_and_award_one_recognition_to_multiple_crew_members(): void
    {
        $admin = User::factory()->staff()->create();
        $type = RecognitionType::query()->where('name', 'Event Hero')->firstOrFail();
        $crew = CrewProfile::factory()->count(2)->sequence(
            ['preferred_name' => 'Alex'], ['preferred_name' => 'Sam'],
        )->create();
        $event = SchedulingEvent::query()->create(['name' => 'Winter Showcase', 'event_type' => 'concert', 'event_date' => '2026-08-20']);

        $this->actingAs($admin)->post(route('admin.recognitions.store'), [
            'recognition_type_id' => $type->id, 'crew_profile_ids' => $crew->pluck('id')->all(),
            'scheduling_event_id' => $event->id, 'title' => 'Showcase Superstars',
            'message' => 'You made a difficult show feel effortless.', 'icon' => '✨',
            'design' => 'rainbow', 'awarded_on' => '2026-08-21', 'show_on_profile' => '1',
        ])->assertRedirect()->assertSessionHas('status', '2 recognitions awarded.');

        $this->assertSame(2, CrewRecognition::query()->count());
        $this->assertDatabaseHas('crew_recognitions', ['crew_profile_id' => $crew[0]->id, 'title' => 'Showcase Superstars', 'icon' => '✨', 'design' => 'rainbow', 'scheduling_event_id' => $event->id]);
        $this->assertDatabaseHas('crew_recognitions', ['crew_profile_id' => $crew[1]->id, 'title' => 'Showcase Superstars']);

        $type->update(['name' => 'Changed template', 'icon' => 'X', 'design' => 'red-bolt']);
        $this->assertSame(['Showcase Superstars'], CrewRecognition::query()->pluck('title')->unique()->all());
    }

    public function test_visible_recognition_appears_on_my_profile_with_hover_details_and_hidden_recognition_does_not(): void
    {
        $user = User::factory()->crew()->create();
        $profile = CrewProfile::factory()->for($user)->create(['commencement_date' => null]);
        CrewRecognition::query()->create(['crew_profile_id' => $profile->id, 'title' => 'Visible Hero', 'message' => 'Wonderful teamwork.', 'icon' => '🌟', 'design' => 'gold-star', 'awarded_on' => '2026-08-21', 'show_on_profile' => true]);
        CrewRecognition::query()->create(['crew_profile_id' => $profile->id, 'title' => 'Private Note', 'message' => 'For internal eyes.', 'icon' => '💬', 'design' => 'purple-heart', 'awarded_on' => '2026-08-22', 'show_on_profile' => false]);

        $this->actingAs($user)->get(route('crew.profile.edit'))->assertOk()
            ->assertSee('Recognition')->assertSee('Visible Hero')->assertSee('Wonderful teamwork.')
            ->assertSee('design-gold-star')->assertSee('21 Aug 2026')->assertDontSee('Private Note');
    }

    public function test_customer_and_crew_accounts_cannot_manage_recognitions(): void
    {
        $customer = User::factory()->customer()->create(['type' => UserType::Customer->value]);
        $crew = User::factory()->crew()->create();
        CrewProfile::factory()->for($crew)->create();

        foreach ([$customer, $crew] as $user) {
            $this->actingAs($user)->get(route('admin.crew-management.recognitions-rewards'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.recognition-types.store'), [])->assertForbidden();
            $this->actingAs($user)->post(route('admin.recognitions.store'), [])->assertForbidden();
        }
    }
}
