<?php

namespace Tests\Feature\Admin;

use App\Features\Downloads\Models\DownloadAccess;
use App\Features\Downloads\Models\DownloadLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_admin_dashboard(): void
    {
        $this->get('/admin')
            ->assertRedirect('/login');
    }

    public function test_active_user_can_login_to_web_admin(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticated();
    }

    public function test_inactive_user_cannot_login_to_web_admin(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => 'password',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'inactive@example.test',
            'password' => 'password',
        ])->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_dashboard_shows_download_link_counts(): void
    {
        $user = User::factory()->create();
        $downloadLink = DownloadLink::factory()->create();
        DownloadAccess::factory()->for($downloadLink)->create([
            'was_successful' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Total links')
            ->assertSee('Access attempts')
            ->assertSee($downloadLink->storage_key);
    }

    public function test_admin_can_create_download_links_from_storage_keys(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/admin/download-links', [
                'keys' => "competition-a/video-1.mp4\ncompetition-a/video-2.mp4",
                'disk' => 's3_competitions',
                'days' => 30,
                'purpose' => 'Competition download links',
            ]);

        $response
            ->assertRedirect('/admin/download-links/create')
            ->assertSessionHas('created_links');

        $this->assertDatabaseHas('download_links', [
            'storage_disk' => 's3_competitions',
            'storage_key' => 'competition-a/video-1.mp4',
            'generated_by_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('download_links', [
            'storage_disk' => 's3_competitions',
            'storage_key' => 'competition-a/video-2.mp4',
            'generated_by_user_id' => $user->id,
        ]);
    }

    public function test_existing_download_link_detail_does_not_expose_raw_public_token(): void
    {
        $user = User::factory()->create();
        $downloadLink = DownloadLink::factory()->create([
            'storage_key' => 'competition-a/private-video.mp4',
        ]);

        $this->actingAs($user)
            ->get('/admin/download-links/'.$downloadLink->uuid)
            ->assertOk()
            ->assertSee('private-video.mp4')
            ->assertSee('The original public token is not stored')
            ->assertDontSee('/download/');
    }
}
