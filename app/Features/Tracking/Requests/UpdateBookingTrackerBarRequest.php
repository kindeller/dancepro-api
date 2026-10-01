<?php

namespace App\Features\Tracking\Requests;

use Illuminate\Validation\Rule;

class UpdateBookingTrackerBarRequest extends UpdateTaskTrackerBarRequest
{
    public function rules(): array
    {
        $panel = $this->input('panel');

        return array_replace(parent::rules(), [
            'status_override' => [Rule::excludeIf($panel !== 'status'), 'required', Rule::in(['pending', 'received', 'approved', 'partial_approval', 'cancelled'])],
            'studio_uuid' => [Rule::excludeIf($panel !== 'status' || ! in_array($this->input('status_override'), ['approved', 'partial_approval'], true)), 'nullable', 'uuid', Rule::exists('studios', 'uuid')->whereNull('deleted_at')],
            'event_ids' => [Rule::excludeIf($panel !== 'status' || $this->input('status_override') !== 'partial_approval'), 'required', 'array', 'min:1'],
            'event_ids.*' => ['uuid', 'distinct'],
            'reason' => [Rule::excludeIf($panel !== 'status' || $this->input('status_override') !== 'cancelled'), 'required', 'string', 'max:3000'],
        ]);
    }
}
