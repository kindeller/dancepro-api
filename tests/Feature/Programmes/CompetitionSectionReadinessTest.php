<?php

namespace Tests\Feature\Programmes;

use App\Features\Crew\Models\CrewRole;
use App\Features\Programmes\Models\CompetitionProgrammeAddition;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammePhotoContribution;
use App\Features\Programmes\Models\ProgrammePhotoFile;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Programmes\Services\CompetitionSectionReadiness;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompetitionSectionReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $event = SchedulingEvent::create(['name' => 'Readiness test', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $p1 = CrewRole::where('code', 'competition-photographer-p1')->firstOrFail();
        $p2 = CrewRole::where('code', 'competition-photographer-p2')->firstOrFail();
        $video = CrewRole::where('code', 'competition-videographer')->firstOrFail();
        foreach ([$p1, $p2, $video] as $role) {
            $event->roleRequirements()->create(['crew_role_id' => $role->id, 'quantity' => 1]);
        }
        $event->shifts()->create(['shift_date' => '2026-09-04', 'period' => 'morning']);
        $day = (string) Str::uuid();
        $section = (string) Str::uuid();
        $item = (string) Str::uuid();
        $snapshot = ['event_type' => 'competition', 'nodes' => [
            ['uuid' => $day, 'kind' => 'day', 'title' => 'Friday', 'date' => '2026-09-04'],
            ['uuid' => $section, 'kind' => 'section', 'title' => 'Jazz', 'number' => '7', 'parent_uuid' => $day],
            ['uuid' => $item, 'kind' => 'item', 'title' => 'Jessica', 'number' => '3', 'parent_uuid' => $section],
        ]];
        $revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'snapshot' => $snapshot]);
        $plan = ProgrammeUploadPlan::create(['programme_id' => $programme->id, 'programme_revision_id' => $revision->id, 'digest' => hash('sha256', json_encode($snapshot)), 'snapshot' => $snapshot]);
        $gallery = ProgrammePhotoGallery::create(['scheduling_event_id' => $event->id, 'programme_upload_plan_id' => $plan->id]);

        return compact('event', 'programme', 'plan', 'gallery', 'p1', 'p2', 'video', 'day', 'section', 'item');
    }

    private function contribution(array $f, CrewRole $role, string $status = 'complete'): ProgrammePhotoContribution
    {
        return ProgrammePhotoContribution::create(['programme_upload_plan_id' => $f['plan']->id, 'item_uuid' => $f['item'],
            'crew_role_id' => $role->id, 'assignment_uuids' => [], 'status' => $status,
            'completed_at' => $status === 'complete' ? now() : null, 'waived_at' => $status === 'waived' ? now() : null]);
    }

    private function readiness(array $f): array
    {
        return app(CompetitionSectionReadiness::class)->forPlan($f['event']->fresh(), $f['plan']->fresh())[$f['section']];
    }

    public function test_both_roles_must_explicitly_finish_and_video_does_not_block(): void
    {
        $f = $this->fixture();
        $this->assertSame('waiting', $this->readiness($f)['status']);
        $p1 = $this->contribution($f, $f['p1']);
        $this->assertFalse($this->readiness($f)['ready']);
        $p2 = $this->contribution($f, $f['p2'], 'receiving');
        $this->assertFalse($this->readiness($f)['ready']);
        $p2->update(['status' => 'complete', 'completed_at' => now()]);
        $result = $this->readiness($f);
        $this->assertTrue($result['ready']);
        $this->assertCount(2, $result['roles']);
        $this->assertSame('private', $f['gallery']->fresh()->visibility);
        $p1->update(['completed_at' => null]);
        $this->assertFalse($this->readiness($f)['ready']);
    }

    public function test_pending_bytes_block_completion_but_waivers_remain_explicit_exceptions(): void
    {
        $f = $this->fixture();
        $p1 = $this->contribution($f, $f['p1']);
        $p2 = $this->contribution($f, $f['p2'], 'waived');
        $this->assertSame('ready_with_exceptions', $this->readiness($f)['status']);
        $file = ProgrammePhotoFile::create(['programme_photo_contribution_id' => $p1->id, 'programme_photo_gallery_id' => $f['gallery']->id,
            'client_photo_uuid' => (string) Str::uuid(), 'file_name' => 'photo.jpg', 'byte_count' => 10, 'sha256' => str_repeat('a', 64), 'status' => 'reserved']);
        $this->assertSame('waiting', $this->readiness($f)['status']);
        $file->update(['status' => 'verified', 'verified_at' => now(), 'storage_key' => 'photo.jpg', 'storage_disk' => 'local']);
        $this->assertSame('ready_with_exceptions', $this->readiness($f)['status']);
        $file->update(['programme_photo_gallery_id' => null]);
        $this->assertSame('waiting', $this->readiness($f)['status']);
        $file->update(['programme_photo_gallery_id' => $f['gallery']->id]);
        $p2->update(['status' => 'receiving', 'waived_at' => null]);
        $this->assertSame('waiting', $this->readiness($f)['status']);
    }

    public function test_additions_and_stale_programmes_cannot_silently_stay_ready(): void
    {
        $f = $this->fixture();
        $this->contribution($f, $f['p1']);
        $this->contribution($f, $f['p2']);
        $this->assertTrue($this->readiness($f)['ready']);
        $uuid = (string) Str::uuid();
        CompetitionProgrammeAddition::create(['programme_upload_plan_id' => $f['plan']->id, 'node_uuid' => $uuid, 'node' => [
            'uuid' => $uuid, 'kind' => 'item', 'title' => 'Extra competitor', 'number' => '4', 'parent_uuid' => $f['section'],
        ]]);
        $this->assertSame('waiting', $this->readiness($f)['status']);
        $this->assertSame(2, $this->readiness($f)['roles'][0]['expected']);
        $f['programme']->revisions()->create(['version' => 2, 'confirmed_at' => now(), 'snapshot' => $f['plan']->snapshot]);
        $this->assertSame('needs_attention', $this->readiness($f)['status']);
    }

    public function test_deactivating_a_required_role_does_not_hide_missing_work(): void
    {
        $f = $this->fixture();
        $this->contribution($f, $f['p1']);
        $f['p2']->update(['is_active' => false]);
        $this->assertSame('needs_setup', $this->readiness($f)['status']);
        $this->assertFalse($this->readiness($f)['ready']);
    }

    public function test_scope_follows_section_date_and_missing_setup_is_not_ready(): void
    {
        $f = $this->fixture();
        $snapshot = $f['plan']->snapshot;
        $otherDay = (string) Str::uuid();
        $otherSection = (string) Str::uuid();
        $snapshot['nodes'][] = ['uuid' => $otherDay, 'kind' => 'day', 'title' => 'Saturday', 'date' => '2026-09-05'];
        $snapshot['nodes'][] = ['uuid' => $otherSection, 'kind' => 'section', 'title' => 'Tap', 'number' => '8', 'parent_uuid' => $otherDay];
        $snapshot['nodes'][] = ['uuid' => (string) Str::uuid(), 'kind' => 'item', 'title' => 'Other dancer', 'number' => '1', 'parent_uuid' => $otherSection];
        $f['plan']->update(['snapshot' => $snapshot]);
        $this->contribution($f, $f['p1']);
        $this->contribution($f, $f['p2']);
        $all = app(CompetitionSectionReadiness::class)->forPlan($f['event'], $f['plan']);
        $this->assertSame('ready', $all[$f['section']]['status']);
        $this->assertSame('needs_setup', $all[$otherSection]['status']);
        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('admin.competition-gallery.show', ['schedulingEvent' => $f['event'], 'folder' => $f['section']]))
            ->assertOk()->assertSee('Contributions ready')->assertSee('Photos remain private')->assertSee('Competitors complete');
    }
}
