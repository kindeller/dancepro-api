<?php

namespace App\Features\Tracking\Requests;

use App\Features\Tracking\Services\BookingTrackerBar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskTrackerBarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    public function rules(): array
    {
        $panel = $this->input('panel');

        return [
            'panel' => ['required', Rule::in(['status', 'due', 'assignment', 'follow_up'])],
            'status_override' => [Rule::excludeIf($panel !== 'status'), 'present', 'nullable', Rule::in([...array_keys(BookingTrackerBar::STATUSES), 'approved'])],
            'due_date_override' => [Rule::excludeIf($panel !== 'due'), 'present', 'nullable', 'date_format:Y-m-d'],
            'assignee_user_id' => [Rule::excludeIf($panel !== 'assignment'), 'present', 'nullable', 'integer', function ($attribute, $value, $fail): void {
                if (! app(BookingTrackerBar::class)->assignees()->whereKey($value)->exists()) {
                    $fail('Choose an active staff or crew member.');
                }
            }],
            'follow_up_status' => [Rule::excludeIf($panel !== 'follow_up'), 'required', Rule::in(array_keys(BookingTrackerBar::FOLLOW_UP_STATUSES))],
            'follow_up_date' => [Rule::excludeIf($panel !== 'follow_up'), 'present', 'nullable', 'date_format:Y-m-d'],
            'note' => [Rule::excludeIf($panel !== 'follow_up'), 'nullable', 'string', 'max:3000'],
        ];
    }
}
