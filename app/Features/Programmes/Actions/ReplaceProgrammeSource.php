<?php

namespace App\Features\Programmes\Actions;

use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Services\ProgrammeSourceStorage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplaceProgrammeSource
{
    public function __construct(private readonly ProgrammeSourceStorage $sources) {}

    public function execute(Programme $programme, User $user, int $version, ?UploadedFile $file = null): void
    {
        DB::transaction(function () use ($programme, $user, $version, $file): void {
            $programme = Programme::whereKey($programme->id)->lockForUpdate()->firstOrFail();
            abort_unless($programme->draft_version === $version, 409, 'This programme changed. Reload before uploading.');
            $source = $this->sources->capture($programme, $user, $file);
            if (! $source) {
                throw ValidationException::withMessages(['programme' => 'No attached PDF is available. Upload a PDF to prepare the programme.']);
            }
            $programme->update(['programme_source_id' => $source->id, 'draft_version' => $version + 1, 'updated_by_user_id' => $user->id]);
            app(QueueProgrammeExtraction::class)->execute($programme);
        });
    }
}
