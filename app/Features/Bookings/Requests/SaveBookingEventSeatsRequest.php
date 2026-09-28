<?php

namespace App\Features\Bookings\Requests;

use App\Features\Studios\Requests\SaveConcertEventSeatsRequest;

class SaveBookingEventSeatsRequest extends SaveConcertEventSeatsRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }
}
