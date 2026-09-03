<?php

namespace Tests\Feature\Operations;

use App\Features\Auth\Support\TokenAbility;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Operations\Models\OperationalResource;
use App\Features\Training\Models\TrainingCourse;
use App\Features\Training\Models\TrainingEnrolment;
use App\Features\Training\Models\TrainingModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrewMobileOfflineApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_private_documents_return_sync_metadata_and_a_short_lived_protected_download(): void
    {
        $this->authenticatedCrew();
        Storage::fake('local');
        Storage::disk('local')->put('operations/guide.pdf', 'private handbook contents');
        $resource = OperationalResource::query()->create([
            'title' => 'Crew handbook', 'resource_type' => 'handbook',
            'file_path' => 'operations/guide.pdf', 'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/documents')->assertOk()
            ->assertJsonPath('data.0.id', $resource->uuid)
            ->assertJsonPath('data.0.bytes', strlen('private handbook contents'))
            ->assertJsonPath('data.0.checksum', hash('sha256', 'private handbook contents'));
        $resource->refresh();
        $this->assertSame(strlen('private handbook contents'), $resource->file_size);
        $this->assertSame(hash('sha256', 'private handbook contents'), $resource->file_checksum);

        Storage::disk('local')->put('operations/guide.pdf', 'changed after metadata was recorded');
        $this->getJson('/api/v1/documents')->assertOk()
            ->assertJsonPath('data.0.bytes', strlen('private handbook contents'))
            ->assertJsonPath('data.0.checksum', hash('sha256', 'private handbook contents'));
        Storage::disk('local')->put('operations/guide.pdf', 'private handbook contents');
        $this->assertStringNotContainsString('operations/guide.pdf', $response->getContent());

        $url = $this->postJson('/api/v1/documents/'.$resource->uuid.'/download')->assertOk()->json('data.url');
        $this->get($url)->assertOk()->assertHeader('cache-control', 'no-store, private');
        $this->get('/api/v1/documents/'.$resource->uuid.'/content')->assertForbidden();

        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
        $this->travelBack();

        $resource->update(['is_active' => false]);
        $this->get($url)->assertNotFound();
    }

    public function test_a_valid_document_signature_does_not_bypass_authentication(): void
    {
        $resource = OperationalResource::query()->create([
            'title' => 'Crew handbook', 'resource_type' => 'handbook',
            'file_path' => 'operations/guide.pdf', 'is_active' => true,
        ]);
        $url = URL::temporarySignedRoute(
            'api.v1.documents.content',
            now()->addMinutes(5),
            ['document' => $resource->uuid],
        );

        $this->get($url)->assertUnauthorized();
    }

    public function test_document_delta_validation_and_training_are_scoped_to_available_records(): void
    {
        [, $profile] = $this->authenticatedCrew();
        $this->getJson('/api/v1/documents?updated_since=not-a-date')->assertUnprocessable();

        $published = TrainingCourse::query()->create(['title' => 'Camera induction', 'status' => 'published']);
        $draft = TrainingCourse::query()->create(['title' => 'Private draft', 'status' => 'draft']);
        $first = TrainingModule::query()->create(['training_course_id' => $published->id, 'title' => 'Setup', 'module_type' => 'content']);
        TrainingModule::query()->create(['training_course_id' => $published->id, 'title' => 'Pack down', 'module_type' => 'content']);
        $enrolment = TrainingEnrolment::query()->create([
            'training_course_id' => $published->id, 'crew_profile_id' => $profile->id,
            'status' => 'in_progress', 'started_at' => now(),
        ]);
        $enrolment->moduleProgress()->create(['training_module_id' => $first->id, 'passed' => true, 'attempts' => 1, 'completed_at' => now()]);

        $this->getJson('/api/v1/training')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->uuid)
            ->assertJsonPath('data.0.progress', 50)
            ->assertJsonMissing(['id' => $draft->uuid]);
    }

    /** @return array{User, CrewProfile} */
    private function authenticatedCrew(): array
    {
        $user = User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        $profile = CrewProfile::factory()->for($user)->create([
            'phone' => '0400 000 000', 'address_line_1' => '1 Test Street', 'suburb' => 'Perth',
            'state' => 'WA', 'postcode' => '6000', 'working_with_children_number' => 'WWC123',
            'working_with_children_expiry' => today()->addYear(),
        ]);
        Sanctum::actingAs($user, [TokenAbility::CrewMobile->value]);

        return [$user, $profile];
    }
}
