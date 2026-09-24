<?php

namespace Tests\Feature\Programmes;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeUploadPlan;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesApiTokens;
use Tests\TestCase;

class ProgrammeSyncTest extends TestCase
{
    use AuthenticatesApiTokens, RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->staff()->create();
        $event = SchedulingEvent::create(['name' => 'Sync test', 'event_type' => 'concert', 'event_date' => '2026-09-04']);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $nodes = array_map(fn ($i) => [
            'uuid' => (string) Str::uuid(), 'kind' => 'item', 'title' => "Dance $i",
            'folder_number' => "0$i", 'folder_title' => "Dance $i",
        ], [1, 2]);
        $revision = $programme->revisions()->create(['version' => 1, 'confirmed_at' => now(), 'snapshot' => [
            'schema_version' => 1, 'event_uuid' => $event->uuid, 'event_type' => 'concert',
            'programme_uuid' => $programme->uuid, 'event_name' => $event->name, 'version' => 1, 'nodes' => $nodes,
        ]]);
        $this->actingAsApiUser($user, ['concert-media:upload']);

        return [$event, $programme, $revision, ['revision_uuid' => $revision->uuid, 'nodes' => $nodes], $user];
    }

    private function url(SchedulingEvent $event): string
    {
        return "/api/staff/events/{$event->uuid}/programme/sync";
    }

    public function test_retry_is_idempotent_and_approved_programme_is_unchanged(): void
    {
        [$event, $programme, $revision, $body] = $this->fixture();
        $original = $revision->fresh()->snapshot;
        $body['nodes'][0]['title'] = "It's Ballet / Jazz!";
        $body['nodes'][0]['folder_title'] = 'Its Ballet Jazz';
        $first = $this->postJson($this->url($event), $body)->assertOk()->json('data.uuid');
        $body['nodes'][0]['parent_uuid'] = null;
        $this->postJson($this->url($event), $body)->assertOk()->assertJsonPath('data.uuid', $first);
        $this->assertDatabaseCount('programme_upload_plans', 1);
        $this->assertSame($original, $revision->fresh()->snapshot);
        $this->assertSame(1, $programme->fresh()->draft_version);
        $this->assertSame("It's Ballet / Jazz!", ProgrammeUploadPlan::first()->snapshot['nodes'][0]['title']);
    }

    public function test_split_and_combine_preserve_original_ids_in_separate_immutable_plans(): void
    {
        [$event, , , $body] = $this->fixture();
        $first = $this->postJson($this->url($event), $body)->assertOk()->json('data.uuid');
        $body['base_plan_uuid'] = $first;
        $body['nodes'][0]['merged_into_uuid'] = $body['nodes'][1]['uuid'];
        unset($body['nodes'][0]['folder_number'], $body['nodes'][0]['folder_title']);
        $body['nodes'][1]['folder_number'] = '01';
        $body['nodes'][] = ['uuid' => strtoupper((string) Str::uuid()), 'kind' => 'item', 'item_kind' => 'routine',
            'title' => 'New!', 'folder_number' => '02', 'folder_title' => 'New'];
        $this->postJson($this->url($event), $body)->assertOk();
        $this->assertDatabaseCount('programme_upload_plans', 2);
    }

    public function test_first_shared_programme_wins_and_retry_notifies_chat_only_once(): void
    {
        [$event, $programme, , $body, $user] = $this->fixture();
        $first = $this->postJson($this->url($event), $body)->assertOk()->json('data.uuid');
        $this->postJson($this->url($event), $body)->assertOk();
        $this->assertSame(1, $event->messages()->count());
        $this->assertStringContainsString('Programme update (automatic)', $event->messages()->first()->body);
        $this->assertSame($user->id, $event->messages()->first()->author_user_id);
        $body['nodes'][0]['title'] = $body['nodes'][0]['folder_title'] = 'Changed';
        $this->postJson($this->url($event), $body)->assertConflict();
        $this->assertDatabaseCount('programme_upload_plans', 1);
        $this->actingAsApiUser($user, ['programmes:read']);
        $this->getJson(str_replace('/sync', '/shared', $this->url($event)))->assertOk()
            ->assertJsonPath('data.plan.uuid', $first)->assertJsonPath('data.programme.nodes.0.title', 'Dance 1');
    }

    public function test_explicit_update_requires_current_base_and_old_clients_cannot_overwrite_it(): void
    {
        [$event, , , $body] = $this->fixture();
        $original = $body;
        $first = $this->postJson($this->url($event), $body)->assertOk()->json('data.uuid');
        $body['nodes'][0]['title'] = $body['nodes'][0]['folder_title'] = 'New title';
        $body['base_plan_uuid'] = $first;
        $second = $this->postJson($this->url($event), $body)->assertOk()->json('data.uuid');
        $this->postJson($this->url($event), $body)->assertOk()->assertJsonPath('data.uuid', $second);
        $this->postJson($this->url($event), $original)->assertConflict();
        $original['base_plan_uuid'] = $first;
        $this->postJson($this->url($event), $original)->assertConflict();
        $this->assertSame(2, $event->messages()->count());
        $this->assertStringContainsString('Renamed: Dance 1 → New title', $event->messages()->latest('id')->first()->body);
    }

    public function test_shared_programme_read_is_scoped_and_does_not_publish_or_notify(): void
    {
        [$event, , , , $user] = $this->fixture();
        $this->actingAsApiUser($user, ['programmes:read']);
        $url = str_replace('/sync', '/shared', $this->url($event));
        $this->getJson($url)->assertOk()->assertJsonPath('data.plan', null);
        $this->assertSame(0, $event->messages()->count());
        $this->actingAsApiUser(User::factory()->customer()->create(), ['programmes:read']);
        $this->getJson($url)->assertForbidden();
        $this->assertDatabaseCount('programme_upload_plans', 0);
    }

    public function test_rejects_stale_or_foreign_revision_and_cancelled_event(): void
    {
        [$event, $programme, $revision, $body] = $this->fixture();
        $body['revision_uuid'] = (string) Str::uuid();
        $this->postJson($this->url($event), $body)->assertConflict();
        $body['revision_uuid'] = $revision->uuid;
        $programme->revisions()->create(['version' => 2, 'snapshot' => $revision->snapshot, 'confirmed_at' => now()]);
        $this->postJson($this->url($event), $body)->assertConflict();
        $event->update(['cancelled_at' => now()]);
        $this->postJson($this->url($event), $body)->assertForbidden();
        $this->assertDatabaseCount('programme_upload_plans', 0);
    }

    public function test_rejects_removed_ids_alias_cycles_unknown_targets_and_bad_folders(): void
    {
        [$event, , , $body] = $this->fixture();
        $variants = [];
        $removed = $body;
        array_pop($removed['nodes']);
        $variants[] = $removed;
        $cycle = $body;
        foreach ([0, 1] as $i) {
            $cycle['nodes'][$i]['merged_into_uuid'] = $body['nodes'][1 - $i]['uuid'];
            unset($cycle['nodes'][$i]['folder_number'], $cycle['nodes'][$i]['folder_title']);
        }
        $variants[] = $cycle;
        $unknown = $body;
        $unknown['nodes'][0]['merged_into_uuid'] = (string) Str::uuid();
        $variants[] = $unknown;
        $gap = $body;
        $gap['nodes'][1]['folder_number'] = '03';
        $variants[] = $gap;
        $path = $body;
        $path['nodes'][0]['folder_title'] = '../outside';
        $variants[] = $path;
        $duplicate = $body;
        $duplicate['nodes'][1]['uuid'] = strtoupper($body['nodes'][0]['uuid']);
        $variants[] = $duplicate;
        foreach ($variants as $invalid) {
            $this->postJson($this->url($event), $invalid)->assertUnprocessable();
        }
        $this->assertDatabaseCount('programme_upload_plans', 0);
    }

    public function test_requires_both_write_ability_and_administrator_policy(): void
    {
        [$event, , , $body, $user] = $this->fixture();
        $this->actingAsApiUser($user, ['programmes:read']);
        $this->postJson($this->url($event), $body)->assertForbidden();
        $this->actingAsApiUser(User::factory()->customer()->create(), ['concert-media:upload']);
        $this->postJson($this->url($event), $body)->assertForbidden();
        $this->assertDatabaseCount('programme_upload_plans', 0);
    }

    public function test_competition_cannot_be_reorganized_and_server_metadata_is_preserved(): void
    {
        [$event, , $revision, $body] = $this->fixture();
        $day = (string) Str::uuid();
        $section = (string) Str::uuid();
        $nodes = [
            ['uuid' => $day, 'kind' => 'day', 'title' => 'Day', 'date' => '2026-09-04'],
            ['uuid' => $section, 'parent_uuid' => $day, 'kind' => 'section', 'number' => '1', 'title' => 'Ballet'],
            ['uuid' => (string) Str::uuid(), 'parent_uuid' => $section, 'kind' => 'item', 'number' => '1', 'title' => 'Dancer'],
        ];
        $snapshot = $revision->snapshot;
        $snapshot['event_type'] = 'competition';
        $snapshot['nodes'] = $nodes;
        $snapshot['nodes'][2]['source_page'] = 3;
        $revision->update(['snapshot' => $snapshot]);
        $event->update(['event_type' => 'competition']);
        $body['nodes'] = $nodes;
        $this->postJson($this->url($event), $body)->assertOk();
        $this->assertSame(3, ProgrammeUploadPlan::first()->snapshot['nodes'][2]['source_page']);
        $body['nodes'][2]['title'] = 'Changed';
        $this->postJson($this->url($event), $body)->assertUnprocessable();
    }
}
