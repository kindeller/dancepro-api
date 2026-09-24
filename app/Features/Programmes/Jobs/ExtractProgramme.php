<?php

namespace App\Features\Programmes\Jobs;

use App\Features\Programmes\Actions\SaveProgrammeDraft;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeExtraction;
use App\Features\Programmes\Models\ProgrammeSource;
use App\Features\Programmes\Services\ProgrammePdfExtractor;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExtractProgramme implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $extractionId) {}

    public function handle(ProgrammePdfExtractor $extractor, SaveProgrammeDraft $save): void
    {
        $extraction = ProgrammeExtraction::findOrFail($this->extractionId);
        if ($extraction->status !== 'queued') {
            return;
        }
        $extraction->update(['status' => 'processing']);
        try {
            $programme = Programme::findOrFail($extraction->programme_id);
            $source = ProgrammeSource::findOrFail($extraction->programme_source_id);
            $result = $extractor->extract($source, $programme->event->event_type->value, $programme->event->event_date?->year);
            DB::transaction(function () use ($extraction, $result, $save): void {
                $programme = Programme::whereKey($extraction->programme_id)->lockForUpdate()->firstOrFail();
                $items = collect($result['nodes'])->where('kind', 'item')->count();
                $user = User::find($programme->updated_by_user_id);
                $canApply = $user && $user->canAccessAdmin() && ! $programme->event->cancelled_at
                    && $programme->programme_source_id === $extraction->programme_source_id
                    && $programme->draft_version === $extraction->draft_version
                    && ! $programme->nodes()->where('is_active', true)->where('kind', 'item')->exists();
                if ($items && $canApply) {
                    $save->execute($programme, $user, $programme->draft_version, $result['nodes']);
                }
                $extraction->update([
                    'status' => $items ? ($canApply ? 'ready' : 'review') : 'empty',
                    'result' => $result,
                    'message' => $items ? ($canApply ? "$items entries extracted. Review them beside the PDF, then approve the programme." : "$items entries extracted. Your existing running order was kept. Compare the extracted suggestions below before making changes.")
                        : 'No running order could be recognised. Your PDF is safe; enter the items manually or try another PDF.',
                ]);
            });
        } catch (Throwable $exception) {
            $this->failed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ProgrammeExtraction::whereKey($this->extractionId)->update([
            'status' => 'failed',
            'message' => 'Automatic extraction could not finish. Your PDF and running order are safe. Try another PDF or enter the items manually.',
        ]);
    }
}
