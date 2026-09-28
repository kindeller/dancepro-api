<?php

namespace App\Features\Tracking\Requests;

use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Services\BookingTrackerBar;
use App\Features\Tracking\Services\ConcertEditing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConcertEditingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('schedulingEvent');

        return $this->user() && $event instanceof SchedulingEvent
            && app(ConcertEditing::class)->canEdit($this->user(), $event);
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return [];
        }
        $status = $this->input('panel') === 'status';

        return [
            'panel' => ['required', Rule::in(['status', 'follow_up'])],
            'status_override' => [Rule::excludeIf(! $status), 'required', Rule::in(['not_started', 'in_progress', 'waiting', 'complete'])],
            'follow_up_status' => [Rule::excludeIf($status), 'required', Rule::in(array_keys(BookingTrackerBar::FOLLOW_UP_STATUSES))],
            'follow_up_date' => [Rule::excludeIf($status), 'present', 'nullable', 'date_format:Y-m-d'],
            'note' => [Rule::excludeIf($status), 'nullable', 'string', 'max:3000'],
        ];
    }
}
