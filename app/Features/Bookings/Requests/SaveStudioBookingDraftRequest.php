<?php

namespace App\Features\Bookings\Requests;

use App\Features\Customers\Services\StudioCommunicationHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudioBookingDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(StudioCommunicationHistory::class)->canView($this->user(), $this->route('studio'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'wants_concert_photography' => $this->boolean('wants_concert_photography'),
            'wants_concert_videography' => $this->boolean('wants_concert_videography'),
        ]);
    }

    public function rules(): array
    {
        return [
            'draft_uuid' => ['nullable', 'uuid'], 'draft_version' => ['nullable', 'integer', 'min:0'],
            'studio_name' => ['nullable', 'string', 'max:255'], 'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'max:255'], 'contact_phone' => ['nullable', 'string', 'max:50'],
            'postal_address' => ['nullable', 'string', 'max:2000'], 'previous_video_feedback' => ['nullable', 'string', 'max:5000'],
            'approximate_family_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'wants_concert_photography' => ['boolean'], 'wants_concert_videography' => ['boolean'],
            'accepted_requirements' => ['nullable', 'array', 'max:30'], 'accepted_requirements.*' => ['string', 'max:100'],
            'items' => ['nullable', 'array', 'max:20'],
            'items.*' => ['array:item_type,event_type_definition_id,title,venue_uuid,venue_search,venue_name,venue_address,event_date,starts_at,finishes_at'],
            'items.*.item_type' => ['nullable', Rule::in(['concert', 'dress_rehearsal'])],
            'items.*.event_type_definition_id' => ['nullable', 'integer'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.venue_uuid' => ['nullable', 'string', 'max:36'],
            'items.*.venue_search' => ['nullable', 'string', 'max:255'],
            'items.*.venue_name' => ['nullable', 'string', 'max:255'], 'items.*.venue_address' => ['nullable', 'string', 'max:1000'],
            'items.*.event_date' => ['nullable', 'string', 'max:10'],
            'items.*.starts_at' => ['nullable', 'string', 'max:5'], 'items.*.finishes_at' => ['nullable', 'string', 'max:5'],
        ];
    }
}
