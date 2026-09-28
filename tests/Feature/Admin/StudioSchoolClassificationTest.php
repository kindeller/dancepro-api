<?php

namespace Tests\Feature\Admin;

use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioSchoolClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_and_unmark_a_school_without_losing_it_when_field_is_omitted(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = ['name' => 'Example School', 'status' => 'active'];
        $this->actingAs($admin)->post(route('admin.studios.store'), $payload + ['is_school' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $studio = Studio::where('name', 'Example School')->firstOrFail();
        $this->assertTrue($studio->is_school);
        $this->get(route('admin.studios.edit', $studio))->assertOk()->assertSee('Organisation type');
        $this->get(route('admin.studios.index'))->assertOk()->assertSee('<span class="badge">School</span>', false);
        $this->put(route('admin.studios.update', $studio), $payload)->assertRedirect();
        $this->assertTrue($studio->fresh()->is_school);
        $this->put(route('admin.studios.update', $studio), $payload + ['is_school' => '0'])->assertRedirect();
        $this->assertFalse($studio->fresh()->is_school);
    }

    public function test_school_classification_is_admin_only_and_validated(): void
    {
        $studio = Studio::factory()->create();
        $this->assertFalse($studio->fresh()->is_school);
        $payload = ['name' => $studio->name, 'status' => 'active', 'is_school' => '1'];
        $this->actingAs(User::factory()->customer()->create())
            ->put(route('admin.studios.update', $studio), $payload)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.studios.update', $studio), array_replace($payload, ['is_school' => 'invalid']))
            ->assertSessionHasErrors('is_school');
        $this->assertFalse($studio->fresh()->is_school);
    }
}
