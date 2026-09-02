<?php

namespace App\Features\Bookings\Requests;

use App\Features\Bookings\Support\ConcertBookingItemType;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\Venues\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConcertBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $defaultEventTypeId = EventTypeDefinition::query()->where('code', 'concert')->value('id');
        $items = collect($this->input('items', []))
            ->filter(fn ($item) => filled($item['event_date'] ?? null) || filled($item['title'] ?? null) || filled($item['venue_uuid'] ?? null))
            ->map(function (array $item) use ($defaultEventTypeId): array {
                $item['event_type_definition_id'] ??= $defaultEventTypeId;

                return $item;
            })
            ->values()->all();
        $this->merge([
            'wants_portrait_photography' => $this->input('portrait_photography_interest') === 'yes',
            'wants_concert_photography' => $this->boolean('wants_concert_photography'),
            'wants_concert_videography' => $this->boolean('wants_concert_videography'),
            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        $videoFieldPresence = $this->boolean('wants_concert_videography') ? ['required'] : ['nullable'];

        return [
            'studio_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'wants_portrait_photography' => ['required', 'boolean'],
            'portrait_photography_interest' => ['required', Rule::in(['yes', 'no', 'unsure'])],
            'wants_concert_photography' => ['required', 'boolean'],
            'wants_concert_videography' => ['required', 'boolean'],
            'approximate_family_count' => [...$videoFieldPresence, 'integer', 'min:1', 'max:10000'],
            'postal_address' => [...$videoFieldPresence, 'string', 'max:2000'],
            'previous_video_feedback' => [...$videoFieldPresence, 'string', 'max:5000'],
            'accepted_requirements' => ['required', 'array', 'min:1'],
            'accepted_requirements.*' => ['string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.item_type' => ['required', Rule::enum(ConcertBookingItemType::class)],
            'items.*.event_type_definition_id' => ['required', 'integer', Rule::exists('event_type_definitions', 'id')->where('system_category', 'concert')->where('is_active', true)],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.venue_uuid' => ['required', 'string', 'max:36'],
            'items.*.venue_name' => ['nullable', 'required_if:items.*.venue_uuid,other', 'string', 'max:255'],
            'items.*.venue_address' => ['nullable', 'required_if:items.*.venue_uuid,other', 'string', 'max:1000'],
            'items.*.event_date' => ['required', 'date', 'after_or_equal:today'],
            'items.*.starts_at' => ['required', 'date_format:H:i'],
            'items.*.finishes_at' => ['required', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $required = ['accurate'];
            if ($this->input('portrait_photography_interest') === 'yes') {
                $required = [...$required, 'portrait_space', 'no_personal_backdrop_photos'];
            }
            if ($this->boolean('wants_concert_photography')) {
                $required = [...$required, 'minimum_spend', 'photographer_seat', 'no_audience_recording', 'promote_gallery', 'credit_images'];
            }
            if ($this->boolean('wants_concert_videography')) {
                $required = [...$required, 'video_access', 'video_space_audio', 'programme_invoice'];
            }

            $accepted = $this->input('accepted_requirements', []);
            foreach (array_diff($required, $accepted) as $missing) {
                $validator->errors()->add('accepted_requirements', "Please accept the {$missing} booking requirement.");
            }

            if (! $this->boolean('wants_concert_photography') && ! $this->boolean('wants_concert_videography') && $this->input('portrait_photography_interest') === 'no') {
                $validator->errors()->add('services', 'Please select at least one service or request portrait information.');
            }

            foreach ($this->input('items', []) as $index => $item) {
                $venueUuid = $item['venue_uuid'] ?? null;
                if ($venueUuid !== 'other' && ! Venue::query()->where('uuid', $venueUuid)->exists()) {
                    $validator->errors()->add("items.{$index}.venue_uuid", 'Please select a valid venue.');
                }
            }
        }];
    }
}
