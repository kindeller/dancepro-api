<?php

namespace App\Features\Studios\Actions;

use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Programmes\Actions\PrepareProgramme;
use App\Features\Programmes\Actions\ReplaceProgrammeSource;
use App\Features\Programmes\Models\Programme;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\StudioEventFile;
use App\Features\Tracking\Models\ConcertTrackerAlert;
use App\Features\Tracking\Models\ConcertTrackerTask;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class StoreStudioEventFiles
{
    public function __construct(private readonly OperationsFileStorage $storage) {}

    /** @param list<UploadedFile> $files */
    public function execute(SchedulingEvent $event, User $user, string $kind, array $files): void
    {
        foreach ($files as $file) {
            $this->store($event, $user, $kind, $file);
        }
    }

    private function store(SchedulingEvent $event, User $user, string $kind, UploadedFile $file): void
    {
        $path = $this->storage->store($file, 'studio-event-files/'.$event->uuid.'/'.$kind);
        try {
            DB::transaction(function () use ($event, $user, $kind, $file, $path): void {
                if ($kind === 'programme') {
                    StudioEventFile::where('scheduling_event_id', $event->id)->where('kind', 'programme')->where('is_current', true)
                        ->update(['is_current' => false, 'status' => 'superseded']);
                }
                $isPdf = $file->getMimeType() === 'application/pdf' || strtolower($file->getClientOriginalExtension()) === 'pdf';
                StudioEventFile::create([
                    'scheduling_event_id' => $event->id, 'kind' => $kind,
                    'status' => $kind === 'programme' && ! $isPdf ? 'needs_conversion' : 'received',
                    'is_current' => true, 'disk' => $this->storage->diskName(), 'storage_key' => $path,
                    'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(),
                    'uploaded_by_user_id' => $user->id, 'uploaded_at' => now(),
                ]);
                if ($kind === 'programme') {
                    $item = $event->concertBookingItem;
                    if ($item) {
                        $item->update(['programme_path' => $path, 'programme_name' => basename($file->getClientOriginalName()), 'programme_mime' => $file->getMimeType(), 'programme_size' => $file->getSize()]);
                    }
                    $event->update(['programme_path' => $path]);
                } else {
                    ConcertTrackerTask::updateOrCreate(
                        ['scope_type' => 'event', 'scope_id' => $event->id, 'task_key' => 'slideshow_received'],
                        ['status' => 'in_progress'],
                    );
                }
            });
        } catch (Throwable $exception) {
            $this->storage->disk()->delete($path);
            throw $exception;
        }

        if ($kind === 'programme' && ($file->getMimeType() === 'application/pdf' || strtolower($file->getClientOriginalExtension()) === 'pdf')) {
            $programme = Programme::where('scheduling_event_id', $event->id)->first();
            $programme
                ? app(ReplaceProgrammeSource::class)->execute($programme, $user, $programme->draft_version, $file)
                : app(PrepareProgramme::class)->execute($event, $user, $file);
        }
        $stored = StudioEventFile::where('storage_key', $path)->firstOrFail();
        foreach (User::query()->where('is_admin', true)->where('is_active', true)->get() as $admin) {
            ConcertTrackerAlert::create([
                'user_id' => $admin->id, 'scheduling_event_id' => $event->id, 'type' => 'studio_file_received',
                'dedupe_key' => 'studio_file_received:'.$stored->uuid,
                'title' => $kind === 'programme' ? 'Studio programme received' : 'Studio video received',
                'body' => $event->name.' submitted '.$stored->original_name.'.',
                'url' => route('admin.scheduling-events.show', $event),
            ]);
        }
    }
}
