<?php

namespace App\Features\Bookings\Requests;

use App\Features\Bookings\Support\ConcertBookingItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConcertBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'wants_portrait_photography' => $this->boolean('wants_portrait_photography'),
            'wants_concert_photography' => $this->boolean('wants_concert_photography'),
            'wants_concert_videography' => $this->boolean('wants_concert_videography'),
        ]);
    }

    public function rules(): array
    {
        return [
            'booking_change_uuid' => ['nullable', 'uuid'],
            'resolution_note' => ['required_with:booking_change_uuid', 'nullable', 'string', 'max:5000'],
            'studio_name' => ['required', 'string', 'max:255'], 'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'], 'contact_phone' => ['required', 'string', 'max:50'],
            'portrait_photography_interest' => ['nullable', Rule::in(['yes', 'no', 'unsure'])],
            'wants_portrait_photography' => ['required', 'boolean'], 'wants_concert_photography' => ['required', 'boolean'], 'wants_concert_videography' => ['required', 'boolean'],
            'approximate_family_count' => ['nullable', 'integer', 'min:1', 'max:10000'], 'postal_address' => ['nullable', 'string', 'max:2000'],
            'previous_video_feedback' => ['nullable', 'string', 'max:5000'], 'concert_inclusions' => ['nullable', 'array'], 'concert_inclusions.*' => ['string', 'max:100'],
            'multiple_concert_relationship' => ['nullable', 'string', 'max:255'], 'accepted_requirements' => ['nullable', 'array'], 'accepted_requirements.*' => ['string', 'max:100'],
            'internal_review_note' => ['nullable', 'string', 'max:5000'], 'items' => ['required', 'array', 'min:1'],
            'items.*.uuid' => ['required', 'uuid', 'distinct'], 'items.*.item_type' => ['required', Rule::enum(ConcertBookingItemType::class)],
            'items.*.event_type_definition_id' => ['required', 'integer', Rule::exists('event_type_definitions', 'id')->where('system_category', 'concert')],
            'items.*.title' => ['nullable', 'string', 'max:255'], 'items.*.venue_uuid' => ['required', 'string', 'max:36'],
            'items.*.venue_name' => ['nullable', 'required_if:items.*.venue_uuid,other', 'string', 'max:255'],
            'items.*.venue_address' => ['nullable', 'required_if:items.*.venue_uuid,other', 'string', 'max:1000'],
            'items.*.event_date' => ['required', 'date'], 'items.*.starts_at' => ['required', 'date_format:H:i'],
            'items.*.access_time' => ['nullable', 'date_format:H:i'],
            'items.*.finishes_at' => ['required', 'date_format:H:i'],
        ];
    }
}
