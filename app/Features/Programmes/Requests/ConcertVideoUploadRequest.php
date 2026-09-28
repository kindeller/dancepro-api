<?php

namespace App\Features\Programmes\Requests;

use App\Features\Tracking\Services\ConcertEditing;
use Illuminate\Foundation\Http\FormRequest;

class ConcertVideoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('schedulingEvent');
        $user = $this->user();

        return $user && $event->event_type->value === 'concert' && ! $event->cancelled_at
            && ($user->canAccessAdmin() || app(ConcertEditing::class)->canEdit($user, $event));
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'begin' => [
                'batch_uuid' => ['required', 'uuid'],
                'files' => ['required', 'array', 'min:1', 'max:500'],
                'files.*.file_uuid' => ['required', 'uuid', 'distinct'],
                'files.*.file_name' => ['required', 'string', 'max:240', 'distinct', 'regex:/^[^\\\\\/\x00-\x1F]+\.mp4$/i'],
                'files.*.byte_count' => ['required', 'integer', 'min:1', 'max:5368709120'],
                'files.*.sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            ],
            'complete' => ['all_items_included' => ['required', 'accepted']],
            default => [],
        };
    }
}
