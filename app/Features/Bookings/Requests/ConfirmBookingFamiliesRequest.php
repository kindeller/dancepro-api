<?php

namespace App\Features\Bookings\Requests;

use App\Features\Bookings\Services\StudioBookingPlans;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmBookingFamiliesRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('admin.*')) {
            return $this->user()?->can('manageScheduling') ?? false;
        }
        app(StudioBookingPlans::class)->receipt($this->user(), $this->route('studio'), $this->route('concertBooking'));

        return true;
    }

    public function rules(): array
    {
        return ['final_family_count' => ['required', 'integer', 'min:1', 'max:10000'], 'confirm_unique_families' => ['accepted']];
    }
}
