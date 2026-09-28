<?php

namespace App\Features\Tracking\Requests;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Services\ConcertEditing;
use Illuminate\Foundation\Http\FormRequest;

class DownloadConcertEditingFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('schedulingEvent');
        $user = $this->user();

        return $user && $event instanceof SchedulingEvent && $event->event_type->value === 'concert'
            && ($user->canAccessAdmin() || app(ConcertEditing::class)->canEdit($user, $event));
    }

    public function rules(): array
    {
        return [];
    }
}
