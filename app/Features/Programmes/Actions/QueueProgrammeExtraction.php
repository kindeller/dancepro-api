<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Jobs\ExtractProgramme;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeExtraction;

class QueueProgrammeExtraction
{
    public function execute(Programme $programme): void
    {
        if (! $programme->programme_source_id) {
            return;
        }
        $extraction = ProgrammeExtraction::firstOrNew([
            'programme_id' => $programme->id,
            'programme_source_id' => $programme->programme_source_id,
        ]);
        if ($extraction->exists && in_array($extraction->status, ['queued', 'processing'], true)) {
            return;
        }
        $extraction->fill([
            'draft_version' => $programme->draft_version,
            'status' => 'queued',
            'result' => null,
            'message' => null,
        ])->save();
        ExtractProgramme::dispatch($extraction->id)->onConnection('programmes')->onQueue('programmes')->afterCommit();
    }
}
