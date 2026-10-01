<?php

namespace Tests\Feature\Programmes;

use App\Features\Programmes\Actions\PrepareProgramme;
use App\Features\Programmes\Actions\SaveProgrammeDraft;
use App\Features\Programmes\Jobs\ExtractProgramme;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeExtraction;
use App\Features\Programmes\Services\ProgrammePdfExtractor;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProgrammeExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        config(['operations.filesystem_disk' => 'local', 'operations.malware_scan.enabled' => false]);
    }

    public function test_upload_starts_preparation_and_extraction_without_a_separate_start_step(): void
    {
        $user = User::factory()->staff()->create();
        $event = $this->event();
        $this->actingAs($user)->post($this->url($event), ['programme' => $this->pdf()])->assertRedirect();
        $programme = Programme::firstOrFail();
        $this->assertNotNull($programme->programme_source_id);
        $this->assertDatabaseHas('programme_extractions', ['programme_id' => $programme->id, 'status' => 'queued']);
        Queue::assertPushed(ExtractProgramme::class, fn ($job) => $job->connection === 'programmes' && $job->queue === 'programmes');
        $this->get($this->url($event))->assertOk()->assertSee('Reading your programme');
        $this->getJson($this->url($event).'/extraction')->assertOk()->assertJsonPath('data.status', 'queued');
    }

    public function test_job_populates_the_empty_draft_but_does_not_approve_it(): void
    {
        [$programme, $extraction] = $this->prepare();
        $nodes = $this->nodes();
        $this->mock(ProgrammePdfExtractor::class, fn (MockInterface $mock) => $mock->shouldReceive('extract')->once()->andReturn(['nodes' => $nodes, 'issues' => ['Check OCR names.']]));
        app()->call([new ExtractProgramme($extraction->id), 'handle']);
        $this->assertSame('ready', $extraction->fresh()->status);
        $this->assertEquals($nodes, $programme->fresh()->draftNodes());
        $this->assertSame(2, $programme->fresh()->draft_version);
        $this->assertDatabaseCount('programme_revisions', 0);
        app()->call([new ExtractProgramme($extraction->id), 'handle']);
        $this->assertSame(2, $programme->fresh()->draft_version);
    }

    public function test_reread_populates_an_empty_draft_while_preserving_confirmed_history(): void
    {
        [$programme, $extraction, $user] = $this->prepare();
        $programme->revisions()->create([
            'version' => 1,
            'programme_source_id' => $programme->programme_source_id,
            'snapshot' => ['event_type' => 'concert', 'nodes' => [['title' => 'Historical item']]],
            'confirmed_by_user_id' => $user->id,
            'confirmed_at' => now()->subDay(),
        ]);
        $nodes = $this->nodes();
        $this->mock(ProgrammePdfExtractor::class, fn (MockInterface $mock) => $mock->shouldReceive('extract')->once()->andReturn(['nodes' => $nodes, 'issues' => []]));

        app()->call([new ExtractProgramme($extraction->id), 'handle']);

        $this->assertSame('ready', $extraction->fresh()->status);
        $this->assertEquals($nodes, $programme->fresh()->draftNodes());
        $this->assertDatabaseHas('programme_revisions', ['programme_id' => $programme->id, 'version' => 1]);
    }

    public function test_studio_uploaded_source_populates_a_draft_that_staff_can_edit_without_approving(): void
    {
        $studioUser = User::factory()->customer()->create();
        $programme = app(PrepareProgramme::class)->execute($this->event(), $studioUser, $this->pdf());
        $extraction = ProgrammeExtraction::firstOrFail();
        $nodes = $this->nodes();
        $this->mock(ProgrammePdfExtractor::class, fn (MockInterface $mock) => $mock->shouldReceive('extract')->once()->andReturn(['nodes' => $nodes, 'issues' => []]));

        app()->call([new ExtractProgramme($extraction->id), 'handle']);

        $this->assertSame('ready', $extraction->fresh()->status);
        $this->assertEquals($nodes, $programme->fresh()->draftNodes());
        $this->assertDatabaseCount('programme_revisions', 0);
        $this->actingAs($studioUser)->putJson(route('admin.programmes.save', $programme->event), ['version' => 2, 'nodes' => $nodes])->assertForbidden();
        $staff = User::factory()->staff()->create();
        $nodes[0]['title'] = 'Corrected title';
        $this->actingAs($staff)->putJson(route('admin.programmes.save', $programme->event), ['version' => 2, 'nodes' => $nodes])->assertOk();
        $this->assertSame('Corrected title', $programme->fresh()->draftNodes()[0]['title']);
        $this->assertDatabaseCount('programme_revisions', 0);
    }

    public function test_job_keeps_concurrent_edits_and_exposes_suggestions_for_review(): void
    {
        [$programme, $extraction, $user] = $this->prepare();
        $original = $this->nodes();
        app(SaveProgrammeDraft::class)->execute($programme, $user, 1, $original);
        $this->mock(ProgrammePdfExtractor::class, fn (MockInterface $mock) => $mock->shouldReceive('extract')->andReturn(['nodes' => $this->nodes(), 'issues' => []]));
        app()->call([new ExtractProgramme($extraction->id), 'handle']);
        $this->assertSame('review', $extraction->fresh()->status);
        $this->assertEquals($original, $programme->fresh()->draftNodes());
        $this->actingAs($user)->get($this->url($programme->event))->assertOk()->assertSee('View extracted suggestions');
    }

    public function test_failed_extraction_retains_source_and_allows_manual_entry(): void
    {
        [$programme, $extraction] = $this->prepare();
        $this->mock(ProgrammePdfExtractor::class, fn (MockInterface $mock) => $mock->shouldReceive('extract')->andThrow(new \RuntimeException('private-path-do-not-display')));
        app()->call([new ExtractProgramme($extraction->id), 'handle']);
        $this->assertSame('failed', $extraction->fresh()->status);
        $this->assertStringNotContainsString('private-path', $extraction->fresh()->message);
        Storage::disk('local')->assertExists($programme->source->path);
        $this->assertSame([], $programme->draftNodes());
    }

    public function test_completed_extraction_can_be_queued_again_for_the_same_pdf(): void
    {
        [$programme, $extraction, $user] = $this->prepare();
        $extraction->update(['status' => 'ready', 'result' => ['nodes' => $this->nodes()], 'message' => 'Complete']);
        Queue::fake();

        $this->actingAs($user)->post($this->url($programme->event))->assertRedirect();

        $extraction->refresh();
        $this->assertSame('queued', $extraction->status);
        $this->assertNull($extraction->result);
        $this->assertNull($extraction->message);
        Queue::assertPushed(ExtractProgramme::class);
    }

    public function test_cancelled_event_cannot_receive_extracted_rows(): void
    {
        [$programme, $extraction] = $this->prepare();
        $programme->event->update(['cancelled_at' => now()]);
        $this->mock(ProgrammePdfExtractor::class, fn (MockInterface $mock) => $mock->shouldReceive('extract')->andReturn(['nodes' => $this->nodes(), 'issues' => []]));
        app()->call([new ExtractProgramme($extraction->id), 'handle']);
        $this->assertSame('review', $extraction->fresh()->status);
        $this->assertSame([], $programme->draftNodes());
    }

    public function test_upload_validation_and_progress_access_are_protected(): void
    {
        $event = $this->event();
        $this->getJson($this->url($event).'/extraction')->assertUnauthorized();
        $user = User::factory()->staff()->create();
        $this->actingAs($user)->postJson($this->url($event), ['programme' => UploadedFile::fake()->create('not-pdf.txt')])->assertUnprocessable();
        $this->assertDatabaseCount('programmes', 0);
        $event->update(['cancelled_at' => now()]);
        $this->post($this->url($event), ['programme' => $this->pdf()])->assertForbidden();
    }

    public function test_real_pdf_extractor_pairs_columns_and_preserves_number_resets_and_wrapping(): void
    {
        [$programme] = $this->prepare();
        $pdf = new Dompdf;
        $pdf->loadHtml('<html><body><h1>ACT 1</h1><table style="width:100%;font-size:18px"><tr><td style="width:50%">1. Opening</td><td>Senior Jazz</td></tr><tr><td>3. Long title<br>continued here</td><td>Teen Ballet</td></tr></table><div style="page-break-before:always"></div><h1>ACT 2</h1><table style="width:100%;font-size:18px"><tr><td style="width:50%">1. Finale</td><td>Everyone</td></tr></table></body></html>');
        $pdf->render();
        Storage::disk('local')->put($programme->source->path, $pdf->output());
        $progress = [];
        $result = app(ProgrammePdfExtractor::class)->extract($programme->source, 'concert', null, function ($page, $total, $ocr) use (&$progress): void {
            $progress[] = [$page, $total, $ocr];
        });
        // The short second page uses the existing OCR fallback (<40 characters).
        $this->assertSame([[1, 2, false], [2, 2, true]], $progress);
        $items = collect($result['nodes'])->where('kind', 'item')->values();
        $this->assertSame(['1', '3', '1'], $items->pluck('number')->all());
        $this->assertSame(['Opening', 'Long title continued here', 'Finale'], $items->pluck('title')->all());
        $this->assertSame(['Senior Jazz', 'Teen Ballet', 'Everyone'], $items->pluck('class_name')->all());
        $this->assertNotSame($items[0]['parent_uuid'], $items[2]['parent_uuid']);
        $this->assertSame([1, 1, 2], $items->pluck('source_page')->all());
    }

    public function test_synthetic_layout_rules(): void
    {
        $process = new Process(['python3', '-m', 'unittest', 'discover', '-s', 'tests/Unit/Programmes', '-p', 'test_*.py'], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_progress_distinguishes_queue_waits_and_recovers_a_stopped_reader(): void
    {
        [$programme, $extraction, $user] = $this->prepare();
        $extraction->update(['updated_at' => now()->subMinutes(2)]);
        $this->actingAs($user)->getJson($this->url($programme->event).'/extraction')->assertOk()
            ->assertJsonPath('data.status', 'queued')->assertJsonPath('data.message', 'Still waiting for the programme reader to start. The worker may be busy or unavailable; your PDF is saved. This page will keep checking.');
        $extraction->update(['status' => 'processing', 'message' => 'Reading scanned page 2 of 5…']);
        $this->getJson($this->url($programme->event).'/extraction')->assertJsonPath('data.message', 'Reading scanned page 2 of 5…');
        $extraction->update(['updated_at' => now()->subMinutes(5)]);
        $this->getJson($this->url($programme->event).'/extraction')->assertJsonPath('data.status', 'failed');
        $this->get($this->url($programme->event))->assertOk()->assertSee('Read PDF again');
        $this->post($this->url($programme->event))->assertRedirect();
        $this->assertSame('queued', $extraction->fresh()->status);
        Storage::disk('local')->assertExists($programme->source->path);
    }

    private function event(): SchedulingEvent
    {
        return SchedulingEvent::create(['name' => 'Example concert', 'event_type' => 'concert', 'event_date' => '2026-09-21']);
    }

    private function prepare(): array
    {
        $user = User::factory()->staff()->create();
        $programme = app(PrepareProgramme::class)->execute($this->event(), $user, $this->pdf());

        return [$programme, ProgrammeExtraction::firstOrFail(), $user];
    }

    private function nodes(): array
    {
        return [['uuid' => (string) Str::uuid(), 'parent_uuid' => null, 'kind' => 'item', 'title' => 'Teen Ballet', 'number' => '1', 'source_page' => 1, 'item_kind' => 'dance']];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('programme.pdf', "%PDF-1.4\n%%EOF");
    }

    private function url(SchedulingEvent $event): string
    {
        return '/admin/scheduling-events/'.$event->uuid.'/programme';
    }
}
