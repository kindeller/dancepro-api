<?php

namespace App\Features\Admin\Requests;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Support\ConcertStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveConcertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageConcerts') ?? false;
    }

    public function rules(): array
    {
        /** @var Concert|null $concert */
        $concert = $this->route('concert');

        return [
            'studio_id' => ['required', 'integer', Rule::exists('studios', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('concerts', 'slug')->ignore($concert)],
            'status' => ['required', Rule::enum(ConcertStatus::class)],
            'event_date' => ['nullable', 'date'],
            'event_end_date' => ['nullable', 'date'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_enabled' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'is_approved' => ['nullable', 'boolean'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date'],
            'program_url' => ['nullable', 'url:http,https', 'max:2048'],
            'external_gallery_url' => ['nullable', 'url:http,https', 'max:2048'],
            'access_password' => ['nullable', 'string', 'min:6', 'max:255'],
            'clear_access_password' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->hasAny(['event_date', 'event_end_date'])
                    && $this->filled(['event_date', 'event_end_date'])
                    && Carbon::parse($this->input('event_end_date'))->isBefore(Carbon::parse($this->input('event_date')))) {
                    $validator->errors()->add('event_end_date', 'The event end date must be on or after the event date.');
                }

                if (! $validator->errors()->hasAny(['available_from', 'available_until'])
                    && $this->filled(['available_from', 'available_until'])
                    && Carbon::parse($this->input('available_until'))->lessThanOrEqualTo(Carbon::parse($this->input('available_from')))) {
                    $validator->errors()->add('available_until', 'The availability end must be after the availability start.');
                }
            },
        ];
    }
}
