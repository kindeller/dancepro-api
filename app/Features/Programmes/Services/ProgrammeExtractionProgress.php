<?php

namespace App\Features\Programmes\Services;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeExtraction;

class ProgrammeExtractionProgress
{
    public function forProgramme(?Programme $programme): ?ProgrammeExtraction
    {
        if (! $programme) {
            return null;
        }
        $extraction = ProgrammeExtraction::where('programme_id', $programme->id)->where('programme_source_id', $programme->programme_source_id)->first();
        if ($extraction?->status === 'processing' && $extraction->updated_at->lt(now()->subMinutes(4))) {
            ProgrammeExtraction::whereKey($extraction->id)->where('status', 'processing')->where('updated_at', $extraction->updated_at)->update([
                'status' => 'failed', 'message' => 'The reader stopped before finishing. Your PDF and saved rows are safe. Use Read PDF again to retry, or continue manually.',
            ]);
            $extraction->refresh();
        }
        if ($extraction?->status === 'queued') {
            $extraction->message = $extraction->updated_at->lt(now()->subMinute())
                ? 'Still waiting for the programme reader to start. The worker may be busy or unavailable; your PDF is saved. This page will keep checking.'
                : 'Waiting for the programme reader to start. This page will update automatically.';
        }

        return $extraction;
    }
}
